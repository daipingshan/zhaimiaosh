<?php
/**
 * 合作伙伴后台首页
 */

namespace Partner\Controller;

/**
 * Class IndexController
 *
 * @package Partner\Controller
 */
class IndexController extends CommonController {

    /**
     * 公告基础页面
     */
    public function index() {
        $this->display();
    }


    /**
     * 首页
     */
    public function main() {
        $system_tips = '';
        $partner = M('partner')->where('id=' . $this->partner_id)->find();

        if ($partner['update_taobao_token_time'] < (time() - 86400 * 23)) {
            $system_tips = '淘宝Token即将过期，请及时更新！';
        }
        if ($partner['update_cookie_time'] < (time() - 43200)) {
            $system_tips .= '淘宝联盟cookie更新不正常，请检查！';
        }

        $data['order_num']      = count(M('order')->where(array('add_time' => array('gt', strtotime(date('Y-m-d'))), 'partner_id' => $this->partner_id))->group('order_sn')->select());
        $data['new_user_num']   = M('user')->where(array('add_time' => array('gt', strtotime(date('Y-m-d'))), 'partner_id' => $this->partner_id))->count('id');
        $data['user_num']       = M('user')->where(array('partner_id' => $this->partner_id))->count('id');
        $data['check_user_num'] = M('user')->where(array('pid' => '', 'invite_a_num' => array('gt', 0), 'partner_id' => $this->partner_id))->count('id');
        $data['pay_num']        = M('withdraw')->where(array('status' => 0, 'partner_id' => $this->partner_id))->count('id');
        $data['pay_money']      = M('withdraw')->where(array('status' => 0, 'partner_id' => $this->partner_id))->sum('money') ? : 0.00;
        $data['day_list']       = M('order_commission')->alias('o')
            ->field('sum(commission+zm_subsidy_money) as money,u.*')
            ->join('left join zm_user u ON u.id = o.user_id')
            ->where(array('o.pay_status' => 'paid', 'o.add_time' => array('gt', strtotime(date('Y-m-d'))), 'o.partner_id' => $this->partner_id))
            ->group('user_id')
            ->order('money desc')
            ->limit(100)
            ->select();
        $this->assign('system_tips', $system_tips);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 编辑系统设置
     */
    public function config() {
        if (IS_AJAX) {
            $tmp = array(
                'cookie'              => I('post.cookie', '', 'trim'),
                'taobao_access_token' => I('post.taobao_access_token', '', 'trim'),
            );

            if ($tmp['cookie'] == '' || $tmp['taobao_access_token'] == '') {
                $this->error('信息不能为空');
            }

            $partner = M('partner')->where('id=' . $this->partner_id)->find();
            if (strcmp($partner['cookie'], $tmp['cookie']) != 0) {
                $tmp['update_cookie_time'] = time();
            }
            if (strcmp($partner['taobao_access_token'], $tmp['taobao_access_token']) != 0) {
                $tmp['update_taobao_token_time'] = time();
            }

            $res = M('partner')->where('id=' . $this->partner_id)->save($tmp);
            if ($res !== false) {
                S('partner_config' . $partner['taobao_union_id'], null);
                $this->success('修改成功');
            } else {
                $this->error('修改失败');
            }
        } else {
            $partner = M('partner')->where('id=' . $this->partner_id)->find();
            $partner['update_taobao_token_time'] = date('m-d H:i', $partner['update_taobao_token_time']);
            $partner['update_cookie_time'] = date('m-d H:i', $partner['update_cookie_time']);
            $this->assign('partner', $partner);
            $this->display();
        }
    }

    /**
     * 修改密码
     */
    public function updatePassword() {
        $old_password = I('post.old_password', '', 'trim');
        $new_password = I('post.new_password', '', 'trim');
        if (empty($old_password)) {
            $this->error('原始密码不能为空！');
        }
        if (empty($new_password)) {
            $this->error('新密码不能为空！');
        }
        if (strlen($new_password) < 6 || strlen($new_password) > 18) {
            $this->error('密码长度必须在6-18位字符之间！');
        }
        if ($old_password == $new_password) {
            $this->error('新密码不能与原始密码一致！');
        }
        $db_password  = M('partner')->getFieldById($this->partner_id, 'password');
        $old_password = md5($old_password . C('password_key'));
        if ($db_password != $old_password) {
            $this->error('原始密码错误');
        }
        $new_password = md5($new_password . C('password_key'));
        $res          = M('partner')->where(array('id' => $this->partner_id))->save(array('password' => $new_password));
        if ($res !== false) {
            session('partner_id', null);
            session('partner_name', null);
            $this->success('修改成功！');
        } else {
            $this->error('修改失败！');
        }
    }

    /**
     * 获取菜单
     */
    public function getMenu() {
        //$data = S('admin_menu_' . $this->user_id);
        $data = array(
            array('title' => '后台首页', 'icon' => 'icon-computer', 'href' => '/Index/main', 'spread' => false),
            array('title' => '系统设置', 'icon' => 'icon-computer', 'href' => '/Index/config', 'spread' => false),
            array('title' => '用户管理', 'icon' => '&#xe613;', 'href' => '/User/index', 'spread' => false),
            array('title' => '订单管理', 'icon' => '&#xe629;', 'href' => '/Order/index', 'spread' => false),
            array('title' => '财务管理', 'icon' => 'icon-zhanghu', 'href' => '/Finance/index', 'spread' => false),
            array('title' => '主账号提现记录', 'icon' => '&#xe60e;', 'href' => '/Finance/masterRecord', 'spread' => false),
            array('title' => '今日数据', 'icon' => '&#xe604;',  'href' => '/BISystem/index', 'spread' => false),
            array('title' => '订单统计', 'icon' => '&#xe636;',  'href' => '/BISystem/history', 'spread' => false),
            array('title' => '用户数据', 'icon' => '&#xe612;',  'href' => '/BISystem/userData', 'spread' => false),
            array('title' => '提现报表', 'icon' => '&#xe62c;',  'href' => '/BISystem/withdraw', 'spread' => false),
        );
        die(json_encode(array_values($data) ? : array()));

    }
}