<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/17
 * Time: 12:01
 */

namespace Api\Controller;

/**
 * Class IndexController
 *
 * @package Api\Controller
 */
class IndexController extends CommonController {

    /**
     * app首页
     */
    public function index() {
        $client_platform = I('client_platform', '', 'trim');

        $data['advert_data']         = $this->_getAdvert(1, $client_platform);
        $data['advert2_data']        = $this->_getAdvert(3, $client_platform);
        $data['module_data']         = $this->_getAdvert(2, $client_platform);
        $data['index_4_module_pics'] = C('INDEX_4_MODULE_PICS');
        $data['cate_data']           = array_values($this->_getCate());
        $data['selected_item_data']  = $this->_getSelectedItem();
        $data['item_data']           = $this->_getItem();
        $data['item_topics']         = $this->_getItemTopic();
        $this->output('ok', 'success', $data);
    }

    /**
     * 获取首页精选商品
     */
    protected function _getSelectedItem() {
        if ($this->openSearchStatus === true) {
            $filter = 'handpick_time > 0';
            $sort   = array(array('key' => 'handpick_time', 'val' => 0));
            $query  = '';
            $data   = $this->_getOpenSearchList($query, $sort, $filter, 0, 20);
        } else {
            $where = array('handpick_time' => array('gt', 0));
            $order = 'handpick_time desc';
            $data  = $this->_getItemsList($where, $order, 0, 20);
        }

        shuffle($data);
        return array_slice($data, 0, 12);
    }

    /**
     * 获取首页商品
     */
    protected function _getItem() {
        if ($this->openSearchStatus === true) {
            $sort  = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
            $query = '';
            $data  = $this->_getOpenSearchList($query, $sort, null, 0, $this->limit);

            if (empty($data)) {
                $order = 'ordid asc,id desc';
                $where = array();
                $data  = $this->_getItemsList($where, $order, 0, $this->limit);
            }
        } else {
            $order = 'ordid asc,id desc';
            $where = array();
            $data  = $this->_getItemsList($where, $order, 0, $this->limit);
        }

        shuffle($data);
        return $data;
    }

    /**
     * 获取专题列表信息
     */
    protected function _getItemTopic() {
        $data = S('item_topic');
        if (empty($data)) {
            $data = M('item_topic')->where(array('type' => 1))->field('id,topic_item_pic')->order('sort asc')->select();
            S('item_topic', $data);
        }
        return array_slice($data, 0, 10);
    }

    public function img(){
        $num_iid = '';
        $data = $this->_getTaoBaoSearch($num_iid,1,0);
        dump($data);
    }
}