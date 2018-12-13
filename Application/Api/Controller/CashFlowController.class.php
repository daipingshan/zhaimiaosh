<?php
/**
 * 资金流水
 * author: dongguangqi
 */

namespace Api\Controller;

/**
 * Class CashFlowController
 *
 * @package Api\Controller
 */
class CashFlowController extends CommonController {
    protected $checkUser = true;

    /**
     * 资金流水记录
     */
    public function index() {
        $page       = I('get.page', 1, 'int');
        $start_day  = I('get.start_day', '', 'trim');
        $end_day    = I('get.end_day', '', 'trim');
        $source     = I('get.source', 'all', 'trim');

        if ('' == $start_day || '' == $end_day) {
            $this->output('日期不能为空');
        }

        $where = array(
            'user_id' => $this->user_id,
            'add_time' => array('between', array(strtotime($start_day), strtotime($end_day) + 86399))
        );

        if (in_array($source, array('self', 'son', 'group_leader', 'award', 'withdraw', 'red_packet'))) {
            $where['source'] = $source;
        }

        $page--;
        $start_num = $page * $this->limit;
        $field = 'order_sn, source, direction, money, add_time, account_balance';
        $cash_flows  = M('cash_flow')->where($where)->field($field)->order('add_time desc')->limit($start_num, $this->limit)->select();
        foreach ($cash_flows as $key => $cash_flow) {
            switch ($cash_flow['source']) {
                case 'son':
                    $cash_flows[$key]['source_view'] = '邀请奖励';
                    break;
                case 'group_leader':
                    $cash_flows[$key]['source_view'] = '团长奖励';
                    break;
                case 'award':
                    $cash_flows[$key]['source_view'] = '平台奖励';
                    break;
                case 'withdraw':
                    $cash_flows[$key]['source_view'] = '提现';
                    break;
                case 'red_packet':
                    $cash_flows[$key]['source_view'] = '首次邀请红包';
                    break;

                default:
                    $cash_flows[$key]['source_view'] = '自购推广';
                    break;
            }
            unset($cash_flows[$key]['source']);
        }

        $total_field = 'sum(money) as total_money, direction';
        $total = M('cash_flow')->where($where)->field($total_field)->group('direction')->index('direction')->select();

        $data = array(
            'income' => isset($total['add']) ? $total['add']['total_money'] : 0,
            'expend' => isset($total['dec']) ? $total['dec']['total_money'] : 0,
            'list' => $cash_flows
        );

        $this->outPut('ok', 'success', $data);
    }
}