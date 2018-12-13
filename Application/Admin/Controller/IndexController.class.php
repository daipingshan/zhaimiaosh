<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/22
 * Time: 11:41
 */

namespace Admin\Controller;

/**
 * Class IndexController
 *
 * @package Admin\Controller
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
        $data['order_num']      = count(M('order')->where(array('add_time' => array('gt', strtotime(date('Y-m-d')))))->group('order_sn')->select());
        $data['new_user_num']   = M('user')->where(array('add_time' => array('gt', strtotime(date('Y-m-d')))))->count('id');
        $data['user_num']       = M('user')->count('id');
        $data['check_user_num'] = M('user')->where(array('pid' => '', 'invite_a_num' => array('gt', 0)))->count('id');
        $data['pay_num']        = M('withdraw')->where(array('status' => 0))->count('id');
        $data['pay_money']      = M('withdraw')->where(array('status' => 0))->sum('money') ? : 0.00;
        $data['day_list']       = M('order_commission')->alias('o')
            ->field('sum(commission+zm_subsidy_money) as money,u.*')
            ->join('left join zm_user u ON u.id = o.user_id')
            ->where(array('o.pay_status' => 'paid', 'o.add_time' => array('gt', strtotime(date('Y-m-d')))))
            ->group('user_id')
            ->order('money desc')
            ->limit(100)
            ->select();
        $this->assign('data', $data);
        $this->display();
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
        $db_password  = M('admin')->getFieldById($this->user_id, 'password');
        $old_password = md5($old_password . C('password_key'));
        if ($db_password != $old_password) {
            $this->error('原始密码错误');
        }
        $new_password = md5($new_password . C('password_key'));
        $res          = M('admin')->where(array('id' => $this->user_id))->save(array('password' => $new_password));
        if ($res !== false) {
            session('user_id', null);
            session('username', null);
            $this->success('修改成功！');
        } else {
            $this->error('修改失败！');
        }
    }

    /**
     * 获取菜单
     */
    public function getMenu() {
        $data = S('admin_menu_' . $this->user_id);
        die(json_encode(array_values($data) ? : array()));
    }
}