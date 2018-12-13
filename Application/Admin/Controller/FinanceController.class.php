<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/25
 * Time: 17:11
 */

namespace Admin\Controller;

/**
 * 财务管理
 * Class FinanceController
 *
 * @package Admin\Controller
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
        $partner_id   = I('get.partner_id', 0, 'intval');
        $time_type    = I('get.time_type', '', 'trim');
        $start_time   = I('get.start_time', '', 'trim');
        $end_time     = I('get.end_time', '', 'trim');
        $search_type  = I('get.search_type', 'select', 'trim');
        $page         = I('get.page', 1, 'int');
        $partners     = M('partner')->index('id')->select();
        $model        = M('withdraw');
        $where        = array();
        $order_by     = 'w.id desc';
        if ($real_name) {
            $where['w.bank_account_real_name'] = $real_name;
        }
        if ($bank_account) {
            $where['w.bank_account'] = $bank_account;
        }
        if ($status !== '') {
            $where['w.status'] = $status;

            if ($status == 0) {
                $order_by = 'w.add_time asc';
            }
        }
        if ($partner_id > 0) {
            $where['w.partner_id'] = $partner_id;
        }
        if ($platform && $platform != 'all') {
            $where['w.account_balance_mall_platform'] = $platform;
        }
        if ($time_type && $time_type != 'all') {
            if ($time_type == 'add_time' || $time_type == 'settle_time') {
                if ($start_time && $end_time) {
                    $where['w.' . $time_type] = array('between', array(strtotime($start_time), strtotime($end_time) + 86399));
                }
            }
        }
        if (IS_AJAX) {
            if ($search_type == 'select') {
                $count     = $model->alias('w')->where($where)->count('id');
                $start_num = ($page - 1) * $this->limit;
                $data      = $model->alias('w')->join('left join zm_partner as p on w.partner_id=p.id')->where($where)->order($order_by)->limit($start_num, $this->limit)->field('w.*,p.partner_name')->select();
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
                $data         = $model->alias('w')->join('left join zm_partner as p on w.partner_id=p.id')->where($where)->order($order_by)->field('w.*,p.partner_name')->select();
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
                    'partner_name'           => '合作伙伴',
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
                if ($partner_id > 0) {
                    $file_name .= '_' . $partners[$partner_id]['partner_name'];
                }
                if ($platform && $platform != 'all') {
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
        $this->assign(array('status_arr' => $status_arr, 'partners' => $partners));
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
            $date  = date('m月d日', $info['add_time']);
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
     * 京东免单订单
     */
    public function jdFreeOrder() {
        $status_arr = array('ing' => '审核中', 'finished' => '审核成功', 'invalid' => '审核失败');
        $search_type = I('get.search_type', 'select', 'trim');
        $mobile     = I('get.mobile', '', 'trim');
        $order_sn   = I('get.order_sn', '', 'trim');
        $status     = I('get.status', '', 'trim');
        $start_date = I('get.start_date', '', 'trim');
        $end_date   = I('get.end_date', '', 'trim');
        $page       = I('get.page', 1, 'int');
        $model      = M('jd_subsidy_order');
        $where      = array('so.user_id' => array('gt', 0));
        $order_by   = 'so.id desc';
        if (!empty($mobile)) {
            $where['u.mobile'] = $mobile;
        }
        if ($order_sn) {
            $where['so.order_sn'] = $order_sn;
        }
        if ($status) {
            $where['so.status'] = $status;
            if ($status != 'ing') {
                $order_by = 'so.subsidy_time asc';
            }
        }
        if ($start_date && $end_date) {
            $con = array('between', array(strtotime($start_date), strtotime($end_date) + 86399));
            if ($status == 'ing') {
                $where['so.add_time'] = $con;
            } else if ($status == 'finished' || $status == 'invalid') {
                $where['so.subsidy_time'] = $con;
            }
        }

        if (IS_AJAX) {
            $count     = $model->alias('so')->join('left join zm_user as u ON so.user_id=u.id')
                        ->where($where)->count('so.id');
            $start_num = ($page - 1) * $this->limit;
            $data      = $model->alias('so')->join('left join zm_user as u ON so.user_id=u.id')
                        ->field('so.*,u.mobile')
                        ->where($where)->order($order_by)
                        ->limit($start_num, $this->limit)->select();

            foreach ($data as &$val) {
                $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                if ($val['subsidy_time'] > 0) {
                    $val['subsidy_time'] = date('Y-m-d H:i:s', $val['subsidy_time']);
                } else {
                    $val['subsidy_time'] = "尚未审核";
                }
                $val['status_name'] = $status_arr[$val['status']];
            }
            $this->success(array('data' => $data, 'count' => $count));
        } else if ($search_type == 'down') {
            $field = 'so.*,u.mobile,u.bank_account_real_name,u.bank_account,o.total_money,o.pay_status';
            $data = $model->alias('so')->join('left join zm_user as u ON so.user_id=u.id')
                    ->join('left join zm_order as o ON so.order_sn=o.order_sn')
                    ->where($where)->group('so.order_sn')->order($order_by)->field($field)->select();
            $pay_status_arr = array('paid' => '订单付款', 'settle' => '订单结算', 'fail' => '订单无效', 'success' => '订单成功', 'refund' => '订单退款');
            foreach ($data as &$val) {
                $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                if ($val['subsidy_time'] > 0) {
                    $val['subsidy_time'] = date('Y-m-d H:i:s', $val['subsidy_time']);
                } else {
                    $val['subsidy_time'] = "尚未打款";
                }
                $val['status_name']   = $status_arr[$val['status']];
                $val['pay_status_name'] = $pay_status_arr[$val['pay_status']];
            }
            $key_name  = array(
                'mobile'                 => '用户手机号',
                'order_sn'               => '订单号',
                'bank_account_real_name' => '真实姓名',
                'bank_account'           => '支付宝',
                'total_money'            => '订单金额',
                'subsidy_money'          => '补贴金额',
                'status_name'            => '申请免单状态',
                'pay_status_name'        => '订单状态',
                'add_time'               => '申请时间',
                'subsidy_time'           => '补贴时间',
                'remark'                 => '备注',
            );
            $file_name = '京东免单补贴';
            if (!empty($mobile)) {
                $file_name .= '_' . $mobile;
            }
            if (!empty($order_sn)) {
                $file_name .= '_' . $order_sn;
            }
            if ($status) {
                $file_name .= '_' . $status_arr[$status];
            }
            if ($start_time && $end_time) {
                $file_name .= '_' . $start_time . '~' . $end_time;
            }

            download_xls($data, $key_name, $file_name);
        }

        $this->assign(array('status_arr' => $status_arr));
        $this->display();
    }

    /**
     * 查看京东订单详情
     */
    public function jdOrderDetail() {
        $id   = I('get.id', 0, 'int');
        $info = M('jd_subsidy_order')->find($id);
        if (!$id || !$info) {
            $this->assign('error_info', '请求参数不合法！');
        } else {
            $subsidy_status_arr = array('ing' => '审核中', 'finished' => '审核成功', 'invalid' => '审核失败');
            $status_arr         = array(
                'fail'    => '订单失效',
                'success' => '订单成功',
                'paid'    => '订单付款',
                'settle'  => '订单结算',
                'refund'  => '订单退款'
            );
            $order              = M('order')->where(array('order_sn' => $info['order_sn']))->select();
            $money              = 0;
            foreach ($order as $v) {
                $money += $v['total_money'];
            }
            $user = M('user')->find($info['user_id']);
            $this->assign(array('info' => $info, 'order' => $order, 'user' => $user, 'status_arr' => $status_arr, 'subsidy_status_arr' => $subsidy_status_arr, 'money' => $money));
        }
        $this->display();
    }

    /**
     * 批量审核通过免单
     */
    public function batchPassFreeOrder() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }

        $order_sns = I('post.order_sns', '', 'trim');
        $order_sns = explode(' ', str_ireplace(array(" ","　","\t","\n","\r"), ' ', $order_sns));
        //$order_sns = array_unique($order_sns); //不去重，可以查看订单重复
        $tips = '共' . count($order_sns) . '个订单号<br />';
        $now = time();
        foreach ($order_sns as $key => $order_sn) {
            if (!empty($order_sn)) {
                $total_money = M('order')->where(array('order_sn' => $order_sn))->sum('total_money');
                if (0 == $total_money) {
                    $tips .= '单号为' . $order_sn . '的支付金额为0或未查到，未审核通过<br />';
                } else {
                    $update_data = array(
                        'subsidy_money' => $total_money,
                        'status' => 'finished',
                        'subsidy_time' => $now
                    );
                    $update_num = M('jd_subsidy_order')->where(array('order_sn' => $order_sn, 'status' => 'ing'))->save($update_data);
                    if ($update_num == 0) {
                        $tips .= '单号为' . $order_sn . '的未查到或状态异常，未审核通过<br />';
                    } else {
                        $tips .= '单号为' . $order_sn . '的已审核通过<br />';
                    }
                }
            }
        }
        $this->success($tips);
    }

    /**
     * 设置订单审核状态
     */
    public function setJdOrderStatus() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('jd_subsidy_order')->find($id);
        if (!$id || !$info) {
            $this->error('请求参数不合法！');
        }
        $status = I('post.status', 'finished', 'trim');
        $remark = I('post.remark', '', 'trim');
        $money  = I('post.money', '', 'trim');
        if ($status == 'finished') {
            $order = M('order')->where(array('order_sn' => $info['order_sn']))->field('total_money')->select();
            if (empty($order)) {
                $this->error('该订单异常，无法找到对应订单，不能审核通过！');
            }
            if ($money == 0) {
                $this->error('补贴金额不能为0');
            }
        } else {
            if (!$remark) {
                $this->error('设置异常时，备注不能为空！');
            }
            $money = 0;
        }
        $data = array('id' => $id, 'status' => $status, 'subsidy_money' => $money, 'remark' => $remark, 'subsidy_time' => time());
        $res  = M('jd_subsidy_order')->save($data);
        if ($res) {
            $this->success('操作成功');
        } else {
            $this->error('操作失败');
        }
    }
}