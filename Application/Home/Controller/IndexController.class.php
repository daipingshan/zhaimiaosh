<?php
namespace Home\Controller;

use Common\Controller\CommonBaseController;

class IndexController extends CommonBaseController {

    /**
     * 首页
     */
    public function index() {
        $this->display();
    }

    /**
     * 企业简介
     */
    public function describe() {
        $this->display();
    }

    /**
     * 下载app
     */
    public function download() {
        $this->display();
    }

    /**
     * 比优助手下载
     */
    public function byDownload() {
        $this->display();
    }

    /**
     * 报名信息
     */
    public function signUp() {
        $param = array(
            'username' => I('post.user', '', 'trim'),
            'mobile'   => I('post.mobile', '', 'trim'),
            'type'     => I('post.type', 0, 'int'),
        );
        if (empty($param['username']) || empty($param['mobile']) || empty($param['type'])) {
            $this->error('姓名，手机或报名类型不能为空');
        }
        $count = M('join_consumer')->where(array('mobile' => $param['mobile']))->count('id');
        if ($count > 0) {
            $this->error('该手机号码已参加过报名了');
        }
        $param['create_time'] = time();
        $join_status          = M('join_consumer')->add($param);
        if ($join_status) {
            $this->success('报名成功');
        } else {
            $this->error('报名失败');
        }
    }


}