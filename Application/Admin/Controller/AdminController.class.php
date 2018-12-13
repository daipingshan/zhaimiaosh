<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/22
 * Time: 14:46
 */

namespace Admin\Controller;

/**
 * 管理员控制器
 * Class AdminController
 *
 * @package Admin\Controller
 */
class AdminController extends CommonController {

    /**
     * 系统管理员
     */
    public function index() {
        if (IS_AJAX) {
            $username = I('get.username', '', 'trim');
            $page     = I('get.page', 1, 'int');
            $where    = array();
            if ($username) {
                $where['username|real_name'] = array('like', "%{$username}%");
            }
            $count     = M('admin')->where($where)->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = M('admin')->where($where)->order('id desc')->limit($start_num, $this->limit)->select();
            foreach ($data as &$val) {
                $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                if ($val['last_time'] > 0) {
                    $val['last_time'] = date('Y-m-d H:i:s', $val['last_time']);
                }
            }
            $this->success(array('data' => $data, 'count' => $count));
        }
        $auth_group_data = M('auth_group')->where(array('status' => 1))->select();
        $this->assign('auth_group_data', json_encode($auth_group_data));
        $this->display();
    }

    /**
     * 获取用户权限
     */
    public function getUserAuthGroup() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $uid = I('get.uid', 0, 'int');
        if (empty($uid)) {
            $this->error('管理员编号异常，无法授权');
        }
        $data = M('auth_group_access')->where(array('uid' => $uid))->getField('group_id', true);
        $this->success(array('data' => $data ? : array()));
    }

    /**
     * 管理员授权
     */
    public function userAuth() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $uid      = I('post.uid', 0, 'int');
        $group_id = I('post.group_id', '', 'trim');
        if (empty($uid)) {
            $this->error('管理员编号异常，无法授权！');
        }
        if (empty($group_id)) {
            $this->error('请选择管理员对应权限组');
        }
        $data = array();
        foreach ($group_id as $val) {
            $data[] = array('uid' => $uid, 'group_id' => $val);
        }
        M('auth_group_access')->where(array('uid' => $uid))->delete();
        $add_res = M('auth_group_access')->addAll($data);
        if ($add_res) {
            $this->success('授权成功');
        } else {
            $this->error('授权失败！');
        }
    }

    /**
     * 添加账号
     */
    public function add() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $username  = I('post.username', '', 'trim');
        $password  = I('post.password', '', 'trim');
        $real_name = I('post.real_name', '', 'trim');
        if (empty($username)) {
            $this->error('账号名称不能为空！');
        }
        if (empty($real_name)) {
            $this->error('真实姓名不能为空！');
        }
        if (empty($password)) {
            $this->error('密码不能为空！');
        }
        $data = array('username' => $username, 'password' => md5($password . C('password_key')), 'real_name' => $real_name, 'add_time' => time());
        $res  = M('admin')->add($data);
        if ($res) {
            $this->success('添加成功');
        } else {
            $this->error('添加失败！');
        }
    }

    /**
     * 修改账号
     */
    public function update() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('admin')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('账号不存在！');
        }
        $username  = I('post.username', '', 'trim');
        $password  = I('post.password', '', 'trim');
        $real_name = I('post.real_name', '', 'trim');
        if (empty($username)) {
            $this->error('账号名称不能为空！');
        }
        if (empty($real_name)) {
            $this->error('真实姓名不能为空！');
        }
        $data = array('username' => $username, 'real_name' => $real_name, 'id' => $id);
        if ($password) {
            $data['password'] = md5($password . C('password_key'));
        }
        $res = M('admin')->save($data);
        if ($res !== false) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败！');
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
        $info = M('admin')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('账号不存在！');
        }
        $status = $info['status'] == 0 ? 1 : 0;
        $msg    = $status == 1 ? '启用' : '禁用';
        $res    = M('admin')->save(array('status' => $status, 'id' => $id));
        if ($res) {
            $this->success($msg . '成功');
        } else {
            $this->error($msg . '失败！');
        }
    }

    /**
     * 权限管理
     */
    public function authList() {
        $parent_list = M('auth_rule')->where(array('parent_id' => 0))->index('id')->order('sort asc')->select();
        $list        = M('auth_rule')->where(array('parent_id' => array('gt', 0)))->order('sort asc')->select();
        $parent_arr  = array();
        foreach ($parent_list as $rule) {
            $parent_arr[$rule['id']] = $rule['title'];
        }
        foreach ($list as $rule) {
            $parent_list[$rule['parent_id']]['son_data'][] = $rule;
        }
        $this->assign('parent_arr', $parent_arr);
        $this->assign('data', $parent_list);
        $this->display();
    }

    /**
     * 添加权限
     */
    public function addAuth() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $parent_id = I('post.parent_id', 0, 'int');
        $name      = I('post.name', '', 'trim');
        $title     = I('post.title', '', 'trim');
        $icon      = I('post.icon', '', 'trim');
        $sort      = I('post.sort', 255, 'int');
        $display   = I('post.display', 1, 'int');
        if (empty($title)) {
            $this->error('权限名称不能为空！');
        }
        $data = array('parent_id' => $parent_id, 'name' => $name, 'title' => $title, 'icon' => $icon, 'sort' => $sort, 'display' => $display);
        $res  = M('auth_rule')->add($data);
        if ($res) {
            $this->success('添加成功');
        } else {
            $this->error('添加失败！');
        }
    }

    /**
     * 添加权限
     */
    public function updateAuth() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('auth_rule')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('权限信息不存在！');
        }
        $name    = I('post.name', '', 'trim');
        $title   = I('post.title', '', 'trim');
        $icon    = I('post.icon', '', 'trim');
        $sort    = I('post.sort', 255, 'int');
        $display = I('post.display', 1, 'int');
        if (empty($title)) {
            $this->error('权限名称不能为空！');
        }
        $data = array('name' => $name, 'title' => $title, 'icon' => $icon, 'sort' => $sort, 'display' => $display);
        $res  = M('auth_rule')->where(array('id' => $id))->save($data);
        if ($res !== false) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败！');
        }
    }

    /**
     * 更新权限状态
     */
    public function setAuthStatus() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('auth_rule')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('权限信息不存在！');
        }
        $status = $info['status'] == 0 ? 1 : 0;
        $msg    = $status == 1 ? '启用' : '禁用';
        $res    = M('auth_rule')->save(array('status' => $status, 'id' => $id));
        if ($res) {
            $this->success($msg . '成功');
        } else {
            $this->error($msg . '失败！');
        }
    }

    /**
     * 权限组列表
     */
    public function authGroup() {
        if (IS_AJAX) {
            $page      = I('get.page', 1, 'int');
            $count     = M('auth_group')->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = M('auth_group')->order('id desc')->limit($start_num, $this->limit)->select();
            $this->success(array('data' => $data, 'count' => $count));
        }
        $this->display();
    }

    /**
     * 添加或修改权限组模板
     */
    public function authGroupDisplay() {
        $id = I('get.id', 0, 'int');
        if ($id > 0) {
            $info = M('auth_group')->find($id);
            $this->assign('info', $info);
        }
        $parent_list = M('auth_rule')->where(array('parent_id' => 0))->index('id')->order('sort asc')->select();
        $list        = M('auth_rule')->where(array('parent_id' => array('gt', 0)))->order('sort asc')->select();
        foreach ($list as $rule) {
            $parent_list[$rule['parent_id']]['son_data'][] = $rule;
        }
        $this->assign('id', $id);
        $this->assign('data', $parent_list);
        $this->display();
    }

    /**
     * 添加权限组
     */
    public function addAuthGroup() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $title  = I('post.title', '', 'trim');
        $remark = I('post.remark', '', 'trim');
        $rules  = I('post.rules', '', 'trim');
        if (empty($title)) {
            $this->error('权限组名称不能为空！');
        }
        if (empty($rules)) {
            $this->error('请选择权限！');
        }
        $data = array('title' => $title, 'remark' => $remark, 'rules' => implode(',', $rules));
        $res  = M('auth_group')->add($data);
        if ($res) {
            $this->success('添加成功');
        } else {
            $this->error('添加失败！');
        }
    }

    /**
     * 修改权限组
     */
    public function updateAuthGroup() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('auth_group')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('权限组信息不存在！');
        }
        $title  = I('post.title', '', 'trim');
        $remark = I('post.remark', '', 'trim');
        $rules  = I('post.rules', '', 'trim');
        if (empty($title)) {
            $this->error('权限组名称不能为空！');
        }
        if (empty($rules)) {
            $this->error('请选择权限！');
        }
        $data = array('title' => $title, 'remark' => $remark, 'rules' => implode(',', $rules));
        $res  = M('auth_group')->where(array('id' => $id))->save($data);
        if ($res) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败！');
        }
    }

    /**
     * 更新权限状态
     */
    public function setGroupStatus() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('auth_group')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('权限组信息不存在！');
        }
        $status = $info['status'] == 0 ? 1 : 0;
        $msg    = $status == 1 ? '启用' : '禁用';
        $res    = M('auth_group')->save(array('status' => $status, 'id' => $id));
        if ($res) {
            $this->success($msg . '成功');
        } else {
            $this->error($msg . '失败！');
        }
    }
}