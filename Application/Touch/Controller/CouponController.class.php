<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2017/12/26
 * Time: 17:11
 */

namespace Touch\Controller;

/**
 * Class Coupon
 *
 * @package Touch\Controller
 */
class CouponController extends CommonController {
    /**
     * @var int
     */
    protected $limit = 10;

    /**
     * 优惠券列表
     */
    public function index() {
        $open_id = session('wx_user_openid');
        if (!$open_id) {
            $this->_WeChatLogin(3);
        }
        $wx_user_id = intval(M('wxuser')->where(array('openid' => $open_id))->getField('id'));
        if ($wx_user_id == 0) {
            $this->redirect('Index/index');
        }
        $map    = array('wxuser_id' => $wx_user_id);
        $status = I('get.status', 'not_use', 'trim');
        if ($status == 'not_use') {
            $where = array('status' => 0, 'end_time' => array('gt', time()));
            $order = "id desc";
        } elseif ($status == 'use') {
            $where = array('status' => 1);
            $order = "used_time desc,id desc";
        } else {
            $where = array('status' => 0, 'end_time' => array('lt', time()));
            $order = "id desc";
        }
        $data = M('coupon')->where($map)->where($where)->limit($this->limit + 1)->order($order)->select();
        if ($status == 'not_use') {
            foreach ($data as &$val) {
                $val['img'] = qrcode($val['coupon_sn']);
            }
        }
        $is_next = 0;
        if (count($data) > $this->limit) {
            $is_next = 1;
            array_pop($data);
        }
        $this->assign(array('data' => $data, 'is_next' => $is_next, 'status' => $status));
        $this->display();
    }

    /**
     * 获取更多优惠券
     */
    public function getMoreCoupon() {
        if (!IS_AJAX) {
            $this->error('非法请求！');
        }
        $open_id = session('wx_user_openid');
        if (!$open_id) {
            $this->error('当前状态已失效，请刷新页面！');
        }
        $wx_user_id = intval(M('wxuser')->where(array('openid' => $open_id))->getField('id'));
        if ($wx_user_id == 0) {
            $this->error('用户信息不存在无法获取优惠券！');
        }
        $page   = I('get.page', 2, 'int');
        $map    = array('wxuser_id' => $wx_user_id);
        $status = I('get.status', 'not_use', 'trim');
        if ($status == 'not_use') {
            $where = array('status' => 0, 'end_time' => array('gt', time()));
            $order = "id desc";
        } elseif ($status == 'use') {
            $where = array('status' => 1);
            $order = "used_time desc,id desc";
        } else {
            $where = array('status' => 0, 'end_time' => array('lt', time()));
            $order = "id desc";
        }
        $data = M('coupon')->where($map)->where($where)->page($page)->limit($this->limit + 1)->order($order)->select();
        foreach ($data as &$val) {
            if ($status == 'not_use') {
                $val['img'] = qrcode($val['coupon_sn']);
            }
            $val['end_time'] = date('Y年m月d日', $val['end_time']);
        }
        $is_next = 0;
        if (count($data) > $this->limit) {
            $is_next = 1;
            array_pop($data);
        }
        $this->success(array('data' => $data, 'is_next' => $is_next));
    }
}