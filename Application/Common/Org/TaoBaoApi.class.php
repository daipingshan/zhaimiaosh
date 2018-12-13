<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/7/1 0001
 * Time: 下午 5:15
 */

namespace Common\Org;

class TaoBaoApi {

    /**
     * @var bool
     */
    protected $proxy_status = false;

    /**
     * 全网搜索商品地址
     *
     * @var string
     */
    private $search_url = "http://pub.alimama.com/items/search.json?q=%s&_t=%s&startPrice=0.4&toPage=%s&queryType=2&sortType=%s&auctionTag=&perPageSize=%s&shopTag=&t=%s&_tb_token_=e13fbf63389e7&pvid=%s";

    /**
     * 获取商品图文详情
     *
     * @var string
     */
    private $desc_url = "http://hws.m.taobao.com/cache/mtop.wdetail.getItemDescx/4.1/?data=%7B%22item_num_id%22%3A%22ppp%22%7D";

    /**
     * @var TopClient|null
     */
    private $topClient = null;


    /**
     * @var string
     */
    //private $app_key = "23836749"; //淘店客2
    private $app_key = "24954924"; //宅喵生活安卓版_316

    /**
     * @var string
     */
    //private $app_pass = "d92c5da07d9c99ee3b21e489c6b04dda"; //淘店客2
    private $app_pass = "6402816360840e5f320360218049f128"; //淘店客2

    /**
     * 构造函数
     * AliApi constructor.
     */
    public function __construct() {
        //$this->httpObj = new Http();
    }

    /**
     * 搜索商品 模拟请求，暂时弃用
     *
     * @param $keyword
     * @param int $sort
     * @param int $page
     * @param int $page_num
     * @return array
     */
    public function searchOld($keyword, $sort = 0, $page = 1, $page_num = 20) {
        $temp       = microtime(true) * 1000;
        $temp       = explode('.', $temp);
        $_t         = $temp[0];
        $t          = $_t + 16;
        $enter_time = $_t - mt_rand(2000, 20000);
        $pvid       = '10_49.221.62.' . mt_rand(1, 250) . '_' . mt_rand(1000, 5000) . '_' . $enter_time;
        $url        = sprintf($this->search_url, urlencode($keyword), $_t, $page, $sort, $page_num, $t, $pvid);
        $proxy      = null;
        if ($this->proxy_status === true) {
            $proxy = $this->_getProxy();
        }
        $ali_data = json_decode($this->_httpGet($url, $proxy), true);
        if (!isset($ali_data['data']['pageList'])) {
            return array('status' => 1, 'info' => 'ok', 'data' => array());
        }
        $data = array();
        foreach ($ali_data['data']['pageList'] as $k => $v) {
            $coupon_price = round($v['zkPrice'] - $v['couponAmount'], 2);
            $ali_rate     = $v['tkSpecialCampaignIdRateMap'];
            if ($ali_rate && is_array($ali_rate)) {
                arsort($ali_rate);
                $rate = array_values($ali_rate);
            } else {
                $rate = array($v['tkRate']);
            }
            $data[] = array(
                'id'              => $v['auctionId'],
                'num_iid'         => $v['auctionId'],
                'title'           => strip_tags($v['title']),
                'price'           => round($v['zkPrice'], 2),
                'coupon_price'    => $coupon_price,
                'pic_url'         => 'http:' . $v['pictUrl'],
                'coupon_money'    => $v['couponAmount'],
                'sale_num'        => $v['biz30day'],
                'commission_rate' => computed_price($rate[0], 1),
                'commission'      => computed_price(round(($coupon_price * $rate[0]) / 100, 2), 2, C('COMMISSION_RATE.base_commission_rate')),
                'group_leader_commission' => computed_price(round(($coupon_price * $rate[0]) / 100, 2), 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader')),
                'shop_type'       => 1 == $v['userType'] ? 'B' : 'C',
                'data_source'     => 'all'
            );
        }
        return array('status' => 1, 'info' => 'ok', 'data' => $data);
    }

    /**
     * 搜索淘宝商品 通用物料搜索API（导购）
     */
    public function search($keyword, $sort = '', $page = 1, $page_num = 20) {
        include_once(__DIR__ . '/Taobaotop/TopClient.php');
        include_once(__DIR__ . '/Taobaotop/RequestCheckUtil.php');
        include_once(__DIR__ . '/Taobaotop/ResultSet.php');
        include_once(__DIR__ . '/Taobaotop/request/TbkDgMaterialOptionalRequest.php');

        $c            = new \TopClient;
        $c->appkey    = $this->app_key;
        $c->secretKey = $this->app_pass;
        $req          = new \TbkDgMaterialOptionalRequest;
        $req->setPageSize("20");
        $req->setPageNo("{$page}");
        $req->setQ($keyword);
        $req->setAdzoneId("1963574096");
        $req->setStartTkRate("200");
        $req->setStartPrice("1");

        if (!empty($sort)) {
            $req->setSort($sort);
        }

        $res    = $c->execute($req);
        $result = object_to_array($res);
        if (!isset($result['result_list']['map_data'])) {
            return array('status' => 1, 'info' => 'ok', 'data' => array());
        }

        if ($result['total_results'] == 1) {
            $result['result_list']['map_data'] = array($result['result_list']['map_data']);
        }

        $data = array();
        foreach ($result['result_list']['map_data'] as $k => $v) {
            $coupon_money = 0; //优惠券金额
            $coupon_end_time = 0; //券结束时间（时间戳）
            $activity_id = ''; //优惠券ID

            if (!empty($v['coupon_info'])) {
                $coupon_end_time = strtotime($v['coupon_end_time']) + 86399;
                $p = '/减(\d{1,9})元/';
                preg_match_all($p, $v['coupon_info'], $m);
                if (isset($m[1][0])) {
                    $coupon_money = $m[1][0];
                }
            }

            if (!empty($v['coupon_id'])) {
                $activity_id = $v['coupon_id'];
            }

            $coupon_price = round($v['zk_final_price'] - $coupon_money, 2); //券后价

            $data[] = array(
                'id'              => $v['num_iid'],
                'num_iid'         => $v['num_iid'],
                'title'           => strip_tags($v['title']),
                'price'           => round($v['zk_final_price'], 2),
                'coupon_price'    => $coupon_price,
                'activity_id'     => $activity_id,
                'pic_url'         => $v['pict_url'],
                'small_images'    => $v['small_images']['string'],
                'coupon_money'    => $coupon_money,
                'coupon_info'     => $v['coupon_info'],
                'coupon_end_time' => $coupon_end_time,
                'sale_num'        => $v['tk_total_sales'],
                'commission_rate' => $v['commission_rate'] / 100,
                'commission'      => computed_price(round(($coupon_price * $v['commission_rate']) / 10000, 2), 2, C('COMMISSION_RATE.base_commission_rate')),
                'group_leader_commission' => computed_price(round(($coupon_price * $v['commission_rate']) / 10000, 2), 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader')),
                'shop_type'       => 1 == $v['user_type'] ? 'B' : 'C',
                'data_source'     => 'all'
            );
        }
        return array('status' => 1, 'info' => 'ok', 'data' => $data);
    }

    /**
     * 通过物料ID搜索淘宝商品 淘宝客物料下行-导购
     */
    public function getTaobaoMaterialItems($material_id, $page_size = 80, $page = 1) {
        include_once(__DIR__ . '/Taobaotop/TopClient.php');
        include_once(__DIR__ . '/Taobaotop/RequestCheckUtil.php');
        include_once(__DIR__ . '/Taobaotop/ResultSet.php');
        include_once(__DIR__ . '/Taobaotop/request/TbkDgOptimusMaterialRequest.php');

        $c            = new \TopClient;
        $c->appkey    = $this->app_key;
        $c->secretKey = $this->app_pass;
        $req          = new \TbkDgOptimusMaterialRequest;
        $req->setPageSize("{$page_size}");
        $req->setPageNo("{$page}");
        $req->setAdzoneId("1963574096");
        $req->setMaterialId("{$material_id}");

        $res    = $c->execute($req);
        $result = object_to_array($res);

        if (!isset($result['result_list']['map_data'])) {
            return array('status' => 1, 'info' => 'ok', 'data' => array());
        }

        if (isset($result['total_results']) && $result['total_results'] == 1) {
            $result['result_list']['map_data'] = array($result['result_list']['map_data']);
        }

        $data = array();
        $now = time() * 1000;
        foreach ($result['result_list']['map_data'] as $k => $v) {
            $coupon_money = $v['coupon_amount']; //优惠券金额
            $coupon_end_time = 0; //券结束时间（时间戳）

            if (4071 == $material_id) {
                $v['zk_final_price'] = $v['jdd_price'];
                $v['coupon_end_time'] = strtotime($v['oetime']) * 1000;
                $v['volume'] = $v['sell_num'];
            } else if (!isset($v['coupon_end_time']) && $coupon_money > 0) {
                $v['coupon_end_time'] = $now + 18000000;
            }

            $coupon_price = round($v['zk_final_price'] - $coupon_money, 2); //券后价
            $data[] = array(
                'id'              => $v['item_id'],
                'num_iid'         => $v['item_id'],
                'title'           => strip_tags($v['title']),
                'price'           => round($v['zk_final_price'], 2),
                'coupon_price'    => $coupon_price,
                'pic_url'         => 'http:' . $v['pict_url'],
                'coupon_money'    => $coupon_money,
                'coupon_end_time' => floatval($v['coupon_end_time']) / 1000,
                'sale_num'        => $v['volume'],
                'commission_rate' => $v['commission_rate'],
                'commission'      => computed_price(round(($coupon_price * $v['commission_rate']) / 100, 2), 2, C('COMMISSION_RATE.base_commission_rate')),
                'group_leader_commission' => computed_price(round(($coupon_price * $v['commission_rate']) / 100, 2), 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader')),
                'shop_type'       => 1 == $v['user_type'] ? 'B' : 'C',
                'data_source'     => 'all'
            );
        }

        return $data;
    }

    /**
     * 通过接口获取口令
     *
     * @param $url
     * @param $text
     * @param $img
     * @return array
     */
    public function getApiPass($url, $text, $img) {
        if (!$url || !$text) {
            return array('status' => 0, 'msg' => '请求参数不合法');
        }
        include_once(__DIR__ . '/Taobaotop/TopClient.php');
        include_once(__DIR__ . '/Taobaotop/TopLogger.php');
        include_once(__DIR__ . '/Taobaotop/RequestCheckUtil.php');
        include_once(__DIR__ . '/Taobaotop/ResultSet.php');
        include_once(__DIR__ . '/Taobaotop/request/TbkTpwdCreateRequest.php');
        $c            = new \TopClient;
        $c->appkey    = $this->app_key;
        $c->secretKey = $this->app_pass;
        $req          = new \TbkTpwdCreateRequest;
        $req->setUrl($url);
        $req->setText($text);
        $req->setLogo($img);
        $result = $c->execute($req);
        $result = object_to_array($result);
        if ($result['data']['model']) {
            $data = array('tao_kou_ling' => $result['data']['model']);
            return array('status' => 1, 'info' => 'ok', 'data' => $data);
        } else {
            return array('status' => 0, 'info' => '淘口令转换失败' . var_export($result, true));
        }
    }

    /**
     * 获取商品图文详情
     *
     * @param $goods_id
     * @return string
     */
    public function getSmallImages($goods_id) {
        $small_image = array();
        if (!$goods_id) {
            return $small_image;
        }
        include_once(__DIR__ . '/Taobaotop/TopClient.php');
        require_once(__DIR__ . "/Taobaotop/ResultSet.php");
        require_once(__DIR__ . "/Taobaotop/RequestCheckUtil.php");
        require_once(__DIR__ . "/Taobaotop/request/TbkItemInfoGetRequest.php");
        $this->topClient            = new \TopClient();
        $req                        = new \TbkItemInfoGetRequest();
        $this->topClient->appkey    = $this->app_key;
        $this->topClient->secretKey = $this->app_pass;
        $req->setFields("small_images");
        $req->setPlatform("1");
        $req->setNumIids($goods_id);
        $result = $this->topClient->execute($req);
        $temp   = object_to_array($result);
        $data   = $temp['results']['n_tbk_item']['small_images'];
        if (isset($data['string']) && $data['string']) {
            $small_image = $data['string'];
        }
        return $small_image;
    }

    /**
     * 获取商品图文详情
     *
     * @param $goods_id
     * @return string
     */
    public function getDesc($goods_id) {
        $desc = array();
        if (!$goods_id) {
            return $desc;
        }
        $url     = str_replace('ppp', $goods_id, $this->desc_url);
        $content = $this->_httpGet($url);
        $data    = json_decode($content, true);
        if (!$data) {
            return $desc;
        }
        if (isset($data['data']['images']) && $data['data']['images']) {
            $desc = $data['data']['images'];
        }
        return $desc;
    }

    /**
     * 获取淘宝拉新的用户订单信息(导购)
     */
    public function getDgNewUserOrder($adzone_id, $page = 1, $page_size = 100) {
        include_once(__DIR__ . '/Taobaotop/TopClient.php');
        include_once(__DIR__ . '/Taobaotop/TopLogger.php');
        include_once(__DIR__ . '/Taobaotop/RequestCheckUtil.php');
        include_once(__DIR__ . '/Taobaotop/ResultSet.php');
        include_once(__DIR__ . '/Taobaotop/request/TbkDgNewuserOrderGetRequest.php');
        $c            = new \TopClient;
        $c->appkey    = $this->app_key;
        $c->secretKey = $this->app_pass;
        $req          = new \TbkDgNewuserOrderGetRequest;
        $req->setPageSize($page_size);
        $req->setAdzoneId($adzone_id);
        $req->setPageNo($page);
        $resp   = $c->execute($req);
        $result = object_to_array($resp);

        if (isset($result['results']['data'])) {
            return $result['results']['data']['results']['map_data'];
        } else {
            return array();
        }
    }

    /**
     * 获取淘宝拉新的用户订单信息（社交）
     */
    public function getScNewUserOrder($site_id, $adzone_id, $page = 1) {
        include_once(__DIR__ . '/Taobaotop/TopClient.php');
        include_once(__DIR__ . '/Taobaotop/TopLogger.php');
        include_once(__DIR__ . '/Taobaotop/RequestCheckUtil.php');
        include_once(__DIR__ . '/Taobaotop/ResultSet.php');
        include_once(__DIR__ . '/Taobaotop/request/TbkScNewuserOrderGetRequest.php');
        $c            = new \TopClient;
        $c->appkey    = $this->app_key;
        $c->secretKey = $this->app_pass;
        $req          = new \TbkScNewuserOrderGetRequest;
        $req->setPageSize("20");
        $req->setSiteId(42450934);
        $req->setAdzoneId(250998699);
        $req->setPageNo($page);
        $resp   = $c->execute($req);
        $result = object_to_array($resp);

        if (isset($result['results']['data'])) {
            return $result['results']['data']['results']['map_data'];
        } else {
            return $result;
        }
    }

    /**
     * 获取淘宝商品基本信息
     */
    public function getTaoBaoItemInfo($id) {
        if (!$id) {
            return array('status' => 0, 'info' => '请求参数不合法');
        }
        include_once(__DIR__ . '/Taobaotop/TopClient.php');
        include_once(__DIR__ . '/Taobaotop/RequestCheckUtil.php');
        include_once(__DIR__ . '/Taobaotop/ResultSet.php');
        include_once(__DIR__ . '/Taobaotop/request/TbkItemInfoGetRequest.php');
        $c            = new \TopClient;
        $c->appkey    = $this->app_key;
        $c->secretKey = $this->app_pass;
        $req          = new \TbkItemInfoGetRequest;
        $req->setFields("num_iid,title,item_url,pict_url,reserve_price,zk_final_price,item_url,nick,seller_id,volume,cat_name");
        $req->setPlatform("1");
        $req->setNumIids($id);
        $res    = $c->execute($req);
        $result = object_to_array($res);
        if (isset($result['results']['n_tbk_item']) && $result['results']['n_tbk_item']) {
            return array('status' => 1, 'data' => $result['results']['n_tbk_item'], 'info' => '获取成功!');
        } else {
            return array('status' => 0, 'info' => '获取失败');
        }
    }

    /**
     * @param $url
     * @param $proxy
     * @return array|mixed\
     */
    protected function _httpGet($url, $proxy = null) {
        if (!trim($url)) {
            return array('error' => '地址不能为空！');
        }
        $curl = curl_init();
        if ($proxy) {
            list($ip, $port) = explode(':', $proxy);
            curl_setopt($curl, CURLOPT_PROXY, $ip);
            curl_setopt($curl, CURLOPT_PROXYPORT, $port);
            curl_setopt($curl, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); //使用http代理模式
        }
        if (stripos($url, "https://") !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, 1);
        }
        $header = array(
            'Accept:application/json, text/javascript, */*; q=0.01',
            'Content-Type:application/json;charset=UTF-8',
            'Referer: http://pub.alimama.com',
            'X-Requested-With:XMLHttpRequest',
        );
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        // 设置基本属性
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        // 发送
        $result      = curl_exec($curl);
        if (@curl_errno($curl)) {
            $result = array('error' => '错误提示：' . curl_error($curl));
        }
        curl_close($curl);
        return $result;
    }

    /**
     * 获取代理ip
     *
     * @return null
     */
    protected function _getProxy() {
        $proxy_data = array(
            "116.255.162.107:16816",
            "43.226.164.60:16816",
            "42.123.83.108:16816",
            "115.28.141.184:16816",
            "122.114.234.157:16816"
        );
        $num        = rand(0, count($proxy_data) - 1);
        $ip         = null;
        if (isset($proxy_data[$num])) {
            $ip = $proxy_data[$num];
        }
        return $ip;
    }
}