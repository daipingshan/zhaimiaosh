<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/7/7 0007
 * Time: 下午 1:21
 */

namespace Common\Org;

class DaTaoKe {

    /**
     * 大淘客app_key
     *
     * @var string
     */
    private $app_key = "4frqyegyob";

    /**
     * @var string
     */
    private $item_url = "http://api.dataoke.com/index.php?r=port/index&appkey=ppp&v=2&id=ddd";

    /**
     * @var string
     */
    private $more_url = "http://api.dataoke.com/index.php?r=Port/index&type=total&appkey=ppp&v=2&page=xxx";

    /**
     * @var string
     */
    private $top_url = "http://api.dataoke.com/index.php?r=Port/index&type=paoliang&appkey=ppp&v=2";

    /**
     * 获取商品图文详情
     *
     * @var string
     */
    private $desc_url = "http://hws.m.taobao.com/cache/mtop.wdetail.getItemDescx/4.1/?data=%7B%22item_num_id%22%3A%22ppp%22%7D";

    /**
     * @var null
     */
    private $httpObj = null;

    /**
     * 全网搜索商品地址
     *
     * @var string
     */
    private $search_url = "http://pub.alimama.com/items/search.json?q=%s&_t=%s&auctionTag=&toPage=%s&perPageSize=%s&shopTag=yxjh&t=%s&_tb_token_=&pvid=10_49.221.62.102_4720_1496801283153";

    /**
     * @var string
     */
    private $pid = "mm_121610813_22448587_79916379";

    /**
     * 构造函数
     * AliApi constructor.
     */
    public function __construct() {
        $this->httpObj = new \Common\Org\Http();
    }

    /**
     * @param $goods_id
     * @return bool|\mix|mixed|string
     */
    public function getItem($goods_id) {
        if (!$goods_id) {
            return false;
        }
        $url  = str_replace('ppp', $this->app_key, $this->item_url);
        $url  = str_replace('ddd', $goods_id, $url);
        $res  = $this->httpObj->get($url);
        $data = json_decode($res, true);
        if (!$data) {
            return false;
        }
        return $data;
    }

    /**
     * @param $page
     * @return array
     */
    public function getMoreData($page) {
        $url    = str_replace('ppp', $this->app_key, $this->more_url);
        $url    = str_replace('xxx', $page, $url);
        $res    = $this->httpObj->get($url);
        $result = json_decode($res, true);
        $data   = array();
        if (isset($result['result']) && $result['result']) {
            foreach ($result['result'] as $v) {
                $data[] = $this->getItemData($v);
            }
        }
        return $data;
    }

    /**
     * top 100
     *
     * @return array
     */
    public function getTopData() {
        $url    = str_replace('ppp', $this->app_key, $this->top_url);
        $res    = $this->httpObj->get($url);
        $result = json_decode($res, true);
        $data   = array();
        if (isset($result['result']) && $result['result']) {
            foreach ($result['result'] as $v) {
                $data[] = $this->getItemData($v);
            }
        }
        return $data;
    }

    /**
     * @param      $v
     * @param bool $status
     * @return array
     */
    public function getItemData($v, $status = false) {
        if ($status == true) {
            $order = $v['ordid'];
        } else {
            $order = 9999;
        }
        $pass            = 1;
        $coupon_end_time = strtotime($v['Quan_time']);
        $commission      = round(($v['Price'] * $v['Commission'] / 100), 2);
        //设置9.9 包邮
        if ($v['Price'] <= 9.9) {
            $v['coupon_type'] = 4;
        }
        $click_url = 'https://uland.taobao.com/coupon/edetail?activityId=' . $v['Quan_id'] . '&pid=' . $this->pid . '&itemId=' . $v['GoodsID'] . '&src=cd_cdll';
        if ($v['Commission_queqiao'] > $v['Commission_jihua']) {
            $uname      = "queqiao";
            $commission = $commission * 0.85;
        } else {
            if ($v['Jihua_link'] === 0 || $v['Jihua_link'] == "") {
                $uname = "tongyong";
            } else {
                $uname = "wfa";
            }
        }
        switch ($v['Cid']) {
            case 1:
                //女装
                $cat_id = 20;
                break;
            case 2:
                //母婴
                $cat_id = 21;
                break;
            case 3:
                //美妆
                $cat_id = 22;
                break;
            case 4:
                //家居
                $cat_id = 23;
                break;
            case 5:
                //鞋包
                $cat_id = 24;
                break;
            case 6:
                //美食
                $cat_id = 25;
                break;
            case 7:
                //文体车品
                $cat_id = 26;
                break;
            case 8:
                //数码家电
                $cat_id = 27;
                break;
            case 9:
                //男装
                $cat_id = 30;
                break;
            case 10:
                //内衣
                $cat_id = 29;
                break;
            default:
                $cat_id = $v['Cid'] + 10000;
        }

        //对于图片链接地址不完整的予以补充完整
        if ('//' == substr($v['Pic'], 0, 2)) {
            if (false !== stripos($v['Pic'], 'alicdn.com')) {
                $v['Pic'] = 'https:' . $v['Pic'];
            } else {
                $v['Pic'] = 'http:' . $v['Pic'];
            }
        }

        if ($v['IsTmall'] == 1) {
            $shop_type = 'B';
        } else {
            $shop_type = 'C';
        }

        $data = array(
            'activity_id'       => $v['Quan_id'],
            'snum'              => $v['Quan_surplus'], //剩余优惠券
            'lnum'              => $v['Quan_receive'], //已领取优惠卷
            'quan'              => $v['Quan_price'], //优惠券金额
            'price'             => $v['Org_Price'], //正常售价
            'intro'             => $v['Introduce'], //文案
            'coupon_rate'       => (int)($v['Quan_price'] / $v['Org_Price']) * 100,
            'volume'            => $v['Sales_num'], //销量
            'commission_rate'   => $v['Commission'] * 100, //佣金比例
            'commission'        => $commission, //佣金
            'title'             => $v['Title'], //标题
            'click_url'         => $click_url, //领券链接，内含pid
            'num_iid'           => $v['GoodsID'], //淘宝商品ID
            'dataoke_id'        => $v['ID'],  //大淘客商品ID
            'pic_url'           => $v['Pic'],
            'coupon_price'      => $v['Price'], //使用优惠券后价格
            'shop_type'         => $shop_type,
            'coupon_type'       => (isset($v['coupon_type']) ? $v['coupon_type'] : 1),
            'uname'             => $uname,
            'pass'              => $pass, //是否上线
            'coupon_end_time'   => $coupon_end_time,
            'cate_id'           => $cat_id, //分类
            'coupon_start_time' => time(),
            'ordid'             => $order, //商品排序
            'endtime'           => substr($v['Quan_time'], 0, 10),//结束时间
        );
        return $data;
    }


    /**
     * @param $num_iid
     * @return array
     */
    public function qwGoodsDetail($num_iid) {
        $keyword      = 'https://detail.tmall.com/item.htm?id=' . $num_iid;
        $key          = urlencode($keyword);
        $temp         = microtime(true) * 1000;
        $temp         = explode('.', $temp);
        $end          = $temp[0] + 8;
        $url          = sprintf($this->search_url, $key, $temp[0], 1, 10, $end);
        $ali_data     = json_decode($this->httpObj->get($url), true);
        $coupon_price = round($ali_data['data']['pageList'][0]['zkPrice'] - $ali_data['data']['pageList'][0]['couponAmount'], 2);

        $data = array(
            'id'           => $ali_data['data']['pageList'][0]['auctionId'],
            'title'        => $ali_data['data']['pageList'][0]['title'],
            'price'        => $ali_data['data']['pageList'][0]['zkPrice'],
            'quan'         => $ali_data['data']['pageList'][0]['couponAmount'],
            'coupon_price' => $coupon_price,
            'volume'       => $ali_data['data']['pageList'][0]['biz30day'],
            'pic_url'      => 'http:' . $ali_data['data']['pageList'][0]['pictUrl'],
        );
        return $data;
    }

    /**
     * 判断商品是否下线
     *
     * @param $num_iid
     * @return bool
     */
    public function isItemOnline($num_iid) {
        $data = $this->getItem($num_iid);
        if ($data) {
            if (isset($data['result']) && $data['result']) {
                $commission = round(($data['result']['Price'] * $data['result']['Commission'] / 100), 2);
                if ($data['result']['Commission_queqiao'] > $data['result']['Commission_jihua']) {
                    $res['type'] = "queqiao";
                    $commission  = $commission * 0.85;
                } else {
                    if ($data['result']['Jihua_link'] === 0 || $data['result']['Jihua_link'] == "") {
                        $res['type'] = "tongyong";
                    } else {
                        $res['type'] = "wfa";
                    }
                }
                $res['coupon_price']    = $data['result']['Price'];
                $res['quan']            = $data['result']['Quan_price'];
                $res['price']           = $data['result']['Org_Price'];
                $res['commission']      = $commission;
                $res['commission_rate'] = $data['result']['Commission'] * 100;
                $res['coupon_end_time'] = strtotime($data['result']['Quan_time']);
                $res['dataoke_id']      = $data['result']['ID'];
                return array('status' => 1, 'data' => $res);
            } else {
                return array('status' => 0, 'data' => array());
            }
        } else {
            //访问受限的 情况
            return array('status' => 1, 'data' => array());
        }
    }
}