<?php
/**
 * 订单数据
 * Author: dongguangqi
 */

namespace Data\Controller;
use Common\Controller\CommonBaseController;
use Common\Org\Http;

/**
 *
 *
 * @package Data\Controller
 */
class PartnerOrderController extends CommonBaseController {
    protected $order_status = array(
        '订单失效' => 'fail', '订单结算' => 'settle', '订单成功' => 'success', '订单付款' => 'paid'
    );

    /**
     * @var string
     */
    private $search_url = "http://pub.alimama.com/items/search.json?q=%s&_t=%s&auctionTag=&toPage=%s&perPageSize=%s&shopTag=yxjh&t=%s&_tb_token_=&pvid=10_49.221.62.102_4720_1496801283153";

    /**
     * @var string
     */
    protected $down_url = "http://pub.alimama.com/report/getTbkPaymentDetails.json?spm=a219t.7664554.1998457203.10.1b1ff9e1XNv6tR&queryType=%s&payStatus=%s&DownloadID=DOWNLOAD_REPORT_INCOME_NEW&startTime=%s&endTime=%s";

    /**
     * @var string
     */
    protected $third_down_url = "http://pub.alimama.com/report/getTbkThirdPaymentDetails.json?spm=a219t.7664554.1998457203.230.6e62f2cfp0UddZ&queryType=%s&payStatus=%s&DownloadID=DOWNLOAD_REPORT_TK3_PUB&startTime=%s&endTime=%s";

    /**
     * @var string
     */
    protected $refund_down_url = "http://pub.alimama.com/report/getNewTbkRefundPaymentDetails.json?refundType=1&searchType=3&DownloadID=DOWNLOAD_EXPORT_CPSPAYMENT_REFUND_OVERVIEW&startTime=%s&endTime=%s";

    /**
     * @var string
     */
    protected $third_refund_down_url = "http://pub.alimama.com/report/getNewTbkRefundPaymentDetails.json?refundType=2&searchType=3&DownloadID=DOWNLOAD_EXPORT_CPSPAYMENT_REFUND_OVERVIEW&startTime=%s&endTime=%s";

    public function __construct() {
        parent::__construct();
    }

    /**
     * 获取淘宝新订单
     */
    public function getNewOrder() {
        $partners = M('partner')->where(array('id' => array('gt', 1), 'status' => 1))->select();
        shuffle($partners);

        //$partners = M('partner')->where(array('id' => 15))->select();

        foreach ($partners as $partner) {
            $data = array();
            list($_, $_, $site_id, $_) = explode('_', $partner['pid']);
            $start          = date('Y-m-d', strtotime('-2 days'));
            $end            = date('Y-m-d');
            $url            = sprintf($this->down_url, '1', '', $start, $end);
            $filename       = "/home/order_log/partner_order_{$partner['mobile']}.xls";
            $res            = $this->_downFile($url, $filename, $partner);
            sleep(3);
            $third_url      = sprintf($this->third_down_url, '2', '', $start, $end);
            $third_filename = "/home/order_log/partner_order_third_{$partner['mobile']}.xls";
            $third_res      = $this->_downFile($third_url, $third_filename, $partner);
            if ($res['code'] == 1) {
                $data = $this->_createData($filename);
            }
            if ($third_res['code'] == 1) {
                $_data = $this->_createData($third_filename);
                $data = array_merge($data, $_data);
            }

            if (!empty($data)) {
                /**
                 * 获取宅喵生活的订单，单独保存
                 */
                $zm_orders = array();
                //$zm_siteids = explode(',', C('zm_tao_bao_site_id'));
                $zm_siteids = array($site_id);
                $taobao_ids = array();
                foreach ($data as $key => $order) {
                    if (in_array($order[26], $zm_siteids)) {
                        $taobao_ids[] = $order[3];
                        $zm_orders[] = $order;
                        unset($data[$key]);
                    }
                }

                if (!empty($zm_orders)) {
                    krsort($zm_orders);
                    $this->_doGetNewOrder($zm_orders, $partner);
                }
            } else {
                echo 'no order';
            }

            sleep(3);
        }
    }

    /**
     * 异步获取淘宝新订单
     */
    public function getNewOrderAsync() {
        $pid = I('request.pid', '', 'trim');
        $content = I('request.content', '', 'trim');

        $goods     = base64_decode(str_replace(array('%2b', ' '), '+', urldecode($content)));
        $goods_arr = @json_decode(str_ireplace(array("\n", "\r", "\t", "\\"), '', $goods) , true);

        $partner = M('partner')->where(array('pid' => $pid))->find();
        if (empty($partner)) {
            exit('该合作伙伴PID错误');
        }

        if (isset($goods_arr['dingdan'])) {
            $orders = array_map(function($item) {
                return array_values($item);
            }, $goods_arr['dingdan']);

            /**
             * 获取宅喵生活的订单，单独保存
             */
            $zm_orders = array();
            list($_, $_, $site_id, $_) = explode('_', $pid);
            $zm_siteids = array($site_id);
            foreach ($orders as $key => $order) {
                if (in_array($order[26], $zm_siteids)) {
                    $zm_orders[] = $order;
                    unset($orders[$key]);
                }
            }

            if (!empty($zm_orders)) {
                krsort($zm_orders);

                /*$this->_addLog('getNewOrderAsync', var_export($partner, true));
                $this->_addLog('getNewOrderAsync', var_export($zm_orders, true));
                echo 'success';
                exit;*/
                echo $this->_doGetNewOrder($zm_orders, $partner);
            } else {
                echo '暂无新的订单';
            }
        } else {
            $this->_addLog('getNewOrderAsync', var_export($partner, true));
            $this->_addLog('getNewOrderAsync', var_export($goods, true));
            $this->_addLog('getNewOrderAsync', var_export($goods_arr, true));
            exit('未知错误！');
        }
    }

    /**
     * 执行获取淘宝新订单
     */
    protected function _doGetNewOrder($orders, $partner) {
        if (empty($orders)) {
            return 'empty order';
        }

        $model = new \Think\Model();

        $taobao_order_sns = array();
        $pids = array();
        foreach ($orders as $key => $order) {
            if ('订单失效' == $order[8]) { //去除失效单
                unset($orders[$key]);
                continue;
            }

            if (!$this->order_status[$order[8]]) {
                $this->_addLog('getNewOrder', $partner['partner_name'] . '不存在的订单类型：' . $order[8] . var_export($order, true));
                unset($orders[$key]);
                continue;
            }

            $taobao_order_sns[] = $order[24];

            $pid = 'mm_' . $partner['taobao_union_id'] . '_' . $order[26] . '_' . $order[28];
            $orders[$key][30] = $pid;
            $pids[] = $pid;
        }

        //新订单
        //查出已存在的和已失效的去除
        //根据PID查询用户信息
        //根据用户是否有上线、是否有团长、是否有团长的团长，来拆分成多条子订单。一次插入多条记录：主订单和子订单

        $fields = 'pid, id, inviter_id, inviter_pid, level, first_order_time, group_leader_id, group_leader_parent_id';
        $pids = array_unique($pids);
        unset($pids['']);
        if (empty($pids)) {
            return 'empty user pid';
        }
        $users = M('user')->where(array('pid' => array('in', $pids)))->getField($fields);
        $inviter_users = M('user')->where(array('pid' => array('in', array_column($users, 'inviter_pid'))))->getField($fields);
        $users = array_merge($users, $inviter_users); //$users为包含了订单用户和订单用户的邀请人

        $exist_order_sns = M('order')->where(array('order_sn' => array('in', $taobao_order_sns)))->getField('order_sn', true);

        /**
         * 删除已存在的订单，然后查询剩余订单里的商品图片
         */
        $num_iids = array();
        foreach ($orders as $key => $order) {
            if (in_array($order[24], $exist_order_sns)) {
                unset($orders[$key]); //去除已存在的订单
                continue;
            }
            $num_iids[] = $order[3];
        }
        if (empty($num_iids)) {
            return '暂无新的订单';
        }
        $sort_num_iids = array_count_values($num_iids);
        arsort($sort_num_iids);
        $search_num_iids = array_slice(array_keys($sort_num_iids), 0, 30);
        $query_array = array();
        foreach ($search_num_iids as $num_iid) {
            $query_array[] = "num_iid:'{$num_iid}'";
        }
        $query = '(' . implode(' OR ', $query_array) . ')';
        $sort  = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
        $filter  = null;
        $items = $this->_getOpenSearchList($query, $sort, $filter, 0, 100);
        $item_pics = array();
        foreach ($items as $key => $item) {
            $item_pics[$item['num_iid']] = $item['pic_url'];
        }

        $add_orders = array(); //待添加的主订单
        $add_order_commissions = array(); //待添加的子订单
        $model->startTrans();
        try {
            foreach ($orders as $key => $order) {
                if (in_array($order[24], $exist_order_sns)) {
                    unset($orders[$key]); //去除已存在的订单
                } else {
                    $pid = $order[30];

                    if (!isset($users[$pid])) {
                        continue;
                    }

                    $add_time = strtotime($order[0]);
                    $total_money = '订单失效' == $order[8] ? 0 : $order[12];
                    if (0 == $users[$pid]['first_order_time']) {
                        $users[$pid]['first_order_time'] = $add_time;
                        M('user')->where('id=' . $users[$pid]['id'])->setField('first_order_time', $add_time);
                    }

                    /*if (isset($orders[$key - 1]) && $order[24] == $orders[$key - 1][24]) {
                        $order_num++; //当前同一订单号的第几单
                    } else {
                        $order_num = 1;
                    }*/

                    $order_num = M('order')->where(array('order_sn' => $order[24]))->count() + 1;

                    if (isset($item_pics[$order[3]])) {
                        $pic_url = $item_pics[$order[3]];
                    } else {
                        $pic_url = '';
                    }

                    //主订单数据
                    $add_order = array(
                        'order_sn'        => $order[24],
                        'order_num'       => $order_num,
                        'num_iid'         => $order[3],
                        'item_num'        => $order[6],
                        'title'           => $order[2],
                        'pic_url'         => $pic_url,
                        'item_cate_name'  => $order[25],
                        'unit_price'      => $order[7],
                        'total_money'     => $total_money,
                        'commission_rate' => floatval($order[10]),
                        'share_rate'      => floatval($order[11]),
                        'commission'      => $order[13],
                        'order_type'      => $order[9],
                        'pay_status'      => $this->order_status[$order[8]],
                        'pid'             => $pid,
                        'user_id'         => $users[$pid]['id'],
                        'partner_id'      => $partner['id'],
                        'add_time'        => $add_time,
                        'settle_time'     => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                    );
                    $order_id = M('order')->add($add_order);

                    /**
                     * 计算子订单
                     * 先计算给自己的分成 然后给上级 然后给团长 最后给团长的团长
                     */
                    $rate = C('COMMISSION_RATE.base_commission_rate');
                    $commission = computed_price($order[13], 2, $rate);
                    $add_order_commissions[] = array( //给自己的分成
                        'order_id' => $order_id,
                        'user_id' => $users[$pid]['id'],
                        'partner_id' => $partner['id'],
                        'order_sn' => $order[24],
                        'order_num' => $order_num,
                        'total_money' => $total_money,
                        'commission' => $commission,
                        'pay_status' => $this->order_status[$order[8]],
                        'zm_subsidy_money' => 0,
                        'source' => 'self',
                        'add_time' => $add_time,
                        'settle_time' => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                    );

                    //给上级的分成
                    $inviter_pid = $users[$pid]['inviter_pid'];
                    if ($users[$pid]['inviter_id'] > 0) {
                        $zm_subsidy_rate = 0; //平台补贴比例
                        if ((time() - $add_time) < C('COMMISSION_RATE.award_days') * 86400) {
                            if (2 == $users[$inviter_pid]['level']) {
                                $zm_subsidy_rate = C('COMMISSION_RATE.v2_award');
                            } else if (3 == $users[$inviter_pid]['level']) {
                                $zm_subsidy_rate = C('COMMISSION_RATE.v3_award');
                            } else if (4 == $users[$inviter_pid]['level']) {
                                $zm_subsidy_rate = C('COMMISSION_RATE.v4_award');
                            }
                        }

                        $commission = computed_price($order[13], 2, C('COMMISSION_RATE.son_commission_rate'));
                        $zm_subsidy_money = computed_price($order[13], 2, $zm_subsidy_rate);
                        $key = 'son' . $order[24] . $order_num;
                        $add_order_commissions[$key] = array(
                            'order_id' => $order_id,
                            'user_id' => $users[$pid]['inviter_id'],
                            'partner_id' => $partner['id'],
                            'order_sn' => $order[24],
                            'order_num' => $order_num,
                            'total_money' => $total_money,
                            'commission' => $commission,
                            'pay_status' => $this->order_status[$order[8]],
                            'zm_subsidy_money' => $zm_subsidy_money,
                            'source' => 'son',
                            'add_time' => $add_time,
                            'settle_time' => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                        );
                    }

                    //给团长的分成
                    if ($users[$pid]['group_leader_id'] > 0) {
                        $commission = computed_price($order[13], 2, C('COMMISSION_RATE.group_leader'));
                        $key = 'group_leader' . $order[24] . $order_num;
                        $add_order_commissions[] = array(
                            'order_id' => $order_id,
                            'user_id' => $users[$pid]['group_leader_id'],
                            'partner_id' => $partner['id'],
                            'order_sn' => $order[24],
                            'order_num' => $order_num,
                            'total_money' => $total_money,
                            'commission' => $commission,
                            'pay_status' => $this->order_status[$order[8]],
                            'zm_subsidy_money' => 0,
                            'source' => 'group_leader',
                            'add_time' => $add_time,
                            'settle_time' => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                        );
                    }

                    //给团长的团长
                    if ($users[$pid]['group_leader_parent_id'] > 0) {
                        $commission = computed_price($order[13], 2, C('COMMISSION_RATE.group_leader_parent'));
                        $add_order_commissions[] = array(
                            'order_id' => $order_id,
                            'user_id' => $users[$pid]['group_leader_parent_id'],
                            'partner_id' => $partner['id'],
                            'order_sn' => $order[24],
                            'order_num' => $order_num,
                            'total_money' => $total_money,
                            'commission' => $commission,
                            'pay_status' => $this->order_status[$order[8]],
                            'zm_subsidy_money' => 0,
                            'source' => 'group_leader',
                            'add_time' => $add_time,
                            'settle_time' => '订单结算' == $order[8] ? strtotime($order[16]) : 0
                        );
                    }
                }
            }

            M('order_commission')->addAll($add_order_commissions);
            if ($model->commit()) {
                return 'success';
            } else {
                throw new \Exception($model->getError());
            }
        } catch (\Exception $e) {
            $model->rollback();
            $this->_addLog('getNewOrder', $partner['partner_name'] . '新增订单异常：' . $e->getMessage() . var_export($add_orders, true) . var_export($add_order_commissions, true));
            return $e->getMessage();
        }
    }

    /**
     * 获取失效单
     */
    public function getFailOrder() {
        $partners = M('partner')->where(array('id' => array('gt', 1), 'status' => 1))->select();
        shuffle($partners);

        foreach ($partners as $partner) {
            $data = array();
            list($_, $_, $site_id, $_) = explode('_', $partner['pid']);
            $start          = date("Y-m-d", strtotime("-18 days"));
            $end            = date("Y-m-d", strtotime("-15 days"));
            $url            = sprintf($this->down_url, '1', '13', $start, $end);
            $filename       = "/home/order_log/partner_order_fail_{$partner['mobile']}.xls";
            $res            = $this->_downFile($url, $filename, $partner);
            $third_url      = sprintf($this->third_down_url, '2', '13', $start, $end);
            $third_filename = "/home/order_log/partner_order_fail_third_{$partner['mobile']}.xls";
            $third_res      = $this->_downFile($third_url, $third_filename, $partner);
            if ($res['code'] == 1) {
                $data = $this->_createData($filename);
            }
            if ($third_res['code'] == 1) {
                $_data = $this->_createData($third_filename);
                $data = array_merge($data, $_data);
            }

            if (!empty($data)) {
                /**
                 * 获取宅喵生活的失效订单，单独保存
                 */
                $zm_orders = array();
                $zm_siteids = array($site_id);
                foreach ($data as $key => $order) {
                    if (in_array($order[26], $zm_siteids)) {
                        $zm_orders[] = $order;
                        unset($data[$key]);
                    }
                }

                if (!empty($zm_orders)) {
                    krsort($zm_orders);
                    $this->_doGetFailOrder($zm_orders, $partner);
                }
            }
        }
    }

    /**
     * 异步获取淘宝失效订单
     */
    public function getFailOrderAsync() {
        $pid = I('request.pid', '', 'trim');
        $content = I('request.content', '', 'trim');

        $goods     = base64_decode(str_replace(array('%2b', ' '), '+', urldecode($content)));
        $goods_arr = @json_decode($goods, true);

        $partner = M('partner')->where(array('pid' => $pid))->find();
        if (empty($partner)) {
            exit('该合作伙伴PID错误');
        }

        if (isset($goods_arr['dingdan'])) {
            $orders = array_map(function($item) {
                return array_values($item);
            }, $goods_arr['dingdan']);

            /**
             * 获取宅喵生活的订单，单独保存
             */
            $zm_orders = array();
            list($_, $_, $site_id, $_) = explode('_', $pid);
            $zm_siteids = array($site_id);
            foreach ($orders as $key => $order) {
                if (in_array($order[26], $zm_siteids)) {
                    $zm_orders[] = $order;
                    unset($orders[$key]);
                }
            }

            if (!empty($zm_orders)) {
                krsort($zm_orders);
                /*$this->_addLog('getFailOrderAsync', var_export($partner, true));
                $this->_addLog('getFailOrderAsync', var_export($zm_orders, true));
                echo '测试返回';
                exit;*/
                echo $this->_doGetFailOrder($zm_orders, $partner);
            } else {
                echo '暂无新的失效订单';
            }
        } else {
            exit('未知错误！');
        }
    }

    /**
     * 获取失效单
     */
    protected function _doGetFailOrder($orders, $partner) {
        if (empty($orders)) {
            return 'empty order';
        }

        //先统一查出所有订单，再匹配看哪些需要失效。然后把订单失效。子订单里根据order_id置为失效
        //根据订单编号以及订单金额，直接把已付款订单置为失效

        $taobao_order_sns = array();
        $format_orders = array(); //把订单做成以订单号为键，以订单为值的多维数组
        foreach ($orders as $key => $order) {
            $taobao_order_sns[] = $order[24];

            if (isset($format_orders[$order[24]])) {
                $format_orders[$order[24]][] = $order;
            } else {
                $format_orders[$order[24]] = array($order);
            }
        }
        unset($orders); //释放内存

        //查找待更新的订单
        $where = array('order_sn' => array('in', $taobao_order_sns), 'pay_status' => 'paid');
        $db_orders = M('order')->where($where)->select();
        $model = new \Think\Model();
        foreach ($db_orders as $db_key => $db_order) {
            foreach ($format_orders[$db_order['order_sn']] as $key => $order) {
                if ($order[3] == $db_order['num_iid'] && $order[6] == $db_order['item_num']) { //商品ID和数量相同
                    $model->startTrans();
                    try {
                        //更新订单状态
                        $save_data = array(
                            'pay_status' => 'fail',
                            'total_money' => 0,
                            'commission' => 0
                        );
                        M('order')->where(array('id' => $db_order['id'], 'pay_status' => 'paid'))->save($save_data);
                        M('order_commission')->where(array('order_id' => $db_order['id'], 'pay_status' => 'paid'))->save(array('pay_status' => 'fail', 'total_money' => 0, 'commission' => 0, 'zm_subsidy_money' => 0));

                        if ($model->commit()) {
                            unset($format_orders[$db_order['order_sn']][$key]);
                            break;
                        } else {
                            throw new \Exception($model->getError());
                        }
                    } catch (\Exception $e) {
                        $model->rollback();
                        $this->_addLog('getFailOrder', $partner['partner_name'] . '失效订单异常：' . $e->getMessage() . var_export($db_order, true));
                        break;
                    }
                }
            }
        }

        return 'success';
    }

    /**
     * 获取结算单
     */
    public function getSettleOrder() {
        $partners = M('partner')->where(array('id' => array('gt', 1), 'status' => 1))->select();
        shuffle($partners);

        foreach ($partners as $partner) {
            $data = array();
            list($_, $_, $site_id, $_) = explode('_', $partner['pid']);
            $start          = date('Y-m-d', strtotime('-1 days'));
            $end            = date('Y-m-d');
            $url            = sprintf($this->down_url, '3', '3', $start, $end);
            $filename       = "/home/order_log/partner_order_end_{$partner['mobile']}.xls";
            $res            = $this->_downFile($url, $filename, $partner);
            $third_url      = sprintf($this->third_down_url, '4', '3', $start, $end);
            $third_filename = "/home/order_log/partner_order_end_third_{$partner['mobile']}.xls";
            $third_res      = $this->_downFile($third_url, $third_filename, $partner);
            if ($res['code'] == 1) {
                $data = array_merge($data, $_data);
            }
            if ($third_res['code'] == 1) {
                $_data = $this->_createData($third_filename);
                $data = array_merge($data, $_data);
            }

            if (!empty($data)) {
                /**
                 * 获取宅喵生活的已结算订单，单独保存
                 */
                $zm_orders = array();
                $zm_siteids = array($site_id);
                foreach ($data as $key => $order) {
                    if (in_array($order[26], $zm_siteids)) {
                        $zm_orders[] = $order;
                        unset($data[$key]);
                    }
                }

                if (!empty($zm_orders)) {
                    krsort($zm_orders);
                    $this->_doGetSettleOrder($zm_orders, $partner);
                }
            }
        }
    }

    /**
     * 异步获取淘宝结算订单
     */
    public function getSettleOrderAsync() {
        $pid = I('request.pid', '', 'trim');
        $content = I('request.content', '', 'trim');

        $goods     = base64_decode(str_replace(array('%2b', ' '), '+', urldecode($content)));
        $goods_arr = @json_decode($goods, true);

        $partner = M('partner')->where(array('pid' => $pid))->find();
        if (empty($partner)) {
            exit('该合作伙伴PID错误');
        }

        if (isset($goods_arr['dingdan'])) {
            $orders = array_map(function($item) {
                return array_values($item);
            }, $goods_arr['dingdan']);

            /**
             * 获取宅喵生活的订单，单独保存
             */
            $zm_orders = array();
            list($_, $_, $site_id, $_) = explode('_', $pid);
            $zm_siteids = array($site_id);
            foreach ($orders as $key => $order) {
                if (in_array($order[26], $zm_siteids)) {
                    $zm_orders[] = $order;
                    unset($orders[$key]);
                }
            }

            if (!empty($zm_orders)) {
                krsort($zm_orders);
                /*$this->_addLog('getSettleOrderAsync', var_export($partner, true));
                $this->_addLog('getSettleOrderAsync', var_export($zm_orders, true));
                echo '测试返回';
                exit;*/
                echo $this->_doGetSettleOrder($zm_orders, $partner);
            } else {
                echo '暂无新的结算订单';
            }
        } else {
            exit('未知错误！');
        }
    }

    /**
     * 执行获取结算单
     */
    protected function _doGetSettleOrder($orders, $partner) {
        if (empty($orders)) {
            return 'empty order';
        }

        //先统一查出所有订单，再匹配看哪些需要结算。然后把主订单结算。子订单里根据order_id置为结算
        //根据订单编号以及订单金额，直接把已付款订单置为结算

        $taobao_order_sns = array();
        $format_orders = array(); //把订单做成以订单号为键，以订单为值的多维数组
        foreach ($orders as $key => $order) {
            $taobao_order_sns[] = $order[24];

            if (isset($format_orders[$order[24]])) {
                $format_orders[$order[24]][] = $order;
            } else {
                $format_orders[$order[24]] = array($order);
            }
        }
        unset($orders); //释放内存

        //查找待更新的订单
        $where = array('order_sn' => array('in', $taobao_order_sns), 'pay_status' => 'paid');
        $db_orders = M('order')->where($where)->select();
        $model = new \Think\Model();
        foreach ($db_orders as $db_key => $db_order) {
            foreach ($format_orders[$db_order['order_sn']] as $key => $order) {
                if ($order[3] == $db_order['num_iid'] && $order[6] == $db_order['item_num']) { //商品ID和数量相同
                    $model->startTrans();
                    try {
                        //更新订单状态
                        $settle_time = strtotime($order[16]);
                        $save_data = array(
                            'pay_status' => 'settle',
                            'settle_time' => $settle_time,
                        );
                        M('order')->where(array('id' => $db_order['id'], 'pay_status' => 'paid'))->save($save_data);
                        M('order_commission')->where(array('order_id' => $db_order['id'], 'pay_status' => 'paid'))->save($save_data);

                        if ($model->commit()) {
                            unset($format_orders[$db_order['order_sn']][$key]);
                            break;
                        } else {
                            throw new \Exception($model->getError());
                        }
                    } catch (\Exception $e) {
                        $model->rollback();
                        $this->_addLog('getSettleOrder', $partner['partner_name'] . '结算订单异常：' . $e->getMessage() . var_export($db_order, true));
                        break;
                    }
                }
            }
        }

        return 'success';
    }

    /**
     * 获取维权单
     * 目前的策略是只要有退款，整个订单就全退。
     */
    public function getRefundOrder() {
        $partners = M('partner')->where(array('id' => array('gt', 1), 'status' => 1))->select();
        shuffle($partners);

        foreach ($partners as $partner) {
            $data = array();
            list($_, $_, $site_id, $_) = explode('_', $partner['pid']);
            $start          = date('Y-m-d', strtotime('-1 days'));
            $end            = date('Y-m-d');
            $url            = sprintf($this->refund_down_url, $start, $end);
            $filename       = "/home/order_log/partner_order_refund_{$partner['mobile']}.xls";
            $res            = $this->_downFile($url, $filename, $partner);
            $third_url      = sprintf($this->third_refund_down_url, $start, $end);
            $third_filename = "/home/order_log/partner_order_refund_third_{$partner['mobile']}.xls";
            $third_res      = $this->_downFile($third_url, $third_filename, $partner);
            if ($res['code'] == 1) {
                $data  = array_merge($data, $_data);
            }
            if ($third_res['code'] == 1) {
                $_data = $this->_createData($third_filename);
                $data  = array_merge($data, $_data);
            }

            if (!empty($data)) {
                krsort($data);
                $this->_doGetRefundOrder($data, $partner);
            }
        }
    }

    /**
     * 异步获取淘宝维权退款订单
     */
    public function getRefundOrderAsync() {
        $pid = I('request.pid', '', 'trim');
        $content = I('request.content', '', 'trim');

        $goods     = base64_decode(str_replace(array('%2b', ' '), '+', urldecode($content)));
        $goods_arr = @json_decode($goods, true);

        $partner = M('partner')->where(array('pid' => $pid))->find();
        if (empty($partner)) {
            exit('该合作伙伴PID错误');
        }

        if (isset($goods_arr['dingdan'])) {
            $orders = array_map(function($item) {
                return array_values($item);
            }, $goods_arr['dingdan']);

            if (!empty($orders)) {
                krsort($orders);
                /*$this->_addLog('getRefundOrderAsync', var_export($partner, true));
                $this->_addLog('getRefundOrderAsync', var_export($orders, true));
                echo '售后测试返回';
                exit;*/
                echo $this->_doGetRefundOrder($orders, $partner);
            } else {
                echo '暂无新的维权退款订单';
            }
        } else {
            exit('未知错误！');
        }
    }

    /**
     * 执行获取维权单
     * 目前的策略是只要有退款，整个订单就全退。
     */
    protected function _doGetRefundOrder($orders, $partner) {
        if (empty($orders)) {
            return 'empty order';
        }

        //先统一查出所有订单，再匹配看哪些需要维权处理。
        //然后把子订单里根据order_id置为退款 子订单有成功处理后主订单退款。

        $taobao_order_sns = array();
        $format_orders = array(); //把订单做成以订单号为键，以订单为值的多维数组
        foreach ($orders as $key => $order) {
            if ('维权成功' != $order[5]) {
                continue;
            }

            $taobao_order_sns[] = $order[0];

            if ($order[0] == $order[1]) {
                $order_num = 1;
            } else {
                $order_num = substr($order[1], 0, -6) - substr($order[0], 0, -6);
            }
            $format_orders[$order[0] . '_' . $order_num] = $order;
        }
        unset($orders); //释放内存

        //查找待更新的订单
        $where = array(
            'order_sn' => array('in', $taobao_order_sns),
            'pay_status' => 'settle',
            'settle_time' => array('gt', time() - C('COMMISSION_RATE.settle_account_days') * 86400)
        );
        $db_orders = M('order')->where($where)->select();
        $model = new \Think\Model();
        foreach ($db_orders as $db_key => $db_order) {
            if (isset($format_orders[$db_order['order_sn'] . '_' . $db_order['order_num']])) {
                $model->startTrans();
                try {
                    $where = array(
                        'order_id' => $db_order['id'],
                        'pay_status' => 'settle',
                        'pay_to_user_time' => 0
                    );
                    $save_data = array(
                        'pay_status' => 'refund',
                        'total_money' => 0,
                        'commission' => 0,
                        'zm_subsidy_money' => 0
                    );
                    $update_num = M('order_commission')->where($where)->save($save_data);
                    if ($update_num > 0) {
                        $where = array(
                            'id' => $db_order['id'],
                            'pay_status' => 'settle',
                        );
                        $save_data = array(
                            'pay_status' => 'refund',
                            'total_money' => 0,
                            'commission' => 0,
                            'refund_time' => strtotime($format_orders[$db_order['order_sn'] . '_' . $db_order['order_num']][8])
                        );
                        M('order')->where($where)->save($save_data);
                    }

                    if (!$model->commit()) {
                        throw new \Exception($model->getError());
                    }
                } catch (\Exception $e) {
                    $model->rollback();
                    $this->_addLog('getRefundOrder', $partner['partner_name'] . '维权订单异常：' . $e->getMessage() . var_export($db_order, true));
                }
            }
        }

        return 'success';
    }

    /**
     * @param $url
     * @param $uid
     * @param $filename
     * @return array
     */
    private function _downFile($url, $filename, $partner) {
        $obj    = new Http();
        $cookie = $this->_getCookie($partner['cookie']);
        $data   = $obj->downFile($url, $cookie['cookie'], $filename);
        if (!file_exists($filename)) {
            if (isset($data['error'])) {
                $error = $data['error'];
            } else {
                $error = '未知';
            }
            $this->_addLog('partner_order_download', '下载订单失败：' . $error);

            return array('code' => -1, 'msg' => '下载订单失败！');
        }

        return array('code' => 1, 'msg' => '下载数据成功！');
    }

    /**
     * @param $filename
     * @return array
     * @throws \PHPExcel_Reader_Exception
     */
    private function _createData($filename) {
        require_once(APP_PATH . "/Common/Org/PHPExcel.class.php");
        require_once(APP_PATH . "/Common/Org/PHPExcel/IOFactory.php");
        $reader   = \PHPExcel_IOFactory::createReader('Excel5');
        $PHPExcel = $reader->load($filename); // 载入excel文件
        $obj      = $PHPExcel->getSheet(0);// 读取第一個工作表
        if ($obj) {
            $data = $obj->toArray();
            $a = array_shift($data);
            if ('技术服务费比率' == $a[19]) {
                foreach ($data as $key => $value) {
                    unset($value[19]);
                    $data[$key] = array_values($value);
                }
            }
            return $data;
        } else {
            return array();
        }
    }

}