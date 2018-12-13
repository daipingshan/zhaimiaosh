<?php

namespace Touch\Controller;

use Common\Controller\CommonBaseController;

class ItemController extends CommonBaseController {
    /**
     * 获取商品详情
     *
     * @param $user_id
     * @param $id
     * @return mixed
     */
    public function detail() {
        $num_iid   = I('get.num_iid', '', 'trim');
        $type      = I('get.type', '', 'trim');
        $user_id   = I('get.user_id', 1, 'intval');
        $kou_ling  = I('get.kou_ling', '', 'trim,urldecode');
        //var_dump($num_iid);exit;

        $user = M('user')->where(array('id' => $user_id))->find();
        if (isset($user['pid']) && !empty($user['pid'])) {
            $this->pid = $user['pid'];
        } else if (isset($user['inviter_pid']) && !empty($user['inviter_pid'])) {
            $this->pid = $user['inviter_pid'];
        }

        switch ($info['shop_type']) {
            case 'B' :
            case 'C' :
                $res = $this->_getDTKDetail($num_iid, $this->pid);
                break;
            default:
                $res = $this->_getQWDetail($num_iid, $this->pid);
                break;
        }

        if ($res['status'] != 1) {
            $this->assign('info', $res['info']);
            $this->display('Public/error');
            exit;
        }

        $tou = 'weixin';
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') === false) {
            $tou = 'other';
        }

        //var_dump($res);exit;

        $this->assign('item', $res['data']);
        $this->assign('tou', $tou);
        $this->assign('user_id', $user_id);
        $this->assign('version', $this->version);
        $this->display();
    }

    /**
     * 获取合作伙伴的信息
     */
    protected function _getPartnerInfo($pid) {
        list($_, $taobao_union_id, $site_id, $_) = explode('_', $pid);
        $partner = S('partner_config' . $taobao_union_id);
        if (empty($partner)) {
            $partner = M('partner')->where(array('taobao_union_id' => $taobao_union_id))->find();
            S('partner_config' . $taobao_union_id, $partner);
        }

        return $partner;
    }

    /**
     * 大淘客相关业务
     *
     * @param $id
     */
    protected function _getDTKDetail($num_iid, $pid) {
        if ($this->openSearchStatus == true) {
            $query  = "num_iid:'{$num_iid}'";
            $detail = $this->_getOpenSearchList($query, null, null, 0, 1);

            if (!empty($detail)) {
                $info = array_pop($detail);
            } else {
                $info = array();
            }
        } else {
            $info = M('items')->where(array('num_iid' => $num_iid))->field('*,quan as coupon_money')->find();
        }
        if (!$info) {
            return array('status' => -1, 'info' => '商品信息不存在！');
        }

        $res = $this->_applyHigh($info, $pid);

        if ($res['status'] == 0) {
            return array('status' => -1, 'info' => '获取优惠券异常，请重试');
        }
        $info['buy_url'] = $res['data']['buy_url'];
        $res             = $this->_getPass($res['data']['buy_url'], $info['title'], $info['pic_url']);

        if ($res['status'] == 0) {
            return array('status' => -1, 'info' => $res['info']);
        }
        $info['tao_kou_ling'] = $res['data']['tao_kou_ling'];

        return array('status' => 1, 'data' => $info);
    }

    /**
     * 全网相关业务
     *
     * @param $id
     * @return array
     */
    protected function _getQWDetail($id, $pid) {
        $url               = "https://detail.tmall.com/item.htm?id={$id}";
        $obj               = new \Common\Org\TaoBaoApi();
        $res               = $obj->search($url);
        $info              = $res['data'][0];
        $info['click_url'] = "https://uland.taobao.com/coupon/edetail?&pid={$pid}&itemId={$id}&src=cd_cdll";
        //迷离获取淘口令
        $res = $this->_applyHigh($info, $pid);
        if ($res['status'] != 1) {
            return array('status' => -1, 'info' => '获取优惠券失败！');
        }
        $info['buy_url'] = $res['data']['buy_url'];
        $res             = $this->_getPass($res['data']['buy_url'], $info['title'], $info['pic_url']);
        if ($res['status'] == 0) {
            return array('status' => -1, 'info' => $res['info']);
        }
        $info['tao_kou_ling'] = $res['data']['tao_kou_ling'];

        return array('status' => 1, 'data' => $info);
    }

    /**
     * 获取搜索列表页
     *
     * @param $key
     * @param $user_id
     * @return mixed
     */
    public function search() {
        $keyword_str = I('get.keyword', '', 'trim');
        $user_id     = I('get.user_id', '', 'intval');
        $p           = I('get.p', 1, 'intval');

        $query = '';
        $type  = 'app';
        $where = array();
        if (!empty($keyword)) {
            $query                  = "keyword:'{$keyword}'";
            $where['title|num_iid'] = array('like', '%' . $keyword . '%');
        }

        $start = 0;
        if ($p > 1) {
            $start = $this->page_size * ($p - 1);
        }
        $sort = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));

        if ($this->openSearchStatus == true) {
            $item = $this->_getOpenSearchList($query, $sort, null, $start, $this->page_size);
        } else {
            $order = 'ordid asc, id desc';
            $item  = M('items')->where($where)->order($order)->limit($start . ',' . $this->page_size)->select();
        }
        if (!$item) {
            $sort = '';
            $item = $this->_getTaoBaoSearch($keyword, $p, $sort, $this->page_size);
            $type = 'qw';
        }
        foreach ($item as &$val) {
            $val['item_url'] = U('Item/detail', array('id' => $val['id'], 'user_id' => $user_id, 'type' => $type));
        }
        if (IS_AJAX) {
            $this->ajaxReturn(array('code' => 1, 'user_id' => $user_id, 'data' => $item, 'msg' => 'ok'));
        }

        //  总共的页数
        $count   = M('items')->where($where)->count();
        $maxpage = ceil($count / $this->page_size);

        if (mb_strlen($keyword) > 6) {
            $keyword = mb_substr($keyword, 0, 6) . '...';
        }

        $this->assign('item', $item);
        $this->assign('user_id', $user_id);
        $this->assign('keyword', $keyword_str);
        $this->assign('maxpage', $maxpage);
        $this->assign('version', $this->version);
        $this->assign('ajaxurl', U('Item/search', array('p' => 1)));
        $this->display();
    }

}