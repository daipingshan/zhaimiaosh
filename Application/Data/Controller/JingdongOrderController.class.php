<?php
/**
 * 京东订单数据
 * Author: dongguangqi
 */

namespace Data\Controller;
use Common\Controller\CommonBaseController;
use Common\Org\Jos;

/**
 *
 *
 * @package Data\Controller
 */
class JingdongOrderController extends CommonBaseController {
    //validCode：有效码（-1：未知,2.无效-拆单,3.无效-取消,4.无效-京东帮帮主订单,5.无效-账号异常,6.无效-赠品类目不返佣,7.无效-校园订单,8.无效-企业订单,9.无效-团购订单,10.无效-开增值税专用发票订单,11.无效-乡村推广员下单,12.无效-自己推广自己下单,13.无效-违规订单,14.无效-来源与备案网址不符,15.待付款,16.已付款,17.已完成,18.已结算
    protected $order_status = array(
        '-1' => 'fail',
        '2' => 'fail',
        '3' => 'fail',
        '4' => 'fail',
        '5' => 'fail',
        '6' => 'fail',
        '7' => 'fail',
        '8' => 'fail',
        '9' => 'fail',
        '10' => 'fail',
        '11' => 'fail',
        '12' => 'fail',
        '13' => 'fail',
        '14' => 'fail',
        '15' => 'fail',
        '16' => 'paid',
        '17' => 'paid',
        '18' => 'settle',
    );

     public function __construct() {
        parent::__construct();
    }

    /**
     * 获取京东订单
     * 新订单插入数据   已存在的订单更新
     */
    public function getOrder() {
        $now_update_time = I('request.time', '', 'trim');
        $now = time();
        if (empty($now_update_time)) {
            $now_minute = date('i');
            if ($now_minute > 7 && $now_minute != 30) {
                $now_update_time = date('YmdH', $now);
            } else {
                $now_update_time = date('YmdH', $now - 3600);
            }
        }

        $jos = new Jos();
        for ($page=1; $page < 30 ; $page++) {
            $result = $jos->queryOrder(array('time' => $now_update_time, 'page' => $page));

            if (isset($result['success']) && 1 == $result['success']) {
                if (isset($result['data']) && !empty($result['data'])) {
                    krsort($result['data']);
                    //查找所有订单，已存在的更新状态。不存在的已支付加入新增
                    $order_sns = array(); //所有订单号
                    $wait_add_order_sns = array(); //待新增的订单号
                    foreach ($result['data'] as $key => $order) {
                        $order_sns[] = $order['orderId'];

                        if (in_array($order['validCode'], array(16, 17, 18))) {
                            $wait_add_order_sns[] = $order['orderId'];
                        }
                    }

                    $where = array('order_sn' => array('in', $order_sns));
                    $exist_order_sns = M('order')->where($where)->getField('order_sn', true);

                    if (!empty($exist_order_sns)) {
                        $update_orders = array();
                        foreach ($result['data'] as $key => $order) {
                            $pay_status = isset($this->order_status[$order['validCode']]) ? $this->order_status[$order['validCode']] : 'fail';
                            if (in_array($order['orderId'], $exist_order_sns) && 'paid' != $pay_status) {
                                $update_orders[] = $order;
                            }
                        }

                        if (!empty($update_orders)) {
                            echo "page {$page} add order:" . $this->_doUpdateOrder($update_orders) . "\n";
                        }
                    } else {
                        $exist_order_sns = array();
                    }

                    //获取真正需要新增的订单
                    if (empty($wait_add_order_sns)) {
                        continue;
                    }
                    $wait_add_order_sns = array_diff($wait_add_order_sns, $exist_order_sns);
                    if (!empty($wait_add_order_sns)) {
                        $add_orders = array();
                        foreach ($result['data'] as $key => $order) {
                            if (in_array($order['orderId'], $wait_add_order_sns)) {
                                $add_orders[] = $order;
                            }
                        }

                        echo "page {$page} add order:" . $this->_doAddOrder($add_orders) . "\n";
                    }
                }

                if (false == $result['hasMore']) {
                    echo "page {$page} over \n";
                    break;
                }
            } else {
                echo "page {$page} Exception : " . var_export($result, true) . " \n";
                break;
            }
        }
    }

    /**
     * 执行新增京东订单
     */
    private function _doAddOrder($orders) {
        if (empty($orders)) {
            return 'empty order';
        }

        $model = new \Think\Model();
        $now = time();

        $pids = array(); //所有PID
        foreach ($orders as $key => $order) {
            foreach ($order['skuList'] as $sku_key => $sku_item) {
                $pids[] = strval($sku_item['spId']);
            }
        }

        //新订单
        //根据PID查询用户信息
        //根据用户是否有上线、是否有团长、是否有团长的团长，来拆分成多条子订单。一次插入多条记录：主订单和子订单

        $fields = 'jd_pid, id, inviter_id, inviter_jd_pid, level, first_order_time, group_leader_id, group_leader_parent_id, partner_id';
        $pids = array_unique($pids);
        $users = M('user')->where(array('jd_pid' => array('in', $pids)))->getField($fields);
        $where = array('jd_pid' => array('in',  array_filter(array_column($users, 'inviter_jd_pid'))));
        $inviter_users = M('user')->field($fields)->where($where)->select();

        //$users为包含了订单用户和订单用户的邀请人 ,不使用array_merge是因为键为数字的时候合并会重置键名
        foreach ($inviter_users as $key => $user) {
            $users[$user['jd_pid']] = $user;
        }

        $add_orders = array(); //待添加的主订单
        $add_order_commissions = array(); //待添加的子订单
        $add_order_num = 0;
        $model->startTrans();
        try {
            foreach ($orders as $key => $order) {
                $add_time = $order['orderTime'] / 1000;

                foreach ($order['skuList'] as $sku_key => $sku_item) {
                    $pid = strval($sku_item['spId']);
                    $chain_commission = 0; //已结给各链条上分成的佣金总额。去除掉该总额，剩余部分给合伙人
                    $total_money = $sku_item['estimateCosPrice']; //实际支付总该金额
                    if (!isset($users[$pid]) || $total_money == 0) {
                        continue;
                    }

                    if (0 == $users[$pid]['first_order_time']) {
                        $users[$pid]['first_order_time'] = $add_time;
                        M('user')->where('id=' . $users[$pid]['id'])->setField('first_order_time', $add_time);
                    }

                    $order_total_commission = $sku_item['estimateFee']; //预付拿到的总佣金
                    $pay_status = isset($this->order_status[$sku_item['validCode']]) ? $this->order_status[$sku_item['validCode']] : 'fail';

                    $jd_item_imgs = $this->_getJdItemInfo($sku_item['skuId']);
                    if (!empty($jd_item_imgs['thumbnail_images'])) {
                        $pic_url = array_shift($jd_item_imgs['thumbnail_images']);
                    } else {
                        $pic_url = '';
                    }

                    //主订单数据
                    $add_order = array(
                        'order_sn'        => $order['orderId'],
                        'order_num'       => 1,
                        'num_iid'         => $sku_item['skuId'],
                        'item_num'        => $sku_item['skuNum'],
                        'title'           => $sku_item['skuName'],
                        'pic_url'         => $pic_url,
                        'item_cate_name'  => '京东|' . $sku_item['firstLevel'] . '|' . $sku_item['secondLevel'] . '|' . $sku_item['thirdLevel'],
                        'unit_price'      => $sku_item['price'], //这里的是京东卖价
                        'total_money'     => $total_money,
                        'commission_rate' => $sku_item['commissionRate'],
                        'share_rate'      => $sku_item['finalRate'],
                        'commission'      => $order_total_commission,
                        'order_type'      => '京东',
                        'order_source_mall_platform' => 'jingdong',
                        'pay_status'      => $pay_status,
                        'pid'             => $pid,
                        'user_id'         => $users[$pid]['id'],
                        'partner_id'      => $users[$pid]['partner_id'],
                        'add_time'        => $add_time,
                        'settle_time'     => 'settle' == $pay_status ? $now : 0
                    );
                    $order_id = M('order')->add($add_order);
                    $add_order_num ++;

                    /**
                     * 8月16号至20号免单商品不给分佣
                     */
                    if ($add_time >= 1534348800 && $add_time < 1534780800) {
                        if (round($total_money, 1) == 9.9 && $sku_item['commissionRate'] > 49) {
                            $order_total_commission = 0;
                        }
                    }

                    /**
                     * 计算子订单
                     * 先计算给自己的分成 然后给上级 然后给团长 最后给团长的团长
                     */
                    $rate = C('COMMISSION_RATE.base_commission_rate');
                    $commission = computed_price($order_total_commission, 2, $rate, 'jingdong');
                    $chain_commission += $commission;
                    $add_order_commissions[] = array( //给自己的分成
                        'order_id' => $order_id,
                        'user_id' => $users[$pid]['id'],
                        'partner_id' => $users[$pid]['partner_id'],
                        'order_sn' => $order['orderId'],
                        'order_num' => 1,
                        'total_money' => $total_money,
                        'commission' => $commission,
                        'pay_status' => $pay_status,
                        'zm_subsidy_money' => 0,
                        'source' => 'self',
                        'add_time' => $add_time,
                        'settle_time' => 'settle' == $pay_status ? $now : 0,
                        'order_source_mall_platform' => 'jingdong',
                    );

                    //给上级的分成
                    $inviter_pid = $users[$pid]['inviter_jd_pid'];
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

                        $commission = computed_price($order_total_commission, 2, C('COMMISSION_RATE.son_commission_rate'), 'jingdong');
                        if ($commission > 0) {
                            $zm_subsidy_money = computed_price($order_total_commission, 2, $subsidy_rate, 'jingdong');
                            $chain_commission += $commission + $zm_subsidy_money;
                            $add_order_commissions[] = array(
                                'order_id' => $order_id,
                                'user_id' => $users[$pid]['inviter_id'],
                                'partner_id' => $users[$pid]['partner_id'],
                                'order_sn' => $order['orderId'],
                                'order_num' => 1,
                                'total_money' => $total_money,
                                'commission' => $commission,
                                'pay_status' => $pay_status,
                                'zm_subsidy_money' => $zm_subsidy_money,
                                'source' => 'son',
                                'add_time' => $add_time,
                                'settle_time' => 'settle' == $pay_status ? $now : 0,
                                'order_source_mall_platform' => 'jingdong',
                            );
                        }
                    }

                    //给团长的分成
                    if ($users[$pid]['group_leader_id'] > 0) {
                        $commission = computed_price($order_total_commission, 2, C('COMMISSION_RATE.group_leader'), 'jingdong');
                        if ($commission > 0) {
                            $chain_commission += $commission;
                            $add_order_commissions[] = array(
                                'order_id' => $order_id,
                                'user_id' => $users[$pid]['group_leader_id'],
                                'partner_id' => $users[$pid]['partner_id'],
                                'order_sn' => $order['orderId'],
                                'order_num' => 1,
                                'total_money' => $total_money,
                                'commission' => $commission,
                                'pay_status' => $pay_status,
                                'zm_subsidy_money' => 0,
                                'source' => 'group_leader',
                                'add_time' => $add_time,
                                'settle_time' => 'settle' == $pay_status ? $now : 0,
                                'order_source_mall_platform' => 'jingdong',
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
                                'order_sn' => $order['orderId'],
                                'order_num' => 1,
                                'total_money' => $total_money,
                                'commission' => $commission,
                                'pay_status' => $pay_status,
                                'zm_subsidy_money' => 0,
                                'source' => 'group_leader',
                                'add_time' => $add_time,
                                'settle_time' => 'settle' == $pay_status ? $now : 0,
                                'order_source_mall_platform' => 'jingdong',
                            );
                        }
                    }
                }
            }

            if (!empty($add_order_commissions)) {
                M('order_commission')->addAll($add_order_commissions);
            }

            if ($model->commit()) {
                return 'success add order num:' . $add_order_num;
            } else {
                $add_order_num --;
                throw new \Exception($model->getError());
            }
        } catch (\Exception $e) {
            $model->rollback();
            $this->_addLog('getNewOrderjingdong', '新增订单异常：' . $e->getMessage() . var_export($add_orders, true) . var_export($add_order_commissions, true));
            return '新增订单异常' . $e->getMessage();
        }
    }

    /**
     * 执行更新京东订单信息
     */
    protected function _doUpdateOrder($orders) {
        if (empty($orders)) {
            return 'empty order';
        }

        $now = time();
        $update_order_num = 0;

        $model = new \Think\Model();
        foreach ($orders as $key => $order) {
            foreach ($order['skuList'] as $sku_key => $sku_item) {
                //更新订单状态
                $pay_status = isset($this->order_status[$sku_item['validCode']]) ? $this->order_status[$sku_item['validCode']] : 'fail';
                if ($sku_item['skuReturnNum'] > 0) {
                    $pay_status = 'refund';
                }

                $model->startTrans();
                try {
                    $save_data = array('pay_status' => $pay_status);

                    if ('settle' == $pay_status) {
                        $save_data['settle_time'] = $now;
                    } else if ('refund' == $pay_status) {
                        $save_data['refund_time'] = $now;
                    }

                    $where = array(
                        'order_sn' => $order['orderId'],
                        'num_iid'  => $sku_item['skuId'],
                    );
                    $order = M('order')->where($where)->find();

                    if ($pay_status != $order['pay_status']) {
                        M('order')->where(array('id' => $order['id']))->save($save_data);
                        M('order_commission')->where(array('order_id' => $order['id']))->save($save_data);
                    }

                    if ($model->commit()) {
                        $update_order_num++;
                    } else {
                        throw new \Exception($model->getError());
                    }
                } catch (\Exception $e) {
                    $model->rollback();
                    $this->_addLog('jdUpdateOrder', '京东更新订单信息异常：' . $e->getMessage() . ' order_sn:' . $order['order_sn'] . var_export($save_data, true) . var_export($order, true));
                    echo '京东更新订单信息异常：' . $e->getMessage() . "\n";
                }
            }
        }

        return 'update num : ' . $update_order_num;
    }

}