<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/24
 * Time: 9:53
 */

namespace Api\Controller;
use Common\Org\TaoBaoApi;

/**
 * 用户中心
 * Class UserController
 *
 * @package Api\Controller
 */
class UserController extends CommonController {

    /**
     * @var bool
     */
    protected $checkUser = true;

    /**
     * 用户中心首页
     */
    public function index() {
        $field                   = 'id as user_id,pid,pdd_pid,inviter_pid,avatar,mobile,invite_code,invite_a_num,invite_b_num,nickname,level,bank_account_real_name,bank_account,account_balance,pdd_account_balance,jd_account_balance,update_invite_time,taobao_new_user_kouling,pdd_promotion_mall_url,pdd_promotion_mall_url_update_time';
        $data                    = M('user')->field($field)->find($this->user_id);
        $data['nickname']        = $data['nickname'] ? : hide_mobile($data['mobile']);
        $data['pid']             = $data['pid'] ? : $data['inviter_pid'];
        $data['level_name']      = get_level($data['level']);
        $data['invite_tip']      = get_invite_tip($data['level'], $data['invite_a_num']);
        $data['invite_more_tip'] = get_more_tip($data['level'], $data['invite_a_num']);
        $data['account_balance'] = format_view_money($data['account_balance'] + $data['pdd_account_balance'] + $data['jd_account_balance']);

        /**
         * 拼多多分享相关信息
         */
        $data['pdd_info'] = array(
            'pdd_mall_title' => C('BASE.pdd_promotion_mall_title'),
            'pdd_mall_desc' => C('BASE.pdd_promotion_mall_desc'),
            'pdd_logo_url' => 'https://pic.taodianke.com/static/Api/pdd_logo.png',
            'pdd_mall_url' => 'https://a.toutiaonanren.com/api/d/NlYEn8', //公司账号下的
        );

        /**
         * 定期更新拼多多的商城短链接
         */
        if (!empty($data['pdd_pid']) && $data['pdd_promotion_mall_url_update_time'] < (time() - 518400)) {
            $data['pdd_promotion_mall_url'] = '';
        }

        if (!empty($data['pdd_promotion_mall_url'])) {
            $data['pdd_info']['pdd_mall_url'] = $data['pdd_promotion_mall_url'];
        } else if (!empty($data['pdd_pid']) && empty($data['pdd_promotion_mall_url'])) {
            $pdd_promotion_mall_url = $this->_pddPromotionMallUrl($data['pdd_pid']);
            if (!empty($pdd_promotion_mall_url)) {
                M('user')->where(array('id' => $data['user_id']))->setField('pdd_promotion_mall_url', $pdd_promotion_mall_url);
                $data['pdd_info']['pdd_mall_url'] = $pdd_promotion_mall_url;
            }
        }

        $taobao_new_user_url = C('BASE.taobao_new_user_url');

        if (C('BASE.is_taobao_new_user') == 1) {
            $url = 'https://mos.m.taobao.com/activity_newer?from=pub&pid=' . $data['pid'];

            if ('' != $data['pid']) {
                $taobao_new_user_url = $url;
            }

            if ('' == $data['taobao_new_user_kouling']) {
                if ('' != $data['pid']) {
                    $obj = new TaoBaoApi();
                    $res = $obj->getApiPass($url, '新人有好礼！手机淘宝新人可领取最高188元超级大礼包！超值好货0元购！', 'http://pic.taodianke.com/static/Api/taobaolaxin.png');
                    if (isset($res['data']['tao_kou_ling']) && '' != $res['data']['tao_kou_ling']) {

                        $data['taobao_new_user_kouling'] = $res['data']['tao_kou_ling'];
                        M('user')->where('id=' . $this->user_id)->setField('taobao_new_user_kouling', $res['data']['tao_kou_ling']);
                    } else {
                        $data['taobao_new_user_kouling'] = C('BASE.taobao_new_user_kouling');
                    }
                } else {
                    $data['taobao_new_user_kouling'] = C('BASE.taobao_new_user_kouling');
                }
            }
        } else {
            $data['taobao_new_user_kouling'] = '';
        }

        $data['taobao_new_user_url'] = $taobao_new_user_url;

        /**
         * 本月下单数据
         */
        $today_zero = strtotime(date('Y-m-d'));
        $current_month_first_day = date('Y-m-01');
        $current_month_zero = strtotime($current_month_first_day);
        $today_order_num = 0; //今日订单数
        $today_paid_commission = 0; //今日成交预估
        $current_month_paid_commission = 0; //本月成交预估
        $where = array(
            'user_id' => $this->user_id,
            'pay_status' => array('in', array('paid', 'settle', 'success')),
            'add_time' => array('egt', $current_month_zero)
        );

        $field = 'commission,zm_subsidy_money,add_time';
        $orders = M('order_commission')->where($where)->field($field)->select();
        foreach ($orders as $key => $order_commission) {
            if ($order_commission['add_time'] >= $today_zero) {
                $today_order_num++;
                $today_paid_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
            } else {
                $current_month_paid_commission += $order_commission['commission'] + $order_commission['zm_subsidy_money'];
            }
        }
        $data['today_order_num'] = $today_order_num;
        $data['today_paid_commission'] = format_view_money($today_paid_commission);
        $data['current_month_paid_commission'] = format_view_money($current_month_paid_commission);

        $this->output('ok', 'success', $data);
    }

    /**
     * 绑定小程序账号
     */
    public function bindMiniProgramOpenid() {
        $mini_program_code   = I('request.mini_program_code', '', 'trim');

        $appid = C('BASE.mini_program_appid');
        $secret = C('BASE.mini_program_secret');
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$mini_program_code}&grant_type=authorization_code";
        $res = file_get_contents($url);
        $mini_program_login_info = json_decode($res, true);
        if (isset($mini_program_login_info['openid'])) {
            M('user')->where(array('id' => $this->user_id, 'mini_program_openid' => ''))->setField('mini_program_openid', $mini_program_login_info['openid']);
            $this->output('ok', 'success', array());
        } else {
            $this->output('获取小程序临时登录信息异常！');
        }
    }

    /**
     * 解绑小程序账号
     */
    public function unbindMiniProgramOpenid() {
        M('user')->where(array('id' => $this->user_id))->setField('mini_program_openid', '');
        $this->output('ok', 'success', array());
    }

    /**
     * 修改密码
     */
    public function updatePassword() {
        $old_password = I('post.old_password', '', 'trim');
        $new_password = I('post.new_password', '', 'trim');
        if (empty($old_password)) {
            $this->output('原始密码不能为空！');
        }
        if (empty($new_password)) {
            $this->output('新密码不能为空！');
        }
        $db_password = M('user')->getFieldById($this->user_id, 'password');
        if (md5($old_password . C('password_key')) != $db_password) {
            $this->output('原始密码错误！');
        }
        $res = M('user')->where(array('id' => $this->user_id))->save(array('password' => md5($new_password . C('password_key'))));
        if ($res !== false) {
            $this->output('修改成功', 'success');
        } else {
            $this->output('修改失败！');
        }
    }

    /**
     * 修改邀请码
     */
    public function updateInviteCode() {
        $update_invite_time = M('user')->getFieldById($this->user_id, 'update_invite_time');
        if ($update_invite_time) {
            $this->output('您已经修改过您的邀请码了，暂不支持二次修改！');
        }
        $invite_code = str_ireplace(' ', '', I('post.invite_code', '', 'trim'));
        $invite_code = str_ireplace(' ', '', $invite_code);
        if (strlen($invite_code) < 4 || strlen($invite_code) > 20) {
            $this->output('邀请码长度必须在4-20位英文或数字之间！');
        }
        $is_have_invite_code = M('user')->where(array('invite_code' => $invite_code))->count('id');
        if ($is_have_invite_code > 0) {
            $this->output('邀请码已存在，请重新填写！');
        }
        $data = array('invite_code' => strtolower($invite_code), 'update_invite_time' => time());
        $res  = M('user')->where(array('id' => $this->user_id))->save($data);
        if ($res !== false) {
            $this->output('修改成功', 'success');
        } else {
            $this->output('修改失败！');
        }
    }

    /**
     * 分享海报
     */
    public function sharePoster() {
        $info                 = C('SHARE_POSTER');
        $invite_code          = M('user')->getFieldById($this->user_id, 'invite_code');
        $info['invite_code']  = $invite_code;
        $info['download_url'] = C('BASE.share_poster_down_url') . "?invite_code=" . $invite_code;
        $info['mini_program_path']  = '/pages/index/index?invite_code=' . $invite_code;
        $info['mini_program_title'] = C('MINI_PROGRAM_SHARE.title');
        $info['mini_program_desc']  = C('MINI_PROGRAM_SHARE.desc');
        $info['mini_program_pic']   = C('MINI_PROGRAM_SHARE.pic');
        $this->output('ok', 'success', $info);
    }

    /**
     * 更新用户信息
     */
    public function updateUserInfo() {
        $avatar   = I('post.avatar', '', 'trim');
        $nickname = I('post.nickname', '', 'trim');
        if (empty($avatar)) {
            $this->output('用户图像不能为空！');
        }
        if (empty($nickname)) {
            $this->output('用户昵称不能为空！');
        }
        $res = M('user')->where(array('id' => $this->user_id))->save(array('avatar' => $avatar, 'nickname' => $nickname));
        if ($res !== false) {
            $this->output('更新成功', 'success');
        } else {
            $this->output('更新失败！');
        }
    }

    /**
     * 查看我的团队详情
     */
    public function teamDetail() {
        $data = array(
            'invite_a_num' => 0,
            'invite_b_num' => 0,
            'today_invite_num' => 0,
            'yesterday_invite_num' => 0,
            'total_team_members' => 0,
        );

        $field = 'id,mobile,invite_a_num,invite_b_num,level,account_balance';
        $user = M('user')->field($field)->find($this->user_id);
        if (isset($user['level'])) {
            $data['invite_a_num'] = $user['invite_a_num'];
            $data['invite_b_num'] = $user['invite_b_num'];

            if (5 == $user['level']) {
                $where = array(
                    'group_leader_id' => $this->user_id,
                    'group_leader_parent_id' => $this->user_id,
                    '_logic' => 'OR'
                );
                $data['total_team_members'] = M('user')->where($where)->count();
            }
        }

        $today_zero = strtotime(date('Y-m-d'));
        $yesterday_zero = $today_zero - 86400;
        $where = array(
            'add_time' => array('egt', $yesterday_zero),
            'inviter_id' => $this->user_id
        );
        $users = M('user')->where($where)->field('add_time')->select();
        foreach ($users as $key => $user) {
            if ($user['add_time'] >= $today_zero) {
                $data['today_invite_num'] ++;
            } else {
                $data['yesterday_invite_num'] ++;
            }
        }

        $this->output('ok', 'success', $data);
    }

    /**
     * 查看己邀请注册用户记录
     */
    public function inviteList() {
        $page          = I('get.page', 1, 'int');
        $invite_level  = I('get.invite_level', '', 'trim');
        $level         = I('get.level', -1, 'intval');

        $page--;
        $start_num = $page * $this->limit;

        $field = 'avatar,nickname,level,mobile,add_time';
        if ('son' == $invite_level) {
            $where = array('inviter_id' => $this->user_id);
            if ($level >= 0) {
                $where['level'] = $level;
            }

            $users = M('User')->where($where)->field($field)->order('add_time desc')->limit($start_num, $this->limit)->select();
        } else {
            $son_user_ids = M('User')->where(array('inviter_id' => $this->user_id))->getField('id', true);
            if (empty($son_user_ids)) {
                $users = array();
            } else {
                $where = array('inviter_id' => array('in', $son_user_ids));

                if ($level >= 0) {
                    $where['level'] = $level;
                }

                $users = M('User')->where($where)->field($field)->order('add_time desc')->limit($start_num, $this->limit)->select();
            }
        }

        foreach ($users as $key => $user) {
            switch ($user['level']) {
                case 0:
                    $level_view = '消费者';
                    break;
                case 1:
                    $level_view = 'V1推广员';
                    break;
                case 2:
                    $level_view = 'V2推广员';
                    break;
                case 3:
                    $level_view = 'V3推广员';
                    break;
                case 4:
                    $level_view = 'V4推广员';
                    break;
                case 5:
                    $level_view = '团长';
                    break;
                default:
                    $level_view = '消费者';
                    break;
            }

            $users[$key]['level_view'] = $level_view;
            unset($users[$key]['level']);

            if ('' == $user['nickname']) {
                $users[$key]['nickname'] = '未知';
            }

            $users[$key]['mobile'] = hide_mobile($user['mobile']);
            $users[$key]['add_time'] = date('Y-m-d', $user['add_time']);
        }

        $this->output('ok', 'success', $users);
    }

    /**
     * 获取淘宝拉新用户记录
     */
    public function getTaobaoNewUser() {
        $page = I('get.page', 1, 'int');

        $field = 'id,pid,taobao_new_user_kouling';
        $data  = M('user')->field($field)->find($this->user_id);
        if ('' == $data['pid']) {
            return $this->output('OK', 'success', array());
        }

        list($_, $_, $_, $adzone_id) = explode('_', $data['pid']);
        $obj = new TaoBaoApi();
        $res = $obj->getDgNewUserOrder(intval($adzone_id), $page, 20);

        $data = array();
        foreach ($res as $key => $item) {
            if ($adzone_id != $item['adzone_id']) { //只返回自己邀请的记录
                continue;
            }

            if (isset($item['bind_time'])) {
                $bind_time = strtotime($item['bind_time']);
            } else {
                $bind_time = 0;
            }

            if (isset($item['buy_time'])) {
                $buy_time = strtotime($item['buy_time']);
            } else {
                $buy_time = 0;
            }

            $data[] = array(
                'mobile' => $item['mobile'],
                'status' => $item['status'],
                'register_time' => strtotime($item['register_time']),
                'bind_time' => $bind_time,
                'buy_time'  => $buy_time,
            );
        }

        return $this->output('OK', 'success', $data);
    }
}