<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/23
 * Time: 12:58
 */

namespace Admin\Controller;

/**
 * 商品分类
 * Class ItemController
 *
 * @package Admin\Controller
 */
class ItemController extends CommonController {

    protected $pddCate    = array(
        0    => '全部商品',
        1    => '美食',
        4    => '母婴',
        13   => '水果',
        14   => '服饰',
        15   => '百货',
        16   => '美妆',
        18   => '电器',
        743  => '男装',
        818  => '家纺',
        1281 => '鞋包',
        1451 => '运动',
        1543 => '手机',
    );
    protected $jd_cate    = array(
        0  => '全部商品',
        1  => '女装',
        2  => '男装',
        3  => '内衣配饰',
        4  => '母婴玩具',
        5  => '美妆个护',
        6  => '食品保健',
        7  => '居家生活',
        8  => '鞋品箱包',
        9  => '运动户外',
        10 => '文体车品',
        11 => '数码家电',
    );
    protected $range_type = array(
        9 => '全部',
        0 => '商品拼团价格区间',
        1 => '商品券后价价格区间',
        2 => '佣金比例区间',
        3 => '优惠券金额区间',
        5 => '销量区间',
        //        6 => '佣金金额区间'
    );

    /**
     * 商品列表
     */
    public function index() {
        $cate_arr = $this->_getCate();
        if (IS_AJAX) {
            $title   = I('get.title', '', 'trim,urldecode');
            $num_iid = I('get.num_iid', '', 'trim');
            $cate_id = I('get.cate_id', 0, 'int');
            $status  = I('get.status', 0, 'int');
            $page    = I('get.page', 1, 'int');
            $sort    = "ordid asc,id desc";
            $where   = array();
            if ($title) {
                $where['title|intro'] = array('like', "%{$title}%");
            }
            if ($num_iid) {
                $where['num_iid'] = $num_iid;
            }
            if ($cate_id) {
                $where['cate_id'] = $cate_id;
            }
            if ($status == 1) {
                $where['handpick_time'] = array('gt', 0);
                $sort                   = 'handpick_time desc,id desc';
            }
            $start_num = ($page - 1) * $this->limit;
            $count     = M('items')->where($where)->count('id');
            $data      = M('items')->where($where)->order($sort)->limit($start_num, $this->limit)->select();
            foreach ($data as &$item) {
                $item['commission']      = computed_price($item['commission'], 2, C('COMMISSION_RATE.base_commission_rate'));
                $item['cate_name']       = isset($cate_arr[$item['cate_id']]['name']) ? $cate_arr[$item['cate_id']]['name'] : '未知';
                $item['coupon_money']    = $item['quan'];
                $item['sale_num']        = $item['volume'];
                $item['shop_type_name']  = $item['shop_type'] == "C" ? '淘宝' : '天猫';
                $item['set_btn_name']    = $item['handpick_time'] > 0 ? date("Y-m-d H:i", $item['handpick_time']) . "-更新" : "设置精选";
                $item['is_set_handpick'] = $item['handpick_time'] > 0 ? 1 : 0;
            }
            $this->success(array('data' => $data, 'count' => $count));
        }
        $this->assign(array('cate_arr' => $cate_arr));
        $this->display();
    }

    /**
     * 拼多多商品列表
     */
    public function pddItemList() {
        if (IS_AJAX) {
            $keyword = I('get.keyword', '', 'trim');
            $num_iid = I('get.num_iid', '', 'trim');
            $page    = I('get.page', 1, 'int');
            $cate_id = I('get.cate_id', 0, 'int');

            $range_id   = I('get.range_id', 9, 'trim');
            $range_from = I('get.range_from', '', 'trim');
            $range_to   = I('get.range_to', '', 'trim');

            $params = array('keyword' => $keyword, 'page' => $page);
            if ($num_iid) {
                $id_list                 = '[' . $num_iid . ']';
                $params['goods_id_list'] = $id_list;
            }
            if ($cate_id > 0) {
                $params['category_id'] = $cate_id;
            }
            if ($range_id != 9) {
                if ($range_from || $range_to) {
                    $range_arr['range_id']   = $range_id;
                    $range_arr['range_from'] = $range_from;
                    $range_arr['range_to']   = $range_to;

                    $params['range_list'] = json_encode(array($range_arr));
                }
            }
            $data = $this->_pddGoodsSearch($params, true);
            foreach ($data['item_list'] as $key => $item) {
                $data['item_list'][$key]['cate_name'] = isset($this->pddCate[$item['cate_id']]) ? $this->pddCate[$item['cate_id']] : '未知';
            }
            $this->success(array('data' => $data['item_list'], 'count' => $data['total_count']));
        }

        $this->assign(array('cate_arr' => $this->pddCate));
        $this->assign(array('range_type' => $this->range_type));
        $this->display();
    }

    /**
     * 京东商品列表
     */
    public function jdItemList() {
        if (IS_AJAX) {
            $keyword = I('get.keyword', '', 'trim');
            $page    = I('get.page', 1, 'int');
            $cate_id = I('get.cate_id', 0, 'int');

            $params = array('so' => $keyword, 'page' => $page);
            if ($cate_id > 0) {
                $params['type'] = $cate_id;
            }

            $data = $this->_jttGoodsSearch($params, true);
            foreach ($data['item_list'] as $key => $item) {
                $data['item_list'][$key]['cate_name'] = isset($this->jd_cate[$item['cate_id']]) ? $this->jd_cate[$item['cate_id']] : '未知';
            }
            $this->success(array('data' => $data['item_list'], 'count' => $data['total_page']));
        }

        $this->assign(array('cate_arr' => $this->jd_cate));
        $this->display();
    }

    /**
     * 更新商品信息
     */
    public function updateItem() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id       = I('post.num_iid', '', 'trim');
        $field    = I('post.field', 'title', 'trim');
        $title    = I('post.title', '', 'trim');
        $del_time = I('post.del_time', 0, 'int');
        $sort     = I('post.sort', 9999, 'int');
        if (!$field || !$id) {
            $this->error('请求参数不完整！');
        }
        if ($field == 'title') {
            if (!$title) {
                $this->error('商品标题不能为空！');
            }
            if ($del_time == 1) {
                $handpick_time = 0;
            } else {
                $handpick_time = time();
            }
            $save_data = array('title' => $title, 'handpick_time' => $handpick_time);
        } else {
            $save_data = array('ordid' => $sort);
        }
        $res = M('items')->where(array('num_iid' => $id))->save($save_data);
        if ($res !== false) {
            $this->success('操作成功');
        } else {
            $this->error('操作失败！');
        }
    }

    /**
     * 删除商品
     */
    public function delItem() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $num_iid = I('post.num_iid', '', 'trim');
        $count   = M('items')->where(array('num_iid' => $num_iid))->count();
        if (empty($num_iid) || empty($count)) {
            $this->error('商品信息不存在无法删除！');
        }
        $res = M('items')->where(array('num_iid' => $num_iid))->delete();
        if ($res) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }

    /**
     * 商品分类
     */
    public function cate() {
        if (IS_AJAX) {
            $page      = I('get.page', 1, 'int');
            $model     = M('items_cate');
            $count     = $model->count('id');
            $start_num = ($page - 1) * $this->limit;
            $data      = $model->order('sort asc,id desc')->limit($start_num, $this->limit)->select();
            $this->success(array('data' => $data, 'count' => $count));
        }
        $this->display();
    }

    /**
     * 更新商品分类信息
     */
    public function updateCate() {
        $id     = I('post.id', 0, 'int');
        $name   = I('post.name', '', 'trim');
        $image  = I('post.icon_url', '', 'trim');
        $sort   = I('post.sort', 255, 'int');
        $status = I('post.status', 1, 'int');
        if (!$id) {
            $this->error('请求参数不完整！');
        }
        if (!$name) {
            $this->error('请输入商品分类！');
        }
        if (!$image) {
            $this->error('请上传商品分类图片！');
        }
        $data = array('id' => $id, 'name' => $name, 'icon_url' => $image, 'sort' => $sort, 'status' => $status);
        $res  = M('items_cate')->save($data);
        if ($res !== false) {
            S('items_cate', null);
            $this->success('修改成功');
        } else {
            $this->error('修改失败！');
        }
    }

    /**
     * 更新商品状态
     */
    public function setStatus() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('post.id', 0, 'int');
        $info = M('items_cate')->find($id);
        if (empty($id) || empty($info)) {
            $this->error('账号不存在！');
        }
        $status = $info['status'] == 0 ? 1 : 0;
        $msg    = $status == 1 ? '启用' : '禁用';
        $res    = M('items_cate')->save(array('status' => $status, 'id' => $id));
        if ($res) {
            S('items_cate', null);
            $this->success($msg . '成功');
        } else {
            $this->error($msg . '失败！');
        }
    }

    /**
     * 推送商品
     */
    public function sendItem() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $push_title = I('post.push_title', '', 'trim');
        $title      = I('post.title', '', 'trim');
        $num_iid    = I('post.num_iid', '', 'trim');
        $send_type  = I('post.send_type', 0, 'int');
        $type       = I('post.type', 'taobao', 'trim');
        if (empty($title) || empty($num_iid) || empty($send_type) || empty($push_title)) {
            $this->error('请求参数不完整！');
        }
        $push = array('type' => $send_type, 'title' => $push_title);
        if ($send_type == 1) {
            $mobile = I('post.mobile', '', 'trim');
            if (empty($mobile)) {
                $this->error('推送用户手机号码不能为空！');
            }
            $user_id = M('user')->where(array('mobile' => $mobile))->getField('id');
            if (empty($user_id)) {
                $this->error('该手机号码尚未注册无法推送！');
            }
            $push['alias'] = array($user_id);
        } else if ($send_type == 2) {
            $level = I('post.level', '', 'trim');
            if ($level === '') {
                $this->error('请选择推送用户身份！');
            }
            $push['tag'] = array($level);
        }
        $data = array('jump_type' => '4', 'content' => $num_iid, 'title' => $title, 'item_mall_platform' => $type);
        $res  = $this->_sendPush($title, $data, $push);
        if ($res['status'] == 1) {
            $this->success('推送成功');
        } else {
            $this->error($res['info']);
        }
    }

    /**
     * 采集商品
     */
    public function searchItem() {
        $id = I('get.id', '', 'trim');
        if ($id) {
            $tao_obj = new \Common\Org\DaTaoKe();
            $res     = $tao_obj->getItem($id);
            $success = $error = $info = '';
            if ($res['result']) {
                $sort = rand(1, 10);
                $info = $res['result'];
                $item = M('items')->where(array('num_iid' => $info['GoodsID']))->find();
                if ($item) {
                    if ($item['ordid'] != 9999) {
                        $error = '您已经提交过此产品，产品排序为' . $item['ordid'];
                    } else {
                        $save_data = array('id' => $item['id'], 'ordid' => $sort);
                        $add_res   = M('items')->save($save_data);
                        if ($add_res) {
                            M('items')->where(array('ordid' => array('lt', 1000), 'id' => array('neq', $item['id']), 'coupon_type' => array('neq', 5)))->setInc('ordid', 3);
                            $success = "更新成功，排序结果为第{$sort}位";
                        } else {
                            $error = '更新失败！';
                        }
                    }
                } else {
                    $sort          = rand(1, 10);
                    $info['ordid'] = $sort;
                    $data          = $tao_obj->getItemData($info, true);
                    $add_res       = M('items')->add($data);
                    if ($add_res) {
                        M('items')->where(array('ordid' => array('lt', 1000), 'id' => array('neq', $add_res), 'coupon_type' => array('neq', 5)))->setInc('ordid', 3);
                        $success = "采集成功，排序结果为第{$sort}位";
                    } else {
                        $error = '采集失败！';
                    }
                }
            } else {
                $error = '未在大淘客中寻找到此款产品,或访问受限';
            }
            $this->assign(array('error' => $error, 'success' => $success, 'info' => $info));
        }
        $this->display();
    }

    /**
     * 手工采集
     */
    public function manualCollection() {
        if (IS_AJAX) {
            $num_iid         = I('post.num_iid', '', 'trim');
            $click_url       = I('post.click_url', '', 'trim');
            $coupon_end_time = I('post.coupon_end_time', '', 'trim');
            $price           = I('post.price', '', 'float');
            $coupon_price    = I('post.coupon_price', '', 'float');
            $commission_rate = I('post.commission_rate', '', 'trim');
            $cate_id         = I('post.cate_id', 0, 'int');
            $title           = I('post.title', '', 'trim');
            $intro           = I('post.intro', '', 'trim');
            $is_set          = I('post.is_set', 0, 'int');
            $is_send         = I('post.is_send', 0, 'int');
            $send_title      = I('post.send_title', '', 'trim');
            if (!$num_iid) {
                $this->error('商品ID不存在');
            }
            $count = M('items')->where(array('num_iid' => $num_iid))->count();
            if ($count) {
                $this->error('该商品已在商品库，不能重复采集！');
            }
            $obj      = new \Common\Org\TaoBaoApi();
            $item_res = $obj->getTaoBaoItemInfo($num_iid);
            if ($item_res['code'] == -1) {
                $this->error('商品信息不存在');
            }
            $info = $item_res['data'];
            if (!$click_url) {
                $this->error('商品优惠券链接不能为空');
            }
            $activity_reg = "/(activityId|activity_id|activityid)=([a-z|\d]{20,})/";
            preg_match($activity_reg, $click_url, $res);
            $activity_id = '';
            if (isset($res[2]) && $res[2]) {
                $activity_id = $res[2];
            }
            if (!$activity_id) {
                $this->error('优惠券活动ID不存在');
            }
            if (strtotime($coupon_end_time) + 86399 <= time()) {
                $this->error('优惠券结束时间不能小于当前时间');
            }
            if (!$price) {
                $this->error('商品正价不能不空');
            }
            if (!$coupon_price) {
                $this->error('券后价不能为空');
            }
            if ($coupon_price > $price) {
                $this->error('券后价不能大于商品正价');
            }
            if (!$commission_rate) {
                $this->error('佣金比率不能为空');
            }
            if ($commission_rate >= 100) {
                $this->error('佣金比率不能超过100');
            }
            if (!$cate_id) {
                $this->error('商品分类必须选择');
            }
            if (!$title) {
                $this->error('商品标题不能为空');
            }
            if (!$intro) {
                $this->error('推广文案不能为空');
            }
            if ($is_send == 1 && !$send_title) {
                $this->error('推送内容不能为空');
            }
            $handpick_time = 0;
            if ($is_set == 'on') {
                $handpick_time = time();
            }
            if ($coupon_price <= 9.9) {
                $coupon_type = 4;
            } else {
                $coupon_type = 1;
            }
            $click_url      = 'https://uland.taobao.com/coupon/edetail?activityId=' . $activity_id . '&pid=' . $this->pid . '&itemId=' . $num_iid . '&src=cd_cdll';
            $quan           = $price - $coupon_price;
            $commission     = $coupon_price * ($commission_rate / 100);
            $data           = array(
                'activity_id'       => $activity_id,
                'snum'              => 5000, //剩余优惠券
                'lnum'              => 0, //已领取优惠卷
                'quan'              => $quan, //优惠券金额
                'endtime'           => $coupon_end_time,//结束时间
                'price'             => $price, //正常售价
                'intro'             => $intro, //文案
                'volume'            => $info['volume'], //销量
                'commission_rate'   => $commission_rate * 100, //佣金比例
                'commission'        => $commission, //佣金
                'title'             => $title, //标题
                'click_url'         => $click_url, //领券链接，内含pid
                'num_iid'           => $num_iid, //淘宝商品ID
                'dataoke_id'        => 0,  //大淘客商品ID
                'pic_url'           => $info['pict_url'],
                'coupon_price'      => $coupon_price, //使用优惠券后价格
                'shop_type'         => 'B',
                'coupon_type'       => $coupon_type,
                'uname'             => 'tongyong',
                'pass'              => 1,
                'coupon_end_time'   => strtotime($coupon_end_time) + 86399,
                'cate_id'           => $cate_id, //分类
                'coupon_start_time' => time(),
                'ordid'             => 9999, //商品排序
                'handpick_time'     => $handpick_time,
            );
            $item_insert_id = M('items')->add($data);
            if ($item_insert_id) {
                if ($is_send && $send_title) {
                    $push = array('type' => 3, 'title' => $send_title);
                    $data = array('jump_type' => '4', 'content' => $num_iid, 'title' => $send_title);
                    $this->_sendPush($title, $data, $push);
                }
                $this->success('添加成功');
            }
        } else {
            $content = I('get.content', '', 'trim,urldecode');
            if ($content) {
                $success = $error = '';
                $info    = array();
                $id      = 0;
                if (preg_match('/^\d{10,20}/', $content)) {
                    $id = $content;
                } else {
                    if (stripos($content, 'http') == 0) {
                        $exp_reg = "/(\?id|\&id)\=(\d{10,20})/";
                        preg_match($exp_reg, $content, $reg);
                        if (isset($reg[2]) && $reg[2]) {
                            $id = $reg[2];
                        }
                    } else {
                        $result = $this->_checkContent($content);
                        if ($result['status'] == 0) {
                            $error = $result['info'];
                        } else {
                            $id = $result['data']['id'];
                            $this->assign('item_content', $result['data']);
                        }
                    }
                }
                if (!$error) {
                    if ($id) {
                        $count = M('items')->where(array('num_iid' => $id))->count();
                        if ($count) {
                            $error = '该商品已在商品库，不能重复采集！';
                        } else {
                            $obj      = new \Common\Org\TaoBaoApi();
                            $item_res = $obj->getTaoBaoItemInfo($id);
                            if ($item_res['status'] == 0) {
                                $error = $item_res['info'];
                            }
                            $info = $item_res['data'];
                        }
                    } else {
                        $error = '商品ID不存在';
                    }
                }
                $this->assign(array('error' => $error, 'success' => $success, 'info' => $info));
            }
            $cate = array(
                20 => '女装',
                21 => '母婴',
                22 => '美妆',
                23 => '家具',
                24 => '鞋包',
                25 => '美食',
                26 => '文体车品',
                27 => '数码家电',
                29 => '男装',
                30 => '内衣',
            );
            $this->assign('cate', $cate);
            $this->display();
        }
    }

    /**
     * 检测用户输入的内容
     */
    protected function _checkContent($content) {
        $return_data = array('status' => 0, 'info' => 'error', 'data' => '');
        $exp_reg     = "/(.+)[\n|\s]+(.+)[\n|\s]+(.+)[\n|\s]+(.+)[\n|\s]+(.*)/";
        preg_match($exp_reg, $content, $result);
        if (count($result) < 5) {
            $return_data['info'] = '推广信息不合法';
            return $return_data;
        }
        $title         = $result[1];
        $intro         = $result[5];
        $price_exp_reg = "/([0-9]+\.*[0-9]*)/";
        preg_match_all($price_exp_reg, $result[2], $price_res);
        $price        = $price_res[0][0];
        $coupon_price = $price_res[0][1];
        $http_exp_reg = "/((http|https):[\/]{2}[a-zA-Z\d\.#\?\/\=\&\_]*)/";
        preg_match($http_exp_reg, $result[3], $http_res);
        if (!isset($http_res[0]) || !$http_res[0]) {
            $return_data['info'] = '领券链接不合法';
            return $return_data;
        }
        $click_url        = $http_res[0];
        $activity_exp_reg = "/(activityId|activity_id|activityid)=([a-z|\d]{20,})/";
        preg_match($activity_exp_reg, $click_url, $activity);
        if (!isset($activity[1]) || count($activity[1]) != 1) {
            $return_data['info'] = '领券链接缺少活动ID';
            return $return_data;
        }
        preg_match($http_exp_reg, $result[4], $http_item_res);
        if (!isset($http_item_res[0]) || !$http_item_res[0] || strpos($http_item_res[0], 's.click.taobao.com')) {
            $return_data['info'] = '商品链接不合法';
            return $return_data;
        }
        $item_url     = $http_item_res[0];
        $item_exp_reg = "/id=(\d{10,20})/";
        preg_match($item_exp_reg, $item_url, $item);
        if (!isset($item[1]) || !$item[1]) {
            $return_data['info'] = '商品链接中不存在商品编号，无法转链！';
            return $return_data;
        }
        $return_data = array(
            'status' => 1,
            'info'   => 'ok',
            'data'   => array(
                'id'           => $item[1],
                'click_url'    => $click_url,
                'price'        => $price,
                'coupon_price' => $coupon_price,
                'title'        => $title,
                'intro'        => $intro
            ));
        return $return_data;
    }

    /**
     * 京东免单商品列表
     */
    public function jdItem() {
        if (IS_AJAX) {
            $keyword = I('get.keyword', '', 'trim,urldecode');
            $page    = I('get.page', 1, 'int');
            $where   = array();
            if ($keyword) {
                $where['title|num_iid'] = array('like', "%{$keyword}%");
            }
            $start_num = ($page - 1) * $this->limit;
            $count     = M('jd_items')->where($where)->count('id');
            $data      = M('jd_items')->where($where)->order('id desc')->limit($start_num, $this->limit)->select();
            foreach ($data as &$val) {
                if ($val['type'] == 'free') {
                    $val['cate_name'] = '<span style="color:#2cbd2e ">免单商品</span>';
                } else {
                    $val['cate_name'] = '<span style="color:#1E9FFF ">新客单</span>';
                }
                if ($val['is_online'] == 1) {
                    $val['color']       = "layui-btn-danger";
                    $val['online_name'] = '立即下线';
                } else {
                    $val['color']       = "layui-btn-warm";
                    $val['online_name'] = '立即上线';
                }
            }
            $this->success(array('data' => $data, 'count' => $count));
        }
        $this->display();
    }

    /**
     * 新增京东免单商品
     */
    public function addJdItem() {
        $this->display('saveJdItem');
    }

    /**
     * 修改京东免单商品
     */
    public function updateJdItem() {
        $id   = I('get.id', 0, 'int');
        $info = M('jd_items')->find($id);
        if ($info['coupon_end_time'] > 0) {
            $info['coupon_end_time'] = date('Y-m-d H:i:s', $info['coupon_end_time']);
        }
        $this->assign('info', $info);
        $this->display('saveJdItem');
    }

    /**
     * 保存京东免单商品
     */
    public function saveJdItem() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id              = I('post.id', 0, 'int');
        $num_iid         = I('post.num_iid', '', 'trim');
        $title           = I('post.title', '', 'trim');
        $pic_url         = I('post.pic_url', '', 'trim');
        $click_url       = I('post.click_url', '', 'trim');
        $price           = I('post.price', '', 'float');
        $coupon_price    = I('post.coupon_price', '', 'float');
        $coupon_money    = I('post.coupon_money', '', 'float');
        $coupon_end_time = I('post.coupon_end_time', '', 'trim');
        $subsidy_money   = I('post.subsidy_money', '', 'float');
        $type            = I('post.type', 'free', 'trim');
        $is_online       = I('post.is_online', 1, 'int');
        if (!$num_iid) {
            $this->error('商品ID不能为空');
        }
        if ($id == 0) {
            $count = M('jd_items')->where(array('num_iid' => $num_iid))->count();
            if ($count) {
                $this->error('该商品已在商品库，不能重复加入！');
            }
        }

        if (!$title) {
            $this->error('商品标题不能不空');
        }
        if (!$pic_url) {
            $this->error('商品图片地址不能不空');
        }
        if (!$price) {
            $this->error('商品正价不能不空');
        }
        if (!$coupon_price) {
            $this->error('券后价不能为空');
        }
        if (!$subsidy_money) {
            $this->error('补贴金额不能为空');
        }
        $data = array(
            'num_iid'         => $num_iid,
            'title'           => $title,
            'pic_url'         => $pic_url,
            'click_url'       => $click_url,
            'price'           => $price,
            'coupon_price'    => $coupon_price,
            'coupon_money'    => $coupon_money,
            'coupon_end_time' => $coupon_end_time ? strtotime($coupon_end_time) : 0,
            'subsidy_money'   => $subsidy_money,
            'type'            => $type,
            'is_online'       => $is_online,
        );
        if ($id > 0) {
            $res = M('jd_items')->where(array('id' => $id))->save($data);
        } else {
            $data['add_time'] = time();
            $res              = M('jd_items')->add($data);
        }
        if ($res !== false) {
            $this->success('保存成功');
        } else {
            $this->success('保存失败');
        }
    }

    /**
     * 设置京东商品状态
     */
    public function setJdItemStatus() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $id   = I('get.id', 0, 'int');
        $info = M('jd_items')->find($id);
        if (!$id || !$info) {
            $this->error('请求参数不合法！');
        }
        $is_online = $info['is_online'] == 0 ? 1 : 0;
        $res       = M('jd_items')->where(array('id' => $id))->save(array('is_online' => $is_online));
        if ($res !== false) {
            $this->success('操作成功');
        } else {
            $this->success('操作失败');
        }
    }
}