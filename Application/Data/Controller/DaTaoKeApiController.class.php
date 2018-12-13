<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/4/16
 * Time: 9:58
 */

namespace Data\Controller;

use Common\Controller\CommonBaseController;
use \Common\Org\DaTaoKe as DTK;

/**
 * 大淘客商品采集器
 * Class DaTaoKeApiController
 *
 * @package Data\Controller
 */
class DaTaoKeApiController extends CommonBaseController {

    /**
     * DaTaoKeController constructor.
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * 获取商品数据
     */
    public function addInitData() {
        //大淘客数据抓取
        $obj = new DTK();
        $i   = I('get.i', 1, 'int');
        while (true) {
            $data = $obj->getMoreData($i);
            if (count($data) > 0) {
                $add_data = $num_iid_data = array();
                foreach ($data as $key => $val) {
                    $add_data[$val['num_iid']] = $val;
                    $num_iid_data[]            = $val['num_iid'];
                }
                $have_num_iid = M('items')->where(array('num_iid' => array('in', $num_iid_data)))->getField('num_iid', true);
                if (count($have_num_iid) > 0) {
                    foreach ($have_num_iid as $v) {
                        if (isset($add_data[$v]) && $add_data[$v]) {
                            unset($add_data[$v]);
                        }
                    }
                }
                if ($add_data) {
                    M('items')->addAll(array_values($add_data));
                }
                $msg = "第{$i}页数据采集成功";
                $this->_addLog('add_item.log', $msg);
                sleep(2);
                $i++;
            } else {
                $msg = "数据采集完毕";
                $this->_addLog('add_item.log', $msg);
                break;
            }
        }
        $msg = "采集完成";
        $this->_addLog('add_item.log', $msg);
    }

    /**
     * 获取商品数据
     */
    public function addData() {
        //大淘客数据抓取
        $obj  = new DTK();
        $data = $num_iid_data = array();
        $time = time();
        for ($i = 1; $i < 11; $i++) {
            if (time() - $time > 540) {
                exit();
            }
            $res = $obj->getMoreData($i);
            foreach ($res as $key => $val) {
                $data[$val['num_iid']] = $val;
                $num_iid_data[]        = $val['num_iid'];
            }
            usleep(910000);
        }
        $have_num_iid = M('items')->where(array('num_iid' => array('in', $num_iid_data)))->getField('num_iid', true);
        if (count($have_num_iid) > 0) {
            foreach ($have_num_iid as $v) {
                if (isset($data[$v]) && $data[$v]) {
                    unset($data[$v]);
                }
            }
        }
        if ($data) {
            M('items')->addAll(array_values($data));
        }
        echo "采集完成\r\n";
    }

    /**
     * 获取商品数据
     */
    public function addTopData() {
        //大淘客数据抓取
        $obj  = new DTK();
        $data = array();
        $time = time();
        $res  = $obj->getTopData();
        M('items')->where(array('coupon_type' => 5, 'ordid' => array('lt', 1000)))->setInc('ordid', 200);
        foreach ($res as $key => $val) {
            if (time() - $time > 1190) {
                exit();
            }
            $info = M('items')->field('id')->where(array('num_iid' => $val['num_iid']))->find();
            if (!$info) {
                $val['ordid']          = $key + 11;
                $val['coupon_type']    = 5;
                $data[$val['num_iid']] = $val;
            } else {
                M('items')->where(array('id' => $info['id']))->save(array('coupon_type' => 5, 'volume' => $val['volume'], 'ordid' => $key + 11));
            }
        }
        if ($data) {
            M('items')->addAll(array_values($data));
        }
        echo "采集完成\r\n";
    }


    /**
     * 处理下线商品
     */
    public function updateData() {
        $id    = S('data_items_id') ? S('data_items_id') : 0;
        $where = array('id' => array('gt', $id), 'dataoke_id' => array('gt', 0));
        $data  = M('items')->field('id,num_iid,dataoke_id')->where($where)->limit(300)->order('id asc')->select();
        $start = time();
        $obj   = new DTK();
        foreach ($data as $k => $v) {
            $end = time();
            if ($end - $start > 298) {
                S('data_items_id', $v['id']);
                exit;
            }
            $item_res = $obj->isItemOnline($v['dataoke_id']);
            if ($item_res['status'] == 0) {
                M('items')->where(array('dataoke_id' => $v['dataoke_id']))->delete();
            } else {
                if ($item_res['data']) {
                    $save_data = $item_res['data'];
                    if ($v['coupon_type'] == 4 && $save_data['coupon_price'] > 9.9) {
                        $save_data['coupon_type'] = 1;
                    }
                    $save_data['last_query_dataoke_time'] = time();
                    M('items')->where(array('id' => $v['id']))->save($save_data);
                }
            }
            usleep(910000);
        }
        $last_id = isset($v['id']) && $v['id'] ? $v['id'] : S('data_items_id');
        S('data_items_id', (int)$last_id);
        if (count($data) < 300) {
            S('data_items_id', 0);
        }
        echo "采集完成\r\n";
    }

    /**
     * 重置手工提单的商品排序
     */
    public function clearSortItem() {
        M('items')->where(array('ordid' => array('neq', 9999), 'coupon_type' => array('neq', 5)))->save(array('ordid' => 9999));
        M('items')->where(array('coupon_end_time' => array('elt', time())))->delete();
        echo "更新完成\r\n";
    }

}