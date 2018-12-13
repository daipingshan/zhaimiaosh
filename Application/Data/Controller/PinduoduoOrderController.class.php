<?php
/**
 * 拼多多订单数据
 * Author: dongguangqi
 */

namespace Data\Controller;
use Common\Controller\CommonBaseController;
use Common\Org\Http;

/**
 *
 *
 * @package Data\Controller
 */
class PinduoduoOrderController extends CommonBaseController {
    protected $order_status = array(
        '-1' => 'fail',
        '0' => 'paid',
        '1' => 'paid',
        '2' => 'paid',
        '3' => 'settle',
        '4' => 'refund',
        '5' => 'settle',
        '8' => 'fail'
    );

     public function __construct() {
        parent::__construct();
    }

    /**
     * 获取拼多多新订单
     */
    public function getNewOrder() {
        $now = time();
        $last_order_add_time = S('DATA_pinduoduo_last_order_add_time');
        if (!is_numeric($last_order_add_time)) {
            $last_new_order = M('order')->where(array('order_source_mall_platform' => 'pinduoduo'))->order('add_time desc')->find();
            $last_order_add_time = isset($last_order_add_time['add_time']) ? $last_order_add_time['add_time'] : $now - 86399;
        }
        if($now - $last_order_add_time > 86399){
            $last_order_add_time =  $now - 86399;
        }

        $params = array(
            'start_update_time' => $last_order_add_time, //最近90天内多多进宝商品订单更新时间--查询时间开始
            'end_update_time' => $now, //最近90天内多多进宝商品订单更新时间--查询时间结束
            'page_size' => 100, //返回的每页结果数，默认为20，范围为10到100
            'page' => 1
        );

        $res = $this->_getPddOrderListIncrement($params, true);

        if ($res['total_count'] <= $params['page_size']) {
            $orders = $res['order_list'];
            krsort($orders);
            echo 'Page 1:' . $this->_doGetNewOrder($orders);
        } else {
            for ($page=ceil($res['total_count'] / $params['page_size']); $page >= 1 ; $page--) {
                $params['page'] = $page;
                $orders = $res['order_list'];
                krsort($orders);
                echo 'Page ' . $page . ':' . $this->_doGetNewOrder($orders) . "\n";
            }
        }

        echo ' over';
    }

    /**
     * 执行获取拼多多新订单
     */
    private function _doGetNewOrder($orders) {
        if (empty($orders)) {
            return 'empty order';
        }

        $model = new \Think\Model();

        $order_sns = array(); //所有订单号
        $pids = array(); //所有PID
        foreach ($orders as $key => $order) {
            $order_sns[] = $order['order_sn'];
            $pids[] = $order['p_id'];
        }

        //新订单
        //查出已存在的和未支付的去除
        //根据PID查询用户信息
        //根据用户是否有上线、是否有团长、是否有团长的团长，来拆分成多条子订单。一次插入多条记录：主订单和子订单
        //拼多多订单状态： -1 未支付; 0-已支付；1-已成团；2-确认收货；3-审核成功；4-审核失败（不可提现）；5-已经结算；8-非多多进宝商品（无佣金订单）

        $fields = 'pdd_pid, id, inviter_id, inviter_pdd_pid, level, first_order_time, group_leader_id, group_leader_parent_id, partner_id';
        $pids = array_unique($pids);
        $users = M('user')->where(array('pdd_pid' => array('in', $pids)))->getField($fields);
        $inviter_users = M('user')->where(array('pdd_pid' => array('in', array_column($users, 'inviter_pdd_pid'))))->getField($fields);
        $users = array_merge($users, $inviter_users); //$users为包含了订单用户和订单用户的邀请人

        $exist_order_sns = M('order')->where(array('order_sn' => array('in', $order_sns)))->getField('order_sn', true);

        /**
         * 删除已存在的订单
         */
        foreach ($orders as $key => $order) {
            if (in_array($order['order_sn'], $exist_order_sns) || -1 == $order['order_status']) {
                unset($orders[$key]); //去除已存在的订单或未支付的订单
                continue;
            }
        }
        if (empty($orders)) {
            return 'no new order';
        }

        //所有合伙人的主账号
        $partners_master_user_id = M('partner')->getField('id,master_user_id');

        $add_orders = array(); //待添加的主订单
        $add_order_commissions = array(); //待添加的子订单
        $now = time();
        $model->startTrans();
        try {
            foreach ($orders as $key => $order) {
                $pid = $order['p_id'];
                $chain_commission = 0; //已结给各链条上分成的佣金总额。去除掉该总额，剩余部分给合伙人

                if (!isset($users[$pid])) {
                    continue;
                }

                $order_pay_time = intval($order['order_pay_time']);
                if ($order_pay_time == 0) { //处理由于拼多多主从同步问题导致有可能没有order_pay_time的情况
                    $order_pay_time = intval($order['order_create_time']);
                }
                $add_time = $last_order_add_time = $order_pay_time;
                if (0 == $users[$pid]['first_order_time']) {
                    $users[$pid]['first_order_time'] = $add_time;
                    M('user')->where('id=' . $users[$pid]['id'])->setField('first_order_time', $add_time);
                }

                $total_money = $order['order_amount'] / 100;
                $order_total_commission = $order['promotion_amount'] / 100;
                $pay_status = isset($this->order_status[$order['order_status']]) ? $this->order_status[$order['order_status']] : 'fail';

                //主订单数据
                $add_order = array(
                    'order_sn'        => $order['order_sn'],
                    'order_num'       => 1,
                    'num_iid'         => $order['goods_id'],
                    'item_num'        => $order['goods_quantity'],
                    'title'           => $order['goods_name'],
                    'pic_url'         => $order['goods_thumbnail_url'],
                    'item_cate_name'  => '拼多多' . $order['type'],
                    'unit_price'      => $order['goods_price'] / 100,
                    'total_money'     => $total_money,
                    'commission_rate' => $order['promotion_rate'] / 10,
                    'share_rate'      => 100,
                    'commission'      => $order_total_commission,
                    'order_type'      => '拼多多',
                    'order_source_mall_platform' => 'pinduoduo',
                    'pay_status'      => $pay_status,
                    'pid'             => $pid,
                    'user_id'         => $users[$pid]['id'],
                    'partner_id'      => $users[$pid]['partner_id'],
                    'add_time'        => $add_time,
                    'settle_time'     => 'settle' == $pay_status ? $order['order_verify_time'] : 0
                );
                $order_id = M('order')->add($add_order);

                /**
                 * 计算子订单
                 * 先计算给自己的分成 然后给上级 然后给团长 最后给团长的团长
                 */
                $rate = C('COMMISSION_RATE.base_commission_rate');
                $commission = computed_price($order_total_commission, 2, $rate, 'pinduoduo');
                $chain_commission += $commission;
                $add_order_commissions[] = array( //给自己的分成
                    'order_id' => $order_id,
                    'user_id' => $users[$pid]['id'],
                    'partner_id' => $users[$pid]['partner_id'],
                    'order_sn' => $order['order_sn'],
                    'order_num' => 1,
                    'total_money' => $total_money,
                    'commission' => $commission,
                    'pay_status' => $pay_status,
                    'zm_subsidy_money' => 0,
                    'source' => 'self',
                    'add_time' => $add_time,
                    'settle_time' => 'settle' == $pay_status ? $order['order_verify_time'] : 0,
                    'order_source_mall_platform' => 'pinduoduo',
                );

                //给上级的分成
                $inviter_pid = $users[$pid]['inviter_pdd_pid'];
                if ($users[$pid]['inviter_id'] > 0) {
                    $subsidy_rate = 0; //平台补贴比例
                    if (($now - $add_time) < C('COMMISSION_RATE.award_days') * 86400) {
                        if (2 == $users[$inviter_pid]['level']) {
                            $subsidy_rate = C('COMMISSION_RATE.v2_award');
                        } else if (3 == $users[$inviter_pid]['level']) {
                            $subsidy_rate = C('COMMISSION_RATE.v3_award');
                        } else if (4 == $users[$inviter_pid]['level']) {
                            $subsidy_rate = C('COMMISSION_RATE.v4_award');
                        }
                    }

                    $commission = computed_price($order_total_commission, 2, C('COMMISSION_RATE.son_commission_rate'), 'pinduoduo');
                    if ($commission > 0) {
                        $zm_subsidy_money = computed_price($order_total_commission, 2, $subsidy_rate, 'pinduoduo');
                        $chain_commission += $commission + $zm_subsidy_money;
                        $add_order_commissions[] = array(
                            'order_id' => $order_id,
                            'user_id' => $users[$pid]['inviter_id'],
                            'partner_id' => $users[$pid]['partner_id'],
                            'order_sn' => $order['order_sn'],
                            'order_num' => 1,
                            'total_money' => $total_money,
                            'commission' => $commission,
                            'pay_status' => $pay_status,
                            'zm_subsidy_money' => $zm_subsidy_money,
                            'source' => 'son',
                            'add_time' => $add_time,
                            'settle_time' => 'settle' == $pay_status ? $order['order_verify_time'] : 0,
                            'order_source_mall_platform' => 'pinduoduo',
                        );
                    }
                }

                //给团长的分成
                if ($users[$pid]['group_leader_id'] > 0) {
                    $commission = computed_price($order_total_commission, 2, C('COMMISSION_RATE.group_leader'), 'pinduoduo');
                    if ($commission > 0) {
                        $chain_commission += $commission;
                        $add_order_commissions[] = array(
                            'order_id' => $order_id,
                            'user_id' => $users[$pid]['group_leader_id'],
                            'partner_id' => $users[$pid]['partner_id'],
                            'order_sn' => $order['order_sn'],
                            'order_num' => 1,
                            'total_money' => $total_money,
                            'commission' => $commission,
                            'pay_status' => $pay_status,
                            'zm_subsidy_money' => 0,
                            'source' => 'group_leader',
                            'add_time' => $add_time,
                            'settle_time' => 'settle' == $pay_status ? $order['order_verify_time'] : 0,
                            'order_source_mall_platform' => 'pinduoduo',
                        );
                    }
                }

                //给团长的团长分成
                if ($users[$pid]['group_leader_parent_id'] > 0) {
                    $commission = computed_price($order_total_commission, 2, C('COMMISSION_RATE.group_leader_parent'));
                    if ($commission > 0) {
                        $chain_commission += $commission;
                        $add_order_commissions[] = array(
                            'order_id' => $order_id,
                            'user_id' => $users[$pid]['group_leader_parent_id'],
                            'partner_id' => $users[$pid]['partner_id'],
                            'order_sn' => $order['order_sn'],
                            'order_num' => 1,
                            'total_money' => $total_money,
                            'commission' => $commission,
                            'pay_status' => $pay_status,
                            'zm_subsidy_money' => 0,
                            'source' => 'group_leader',
                            'add_time' => $add_time,
                            'settle_time' => 'settle' == $pay_status ? $order['order_verify_time'] : 0,
                            'order_source_mall_platform' => 'pinduoduo',
                        );
                    }
                }

                //给合伙人
                $commission = computed_price($order_total_commission, 2, 100, 'pinduoduo') - $chain_commission;
                if ($commission > 0) {
                    $add_order_commissions[] = array(
                        'order_id' => $order_id,
                        'user_id' => $partners_master_user_id[$users[$pid]['partner_id']],
                        'partner_id' => $users[$pid]['partner_id'],
                        'order_sn' => $order['order_sn'],
                        'order_num' => 1,
                        'total_money' => $total_money,
                        'commission' => $commission,
                        'pay_status' => $pay_status,
                        'zm_subsidy_money' => 0,
                        'source' => 'partner',
                        'add_time' => $add_time,
                        'settle_time' => 'settle' == $pay_status ? $order['order_verify_time'] : 0,
                        'order_source_mall_platform' => 'pinduoduo',
                    );
                }
            }

            if (!empty($add_order_commissions)) {
                M('order_commission')->addAll($add_order_commissions);
            }

            if ($model->commit()) {
                S('DATA_pinduoduo_last_order_add_time', $last_order_add_time);

                return 'success last_order_add_time:' . date('Y-m-d H:i:s', $last_order_add_time);
            } else {
                throw new \Exception($model->getError());
            }
        } catch (\Exception $e) {
            $model->rollback();
            $this->_addLog('getNewOrderPinduoduo', '新增订单异常：' . $e->getMessage() . var_export($add_orders, true) . var_export($add_order_commissions, true));
            return '新增订单异常';
        }
    }

    /**
     * 更新拼多多订单信息
     */
    public function updateOrder() {
        $now = time();
        $last_order_update_time = S('DATA_pinduoduo_last_order_update_time');
        if (!is_numeric($last_order_update_time)) {
            $last_order_update_time = $now - 86399;
        }
        if($now - $last_order_update_time > 86399){
            $last_order_update_time = $now - 86399;
        }

        $params = array(
            'start_update_time' => $last_order_update_time, //最近90天内多多进宝商品订单更新时间--查询时间开始
            'end_update_time' => $now, //最近90天内多多进宝商品订单更新时间--查询时间结束
            'page_size' => 100, //返回的每页结果数，默认为20，范围为10到100
            'page' => 1
        );

        $res = $this->_getPddOrderListIncrement($params, true);

        if ($res['total_count'] <= $params['page_size']) {
            $orders = $res['order_list'];
            krsort($orders);
            echo 'Page 1:' . $this->_doUpdateOrder($orders);
        } else {
            for ($page=ceil($res['total_count'] / $params['page_size']); $page >= 1 ; $page--) {
                $params['page'] = $page;
                $orders = $res['order_list'];
                krsort($orders);
                echo 'Page ' . $page . ':' . $this->_doUpdateOrder($orders) . "\n";
            }
        }

        echo ' over';
    }

    /**
     * 执行更新拼多多订单信息
     */
    protected function _doUpdateOrder($orders) {
        if (empty($orders)) {
            return 'empty order';
        }

        $order_sns = array(); //所有订单号
        foreach ($orders as $key => $order) {
            $order_sns[] = $order['order_sn'];
        }

        $exist_order_sns = M('order')->where(array('order_sn' => array('in', $order_sns)))->getField('order_sn', true);

        /**
         * 删除不存在的订单
         */
        foreach ($orders as $key => $order) {
            if (!in_array($order['order_sn'], $exist_order_sns)) {
                unset($orders[$key]); //去除不存在的订单
                continue;
            }
        }
        if (empty($orders)) {
            return 'no update order';
        }

        $model = new \Think\Model();
        foreach ($orders as $key => $order) {
            //更新订单状态
            $pay_status = isset($this->order_status[$order['order_status']]) ? $this->order_status[$order['order_status']] : 'fail';
            if ('paid' == $pay_status) {
                S('DATA_pinduoduo_last_order_update_time', $order['order_modify_at'], 86400);
                continue;
            }

            $model->startTrans();
            try {
                $save_data = array('pay_status' => $pay_status);

                if ('settle' == $pay_status) {
                    $save_data['settle_time'] = $order['order_verify_time'];
                } else if ('refund' == $pay_status) {
                    $save_data['refund_time'] = $order['order_verify_time'];
                }

                M('order')->where(array('order_sn' => $order['order_sn']))->save($save_data);
                M('order_commission')->where(array('order_sn' => $order['order_sn']))->save($save_data);

                if ($model->commit()) {
                    S('DATA_pinduoduo_last_order_update_time', $order['order_modify_at'], 86400);
                } else {
                    throw new \Exception($model->getError());
                }
            } catch (\Exception $e) {
                $model->rollback();
                $this->_addLog('pddUpdateOrder', '拼多多更新订单信息异常：' . $e->getMessage() . ' order_sn:' . $order['order_sn'] . var_export($save_data, true) . var_export($order, true));
            }
        }

        return 'last update time ' . date('Y-m-d H:i:s', S('DATA_pinduoduo_last_order_update_time'));
    }

}