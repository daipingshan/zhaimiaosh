<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/26
 * Time: 17:39
 */

namespace Api\Controller;

/**
 * h5相关功能
 * Class TouchController
 *
 * @package Api\Controller
 */
class TouchController extends CommonController {

    /**
     * app 分享下载
     */
    public function download() {
        $invite_code = I('get.invite_code', 'zhaimiaoshenghuo', 'trim');
        $this->assign('invite_code', $invite_code);
        $this->display();
    }

    /**
     * 商品详情
     */
    public function detail() {
        $num_iid      = I('get.num_iid', '', 'trim');
        $user_id      = I('get.user_id', 1, 'int');
        $tao_kou_ling = I('get.tao_kou_ling', '', 'trim,urldecode');

        if (0 == $user_id) {
            $user_id = 1;
        }

        if (empty($num_iid) || empty($user_id) || empty($tao_kou_ling)) {
            $error = '请求参数不合法';
            $this->assign('error', $error);
            $this->display('noItem');
            die();
        }
        $res = $this->_getItemDetail($num_iid);
        if ($res['status'] == 1) {
            $info = $res['data'];
        } else {
            $url  = "http://item.taobao.com/item.htm?id={$num_iid}";
            $data = $this->_getTaoBaoSearch($url, 1, 0);
            $info = $data[0];
        }
        if (empty($info)) {
            $error = '商品已下线';
            $this->assign('error', $error);
            $this->display('noItem');
            die();
        }
        $user_info = M('user')->find($user_id);
        if (empty($user_info) || $user_info['status'] == 0) {
            $error = '分享用户信息异常';
            $this->assign('error', $error);
            $this->display('noItem');
            die();
        }
        $info['invite_code'] = $user_info['invite_code'];
        $pid                 = $user_info['pid'];
        if (empty($pid)) {
            $pid = $user_info['inviter_pid'];
        }
        $res = $this->_applyHigh($info, $pid);
        if ($res['status'] == 0) {
            $this->assign('error', $res['info']);
            $this->display('noItem');
            die();
        }
        $info['buy_url']      = $res['data']['buy_url'];
        $info['tao_kou_ling'] = $tao_kou_ling;
        $this->assign('item', $info);
        $this->display();
    }

    /**
     * 用户等级说明
     */
    public function grade() {
        $data = C('COMMISSION_RATE');
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 用户升级说明
     */
    public function upgrade() {
        $level               = I('get.level', 1, 'intval');
        $tips                = '';
        $data                = C('COMMISSION_RATE');
        $son_commission_rate = $data['son_commission_rate'] / $data['base_commission_rate'] * 100;

        switch ($level) {
            case 1:
                $tips = "1、自购或分享订单（交易成功），可获得平台展示的100%收入；<br />2、邀请的下级下单平台奖利{$son_commission_rate}%；";
                break;
            case 2:
                $tips = "1、自购或分享订单（交易成功），可获得平台展示的100%收入；<br />2、邀请的下级下单平台奖利{$son_commission_rate}% + 平台补贴" . $data['v2_award'] / $data['base_commission_rate'] * 100 . "%（限1个月）；";
                break;
            case 3:
                $tips = "1、自购或分享订单（交易成功），可获得平台展示的100%收入；<br />2、邀请的下级下单平台奖利{$son_commission_rate}% + 平台补贴" . $data['v3_award'] / $data['base_commission_rate'] * 100 . "%（限1个月）；";
                break;
            case 4:
                $tips = "1、自购或分享订单（交易成功），可获得平台展示的100%收入；<br />2、邀请的下级下单平台奖利{$son_commission_rate}% + 平台补贴" . $data['v4_award'] / $data['base_commission_rate'] * 100 . "%（限1个月）；";
                break;
            case 5:
                $tips = "1、自购或分享订单（交易成功），可获得平台展示的100%收入；<br />2、邀请的下级下单平台奖利{$son_commission_rate}% + 团队所有成员收益的" . $data['group_leader'] / $data['base_commission_rate'] * 100 . "%；";
                break;

            default:
                $tips = "平台展示所有优惠券，免费使用！";
                break;
        }
        $this->assign('level_name', get_level($level));
        $this->assign('tips', $tips);
        $this->assign('data', $data);
        $this->display();
    }

    /**
     * 新手指南帮助
     */
    public function help() {
        $this->display();
    }

    /**
     * 玖富万卡
     */
    public function proxy() {
        $url = "https://onecard.9fbank.com/wkCubeNew/#/register?proId=zk1bb43bf65dfee1eef854f55099282313c";
        header("location: {$url}");
    }

    public function test() {
        $this->display();
    }

    /**
     * 查看订单
     */
    public function getOrderInfo() {
        if (IS_AJAX) {
            $order_sn = I('get.order_sn', '', 'trim');
            if (strlen($order_sn) < 11) {
                $this->output('请填写正确的京东订单');
            } else {
                //查询订单，如果已分佣，就不是免单的订单
                $order = M('order')->where(array('order_sn' => $order_sn))->field('order_sn,pay_status,title,total_money,commission')->select();
                if (count($order) == 0) {
                    $this->output('订单信息不存在，请稍后查询，订单同步需要5-10分钟！');
                } else {
                    foreach ($order as &$order_info) {
                        switch ($order_info['pay_status']) {
                            case 'paid':
                                $order_info['pay_status_view'] = '订单付款 初审为有效订单';
                                break;
                            case 'settle':
                                $order_info['pay_status_view'] = '订单结算';
                                break;
                            case 'fail':
                                $order_info['pay_status_view'] = '订单失效';
                                break;
                            case 'success':
                                $order_info['pay_status_view'] = '交易成功';
                                break;
                            case 'refund':
                                $order_info['pay_status_view'] = '订单退款';
                                break;
                            default:
                                $order_info['pay_status_view'] = '订单';
                                break;
                        }

                        if ($order_info['total_money'] == 0) {
                            $order_info['pay_status_view'] = '该商品为赠品';
                        } else if ($order_info['total_money'] != 9.9) {
                            $order_info['pay_status_view'] = '订单付款金额不符合免单条件，仅9.9元才可免单';
                        }

                        if ($order_info['commission'] < 4.46 && $order_info['commission'] > 0) {
                            $order_info['pay_status_view'] = '订单无效，商家不参与免单活动了';
                        }
                    }
                }
                $this->output('ok', 'success', $order);
            }
        } else {
            $this->display();
        }
    }

}