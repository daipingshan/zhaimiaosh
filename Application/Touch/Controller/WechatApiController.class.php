<?php
/**
 * Created by PhpStorm.
 * User: daishan
 * Date: 2017/9/1
 * Time: 9:42
 */

namespace Touch\Controller;


use Common\Org\Http;

class WechatApiController extends CommonController {

    const TOKEN = '23xcofeygmqdlxq3tc0fjdxv0r5m4hle';

    /**
     * 是否验证用户登录
     *
     * @var bool
     */
    protected $checkUser = false;

    //	验证token
    protected $checkToken = true;

    /**
     * @var string
     */
    protected $postData = '';


    public function __construct() {
        parent:: __construct();
        $this->postData = $GLOBALS["HTTP_RAW_POST_DATA"];
    }

    /**
     * 验证Token
     */
    protected function _checkToken() {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce     = $_GET["nonce"];
        $token     = self::TOKEN;
        $tmpArr    = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        ob_clean();
        if ($tmpStr == $signature) {
            echo $_GET["echostr"];
            exit;
        } else {
            echo 'token验证失败';
            exit;
        }
    }

    public function index() {
        if ($this->checkToken === false) {
            $this->_checkToken();
        } else {
            if (!empty($this->postData) && $this->postData) {
                //解析数据
                $postObj = simplexml_load_string($this->postData, 'SimpleXMLElement', LIBXML_NOCDATA);
                //消息类型
                $form_MsgType = $postObj->MsgType;
                switch ($form_MsgType) {
                    //接收事件
                    case 'event':
                        $this->_getEvent($postObj);
                        break;
                    //接收text消息
                    case 'text':
                        $this->_getText($postObj);
                        break;
                }
            }
        }
    }

    /**
     *  接收扫描用户事件
     */
    protected function _getEvent($postObj) {
        $user = M('wxuser');
        //发送消息方ID
        $fromUsername = $postObj->FromUserName;//openid
        //接收消息方ID
        $toUsername = $postObj->ToUserName;
        //获取事件类型
        $form_Event = $postObj->Event;
        //关注带有扫描事件
        $event_key = $postObj->EventKey;//传进来的参数  用户的pid

        //  获取用户基本信息,扫描二维码的用户的openid
        $openid    = $this->xmlToArr($fromUsername);
        $user_info = $this->_getUserInfo($openid);

        //  首次关注提示信息
        $first_msg = C('WEIXIN_MP.first_subscribe_msg');
        //  重复关注提示信息
        $repeat_msg = C('WEIXIN_MP.repeat_subscribe_msg');

        $msg = '欢迎' . $user_info['nickname'] . '关注,' . $first_msg . '更多优惠信息，请点击：';
        if ($event_key) {
            $proxy_pid = str_ireplace('qrscene_', '', $event_key);
        }
        $proxy_pid = isset($proxy_pid) && $proxy_pid ? $proxy_pid : C('PID');
        if ($form_Event == "subscribe" || $form_Event == "SCAN") {
            if (isset($proxy_pid)) {
                $uid = M('user')->where(array('pid' => $proxy_pid))->getField('id');
            }
            $uid = isset($uid) && $uid > 0 ? $uid : 1;
            // 查找用户是否存在
            $info = $user->where(array('openid' => $openid))->find();
            if (!$info) {
                $user_data = array(
                    'nickname'   => filterEmoJi($user_info['nickname']),
                    'openid'     => $openid,
                    'proxy_pid'  => $proxy_pid,
                    'proxy_uid'  => $uid,
                    'sex'        => $user_info['sex'],
                    'headimgurl' => $user_info['headimgurl'],
                    'city'       => $user_info['city'],
                    'province'   => $user_info['province'],
                    'country'    => $user_info['country'],
                    'add_time'   => time(),
                    'status'     => 'Y'
                );
                $user->add($user_data);
                $short_url = getShortUrl(C('WEIXIN_BASE.public_number_url') . U('index/index', array('uid' => $uid)));
                $result    = $msg . $short_url;
            } else {
                $result = $repeat_msg;
            }
            $this->_getTextTpl($fromUsername, $toUsername, time(), 'text', $result);
        }
    }

    /**
     * 接收发送消息的事件(找单功能)
     *
     * @param $postObj
     */
    protected function _getText($postObj) {
        //发送消息方ID
        $fromUsername = $postObj->FromUserName;
        //接收消息方ID
        $toUsername = $postObj->ToUserName;
        $content    = $postObj->Content;
        $openid     = $this->xmlToArr($fromUsername);

        $proxy_pid = M('wxuser')->where(array('openid' => $openid))->getField('proxy_pid');
        $agent     = M('user')->where(array('pid' => $proxy_pid))->find();
        $user      = $this->_getParentPid($agent['id']);
        $obj       = new \Common\Org\TaoBaoApi();
        //  返回图文消息
        if (!empty(mb_strstr($content, "找")) || !empty(mb_strstr($content, "买"))) {
            $keyword = str_replace(array('找', '买'), "", $content);
            $like    = '';
            if (strpos($keyword, ' ')) {
                $arr = split(' ', $keyword);
                foreach ($arr as $kw) {
                    $like[] = array('like', '%' . $kw . '%');
                }
                $where['title|num_iid'] = $like;
            } else {
                $where['title|num_iid'] = array('like', '%' . $keyword . '%');
            }


            $where['shop_type'] = array('in', 'B,C');
            $query              = "keyword:'{$keyword}' AND (shop_type:'B' OR shop_type:'C') ";
            if ($agent['dwxk_adsense_id'] != '0') {
                $where['shop_type'] = array('in', 'B,C,J');
                $query              = "keyword:'{$keyword}'";
            }

            $sort = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
            if ($this->openSearchStatus == true) {
                $goods = $this->_getOpenSearchList($query, $sort, null, 0, 5, 'wechat');
            } else {
                $order = 'ordid asc, id desc';
                $goods = M('items')->where($where)->limit(5)->order('coupon_price asc')->order($order)->select();
            }

            //  请输入图文会话标题
            $title  = array('0' => C('WEIXIN_MP.title'));
            $picUrl = array('0' => C('WEIXIN_MP.bg_image'));
            $desc   = array('0' => '淘店客，您身边的购物神器！');
            $url    = array('0' => C('WEIXIN_MP.bg_url'));

            if (!empty($goods)) {
                foreach ($goods as $k => $v) {
                    $title[$k + 1]  = '【券后价' . $v['coupon_price'] . '元】' . $v['title'];
                    $picUrl[$k + 1] = $v['pic_url'];
                    $desc[$k + 1]   = $v['title'];
                    $url[$k + 1]    = C('wechat_mp_domain_url') . U('Item/index', array('id' => $v['id'], 'uid' => $agent['id']));
                }
            } else {
                $obj      = new \Common\Org\TaoBaoApi();
                $res      = $obj->search($keyword, $agent['id'], 1, 4, '');
                $all_data = $res['data'] ? $res['data'] : array();
                foreach ($all_data as $k => $v) {
                    $title[$k + 1]  = '【券后价' . $v['coupon_price'] . '元】' . $v['title'];
                    $picUrl[$k + 1] = $v['pic_url'];
                    $desc[$k + 1]   = $v['title'];
                    $url[$k + 1]    = C('wechat_mp_domain_url') . U('Item/index', array('id' => $v['id'], 'uid' => $agent['id']));
                }
            }
            $last_url = C('wechat_mp_domain_url') . U('Item/search', array('keyword' => $keyword, 'uid' => $agent['id']));
            array_push($title, '点击查看【更多的"' . $keyword . '"优惠商品】');
            array_push($desc, '');
            array_push($picUrl, '');
            array_push($url, $last_url);
            $num = count($title);
            if ($num < 3) {
                $result = '你想要的产品:' . $keyword . '，小编真的找不到啊！';
                $this->_getTextTpl($fromUsername, $toUsername, time(), 'text', $result);
            } else {
                $this->_getNewsTpl($fromUsername, $toUsername, time(), 'news', $title, $desc, $picUrl, $url, $num);
            }
        } elseif (!empty($this->checkUrl($content))) {
            //  输入商品链接，返回购买商品
            $goods_id = $this->findItemNumid($content);
            if ($goods_id != '0') {
                $where['num_iid']   = $goods_id;
                $where['shop_type'] = array('in', 'B,C');
                $query              = "num_iid:'{$goods_id}' AND (shop_type:'B' OR shop_type:'C') ";
                if ($agent['dwxk_adsense_id'] != '0') {
                    $where['shop_type'] = array('in', 'B,C,J');
                    $query              = "num_iid:'{$goods_id}'";
                }

                $sort = array(array('key' => 'ordid', 'val' => 1), array('key' => 'id', 'val' => 0));
                if ($this->openSearchStatus == true) {
                    $detail = $this->_getOpenSearchList($query, $sort, null, 0, 1, 'wechat');
                    if (!empty($detail)) {
                        $goods = array_pop($detail);
                    } else {
                        $goods = array();
                    }
                } else {
                    $order = 'ordid asc, id desc';
                    $goods = M('items')->where($where)->order($order)->find();
                }

                if (!$goods) {
                    //  获取搜索商品的详情，获取商品的高佣链接和淘口令，对全网数据的处理
                    $pass_data = $obj->getPass($goods_id, $this->_getCookie($user['parent_pid']), $proxy_pid);
                    $dtkObj    = new \Common\Org\DaTaoKe();
                    $tmp       = $dtkObj->qwGoodsDetail($goods_id);
                    $short_url = C('wechat_mp_domain_url') . U('Item/index', array('id' => $goods_id, 'uid' => $agent['id'], 'kou_ling' => $pass_data['data']['kou_ling']));

                    $result = '你要找【' . $tmp['title'] . '】吗？点击查看更多相关内容：' . $short_url . ',祝您购物愉快！';
                } else {
                    $short_url = C('wechat_mp_domain_url') . U('Item/index', array('id' => $goods['id'], 'uid' => $agent['id']));
                    $result    = '你要找【' . $goods['title'] . '】吗？点击查看更多相关内容：' . $short_url . ',祝您购物愉快！';
                }
            } else {
                $result = '您输入的链接有误，请核对后，重新输入。';
            }
            $this->_getTextTpl($fromUsername, $toUsername, time(), 'text', $result);
        } elseif (0 == strcasecmp($content, 'app')) {
            $down_url = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.youngt.taodianke';
            $result   = '点击链接下载app ' . $down_url . ' 更多优惠等着您！';
            $this->_getTextTpl($fromUsername, $toUsername, time(), 'text', $result);
        } elseif (strstr($content, "订单")) {
            $result = '暂未开放';
            $this->_getTextTpl($fromUsername, $toUsername, time(), 'text', $result);
        } elseif (strstr($content, "提现")) {
            $result = '暂未开放';
            $this->_getTextTpl($fromUsername, $toUsername, time(), 'text', $result);
        } elseif (strstr($content, "查询积分")) {
            $result = '暂未开放';
            $this->_getTextTpl($fromUsername, $toUsername, time(), 'text', $result);
        } elseif (strstr($content, "签到")) {
            $result = '暂未开放';
            $this->_getTextTpl($fromUsername, $toUsername, time(), 'text', $result);
        }
    }


    /**
     * Convert a SimpleXML object into an array (last resort).
     *
     * @param object $xml
     * @param bool $root Should we append the root node into the array
     * @return array|string
     */
    public function xmlToArr($xml, $root = true) {
        if (!$xml->children()) {
            return (string)$xml;
        }
        $array = array();
        foreach ($xml->children() as $element => $node) {
            $totalElement = count($xml->{$element});
            if (!isset($array[$element])) {
                $array[$element] = "";
            }
            // Has attributes
            if ($attributes = $node->attributes()) {
                $data = array('attributes' => array(), 'value' => (count($node) > 0) ? $this->xmlToArr($node, false) : (string)$node);
                foreach ($attributes as $attr => $value) {
                    $data['attributes'][$attr] = (string)$value;
                }
                if ($totalElement > 1) {
                    $array[$element][] = $data;
                } else {
                    $array[$element] = $data;
                }
                // Just a value
            } else {
                if ($totalElement > 1) {
                    $array[$element][] = $this->xmlToArr($node, false);
                } else {
                    $array[$element] = $this->xmlToArr($node, false);
                }
            }
        }
        if ($root) {
            return array($xml->getName() => $array);
        } else {
            return $array;
        }

    }

    /**
     * @param $fromUserName
     * @param $toUsername
     * @param $time
     * @param $MsgType
     * @param $content
     */
    protected function _getTextTpl($fromUserName, $toUsername, $time, $MsgType, $content = '') {
        ob_clean();
        if ($content != '') {
            echo "<xml>
                    <ToUserName><![CDATA[$fromUserName]]></ToUserName>
                    <FromUserName><![CDATA[$toUsername]]></FromUserName>
                    <CreateTime>$time</CreateTime>
                    <MsgType><![CDATA[$MsgType]]></MsgType>
                    <Content><![CDATA[$content]]></Content>
                    <FuncFlag>0</FuncFlag>
                    </xml>";
        } else {
            echo "<xml>
                    <ToUserName><![CDATA[$fromUserName]]></ToUserName>
                    <FromUserName><![CDATA[$toUsername]]></FromUserName>
                    <CreateTime>$time</CreateTime>
                    <MsgType><![CDATA[$MsgType]]></MsgType>
                    <FuncFlag>0</FuncFlag>
                    </xml>";
        }
        exit;
    }


    /**
     * @param $fromUserName
     * @param $toUsername
     * @param $time
     * @param $MsgType
     * @param $title
     * @param $description
     * @param $picUrl
     * @param $url
     */
    protected function _getNewsTpl($fromUserName, $toUsername, $time, $MsgType, $title, $description, $picUrl, $url, $num = 1) {
        ob_clean();
        if ($num == 1) {
            echo "<xml>
                    <ToUserName><![CDATA[$fromUserName]]></ToUserName>
                    <FromUserName><![CDATA[$toUsername]]></FromUserName>
                    <CreateTime>$time</CreateTime>
                    <MsgType><![CDATA[$MsgType]]></MsgType>
                    <ArticleCount>1</ArticleCount>
                    <Articles>
                    <item>
                    <Title><![CDATA[$title]]></Title>
                    <Description><![CDATA[$description]]></Description>
                    <PicUrl><![CDATA[$picUrl]]></PicUrl>
                    <Url><![CDATA[$url]]></Url>
                    </item>
                    </Articles>
                    <FuncFlag>1</FuncFlag>
                    </xml> ";
        } else {
            $xml = "<xml>
                        <ToUserName><![CDATA[$fromUserName]]></ToUserName>
                        <FromUserName><![CDATA[$toUsername]]></FromUserName>
                        <CreateTime>$time</CreateTime>
                        <MsgType><![CDATA[$MsgType]]></MsgType>
                        <ArticleCount>$num</ArticleCount>
                        <Articles>";
            for ($i = 0; $i < $num; $i++) {
                $xml .= "<item>
                        <Title><![CDATA[$title[$i]]]></Title>
                        <Description><![CDATA[$description[$i]]]></Description>
                        <PicUrl><![CDATA[$picUrl[$i]]]></PicUrl>
                        <Url><![CDATA[$url[$i]]]></Url>
                        </item>";
            }
            $xml .= "</Articles>
                    <FuncFlag>1</FuncFlag>
                    </xml>";
            echo $xml;
        }
        exit;
    }

    /**
     * 校验链接的合法性
     *
     * @param $url
     * @return string
     */
    public function checkUrl($url) {
        $exp = explode('http', $url);
        $url = 'http' . trim($exp[1]) . ' ';
        preg_match('/[\s]/u', $url, $matches, PREG_OFFSET_CAPTURE);
        $url = substr($url, 0, $matches[0][1]);
        if (stripos($url, 'taobao.com') !== false || stripos($url, 'tmall.com') !== false || stripos($url, 'tmall.hk') !== false || stripos($url, 'chuchutong.com') !== false) {
            return $url;
        } else {
            return '';
        }
    }

    /**
     * 查找商品id
     *
     * @param $url
     * @return int
     */
    public function findItemNumid($url) {
        if (preg_match("/[\?&]id=(\d+)/", $url, $match)) {
            return $match[1];
        }
        return 0;
    }

    /**
     * 获取用户的基本信息
     *
     * @param $openid
     * @return array
     */
    protected function _getUserInfo($openid) {
        $httpObj      = new Http();
        $access_token = $this->_getWeChatAccessToken();
        $token_url    = $this->wx_api_url . '?access_token=' . $access_token . '&openid=' . $openid . '&lang=zh_CN';
        $user_info    = json_decode($httpObj->post($token_url), true);
        return $user_info;
    }

}
