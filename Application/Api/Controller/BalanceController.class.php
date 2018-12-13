<?php
/**
 * 推广收益
 * author: dongguangqi
 */

namespace Api\Controller;

/**
 * Class BalanceController
 *
 * @package Api\Controller
 */
class BalanceController extends CommonController {
    protected $checkUser = true;

    /**
     * 推广收益概况
     */
    public function index() {
        $today_zero = strtotime(date('Y-m-d'));
        $yesterday_zero = $today_zero - 86400;
        $current_month_first_day = date('Y-m-01');
        $current_month_zero = strtotime($current_month_first_day);
        $before_month_zero =  strtotime($current_month_first_day . ' -1 month');

        $user_info = M('user')->find($this->user_id);
        $total_withdraw_money = M('withdraw')->where(array('user_id' => $this->user_id))->sum('money');
        $total_settle_commission = $user_info['account_balance'] + $user_info['jd_account_balance'] + $user_info['pdd_account_balance'] + $total_withdraw_money; //累计总收益
        $today_order_num = 0; //今日订单数
        $today_paid_commission = 0; //今日成交预估
        $today_settle_commission = 0; //今日结算
        $yesterday_order_num = 0; //昨日订单数
        $yesterday_paid_commission = 0; //昨日成交预估
        $yesterday_settle_commission = 0; //昨日结算
        $current_month_order_num = 0; //本月订单数
        $current_month_paid_commission = 0; //本月成交预估
        $current_month_settle_commission = 0; //本月成交预估
        $before_month_order_num = 0; //上月订单数
        $before_month_paid_commission = 0; //上月成交预估
        $before_month_settle_commission = 0; //上月成交预估

        /**
         * 各时间段分平台统计
         */
        $today_data = $yesterday_data = $current_month_data = $before_month_data = array(
            'taobao_order_num' => 0, //淘宝订单数
            'taobao_paid_commission' => 0, //淘宝成交预估
            'taobao_settle_commission' => 0, //淘宝结算预估
            'pinduoduo_order_num' => 0, //拼多多订单数
            'pinduoduo_paid_commission' => 0, //拼多多成交预估
            'pinduoduo_settle_commission' => 0, //拼多多结算预估
            'jingdong_order_num' => 0, //京东订单数
            'jingdong_paid_commission' => 0, //京东成交预估
            'jingdong_settle_commission' => 0, //京东结算预估
        );

        /**
         * 最近2个月下单数据
         */
        $where = array(
            'user_id' => $this->user_id,
            'pay_status' => array('in', array('paid', 'settle', 'success')),
            'add_time' => array('egt', $before_month_zero)
        );

        $field = 'commission,zm_subsidy_money,add_time,order_source_mall_platform';
        $last_2_month_paid_orders = M('order_commission')->where($where)->field($field)->select();
        foreach ($last_2_month_paid_orders as $key => $order_commission) {
            $date = null;

            if ($order_commission['add_time'] >= $current_month_zero) {
                $current_month_order_num++;
                $current_month_paid_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];

                /**
                 * 最近2天的下单数据
                 */
                if ($order_commission['add_time'] >= $today_zero) {
                    $today_order_num++;
                    $today_paid_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];

                    $date = 'today_data';
                } else if ($order_commission['add_time'] >= $yesterday_zero) {
                    $yesterday_order_num++;
                    $yesterday_paid_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];

                    $date = 'yesterday_data';
                }

                if (isset($date)) {
                    if ('pinduoduo' == $order_commission['order_source_mall_platform']) {
                        ${$date}['pinduoduo_order_num'] ++;
                        ${$date}['pinduoduo_paid_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
                    } else if ('jingdong' == $order_commission['order_source_mall_platform']) {
                        ${$date}['jingdong_order_num'] ++;
                        ${$date}['jingdong_paid_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
                    } else {
                        ${$date}['taobao_order_num'] ++;
                        ${$date}['taobao_paid_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
                    }
                }

                $month_date = 'current_month_data';
            } else {
                $before_month_order_num++;
                $before_month_paid_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];

                $month_date = 'before_month_data';
            }

            if ('pinduoduo' == $order_commission['order_source_mall_platform']) {
                ${$month_date}['pinduoduo_order_num'] ++;
                ${$month_date}['pinduoduo_paid_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
            } else if ('jingdong' == $order_commission['order_source_mall_platform']) {
                ${$month_date}['jingdong_order_num'] ++;
                ${$month_date}['jingdong_paid_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
            } else {
                ${$month_date}['taobao_order_num'] ++;
                ${$month_date}['taobao_paid_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
            }
        }

        /**
         * 最近2个月结算数据
         */
        $where = array(
            'user_id' => $this->user_id,
            'pay_status' => 'settle',
            'settle_time' => array('egt', $before_month_zero)
        );

        $field = 'commission,zm_subsidy_money,settle_time,order_source_mall_platform';
        $last_2_month_settle_orders = M('order_commission')->where($where)->field($field)->select();
        foreach ($last_2_month_settle_orders as $key => $order_commission) {
            $date = null;

            if ($order_commission['settle_time'] >= $current_month_zero) {
                $current_month_settle_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];

                /**
                 * 最近2天的结算数据
                 */
                if ($order_commission['settle_time'] >= $today_zero) {
                    $today_settle_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];

                    $date = 'today_data';
                } else if ($order_commission['settle_time'] >= $yesterday_zero) {
                    $yesterday_settle_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
                    $date = 'yesterday_data';
                }

                if (isset($date)) {
                    if ('pinduoduo' == $order_commission['order_source_mall_platform']) {
                        ${$date}['pinduoduo_settle_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
                    } else if ('jingdong' == $order_commission['order_source_mall_platform']) {
                        ${$date}['jingdong_settle_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
                    } else {
                        ${$date}['taobao_settle_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
                    }
                }

                $month_date = 'current_month_data';
            } else {
                $before_month_settle_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];

                $month_date = 'before_month_data';
            }

            if ('pinduoduo' == $order_commission['order_source_mall_platform']) {
                ${$month_date}['pinduoduo_settle_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
            } else if ('jingdong' == $order_commission['order_source_mall_platform']) {
                ${$month_date}['jingdong_settle_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
            } else {
                ${$month_date}['taobao_settle_commission'] += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
            }
        }

        $data = array(
            'total_settle_commission' => format_view_money($total_settle_commission),
            'today_order_num' => $today_order_num,
            'today_paid_commission' => format_view_money($today_paid_commission),
            'today_settle_commission' => format_view_money($today_settle_commission),
            'yesterday_order_num' => $yesterday_order_num,
            'yesterday_paid_commission' => format_view_money($yesterday_paid_commission),
            'yesterday_settle_commission' => format_view_money($yesterday_settle_commission),
            'current_month_order_num' => $current_month_order_num,
            'current_month_paid_commission' => format_view_money($current_month_paid_commission),
            'current_month_settle_commission' => format_view_money($current_month_settle_commission),
            'before_month_order_num' => $before_month_order_num,
            'before_month_paid_commission' => format_view_money($before_month_paid_commission),
            'before_month_settle_commission' => format_view_money($before_month_settle_commission),
            'today_data' => $today_data,
            'yesterday_data' => $yesterday_data,
            'current_month_data' => $current_month_data,
            'before_month_data' => $before_month_data,
        );
        $this->output('ok', 'success', $data);
    }
}