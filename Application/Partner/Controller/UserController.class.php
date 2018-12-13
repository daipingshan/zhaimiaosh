<?php
/**
 * 用户管理
 */

namespace Partner\Controller;

/**
 * 用户管理
 * Class UserController
 *
 * @package Partner\Controller
 */
class UserController extends CommonController {

    /**
     * 用户列表
     */
    public function index() {
        $level_arr = array('' => '全部', '消费者', 'V1推广员', 'V2推广员', 'V3推广员', 'V4推广员', '团长');
        if (IS_AJAX) {
            $mobile       = I('get.mobile', '', 'trim');
            $invite_code  = I('get.invite_code', '', 'trim');
            $invite_a_num = I('get.invite_a_num', 0, 'int');
            $level        = I('get.level', '', 'trim');
            $is_auth      = I('get.is_auth', 0, 'int');
            $sort_field   = I('get.sort_field', '', 'trim');
            $start_time   = I('get.start_time', '', 'trim');
            $end_time     = I('get.end_time', '', 'trim');
            $page         = I('get.page', 1, 'int');
            $model        = M('user');
            $where        = array('partner_id' => $this->partner_id);
            $order        = "id desc";
            if ($mobile) {
                $where['mobile'] = $mobile;
            }
            if ($invite_code) {
                $where['invite_code'] = $invite_code;
            }
            if ($invite_a_num > 0) {
                $where['invite_a_num'] = array('egt', $invite_a_num);
            }
            if ($level !== '') {
                $where['level'] = $level;
            }
            if ($is_auth == 1) {
                $where['pid']          = '';
                $where['invite_a_num'] = array('gt', 0);
            }
            if ($sort_field) {
                $order = $sort_field . " desc,{$order}";
            }
            if ($start_time && $end_time) {
                $where['add_time'] = array('between', array(strtotime($start_time), strtotime($end_time) + 86399));
            }
            $count     = $model->where($where)->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = $model->where($where)->order($order)->limit($start_num, $this->limit)->select();
            foreach ($data as &$val) {
                $val['time']   = date('Y-m-d H:i', $val['add_time']) . '注册';

                if ($val['level_upgrade_time'] > 0) {
                    $val['time'] .= '<br />' . date('Y-m-d H:i', $val['level_upgrade_time']) . '升级';
                }

                $val['level_name'] = isset($level_arr[$val['level']]) ? $level_arr[$val['level']] : '未知身份';
            }
            $this->success(array('data' => $data, 'count' => $count));
        }
        $this->assign(array('level_arr' => $level_arr));
        $this->display();
    }

    /**
     * 设置用户PID
     */
    public function setPid() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $pid  = I('post.pid', '', 'trim');
        $info = M('user')->where(array('id' => $id, 'partner_id' => $this->partner_id))->find($id);
        if (empty($id) || empty($info)) {
            $this->error('用户信息不存在！');
        }
        if (empty($pid)) {
            $this->error('用户PID不能为空！');
        }
        $reg_exp = "/^m{2}_\d{7,9}_\d{7,10}_\d{7,12}$/";
        if (!preg_match($reg_exp, $pid)) {
            $this->error('PID格式不符合要求！');
        }
        list($_, $_, $invite_site_id, $_) = explode('_', $info['inviter_pid']);
        list($_, $_, $site_id, $_) = explode('_', $pid);
        if ($invite_site_id != $site_id) {
            $this->error('导购ID必须与邀请人导购ID一致！');
        }

        $user = M('user')->where(array('pid' => $pid))->find();
        if (!empty($user)) {
            $this->error('该PID已被使用，请勿重复设置！');
        }

        $model = M();
        $model->startTrans();
        try {
            $v4_num = C('COMMISSION_RATE.v4_people_num');
            $v3_num = C('COMMISSION_RATE.v3_people_num');
            $v2_num = C('COMMISSION_RATE.v2_people_num');
            if ($info['invite_a_num'] >= $v4_num) {
                $level = 4;
            } else if ($info['invite_a_num'] >= $v3_num) {
                $level = 3;
            } else if ($info['invite_a_num'] >= $v2_num) {
                $level = 2;
            } else {
                $level = 1;
            }
            $data = array('level' => $level, 'pid' => $pid, 'level_upgrade_time' => time());
            M('user')->where(array('id' => $id))->save($data);
            S($info['token'], null);

            /**
             * 修改所有子孙级的邀请人
             */
            $child_user_ids = M('invite')->where('inviter_id=' . $id)->getField('user_id', true);
            if (!empty($child_user_ids)) {
                $child_users  = M('user')->where(array('id' => array('in', $child_user_ids)))->getField('id,pid,token');
                $update_users = array(); //待更新邀请者pid的用户
                foreach ($child_users as $user_id => $user) {
                    $update_users[$user_id] = $user;
                    if ($user['pid'] != '') {
                        break;
                    }
                }
                if (!empty($update_users)) {
                    $chunk_update_users = array_chunk($update_users, 999, true);
                    foreach ($chunk_update_users as $key => $part_update_users) { //一次最多修改999人
                        M('user')->where(array('id' => array('in', array_keys($part_update_users))))->save(array('inviter_pid' => $pid));
                        foreach ($part_update_users as $u) {
                            S($u['token'], null);
                        }
                    }
                }
            }

            $model->commit();

            $alert = "恭喜您已升级为V{$level}推广员,再次下单可获得平台返现！";
            $push  = array('type' => 1, 'alias' => array(strval($id)), 'title' => '账号升级，下单有返现');
            $data  = array('jump_type' => '3', 'content' => 'zhaimiaosh://User/index');
            $this->_sendPush($alert, $data, $push);

            $this->success('授权成功');
        } catch (\Exception $e) {
            $model->rollback();
            $this->error('授权失败，请重新授权！');
        }
    }

    /**
     * 修改用户密码
     */
    public function updatePassword() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id       = I('post.id', 0, 'int');
        $password = I('post.password', '', 'trim');
        $info     = M('user')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('用户信息不存在！');
        }
        if (empty($password)) {
            $this->error('用户密码不能为空！');
        }
        if (strlen($password) < 6 || strlen($password) > 18) {
            $this->error('用户密码长度必须在6-18位！');
        }
        $res = M('user')->where(array('id' => $id, 'partner_id' => $this->partner_id))->save(array('token' => '', 'password' => md5(md5($password) . C('password_key'))));
        if ($res !== false) {
            S($info['token'], null);
            $this->success('修改成功');
        } else {
            $this->error('修改失败！');
        }
    }

    /**
     * 更新账号级别
     */
    public function setLevel() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('user')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('用户信息不存在！');
        }
        $res = $this->_setGroupLeader($id);
        if ($res['status'] == 1) {
            $alert = "恭喜您已升级为团长！";
            $push  = array('type' => 1, 'alias' => array(strval($id)), 'title' => '账号升级');
            $data  = array('jump_type' => '3', 'content' => 'zhaimiaosh://User/index');
            $this->_sendPush($alert, $data, $push);

            $this->success('升级成功！');
        } else {
            $this->success($res['info']);
        }
    }


    /**
     * 更新账号状态
     */
    public function setStatus() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('user')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('用户信息不存在！');
        }
        $status = $info['status'] == 0 ? 1 : 0;
        $msg    = $status == 1 ? '启用' : '禁用';
        $res    = M('user')->save(array('status' => $status, 'id' => $id, 'token' => ''));
        if ($res !== false) {
            S($info['token'], null);
            $this->success($msg . '成功');
        } else {
            $this->error($msg . '失败！');
        }
    }

    /**
     * 查看用户详情
     */
    public function userInfo() {
        $level_arr     = array('消费者', 'V1推广员', 'V2推广员', 'V3推广员', 'V4推广员', '团长');
        $source_arr    = array('' => '全部', 'self' => '自购推广', 'son' => '邀请分成', 'group_leader' => '团长分成', 'award' => '平台奖励', 'withdraw' => '提现', 'red_packet' => '现金红包');
        $direction_arr = array('' => '全部', 'add' => '收入', 'dec' => '支出');
        $user_id       = I('get.user_id', 0, 'int');
        $info          = M('user')->alias('u')->where('u.id=' . $user_id)->join('left join zm_user as i on u.inviter_id=i.id')->field('u.*,i.mobile as i_mobile,i.invite_code as i_invite_code')->find();
        if (!$user_id || !$info) {
            $this->assign('error_info', '用户信息不存在无法查看');
        }
        $info['level_name'] = isset($level_arr[$info['level']]) ? $level_arr[$info['level']] : '未知身份';
        $this->assign(array('info' => $info, 'source_arr' => $source_arr, 'direction_arr' => $direction_arr));
        $this->display();
    }

    /**
     * 用户流水记录
     */
    public function cashFlow() {
        $source_arr    = array('self' => '自购推广', 'son' => '邀请分成', 'group_leader' => '团长分成', 'award' => '平台奖励', 'withdraw' => '提现', 'red_packet' => '现金红包');
        $direction_arr = array('add' => '收入', 'dec' => '支出');
        $user_id       = I('get.user_id', 0, 'int');
        $order_sn      = I('get.order_sn', '', 'trim');
        $source        = I('get.source', '', 'trim');
        $direction     = I('get.direction', '', 'trim');
        $start_date    = I('get.start_date', '', 'trim');
        $end_date      = I('get.end_date', '', 'trim');
        $page          = I('get.page', 1, 'int');
        $where         = array('user_id' => $user_id);
        if ($order_sn) {
            $where['order_sn'] = $order_sn;
        }
        if ($source) {
            $where['source'] = $source;
        }
        if ($direction) {
            $where['direction'] = $direction;
        }
        if ($start_date && $end_date){
            $where['add_time'] = array('between', array(strtotime($start_date), strtotime($end_date) + 86399));
        }
        $model      = M('cash_flow');
        $count      = $model->where($where)->count('id');
        $flow_detail = $model->field('sum(money) as total_money,direction')->where($where)->group('direction')->select();

        $income_money = 0;
        $expend_money = 0;
        if (!empty($flow_detail)){
            foreach ($flow_detail as $val){
                if ($val['direction']=='add'){
                    $income_money = $val['total_money'];
                }else{
                    $expend_money = $val['total_money'];
                }
            }
        }

        $start_num = ($page - 1) * $this->limit;
        $data      = $model->where($where)->order('id desc')->limit($start_num, $this->limit)->select();
        foreach ($data as &$flow) {
            $flow['add_time']       = date('Y-m-d H:i:s', $flow['add_time']);
            $flow['source_name']    = isset($source_arr[$flow['source']]) ? $source_arr[$flow['source']] : '未知来源';
            $flow['direction_name'] = isset($direction_arr[$flow['direction']]) ? $direction_arr[$flow['direction']] : '未知流向';
        }
        $this->success(array('data' => $data, 'count' => $count,'income_money'=>$income_money,'expend_money'=>$expend_money));
    }
}