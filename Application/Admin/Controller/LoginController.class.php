<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/22
 * Time: 11:13
 */

namespace Admin\Controller;

use Think\Verify;

/**
 * 登录控制器
 * Class LoginController
 *
 * @package Admin\Controller
 */
class LoginController extends CommonController {

    /**
     * @var bool
     */
    protected $checkUser = false;

    /**
     * 构造方法
     */
    public function __construct() {
        parent::__construct();
        $auth_arr = array('index', 'doLogin');
        if (in_array(ACTION_NAME, $auth_arr) && session('user_id') > 0) {
            $this->redirect('Index/index');
        }
    }

    /**
     * 登录页面
     */
    public function index() {
        $this->display();
    }

    /**
     * 用户登录
     */
    public function doLogin() {
        if (!IS_AJAX || !IS_POST) {
            $this->error('非法请求！');
        }
        $username = I('post.username', '', 'trim');
        $password = I('post.password', '', 'trim');
        $code     = I('post.code', '', 'trim');
        if (empty($username) || empty($password)) {
            $this->error('用户名或密码不能为空');
        }
        if (!$code) {
            $this->error('验证码不能为空');
        }
        $verify = new Verify();
        if (!$verify->check($code)) {
            $this->error('验证码错误');
        }
        $admin = M('admin');
        $where = array(
            'username' => $username,
            'password' => md5($password . C('password_key')),
        );
        $user  = $admin->where($where)->find();
        if (empty($user)) {
            $this->error('您的账号或密码不正确，请稍后重试！');
        }
        if ($user['status'] == 0) {
            $this->error('您的账号已禁用');
        }
        $admin->where(array('id' => $user['id']))->save(array('last_time' => time(), 'last_ip' => get_client_ip()));
        session('user_id', $user['id']);
        session('username', $user['username']);
        $this->success('登录成功！');
    }


    /**
     * 验证码
     */
    public function verify() {
        $config = array(
            'fontSize' => 18,    // 验证码字体大小
            'length'   => 4,     // 验证码位数
            'useNoise' => false, // 关闭验证码杂点
            'imageW'   => 140,
            'imageH'   => 35,
        );
        $Verify = new Verify($config);
        $Verify->entry();
    }

    /**
     * 退出登录
     */
    public function logout() {
        S('admin_rule_id_' . session('user_id'), null);
        S('admin_auth_data_' . session('user_id'), null);
        S('admin_menu_' . session('user_id'), null);
        S('admin_auth_' . session('user_id'), null);
        session('user_id', null);
        session('username', null);
        $this->redirect('index');
    }
}