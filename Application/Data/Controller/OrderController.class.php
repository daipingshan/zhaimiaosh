<?php
/**
 * 订单数据
 * Author: dongguangqi
 */

namespace Data\Controller;
use Common\Controller\CommonBaseController;

/**
 *
 *
 * @package Data\Controller
 */
class OrderController extends CommonBaseController {
    protected $order_status = array(
        '订单失效' => 'fail', '订单结算' => 'settle', '订单成功' => 'success', '订单付款' => 'paid'
    );

    public function __construct() {
        parent::__construct();
    }

    /**
     * 获取淘宝新订单
     */
    public function getNewOrder() {
        if (!file_exists('/home/order_log/zm_new_order.php')) {
            exit('no new order');
        }

        $orders = include '/home/order_log/zm_new_order.php';

        $model = new \Think\Model();

        $taobao_order_sns = array();
        $pids = array();
        foreach ($orders as $key => $order) {
            if ('订单失效' == $order[8]) { //去除失效单
                unset($orders[$key]);
                continue;
            }

            if (!$this->order_status[$order[8]]) {
                $this->_addLog('getNewOrder', '不存在的订单类型：' . $order[8] . var_export($order, true));
                unset($orders[$key]);
                continue;
            }

            $taobao_order_sns[] = $order[24];

            $temp = explode('_', $order[30]);
            $pid = $temp[0] . '_' . $temp[1] . '_' . $order[26] . '_' . $order[28];
            $orders[$key][] = $pid;
            $pids[] = $pid;
        }

        //新订单
        //查出已存在的和已失效的去除
        //根据PID查询用户信息
        //根据用户是否有上线、是否有团长、是否有团长的团长，来拆分成多条子订单。一次插入多条记录：主订单和子订单

        $fields = 'pid, id, inviter_id, inviter_pid, level, first_order_time, group_leader_id, group_leader_parent_id';
        $users = M('user')->where(array('pid' => array('in', $pids)))->getField($fields);
        $inviter_users = M('user')->where(array('pid' => array('in', array_column($users, 'inviter_pid'))))->getField($fields);
        $users = array_merge($users, $inviter_users); //$users为包含了订单用户和订单用户的邀请人

        $exist_order_sns = M('order')->where(array('order_sn' => array('in', $taobao_order_sns)))->getField('order_sn', true);
        $add_orders = array(); //待添加的主订单
        $add_order_commissions = array(); //待添加的子订单
        $model->startTrans();
        try {
            foreach ($orders as $key => $order) {
                if (in_array($order[24], $exist_order_sns)) {
                    unset($orders[$key]); //去除已存在的订单
                } else {
                    $pid = $order[32];

                    if (!isset($users[$pid])) {
                        continue;
                    }

                    $add_time = strtotime($order[0]);
                    $total_money = '订单失效' == $order[8] ? 0 : $order[12];
                    if (0 == $users[$pid]['first_order_time']) {
                        $users[$pid]['first_order_time'] = $add_time;
                        M('user')->where('id=' . $users[$pid]['id'])->setField('first_order_time', $add_time);
                    }

                    /*if (isset($orders[$key - 1]) && $order[24] == $orders[$key - 1][24]) {
                        $order_num++; //当前同一订单号的第几单
                    } else {
                        $order_num = 1;
                    }*/

                    $order_num = M('order')->where(array('order_sn' => $order[24]))->count() + 1;

                    //主订单数据
                    $add_order = array(
                        'order_sn'        => $order[24],
                        'order_num'       => $order_num,
                        'num_iid'         => $order[3],
                        'item_num'        => $order[6],
                        'title'           => $order[2],
                        'pic_url'         => $order[31],
                        'item_cate_name'  => $order[25],
                        'unit_price'      => $order[7],
                        'total_money'     => $total_money,
                        'commission_rate' => floatval($order[10]),
                        'share_rate'      => floatval($order[11]),
                        'commission'      => $order[13],
                        'order_type'      => $order[9],
                        'pay_status'      => $this->order_status[$order[8]],
                        'pid'             => $pid,
                        'user_id'         => $users[$pid]['id'],
                        'add_time'        => $add_time,
                        'settle_time'     => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                    );
                    $order_id = M('order')->add($add_order);

                    /**
                     * 计算子订单
                     * 先计算给自己的分成 然后给上级 然后给团长 最后给团长的团长
                     */
                    $rate = C('COMMISSION_RATE.base_commission_rate');
                    $commission = computed_price($order[13], 2, $rate);
                    $add_order_commissions[] = array( //给自己的分成
                        'order_id' => $order_id,
                        'user_id' => $users[$pid]['id'],
                        'order_sn' => $order[24],
                        'order_num' => $order_num,
                        'total_money' => $total_money,
                        'commission' => $commission,
                        'pay_status' => $this->order_status[$order[8]],
                        'zm_subsidy_money' => 0,
                        'source' => 'self',
                        'add_time' => $add_time,
                        'settle_time' => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                    );

                    //给上级的分成
                    $inviter_pid = $users[$pid]['inviter_pid'];
                    if ($users[$pid]['inviter_id'] > 0) {
                        $zm_subsidy_rate = 0; //平台补贴比例
                        if ((time() - $add_time) < C('COMMISSION_RATE.award_days') * 86400) {
                            if (2 == $users[$inviter_pid]['level']) {
                                $zm_subsidy_rate = C('COMMISSION_RATE.v2_award');
                            } else if (3 == $users[$inviter_pid]['level']) {
                                $zm_subsidy_rate = C('COMMISSION_RATE.v3_award');
                            } else if (4 == $users[$inviter_pid]['level']) {
                                $zm_subsidy_rate = C('COMMISSION_RATE.v4_award');
                            }
                        }

                        $commission = computed_price($order[13], 2, C('COMMISSION_RATE.son_commission_rate'));
                        $zm_subsidy_money = computed_price($order[13], 2, $zm_subsidy_rate);
                        $key = 'son' . $order[24] . $order_num;
                        $add_order_commissions[$key] = array(
                            'order_id' => $order_id,
                            'user_id' => $users[$pid]['inviter_id'],
                            'order_sn' => $order[24],
                            'order_num' => $order_num,
                            'total_money' => $total_money,
                            'commission' => $commission,
                            'pay_status' => $this->order_status[$order[8]],
                            'zm_subsidy_money' => $zm_subsidy_money,
                            'source' => 'son',
                            'add_time' => $add_time,
                            'settle_time' => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                        );
                    }

                    //给团长的分成
                    if ($users[$pid]['group_leader_id'] > 0) {
                        $commission = computed_price($order[13], 2, C('COMMISSION_RATE.group_leader'));
                        $key = 'group_leader' . $order[24] . $order_num;
                        $add_order_commissions[] = array(
                            'order_id' => $order_id,
                            'user_id' => $users[$pid]['group_leader_id'],
                            'order_sn' => $order[24],
                            'order_num' => $order_num,
                            'total_money' => $total_money,
                            'commission' => $commission,
                            'pay_status' => $this->order_status[$order[8]],
                            'zm_subsidy_money' => 0,
                            'source' => 'group_leader',
                            'add_time' => $add_time,
                            'settle_time' => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                        );
                    }

                    //给团长的团长
                    if ($users[$pid]['group_leader_parent_id'] > 0) {
                        $commission = computed_price($order[13], 2, C('COMMISSION_RATE.group_leader_parent'));
                        $add_order_commissions[] = array(
                            'order_id' => $order_id,
                            'user_id' => $users[$pid]['group_leader_parent_id'],
                            'order_sn' => $order[24],
                            'order_num' => $order_num,
                            'total_money' => $total_money,
                            'commission' => $commission,
                            'pay_status' => $this->order_status[$order[8]],
                            'zm_subsidy_money' => 0,
                            'source' => 'group_leader',
                            'add_time' => $add_time,
                            'settle_time' => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                        );
                    }
                }
            }

            M('order_commission')->addAll($add_order_commissions);
            if ($model->commit()) {
                @unlink('/home/order_log/zm_new_order.php');
            } else {
                throw new \Exception($model->getError());
            }
        } catch (\Exception $e) {
            $model->rollback();
            $this->_addLog('getNewOrder', '新增订单异常：' . $e->getMessage() . var_export($add_orders, true) . var_export($add_order_commissions, true));
        }
    }

    /**
     * 获取失效单
     */
    public function getFailOrder() {
        if (!file_exists('/home/order_log/zm_fail_order.php')) {
            exit('no fail order');
        }

        //先统一查出所有订单，再匹配看哪些需要失效。然后把订单失效。子订单里根据order_id置为失效
        //根据订单编号以及订单金额，直接把已付款订单置为失效

        $orders = include '/home/order_log/zm_fail_order.php';

        $taobao_order_sns = array();
        $format_orders = array(); //把订单做成以订单号为键，以订单为值的多维数组
        foreach ($orders as $key => $order) {
            $taobao_order_sns[] = $order[24];

            if (isset($format_orders[$order[24]])) {
                $format_orders[$order[24]][] = $order;
            } else {
                $format_orders[$order[24]] = array($order);
            }
        }
        unset($orders); //释放内存

        //查找待更新的订单
        $where = array('order_sn' => array('in', $taobao_order_sns), 'pay_status' => 'paid');
        $db_orders = M('order')->where($where)->select();
        $model = new \Think\Model();
        foreach ($db_orders as $db_key => $db_order) {
            foreach ($format_orders[$db_order['order_sn']] as $key => $order) {
                if ($order[3] == $db_order['num_iid'] && $order[6] == $db_order['item_num']) { //商品ID和数量相同
                    $model->startTrans();
                    try {
                        //更新订单状态
                        $save_data = array(
                            'pay_status' => 'fail',
                            'total_money' => 0,
                            'commission' => 0
                        );
                        M('order')->where(array('id' => $db_order['id'], 'pay_status' => 'paid'))->save($save_data);
                        M('order_commission')->where(array('order_id' => $db_order['id'], 'pay_status' => 'paid'))->save(array('pay_status' => 'fail', 'total_money' => 0, 'commission' => 0, 'zm_subsidy_money' => 0));

                        if ($model->commit()) {
                            unset($format_orders[$db_order['order_sn']][$key]);
                            break;
                        } else {
                            throw new \Exception($model->getError());
                        }
                    } catch (\Exception $e) {
                        $model->rollback();
                        $this->_addLog('getFailOrder', '失效订单异常：' . $e->getMessage() . var_export($db_order, true));
                        break;
                    }
                }
            }
        }

        //处理完成，删除失效订单缓存文件
        @unlink('/home/order_log/zm_fail_order.php');
    }

    /**
     * 获取结算单
     */
    public function getSettleOrder() {
        if (!file_exists('/home/order_log/zm_settle_order.php')) {
            exit('no settle order');
        }

        //先统一查出所有订单，再匹配看哪些需要结算。然后把主订单结算。子订单里根据order_id置为结算
        //根据订单编号以及订单金额，直接把已付款订单置为结算

        $orders = include '/home/order_log/zm_settle_order.php';

        $taobao_order_sns = array();
        $format_orders = array(); //把订单做成以订单号为键，以订单为值的多维数组
        foreach ($orders as $key => $order) {
            $taobao_order_sns[] = $order[24];

            if (isset($format_orders[$order[24]])) {
                $format_orders[$order[24]][] = $order;
            } else {
                $format_orders[$order[24]] = array($order);
            }
        }
        unset($orders); //释放内存

        //查找待更新的订单
        $where = array('order_sn' => array('in', $taobao_order_sns), 'pay_status' => 'paid');
        $db_orders = M('order')->where($where)->select();
        $model = new \Think\Model();
        foreach ($db_orders as $db_key => $db_order) {
            foreach ($format_orders[$db_order['order_sn']] as $key => $order) {
                if ($order[3] == $db_order['num_iid'] && $order[6] == $db_order['item_num']) { //商品ID和数量相同
                    $model->startTrans();
                    try {
                        //更新订单状态
                        $settle_time = strtotime($order[16]);
                        $save_data = array(
                            'pay_status' => 'settle',
                            'settle_time' => $settle_time,
                        );
                        M('order')->where(array('id' => $db_order['id'], 'pay_status' => 'paid'))->save($save_data);
                        M('order_commission')->where(array('order_id' => $db_order['id'], 'pay_status' => 'paid'))->save($save_data);

                        if ($model->commit()) {
                            unset($format_orders[$db_order['order_sn']][$key]);
                            break;
                        } else {
                            throw new \Exception($model->getError());
                        }
                    } catch (\Exception $e) {
                        $model->rollback();
                        $this->_addLog('getSettleOrder', '结算订单异常：' . $e->getMessage() . var_export($db_order, true));
                        break;
                    }
                }
            }
        }

        //处理完成，删除结算订单缓存文件
        @unlink('/home/order_log/zm_settle_order.php');
    }

    /**
     * 获取维权单
     * 目前的策略是只要有退款，整个订单就全退。
     */
    public function getRefundOrder() {
        if (!file_exists('/home/order_log/zm_refund_order.php')) {
            exit('no refund order');
        }

        //先统一查出所有订单，再匹配看哪些需要维权处理。
        //然后把子订单里根据order_id置为退款 子订单有成功处理后主订单退款。

        $orders = include '/home/order_log/zm_refund_order.php';

        $taobao_order_sns = array();
        $format_orders = array(); //把订单做成以订单号为键，以订单为值的多维数组
        foreach ($orders as $key => $order) {
            if ('维权成功' != $order[5]) {
                continue;
            }

            $taobao_order_sns[] = $order[0];

            if ($order[0] == $order[1]) {
                $order_num = 1;
            } else {
                $order_num = substr($order[1], 0, -6) - substr($order[0], 0, -6);
            }
            $format_orders[$order[0] . '_' . $order_num] = $order;
        }
        unset($orders); //释放内存

        //查找待更新的订单
        $where = array(
            'order_sn' => array('in', $taobao_order_sns),
            'pay_status' => 'settle',
            'settle_time' => array('gt', time() - C('COMMISSION_RATE.settle_account_days') * 86400)
        );
        $db_orders = M('order')->where($where)->select();
        $model = new \Think\Model();
        foreach ($db_orders as $db_key => $db_order) {
            if (isset($format_orders[$db_order['order_sn'] . '_' . $db_order['order_num']])) {
                $model->startTrans();
                try {
                    $where = array(
                        'order_id' => $db_order['id'],
                        'pay_status' => 'settle',
                        'pay_to_user_time' => 0
                    );
                    $save_data = array(
                        'pay_status' => 'refund',
                        'total_money' => 0,
                        'commission' => 0,
                        'zm_subsidy_money' => 0
                    );
                    $update_num = M('order_commission')->where($where)->save($save_data);
                    if ($update_num > 0) {
                        $where = array(
                            'id' => $db_order['id'],
                            'pay_status' => 'settle',
                        );
                        $save_data = array(
                            'pay_status' => 'refund',
                            'total_money' => 0,
                            'commission' => 0,
                            'refund_time' => strtotime($format_orders[$db_order['order_sn'] . '_' . $db_order['order_num']][8])
                        );
                        M('order')->where($where)->save($save_data);
                    }

                    if (!$model->commit()) {
                        throw new \Exception($model->getError());
                    }
                } catch (\Exception $e) {
                    $model->rollback();
                    $this->_addLog('getRefundOrder', '维权订单异常：' . $e->getMessage() . var_export($db_order, true));
                }
            }
        }

        //处理完成，删除维权订单缓存文件
        @unlink('/home/order_log/zm_refund_order.php');
    }

    /**
     * 定时获取待结算的账户余额的订单
     * 给用户结算到余额里
     */
    public function settleToAccount() {
        $where = array(
            'pay_status' => 'settle',
            'settle_time' => array('lt', time() - C('COMMISSION_RATE.settle_account_days') * 86400),
            'pay_to_user_time' => 0
        );
        $orders = M('order_commission')->where($where)->order('settle_time asc')->limit(100)->select();
        $model = new \Think\Model();
        foreach ($orders as $key => $order) {
            if ('jingdong' == $order['order_source_mall_platform']) {
                $order['order_sn'] .= '_' . $order['order_id'];
            }

            $now = time();
            $model->startTrans();
            try {
                $user = M('user')->where('id=' . $order['user_id'])->find();
                $user_account_balance = $user['account_balance'] + $user['pdd_account_balance'] + $user['jd_account_balance'];

                M('order_commission')->where('id=' . $order['id'])->setField('pay_to_user_time', $now);

                $cash_flow = array();
                if ($order['commission'] > 0) {
                    $cash_flow[] = array(
                        'user_id' => $order['user_id'],
                        'order_sn' => $order['order_sn'],
                        'order_num' => $order['order_num'],
                        'source' => $order['source'],
                        'direction' => 'add',
                        'money' => $order['commission'],
                        'account_balance' => $user_account_balance + $order['commission'],
                        'add_time' => $now,
                    );
                }
                if ($order['zm_subsidy_money'] > 0) {
                    $cash_flow[] = array(
                        'user_id' => $order['user_id'],
                        'order_sn' => $order['order_sn'],
                        'order_num' => $order['order_num'],
                        'source' => 'award',
                        'direction' => 'add',
                        'money' => $order['zm_subsidy_money'],
                        'account_balance' => $user_account_balance + $order['commission'] + $order['zm_subsidy_money'],
                        'add_time' => $now,
                    );
                }

                if (!empty($cash_flow)) {
                    M('cash_flow')->addAll($cash_flow);
                }

                if ('pinduoduo' == $order['order_source_mall_platform']) {
                    M('user')->where('id=' . $order['user_id'])->setInc('pdd_account_balance', $order['commission'] + $order['zm_subsidy_money']);
                } else if ('jingdong' == $order['order_source_mall_platform']) {
                    M('user')->where('id=' . $order['user_id'])->setInc('jd_account_balance', $order['commission'] + $order['zm_subsidy_money']);
                } else {
                    M('user')->where('id=' . $order['user_id'])->setInc('account_balance', $order['commission'] + $order['zm_subsidy_money']);
                }

                if (!$model->commit()) {
                    throw new \Exception($model->getError());
                }
            } catch (\Exception $e) {
                $model->rollback();

                $this->_addLog('settleToAccount', '结算到余额异常：' . $e->getMessage());
            }
        }
    }

}