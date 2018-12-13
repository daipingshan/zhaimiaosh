<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/17
 * Time: 17:49
 */

namespace Api\Controller;

use Think\Exception;
use Common\Org\TaoBaoApi;

/**
 * 公共接口
 * Class PublicController
 *
 * @package Api\Controller
 */
class PublicController extends CommonController {

    /**
     * 用户登录
     */
    public function login() {
        $mobile   = I('request.mobile', '', 'trim');
        $password = I('request.password', '', 'trim');
        if (empty($mobile)) {
            $this->output('手机号码不能为空！');
        }
        if (empty($password)) {
            $this->output('登录密码不能为空！');
        }
        if (is_mobile($mobile) === false) {
            $this->output('手机号码格式不正确');
        }
        $user = M('user')->where(array('mobile' => $mobile))->find();
        if (empty($user)) {
            $this->output('手机号码尚未注册，请注册后登录！');
        }
        if ($user['password'] != md5($password . C('password_key'))) {
            $this->output('密码错误！');
        }
        if ($user['status'] == 0) {
            $this->output('账号已禁用！');
        }
        $token = $user['token'];
        if (empty($token)) {
            $token = md5($mobile . C('token_key'));
            M('user')->where(array('id' => $user['id']))->save(array('token' => $token));
        }
        $this->output('ok', 'success', array('token' => $token));
    }

    /**
     * 使用小程序openid登录
     */
    public function loginWithMiniProgramOpenid() {
        $mini_program_code   = I('request.mini_program_code', '', 'trim');

        $appid = C('BASE.mini_program_appid');
        $secret = C('BASE.mini_program_secret');
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$mini_program_code}&grant_type=authorization_code";
        $res = file_get_contents($url);
        $mini_program_login_info = json_decode($res, true);
        if (isset($mini_program_login_info['openid'])) {
            $user = M('user')->where(array('mini_program_openid' => $mini_program_login_info['openid']))->find();
            if (empty($user)) {
                $this->output('该账号尚未注册，请注册后登录！');
            }
            if ($user['status'] == 0) {
                $this->output('账号已禁用！');
            }
            $token = $user['token'];
            if (empty($token)) {
                $token = md5($mobile . C('token_key'));
                M('user')->where(array('id' => $user['id']))->save(array('token' => $token));
            }
            $this->output('ok', 'success', array('token' => $token));
        } else {
            $this->output('获取小程序临时登录信息异常！');
        }
    }

    /**
     * 根据团长手机号显示该团下的所有人员
     */
    public function groupLeader() {
        $mobile   = I('request.mobile', '', 'trim');
        if (empty($mobile)) {
            $this->output('手机号码不能为空！');
        }

        $group_leader = M('user')->where(array('mobile' => $mobile))->find();

        $where = array(
            '_logic' => 'OR',
            'id' => $group_leader['id'],
            'inviter_id' => $group_leader['id'],
            'group_leader_id' => $group_leader['id'],
            'group_leader_parent_id' => $group_leader['id']
        );
        $users = M('user')->where($where)->field('mobile,level,add_time')->order('add_time desc')->select();
        if (empty($users)) {
            die('no users');
        }

        echo '<table>';
        echo '<tr><td>手机号</td><td>用户等级</td><td>注册时间</td></tr>';
        foreach ($users as $key => $user) {
            echo '<tr>';
            echo '<td>' . $user['mobile'] . '</td>';

            switch ($user['level']) {
                case 0:
                    $level = '消费者';
                    break;
                case 1:
                    $level = 'V1推广员';
                    break;
                case 2:
                    $level = 'V2推广员';
                    break;
                case 3:
                    $level = 'V3推广员';
                    break;
                case 4:
                    $level = 'V4推广员';
                    break;
                case 5:
                    $level = '团长';
                    break;

                default:
                    $level = '未知';
                    break;
            }

            echo '<td align="center">' . $level . '</td>';
            echo '<td align="center">' . date('Y-m-d H:i:s', $user['add_time']) . '</td>';
            echo '</tr>';
        }
        echo '<table>';
    }

    /**
     * 查询邀请码是否有效。有效返回成功，其他返回失败
     */
    public function checkInviteCode() {
        $invite_code = I('request.invite_code', '', 'trim');
        $invite_code = str_ireplace('agwhwetht', '', $invite_code);

        $invite_a_info = M('user')->where(array('invite_code' => $invite_code))->find();
        if (empty($invite_a_info)) {
            $this->output('邀请码有误，请核对！');
        } else {
            $this->output('ok', 'success', array());
        }
    }

    /**
     * 用户注册
     */
    public function register() {
        $mobile      = I('request.mobile', '', 'trim');
        $password    = I('request.password', '', 'trim');
        $invite_code = I('request.invite_code', '', 'trim');
        $invite_code = str_ireplace(array('agwhwetht', ' '), '', $invite_code);
        $code        = I('request.code', '', 'trim');
        $register_client_platform = I('request.client_platform', '', 'trim');

        if (!in_array($register_client_platform, array('ios','android','mini_program'))) {
            $register_client_platform = 'unknown';
        }

        if (empty($mobile)) {
            $this->output('手机号码不能为空！');
        }
        if (empty($password)) {
            $this->output('登录密码不能为空！');
        }
        if (is_mobile($mobile) === false) {
            $this->output('手机号码格式不正确！');
        }
        $is_mobile = M('user')->where(array('mobile' => $mobile))->count();
        if ($is_mobile > 0) {
            $this->output('手机号码已注册');
        }
        $invite_a_info = M('user')->where(array('invite_code' => $invite_code))->find(); //邀请人信息
        if (empty($invite_a_info)) {
            $this->output('邀请码有误，请核对后注册！');
        }
        $sms_code = S($mobile);
        if ($sms_code != $code) {
            $this->output('验证码错误！');
        }

        $invite_b_id         = M('user')->where(array('id' => $invite_a_info['inviter_id']))->getField('id');
        $invite_code         = create_invite_code();
        $is_have_invite_code = 1;
        while ($is_have_invite_code == 1) {
            $is_have_invite_code = M('user')->where(array('invite_code' => $invite_code))->count('id');
        }
        $model = M();
        $model->startTrans();
        try {
            $pid        = '';
            $parent_pid = $invite_a_info['pid'] ? : $invite_a_info['inviter_pid'];

            $partner = $this->_getPartnerInfo($parent_pid);
            if (!isset($partner['id'])) {
                throw new \Exception('异常，请重试');
            }

            if (empty($invite_a_info['pid'])) {
                $res = $this->_createPid($parent_pid, $invite_a_info['mobile']);
                if ($res['status'] == 1) {
                    $pid = $res['pid'] ? : '';
                }
            }
            $this->_setLevel($invite_a_info, $pid);
            $data    = array(
                'partner_id'             => $partner['id'],
                'mobile'                 => $mobile,
                'password'               => md5($password . C('password_key')),
                'pid'                    => '',
                'inviter_id'             => $invite_a_info['id'],
                'inviter_pid'            => $pid ? : $parent_pid,
                'token'                  => md5($mobile . C('token_key')),
                'invite_code'            => $invite_code,
                'group_leader_id'        => $invite_a_info['group_leader_id'],
                'group_leader_parent_id' => $invite_a_info['group_leader_parent_id'],
                'account_balance'        => 0,
                'add_time'               => time(),
                'register_client_platform' => $register_client_platform
            );
            $user_id = M('user')->add($data);
            if ($invite_a_info['invite_a_num'] == 0) {
                $this->_addCashFlow($invite_a_info['id']);
            }
            if ($invite_b_id > 0) {
                M('user')->where(array('id' => $invite_b_id))->setInc('invite_b_num');
            }
            $this->_addInvite($user_id, $invite_a_info);
            if ($model->commit()) {
                $this->output('ok', 'success', array('token' => $data['token']));
            } else {
                throw new \Exception($model->getError());
            }
        } catch (\Exception $e) {
            $model->rollback();
            $this->_addlog('register', $e->getMessage() . "注册失败");
            $this->output('注册失败');
        }
    }

    /**
     * 更改用户身份
     *
     * @param $invite_a_info
     * @param $pid
     */
    protected function _setLevel($invite_a_info, $pid = '') {
        if ($invite_a_info['level'] > 0) {
            $v4_num = C('COMMISSION_RATE.v4_people_num') - 1;
            $v3_num = C('COMMISSION_RATE.v3_people_num') - 1;
            $v2_num = C('COMMISSION_RATE.v2_people_num') - 1;
            if ($invite_a_info['invite_a_num'] >= $v4_num && $invite_a_info['level'] < 4) {
                $invite_save_data = array('level' => 4, 'level_upgrade_time' => time(), 'invite_a_num' => $invite_a_info['invite_a_num'] + 1);
            } else if ($invite_a_info['invite_a_num'] >= $v3_num && $invite_a_info['level'] < 3) {
                $invite_save_data = array('level' => 3, 'level_upgrade_time' => time(), 'invite_a_num' => $invite_a_info['invite_a_num'] + 1);
            } else if ($invite_a_info['invite_a_num'] >= $v2_num && $invite_a_info['level'] < 2) {
                $invite_save_data = array('level' => 2, 'level_upgrade_time' => time(), 'invite_a_num' => $invite_a_info['invite_a_num'] + 1);
            } else {
                $invite_save_data = array('invite_a_num' => $invite_a_info['invite_a_num'] + 1);
            }
            M('user')->where(array('id' => $invite_a_info['id']))->save($invite_save_data);
        } else {
            $invite_save_data = array('invite_a_num' => $invite_a_info['invite_a_num'] + 1);
            if (empty($invite_a_info['pid']) && !empty($pid)) {
                $invite_save_data['pid']                = $pid;
                $invite_save_data['level']              = 1;
                $invite_save_data['level_upgrade_time'] = time();
            }
            M('user')->where(array('id' => $invite_a_info['id']))->save($invite_save_data);
            if (!empty($pid)) {
                S($invite_a_info['token'], null);
                $child_user_ids = M('invite')->where('inviter_id=' . $invite_a_info['id'])->getField('user_id', true);
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
                            M('user')->where(array('id' => array('in', array_keys($part_update_users))))->save(array('inviter_pid' => $invite_save_data['pid']));
                            foreach ($part_update_users as $u) {
                                S($u['token'], null);
                            }
                        }
                    }
                }
                $alert = "恭喜您已升级为V1推广员,再次下单可获得平台返现！";
                $push  = array('type' => 1, 'alias' => array(strval($invite_a_info['id'])), 'title' => '账号升级，下单有返现');
                $data  = array('jump_type' => '3', 'content' => 'zhaimiaosh://User/index');
                $this->_sendPush($alert, $data, $push);
            }
        }
    }

    /**
     * 添加红包记录
     *
     * @param $user_id
     */
    protected function _addCashFlow($user_id) {
        $data = array(
            'user_id'         => $user_id,
            'order_sn'        => 'red_packet_' . $user_id,
            'order_num'       => 1,
            'source'          => 'red_packet',
            'direction'       => 'add',
            'money'           => C('BASE.red_packet'),
            'account_balance' => C('BASE.red_packet'),
            'add_time'        => time(),
        );
        M('cash_flow')->add($data);

        M('user')->where('id=' . $user_id)->setInc('account_balance', C('BASE.red_packet'));
    }

    /**
     * 添加邀请记录
     *
     * @param $user_id
     * @param $invite_info
     */
    protected function _addInvite($user_id, $inviter_info) {
        $now                  = time();
        $new_inviter_id_queue = array(); //新用户的邀请人队列
        if ($inviter_info['level'] < 5) {
            $parent_invite_list = M('invite')->where(array('user_id' => $inviter_info['id']))->getField('inviter_id', true);
            $start_key          = 0; //开始记录的键
            if ($inviter_info['group_leader_id'] > 0) {
                $start_key = count($parent_invite_list);
            }

            foreach ($parent_invite_list as $key => $inviter_id) {
                if ($inviter_id == $inviter_info['group_leader_id']) {
                    $start_key = $key;
                }

                if ($key >= $start_key) {
                    $new_inviter_id_queue[] = $inviter_id;
                }
            }
        }

        $invite_data = array();
        foreach ($new_inviter_id_queue as $key => $inviter_id) {
            $invite_data[] = array(
                'user_id'    => $user_id,
                'inviter_id' => $inviter_id,
                'add_time'   => $now
            );
        }

        $invite_data[] = array(
            'user_id'    => $user_id,
            'inviter_id' => $inviter_info['id'],
            'add_time'   => $now
        );

        M('invite')->addAll($invite_data);
    }

    /**
     * 忘记密码
     */
    public function forget() {
        $mobile   = I('post.mobile', '', 'trim');
        $password = I('post.password', '', 'trim');
        $code     = I('post.code', '', 'trim');
        if (empty($mobile)) {
            $this->output('手机号码不能为空！');
        }
        if (empty($password)) {
            $this->output('登录密码不能为空！');
        }
        if (is_mobile($mobile) === false) {
            $this->output('手机号码格式不正确！');
        }
        $user_id = M('user')->where(array('mobile' => $mobile))->getField('id');
        if (!$user_id) {
            $this->output('手机号码未注册！');
        }
        $sms_code = S($mobile);
        if ($sms_code != $code) {
            $this->output('验证码错误！');
        }
        $data = array(
            'id'       => $user_id,
            'password' => md5($password . C('password_key')),
        );
        $res  = M('user')->save($data);
        if ($res !== false) {
            $this->output('ok', 'success');
        } else {
            $this->output('修改失败！');
        }
    }

    /**
     * 发送验证码
     */
    public function sendCode() {
        $type   = I('post.type', '', 'trim');
        $mobile = I('post.mobile', '', 'trim');
        $receive_mode = I('post.receive_mode', 'sms', 'trim');
        if ($type == 'register') {
            $id = M('user')->where(array('mobile' => $mobile))->getField('id');
            if ($id) {
                $this->output('你的号码已经注册，请重新输入！');
            }
        } else if ($type == 'forget' || $type == 'set_bank_account') {
            $id = M('user')->where(array('mobile' => $mobile))->getField('id');
            if (!$id) {
                $this->output('你的号码尚未注册！');
            }
        } else {
            $this->output('验证码类型不合法！');
        }
        $code = rand(1000, 9999);
        if ('voice' == $receive_mode) {
            $res  = send_voice($mobile, $code, $type);
        } else {
            $res  = send_sms($mobile, $code);
        }

        if ($res['status'] == 1) {
            S($mobile, $code, C('sms_expire_time'));
            $this->output('ok', 'success');
        } else {
            $this->output($res['info']);
        }
    }

    /**
     * 动态启动加载页面
     */
    public function appLoadInit() {
        $client_platform = I('client_platform', '', 'trim');

        $custom            = C('CUSTOM_SERVICE');
        $custom['wei_xin'] = array_filter($custom['wei_xin']);
        $custom['qq']      = array_filter($custom['qq']);
        $custom['tel']     = array_filter($custom['tel']);
        if (empty($custom['wei_xin'])) {
            $custom['wei_xin'] = array();
        }
        if (empty($custom['qq'])) {
            $custom['qq'] = array();
        }
        if (empty($custom['tel'])) {
            $custom['tel'] = array();
        }
        $data = array(
            'app_init'             => C('APP_INIT'),
            'custom'               => $custom,
            'invite_tip'           => get_invite_tip(0, 0),
            'h5_user_grade'        => C('BASE.user_grade_url'),
            'search_keyword'       => explode(',', C('BASE.search_keyword')),
            'min_withdraw_money'   => C('BASE.min_withdraw_money'),
            'settle_account_days'  => C('COMMISSION_RATE.settle_account_days'),
            'search_tips'          => C('BASE.search_tips'),
            'is_taobao_new_user'   => C('BASE.is_taobao_new_user'),
            'is_open_local_search' => C('BASE.is_open_local_search'),
            'mini_program_title'   => C('MINI_PROGRAM_SHARE.title'),
            'mini_program_pic'     => C('MINI_PROGRAM_SHARE.pic'),
            'advert_data'          => $this->_getAdvert(1, $client_platform),
            'advert2_data'         => $this->_getAdvert(3, $client_platform),
            'base_commission_rate' => 0.9 * (C('COMMISSION_RATE.base_commission_rate') / 100),
            'group_leader_commission_rate' => 0.9 * (C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader')) / 100
        );

        if (C('BASE.is_taobao_new_user') == 1) {
            $data['taobao_new_user_url'] = C('BASE.taobao_new_user_url');
            $data['taobao_new_user_kouling'] = C('BASE.taobao_new_user_kouling');
        } else {
            $data['taobao_new_user_url'] = '';
            $data['taobao_new_user_kouling'] = '';
        }

        $this->output('ok', 'success', $data);
    }

    /***
     * 检测更新
     */
    public function checkUpdate() {
        $app_ver     = I('get.ver', '', 'trim');
        $config      = C('APP_UPDATE');
        $service_ver = $config['version'] ? : '';
        $is_upgrade  = 'N';
        if ($app_ver && strcmp($service_ver, $app_ver) > 0) {
            $is_upgrade = 'Y';
        }
        $data = array(
            'version'      => $service_ver,
            'is_force'     => $config['is_force'] == 1 ? 'Y' : 'N',
            'is_upgrade'   => $is_upgrade,
            'description'  => $config['description'] ? : '',
            'download_url' => $config['down_url'] ? : '',
        );
        $this->output('ok', 'success', $data);
    }

    /**
     * 更新合作伙伴淘宝联盟cookie
     */
    public function updatePartnerCookie() {
        $cookie = I('post.cookie', NULL, 'htmlspecialchars');
        $pid = I('post.pid', NULL, 'htmlspecialchars');

        if ($pid != NULL && $cookie != NULL) {
            $data['cookie']             = $cookie;
            $data['update_cookie_time'] = time();
            $where['pid']               = $pid;
            if (M('partner')->where(array('pid' => $pid))->save($data)) {
                list($_, $taobao_union_id, $site_id, $_) = explode('_', $pid);
                S('partner_cookie' . $taobao_union_id, $cookie);

                $this->output('ok', 'success', array());
            }
        }
        $this->output("错误：未成功写入COOKIE");
    }
}