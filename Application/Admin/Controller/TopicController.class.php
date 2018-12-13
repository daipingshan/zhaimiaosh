<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 2018/4/18
 * Time: 16:17
 */

namespace Admin\Controller;

use Common\Org\AliYunOss as OSS;

class TopicController extends CommonController {

    protected $type = [
        1 => '普通专题',
        2 => '品牌专场-通用',
        3 => '品牌专场-专用',
        4 => '时尚范儿-潮男',
        5 => '时尚范儿-靓女',
        6 => '9块9包邮',
        7 => '小编精选'
    ];
    protected $sort = [
        1 => '最新',
        2 => '销量倒序',
        3 => '券金额倒序',
        4 => '券后价正序',
        5 => '券后价倒序',
        6 => '佣金率倒序'
    ];

    /**
     * 专题列表
     */
    public function index() {
        if (IS_AJAX) {
            $page = I('get.page', 1, 'int');
            $type = I('get.type', 1, 'trim');

            $where['type'] = $type;

            $model     = M('item_topic');
            $count     = $model->where($where)->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = $model->where($where)->order('sort asc')->limit($start_num, $this->limit)->select();
            foreach ($data as &$val) {
                $items = json_decode($val['items'], true);

                $val['add_time']  = date('Y-m-d H:i:s', $val['add_time']);
                $val['type']      = $this->type[$val['type']];
                $val['total_num'] = count($items);

                $num_iid_data = array();
                foreach ($items as $item) {
                    $num_iid_data[] = $item['num_iid'];
                }

                if (empty($num_iid_data)) {
                    $item_count = 0;
                } else {
                    $where      = array('num_iid' => array('in', $num_iid_data));
                    $item_count = M('items')->where($where)->count('id');
                }

                $val['count'] = $item_count;
            }
            $this->success(['data' => $data, 'count' => $count]);
        }
        $this->assign('type', $this->type);
        $this->display();
    }

    /**
     * 显示 新增/编辑专题页面
     */
    public function topicDisplay() {
        $id = I('get.id', 0, 'int');
        if ($id) {
            $info         = M('item_topic')->find($id);
            $items        = json_decode($info['items'], true);
            $num_iid_data = array();
            $temp_item    = array();
            $temp_data    = array();
            foreach ($items as $item) {
                $num_iid_data[]              = $item['num_iid'];
                $temp_item[$item['num_iid']] = $item;
            }

            if (empty($num_iid_data)) {
                $temp_data = array();
            } else {
                $where = array('num_iid' => array('in', $num_iid_data));
                $data  = $this->_getItemsList($where, '', 0, count($num_iid_data));
                foreach ($data as $val) {
                    $temp_data[] = $temp_item[$val['num_iid']];
                }
                $temp_data     = array_sequence($temp_data, 'sort', 'SORT_ASC');
            }

            $info['items'] = json_encode($temp_data);
            if ($info) {
                $this->assign([
                    'info' => $info
                ]);
            }
        }
        $cate_array = $this->_getCate();
        $this->assign('cate', $cate_array);
        $this->assign('type', $this->type);
        $this->assign('sort', $this->sort);
        $this->display();
    }

    /**
     * 获取商品
     */
    public function getItems() {
        if (IS_AJAX) {
            $title   = I('get.title', 0, 'trim');
            $cate_id = I('get.cate_id', 0, 'int');
            $sort_id = I('get.sort_id', 0, 'int');
            $num_iid = I('get.num_iid', 0, 'trim');
            $page    = I('get.page', 1, 'int');
            $page--;
            $where = array();
            $query = '';
            if ($num_iid) {
                $where = array('num_iid' => $num_iid);
                $query = "num_iid:'{$num_iid}'";
            } else {
                if ($title) {
                    $where['title'] = array('like', '%' . $title . '%');
                    $query          = "keyword:'{$title}'";
                }
                if ($cate_id > 0) {
                    $where[$cate_id] = $cate_id;
                    if ($query != '') {
                        $query .= " AND cate_id:'{$cate_id}'";
                    } else {
                        $query = "cate_id:'{$cate_id}'";
                    }
                }
            }
            $sort  = array();
            $order = "";
            switch ($sort_id) {
                case 1:
                    $sort  = array(array('key' => 'id', 'val' => 0));
                    $order = "id desc";
                    break;
                case 2:
                    $sort  = array(array('key' => 'volume', 'val' => 0));
                    $order = "volume desc";
                    break;
                case 3:
                    $sort  = array(array('key' => 'quan', 'val' => 0));
                    $order = "quan desc";
                    break;
                case 4:
                    $sort  = array(array('key' => 'coupon_price', 'val' => 1));
                    $order = "coupon_price ase";
                    break;
                case 5:
                    $sort  = array(array('key' => 'coupon_price', 'val' => 0));
                    $order = "coupon_price desc";
                    break;
                case 6:
                    $sort  = array(array('key' => 'commission_rate', 'val' => 0));
                    $order = "commission_rate desc";
                    break;
                default:
                    //.....
                    break;
            }
            $this->limit = 12;
            $start_num   = $page * $this->limit;
            if ($this->openSearchStatus === true) {
                $items = $this->_getOpenSearchList($query, $sort, null, $start_num, $this->limit);
                $count = $this->_getOpenSearchCount($query);
                if (empty($items)) {
                    $count = M('items')->where($where)->count('id');
                    $items = $this->_getItemsList($where, $order, $start_num, $this->limit);
                }
            } else {
                $count = M('items')->where($where)->count('id');
                $items = $this->_getItemsList($where, $order, $start_num, $this->limit);
            }
            $this->success(['items' => $items, 'count' => $count]);
        }
    }

    /**
     * 保存专题数据
     */
    public function updateTopic() {
        $id               = I('post.id', 0, 'int');
        $type             = I('post.type', '', 'int');
        $topic_name       = I('post.topic_name', '', 'trim');
        $topic_item_pic   = I('post.topic_item_pic', '', 'trim');
        $topic_detail_pic = I('post.topic_detail_pic', '', 'trim');
        $topic_desc       = I('post.topic_desc', '', 'trim');
        $items            = I('post.items', []);
        $tbk_material_id  = I('post.tbk_material_id', 0, 'int');
        $sort             = I('post.sort', 0, 'int');

        if (!$topic_name) {
            $this->error('请填写专题标题！');
        }
        if ($type == 1) {
            if (!$topic_item_pic) {
                $this->error('请上传专题入口图！');
            }
        }
        if (empty($items) && 0 == $tbk_material_id) {
            $this->error('请选择商品或设置淘宝物料ID！');
        }
        $items                    = array_sequence($items, 'sort', 'SORT_ASC');
        $data['type']             = $type;
        $data['topic_name']       = $topic_name;
        $data['topic_item_pic']   = $topic_item_pic;
        $data['topic_detail_pic'] = $topic_detail_pic;
        $data['topic_desc']       = $topic_desc;
        $data['items']            = json_encode($items);
        $data['tbk_material_id']  = $tbk_material_id;
        $data['sort']             = $sort;

        if (!$id) {
            $data['add_time'] = time();
            $id               = M('item_topic')->add($data);
            $message          = "新增专题";
        } else {
            $data['id'] = $id;
            $model      = M('item_topic')->find($id);
            //删除入口图片
            if ($model['topic_item_pic'] != $topic_item_pic) {
                $path_info = parse_url($model['topic_item_pic']);
                $oss       = new OSS();
                $oss->deleteObject(substr($path_info['path'], 1));
            }

            //删除专题详情图片
            if ($model['topic_detail_pic'] != $topic_detail_pic) {
                $path_info = parse_url($model['topic_detail_pic']);
                $oss       = new OSS();
                $oss->deleteObject(substr($path_info['path'], 1));
            }

            $res     = M('item_topic')->save($data);
            $message = "修改专题";
            S('TOPIC_' . $id, null);
        }

        /**
         * 如果有2个同样的排序，那么就把其他的排序值加1
         */
        $sort_num = M('item_topic')->where(array('type' => $type, 'sort' => $sort))->count();
        if ($sort_num > 1) {
            M('item_topic')->where(array('type' => $type, 'id' => array('neq', $id), 'sort' => array('egt', $sort)))->setInc('sort', 1);
        }

        if ($res !== false) {
            if ($type == 1) {
                S('item_topic', null);
            }

            $this->success($message . '成功');
        } else {
            $this->error($message . '失败！');
        }
    }

    /**
     * 删除专题
     */
    public function deleteTopic() {
        $id = I('post.id', 0, 'int');
        if (!$id) {
            $this->error('记录编号异常！');
        }
        $model = M('item_topic')->find($id);
        //删除入口图片
        if ($model['topic_item_pic']) {
            $path_info = parse_url($model['topic_item_pic']);
            $oss       = new OSS();
            $oss->deleteObject(substr($path_info['path'], 1));
        }

        //删除专题详情图片
        if ($model['topic_detail_pic']) {
            $path_info = parse_url($model['topic_detail_pic']);
            $oss       = new OSS();
            $oss->deleteObject(substr($path_info['path'], 1));
        }
        if (M('item_topic')->delete($id)) {
            if ($model['type'] == 1) {
                S('item_topic', null);
            }
            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }
}