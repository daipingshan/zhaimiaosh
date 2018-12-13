<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 2018/3/29
 * Time: 11:48
 */

namespace Data\Controller;

use Common\Controller\CommonBaseController;

class DataController extends CommonBaseController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * 处理用户数据
     *
     * @param string $date
     */
    public function processUserData($date = '') {
        $yesterday       = $date ? $date : date('Y-m-d', time() - 84600);
        $yesterday_start = strtotime($yesterday);
        $yesterday_end   = $yesterday_start + 86399;

        $partner_list = M('partner')->field('id')->order('id')->select();
        $add_data     = [];
        foreach ($partner_list as $partner) {
            $data         = [];
            $get_user_num = M('user')->field('count(id) as new_user_qty')
                ->where([
                    'add_time'   => ['between', [$yesterday_start, $yesterday_end]],
                    'partner_id' => $partner['id']
                ])
                ->find();
            $temp_data    = M('user')->field('count(id) as num,level')->where([
                'partner_id' => $partner['id']
            ])->group('level')->select();

            $data['level_zero']  = 0;
            $data['level_one']   = 0;
            $data['level_two']   = 0;
            $data['level_three'] = 0;
            $data['level_four']  = 0;
            $data['level_five']  = 0;
            foreach ($temp_data as $val) {
                $field = "level_zero";
                switch ($val['level']) {
                    case 1:
                        $field = "level_one";
                        break;
                    case 2:
                        $field = "level_two";
                        break;
                    case 3:
                        $field = "level_three";
                        break;
                    case 4:
                        $field = "level_four";
                        break;
                    case 5:
                        $field = "level_five";
                        break;
                    default:
                        //...
                        break;
                }
                $data[$field] = $val['num'];
            }

            $reg_result = M('user')->field('count(id) as reg_num,register_client_platform')->where([
                'add_time'   => ['between', [$yesterday_start, $yesterday_end]],
                'partner_id' => $partner['id']
            ])->group('register_client_platform')->select();

            $reg_temp_data = [];
            foreach ($reg_result as $key => $val) {
                $reg_temp_data[$val['register_client_platform']] = $val['reg_num'];
            }
            $data['reg_ios_qty']          = isset($reg_temp_data['ios']) ? $reg_temp_data['ios'] : 0;
            $data['reg_android_qty']      = isset($reg_temp_data['android']) ? $reg_temp_data['android'] : 0;
            $data['reg_mini_program_qty'] = isset($reg_temp_data['mini_program']) ? $reg_temp_data['mini_program'] : 0;
            $data['new_user_qty']         = $get_user_num['new_user_qty'];
            $data['partner_id']           = $partner['id'];
            $data['record_date']          = $yesterday;

            $add_data[] = $data;
        }

        $res = M('bi_data_user')->addAll(array_values($add_data));
        if ($res) {
            echo "user data processing complete \n";
        } else {
            echo "user data processing failed \n";
        }
    }

    /**
     * 处理订单数据汇总
     *
     * @param string $date
     */
    public function processOrder($date = "") {
        $yesterday       = $date ? $date : date("Y-m-d", time() - 86400);
        $yesterday_start = strtotime($yesterday);
        $yesterday_end   = $yesterday_start + 86399;

        $item_data = [
            'partner_id'                 => 0,
            'order_qty'                  => 0,
            'order_paid_user_num'        => 0,
            'new_user_order_qty'         => 0,
            'order_pay_total'            => 0,
            'order_commission_paid'      => 0,
            'order_commission_settle'    => 0,
            'user_commission_paid'       => 0,
            'user_commission_settle'     => 0,
            'profit_paid'                => 0,
            'profit_settle'              => 0,
            'order_source_mall_platform' => '',
            'order_date'                 => $yesterday
        ];
        //初始化记录
        $partner_list = M('partner')->field('id')->select();

        $data = [];
        foreach ($partner_list as $partner) {
            $item_data['partner_id']                 = $partner['id'];
            $item_data['order_source_mall_platform'] = 'taobao';
            $data[$partner['id']]['taobao']          = $item_data;
            $item_data['order_source_mall_platform'] = 'pinduoduo';
            $data[$partner['id']]['pinduoduo']       = $item_data;
            $item_data['order_source_mall_platform'] = 'jingdong';
            $data[$partner['id']]['jingdong']       = $item_data;
        }

        //获取新用户订单数
        $new_user_order_infos = M('user')
            ->where([
                'add_time'         => ['between', [$yesterday_start, $yesterday_end]],
                'first_order_time' => ['between', [$yesterday_start, $yesterday_end]]
            ])
            ->group('partner_id,register_client_platform')
            ->field('partner_id,count(id) as new_user_order_qty,register_client_platform')
            ->select();

        foreach ($new_user_order_infos as $new_user_order_info) {
            $partner_id = $new_user_order_info['partner_id'];

            if ($new_user_order_info['register_client_platform'] == 'mini_program') {
                $data[$partner_id]['pinduoduo']['new_user_order_qty'] = $new_user_order_info['new_user_order_qty'];
                $data[$partner_id]['jingdong']['new_user_order_qty'] = $new_user_order_info['new_user_order_qty'];
            } else {
                if (isset($data[$partner_id]['taobao']['new_user_order_qty'])) {
                    $data[$partner_id]['taobao']['new_user_order_qty'] += $new_user_order_info['new_user_order_qty'];
                } else {
                    $data[$partner_id]['taobao']['new_user_order_qty'] = $new_user_order_info['new_user_order_qty'];
                }
            }
        }

        /**
         * 获取订单数和用户数
         */
        $orders = M('order')->field('partner_id,user_id,order_source_mall_platform')
            ->where([
                'add_time'   => ['between', [$yesterday_start, $yesterday_end]],
                'pay_status' => ['in', ['paid', 'settle', 'success']]
            ])
            ->group("order_sn")
            ->select();

        $users = array(); //暂存所有用户数据
        foreach ($orders as $order) {
            $mall_platform = $order['order_source_mall_platform'];

            $data[$order['partner_id']][$mall_platform]['order_qty'] += 1;
            $users[$mall_platform][$order['partner_id']][]           = $order['user_id'];
        }

        foreach ($users as $mall_platform => $val) { //计算各合作伙伴下单用户数
            foreach ($val as $partner_id => $partners) {
                $data[$partner_id][$mall_platform]['order_paid_user_num'] = count(array_unique($partners));
            }
        }
        unset($orders);
        unset($users);

        /**
         * 查询订单成交相关
         */
        $data_paid_list = M('order_commission')
            ->field('partner_id,total_money,commission,zm_subsidy_money,source,order_source_mall_platform')
            ->where([
                'add_time'   => ['between', [$yesterday_start, $yesterday_end]],
                'pay_status' => ['in', ['paid', 'settle', 'success']]
            ])
            ->select();

        foreach ($data_paid_list as $key => $order) {
            //订单实得总佣金（淘宝京东已去除10%手续费）
            $order_commission_total = $order['commission'] / (C('COMMISSION_RATE.base_commission_rate') / 100);
            $mall_platform = $order['order_source_mall_platform']; //商城平台 淘宝、拼多多或京东
            if ($mall_platform == 'pinduoduo') {
                if ($order['source'] == "self") {
                    $data[$order['partner_id']]['pinduoduo']['order_pay_total']       += $order['total_money'];
                    $data[$order['partner_id']]['pinduoduo']['order_commission_paid'] += $order_commission_total;
                }
                //利润
                if ($order['source'] == "partner") {
                    $data[$order['partner_id']]['pinduoduo']['profit_paid'] += $order['commission'];
                }
            } else {
                if ($order['source'] == "self") {
                    $data[$order['partner_id']][$mall_platform]['order_pay_total']       += $order['total_money'];
                    $data[$order['partner_id']][$mall_platform]['order_commission_paid'] += $order_commission_total;
                    $data[$order['partner_id']][$mall_platform]['profit_paid']           += $order_commission_total;
                }
            }
        }

        foreach ($data_paid_list as $key => $order) {
            $mall_platform = $order['order_source_mall_platform']; //商城平台 淘宝、拼多多或京东
            if ($mall_platform == 'pinduoduo') {
                if ($order['source'] != "partner") {
                    $data[$order['partner_id']]['pinduoduo']['user_commission_paid'] += $order['commission'] + $order['zm_subsidy_money'];
                }
            } else {
                $data[$order['partner_id']][$mall_platform]['user_commission_paid'] += $order['commission'] + $order['zm_subsidy_money'];
                $data[$order['partner_id']][$mall_platform]['profit_paid']          -= ($order['commission'] + $order['zm_subsidy_money']);
            }
        }

        unset($data_paid_list);

        /**
         * 查询订单结算相关
         */
        $data_settle_list = M('order_commission')
            ->field('partner_id,commission,zm_subsidy_money,source,order_source_mall_platform')
            ->where([
                'settle_time' => ['between', [$yesterday_start, $yesterday_end]],
                'pay_status'  => 'settle'
            ])
            ->select();

        foreach ($data_settle_list as $key => $order) {
            //订单实得总佣金（已去除10%手续费）
            $order_commission_total = $order['commission'] / (C('COMMISSION_RATE.base_commission_rate') / 100);
            $mall_platform = $order['order_source_mall_platform']; //商城平台 淘宝、拼多多或京东
            if ($mall_platform == 'pinduoduo') {
                if ($order['source'] == "self") {
                    $data[$order['partner_id']]['pinduoduo']['order_commission_settle'] += $order_commission_total;
                }
                //利润
                if ($order['source'] == "partner") {
                    $data[$order['partner_id']]['pinduoduo']['profit_settle'] += $order['commission'];
                }
            } else {
                if ($order['source'] == "self") {
                    $data[$order['partner_id']][$mall_platform]['order_commission_settle'] += $order_commission_total;
                    $data[$order['partner_id']][$mall_platform]['profit_settle']           += $order_commission_total;
                }
            }
        }

        foreach ($data_settle_list as $key => $order) {
            $mall_platform = $order['order_source_mall_platform']; //商城平台 淘宝、拼多多或京东
            if ($mall_platform == 'pinduoduo') {
                if ($order['source'] != "partner") {
                    $data[$order['partner_id']]['pinduoduo']['user_commission_settle'] += $order['commission'] + $order['zm_subsidy_money'];
                }
            } else {
                $data[$order['partner_id']][$mall_platform]['user_commission_settle'] += $order['commission'] + $order['zm_subsidy_money'];
                $data[$order['partner_id']][$mall_platform]['profit_settle']          -= ($order['commission'] + $order['zm_subsidy_money']);
            }
        }

        unset($data_settle_list);
        $insert_data = [];
        foreach ($data as $val) {
            foreach ($val as $mall_platform => $data_order) {
                if ($data_order['order_qty'] > 0) {
                    $insert_data[] = $data_order;
                }
            }
        }

        M('bi_data_order')->addAll($insert_data);
        echo $yesterday . " order data processing complete \n";
    }

}