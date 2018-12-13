<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/31
 * Time: 17:38
 */

namespace Partner\Controller;

/**
 * BI统计
 * Class BISystemController
 *
 * @package Admin\Controller
 */
class BISystemController extends CommonController
{

    public function index() {

        if (IS_AJAX) {
            $partner_id = $this->partner_id;
            $date_time  = strtotime(date('Y-m-d', time()));

            $data = [];
            //获取新用户数量
            $new_user_order_num = M('user')
                ->where([
                    'add_time'         => ['between', [$date_time, $date_time + 86399]],
                    'first_order_time' => ['between', [$date_time, $date_time + 86399]],
                ])
                ->group('partner_id')
                ->getField('partner_id,count(id) as new_user_order_qty');

            foreach ($new_user_order_num as $user_partner_id => $new_user_order_qty) {
                $data[$user_partner_id]['new_user_order_qty'] = $new_user_order_qty;
            }

            //获取订单数和用户数
            $orders = M('order')->field('partner_id,user_id')
                ->where([
                    'add_time'   => ['between', [$date_time, $date_time + 86399]],
                    'pay_status' => ['in', ['paid', 'settle', 'success']]
                ])
                ->group("order_sn")
                ->select();
            $users  = array(); //暂存所有用户数据
            foreach ($orders as $order) {
                $data[$order['partner_id']]['order_qty'] += 1;
                $users[$order['partner_id']][]           = $order['user_id'];
            }
            foreach ($users as $user_partner_id => $partner_users) { //计算各合作伙伴下单用户数
                $data[$user_partner_id]['order_paid_user_num'] = count(array_unique($partner_users));
            }

            //获取成交数据
            $data_paid_list = M('order_commission')
                ->field('partner_id,total_money,commission,zm_subsidy_money,source')
                ->where([
                    'add_time'   => ['between', [$date_time, $date_time + 86399]],
                    'pay_status' => ['in', ['paid', 'settle', 'success']]
                ])
                ->select();

            foreach ($data_paid_list as $key => $order) {
                if ($order['source'] == "self") {
                    //订单实得总佣金（已去除10%手续费）
                    $order_commission_total                              = $order['commission'] / (C('COMMISSION_RATE.base_commission_rate') / 100);
                    $data[$order['partner_id']]['order_pay_total']       += $order['total_money'];
                    $data[$order['partner_id']]['order_commission_paid'] += $order_commission_total;
                    $data[$order['partner_id']]['profit_paid']           += $order_commission_total;
                }
            }

            foreach ($data_paid_list as $key => $order) {
                $data[$order['partner_id']]['user_commission_paid'] += $order['commission'] + $order['zm_subsidy_money'];
                $data[$order['partner_id']]['profit_paid']          -= ($order['commission'] + $order['zm_subsidy_money']);
            }

            unset($data_paid_list);

            /**
             * 查询订单结算相关
             */
            $data_settle_list = M('order_commission')
                ->field('partner_id,commission,zm_subsidy_money,source')
                ->where([
                    'settle_time' => ['between', [$date_time, $date_time + 86399]],
                    'pay_status'  => 'settle'
                ])
                ->select();

            foreach ($data_settle_list as $key => $order) {
                if ($order['source'] == "self") {
                    //订单实得总佣金（已去除10%手续费）
                    $order_commission_total                                = $order['commission'] / (C('COMMISSION_RATE.base_commission_rate') / 100);
                    $data[$order['partner_id']]['order_commission_settle'] += $order_commission_total;
                    $data[$order['partner_id']]['profit_settle']           += $order_commission_total;
                }
            }

            foreach ($data_settle_list as $key => $order) {
                $data[$order['partner_id']]['user_commission_settle'] += $order['commission'] + $order['zm_subsidy_money'];
                $data[$order['partner_id']]['profit_settle']          -= ($order['commission'] + $order['zm_subsidy_money']);
            }

            unset($data_settle_list);

            $js_data = $data[$partner_id];

            unset($data);

            // 获取用户今日数据
            $condition['add_time'] = ['between', [$date_time, $date_time + 86399]];
            if ($partner_id) {
                $condition['partner_id'] = $partner_id;
            }
            // 获取今日新增用户数
            $get_user_num            = M('user')
                ->field('count(id) as new_user_qty')
                ->where($condition)
                ->find();
            $js_data['new_user_qty'] = $get_user_num['new_user_qty'];

            //获取今日各级别用户升级数
            $con['level_upgrade_time'] = ['between', [$date_time, $date_time + 86399]];
            if ($partner_id) {
                $con['partner_id'] = $partner_id;
            }
            $user_update_level             = M('user')
                ->field('count(id) as update_num,level')
                ->where($con)
                ->group('level')
                ->select();
            foreach ($user_update_level as $row){
                $js_data['update_level_'.$row['level']]   = $row['update_num'];
            }

            // 获取当日拉新排名前五十的用户
            $where['u.add_time'] = ['between', [$date_time, $date_time+86399]];
            if ($partner_id) {
                $where['u.partner_id'] = $partner_id;
            }
            $rank_list = M('user')
                ->alias("u")
                ->field('count(u.id) AS num,gu.nickname,gu.mobile,p.partner_name')
                ->join('left join zm_user gu ON gu.id = u.group_leader_id')
                ->join('left join zm_partner p ON p.id = gu.partner_id')
                ->where($where)
                ->group('u.group_leader_id')
                ->order('num desc')
                ->limit(50)
                ->select();

            $this->success(['data'=>$js_data,'rankList'=>$rank_list]);
        }
        $this->display();
    }

    /**
     * 根据时间获取消费排行
     */
    public function history() {
        $start_date = date('Y-m-d', time() - 86400*7);
        $end_date = date('Y-m-d', time() - 86400);

        $this->assign([
            'start_date' => $start_date,
            'end_date' => $end_date

        ]);
        if (IS_AJAX) {
            //获取纵览数据
            $start_date = I('get.start_date', '', 'trim') ? I('get.start_date', '', 'trim') : $start_date;
            $end_date = I('get.end_date', '', 'trim') ? I('get.end_date', '', 'trim') : $end_date;
            $partner_id = $this->partner_id;
            $mall_platform = I('get.mall_platform', 'all', 'trim');

            //获取折线图x轴坐标 订单增长曲线
            $date_list = [];
            $i         = 0;
            while (true) {
                $temp_date   = date('Y-m-d', strtotime($end_date) - 86400 * $i);
                $date_list[] = $temp_date;
                if ($start_date == $temp_date) {
                    break;
                }
                $i++;
            }
            $date_list = array_reverse($date_list);

            $model = M('bi_data_order')->field('sum(order_qty) as order_qty,sum(new_user_order_qty) as new_user_order_qty,sum(order_pay_total) as order_pay_total,sum(order_paid_user_num) as order_paid_user_num,sum(order_commission_paid) as order_commission_paid,sum(order_commission_settle) as order_commission_settle,sum(user_commission_paid) as user_commission_paid,sum(user_commission_settle) as user_commission_settle,sum(profit_paid) as profit_paid,sum(profit_settle) as profit_settle,order_date');
            $condition['order_date'] = ['between', [reset($date_list), end($date_list)]];

            if ($partner_id) {
                $condition['partner_id'] = $partner_id;
            }

            if ('all' != $mall_platform) {
                $condition['order_source_mall_platform'] = $mall_platform;
            }

            $order_list = $model->where($condition)->group('order_date')->order('order_date asc')->select();

            //获取选中日期总数据
            $data = [];
            foreach ($order_list as $val){
                $data['order_qty']               += $val['order_qty'];
                $data['new_user_order_qty']      += $val['new_user_order_qty'];
                $data['order_pay_total']         += $val['order_pay_total'];
                $data['order_paid_user_num']     += $val['order_paid_user_num'];
                $data['order_commission_paid']   += $val['order_commission_paid'];
                $data['order_commission_settle'] += $val['order_commission_settle'];
                $data['user_commission_paid']    += $val['user_commission_paid'];
                $data['user_commission_settle']  += $val['user_commission_settle'];
                $data['profit_paid']              += $val['profit_paid'];
                $data['profit_settle']            += $val['profit_settle'];
            }

            //获取折线图数据
            $temp_array = [];
            foreach ($order_list as $val) {
                $temp_array['order_qty'][$val['order_date']]               = $val['order_qty'];
                $temp_array['new_user_order_qty'][$val['order_date']]      = $val['new_user_order_qty'];
                $temp_array['order_pay_total'][$val['order_date']]         = $val['order_pay_total'];
                $temp_array['order_paid_user_num'][$val['order_date']]     = $val['order_paid_user_num'];
                $temp_array['order_commission_paid'][$val['order_date']]   = $val['order_commission_paid'];
                $temp_array['order_commission_settle'][$val['order_date']] = $val['order_commission_settle'];
                $temp_array['user_commission_paid'][$val['order_date']]    = $val['user_commission_paid'];
                $temp_array['user_commission_settle'][$val['order_date']]  = $val['user_commission_settle'];
                $temp_array['profit_paid'][$val['order_date']]             = $val['profit_paid'];
                $temp_array['profit_settle'][$val['order_date']]           = $val['profit_settle'];
                $temp_array['user_average_price'][$val['order_date']]      = sprintf("%.2f",$val['order_pay_total'] / $val['order_paid_user_num']);
                $temp_array['user_average_num'][$val['order_date']]        = sprintf("%.2f",floatval($val['order_qty'] / $val['order_paid_user_num']));
                $temp_array['order_average_price'][$val['order_date']]     = sprintf("%.2f",floatval($val['order_pay_total'] / $val['order_qty']));
                $temp_array['user_average_profit'][$val['order_date']]     = sprintf("%.2f",floatval($val['profit_paid'] / $val['order_paid_user_num']));
                $temp_array['order_average_profit'][$val['order_date']]    = sprintf("%.2f",floatval($val['profit_paid'] / $val['order_qty']));
                $temp_array['commission_rate'][$val['order_date']]         = sprintf("%.2f",floatval($val['order_commission_paid'] / $val['order_pay_total'])*100);
            }

            $num_list = [];
            foreach ($date_list as $d) {
                $num_list['order_qty'][]               = isset($temp_array['order_qty'][$d]) ? $temp_array['order_qty'][$d] : 0;
                $num_list['new_user_order_qty'][]      = isset($temp_array['new_user_order_qty'][$d]) ? $temp_array['new_user_order_qty'][$d] : 0;
                $num_list['order_pay_total'][]         = isset($temp_array['order_pay_total'][$d]) ? $temp_array['order_pay_total'][$d] : 0;
                $num_list['order_paid_user_num'][]     = isset($temp_array['order_paid_user_num'][$d]) ? $temp_array['order_paid_user_num'][$d] : 0;
                $num_list['order_commission_paid'][]   = isset($temp_array['order_commission_paid'][$d]) ? $temp_array['order_commission_paid'][$d] : 0;
                $num_list['order_commission_settle'][] = isset($temp_array['order_commission_settle'][$d]) ? $temp_array['order_commission_settle'][$d] : 0;
                $num_list['user_commission_paid'][]    = isset($temp_array['user_commission_paid'][$d]) ? $temp_array['user_commission_paid'][$d] : 0;
                $num_list['user_commission_settle'][]  = isset($temp_array['user_commission_settle'][$d]) ? $temp_array['user_commission_settle'][$d] : 0;
                $num_list['profit_paid'][]             = isset($temp_array['profit_paid'][$d]) ? $temp_array['profit_paid'][$d] : 0;
                $num_list['profit_settle'][]           = isset($temp_array['profit_settle'][$d]) ? $temp_array['profit_settle'][$d] : 0;
                $num_list['user_average_price'][]      = isset($temp_array['user_average_price'][$d]) ? $temp_array['user_average_price'][$d] : 0;
                $num_list['user_average_num'][]        = isset($temp_array['user_average_num'][$d]) ? $temp_array['user_average_num'][$d] : 0;
                $num_list['order_average_price'][]     = isset($temp_array['order_average_price'][$d]) ? $temp_array['order_average_price'][$d] : 0;
                $num_list['user_average_profit'][]     = isset($temp_array['user_average_profit'][$d]) ? $temp_array['user_average_profit'][$d] : 0;
                $num_list['order_average_profit'][]    = isset($temp_array['order_average_profit'][$d]) ? $temp_array['order_average_profit'][$d] : 0;
                $num_list['commission_rate'][]         = isset($temp_array['commission_rate'][$d]) ? $temp_array['commission_rate'][$d] : 0;
            }

            $this->success(['data' => $data,'dateList'=>$date_list,'numList'=>$num_list]);
        }
        $this->display();
    }

    /**
     * 用户统计
     */
    public function userData()
    {
        $start_date = date("Y-m-d",time()-7*86400);
        $end_date = date("Y-m-d",time()-86400);

        $this->assign([
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        if (IS_AJAX) {
            $start_date = I('get.start_date', '', 'trim') ? I('get.start_date', '', 'trim') : $start_date;
            $end_date   = I('get.end_date', '', 'trim')   ? I('get.end_date', '', 'trim')   : $end_date;
            $partner_id = $this->partner_id;

            //获取方块数据
            $condition['record_date'] = ['between', [$start_date, $end_date]];
            if ($partner_id) {
                $condition['partner_id'] = $partner_id;
            }
            $temp = M('bi_data_user')->field('sum(new_user_qty) as new_user_qty')
                ->where($condition)->find();
            $data['new_user_qty'] = $temp['new_user_qty'];

            $condition_level['record_date'] = $end_date;
            if ($partner_id) {
                $condition_level['partner_id'] = $partner_id;
            }
            $temp_level = M('bi_data_user')->field('sum(level_zero) as level_zero,sum(level_one) as level_one,sum(level_two) as level_two,sum(level_three) as level_three,sum(level_four) as level_four,sum(level_five) as level_five')
                ->where($condition_level)->find();
            $data = array_merge($data,$temp_level);
            //获取折线图x轴坐标
            $date_list = [];
            $i         = 0;
            while (true) {
                $temp_date   = date('Y-m-d', strtotime($end_date) - 86400 * $i);
                $date_list[] = $temp_date;
                if ($start_date == $temp_date) {
                    break;
                }
                $i++;
            }
            $date_list = array_reverse($date_list);

            //获取折线图数据
            $charts_wherep['record_date'] = ['between', [reset($date_list), end($date_list)]];
            if ($partner_id) {
                $charts_wherep['partner_id'] = $partner_id;
            }
            $nUserList = M('bi_data_user')->field('record_date,sum(new_user_qty) as new_user_qty,sum(level_zero) as level_zero,sum(level_one) as level_one,sum(level_two) as level_two,sum(level_three) as level_three,sum(level_four) as level_four,sum(level_five) as level_five')
                ->where($charts_wherep)
                ->group('record_date')
                ->order('record_date asc')
                ->select();

            $temp_array = [];
            foreach ($nUserList as $val) {
                $temp_array['new_user_qty'][$val['record_date']] = $val['new_user_qty'];
                $temp_array['level_zero'][$val['record_date']]   = $val['level_zero'];
                $temp_array['level_one'][$val['record_date']]    = $val['level_one'];
                $temp_array['level_two'][$val['record_date']]    = $val['level_two'];
                $temp_array['level_three'][$val['record_date']]  = $val['level_three'];
                $temp_array['level_four'][$val['record_date']]   = $val['level_four'];
                $temp_array['level_five'][$val['record_date']]   = $val['level_five'];
            }

            $num_list = [];
            foreach ($date_list as $d) {
                $num_list['new_user_qty'][] = isset($temp_array['new_user_qty'][$d]) ? $temp_array['new_user_qty'][$d] : 0;
                $num_list['level_zero'][]   = isset($temp_array['level_zero'][$d]) ? $temp_array['level_zero'][$d] : 0;
                $num_list['level_one'][]    = isset($temp_array['level_one'][$d]) ? $temp_array['level_one'][$d] : 0;
                $num_list['level_two'][]    = isset($temp_array['level_two'][$d]) ? $temp_array['level_two'][$d] : 0;
                $num_list['level_three'][]  = isset($temp_array['level_three'][$d]) ? $temp_array['level_three'][$d] : 0;
                $num_list['level_four'][]   = isset($temp_array['level_four'][$d]) ? $temp_array['level_four'][$d] : 0;
                $num_list['level_five'][]   = isset($temp_array['level_five'][$d]) ? $temp_array['level_five'][$d] : 0;
            }

            // 获取当日拉新排名前五十的用户
            $where['u.add_time'] = ['between', [strtotime($start_date), strtotime(date('Y-m-d H:i:s', strtotime($end_date) + 86399))]];
            $where['u.group_leader_id'] = ['NEQ',0];
            if ($partner_id) {
                $where['u.partner_id'] = $partner_id;
            }
            $rank_list = M('user')
                ->alias("u")
                ->field('count(u.id) AS num,gu.id,gu.nickname,gu.mobile,p.partner_name')
                ->join('left join zm_user gu ON gu.id = u.group_leader_id')
                ->join('left join zm_partner p ON p.id = gu.partner_id')
                ->where($where)
                ->group('u.group_leader_id')
                ->order('num desc')
                ->limit(50)
                ->select();

            $this->success(array('data'=>$data,'dateList' => $date_list,'numList' => $num_list,'rankList'=>$rank_list));
        }
        $this->display();
    }

    /**
     * 提现统计
     */
    public function withdraw()
    {
        if (IS_AJAX) {
            //获取 最近三十天日期
            $date_list = [];
            for ($i = 60; $i > 0; $i--) {
                $date_list[] = date('Y-m-d', time() - 86400 * $i);
            }

            $model = M('withdraw');
            $withdraw_list = $model->field('FROM_UNIXTIME(add_time, "%Y-%m-%d") as add_date,sum(money) as total_money')->where(
                [
                    'add_time' => ['between', [strtotime(reset($date_list)), strtotime(end($date_list))]],
                    'partner_id' =>$this->partner_id
                ]
            )
                ->group('add_date')
                ->order('add_date asc')
                ->select();

            $temp_array = [];
            foreach ($withdraw_list as $val) {
                $temp_array[$val['add_date']] = $val['total_money'];
            }

            $data_list = [];
            for ($i = 60; $i > 0; $i--) {
                $withdraw_date = date('Y-m-d', time() - 86400 * $i);
                $data_list[] = isset($temp_array[$withdraw_date]) ? $temp_array[$withdraw_date] : 0;
            }

            $this->success(['dateList' => $date_list, 'dataList' => $data_list]);
        }
        $this->display();
    }
}