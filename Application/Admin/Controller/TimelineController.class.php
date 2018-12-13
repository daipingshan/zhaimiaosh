<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 2018/4/17
 * Time: 09:47
 */

namespace Admin\Controller;

use Common\Org\AliYunOss as OSS;

class TimelineController extends CommonController {

    /**
     * 宅喵说列表
     */
    public function index() {
        if (IS_AJAX) {
            $page        = I('get.page', 1, 'int');
            $author_name = I('get.author_name', '', 'trim');
            $num_iid     = I('get.num_iid', '', 'trim');
            $date        = I('get.date', '', 'trim');
            $status      = I('get.status', '', 'trim');

            if ($author_name) {
                $where['author_name'] = $author_name;
            }
            if ($num_iid) {
                $where['num_iid'] = $num_iid;
            }
            if ($date) {
                $where['add_time'] = ['between', [strtotime($date), strtotime($date) + 86400]];
            }
            if (in_array($status, array(0, 1))) {
                $where['status'] = $status;
            }

            $model     = M('timeline');
            $count     = $model->where($where)->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = $model->where($where)->order('add_time desc')->limit($start_num, $this->limit)->select();
            foreach ($data as &$val) {
                $val['content']     = substr_text($val['content'], 0, 80);
                $val['add_time']    = date('Y-m-d H:i:s', $val['add_time']);
                $val['create_time'] = date('Y-m-d H:i:s', $val['create_time']);
            }
            $this->success(['data' => $data, 'count' => $count]);
        }
        $author_list = M('author')->field('author_name')->where(['is_del' => 0])->order('id desc')->select();
        $this->assign([
            'author_list' => $author_list
        ]);
        $this->display();
    }

    /**
     * 添加/编辑说说页面
     */
    public function timelineDisplay() {
        $id = I('get.id', 0, 'int');
        if ($id) {
            $info = M('timeline')->find($id);
            if ($info) {
                $this->assign([
                    'info' => $info
                ]);
            }
        }
        $author_list = M('author')->field('id,author_name')->where(['is_del' => 0])->order('id desc')->select();
        $this->assign([
            'author_list' => $author_list
        ]);
        $this->display();
    }

    /**
     * 获取商品图
     */
    public function getItemImages() {
        if (IS_AJAX) {
            $num_iid = I('get.num_iid', '', 'trim');
            $mall_platform = I('get.mall_platform', 'taobao', 'trim');
            if (!$num_iid) {
                $this->error('请输入商品编号！');
            }

            if ('pinduoduo' == $mall_platform) {
                $detail = $this->_pddGoodsDetail($num_iid);
                $images = $detail['small_images'];
            } else if ('jingdong' == $mall_platform) {
                $detail = $this->_getJdItemInfo($num_iid);
                $images = array_merge($detail['small_images'], $detail['desc_images']);
            } else {
                $info = M('items')->field('pic_url,small_images,desc')->where(array('num_iid' => $num_iid))->find();
                if (!$info) {
                    $this->error('商品信息不存在');
                }
                $main_image = array();
                if ($info['pic_url']) {
                    $main_image = array($info['pic_url']);
                }
                if ($info['small_images']) {
                    $small_image = json_decode($info['small_images'], true);
                } else {
                    $small_image = $this->_getItemsDesc($num_iid);
                }
                if ($info['desc']) {
                    $desc_image = json_decode($info['desc'], true);
                } else {
                    $desc_image = $this->_getItemSmallImage($num_iid);
                }
                $images = array_merge($main_image, $small_image, $desc_image);
            }

            $this->success(['data' => $images]);
        }
    }

    /**
     * 新增/编辑说说 保存数据
     */
    public function updateTimeline() {
        $id           = I('post.id', 0, 'int');
        $author_id    = I('post.author_id', '', 'int');
        $coupon_price = I('post.coupon_price', 0, 'floatval');
        $commission   = I('post.commission', 0, 'floatval');
        $mall_platform = I('post.mall_platform', '', 'trim');
        $num_iid      = I('post.num_iid', 0, 'trim');
        $tags         = I('post.tags', '', 'trim');
        $content      = I('post.content', '', 'trim');
        $pics         = I('post.pics', []);
        $status       = I('post.status', 0, 'int');
        $add_time     = I('post.add_time', 0, 'trim');

        if (!$author_id) {
            $this->error('请选择小编！');
        }
        if (0 == $coupon_price) {
            $this->error('请输入券后价！');
        }
        if (0 == $commission) {
            $this->error('请输入推广员佣金！');
        }
        if (!$num_iid) {
            $this->error('请填写商品编码！');
        }
        if (empty($pics)) {
            $this->error('未选择商品图！');
        }
        $author = M('author')->find($author_id);

        $data['author_name']   = $author['author_name'];
        $data['author_avatar'] = $author['author_avatar'];
        $data['coupon_price']  = $coupon_price;
        $data['commission']    = $commission;
        $data['tags']          = $tags;
        $data['content']       = $content;
        $data['mall_platform'] = $mall_platform;
        $data['num_iid']       = $num_iid;
        $data['pics']          = json_encode($pics);
        $data['status']        = $status;
        $data['add_time']      = $add_time ? strtotime($add_time) : time();

        if (!$id) {
            $data['create_time'] = time();
            $res                 = M('timeline')->add($data);
            $message             = "新增说说";
        } else {
            $data['id'] = $id;
            $model      = M('timeline')->find($id);

            $images = json_decode($model['pics'], true);
            $oss    = new OSS();
            foreach ($images as $image) {
                $path_info = parse_url($image);
                if (strpos(C('IMG_PREFIX'), $path_info['host']) === false) {
                    unset($image);
                    continue;
                }

                if (!in_array($image, $pics)) {
                    $oss->deleteObject(substr($path_info['path'], 1));
                }
            }
            $res     = M('timeline')->save($data);
            $message = "修改说说";
        }

        if ($res !== false) {
            $this->success($message . '成功');
        } else {
            $this->error($message . '失败！');
        }
    }

    /**
     * 删除说说
     */
    public function delTimeline() {
        $id = I('post.id', 0, 'int');
        if (!$id) {
            $this->error('记录编号异常！');
        }

        //删除图片
        $model  = M('timeline')->find($id);
        $images = json_decode($model['pics'], true);
        $oss    = new OSS();
        foreach ($images as $image) {
            $path_info = parse_url($image);
            if (strpos(C('IMG_PREFIX'), $path_info['host']) === false) {
                unset($image);
                continue;
            }
            $oss->deleteObject(substr($path_info['path'], 1));
        }

        if (M('timeline')->delete($id)) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }

    /**
     * 切换显示状态
     */
    public function switchStatus() {
        $id = I('post.id', 0, 'int');
        if (!$id) {
            $this->error('记录编号异常！');
        }

        $model          = M('timeline')->find($id);
        $data['id']     = $id;
        $data['status'] = $model['status'] == 1 ? 0 : 1;

        $message = $data['status'] == 1 ? '前台已显示' : '前台已隐藏';

        if (M('timeline')->save($data)) {
            $this->success($message);
        } else {
            $this->error('切换失败！');
        }
    }

    /**
     * 小编列表
     */
    public function authors() {
        if (IS_AJAX) {
            $page      = I('get.page', 1, 'int');
            $model     = M('author');
            $count     = $model->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = $model->where(['is_del' => 0])->order('id desc')->limit($start_num, $this->limit)->select();
            $this->success(['data' => $data, 'count' => $count]);
        }
        $this->display();
    }

    /**
     * 添加/更新小编信息
     */
    public function updateAuthor() {
        $id            = I('post.id', 0, 'int');
        $author_name   = I('post.author_name', '', 'trim');
        $author_avatar = I('post.author_avatar', '', 'trim');
        $is_delete     = I('post.is_del', 0, 'int');
        if (!$id) {
            if (!$author_name) {
                $this->error('小编名称不能为空！');
            }
            if (!$author_avatar) {
                $this->error('请上传小编头像！');
            }
            $data['author_name']   = $author_name;
            $data['author_avatar'] = $author_avatar;
            $data['add_time']      = time();

            $res = M('author')->add($data);
        } else {
            $data['id'] = $id;
            $model      = M('author')->find($id);
            if (!$model) {
                $this->error('当前记录不存在！');
            }
            if ($is_delete) {
                $data['is_del'] = 1;
            } else {
                if (!$author_name) {
                    $this->error('小编名称不能为空！');
                }
                if (!$author_avatar) {
                    $this->error('请上传小编头像！');
                }
                if ($model['author_avatar'] != $author_avatar) {
                    $path_info = parse_url($model['author_avatar']);
                    $oss       = new OSS();
                    $oss->deleteObject(substr($path_info['path'], 1));
                    //重新赋值
                    $data['author_avatar'] = $author_avatar;
                }
                $data['author_name'] = $author_name;
            }
            $res = M('author')->save($data);
        }

        if ($res !== false) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败！');
        }
    }


}