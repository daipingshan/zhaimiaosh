<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/17
 * Time: 11:42
 */

namespace Common\Controller;

use Common\Org\AliYunOss as OSS;
use Common\Org\DaTaoKe;
use Common\Org\Http;
use Common\Org\Jos;
use Common\Org\JPush;
use Common\Org\OpenSearch;
use Common\Org\TaoBaoApi;
use Think\Controller;
use Think\Page;

/**
 * Class CommonController
 *
 * @package Common\Controller
 */
class CommonBaseController extends Controller {

    /**
     * @var bool
     */
    protected $openSearchStatus = true;

    /**
     * 默认淘宝联盟PID
     *
     * @var string
     */
    protected $pid = "mm_121610813_22448587_79916379";

    /**
     * 默认拼多多PID
     *
     * @var string
     */
    protected $pdd_pid = "1008327_13060547";

    /**
     * 默认京东PID
     *
     * @var string
     */
    protected $jd_pid = "1311656637";

    /**
     * @var int
     */
    protected $limit = 20;

    /**
     * CommonBaseController constructor.
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * 上传项目图片
     */
    public function uploadImg() {
        $Object = new OSS();
        $data   = $Object->uploadObject();
        if ($data['code'] == 1) {
            $res = array(
                "state"   => "SUCCESS",
                "db_url"  => $data['url'],
                "url"     => get_img_url($data['url']),
                "message" => '上传成功',
                "error"   => 0,
            );
        } else {
            $res = array(
                "state"   => 'ERROR',
                "error"   => 1,
                "url"     => '',
                'db_url'  => '',
                "message" => $data['info'],
            );
        }
        ob_clean();
        die(json_encode($res));
    }

    /**
     * @param $totalRows
     * @param $listRows
     * @param array $map
     * @param int $rollPage
     * @return Page
     */
    protected function pages($totalRows, $listRows, $map = array(), $rollPage = 5) {
        $Page = new Page($totalRows, $listRows, '', MODULE_NAME . '/' . ACTION_NAME);
        if ($map && IS_POST) {
            foreach ($map as $key => $val) {
                $val = urlencode($val);
                $Page->parameter .= "$key=" . urlencode($val) . '&';
            }
        }
        if ($rollPage > 0) {
            $Page->rollPage = $rollPage;
        }
        $Page->setConfig('header', '条信息');
        $Page->setConfig('prev', '上一页');
        $Page->setConfig('next', '下一页');
        $Page->setConfig('first', '首页');
        $Page->setConfig('last', '末页');
        $Page->setConfig(
            'theme', '<div style="float: left"><span>当前页' . $listRows . '条数据 总%TOTAL_ROW% %HEADER%</span></div><div style="float: right"><ul class=pagination><li>%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE%</li></ul></div>'
        );
        return $Page;
    }

    /**
     * 设置某个账户为团长
     *
     * @param $user_id 待设置团长的用户ID
     * @return bool true or error string
     */
    protected function _setGroupLeader($user_id) {
        $upgrade_group_leader_protect_days = C('COMMISSION_RATE.upgrade_group_leader_protect_days'); //团长升级保护天数
        $now                               = time();

        $user = M('user')->find($user_id);
        if ($user['level'] >= 5) {
            return array('status' => 0, 'info' => '该用户已经是团长，请勿重复设置');
        }

        if ($user['level'] < 1) {
            return array('status' => 0, 'info' => '该用户身份不符合升级要求，请勿重复设置');
        }

        /* if ($user['invite_a_num'] < C('COMMISSION_RATE.group_leader_num')) {
             return array('status' => 0, 'info' => '邀请下级数量不够，不能升级该用户为团长');
         }*/

        //首先把自己所有的下级找出来
        //然后把下级里当了团长的找出来
        //下级里排除团长才算是纯下级

        try {
            $model = new \Think\Model();
            $model->startTrans();

            //修改自己为团长
            M('user')->where('id=' . $user_id)->setField(array('level' => 5, 'group_leader_id' => $user_id, 'group_leader_parent_id' => $user['group_leader_id'], 'level_upgrade_time' => $now));

            //所有下级
            $child_users = M('invite')->where('inviter_id=' . $user_id)->getField('user_id', true);

            //所有最近升级的下级团长
            $child_group_leader_where = array(
                'group_leader_parent_id' => $user['group_leader_id'],
                'level'                  => 5,
                'level_upgrade_time'     => array('gt', $now - $upgrade_group_leader_protect_days * 86400)
            );
            $upgrade_group_leaders    = M('user')->where($child_group_leader_where)->getField('id', true);

            //所有下级团长
            $child_group_leaders = array_intersect($child_users, (array)$upgrade_group_leaders);

            //所有下级团长的下级用户
            if (empty($child_group_leaders)) {
                $child_group_leader_children = array();
            } else {
                $child_group_leader_children = M('invite')->where(array('inviter_id' => array('in', $child_group_leaders)))->getField('user_id', true);
            }

            //所有未被自己的下级设置为团长的下级。有可能这批人的团长是自己的上级,也有可能是90天升级团长的下级
            $no_group_leader_child_users = array_diff($child_users, $child_group_leaders, (array)$child_group_leader_children);

            $chunk_child_users = array_chunk($no_group_leader_child_users, 999);

            foreach ($chunk_child_users as $key => $part_child_users) { //一次最多修改999人
                //跟自己一样的，把团长换一下
                M('user')->where(array('id' => array('in', $part_child_users), 'level' => array('lt', 5), 'group_leader_id' => $user['group_leader_id']))->setField(array('group_leader_id' => $user_id, 'group_leader_parent_id' => $user['group_leader_id']));
            }

            //自己的下级团长及下级团长的下级 团长的团长设置为自己
            if (!empty($child_group_leaders)) {
                M('user')->where(array('group_leader_id' => array('in', $child_group_leaders)))->setField('group_leader_parent_id', $user_id);
                M('user')->where(array('id' => array('in', $child_group_leaders)))->setField('group_leader_parent_id', $user_id);
            }
            if ($model->commit()) {
                return array('status' => 0, 'info' => '升级成功');
            } else {
                throw new \Exception($model->getError());
            }
        } catch (\Exception $e) {
            $model->rollback();
            return array('status' => 0, 'info' => $e->getMessage());


        }
    }

    /**
     * 广告数据缓存
     */
    protected function _getAdvert($position = 0, $client_platform = 'app') {
        $data = S('advert');
        if (empty($data)) {
            $list = M('advert')->where(array('status' => 1))->order('sort asc,id desc')->select();
            foreach ($list as $row) {
                $data[$row['position']][] = $row;
            }
            if ($data) {
                S('advert', $data);
            }
        }

        if (isset($data[$position])) {
            $ads = array();
            foreach ($data[$position] as $key => $ad) {
                if ('mini_program' == $client_platform) {
                    if (in_array($ad['client_platform'], array('all', 'mini_program')) ) {
                        $ads[] = $ad;
                    }
                } else {
                    if (in_array($ad['client_platform'], array('all', 'app')) ) {
                        $ads[] = $ad;
                    }
                }
            }
        } else {
            $ads = array();
        }
        return $ads;
    }

    /**
     * 商品分类数据缓存
     */
    protected function _getCate() {
        $data = S('items_cate');
        if (empty($data)) {
            $data = M('items_cate')->where(array('status' => 1))->order('sort asc,id desc')->index('id')->select();
            if ($data) {
                S('items_cate', $data);
            }
        }
        return $data;
    }

    /**
     * @param $where
     * @param $order
     * @param int $start_num
     * @param int $limit
     * @return mixed
     */
    public function _getThirdItemsList($where, $order, $start_num = 0, $limit = 20) {
        $url       = "http://api.taodianke.com/ThirdParty/getItems";
        $obj       = new Http();
        $post_data = array('where' => json_encode($where), 'order' => json_encode($order), 'start_num' => $start_num, 'limit' => $limit);
        $res       = json_decode($obj->post($url, $post_data), true);
        if ($res['status'] == 1) {
            $data = $res['data'];
            foreach ($data as &$val) {
                if ('//' == substr($val['pic_url'], 0, 2)) {
                    $val['pic_url'] = 'https:' . $val['pic_url'];
                }
                $val['commission_rate'] = computed_price($val['commission_rate'] / 100, 1);
                $val['commission']      = computed_price($val['commission']);
                $val['coupon_price']    = format_view_money($val['coupon_price']);
            }
        } else {
            $data = array();
        }
        return $data;
    }

    /**
     * @param $where
     * @param $order
     * @param int $start_num
     * @param int $limit
     * @return mixed
     */
    protected function _getItemsList($where, $order, $start_num = 0, $limit = 20) {
        $field = 'id,num_iid,title,price,coupon_price,pic_url,quan as coupon_money,volume as sale_num,commission_rate,commission,shop_type,coupon_end_time';
        $data  = M('items')->field($field)->where($where)->limit($start_num, $limit)->order($order)->select();
        foreach ($data as &$val) {
            $commission_rate        = $val['commission_rate'] / 100;
            $val['commission_rate'] = computed_price($commission_rate, 1);
            $val['commission']      = computed_price($val['commission'], 2, C('COMMISSION_RATE.base_commission_rate'));
            $val['group_leader_commission'] = computed_price($val['commission'], 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader'));
            $val['price']           = format_view_money($val['price']);
            $val['coupon_price']    = format_view_money($val['coupon_price']);
            $val['data_source']     = 'app';
        }
        return $data;
    }

    /**
     * @param $num_iid
     * @return array|mixed
     */
    public function _getThirdItemDetail($num_iid) {
        $url      = "http://api.taodianke.com/ThirdParty/getItemDetail";
        $obj      = new Http();
        $get_data = array('num_iid' => $num_iid);
        $res      = json_decode($obj->get($url, $get_data), true);
        if (isset($res['data']) && $res['data']) {
            if ('//' == substr($res['data']['pic_url'], 0, 2)) {
                $res['data']['pic_url'] = 'https:' . $res['data']['pic_url'];
            }
            $res['data']['commission_rate'] = computed_price($res['data']['commission_rate'] / 100, 1);
            $res['data']['commission']      = computed_price($res['data']['commission']);
            $res['data']['price']           = format_view_money($res['data']['price']);
            $res['data']['coupon_price']    = format_view_money($res['data']['coupon_price']);
        }
        return $res;
    }

    /**
     * @param $num_iid
     * @return array
     */
    protected function _getItemDetail($num_iid) {
        if (!$num_iid) {
            return array('status' => 0, 'info' => '商品编号不能为空');
        }
        $field = 'id,num_iid,title,price,coupon_price,pic_url,quan as coupon_money,volume as sale_num,commission_rate,commission,dataoke_id,activity_id,click_url,small_images,shop_type,coupon_end_time, desc';
        $info  = M('items')->field($field)->where(array('num_iid' => $num_iid))->find();
        if (empty($info)) {
            M('timeline')->where(array('num_iid' => $num_iid))->setField('status', 0);
            return array('status' => -1, 'info' => '优惠券已被领完，请选择其他商品');
        }

        if ($info['dataoke_id'] > 0) {
            $dtkObj   = new DaTaoKe();
            $item_res = $dtkObj->isItemOnline($info['num_iid']);
        } else {
            $item_res = array('status' => 1, 'data' => array());
        }

        if ($item_res['status'] == 0) {
            M('items')->where(array('num_iid' => $num_iid))->delete();
            M('timeline')->where(array('num_iid' => $num_iid))->setField('status', 0);
            return array('status' => -1, 'info' => '优惠券已被领完，请选择其他商品');
        } else {
            if ($item_res['data']) {
                $save_data   = $item_res['data'];
                $coupon_type = $info['coupon_type'];
                if ($coupon_type == 4 && $save_data['coupon_price'] > 9.9) {
                    $save_data['coupon_type'] = 1;
                }
                $save_data['last_query_dataoke_time'] = time();
                M('items')->where(array('num_iid' => $num_iid))->save($save_data);
            }

            $info['group_leader_commission'] = computed_price($info['commission'], 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader'));
            $info['commission_rate']         = computed_price($info['commission_rate'] / 100, 1);
            $info['commission']              = computed_price($info['commission'], 2, C('COMMISSION_RATE.base_commission_rate'));
            $info['price']                   = format_view_money($info['price']);
            $info['coupon_price']            = format_view_money($info['coupon_price']);

            if (empty($info['small_images'])) {
                $info['small_images'] = $this->_getItemSmallImage($num_iid);
            } else {
                $info['small_images'] = json_decode($info['small_images'], true);
            }

            if (empty($info['desc'])) {
                $info['desc_images'] = array();
            } else {
                $info['desc_images'] = json_decode($info['desc'], true);
            }

            return array('status' => 1, 'info' => 'ok', 'data' => $info);
        }
    }

    /**
     * 获取搜索服务中的商品
     *
     * @param        $query_content
     * @param        $sort
     * @param null $filter
     * @param int $start_num
     * @param int $limit
     * @return array
     */
    protected function _getOpenSearchList($query_content, $sort, $filter = null, $start_num = 0, $limit = 20) {
        $query = "pass:'1'";
        if ($query_content) {
            $keyword = $query_content . ' AND ' . $query;
        } else {
            $keyword = $query;
        }
        $obj = new OpenSearch();
        $res = $obj->search($keyword, $sort, $filter, $start_num, $limit);
        if ($res['status'] == 'OK') {
            $data     = array();
            $res_data = $res['data'];
            foreach ($res_data as $key => &$val) {
                $data[] = array(
                    'id'              => $val['id'],
                    'num_iid'         => $val['num_iid'],
                    'cate_id'         => $val['cate_id'],
                    'title'           => $val['title'],
                    'price'           => format_view_money($val['price']),
                    'coupon_price'    => format_view_money($val['coupon_price']),
                    'pic_url'         => $val['pic_url'],
                    'coupon_money'    => format_view_money($val['quan']),
                    'sale_num'        => $val['volume'],
                    'coupon_end_time' => $val['coupon_end_time'],
                    'commission_rate' => computed_price($val['commission_rate'] / 100, 1),
                    'commission'      => computed_price($val['commission'], 2, C('COMMISSION_RATE.base_commission_rate')),
                    'group_leader_commission' => computed_price($val['commission'], 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader')),
                    'shop_type'       => $val['shop_type'],
                    'data_source'     => 'app'
                );
                if ('//' == substr($val['pic_url'], 0, 2)) {
                    $data[]['pic_url'] = 'https:' . $val['pic_url'];
                }
            }
            return $data;
        } else {
            $this->_addLog('OpenSearch', var_export($res, true));
            return array();
        }
    }

    /**
     * 获取搜索服务中的商品
     *
     * @param        $query_content
     * @param null $filter
     * @return int
     */
    protected function _getOpenSearchCount($query_content, $filter = null) {
        $query = "(shop_type:'B' OR shop_type:'C') AND pass:'1'";
        if ($query_content) {
            $keyword = $query_content . ' AND ' . $query;
        } else {
            $keyword = $query;
        }
        $obj = new OpenSearch();
        return $obj->searchCount($keyword, $filter);
    }

    /**
     * 获取全网数据
     *
     * @param $keyword
     * @param $page
     * @param $sort
     * @param int $limit
     * @return array
     */
    protected function _getTaoBaoSearch($keyword, $page, $sort, $limit = 20) {
        /*  $second = date('s') % 2;
          if ($second == 1) {
              $obj  = new TaoBaoApi();
              $res  = $obj->search($keyword, $sort, $page, $limit);
              $data = $res['data'] ? $res['data'] : array();
          } else {
              $data = $this->_getTDKTaoBaoSearch($keyword, $page, $sort, $limit);
              foreach ($data as &$val) {
                  $val['group_leader_commission'] = computed_price($val['commission'], 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader'));
                  $val['commission']              = computed_price($val['commission'], 2, C('COMMISSION_RATE.base_commission_rate'));
                  $val['coupon_price']            = format_view_money($val['coupon_price']);
                  $val['data_source']             = 'all';
              }
          }*/
        $obj  = new TaoBaoApi();
        $res  = $obj->search($keyword, $sort, $page, $limit);
        $data = $res['data'] ? $res['data'] : array();
        return $data;
    }

    /**
     * @param $keyword
     * @param $page
     * @param $sort
     * @param int $limit
     * @return mixed
     */
    protected function _getTDKTaoBaoSearch($keyword, $page, $sort, $limit = 20) {
        $url     = "http://juan.taodianke.com/AppApi/ThirdParty/search";
        $param   = array('keyword' => $keyword, 'page' => $page, 'sort' => $sort, 'limit' => $limit);
        $curlObj = new Http();
        $res     = $curlObj->get($url, $param);
        $res     = json_decode($res, true);
        return $res['data'] ? $res['data'] : array();
    }

    /**
     * @param $item
     * @param $pid
     * @return array
     */
    protected function _applyHigh($item, $pid) {
        if ('' != $item['activity_id']) {
            $activity_id = $item['activity_id'];
        } else {
            if (strpos($item['click_url'], 'activityId')) {
                $activity      = explode('=', $item['click_url']);
                $activity_temp = explode('&', $activity[1]);
                $activity_id   = $activity_temp[0];
            } else {
                $activity_id = '';
            }
        }
        $httpObj  = new Http();
        $pid_info = explode('_', $pid);

        $tao_bao_access_token = '';
        $partner              = $this->_getPartnerInfo($pid);
        if (isset($partner['taobao_access_token'])) {
            $tao_bao_access_token = $partner['taobao_access_token'];
        } else {
            $tao_bao_access_token = C('BASE.tao_bao_access_token');
        }

        if (empty($tao_bao_access_token)) {
            return array('status' => 0, 'info' => '联盟token失效');
        }
        $url       = 'http://tbapi.00o.cn/highapi.php';
        $post_data = array(
            'item_id'   => $item['num_iid'],
            'adzone_id' => $pid_info[3],
            'platform'  => 1,
            'site_id'   => $pid_info[2],
            'token'     => $tao_bao_access_token,
        );
        $res       = $httpObj->post($url, $post_data);

        $res = json_decode($res, true);
        if ($res && $res['result']['data']['coupon_click_url']) {
            if ($activity_id) {
                $click_url = $res['result']['data']['coupon_click_url'] . '&activityId=' . $activity_id . '&pid=' . $pid . '&itemId=' . $item['num_iid'];
            } else {
                $click_url = $res['result']['data']['coupon_click_url'];
            }
            return array('status' => 1, 'info' => 'ok', 'data' => array('buy_url' => $click_url));
        } else {
            $log       = '时间：' . date('Y-m-d H:i:s') . "\n请求参数：" . var_export($post_data, true) . "\n迷离返回结果：" . $res;
            $this->_addLog('mi_li', $log);
            return array('status' => 0, 'info' => $res['msg']);
        }
    }

    /**
     * 淘口令转换
     *
     * @param $url
     * @param $title
     * @param $pic_url
     * @return array
     */
    protected function _getPass($url, $title, $pic_url) {
        $obj = new TaoBaoApi();
        $res = $obj->getApiPass($url, $title, $pic_url);
        return $res;
    }

    /**
     * @param $alert
     * @param $data
     * @param string $push
     * @param string $platform
     * @param string $type
     * @param int $time_to_live
     * @return array
     */
    protected function _sendPush($alert, $data, $push, $platform = 'all', $type = 'all', $time_to_live = 86400) {
        $obj = new JPush();
        $res = $obj->push($alert, $data, $push, $platform, $type, $time_to_live);
        return $res;
    }

    /**
     * @param $pid
     * @return array
     */
    protected function _getCookie($cookie) {
        $t          = array(' ', '　', '', '', '');
        $p          = array("", "", "", "", "");
        $cookie     = str_replace($t, $p, $cookie);
        $cookie     = $cookie . ';';
        $token      = get_word($cookie, '_tb_token_=', ';');
        $temp_one   = get_word($cookie, 't=', ';');
        $temp_two   = get_word($cookie, 'cna=', ';');
        $temp_three = get_word($cookie, 'l=', ';');
        $temp_four  = get_word($cookie, 'isg=', ';');
        $temp_five  = get_word($cookie, 'mm-guidance3', ';');
        $temp_six   = get_word($cookie, '_umdata=', ';');
        $temp_seven = get_word($cookie, 'cookie2=', ';');
        $temp_eight = get_word($cookie, 'cookie32=', ';');
        $temp_nice  = get_word($cookie, 'cookie31=', ';');
        $temp_pass  = get_word($cookie, 'alimamapwag=', ';');
        $temp_login = get_word($cookie, 'login=', ';');
        $temp_pw    = get_word($cookie, 'alimamapw=', ';');
        $cookie     = 't=' . $temp_one . ';cna=' . $temp_two . ';l=' . $temp_three . ';isg=' . $temp_four . ';mm-guidance3=' . $temp_five . ';_umdata=' . $temp_six . ';cookie2=' . $temp_seven . ';_tb_token_=' . $token . ';v=0;cookie32=' . $temp_eight . ';cookie31=' . $temp_nice . ';alimamapwag=' . $temp_pass . ';login=' . $temp_login . ';alimamapw=' . $temp_pw;
        $data       = array(
            'token'  => $token,
            'cookie' => $cookie,
        );
        return $data;
    }

    /**
     * 获取分词后的字符串。获得词频最高的前5个词
     *
     * @param $str
     * @return string
     */
    protected function _getTagsStr($str) {
        if (empty($str)) {
            return $str;
        }

        if (mb_strlen($str) > 5) { //大于5个字才做分词
            $Obj  = new \Common\Org\Participle();
            $path = APP_PATH . "Common/Org/Participle";
            $Obj->set_dict($path . '/dict.utf8.xdb');
            $Obj->set_rule($path . '/rules.utf8.ini');
            $Obj->set_ignore(true);
            $Obj->send_text($str);
            $words = $Obj->get_tops(5);
            $Obj->close();
            $tags = array();
            foreach ($words as $val) {
                $tags[] = $val['word'];
            }
        } else {
            $tags = array($str);
        }

        return implode('', $tags);
    }

    /**
     * 记录日志
     *
     * @param $file_name
     * @param $data 日志内容，兼容普通变量和字符串
     */
    protected function _addLog($file_name, $data) {
        $now  = date('[ c ] ');
        $path = "/data/log/www.zhaimiaosh.com/{$file_name}" . date('Ymd') . ".log";

        if (!is_string($data)) {
            $data = var_export($data, true);
        }

        file_put_contents($path, $now . $data . "\r\n", FILE_APPEND);
    }

    /**
     * @param $num_iid
     * @return array
     */
    public function _getItemsDesc($num_iid, $type = 'app') {
        if (empty($num_iid)) {
            return array();
        }
        if ($type == 'app') {
            $img_data = M('items')->where(array('num_iid' => $num_iid))->getField('desc');
            if (!empty($img_data)) {
                return json_decode($img_data, true);
            }
        }
        $obj      = new TaoBaoApi();
        $img_data = $obj->getDesc($num_iid);
        foreach ($img_data as &$val) {
            if (strpos($val, 'http') === false) {
                $val = "https:" . $val;
            }
        }
        if (!empty($img_data) && $type == 'app') {
            $count = M('items')->where(array('num_iid' => $num_iid))->count('id');
            if ($count > 0) {
                M('items')->where(array('num_iid' => $num_iid))->save(array('desc' => json_encode($img_data)));
            }
        }
        return $img_data;
    }

    /**
     * @param $num_iid
     * @return array
     */
    protected function _getItemSmallImage($num_iid, $type = 'app') {
        if (empty($num_iid)) {
            return array();
        }
        $obj      = new TaoBaoApi();
        $img_data = $obj->getSmallImages($num_iid);
        if (!empty($img_data)) {
            if (!is_array($img_data)) {
                $img_data = array($img_data);
            }
        } else {
            $img_data = array();
        }
        foreach ($img_data as &$val) {
            if (strpos($val, 'http') === false) {
                $val = "https:" . $val;
            }
        }
        if (!empty($img_data) && $type == 'app') {
            M('items')->where(array('num_iid' => $num_iid))->save(array('small_images' => json_encode($img_data)));
        }
        return $img_data;
    }

    /**
     * 获取京东商品的图文详情图片和轮播图
     * @param $num_iid
     * @return mixed
     */
    protected function _getJdItemInfo($num_iid) {
        $jd_detail = S('jd_detail_' . $num_iid);
        if (false === $jd_detail) {
            $url      = "https://item.m.jd.com/ware/detail.json";
            $obj      = new Http();
            $get_data = array('wareId' => $num_iid);
            $jd_detail      = json_decode($obj->get($url, $get_data), true);

            //查出来数据，缓存10天。查不出来缓存1天，一天后重新请求
            if (isset($jd_detail['ware'])) {
                S('jd_detail_' . $num_iid, $jd_detail, 864000);
            } else {
                S('jd_detail_' . $num_iid, array(), 86400);
            }
        }

        //图片详情图片
        $desc_images    = array();
        $content = isset($jd_detail['ware']['wdisHtml']) ? $jd_detail['ware']['wdisHtml'] : '';
        if (!empty($content)) {
            $pattern='/<img\s+src=[\'"]([^\'"]*)[\'"]>??/i';
            preg_match_all($pattern,$content,$intro_images);
            if (isset($intro_images[1]) && !empty($intro_images[1])) {
                foreach ($intro_images[1] as $val) {
                    if (stripos($val,'http')===false){
                        $desc_images[] = 'https:'.$val;
                    }else{
                        $desc_images[] = $val;
                    }
                }
            }
        }

        //轮播图图片
        $small_images = array(); //正常大小图片
        $thumbnail_images = array(); //缩略图
        if (isset($jd_detail['ware']['images'])) {
            foreach($jd_detail['ware']['images'] as $val) {
                $small_images[]     = $val['bigpath'];
                $thumbnail_images[] = $val['newpath'];
            }
        }
        return array('desc_images'=>$desc_images,'small_images'=>$small_images, 'thumbnail_images' => $thumbnail_images);
    }

    /**
     * @param $site_id
     * @param $mobile
     * @return array
     */
    protected function _createPid($pid, $mobile) {
        $return_data = array('status' => 0, 'info' => '');
        list($_, $taobao_union_id, $site_id, $_) = explode('_', $pid);
        $cookie = S('partner_cookie' . $taobao_union_id);
        if (empty($cookie)) {
            $return_data['info'] = "cookie为空，无法创建";
            return $return_data;
        }
        $url    = "http://pub.alimama.com/common/adzone/selfAdzoneCreate.json";
        $params = array(
            'tag'           => 29,
            'gcid'          => 8,
            'siteid'        => $site_id,
            'selectact'     => 'add',
            'newadzonename' => '宅喵A' . $mobile,
            't'             => time() * 1000,
            '_tb_token_'    => get_word($cookie, '_tb_token_=', ';'),
            'pv_id'         => '10_' . get_client_ip() . '_333_' . time() * 1000,
        );
        $res    = $this->_post($url, $params, $cookie);
        $res    = json_decode($res, true);
        if ($res['ok'] == false) {
            //$return_data['info'] = $res['info']['message'];
            $return_data['info'] = var_export($res, true);
        } else {
            $return_data['status'] = 1;
            $return_data['pid']    = "mm_{$taobao_union_id}_" . $res['data']['siteId'] . "_" . $res['data']['adzoneId'];
        }
        return $return_data;
    }

    /**
     * @param string $url
     * @param array $params
     * @param $cookie
     * @return bool|mixed
     */
    protected function _post($url = '', $params = array(), $cookie) {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== false) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1);
        }
        $header = array(
            'Content-Type:application/x-www-form-urlencoded; charset=UTF-8',
            'Referer: http://pub.alimama.com/',
            'X-Requested-With:XMLHttpRequest',
        );
        if ($cookie) {
            $header[] = "cookie:{$cookie}";
        }
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_POST, 1);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($oCurl, CURLOPT_TIMEOUT, 36);
        $sContent = curl_exec($oCurl);
        $aStatus  = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }

    /**
     * 查询拼多多商品列表
     *
     * @param $params 查询参数
     * @param $return_total_count 是否返回查询数据的总数
     *
     * @return array
     */
    protected function _pddGoodsSearch($params, $return_total_count = false) {
        $business_params = array(
            'type' => 'pdd.ddk.goods.search', //API接口名称
            'keyword' => '', //选填 商品关键词，与category_id字段选填一个或全部填写
            'category_id' => '', //选填商品类目id，一级类目ID
            'page' => 1, //选填 分页
            'page_size' => 20, //选填 默认100，每页商品数量
            'sort_type' => 0, //排序方式：0-综合排序；1-按佣金比率升序；2-按佣金比例降序；3-按价格升序；4-按价格降序；5-按销量升序；6-按销量降序
            'with_coupon' => 'true', //是否只返回优惠券的商品，false返回所有商品，ture只返回有优惠券的商品
        );
        $res = $this->_getPinduoduoData(array_merge($business_params, $params));

        if (isset($res['goods_search_response']['goods_list'])) {
            $items = array();
            foreach ($res['goods_search_response']['goods_list'] as $key => $goods) {
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
                    'snum' => $goods['coupon_remain_quantity'],
                    'lnum' => $goods['coupon_total_quantity'] - $goods['coupon_remain_quantity'],
                    'sale_num' => $goods['sold_quantity'],
                    'commission_rate' => computed_price($goods['promotion_rate'] / 10, 1, C('COMMISSION_RATE.base_commission_rate'), 'pinduoduo'),
                    'commission' => $commission,
                    'group_leader_commission' => $group_leader_commission,
                    'shop_type' => 'PDD',
                    'cate_id' => $goods['category_id'],
                    'coupon_end_time' => $goods['coupon_end_time'],
                    'data_source'     => 'pinduoduo'
                );
            }

            if (true === $return_total_count) {
                return array('item_list' => $items, 'total_count' => $res['goods_search_response']['total_count']);
            } else {
                return $items;
            }
        } else {
            $this->_addLog('pdd_goods_search', '获取商品异常' . var_export($res, true));
            if (true === $return_total_count) {
                return array('item_list' => array(), 'total_count' => 0);
            } else {
                return array();
            }
        }
    }

    /**
     * 查询拼多多商品详情
     */
    protected function _pddGoodsDetail($id) {
        $business_params = array(
            'type' => 'pdd.ddk.goods.detail', //API接口名称
            'goods_id_list' => '[' . $id . ']', //商品ID，仅支持单个查询。例如：[123456]
        );

        $res = $this->_getPinduoduoData($business_params);
        if (isset($res['goods_detail_response']) && !empty($res['goods_detail_response']['goods_details'])) {
            $goods = array_shift($res['goods_detail_response']['goods_details']);
            $after_coupon_price = ($goods['min_group_price'] - $goods['coupon_discount']) / 100;
            $commission = computed_price($after_coupon_price * $goods['promotion_rate'] / 1000, 2, C('COMMISSION_RATE.base_commission_rate'), 'pinduoduo');
            $group_leader_commission = computed_price($after_coupon_price * $goods['promotion_rate'] / 1000, 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader'), 'pinduoduo');

            return array(
                'num_iid' => $goods['goods_id'],
                'title' => $goods['goods_name'],
                'intro' => $goods['goods_desc'],
                'price' => format_view_money($goods['min_group_price'] / 100),
                'coupon_price' => format_view_money($after_coupon_price),
                'pic_url' => format_url_to_https($goods['goods_image_url']),
                'small_images' => format_url_to_https($goods['goods_gallery_urls']),
                'coupon_money' => format_view_money($goods['coupon_discount'] / 100),
                'snum' => $goods['coupon_remain_quantity'],
                'lnum' => $goods['coupon_total_quantity'] - $goods['coupon_remain_quantity'],
                'sale_num' => $goods['sold_quantity'],
                'commission_rate' => computed_price($goods['promotion_rate'] / 10, 1, C('COMMISSION_RATE.base_commission_rate'), 'pinduoduo'),
                'commission' => $commission,
                'group_leader_commission' => $group_leader_commission,
                'dataoke_id' => 0,
                'activity_id' => '',
                'tao_kou_ling' => '',
                'shop_type' => 'PDD',
                'coupon_start_time' => $goods['coupon_start_time'],
                'coupon_end_time' => $goods['coupon_end_time'],
                'desc_images' => array(),
                'pinduoduo_info' => array('pdd_mini_program_path' => ''),
                'jingdong_info' => array('jd_mini_program_path' => '')
            );
        } else {
            return array();
        }
    }

    /**
     * 查询拼多多商品推广链接
     */
    protected function _pddGoodsPromotionUrl($pid, $goods_id) {
        $business_params = array(
            'type' => 'pdd.ddk.goods.promotion.url.generate', //API接口名称
            'p_id' => $pid, //推广位PID
            'goods_id_list' => '[' . $goods_id . ']', //商品ID，仅支持单个查询。例如：[123456]
            'generate_short_url' => 'true', //是否生成短链接，true-是，false-否
            'page' => 1,
            'page_size' => 20,
        );

        $res = $this->_getPinduoduoData($business_params);

        if (isset($res['goods_promotion_url_generate_response']['goods_promotion_url_list']) && !empty($res['goods_promotion_url_generate_response']['goods_promotion_url_list'])) {
            return array_shift($res['goods_promotion_url_generate_response']['goods_promotion_url_list']);
        } else {
            return array();
        }
    }

    /**
     * 查询拼多多商城推广链接
     */
    protected function _pddPromotionMallUrl($pid) {
        $business_params = array(
            'type' => 'pdd.ddk.cms.prom.url.generate', //API接口名称
            'generate_short_url' => 'true', //是否生成短链接
            'p_id_list' => '["' . $pid . '"]', //PID
        );

        $res = $this->_getPinduoduoData($business_params);

        if (isset($res['cms_promotion_url_generate_response']['url_list'])) {
            $info = array_shift($res['cms_promotion_url_generate_response']['url_list']);
            return $info['short_url'];
        } else {
            return '';
        }
    }

    /**
     * 创建京东推广位
     * @param $mobile
     * @throws \Think\Exception
     */
    protected function _jdCreatePid($mobile){
        $jos = new Jos();
        $res = $jos->createPromotion($mobile);
        $pid = '';
        if (isset($res['code']) && $res['code']==200 && isset($res['data']['resultList'])){
            $pid = $res['data']['resultList'][$jos->prefix.$mobile];
            return $pid;
        } else {
            $this->_addLog('jd_create_pid', var_export($res, true));
            return 'ERROR:' . var_export($res, true);
        }
    }

    /**
     * 创建推广位PID
     * @param $number 要生成的推广位数量，范围为：1~100 为1时返回PID字符串，其他返回数组
     */
    protected function _pddCreatePid($number = 1) {
        $business_params = array(
            'type' => 'pdd.ddk.goods.pid.generate', //API接口名称
            'number' => $number, //要生成的推广位数量，默认为10，范围为：1~100
        );

        $pid_data = $this->_getPinduoduoData($business_params);
        if (isset($pid_data['p_id_generate_response']['p_id_list']) && !empty($pid_data['p_id_generate_response']['p_id_list'])) {
            if (1 == $number) {
                return $pid_data['p_id_generate_response']['p_id_list'][0]['p_id'];
            } else {
                $pids = array();
                foreach ($pid_data['p_id_generate_response']['p_id_list'] as $value) {
                    $pids[] = $value['p_id'];
                }
                return $pids;
            }
        } else {
            if (1 == $number) {
                return '';
            } else {
                return array();
            }
        }
    }

    /**
     * 查询拼多多已经生成的推广位信息
     */
    protected function _getPddPids($params) {
        $business_params = array(
            'type' => 'pdd.ddk.goods.pid.query', //API接口名称
            'page' => 1,
            'page_size' => 20,
        );

        return $this->_getPinduoduoData(array_merge($business_params, $params));
    }

    /**
     * 获取推广返利订单
     * @param $params 查询参数
     * @param $return_total_count 是否返回查询数据的总数
     */
    protected function _getPddOrderList($params, $return_total_count = false) {
        $business_params = array(
            'type' => 'pdd.ddk.order.list.get', //API接口名称
            //'p_id' => '', //选填 推广位PID，不填表示返回所有订单
            'date' => '', //订单完成时间，格式为：YYYY-MM-DD，返回近90天的订单数据
            'page' => 1,
            'page_size' => 100, //返回的每页结果数，默认为20，范围为10到100
        );

        $res = $this->_getPinduoduoData(array_merge($business_params, $params));

        if (isset($res['order_list_get_response'])) {
            if (true === $return_total_count) {
                return $res['order_list_get_response'];
            } else {
                return $res['order_list_get_response']['order_list'];
            }
        } else {
            $this->_addLog('pdd_order_list', '获取订单异常' . var_export($res, true));

            if (true === $return_total_count) {
                return array('order_list' => array(), 'total_count' => 0);
            } else {
                return array();
            }
        }
    }

    /**
     * 以增量方式获取推广返利订单
     *
     * @param $params 查询参数
     * @param $return_total_count 是否返回查询数据的总数
     */
    protected function _getPddOrderListIncrement($params, $return_total_count = false) {
        $business_params = array(
            'type' => 'pdd.ddk.order.list.increment.get', //API接口名称
            //'p_id' => '', //选填 推广位PID，不填表示返回所有订单
            'start_update_time' => 0, //最近90天内多多进宝商品订单更新时间--查询时间开始
            'end_update_time' => 0, //最近90天内多多进宝商品订单更新时间--查询时间结束
            'page' => 1,
            'page_size' => 50, //返回的每页结果数，默认为20，范围为10到100
        );

        $res = $this->_getPinduoduoData(array_merge($business_params, $params));

        if (isset($res['order_list_get_response'])) {
            if (true === $return_total_count) {
                return $res['order_list_get_response'];
            } else {
                return $res['order_list_get_response']['order_list'];
            }
        } else {
            $this->_addLog('pdd_order_list', '获取订单异常' . var_export($res, true));

            if (true === $return_total_count) {
                return array('order_list' => array(), 'total_count' => 0);
            } else {
                return array();
            }
        }
    }

    /**
     * 获取拼多多数据
     */
    protected function _getPinduoduoData($business_params) {
        $obj  = new Http();
        $url  = 'http://gw-api.pinduoduo.com/api/router';
        $base_params = array(
            'client_id' => C('BASE.pinduoduo_client_id'), //POP分配给应用的client_id
            //'access_token' => '',
            'timestamp' => time(),
            'data_type' => 'JSON', //返回格式，默认JSON，选填
            'version' => 'V1', //API协议版本号。默认为V1，可不填
        );

        $params = array_merge($base_params, $business_params);

        /**
         * 获取sign值
         */
        $client_secret = C('BASE.pinduoduo_client_secret');
        ksort($params);
        $sign = $client_secret;
        foreach ($params as $k => $v) {
            $sign .= $k . $v;
        }
        $sign .= $client_secret;
        $params['sign'] = strtoupper(md5($sign));

        return json_decode($obj->post($url, $params), true);
    }

    /**
     * 查询京推推（京东）商品列表
     *
     * @param $params array 查询参数
     * @param $return_total_count boolean 是否返回查询数据的总数
     *
     * @return array
     */
    protected function _jttGoodsSearch($params, $return_total_page = false) {
        $business_params = array(
            'appid' => C('BASE.jingtuitui_app_id'),
            'appkey' => C('BASE.jingtuitui_app_key'),
            'page' => 1,
            'num' => 20,
            'so' => '', //商品搜索：商品名称/商品SKU
            //'type' => 1, //商品分类
            //'rank' => 'sift' //商品筛选： self:京东自营; sift:精选好货；wtype:京东配送产品；finally:券后价最低； brokerage:佣金比例最高；sale:优惠最多；
        );

        $http      = new Http();
        $post_data = array_merge($business_params, $params);
        $res       = json_decode($http->post('http://japi2.jingtuitui.com/api/get_goods_list', $post_data), true);
        if (isset($res['result']['data'])) {
            $items = array();
            foreach ($res['result']['data'] as $key => $goods) {
                $after_coupon_price = $goods['coupon_price'];
                $commission = computed_price($after_coupon_price * $goods['commission'] / 100, 2, C('COMMISSION_RATE.base_commission_rate'), 'jingdong');
                $group_leader_commission = computed_price($after_coupon_price * $goods['commission'] / 100, 2, C('COMMISSION_RATE.base_commission_rate') + C('COMMISSION_RATE.group_leader'), 'jingdong');
                $items[] = array(
                    'num_iid' => $goods['goods_id'],
                    'title' => $goods['goods_name'],
                    'intro' => $goods['goods_content'],
                    'price' => format_view_money($goods['goods_price']),
                    'coupon_price' => format_view_money($after_coupon_price),
                    'pic_url' => format_url_to_https($goods['goods_img']),
                    'coupon_money' => format_view_money($goods['discount_price']),
                    'click_url' => $goods['discount_link'], //领券地址
                    'snum' => 0, // mt_rand(500, 2000),
                    'lnum' => 0, //mt_rand(500, 2000),
                    'sale_num' => 0, // mt_rand(1, 1000000 / $goods['goods_price']),
                    'commission_rate' => computed_price($goods['commission'], 1, C('COMMISSION_RATE.base_commission_rate'), 'jingdong'),
                    'commission' => $commission,
                    'group_leader_commission' => $group_leader_commission,
                    'shop_type' => 'JD',
                    'cate_id' => isset($post_data['type']) ? $post_data['type'] :0,
                    'coupon_end_time' => $goods['discount_end'] / 1000,
                    'data_source'     => 'jingdong'
                );
            }

            if (true === $return_total_page) {
                return array('item_list' => $items, 'total_page' => $res['result']['total_page']);
            } else {
                return $items;
            }
        } else {
            return array();
        }
    }

    /**
     * 查询京推推（京东）商品详情
     */
    protected function _jttGoodsDetail($id, $pid) {
        $params = array(
            'so' => $id
        );
        $items = $this->_jttGoodsSearch($params);

        if (empty($items)) {
            return array();
        } else {
            $item = array();
            foreach ($items as $key => $value) {
                if ($id == $value['num_iid']) {
                    $item = $value;
                }
            }

            if (empty($item)) {
                return array();
            }

            $business_params = array(
                'appid' => C('BASE.jingtuitui_app_id'),
                'appkey' => C('BASE.jingtuitui_app_key'),
                'unionid' => C('BASE.jingdong_union_id'),
                'positionid' => $pid,
                'gid' => $id,
                'coupon_url' => $item['click_url']
            );

            $http = new Http();
            $res  = json_decode($http->post('http://japi2.jingtuitui.com/api/get_goods_link', $business_params), true);

            if (isset($res['result']['link'])) {
                $jd_mini_program_path = 'pages/jingfen_twotoone/item?spreadUrl=' . urlencode($res['result']['link']) . '&customerinfo=JtuiTui';
                $item['small_images'] = format_url_to_https(array($item['pic_url']));
                $item['desc_images'] = array();
                $item['dataoke_id'] = 0;
                $item['activity_id'] = '';
                $item['tao_kou_ling'] = '';
                $item['buy_url'] = $item['share_url'] = $res['result']['link'];
                $item['jingdong_info'] = array('jd_mini_program_path' => $jd_mini_program_path);
                $item['pinduoduo_info'] = array('pdd_mini_program_path' => '');

                return $item;
            } else {
                return array();
            }
        }
    }

    /**
     * 获取小程序的access token
     */
    protected function _getMiniProgramAccessToken() {
        $access_token = S('mini_program_access_token');
        if (empty($access_token)) {
            $appid = C('BASE.mini_program_appid');
            $secret = C('BASE.mini_program_secret');
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
            $res = file_get_contents($url);
            $access_token_info = json_decode($res, true);
            if (isset($access_token_info['access_token'])) {
                S('mini_program_access_token', $access_token_info['access_token'], $access_token_info['expires_in'] - 10);
                $access_token = $access_token_info['access_token'];
            } else {
                return '';
            }
        }

        return $access_token;
    }

}