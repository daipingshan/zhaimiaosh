<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/19
 * Time: 17:03
 */

namespace Api\Controller;

use Common\Org\TaoBaoApi;

/**
 * Class ItemsController
 *
 * @package Api\Controller
 */
class ItemController extends CommonController {

    /**
     * 构造方法
     */
    public function __construct() {
        parent::__construct();

        $auth_arr = array('detail', 'getTaobaoBuyInfo');
        $token = I('request.token', '', 'trim');
        $invite_code = I('get.invite_code', '', 'trim');
        if ('' == $token && '' != $invite_code) {
            $user_info = M('user')->where(array('invite_code' => $invite_code))->find();
            if (!empty($user_info['pid'])) {
                $this->pid = $user_info['pid'];
            } else if (!empty($user_info['inviter_pid'])) {
                $this->pid = $user_info['inviter_pid'];
            }

            if (!empty($user_info['pdd_pid'])) {
                $this->pdd_pid = $user_info['pdd_pid'];
            } else if (!empty($user_info['inviter_pdd_pid'])) {
                $this->pdd_pid = $user_info['inviter_pdd_pid'];
            }
        }
        if (in_array(ACTION_NAME, $auth_arr) && '' != $token) {
            $this->_checkUser();
        }
    }

    /**
     * 商品列表
     */
    public function index() {
        $cate_id    = I('get.cate_id', 0, 'int');
        $sort_field = I('get.sort_field', '', 'trim');
        $page       = I('get.page', 1, 'int');
        $page--;
        $where = array();
        $query = '';
        if ($cate_id > 0) {
            $where = array('cate_id' => $cate_id);
            $query = "cate_id:'{$cate_id}'";
        }
        switch ($sort_field) {
            case 'sale':
                //按销量排序
                $order = 'volume desc';
                $sort  = array(array('key' => 'volume', 'val' => 0));
                break;
            case 'new':
                //最新
                $order = 'id desc';
                $sort  = array(array('key' => 'id', 'val' => 0));
                break;
            case 'coupon_price_asc':
                //券后价正序
                $order = 'coupon_price asc';
                $sort  = array(array('key' => 'coupon_price', 'val' => 1));
                break;
            case 'coupon_price_desc':
                //券后价倒序
                $order = 'coupon_price desc';
                $sort  = array(array('key' => 'coupon_price', 'val' => 0));
                break;
            case 'commission_rate_desc':
                //按佣金率倒序
                $order = 'commission_rate desc';
                $sort  = array(array('key' => 'commission_rate', 'val' => 0));
                break;
            default:
                $order = 'ordid asc,id desc';
                $sort  = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
        }
        $start_num = $page * $this->limit;
        if ($this->openSearchStatus === true) {
            $data = $this->_getOpenSearchList($query, $sort, null, $start_num, $this->limit);
            if (empty($data)) {
                $data = $this->_getItemsList($where, $order, $start_num, $this->limit);
            }
        } else {
            $data = $this->_getItemsList($where, $order, $start_num, $this->limit);
        }

        if ($page <= 5) {
            shuffle($data);
        }

        $this->output('ok', 'success', $data);
    }

    /**
     * 商品搜索
     */
    public function search() {
        $keyword    = urldecode(I('get.keyword', '', 'trim'));
        $page       = I('get.page', 1, 'int');
        $sort_field = I('get.sort_field', '', 'trim');
        $type       = I('get.type', 'app', 'trim');
        $cate_id    = I('get.cate_id', 0, 'intval');

        if (!empty($keyword)) {
            $pos = stripos($keyword, '复制这条信息');
            if (false !== $pos) {
                $keyword = substr($keyword, 0, $pos);
                $pos1    = stripos($keyword, '快来和我一起瓜分');
                if (false !== $pos1) {
                    $keyword = substr($keyword, 0, $pos1);
                }
            }

            $keyword = str_ireplace(array('【', '】', '（', '）', '，', ' ', '我剁手都要买的宝贝'), '', $keyword);
        }

        $where = array();
        switch ($sort_field) {
            case 'sale':
                $order     = 'volume desc';
                $open_sort = array(array('key' => 'volume', 'val' => 0));
                $sort      = 'total_sales_des';
                $pdd_sort  = 6;
                $jtt_rank  = 'sift';
                break;
            case 'new':
                //最新
                $order     = 'id desc';
                $open_sort = array(array('key' => 'id', 'val' => 0));
                $sort      = '';
                $pdd_sort  = 12;
                $jtt_rank  = 'sift';
                break;
            case 'coupon_price_asc':
                //券后价正序
                $order     = 'coupon_price asc';
                $open_sort = array(array('key' => 'coupon_price', 'val' => 1));
                $sort      = 'price_asc';
                $pdd_sort  = 9;
                $jtt_rank  = 'finally';
                break;
            case 'coupon_price_desc':
                //按券后价倒序
                $order     = 'coupon_price desc';
                $open_sort = array(array('key' => 'coupon_price', 'val' => 0));
                $sort      = 'price_des';
                $pdd_sort  = 10;
                $jtt_rank  = 'sale';
                break;
            case 'commission_rate_desc':
                //按佣金率倒序
                $order     = 'commission_rate desc';
                $open_sort = array(array('key' => 'commission_rate', 'val' => 0));
                $sort      = 'tk_rate_des';
                $pdd_sort  = 2;
                $jtt_rank  = 'brokerage';
                break;
            default:
                $order     = 'ordid asc,id desc';
                $open_sort = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
                $sort      = '';
                $pdd_sort  = 0;
                $jtt_rank  = '';
        }
        if ($type == 'all') {
            $data = $this->_getTaoBaoSearch($keyword, $page, $sort);
        } else if ($type == 'pinduoduo') {
            $params = array('sort_type' => $pdd_sort, 'page' => $page);
            if ($cate_id > 0) {
                $params['category_id'] = $cate_id;
            }

            if (is_numeric($keyword) && $keyword > 100000) {
                $params['goods_id_list'] = '[' . $keyword . ']';
            } else {
                $params['keyword'] = $keyword;
            }
            $data = $this->_pddGoodsSearch($params);
        } else if ($type == 'jingdong') {
            $params = array('so' => $keyword, 'page' => $page);
            if ($jtt_rank != '' && $keyword == '') {
                $jtt_rank = 'sift';
            }

            if ($cate_id) {
                $params['type'] = $cate_id;
            }

            if ($jtt_rank != '') {
                $params['rank'] = $jtt_rank;
            }

            $data = $this->_jttGoodsSearch($params);
        } else {
            $keyword = $this->_getTagsStr($keyword);

            $where['title|num_iid'] = array('like', '%' . $keyword . '%');
            $query                  = "keyword:'{$keyword}'";
            if ($this->openSearchStatus === true) {
                $start_num = ($page - 1) * $this->limit;
                $data      = $this->_getOpenSearchList($query, $open_sort, null, $start_num, $this->limit);
            } else {
                $start_num = ($page - 1) * $this->limit;
                $data      = $this->_getItemsList($where, $order, $start_num, $this->limit);
            }
        }
        $this->output('ok', 'success', $data);
    }

    /**
     * 商品详情
     */
    public function detail() {
        $num_iid = I('get.num_iid', '', 'trim');
        $type    = I('get.type', 'app', 'trim');
        $invite_code    = I('get.invite_code', '', 'trim');
        if (empty($num_iid)) {
            $this->output('商品编号不能为空！');
        }

        if ($type == 'pinduoduo') {
            $item = $this->_pddGoodsDetail($num_iid);
            if (empty($item)) {
                $this->output('商品获取异常，请重试！');
            }

            $buy_url = $this->_pddGoodsPromotionUrl($this->pdd_pid, $num_iid);
            if (empty($buy_url)) {
                $this->output('商品获取异常，请重试！');
            } else {
                $item['buy_url'] = $buy_url['url'];
                $item['share_url'] = $buy_url['short_url'];
                $temp = parse_url($buy_url['url']);
                $item['pinduoduo_info']['pdd_mini_program_path'] = 'pages/welfare_coupon/welfare_coupon?' . $temp['query'];
            }

            $item['lately_buy_users'] = $this->_getLatelyBuyUsers();

            $this->output('ok', 'success', $item);
        } else if ($type == 'jingdong') {
            $item = $this->_jttGoodsDetail($num_iid, $this->jd_pid);
            if (!empty($item)){
                $item_imgs = $this->_getJdItemInfo($num_iid);
                if (!empty($item_imgs['small_images'])) {
                    $item['small_images'] = format_url_to_https($item_imgs['small_images']);
                    $item['desc_images'] = format_url_to_https($item_imgs['desc_images']);
                }

                $item['lately_buy_users'] = $this->_getLatelyBuyUsers();
            }

            $this->output('ok', 'success', $item);
        } else if ($type == 'all') {
            $url  = "http://item.taobao.com/item.htm?id={$num_iid}";
            $data = $this->_getTaoBaoSearch($url, 1, 0);
            if (!isset($data[0]) || !$data[0]) {
                $this->output('商品信息不存在！');
            }
            $info                 = $data[0];
            $info['desc_images']  = array();

            if (empty($info['small_images'])) {
                $info['small_images'] = $this->_getItemSmallImage($num_iid, $type);
            }
        } else {
            $res = $this->_getItemDetail($num_iid);
            if ($res['status'] == -1) {
                $this->output($res['info'], 'item_down_line');
            } else if ($res['status'] == 0) {
                $this->output($res['info']);
            }
            $info = $res['data'];
        }
        $res = $this->_applyHigh($info, $this->pid);
        if ($res['status'] == 0) {
            $this->output('获取优惠券异常，请重试');
        }
        $info['buy_url'] = $res['data']['buy_url'];
        $res             = $this->_getPass($res['data']['buy_url'], $info['title'], $info['pic_url']);
        if ($res['status'] == 0) {
            $this->output($res['info']);
        }
        $info['tao_kou_ling'] = $res['data']['tao_kou_ling'];

        //快站可用时优先使用快站域名
        if ('' != C('BASE.share_detail_kuaizhan_domain')) {
            /*$key = base64_encode(json_encode(array('tkl'=>$info['tao_kou_ling'],'image'=>$info['pic_url'])));
            $share_url = C('BASE.share_detail_kuaizhan_domain') . "?key={$key}";*/
            $share_url = C('BASE.share_detail_kuaizhan_domain') . "/?taowords={$info['tao_kou_ling']}&pic=" . base64_encode($info['pic_url']);
        } else {
            $tao_kou_ling = urlencode($res['data']['tao_kou_ling']);
            $share_url = C('BASE.share_detail_url') . "/num_iid/{$num_iid}/user_id/{$this->user_id}/tao_kou_ling/{$tao_kou_ling}";
        }

        $info['share_url']    = get_short_url(urlencode($share_url));
        $info['lately_buy_users'] = $this->_getLatelyBuyUsers();
        $this->output('ok', 'success', $info);
    }

    /**
     * 获取商品图文详情
     */
    public function itemDesc() {
        $num_iid = I('get.num_iid', '', 'trim');
        if (empty($num_iid)) {
            $this->output('商品编号不能为空！');
        }
        $img_data = $this->_getItemsDesc($num_iid);
        $this->output('ok', 'success', array('img_data' => $img_data));
    }

    /**
     * 获取商品图文详情
     */
    public function itemDescDisplay() {
        $num_iid = I('get.num_iid', '', 'trim');
        if (empty($num_iid)) {
            $this->output('商品编号不能为空！');
        }
        $img_data = $this->_getItemsDesc($num_iid);
        $this->assign('img_data', $img_data);
        $this->display('Touch/itemDesc');
    }


    /**
     * 获取指定类型商品
     */
    public function special() {
        $type   = I('get.type', 'sale', 'trim');
        $where  = array();
        $query  = '';
        $page   = I('get.page', 1, 'int');
        $order  = 'ordid asc,id desc';
        $sort   = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
        $filter = null;
        switch ($type) {
            case 'sale':
                //按销量排序
                $order = 'volume desc';
                $sort  = array(array('key' => 'volume', 'val' => 0));
                break;
            case 'home' :
                $where['cate_id'] = 23;
                $query            = "cate_id:'23'";
                break;
            case 'hot' :
                $where['coupon_type'] = 5;
                $query                = "coupon_type:'5'";
                break;
            case 'high_commission' :
                $where['quan'] = array('gt', 100);
                $filter        = 'quan > 100';
                $order         = 'commission_rate desc';
                $sort          = array(array('key' => 'commission_rate', 'val' => 0));
                break;
            case 'twenty' :
                $where['coupon_price'] = array('elt', 20);
                $filter                = 'coupon_price<=20';
                break;
            case 'handpick' :
                $where['handpick_time'] = array('gt', 0);
                $filter                 = 'handpick_time > 0';
                $order                  = 'handpick_time desc';
                $sort                   = array(array('key' => 'handpick_time', 'val' => 0));
                break;
            case 'limit_time' :
                $time             = date('Y-m-d', strtotime('+1 days'));
                $where['endtime'] = $time;
                $query            = "endtime:'{$time}'";
                break;
            case 'nine_nine' :
                $where['coupon_type'] = 4;
                $query                = "coupon_type:'4'";
                break;
            case 'new' :
                $order = 'id desc';
                $sort  = array(array('key' => 'id', 'val' => 0));
                break;
            case 'haoquan' :
                $obj  = new \Common\Org\TaoBaoApi();
                $items = $obj->getTaobaoMaterialItems(3756, 20, $page);
                $this->output('ok', 'success', $items);
            case 'pinpai' :
                $obj  = new \Common\Org\TaoBaoApi();
                $items = $obj->getTaobaoMaterialItems(3786, 20, $page);
                $this->output('ok', 'success', $items);
            default :
                break;
        }
        $start_num = ($page - 1) * $this->limit;
        if ($this->openSearchStatus === true) {
            $data = $this->_getOpenSearchList($query, $sort, $filter, $start_num, $this->limit);
        } else {
            $data = $this->_getItemsList($where, $order, $start_num, $this->limit);
        }
        $this->output('ok', 'success', $data);
    }

    /**
     * 多商品ID商品列表
     */
    public function moreItem() {
        $num_iid = I('get.num_iid', '', 'trim');
        if (strpos($num_iid, ',') === false) {
            $this->output('商品编号不合法！');
        }
        $num_iid_arr = array_filter(explode(',', $num_iid));
        $where       = array('num_iid' => array('in', $num_iid_arr));
        $query       = "num_iid:'" . implode("' OR num_iid:'", $num_iid_arr) . "'";
        $order       = 'ordid asc,id desc';
        $sort        = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
        if ($this->openSearchStatus === true) {
            $data = $this->_getOpenSearchList($query, $sort, null, 0, count($num_iid_arr));
        } else {
            $data = $this->_getItemsList($where, $order, 0, count($num_iid_arr));
        }
        $this->output('ok', 'success', $data);
    }

    /**
     * 相似商品
     */
    public function similar() {
        $title   = I('get.title', '', 'trim,urldecode');
        $num_iid = I('get.num_iid', '', 'trim');
        if (empty($title)) {
            $this->output(array('ok', 'success', array()));
        }
        $keyword        = $this->_getTagsStr($title);
        $where['title'] = array('like', '%' . $keyword . '%');
        $query          = "keyword:'{$keyword}'";
        $limit          = 7;
        $order          = 'ordid asc,id desc';
        $sort           = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
        if ($this->openSearchStatus === true) {
            $data = $this->_getOpenSearchList($query, $sort, null, 0, $limit);
        } else {
            $data = $this->_getItemsList($where, $order, 0, $limit);
        }
        foreach ($data as $key => $val) {
            if ($val['num_iid'] == $num_iid) {
                unset($data[$key]);
            }
        }
        if (empty($data)) {
            $data = $this->_getTaoBaoSearch($keyword, 1, $sort, 6);
        }
        $this->output('ok', 'success', array_values($data));
    }

    /**
     * 品牌专场
     */
    public function good() {
        $topic_info = M('item_topic')->field('id,topic_detail_pic,items')->where(array('type' => 2))->order('sort asc')->find();
        if (empty($topic_info)) {
            $this->output('专题正在努力创建中.....');
        }

        $topic_detail = $this->_getTopicDetail($topic_info['id']);
        $topic_info['items'] = $topic_detail['items'];

        $topic_list          = M('item_topic')->field('id,topic_detail_pic,items')->where(array('type' => 3))->order('sort asc')->select();
        foreach ($topic_list as &$list) {
            $topic_detail = $this->_getTopicDetail($list['id']);
            $items = $topic_detail['items'];

            $temp_data = array();
            foreach ($items as $item) {
                $temp_data[] = array('num_iid' => $item['num_iid'], 'pic_url' => $item['pic_url'], 'title' => $item['title'], 'coupon_price' => $item['coupon_price']);
            }
            $list['items'] = $temp_data;
        }
        unset($list);
        $topic_info['topics'] = $topic_list;
        $this->output('ok', 'success', $topic_info);
    }

    /**
     * 时尚范儿
     */
    public function fashion() {
        $type       = I('get.type', 'girl', 'trim');
        $type_id    = $type == 'boy' ? 4 : 5;
        $topic_info = M('item_topic')->field('id,topic_detail_pic,items')->where(array('type' => $type_id))->order('sort asc')->find();
        if (empty($topic_info)) {
            $this->output('专题正在努力创建中.....');
        }

        $topic_detail = $this->_getTopicDetail($topic_info['id']);
        $topic_info['items'] = $topic_detail['items'];
        $this->output('ok', 'success', $topic_info);
    }

    /**
     * 9.9包邮
     */
    public function nineNine() {
        $cate_id    = I('get.cate_id', 0, 'int');
        $page       = I('get.page', 1, 'int');
        $topic_info = $list = array();
        if ($cate_id == 0 && $page == 1) {
            $topic_info = M('item_topic')->field('id,topic_detail_pic,items')->where(array('type' => 6))->order('sort asc')->find();
            $topic_detail = $this->_getTopicDetail($topic_info['id']);
            $items = $topic_detail['items'];

            $temp_data  = array();
            foreach ($items as $item) {
                if (count($temp_data) < 6) {
                    $temp_data[] = array('num_iid' => $item['num_iid'], 'pic_url' => $item['pic_url'], 'coupon_price' => $item['coupon_price']);
                }
            }
            $topic_info['hot_items'] = $temp_data;
        }
        $where = array('coupon_type' => 4);
        $query = "coupon_type:'4'";
        $order = 'ordid asc,id desc';
        $sort  = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
        if ($cate_id > 0) {
            $where['cate_id'] = $cate_id;
            $query .= " AND cate_id:'{$cate_id}'";
        }
        $start_num = ($page - 1) * $this->limit;
        if ($this->openSearchStatus === true) {
            $list = $this->_getOpenSearchList($query, $sort, null, $start_num, $this->limit);
        } else {
            $list = $this->_getItemsList($where, $order, $start_num, $this->limit);
        }
        $data = array(
            'topic_detail_pic' => isset($topic_info['topic_detail_pic']) ? $topic_info['topic_detail_pic'] : '',
            'hot_items'        => isset($topic_info['hot_items']) ? $topic_info['hot_items'] : array(),
            'items'            => $list
        );
        $this->output('ok', 'success', $data);
    }

    /**
     * 高额券
     */
    public function highCoupon() {
        $type  = I('get.type', 'rate', 'trim');
        $page  = I('get.page', 1, 'int');
        $where = array();
        $query = '';
        if ($type == 'rate') {
            $sort  = array(array('key' => 'commission_rate', 'val' => 0), array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
            $order = 'commission_rate desc,ordid asc,id desc';
        } else {
            $sort  = array(array('key' => 'quan', 'val' => 0), array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
            $order = 'coupon_money desc,ordid asc,id desc';
        }
        $start_num = ($page - 1) * $this->limit;
        if ($this->openSearchStatus === true) {
            $data = $this->_getOpenSearchList($query, $sort, null, $start_num, $this->limit);
        } else {
            $data = $this->_getItemsList($where, $order, $start_num, $this->limit);
        }
        $this->output('ok', 'success', $data);
    }

    /**
     * 小编精选专题
     */
    public function handpick() {
        $topic_info = M('item_topic')->where(array('type' => 7))->order('sort asc')->find();
        if (empty($topic_info)) {
            $this->output('专题正在努力创建中.....');
        }
        $where  = array('handpick_time' => array('gt', 0));
        $filter = 'handpick_time > 0';
        $order  = 'handpick_time desc';
        $sort   = array(array('key' => 'handpick_time', 'val' => 0));
        $count  = M('items')->where($where)->count('id');
        if ($this->openSearchStatus === true) {
            $data = $this->_getOpenSearchList('', $sort, $filter, 0, $count);
        } else {
            $data = $this->_getItemsList($where, $order, 0, $count);
        }
        $topic_info['items'] = $data;
        $this->output('ok', 'success', $topic_info);
    }

    /**
     * 根据商品信息获取淘口令
     */
    public function getTaobaoBuyInfo(){
        $params['num_iid']     = I('request.num_iid', '', 'trim');
        $params['title']       = strip_tags(urldecode(I('request.title', '', 'trim')));
        $params['pic_url']     = I('request.pic_url', '', 'trim');

        $params['activity_id'] = '';
        $params['click_url']   = '';

        if (!$params['title'] || !$params['num_iid'] || !$params['pic_url']){
            $this->output('参数异常，请检查参数是否正确');
        }

        $res = $this->_applyHigh($params, $this->pid);
        if ($res['status'] == 0) {
            $this->output('获取优惠券异常，请重试');
        }
        $params['buy_url'] = $res['data']['buy_url'];
        $res             = $this->_getPass($res['data']['buy_url'], $params['title'], $params['pic_url']);
        if ($res['status'] == 0) {
            $this->output($res['info']);
        }

        //快站可用时优先使用快站域名
        if ('' != C('BASE.share_detail_kuaizhan_domain')) {
            $share_url = C('BASE.share_detail_kuaizhan_domain') . "/?taowords={$res['data']['tao_kou_ling']}&pic=" . base64_encode($params['pic_url']);
        } else {
            $tao_kou_ling = urlencode($res['data']['tao_kou_ling']);
            $share_url = C('BASE.share_detail_url') . "/num_iid/{$num_iid}/user_id/{$this->user_id}/tao_kou_ling/{$tao_kou_ling}";
        }

        $data['share_url']    = get_short_url(urlencode($share_url));
        $data['tao_kou_ling'] = $res['data']['tao_kou_ling'];
        $data['buy_url']      = $params['buy_url'];
        $data['small_images'] = $this->_getItemSmallImage($params['num_iid'], 'all');
        $this->output('ok', 'success', $data);
    }

    /**
     * 获取商品详情小程序码图片
     */
    public function getMiniProgramCodePic() {
        $num_iid = I('get.num_iid', '', 'trim');
        $invite_code = I('get.invite_code', '', 'trim');
        $shop_type   = I('get.shop_type', '', 'trim');

        $access_token = $this->_getMiniProgramAccessToken();
        if ('' == $access_token) {
            $this->output('获取access token异常');
        }

        $url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $access_token;
        $http = new \Common\Org\Http();
        $data = array(
            'scene' => $num_iid . '/' . $invite_code . '/' . $shop_type,
            'page' => 'pages/goodsDetail/goodsInfo',
            'width' => 200
        );

        $first_two_char = substr($invite_code, 0, 2);
        $dir = dirname(APP_PATH) . '/www/Static/images/' . $first_two_char;
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $res = $http->post($url, json_encode($data));
        $file_name = $dir . '/mini_program_code_' . $invite_code . '.png';
        file_put_contents($file_name, $res);

        $this->output('ok', 'success', array('mini_program_code_pic' => C('BASE.app_interface_domain') . '/Static/images/' . $first_two_char . '/' . 'mini_program_code_' . $invite_code . '.png'));
    }
}