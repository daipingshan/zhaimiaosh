<?php

namespace Api\Controller;

/**
 * 拼多多接口
 * Class PinduoduoController
 *
 * @package Api\Controller
 */
class PddController extends CommonController {

    /**
     * 构造方法
     */
    public function __construct() {
        parent::__construct();

        $auth_arr = array('getTopicList', 'getTopicShareUrl', 'getTopicDetail');
        $token = I('request.token', '', 'trim');

        if (in_array(ACTION_NAME, $auth_arr) && '' != $token) {
            $this->_checkUser();
        }
    }

    /**
     * 查询拼多多专题列表
     */
    public function getTopicList() {
        $page = I('get.page', 1, 'intval');
        $business_params = array(
            'type' => 'pdd.ddk.theme.list.get', //API接口名称
            'page' => $page, //选填 分页
            'page_size' => 20,
        );

        $r = $this->_getPinduoduoData($business_params);
        if (isset($r['theme_list_get_response']['theme_list'])) {
            $topics = array();
            foreach ($r['theme_list_get_response']['theme_list'] as $key => $topic) {
                $topics[] = array(
                    'topic_id' => $topic['id'],
                    'pic_url' => $topic['image_url'],
                    'topic_name' => $topic['name'],
                    'item_num' => $topic['goods_num'],
                );
            }

            shuffle($topics);
            $this->output('ok', 'success', $topics);
        } else {
            $this->output('ok', 'success', array());
        }
    }

    /**
     * 查询拼多多专题分享链接
     */
    public function getTopicShareUrl() {
        $topic_id = I('request.topic_id', '', 'trim');
        $topic_share_url = $this->_getTopicShareUrl($topic_id);
        if (empty($topic_share_url)) {
            $this->output('暂未获取到分享链接，请重试！');
        } else {
            $this->output('ok', 'success', array('topic_share_url' => $topic_share_url));
        }
    }

    /**
     * 获取拼多多专题信息
     */
    public function getTopicDetail() {
        $topic_id = I('request.topic_id', '', 'trim');
        $business_params = array(
            'type' => 'pdd.ddk.theme.goods.search', //API接口名称
            'theme_id' => $topic_id,
        );

        $r = $this->_getPinduoduoData($business_params);
        if (isset($r['theme_list_get_response']['goods_list'])) {
            $items = array();
            foreach ($r['theme_list_get_response']['goods_list'] as $key => $goods) {
                $after_coupon_price = ($goods['min_group_price'] - $goods['coupon_discount']) / 100;
                $commission = computed_price($after_coupon_price * $goods['promotion_rate'] / 1000, 2, C('COMMISSION_RATE.base_commission_rate'), 'pinduoduo');
                $group_leader_commission = computed_price($after_coupon_price * $goods['promotion_rate'] / 1000, 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader'), 'pinduoduo');
                $items[] = array(
                    'num_iid' => $goods['goods_id'],
                    'title' => $goods['goods_name'],
                    'price' => format_view_money($goods['min_group_price'] / 100),
                    'coupon_price' => format_view_money($after_coupon_price),
                    'pic_url' => format_url_to_https($goods['goods_thumbnail_url']),
                    'coupon_money' => format_view_money($goods['coupon_discount'] / 100),
                    'sale_num' => $goods['sold_quantity'],
                    'commission' => $commission,
                    'group_leader_commission' => $group_leader_commission,
                );
            }

            $topic_share_url = $this->_getTopicShareUrl($topic_id);
            $this->output('ok', 'success', array('items' => $items, 'topic_share_url' => $topic_share_url));
        } else {
            $this->output('暂未获取专题商品信息！');
        }
    }

    /**
     * 查询拼多多专题分享链接
     */
    private function _getTopicShareUrl($topic_id) {
        $business_params = array(
            'type' => 'pdd.ddk.theme.prom.url.generate', //API接口名称
            'pid' => $this->pdd_pid,
            'theme_id_list' => '[' . $topic_id . ']',
            'generate_short_url' => 'true'
        );

        $r = $this->_getPinduoduoData($business_params);
        if (isset($r['theme_promotion_url_generate_response']['url_list']) && !empty($r['theme_promotion_url_generate_response']['url_list'])) {
            $url_info = array_shift($r['theme_promotion_url_generate_response']['url_list']);
            return $url_info['short_url'];
        } else {
            return '';
        }
    }

}