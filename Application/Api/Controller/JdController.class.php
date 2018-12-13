<?php
/**
 * 京东免单相关
 */

namespace Api\Controller;

use Common\Org\Http;

/**
 * Class ItemsController
 *
 * @package Api\Controller
 */
class JdController extends CommonController {

    /**
     * 构造方法
     */
    public function __construct() {
        parent::__construct();

        $auth_arr = array('getItemCouponInfo', 'addSubsidyOrder', 'getOrderList', 'getGroupOrderNum');
        $token    = I('request.token', '', 'trim');

        if (in_array(ACTION_NAME, $auth_arr) && '' != $token) {
            $this->_checkUser();
        }
    }

    /**
     * 商品列表
     */
    public function getItemList() {
        $type = I('get.type', 'free', 'trim');
        $page = I('get.page', 1, 'int');
        $page--;

        if ($type != 'new') {
            $type = 'free';
        }

        $where = array('coupon_end_time' => array('gt', time()), 'type' => $type, 'is_online' => 1);

        $start_num = $page * $this->limit;
        $field     = 'num_iid,title,pic_url,price,coupon_price,coupon_money,subsidy_money,click_url';
        $data      = M('jd_items')->where($where)->field($field)->limit($start_num, $this->limit)->order('id desc')->select();
        foreach ($data as $key => $item) {
            $data[$key]['price']         = format_view_money($item['price']);
            $data[$key]['coupon_price']  = format_view_money($item['coupon_price']);
            $data[$key]['coupon_money']  = format_view_money($item['coupon_money']);
            $data[$key]['subsidy_money'] = format_view_money($item['subsidy_money']);
        }

        $this->output('ok', 'success', $data);
    }

    /**
     * 获取京东推广信息
     */
    public function getItemCouponInfo() {
        $num_iid   = I('request.num_iid', 0, 'int');
        $click_url = I('request.click_url', '', 'trim');

        if (empty($num_iid) || empty($click_url)) {
            $this->output('参数不能为空，请检查参数是否正确');
        }

        $business_params = array(
            'appid'      => C('BASE.jingtuitui_app_id'),
            'appkey'     => C('BASE.jingtuitui_app_key'),
            'unionid'    => C('BASE.jingdong_union_id'),
            'positionid' => $this->jd_pid,
            'gid'        => $num_iid,
            'coupon_url' => urldecode($click_url)
        );

        $http = new \Common\Org\Http();
        $res  = json_decode($http->post('http://japi.jingtuitui.com/api/get_goods_link', $business_params), true);
        if (isset($res['result']['link'])) {
            $jd_mini_program_path = 'pages/jingfen_twotoone/item?spreadUrl=' . urlencode($res['result']['link']) . '&customerinfo=JtuiTui';
            $this->output('ok', 'success', array('jd_mini_program_path' => $jd_mini_program_path));
        } else {
            $this->output('获取优惠券失败，请重试');
        }
    }

    /**
     * 新增补贴订单
     */
    public function addSubsidyOrder() {
        $now      = time();
        $end_time = strtotime(date("2018-08-24"));
        if ($now > $end_time) {
            $this->output('活动已结束，仅8月23日前可申请补贴订单');
        }

        $order_sn = I('request.order_sn', '', 'trim');
        if (strlen($order_sn) < 11) {
            $this->output('请填写正确的京东订单');
        } else {
            //查询订单，如果已分佣，就不是免单的订单
            $commission = M('order_commission')->where(array('order_sn' => $order_sn))->getField('commission');
            if ($commission > 0) {
                $this->output('该单为非免单商品，请勿提交！');
            }

            //查询订单，不符合条件的做友好提示
            $order = M('order')->where(array('order_sn' => $order_sn))->field('order_sn,pay_status,title,total_money,commission,add_time')->select();
            if (count($order) == 0) {
                $this->output('订单不存在，请稍后查询，订单同步需要5-10分钟！下单半小时还查不到，说明您账号没有免单资格，请直接退款！');
            } else {
                $pay_status_view = '';
                foreach ($order as &$order_info) {
                    if ('fail' == $order_info['pay_status']) {
                        $pay_status_view = '订单未支付或已退款或有其他异常情况,暂不能申请免单！';
                        continue;
                    }
                    if ($order_info['total_money'] == 0) {
                        $pay_status_view = '该商品为赠品';
                    } else if ($order_info['total_money'] != 9.9) {
                        $pay_status_view = '订单付款金额不符合免单条件，仅9.9元才可免单';
                    }
                    if ($order_info['commission'] < 4.46 && $order_info['commission'] > 0) {
                        $pay_status_view = '订单无效，商家不参与免单活动了';
                    }

                    if ($order_info['add_time'] < 1534348800 || $order_info['add_time'] > 1534780800) {
                        $pay_status_view = '订单无效，仅支持8月16至20号的订单';
                    }
                }
                if('' != $pay_status_view){
                     $this->output($pay_status_view);
                }
            }
        }
        $order = M('jd_subsidy_order')->where(array('order_sn' => $order_sn))->find();
        if (!empty($order)) {
            $this->output('该订单已被申请补贴，请勿重复申请！');
        }

        $user = M('user')->where(array('id' => $this->user_id))->find();
        if (empty($user['bank_account'])) {
            $this->output('请先设置支付宝账号信息！');
        } else {
            $add_data = array(
                'user_id'  => $this->user_id,
                'order_sn' => $order_sn,
                'status'   => 'ing',
                'add_time' => $now
            );
            M('jd_subsidy_order')->add($add_data);

            $this->output('ok', 'success', array());
        }
    }

    /**
     * 查看已申请补贴订单列表
     */
    public function getOrderList() {
        $page = I('get.page', 1, 'int');
        $page--;
        $start_num = $page * $this->limit;
        $where     = array(
            'so.user_id' => $this->user_id
        );
        $field     = 'so.order_sn,so.status';
        $orders    = M('jd_subsidy_order')->alias('so')
            ->where($where)->field($field)
            ->order('so.id desc')->limit($start_num, $this->limit)->select();
        foreach ($orders as $key => $order) {
            switch ($order['status']) {
                case 'ing':
                    $status_view = '审核中';
                    break;
                case 'invalid':
                    $status_view = '无效单';
                    break;
                case 'finished':
                    $status_view = '已补贴';
                    break;

                default:
                    $status_view = '审核中';
                    break;
            }

            $orders[$key]['status_view'] = $status_view;
            $orders[$key]['total_money'] = floatval($order['total_money']);
            unset($orders[$key]['status']);
        }

        $this->output('ok', 'success', $orders);
    }

    /**
     * 获取分享小程序码图片
     */
    public function getMiniProgramCodePic() {
        $invite_code = I('get.invite_code', '', 'trim');

        if (strlen($invite_code) < 4) {
            $this->output('参数异常，邀请码错误');
        }

        $access_token = $this->_getMiniProgramAccessToken();
        if ('' == $access_token) {
            $this->output('获取access token异常');
        }

        $url  = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $access_token;
        $http = new \Common\Org\Http();
        $data = array(
            'scene' => $invite_code,
            'page'  => 'pages/jdAd/index',
            'width' => 200
        );

        $first_two_char = substr($invite_code, 0, 2);
        $dir            = dirname(APP_PATH) . '/www/Static/images/' . $first_two_char;
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $res       = $http->post($url, json_encode($data));
        $file_name = $dir . '/mini_program_code_' . $invite_code . '.png';
        file_put_contents($file_name, $res);

        $this->output('ok', 'success', array('mini_program_code_pic' => C('BASE.app_interface_domain') . '/Static/images/' . $first_two_char . '/' . 'mini_program_code_' . $invite_code . '.png'));
    }

    /**
     * 获取分享小程序码大图
     */
    public function getMiniProgramCodeBigPic() {
        $invite_code = I('get.invite_code', '', 'trim');
        $width = I('get.width', 430, 'intval');

        if (strlen($invite_code) < 4) {
            $this->output('参数异常，邀请码错误');
        }

        $access_token = $this->_getMiniProgramAccessToken();
        if ('' == $access_token) {
            $this->output('获取access token异常');
        }

        $url  = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $access_token;
        $http = new \Common\Org\Http();
        $data = array(
            'scene' => $invite_code,
            'page'  => 'pages/jdAd/index',
            'width' => $width
        );

        $first_two_char = substr($invite_code, 0, 2);
        $dir            = dirname(APP_PATH) . '/www/Static/images/' . $first_two_char;
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $res       = $http->post($url, json_encode($data));
        $file_name = $dir . '/mini_program_code_' . $invite_code . '.png';
        file_put_contents($file_name, $res);

        $this->output('ok', 'success', array('mini_program_code_pic' => C('BASE.app_interface_domain') . '/Static/images/' . $first_two_char . '/' . 'mini_program_code_' . $invite_code . '.png'));
    }

    /**
     * 618免单
     */
    public function freeItems() {
        $param = array(
            'appid'  => C('BASE.jingtuitui_app_id'),
            'appkey' => C('BASE.jingtuitui_app_key'),
            'page'   => I('get.page', 1, 'int'),
            'num'    => 20,
            'code'   => 'miandan618', //商品搜索：商品名称/商品SKU
        );
        $http  = new Http();
        $res   = json_decode($http->get('http://japi.jingtuitui.com/api/free_goods', $param), true);
        $items = array();
        if (isset($res['result']['data'])) {
            foreach ($res['result']['data'] as $key => $goods) {
                $after_coupon_price = $goods['coupon_price'];
                $free_price         = $goods['free_price'];
                $items[]            = array(
                    'num_iid'       => $goods['gid'],
                    'title'         => $goods['goods_name'],
                    'pic_url'       => format_url_to_https($goods['goods_img']),
                    'click_url'     => $goods['discount_link'], //领券地址
                    'price'         => format_view_money($goods['goods_price']),
                    'coupon_price'  => format_view_money($after_coupon_price),
                    'coupon_money'  => format_view_money($goods['discount_price']),
                    'subsidy_money' => format_view_money($free_price),
                    'jid'           => $goods['jid'],
                );
            }
        }
        $this->output('ok', 'success', $items);
    }

    /**
     * 查看团队免单数量
     */
    public function getGroupOrderNum() {
        $level = M('user')->getFieldById($this->user_id, 'level');
        $count = 0;
        if ($level == 5) {
            $user_id_data = M('user')->where(array('group_leader_id' => $this->user_id))->getField('id', true);
        } else {
            $user_id_data = M('user')->where(array('inviter_id' => $this->user_id))->getField('id', true);
            if (empty($user_id_data)) {
                $user_id_data = array($this->user_id);
            } else {
                array_push($user_id_data, $this->user_id);
            }
        }
        $user_id_chunk = array_chunk($user_id_data, 999);
        foreach ($user_id_chunk as $user_id) {
            $count += M('jd_subsidy_order')->where(array('add_time' => array('gt', 1534348800), 'user_id' => array('in', $user_id)))->count('id');
        }
        $this->output('ok', 'success', array('total_order_num' => $count));
    }


}