<?php
/**
 * 合作伙伴管理相关
 * User: dong
 * Date: 2018/3/1
 */

namespace Admin\Controller;

/**
 * 合作伙伴控制器
 * Class AdminController
 *
 * @package Admin\Controller
 */
class PartnerController extends CommonController {

    /**
     * 合作伙伴列表
     */
    public function index() {
        if (IS_AJAX) {
            $keyword = I('get.keyword', '', 'trim');
            $page     = I('get.page', 1, 'int');
            $where    = array();
            if (!empty($keyword)) {
                $where['mobile|partner_name'] = array('like', "%{$keyword}%");
            }
            $count     = M('partner')->where($where)->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = M('partner')->where($where)->order('id desc')->limit($start_num, $this->limit)->select();
            foreach ($data as &$val) {
                $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                $val['update_taobao_token_time'] = date('Y-m-d H:i:s', $val['update_taobao_token_time']);
                $val['update_cookie_time'] = date('Y-m-d H:i:s', $val['update_cookie_time']);
                if ($val['last_time'] > 0) {
                    $val['last_time'] = date('Y-m-d H:i:s', $val['last_time']);
                } else {
                    $val['last_time'] = '暂未登录';
                }
            }
            $this->success(array('data' => $data, 'count' => $count));
        }

        $this->display();
    }

    /**
     * 添加合作伙伴
     */
    public function add() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }

        $mobile       = I('post.mobile', '', 'trim');
        $password     = I('post.password', '', 'trim');
        $partner_name = I('post.partner_name', '', 'trim');
        $pid          = I('post.pid', '', 'trim');
        $remark       = I('post.remark', '', 'trim');

        if (!is_mobile($mobile)) {
            $this->error('登录手机号格式错误！');
        }
        if (empty($password)) {
            $this->error('密码不能为空！');
        }
        if (empty($partner_name)) {
            $this->error('合作伙伴名称不能为空！');
        }
        if (empty($pid)) {
            $this->error('合作伙伴PID不能为空！');
        }
        $reg_exp = "/^m{2}_\d{7,9}_\d{7,9}_\d{7,9}$/";
        if (!preg_match($reg_exp, $pid)) {
            $this->error('PID格式不符合要求！');
        }
        list($_, $taobao_union_id, $_, $_) = explode('_', $pid);

        $data = array(
            'mobile' => $mobile,
            'password' => md5($password . C('password_key')),
            'partner_name' => $partner_name,
            'pid' => $pid,
            'taobao_union_id' => $taobao_union_id,
            'remark' => $remark,
            'add_time' => time()
        );
        $res  = M('partner')->add($data);
        if ($res) {
            $this->success('添加成功');
        } else {
            $this->error('添加失败！');
        }
    }

    /**
     * 修改合作伙伴信息
     */
    public function edit() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('partner')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('账号不存在！');
        }

        $mobile       = I('post.mobile', '', 'trim');
        $password     = I('post.password', '', 'trim');
        $partner_name = I('post.partner_name', '', 'trim');
        $pid          = I('post.pid', '', 'trim');
        $remark       = I('post.remark', '', 'trim');

        if (!is_mobile($mobile)) {
            $this->error('登录手机号格式错误！');
        }
        if (empty($partner_name)) {
            $this->error('合作伙伴名称不能为空！');
        }
        if (empty($pid)) {
            $this->error('合作伙伴PID不能为空！');
        }
        $reg_exp = "/^m{2}_\d{7,9}_\d{7,9}_\d{7,9}$/";
        if (!preg_match($reg_exp, $pid)) {
            $this->error('PID格式不符合要求！');
        }
        list($_, $taobao_union_id, $_, $_) = explode('_', $pid);

        $data = array(
            'id' => $id,
            'mobile' => $mobile,
            'partner_name' => $partner_name,
            'pid' => $pid,
            'taobao_union_id' => $taobao_union_id,
            'remark' => $remark,
        );
        if (!empty($password)) {
            $data['password'] = md5($password . C('password_key'));
        }

        $res = M('partner')->save($data);
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
        $info = M('partner')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('账号不存在！');
        }
        $status = $info['status'] == 0 ? 1 : 0;
        $res    = M('partner')->save(array('status' => $status, 'id' => $id));
        if ($res) {
            $this->success('修改状态成功');
        } else {
            $this->error('修改状态失败！');
        }
    }

}