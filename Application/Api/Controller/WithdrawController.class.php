<?php
/**
 * 提现
 * author: dongguangqi
 */

namespace Api\Controller;

/**
 * Class WithdrawController
 *
 * @package Api\Controller
 */
class WithdrawController extends CommonController {
    protected $checkUser = true;

    /**
     * 提现记录
     */
    public function index() {
        $page = I('get.page', 1, 'int');

        $page--;
        $start_num = $page * $this->limit;
        $field = 'money, bank_account, status, add_time, settle_time, remark';
        $withdraws  = M('withdraw')->where('user_id=' . $this->user_id)->field($field)->order('add_time desc')->limit($start_num, $this->limit)->select();
        foreach ($withdraws as $key => $withdraw) {
            $withdraws[$key]['bank_account'] = '支付宝(' . substr($withdraw['bank_account'], 0, 3) . '****' . substr($withdraw['bank_account'], -3) . ')';
            switch ($withdraw['status']) {
                case 1:
                    $withdraws[$key]['status_view'] = '已结算';
                    break;
                case 2:
                    $withdraws[$key]['status_view'] = '结算异常';
                    break;

                default:
                    $withdraws[$key]['status_view'] = '未结算';
                    break;
            }
            unset($withdraws[$key]['status']);
        }

        $this->output('ok', 'success', $withdraws);
    }

    /**
     * 设置提现账户
     */
    public function setBankAccount() {
        $bank_account_real_name = I('request.bank_account_real_name', '', 'trim');
        $bank_account = I('request.bank_account', '', 'trim');
        $sms_code     = I('request.sms_code', '', 'trim');

        if ('' == $bank_account_real_name || '' == $bank_account) {
            $this->output('姓名或支付宝账号不能为空！');
        }

        $right_sms_code = S($this->mobile);
        if ($sms_code != $right_sms_code || empty($right_sms_code)) {
            $this->outPut('验证码错误！');
        }

        $res = M('user')->where('id=' . $this->user_id)->setField(array('bank_account_real_name' => $bank_account_real_name, 'bank_account' => $bank_account));
        if ($res !== false) {
            S($this->mobile, null);
            $this->output('ok', 'success', array());
        } else {
            $this->output('exception');
        }
    }

    /**
     * 新增提现
     */
    public function add() {
        $model = new \Think\Model();
        $model->startTrans();
        try {
            $user = M('user')->where('id=' . $this->user_id)->find();

            if ('' == $user['bank_account']) {
                $this->output('没有设置提现账号，暂不能提现');
            }

            if (($user['account_balance'] + $user['pdd_account_balance'] + $user['jd_account_balance']) < C('BASE.min_withdraw_money')) {
                $this->output('暂未达到最低提现金额，满' . C('BASE.min_withdraw_money') . '元起提');
            }

            $now = time();
            $partner = $this->_getPartnerInfo($user['pid']);
            if (!isset($partner['id'])) {
                throw new \Exception('异常，请重试');
            }

            if ($user['account_balance'] > 0) {
                $cash_flow = array(
                    'user_id' => $this->user_id,
                    'order_sn' => 'withdraw_' . $now . $this->user_id,
                    'order_num' => 1,
                    'source' => 'withdraw',
                    'direction' => 'dec',
                    'money' => $user['account_balance'],
                    'account_balance' => $user['pdd_account_balance'] + $user['jd_account_balance'],
                    'add_time' => $now,
                );
                M('cash_flow')->add($cash_flow);

                $withdraw = array(
                    'user_id' => $this->user_id,
                    'partner_id' => $partner['id'],
                    'money' => $user['account_balance'],
                    'bank_account_real_name' => $user['bank_account_real_name'],
                    'bank_account' => $user['bank_account'],
                    'status' => 0,
                    'account_balance_mall_platform' => 'taobao',
                    'add_time' => $now,
                    'settle_time' => 0,
                    'remark' => '',
                );
                M('withdraw')->add($withdraw);
            }

            if ($user['pdd_account_balance'] + $user['jd_account_balance'] > 0) {
                $partner_master_user = M('user')->where('id=' . $partner['master_user_id'])->find();
                $partner_account_balance = $partner_master_user['account_balance'] + $partner_master_user['pdd_account_balance'] + $partner_master_user['jd_account_balance'];

                /**
                 * 拼多多账号余额 合作伙伴给用户代付款
                 */
                if ($user['pdd_account_balance'] > 0) {
                    $cash_flow = array(
                        'user_id' => $this->user_id,
                        'order_sn' => 'withdraw_pdd_' . $now . $this->user_id,
                        'order_num' => 1,
                        'source' => 'withdraw',
                        'direction' => 'dec',
                        'money' => $user['pdd_account_balance'],
                        'account_balance' => $user['jd_account_balance'],
                        'add_time' => $now,
                    );
                    M('cash_flow')->add($cash_flow);

                    $withdraw = array(
                        'user_id' => $this->user_id,
                        'partner_id' => $partner['id'],
                        'money' => $user['pdd_account_balance'],
                        'bank_account_real_name' => $user['bank_account_real_name'],
                        'bank_account' => $user['bank_account'],
                        'status' => 0,
                        'account_balance_mall_platform' => 'pinduoduo',
                        'add_time' => $now,
                        'settle_time' => 0,
                        'remark' => '拼多多提现',
                    );
                    $pdd_withdraw_id = M('withdraw')->add($withdraw);

                    //宅喵生活把钱给合作伙伴 （一条补贴流水记录，一条提现流水记录，一条提现记录）
                    if (!in_array($partner['id'], array(1, 15))) {
                        $cash_flow = array(
                            'user_id' => $partner_master_user['id'],
                            'order_sn' => 'withdraw_pdd_' . $now . $this->user_id,
                            'order_num' => 1,
                            'source' => 'award',
                            'direction' => 'add',
                            'money' => $user['pdd_account_balance'],
                            'account_balance' => $partner_account_balance + $user['pdd_account_balance'],
                            'add_time' => $now,
                        );
                        M('cash_flow')->add($cash_flow);

                        $cash_flow = array(
                            'user_id' => $partner_master_user['id'],
                            'order_sn' => 'withdraw_pdd_' . $now . $partner_master_user['id'] . '_' . mt_rand(100, 1000),
                            'order_num' => 1,
                            'source' => 'withdraw',
                            'direction' => 'dec',
                            'money' => $user['pdd_account_balance'],
                            'account_balance' => $partner_account_balance,
                            'add_time' => $now,
                        );
                        M('cash_flow')->add($cash_flow);

                        $remark = date('Y-m-d H:i:s', $now) . '提现ID为' . $pdd_withdraw_id . '的支付宝账号' . $user['bank_account'] . '的拼多多提现代付补款,partner_id为'. $partner_master_user['partner_id'];
                        $withdraw = array(
                            'user_id' => $partner_master_user['id'],
                            'partner_id' => 1,
                            'money' => $user['pdd_account_balance'],
                            'bank_account_real_name' => $partner_master_user['bank_account_real_name'],
                            'bank_account' => $partner_master_user['bank_account'],
                            'status' => 0,
                            'account_balance_mall_platform' => 'pinduoduo',
                            'add_time' => $now,
                            'settle_time' => 0,
                            'remark' => $remark,
                        );
                        M('withdraw')->add($withdraw);
                    }
                }

                /**
                 * 京东账号余额 合作伙伴给用户代付款
                 */
                if ($user['jd_account_balance'] > 0) {
                    $cash_flow = array(
                        'user_id' => $this->user_id,
                        'order_sn' => 'withdraw_jd_' . $now . $this->user_id,
                        'order_num' => 1,
                        'source' => 'withdraw',
                        'direction' => 'dec',
                        'money' => $user['jd_account_balance'],
                        'account_balance' => 0,
                        'add_time' => $now,
                    );
                    M('cash_flow')->add($cash_flow);

                    $withdraw = array(
                        'user_id' => $this->user_id,
                        'partner_id' => $partner['id'],
                        'money' => $user['jd_account_balance'],
                        'bank_account_real_name' => $user['bank_account_real_name'],
                        'bank_account' => $user['bank_account'],
                        'status' => 0,
                        'account_balance_mall_platform' => 'jingdong',
                        'add_time' => $now,
                        'settle_time' => 0,
                        'remark' => '京东提现',
                    );
                    $jd_withdraw_id = M('withdraw')->add($withdraw);

                    //宅喵生活把钱给合作伙伴 （一条补贴流水记录，一条提现流水记录，一条提现记录）
                    if (!in_array($partner['id'], array(1, 15))) {
                        $cash_flow = array(
                            'user_id' => $partner_master_user['id'],
                            'order_sn' => 'withdraw_jd_' . $now . $this->user_id,
                            'order_num' => 1,
                            'source' => 'award',
                            'direction' => 'add',
                            'money' => $user['jd_account_balance'],
                            'account_balance' => $partner_account_balance + $user['jd_account_balance'],
                            'add_time' => $now,
                        );
                        M('cash_flow')->add($cash_flow);

                        $cash_flow = array(
                            'user_id' => $partner_master_user['id'],
                            'order_sn' => 'withdraw_jd_' . $now . $partner_master_user['id'] . '_' . mt_rand(100, 1000),
                            'order_num' => 1,
                            'source' => 'withdraw',
                            'direction' => 'dec',
                            'money' => $user['jd_account_balance'],
                            'account_balance' => $partner_account_balance,
                            'add_time' => $now,
                        );
                        M('cash_flow')->add($cash_flow);

                        $remark = date('Y-m-d H:i:s', $now) . '提现ID为' . $jd_withdraw_id . '的支付宝账号' . $user['bank_account'] . '的京东提现代付补款,partner_id为'. $partner_master_user['partner_id'];
                        $withdraw = array(
                            'user_id' => $partner_master_user['id'],
                            'partner_id' => 1,
                            'money' => $user['jd_account_balance'],
                            'bank_account_real_name' => $partner_master_user['bank_account_real_name'],
                            'bank_account' => $partner_master_user['bank_account'],
                            'status' => 0,
                            'account_balance_mall_platform' => 'jingdong',
                            'add_time' => $now,
                            'settle_time' => 0,
                            'remark' => $remark,
                        );
                        M('withdraw')->add($withdraw);
                    }
                }
            }

            M('user')->where('id=' . $this->user_id)->setField(array('account_balance' => 0, 'pdd_account_balance' => 0, 'jd_account_balance' => 0));

            if ($model->commit()) {
                $this->output('提现申请成功，等待财务打款。可在提现记录里查看进度!', 'success', array());
            } else {
                throw new \Exception($model->getError());
            }
        } catch (\Exception $e) {
            $model->rollback();
            $this->_addLog('withdraw', 'ERROR:' . $e->getMessage() . var_export($cash_flow, true));
            $this->output('提现失败，请联系客服！');
        }
    }
}