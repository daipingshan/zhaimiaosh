<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/25
 * Time: 15:08
 */

namespace Partner\Controller;

/**
 * 用户记录
 * Class OrderController
 *
 * @package Admin\Controller
 */
class OrderController extends CommonController {

    /**
     * 订单列表
     */
    public function index() {
        $status_arr    = array(
            'fail'    => '订单失效',
            'success' => '订单成功',
            'paid'    => '订单付款',
            'settle'  => '订单结算',
            'refund'  => '订单退款'
        );
        $mall_platform = [
            'all'       => '全部平台',
            'taobao'    => '淘宝',
            'pinduoduo' => '拼多多',
            'jingdong'  => '京东'
        ];
        $partners_list = M('partner')->field('id,partner_name')->select();
        $partners      = [];
        foreach ($partners_list as $val) {
            $partners[$val['id']] = $val['partner_name'];
        }
        if (IS_AJAX) {
            $title      = I('get.title', '', 'trim');
            $order_sn   = I('get.order_sn', '', 'trim');
            $num_iid    = I('get.num_iid', '', 'trim');
            $mobile     = I('get.mobile', '', 'trim');
            $status     = I('get.status', '', 'trim');
            $platform   = I('get.mall_platform', '', 'trim');
            $date_type  = I('get.date_type', 0, 'int');
            $start_date = I('get.start_date', '', 'trim');
            $end_date   = I('get.end_date', '', 'trim');
            $page       = I('get.page', 1, 'int');
            $model      = M('order_commission');
            $where      = array();
            if ($title) {
                $where['o.title'] = array('like', "%{$title}%");
            }
            if ($order_sn) {
                $where['c.order_sn'] = array('like', "%{$order_sn}%");
            }
            if ($num_iid) {
                $where['o.num_iid'] = $num_iid;
            }
            $where['c.partner_id'] = $this->partner_id;
            if ($mobile) {
                $where['u.mobile'] = $mobile;
            }
            if ($status) {
                $where['c.pay_status'] = $status;
            }
            if ($date_type){

                if ($start_date && $end_date){
                    $con = array('between', array(strtotime($start_date), strtotime($end_date) + 86399));
                    if ($date_type==1){
                        $where['c.add_time'] = $con;
                    }else{
                        $where['c.settle_time'] = $con;
                    }
                }
            }
            if ($platform && $platform != 'all') {
                $where['c.order_source_mall_platform'] = $platform;
            }
            $join_order = "left join zm_order o ON c.order_id = o.id";
            $join_user  = "left join zm_user u ON c.user_id = u.id";
            $count      = $model->alias('c')->where($where)->join($join_order)->join($join_user)->count('c.id');
            $start_num  = ($page - 1) * $this->limit;
            $field      = "c.*,u.mobile,o.title,o.item_num,o.num_iid";
            $data       = $model->alias('c')->field($field)->where($where)->join($join_order)->join($join_user)->limit($start_num, $this->limit)->order('c.add_time desc')->select();
            $source_arr = array(
                'self'         => '自身分成',
                'son'          => '上级分成',
                'group_leader' => '团长分成',
            );
            foreach ($data as &$val) {
                $val['source_name']     = $source_arr[$val['source']];
                $val['pay_status_name'] = $status_arr[$val['pay_status']];
                $val['partner_name']    = $partners[$val['partner_id']];
                $val['mall_platform']   = $mall_platform[$val['order_source_mall_platform']];
                $val['add_time']        = date('Y-m-d H:i:s', $val['add_time']);
                if ($val['settle_time'] > 0) {
                    $val['settle_time'] = date('Y-m-d H:i:s', $val['settle_time']);
                } else {
                    $val['settle_time'] = "尚未结算";
                }
            }
            $this->success(array('data' => $data, 'count' => $count));
        }
        $this->assign(array('status_arr' => $status_arr,'mall_platform' => $mall_platform));
        $this->display();
    }

}