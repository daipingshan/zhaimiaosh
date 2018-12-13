<?php

namespace Touch\Controller;

use Common\Controller\CommonBaseController;
use Common\Org\Http;

class IndexController extends CommonBaseController {

    //  每页数据
    protected $page_size = 20;
    // 9.9包邮分类
    private $nine_nine = 28;
    //  楚楚街商品特殊标识分类ID 1029
    private $ccj_cate_id = 1029;
    //  热销
    private $hot = 1000;

    /**
     * 获取用户的基本信息
     */
    public function callBackUrl() {
        if (isset($_GET['code']) && trim($_GET['code'])) {
            $code       = trim($_GET['code']);
            $state      = trim($_GET['state']);
            $app_id     = C('WEIXIN_BASE.app_id');
            $app_secret = C('WEIXIN_BASE.app_secret');
            $token_url  = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $app_id . '&secret=' . $app_secret . '&code=' . $code . '&grant_type=authorization_code';
            $http       = new Http();
            $res        = json_decode($http->get($token_url), true);
            if (isset($res['openid']) && $res['openid']) {
                $openid = $res['openid'];
                //  登陆用户的基本信息
                $user = M('wxuser')->where(array('openid' => $openid))->find();
                if ($user) {
                    //  代理id
                    $proxy_id = M('user')->where(array('pid' => $user['proxy_pid']))->getField('id');
                    session('proxy_id', $proxy_id);
                } else {
                    session('proxy_id', 1);
                }
                session('wx_user_openid', $openid);
                if ($state == 1) {
                    //  个人商城首页地址
                    $this->redirect('Index/index', array('user_id' => session('proxy_id')));
                } elseif ($state == 2) {
                    //  直播间首页地址
                    $this->redirect('Index/zhiBoJian');
                } elseif ($state == 3) {
                    $this->redirect('Coupon/index');
                }
            }
        }
    }

    /**
     * 直播间首页地址
     */
    public function zhiBoJian() {
        $user_id = intval(session('proxy_id'));
        if ($user_id == 0) {
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                $this->_WeChatLogin(2);
            } else {
                session('proxy_id', 1);
            }
        }
        $this->assign('proxy_id', session('proxy_id'));
        $this->display();
    }

    /**
     * 获取用户信息
     *
     * @param $user_id
     * @return array
     */
    public function getUser() {
        $wxuser = M('wxuser')->where(array('openid' => session('wx_user_openid')))->field('id,nickname,headimgurl')->find();
        $this->ajaxReturn(array('code' => 0, 'user' => $wxuser, 'msg' => '用户数据获取成功'));
    }

    /**
     * 获取热销的产品
     *
     * @param $type
     * @return array
     */
    public function getHotGoods() {
        $proxy_id = session('proxy_id');
        if (!$proxy_id) {
            $proxy_id = 0;
        }
        $zbj_data = S('zbj_data');
        if (!$zbj_data) {
            $query                = " coupon_type:'5' ";
            $where['coupon_type'] = '5';
            $sort                 = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
            if ($this->openSearchStatus == true) {
                $items_list = $this->_getOpenSearchList($query, $sort, null, 0, 50);
            } else {
                $order      = 'ordid asc, id desc';
                $items_list = M('items')->order($order)->limit(0, 50)->select();
            }
            $zbj_data = S('zbj_data', $items_list, 86400);
        }
        foreach ($zbj_data as &$val) {
            $val['url'] = U('Item/index', array('id' => $val['id'], 'user_id' => $proxy_id, 'type' => 'tdk'));
        }
        if (!empty($zbj_data)) {
            $this->ajaxReturn(array('code' => 0, 'data' => $zbj_data));
        } else {
            $this->ajaxReturn(array('code' => -1, 'data' => ''));
        }
    }

    /**
     * 首页列表
     *
     * @param $user_id
     * @param $page
     * @return array|false|mixed|\PDOStatement|string|\think\Collection
     */
    public function index() {
        $user_id  = I('get.user_id', 1, 'intval');
        $p        = I('get.p', 1, 'intval');

        $query     = '';
        $where     = array();
        $sort      = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
        $start     = 0;
        if ($p > 1) {
            $start = $this->page_size * ($p - 1);
        }
        if ($this->openSearchStatus == true) {
            $items_list = $this->_getOpenSearchList($query, $sort, null, $start, $this->page_size);
        } else {
            $order      = 'ordid asc, id desc';
            $items_list = M('items')->where($where)->order($order)->limit($start . ',' . $this->page_size)->select();
        }
        foreach ($items_list as &$val) {
            $val['item_url'] = U('Item/detail', array('num_iid' => $val['num_iid'], 'user_id' => $user_id, 'type' => 'app'));
        }

        if (IS_AJAX) {
            $this->ajaxReturn(array('code' => 1, 'data' => $items_list, 'msg' => 'ok'));
        }

        // 商品分类的数据
        $cate = $this->_getCate();

        $count   = M('items')->where($where)->count();
        $maxpage = ceil($count / $this->page_size);

        $this->assign('items_list', $items_list);
        $this->assign('ajaxurl', U('Index/index', array('p' => $p)));
        $this->assign('maxpage', $maxpage);
        $this->assign('cate', $cate);
        $this->assign('user_id', $user_id);
        $this->assign('version', $this->version);
        $this->display();
    }

    /**
     * 获取分类列表页
     *
     * @param $key
     * @param $user_id
     * @return mixed
     */
    public function getCateList() {
        $cate_id = I('get.cate_id', 0, 'intval');
        $user_id = I('get.user_id', 0, 'intval');
        $p    = I('get.p', 1, 'int'); //页码

        $where = array();
        $query = '';
        if ($cate_id == $this->nine_nine) {
            $where['coupon_type'] = '4';
            $query                = "coupon_type:'4'";
            $name                 = '9.9';
        } else if ($cate_id == $this->hot) {
            $where['coupon_type'] = '5';
            $query                = "coupon_type:'5'";
            $name                 = '热销';
        } else {
            if ($cate_id > 0) {
                $where['cate_id'] = $cate_id;
                $query            = "cate_id:'{$cate_id}'";
            }
        }

        $sort  = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
        $start = 0;
        if ($p > 1) {
            $start = $this->page_size * ($p - 1);
        }
        if ($this->openSearchStatus == true) {
            $items_list = $this->_getOpenSearchList($query, $sort, null, $start, $this->page_size);
        } else {
            $order      = 'ordid asc, id desc';
            $items_list = M('items')->where($where)->order($order)->limit($start . ',' . $this->page_size)->select();
        }
        foreach ($items_list as &$val) {
            $val['item_url'] = U('Item/detail', array('num_iid' => $val['num_iid'], 'user_id' => $user_id, 'type' => 'app'));
        }
        if (IS_AJAX) {
            $this->ajaxReturn(array('code' => 1, 'data' => $items_list, 'msg' => 'ok'));
        }

        //  总共的页数
        $count   = M('items')->where($where)->count();
        $maxpage = ceil($count / $this->page_size);

        if (empty($name)) {
            $name = M('items_cate')->where(array('id' => $cate_id))->getField('name');
        }
//var_dump($name);exit;
        $this->assign('items_list', $items_list);
        $this->assign('ajaxurl', U('Index/getCateList', array('p' => 1)));
        $this->assign('user_id', $user_id);
        $this->assign('name', $name);
        $this->assign('maxpage', $maxpage);
        $this->assign('cate_id', $cate_id);
        $this->assign('version', $this->version);
        if ($cate_id == 28) {
            $act = 'two';
        } else if ($cate_id == 1000) {
            $act = 'three';
        } else {
            $act = 'one';
        }
        $this->assign('act', $act);
        $this->display();
    }

}