<?php
/**
 * 订单
 * author: dongguangqi
 */

namespace Api\Controller;

/**
 * Class OrderController
 *
 * @package Api\Controller
 */
class OrderController extends CommonController {
    protected $checkUser = true;

    /**
     * 订单明细
     */
    public function index() {
        $page       = I('get.page', 1, 'int');
        $start_day  = I('get.start_day', '', 'trim');
        $end_day    = I('get.end_day', '', 'trim');
        $pay_status = I('get.pay_status', 'all', 'trim');
        $source     = I('get.source', 'all', 'trim');
        $order_source_mall_platform = I('get.order_source_mall_platform', 'all', 'trim');

        if ('' == $start_day || '' == $end_day) {
            $this->output('日期不能为空');
        }

        $where = array(
            'oc.user_id' => $this->user_id,
            'oc.add_time' => array('between', array(strtotime($start_day), strtotime($end_day) + 86399))
        );

        if (in_array($pay_status, array('paid', 'settle'))) {
            $where['oc.pay_status'] = $pay_status;
        } else {
            $where['oc.pay_status'] = array('in', array('paid', 'settle'));
        }

        if (in_array($source, array('self', 'son', 'group_leader'))) {
            $where['oc.source'] = $source;
        }

        if (in_array($order_source_mall_platform, array('taobao', 'jingdong', 'pinduoduo'))) {
            $where['oc.order_source_mall_platform'] = $order_source_mall_platform;
        }

        $page--;
        $start_num = $page * $this->limit;
        $field = 'oc.order_sn,oc.commission, oc.zm_subsidy_money, oc.total_money, oc.add_time, oc.settle_time, oc.pay_status, oc.source, oc.order_source_mall_platform, o.title, o.pic_url, o.order_type, o.num_iid';
        $order_commissions  = M('order_commission')->alias('oc')->join('left join zm_order as o ON oc.order_id=o.id')->where($where)->field($field)->order('oc.add_time desc')->limit($start_num, $this->limit)->select();
        foreach ($order_commissions as $key => $order_commission) {
            $order_commission['commission'] += $order_commission['zm_subsidy_money'];
            unset($order_commissions[$key]['zm_subsidy_money']);

            switch ($order_commission['source']) {
                case 'son':
                    $order_commissions[$key]['source_view'] = '邀请奖励';
                    break;
                case 'group_leader':
                    $order_commissions[$key]['source_view'] = '团长奖励';
                    break;

                default:
                    $order_commissions[$key]['source_view'] = '自购推广';
                    break;
            }
            unset($order_commissions[$key]['source']);

            switch ($order_commission['pay_status']) {
                case 'paid':
                    $order_commissions[$key]['pay_status_view'] = '订单付款';
                    break;
                case 'settle':
                    $order_commissions[$key]['pay_status_view'] = '订单结算';
                    break;
                case 'fail':
                    $order_commissions[$key]['pay_status_view'] = '订单失效';
                    break;
                case 'success':
                    $order_commissions[$key]['pay_status_view'] = '交易成功';
                    break;
                case 'refund':
                    $order_commissions[$key]['pay_status_view'] = '订单退款';
                    break;

                default:
                    $order_commissions[$key]['pay_status_view'] = '订单';
                    break;
            }

            $order_commissions[$key]['commission']  = format_view_money($order_commission['commission']);
            $order_commissions[$key]['total_money'] = format_view_money($order_commission['total_money']);
        }

        $total_field = 'count(id) as total_num, sum(total_money) as total_paid_money, sum(commission) as total_commission, sum(zm_subsidy_money) as total_subsidy_money';
        $total = M('order_commission')->alias('oc')->where($where)->field($total_field)->find();

        $data = array(
            'total_num' => intval($total['total_num']),
            'total_money' => floatval($total['total_paid_money']),
            'total_commission' => $total['total_commission'] + $total['total_subsidy_money'],
            'list' => $order_commissions
        );
        $this->output('ok', 'success', $data);
    }
}