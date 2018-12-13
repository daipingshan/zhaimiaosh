<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/22
 * Time: 9:40
 */

namespace Admin\Controller;

use Common\Controller\CommonBaseController;

/**
 * 后台公共基础类库
 * Class CommonController
 *
 * @package Admin\Controller
 */
class CommonController extends CommonBaseController {

    /**
     * @var bool
     */
    protected $checkUser = true;

    /**
     * @var bool
     */
    protected $user_id = 0;

    /**
     * @var int
     */
    protected $limit = 10;


    public function __construct() {
        parent::__construct();
        if ($this->checkUser) {
            $this->_checkUser();
            if (C('AUTH_ON') == true) {
                if ($this->_checkAuthData() === false) {
                    $rule_id = $this->_getRuleId($this->user_id);
                    $this->_setMenu($rule_id, $this->user_id);
                    $this->_setAuth($rule_id, $this->user_id);
                }
                $this->_checkAuth();
            } else {
                if ($this->_checkSuperMenuData($this->user_id) === false) {
                    $this->_setSuperMenu($this->user_id);
                }
            }
        }
    }

    /**
     * 检测用户菜单数据是否生成
     *
     * @param $user_id
     * @return bool
     */
    protected function _checkSuperMenuData($user_id) {
        $data = S('admin_menu_' . $user_id);
        if (empty($data)) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * 检测用户权限数据是否生成
     */
    protected function _checkAuthData() {
        $data = S('admin_auth_' . $this->user_id);
        if (empty($data)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 检测是否有权限操作
     */
    protected function _checkAuth() {
        $data    = S('admin_auth_' . $this->user_id);
        $name    = '/' . CONTROLLER_NAME . '/' . ACTION_NAME;
        $auth_id = C('AUTH_ID');
        if (!in_array($this->user_id, $auth_id)) {
            if (!in_array(strtolower($name), $data)) {
                $jump_url = $_SERVER['HTTP_REFERER'];
                $this->error('对不起！无权操作', $jump_url);
            }
        }
    }

    /**
     * 获取用户权限
     *
     * @param $user_id
     * @return array
     */
    protected function _getRuleId($user_id) {
        $rule_id           = array();
        $auth_group_access = M('auth_group_access')->where(array('uid' => $user_id))->getField('group_id', true);
        if ($auth_group_access) {
            $rules = M('auth_group')->where(array('id' => array('in', $auth_group_access)))->getField('rules', true);
            if (count($rules) > 1) {
                foreach ($rules as $v) {
                    $rule_id = array_merge($rule_id, implode(',', $v));
                }
                $rule_id = array_unique($rule_id);
            } else {
                $rule_id = explode(',', $rules[0]);
            }
        }
        if ($rule_id) {
            S('admin_rule_id_' . $user_id, $rule_id);
        }
        return $rule_id;
    }


    /**
     * 设置菜单
     *
     * @param $rule_id
     * @param $user_id
     */
    protected function _setMenu($rule_id, $user_id) {
        if ($rule_id) {
            $parent_list = M('auth_rule')->where(array('parent_id' => 0, 'status' => 1, 'display' => 1, array('id' => array('in', $rule_id))))->index('id')->order('sort asc')->select();
            $list        = M('auth_rule')->where(array('id' => array('in', $rule_id), 'status' => 1, 'display' => 1, 'parent_id' => array('gt', 0)))->order('sort asc')->select();
            $menu_data   = array();
            foreach ($parent_list as $val) {
                $menu_data[$val['id']] = array('title' => $val['title'], 'icon' => $val['icon'], 'href' => $val['name'], 'spread' => false);
            }
            foreach ($list as $val) {
                if (isset($menu_data[$val['parent_id']]) && isset($menu_data[$val['parent_id']])) {
                    $menu_data[$val['parent_id']]['children'][] = array('title' => $val['title'], 'icon' => $val['icon'], 'href' => $val['name'], 'spread' => false);
                }
            }
        }
        if (empty($menu_data)) {
            $list      = M('auth_rule')->find(1);
            $menu_data = array(array('title' => $list['title'], 'icon' => $list['icon'], 'href' => $list['name'], 'spread' => false));
        }
        S('admin_menu_' . $user_id, $menu_data);
    }

    /**
     * 设置菜单
     *
     * @param $user_id
     */
    protected function _setSuperMenu($user_id) {
        $parent_list = M('auth_rule')->where(array('parent_id' => 0, 'status' => 1, 'display' => 1))->index('id')->order('sort asc')->select();
        $list        = M('auth_rule')->where(array('status' => 1, 'display' => 1, 'parent_id' => array('gt', 0)))->order('sort asc')->select();
        $menu_data   = array();
        foreach ($parent_list as $val) {
            $menu_data[$val['id']] = array('title' => $val['title'], 'icon' => $val['icon'], 'href' => $val['name'], 'spread' => false);
        }
        foreach ($list as $val) {
            if (isset($menu_data[$val['parent_id']]) && isset($menu_data[$val['parent_id']])) {
                $menu_data[$val['parent_id']]['children'][] = array('title' => $val['title'], 'icon' => $val['icon'], 'href' => $val['name'], 'spread' => false);
            }
        }
        S('admin_menu_' . $user_id, $menu_data);
    }

    /**
     * 设置权限
     *
     * @param $rule_id
     * @param $user_id
     */
    protected function _setAuth($rule_id, $user_id) {
        if ($rule_id) {
            $auth_name   = M('auth_rule')->where(array('status' => 1, array('id' => array('in', $rule_id))))->getField('name', true);
            $auth_common = C('AUTH_COMMON');
            $auth        = array_unique(array_filter(array_merge($auth_name, $auth_common)));
        }
        if (empty($auth)) {
            $auth = C('AUTH_COMMON');
        }
        $data = array();
        foreach ($auth as $val) {
            $data[] = strtolower($val);
        }
        S('admin_auth_' . $user_id, $data);
    }


    /**
     * 检测用户状态
     */
    protected function _checkUser() {
        $user_id = session('user_id');
        if ($user_id > 0) {
            $this->user_id = $user_id;
        } else {
            if (IS_AJAX) {
                $this->error('登录已失效，请刷新页面');
            } else {
                $this->redirect('Login/index');
            }
        }
    }

    /**
     * @param $url_data
     * @return int
     */
    protected function _btnCheckAuth($url_data) {
        $status = 0;
        if (empty($url_data)) {
            return $status;
        }
        $auth_id = C('AUTH_ID');
        if (C('AUTH_ON') == false || in_array($this->user_id, $auth_id)) {
            $status = 1;
            return $status;
        }
        $auth_data = S('admin_auth_data_' . $this->user_id);
        if (empty($auth_data)) {
            $auth_data = $this->_setAllAuth();
            if ($auth_data) {
                S('admin_auth_data_' . $this->user_id, $auth_data);
            }
        }
        if (is_array($url_data)) {
            foreach ($url_data as $val) {
                if (in_array(strtolower($val), $auth_data)) {
                    $status = 1;
                    break;
                }
            }
        } else {
            if (in_array(strtolower($url_data), $auth_data)) {
                $status = 1;
            }
        }
        return $status;
    }

    /**
     * @return array
     */
    protected function _setAllAuth() {
        $rule_id = S('admin_rule_id_' . $this->user_id);
        if ($rule_id) {
            $auth_name   = M('auth_rule')->where(array(array('id' => array('in', $rule_id))))->getField('name', true);
            $auth_common = C('AUTH_COMMON');
            $auth        = array_unique(array_filter(array_merge($auth_name, $auth_common)));
        }
        if (empty($auth)) {
            $auth = C('AUTH_COMMON');
        }
        $data = array();
        foreach ($auth as $val) {
            $data[] = strtolower($val);
        }
        return $data;
    }
}