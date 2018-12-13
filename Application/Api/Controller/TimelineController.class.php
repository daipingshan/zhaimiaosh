<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/4/18
 * Time: 10:31
 */

namespace Api\Controller;

/**
 * 宅喵说
 * Class TimelineController
 *
 * @package Api\Controller
 */
class TimelineController extends CommonController {

    /**
     * 获取宅喵说列表
     */
    public function index() {
        $page      = I('get.page', 1, 'int');
        $now = time();
        $where     = array('add_time' => array('elt', $now), 'status' => 1);
        $start_num = ($page - 1) * $this->limit;
        $data      = M('timeline')->where($where)->limit($start_num, $this->limit)->order('add_time desc')->select();
        foreach ($data as &$val) {
            switch ($val['mall_platform']) {
                case 'jingdong':
                    $mall_platform = '京东';
                    break;
                case 'pinduoduo':
                    $mall_platform = '拼多多';
                    break;

                default:
                    $mall_platform = '淘宝';
                    break;
            }

            if (empty($val['tags'])) {
                $val['tags'] = $mall_platform;
            } else {
                $val['tags'] = $mall_platform . '#' . $val['tags'];
            }

            $val['pics']         = json_decode($val['pics'], true);
            $val['coupon_price'] = format_view_money($val['coupon_price']);
            $val['share_user_num'] = $this->_getShareNum($now - $val['add_time']);
        }

        $this->output('ok', 'success', $data);
    }

    /**
     * 获取一个线性增长的数
     * 根据给的数的不同返回不同增长速度的数
     */
    private function _getShareNum($dvalue) {
        if ($dvalue <= 3600) {
            return intval($dvalue * pow(1 + sin($dvalue / 3600), 2));
        } else if ($dvalue <= 86400) {
            //return 3600 * pow(1 + sin(1), 2) * pow(1 + sin($dvalue / 86400), 2);
            return intval(12207 * pow(1 + sin($dvalue / 86400), 2));
        } else {
            $day = $dvalue / 86400;
            //return 3600 * pow(1 + sin(1), 2) * pow(1 + sin(1), 2) * (1 + sin($day / ($day + 3)));
            return intval(41394 * (1 + sin($day / ($day + 3))));
        }
    }

}