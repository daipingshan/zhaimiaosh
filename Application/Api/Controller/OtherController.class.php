<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/6/14
 * Time: 11:00
 */

namespace Api\Controller;


class OtherController extends CommonController {

    /**
     * 京东免单商品
     */
    public function index() {
        $this->display();
    }

    /**
     * 新增补贴订单
     */
    public function addSubsidyOrder() {
        $now      = time();
        $end_time = strtotime(date("2018-06-22"));
        if ($now > $end_time) {
            $this->output('活动已结束，仅6月21日前可申请补贴订单');
        }

        $mobile   = I('request.mobile', '', 'trim');
        $order_sn = I('request.order_sn', '', 'trim');
        if (empty($mobile)) {
            $this->output('手机号不能为空！');
        }
        if (!is_mobile($mobile)) {
            $this->output('手机号码格式不正确！');
        }
        if (strlen($order_sn) < 11) {
            $this->output('请填写正确的京东订单');
        } else {
            //查询订单，如果已分佣，就不是免单的订单
            $commission = M('order_commission')->where(array('order_sn' => $order_sn))->getField('commission');
            if ($commission > 0) {
                $this->output('该单为非免单商品，请勿提交！');
            }
        }

        $order = M('jd_subsidy_order')->where(array('order_sn' => $order_sn))->find();
        if (!empty($order)) {
            $this->output('该订单已被申请补贴，请勿重复申请！');
        }
        $add_data = array(
            'user_id'  => 0,
            'order_sn' => $order_sn,
            'status'   => 'ing',
            'add_time' => $now,
            'mobile'   => $mobile,
        );
        M('jd_subsidy_order')->add($add_data);
        $this->output('申请成功，等待平台补贴', 'success', array());
    }
}