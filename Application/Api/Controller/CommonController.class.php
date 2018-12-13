<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/17
 * Time: 11:46
 */

namespace Api\Controller;

use Common\Controller\CommonBaseController;
use Common\Org\TaoBaoApi;
use Common\Org\Http;

/**
 * Class CommonController
 *
 * @package Api\Controller
 */
class CommonController extends CommonBaseController {

    /**
     * 检测用户是否需要登录
     *
     * @var bool
     */
    protected $checkUser = false;


    /**
     * @var int
     */
    protected $user_id = 0;

    /**
     * @var int
     */
    protected $mobile = 0;

    public function __construct() {
        parent::__construct();
        if ($this->checkUser) {
            $this->_checkUser();
        }
    }

    /**
     * 验证函数
     *
     * @access private
     */
    protected function _checkUser() {
        $token = I('request.token', '', 'trim');
        if (empty($token)) {
            $this->output('请登录', 'not_login');
        }
        $user_data = S($token);
        if (!isset($user_data['pdd_pid'])) {
            $user_data = array();
        }
        if (!isset($user_data['jd_pid'])) {
            $user_data = array();
        }
        if (!$user_data) {
            $field     = 'id as user_id,pid,mobile,inviter_pid,level,inviter_id,pdd_pid,inviter_pdd_pid,jd_pid,inviter_jd_pid';
            $user_data = M('user')->field($field)->where(array('token' => $token))->find();
            if ($user_data) {
                S($token, $user_data, 864000);
            }
        }

        if (!$user_data) {
            $this->output('登录失效，请重新登录', 'not_login');
        }

        /**
         * 创建拼多多PID
         */
        if (($user_data['level'] > 0 && empty($user_data['pdd_pid'])) || empty($user_data['inviter_pdd_pid'])) {
            if ($user_data['level'] > 0 && empty($user_data['pdd_pid'])) {
                $create_user_id = $user_data['user_id'];
            } else {
                $create_user_id = $user_data['inviter_id'];
            }

            $create_user = M('user')->where('id=' . $create_user_id)->find();
            if (empty($create_user['pdd_pid'])) {
                $pdd_pid = $this->_pddCreatePid(1);
                if (!empty($pdd_pid)) {
                    M('user')->where('id=' . $create_user_id)->setField('pdd_pid', $pdd_pid);
                    M('user')->where(array('inviter_id' => $create_user_id))->setField('inviter_pdd_pid', $pdd_pid);

                    if ($create_user_id == $user_data['user_id']) {
                        $user_data['pdd_pid'] = $pdd_pid;
                    } else {
                        $user_data['inviter_pdd_pid'] = $pdd_pid;
                    }
                }

                S($token, null);
            } else { //上级已有拼多多PID，更新自己的inviter_pdd_pid
                if ($create_user_id == $user_data['inviter_id'] && empty($user_data['inviter_pdd_pid'])) {
                    M('user')->where('id=' . $user_data['user_id'])->setField('inviter_pdd_pid', $create_user['pdd_pid']);
                    $user_data['inviter_pdd_pid'] = $create_user['pdd_pid'];
                }
            }
        }

        /**
         * 创建jd_pid
         */
        if (($user_data['level'] > 0 && empty($user_data['jd_pid'])) || empty($user_data['inviter_jd_pid'])) {
            if ($user_data['level'] > 0 && empty($user_data['jd_pid'])) {
                $create_user_id = $user_data['user_id'];
            } else {
                $create_user_id = $user_data['inviter_id'];
            }

            $create_user = M('user')->where('id=' . $create_user_id)->find();

            if (empty($create_user['jd_pid'])) {
                $jd_pid = $this->_jdCreatePid($create_user['mobile'] . '_self');

                if (is_numeric($jd_pid)) {
                    M('user')->where('id=' . $create_user_id)->setField('jd_pid', $jd_pid);
                    M('user')->where(array('inviter_id' => $create_user_id))->setField('inviter_jd_pid', $jd_pid);

                    if ($create_user_id == $user_data['user_id']) {
                        $user_data['jd_pid'] = $jd_pid;
                    } else {
                        $user_data['inviter_jd_pid'] = $jd_pid;
                    }
                }

                S($token, null);
            } else { //上级已有京东PID，更新自己的inviter_jd_pid
                if ($create_user_id == $user_data['inviter_id'] && empty($user_data['inviter_jd_pid'])) {
                    M('user')->where('id=' . $user_data['user_id'])->setField('inviter_jd_pid', $create_user['jd_pid']);
                    $user_data['inviter_jd_pid'] = $create_user['jd_pid'];
                }
            }
        }

        $this->user_id = $user_data['user_id'];
        $this->mobile  = $user_data['mobile'];
        if ($user_data['pid']) {
            $this->pid = $user_data['pid'];
        } else {
            if ($user_data['inviter_pid']) {
                $this->pid = $user_data['inviter_pid'];
            }
        }

        if (!empty($user_data['pdd_pid'])) {
            $this->pdd_pid = $user_data['pdd_pid'];
        } else {
            if (!empty($user_data['inviter_pdd_pid'])) {
                $this->pdd_pid = $user_data['inviter_pdd_pid'];
            }
        }

        if (!empty($user_data['jd_pid'])) {
            $this->jd_pid = $user_data['jd_pid'];
        } else {
            if (!empty($user_data['inviter_jd_pid'])) {
                $this->jd_pid = $user_data['inviter_jd_pid'];
            }
        }
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
     * @param string $msg
     * @param string $code
     * @param array $data
     */
    protected function output($msg = '请求错误', $code = 'fail', $data = array()) {
        $json_data = array('code' => $code, 'msg' => $msg, 'data' => $this->_pretreatmentNumericToString($data));
        ob_clean();
        die(json_encode($json_data));
    }

    /**
     * 对数组数据处理，所有数字变成字符串
     */
    private function _pretreatmentNumericToString($string) {
        if (is_array($string)) {
            foreach ($string as $key => $val) {
                $string[$key] = $this->_pretreatmentNumericToString($val);
            }
        } else if (is_numeric($string)) {
            $string = strval($string);
        }
        return $string;
    }

    /**
     * 获取专题详情，含加工后的专题里的商品
     *
     * @param $topic_id
     * @return array
     */
    protected function _getTopicDetail($topic_id) {
        $topic_detail = S('TOPIC_' . $topic_id);
        if (empty($topic_detail)) {
            $topic_detail = M('item_topic')->where('id=' . $topic_id)->find();

            if ($topic_detail['tbk_material_id'] > 0) {
                $obj  = new TaoBaoApi();
                $items = $obj->getTaobaoMaterialItems($topic_detail['tbk_material_id']);
                $topic_detail['items'] = array_values($items);
                S('TOPIC_' . $topic_id, $topic_detail, 36000);

                return $topic_detail;
            }

            $items = json_decode($topic_detail['items'], true);
            $num_iid_data = $item_data = array();
            foreach ($items as $item) {
                $num_iid_data[]              = $item['num_iid'];
                $item_data[$item['num_iid']] = $item;
            }

            $where   = array('num_iid' => array('in', $num_iid_data));
            $order   = "id desc";
            $db_data = $this->_getItemsList($where, $order, 0, count($num_iid_data));
            foreach ($db_data as $item) {
                $item['title']          = isset($item_data[$item['num_iid']]['title']) ? $item_data[$item['num_iid']]['title'] : $item['title'];
                $item['sort']           = isset($item_data[$item['num_iid']]['sort']) ? $item_data[$item['num_iid']]['sort'] : 100;
                $item['data_source']    = 'app'; //数据来源 现在只有淘宝高佣商品
                $data[$item['num_iid']] = $item;
            }
            $data = array_sequence($data, 'sort', 'SORT_ASC');
            $topic_detail['items'] = array_values($data);

            S('TOPIC_' . $topic_id, $topic_detail, 36000);
        }

        if (isset($topic_detail['tbk_material_id']) && $topic_detail['tbk_material_id'] > 0) {
            $obj  = new TaoBaoApi();
            $topic_detail['items'] = $obj->getTaobaoMaterialItems($topic_detail['tbk_material_id']);
        }

        return $topic_detail;
    }

    /**
     * 获取最近10分钟内下单的用户头像和昵称
     */
    protected function _getLatelyBuyUsers() {
        $users = S('lately_buy_users');
        if (!is_array($users)) {
            $user_ids = M('order')->where(array('add_time' => array('gt', time() - 600)))->getField('user_id', true);
            if (!empty($user_ids)) {
                $users = M('user')->where(array('id' => array('in', $user_ids)))->limit(10)->field('mobile,nickname,avatar')->select();
                foreach ($users as $key => $user) {
                    if (empty($user['nickname'])) {
                        $users[$key]['nickname'] = hide_mobile($user['mobile']);
                    }
                    unset($users[$key]['mobile']);
                }
            } else {
                $users = array();
            }

            S('lately_buy_users', $users, 300);
        }
        return $users;
    }
}
