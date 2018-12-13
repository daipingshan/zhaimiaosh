<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/23
 * Time: 14:03
 */

namespace Admin\Controller;

use Common\Org\AliYunOss as OSS;

/**
 * 广告管理
 * Class AdvertController
 *
 * @package Admin\Controller
 */
class AdvertController extends CommonController {
    /**
     * 商品列表
     */
    public function index() {
        $client_platform_data  = array(
            'all' => 'app和小程序',
            'app' => '仅app',
            'mini_program' => '仅小程序'
        );
        $position_data  = array(
            1 => 'app首页顶部banner',
            2 => 'app首页功能模块',
            3 => '首页横屏展示广告'
        );
        $jump_type_data = array(

            1 => '不跳转',
            2 => 'H5网页跳转',
            3 => 'APP原生跳转',
            4 => '跳转至商品',
            5 => '跳转至小程序',
        );
        if (IS_AJAX) {
            $title     = I('get.title', '', 'trim');
            $client_platform = I('get.client_platform', '', 'trim');
            $position  = I('get.position', 0, 'int');
            $jump_type = I('get.jump_type', 0, 'int');
            $status    = I('get.status', 1, 'int');
            $page      = I('get.page', 1, 'int');
            $model     = M('advert');
            $where     = array('status' => $status);
            if ($title) {
                $where['title'] = array('like', "%{$title}%");
            }
            if ($client_platform != '') {
                $where['client_platform'] = $client_platform;
            }
            if ($position) {
                $where['position'] = $position;
            }
            if ($jump_type) {
                $where['jump_type'] = $jump_type;
            }
            $count     = $model->where($where)->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = $model->where($where)->order('sort asc,id desc')->limit($start_num, $this->limit)->select();
            foreach ($data as &$val) {
                $val['add_time']       = date('Y-m-d', $val['add_time']);
                $val['client_platform_name'] = $client_platform_data[$val['client_platform']];
                $val['position_name']  = $position_data[$val['position']];
                $val['jump_type_name'] = $jump_type_data[$val['jump_type']];
            }
            $this->success(array('data' => $data, 'count' => $count));
        }
        $this->assign(array('client_platform' => $client_platform_data, 'position' => $position_data, 'jump_type' => $jump_type_data));
        $this->display();
    }

    /**
     * 添加广告
     */
    public function add() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $title     = I('post.title', '', 'trim,strip_tags');
        $position  = I('post.position', 0, 'int');
        $jump_type = I('post.jump_type', 0, 'int');
        $img_url   = I('post.img_url', '', 'trim');
        $content   = I('post.content', '', 'trim');
        $sort      = I('post.sort', 255, 'int');
        $client_platform    = I('post.client_platform', '', 'trim');
        $item_mall_platform = I('post.item_mall_platform', 'none', 'trim');
        if (empty($title)) {
            $this->error('广告标题不能为空！');
        }
        if (empty($position)) {
            $this->error('请选择广告位置！');
        }
        if (empty($position)) {
            $this->error('请选择广告类型！');
        }
        if (empty($img_url)) {
            $this->error('请上传广告图片！');
        }
        if (empty($content) && $jump_type > 1) {
            $this->error('请输入广告跳转内容！');
        }
        if (4 == $jump_type && 'taobao' == $item_mall_platform) { //去除淘宝不在线的商品和去重
            $old_num_iids = explode(',', $content);
            $num_iids = M('items')->where(array('num_iid' => array('in', $old_num_iids)))->getField('num_iid', true);
            $content = implode(',', array_unique(array_intersect($old_num_iids, $num_iids)));
        }
        $data = array(
            'title'     => $title,
            'position'  => $position,
            'jump_type' => $jump_type,
            'img_url'   => $img_url,
            'content'   => $content,
            'sort'      => $sort,
            'status'    => 1,
            'add_time'  => time(),
            'client_platform'    => $client_platform,
            'item_mall_platform' => $item_mall_platform,
        );
        $res  = M('advert')->add($data);
        if ($res !== false) {
            S('advert', null);
            $this->success('添加成功');
        } else {
            $this->error('添加失败！');
        }
    }

    /**
     * 修改广告
     */
    public function update() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $title     = I('post.title', '', 'trim,strip_tags');
        $position  = I('post.position', 0, 'int');
        $jump_type = I('post.jump_type', 0, 'int');
        $img_url   = I('post.img_url', '', 'trim');
        $content   = I('post.content', '', 'trim');
        $sort      = I('post.sort', 255, 'int');
        $client_platform    = I('post.client_platform', '', 'trim');
        $item_mall_platform = I('post.item_mall_platform', 'none', 'trim');
        $id        = I('post.id', 0, 'int');
        $info      = M('advert')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('广告信息不存在！');
        }
        if (empty($title)) {
            $this->error('广告标题不能为空！');
        }
        if (empty($position)) {
            $this->error('请选择广告类型！');
        }
        if (empty($img_url)) {
            $this->error('请上传广告图片！');
        }
        if (empty($content) && $jump_type > 1) {
            $this->error('请输入广告跳转内容！');
        }
        if (4 == $jump_type && 'taobao' == $item_mall_platform) { //去除淘宝不在线的商品和去重
            $old_num_iids = explode(',', $content);
            $num_iids = M('items')->where(array('num_iid' => array('in', $old_num_iids)))->getField('num_iid', true);
            $content = implode(',', array_unique(array_intersect($old_num_iids, $num_iids)));
        }
        $data = array(
            'id'        => $id,
            'title'     => $title,
            'position'  => $position,
            'jump_type' => $jump_type,
            'img_url'   => $img_url,
            'content'   => $content,
            'sort'      => $sort,
            'client_platform'    => $client_platform,
            'item_mall_platform' => $item_mall_platform,
        );
        $res  = M('advert')->save($data);
        if ($res !== false) {
            S('advert', null);
            $this->success('修改成功');
        } else {
            $this->error('修改失败！');
        }
    }

    /**
     * 更新广告状态
     */
    public function setStatus() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('advert')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('广告信息不存在！');
        }
        $status = $info['status'] == 0 ? 1 : 0;
        $msg    = $status == 1 ? '启用' : '禁用';
        $res    = M('advert')->save(array('status' => $status, 'id' => $id));
        if ($res) {
            S('advert', null);
            $this->success($msg . '成功');
        } else {
            $this->error($msg . '失败！');
        }
    }

    /**
     * 删除广告
     */
    public function delete() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }

        $id        = I('post.id', 0, 'int');
        $info      = M('advert')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('广告信息不存在！');
        }
        if (1 == $info['status']) {
            $this->error('不能删除正在启用的广告');
        }

        $res  = M('advert')->delete($id);
        if ($res !== false) {
            $path_info = parse_url($info['img_url']);
            $oss = new OSS();
            $oss->deleteObject(substr($path_info['path'], 1));

            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }
}