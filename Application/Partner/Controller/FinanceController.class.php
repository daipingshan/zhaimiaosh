<?php
/**
 * 财务管理
 */

namespace Partner\Controller;

/**
 * 财务管理
 * Class FinanceController
 *
 * @package Partner\Controller
 */
class FinanceController extends CommonController {

    /**
     * 提现列表
     */
    public function index() {
        $status_arr   = array('未结算', '已结算', '结算异常');
        $real_name    = I('get.bank_account_real_name', '', 'trim');
        $bank_account = I('get.bank_account', '', 'trim');
        $status       = I('get.status', '', 'trim');
        $platform     = I('get.platform', 'all', 'trim');
        $time_type    = I('get.time_type', '', 'trim');
        $start_time   = I('get.start_time', '', 'trim');
        $end_time     = I('get.end_time', '', 'trim');
        $search_type  = I('get.search_type', 'select', 'trim');
        $page         = I('get.page', 1, 'int');
        $model        = M('withdraw');
        $where        = array('partner_id' => $this->partner_id);
        $order_by     = 'id desc';
        if ($real_name) {
            $where['bank_account_real_name'] = $real_name;
        }
        if ($bank_account) {
            $where['bank_account'] = $bank_account;
        }
        if ($status !== '') {
            $where['status'] = $status;

            if ($status == 0) {
                $order_by = 'add_time asc';
            }
        }
        if ($platform && $platform != 'all') {
            $where['account_balance_mall_platform'] = $platform;
        }
        if ($time_type && $time_type != 'all') {
            if ($time_type == 'add_time' || $time_type == 'settle_time') {
                if ($start_time && $end_time) {
                    $where[$time_type] = array('between', array(strtotime($start_time), strtotime($end_time) + 86399));
                }
            }
        }
        if (IS_AJAX) {
            if ($search_type == 'select') {
                $count     = $model->where($where)->count('id');
                $start_num = ($page - 1) * $this->limit;
                $data      = $model->where($where)->order($order_by)->limit($start_num, $this->limit)->select();
                foreach ($data as &$val) {
                    $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                    if ($val['settle_time'] > 0) {
                        $val['settle_time'] = date('Y-m-d H:i:s', $val['settle_time']);
                    } else {
                        $val['settle_time'] = "尚未打款";
                    }
                    if ($val['status'] == 2) {
                        $user_info                         = M('user')->field('bank_account,bank_account_real_name')->find($val['user_id']);
                        $val['new_bank_account']           = $user_info['bank_account'];
                        $val['new_bank_account_real_name'] = $user_info['bank_account_real_name'];
                    }
                    $val['status_name'] = $status_arr[$val['status']];
                }
                $this->success(array('data' => $data, 'count' => $count));
            }
        } else {
            if ($search_type == 'down') {
                $data         = $model->where($where)->order($order_by)->select();
                $platform_arr = array('taobao' => '淘宝', 'jingdong' => '京东', 'pinduoduo' => '拼多多');
                foreach ($data as &$val) {
                    $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                    if ($val['settle_time'] > 0) {
                        $val['settle_time'] = date('Y-m-d H:i:s', $val['settle_time']);
                    } else {
                        $val['settle_time'] = "尚未打款";
                    }
                    if ($val['status'] == 2) {
                        $user_info     = M('user')->field('bank_account,bank_account_real_name')->find($val['user_id']);
                        $val['remark'] = $val['remark'] . '，真实姓名：' . $user_info['bank_account_real_name'] . ',支付宝账号：' . $user_info['bank_account'];
                    }
                    $val['status_name']   = $status_arr[$val['status']];
                    $val['platform_name'] = $platform_arr[$val['account_balance_mall_platform']];
                }
                $key_name  = array(
                    'bank_account'           => '支付宝',
                    'bank_account_real_name' => '真实姓名',
                    'money'                  => '提现金额',
                    'status_name'            => '结算状态',
                    'add_time'               => '申请时间',
                    'settle_time'            => '打款时间',
                    'platform_name'          => '平台',
                    'remark'                 => '备注',
                );
                $file_name = '宅喵生活提现';
                if ($platform && $platform != 'all') {
                    $platform_arr = array('taobao' => '淘宝', 'jingdong' => '京东', 'pinduoduo' => '拼多多');
                    $file_name .= '_' . $platform_arr[$platform];
                }
                if ($status) {
                    $file_name .= '_' . $status_arr[$status];
                }
                if ($start_time && $end_time) {
                    $file_name .= '_' . $start_time . '~' . $end_time;
                }
                download_xls($data, $key_name, $file_name);
            }
        }
        $this->assign(array('status_arr' => $status_arr));
        $this->display();
    }

    /**
     * 打款或设置异常操作
     */
    public function setStatus() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id     = I('post.id', 0, 'int');
        $status = I('post.status', 0, 'int');
        $info   = M('withdraw')->find($id);
        $remark = I('post.remark', '', 'trim');
        if (empty($id) || empty($info)) {
            $this->error('用户信息不存在！');
        }
        if ($status == 1) {
            if ($info['status'] == 1) {
                $this->error('已打款，请勿重复操作');
            }
        } else if ($status == 2) {
            if (empty($remark)) {
                $this->error('异常原因不能为空');
            }
            if ($info['status'] != 0) {
                $this->error('打款记录异常，请联系管理员');
            }
        } else {
            $this->error('请求状态不合法！');
        }
        $data = array('status' => $status, 'remark' => $remark);
        if ($status == 1) {
            $data['settle_time'] = time();
        }
        if ($info['status'] == 2 && $status == 1) {
            $user_info                      = M('user')->field('bank_account,bank_account_real_name')->find($info['user_id']);
            $data['bank_account']           = $user_info['bank_account'];
            $data['bank_account_real_name'] = $user_info['bank_account_real_name'];
        }
        $res = M('withdraw')->where(array('id' => $id))->save($data);
        if ($res !== false) {
            $date  = date('m月d日', strtotime($info['add_time']));
            $alert = "您{$date}申请的{$info['money']}元提现已支付到账，请查看！";
            $push  = array('type' => 1, 'alias' => array(strval($info['user_id'])));
            $data  = array('jump_type' => '3', 'content' => 'zhaimiaosh://Withdraw/index');
            $this->_sendPush($alert, $data, $push);
            $this->success('操作成功');
        } else {
            $this->error('操作失败！');
        }
    }

    /**
     * 提现列表
     */
    public function masterRecord() {
        $status_arr = array('未结算', '已结算', '结算异常');
        if (IS_AJAX) {
            $real_name    = I('get.bank_account_real_name', '', 'trim');
            $bank_account = I('get.bank_account', '', 'trim');
            $status       = I('get.status', '', 'trim');
            $platform     = I('get.platform', 'all', 'trim');
            $page         = I('get.page', 1, 'int');
            $date_type    = I('get.date_type', 0, 'int');
            $start_date   = I('get.start_date', '', 'trim');
            $end_date     = I('get.end_date', '', 'trim');

            $model        = M('withdraw');
            $partner_info = M('partner')->where(array('id' => $this->partner_id))->find();
            $where        = array('user_id' => $partner_info['master_user_id']);
            $order_by     = 'id desc';
            if ($real_name) {
                $where['bank_account_real_name'] = $real_name;
            }
            if ($bank_account) {
                $where['bank_account'] = $bank_account;
            }
            if ($status !== '') {
                $where['status'] = $status;

                if ($status == 0) {
                    $order_by = 'add_time asc';
                }
            }
            if ($platform && $platform != 'all') {
                $where['account_balance_mall_platform'] = $platform;
            }
            if ($date_type) {
                if ($start_date && $end_date) {
                    $con = array('between', array(strtotime($start_date), strtotime($end_date) + 86399));
                    if ($date_type == 1) {
                        $where['add_time'] = $con;
                    } else {
                        $where['settle_time'] = $con;
                    }
                }
            }
            $count     = $model->where($where)->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = $model->where($where)->order($order_by)->limit($start_num, $this->limit)->select();
            foreach ($data as &$val) {
                $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                if ($val['settle_time'] > 0) {
                    $val['settle_time'] = date('Y-m-d H:i:s', $val['settle_time']);
                } else {
                    $val['settle_time'] = "尚未打款";
                }
                if ($val['status'] == 2) {
                    $user_info                         = M('user')->field('bank_account,bank_account_real_name')->find($val['user_id']);
                    $val['new_bank_account']           = $user_info['bank_account'];
                    $val['new_bank_account_real_name'] = $user_info['bank_account_real_name'];
                }
                $val['status_name'] = $status_arr[$val['status']];
            }
            $this->success(array('data' => $data, 'count' => $count));
        }
        $this->assign(array('status_arr' => $status_arr));
        $this->display();
    }
}