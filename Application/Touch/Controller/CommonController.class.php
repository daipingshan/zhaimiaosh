<?php

namespace Touch\Controller;

use Common\Controller\CommonBusinessController;
use Common\Org\OpenSearch;

/**
 * Class CommonAction
 */
class CommonController extends CommonBusinessController {
    //  每页数据
    protected $page_size = 20;
    // 对话框图片地址
    protected $logo = 'http://tao.taodianke.com/Public/Wechat/images/mao.jpg';

    //  微信api url
    protected $wx_api_url = 'https://api.weixin.qq.com/cgi-bin/user/info';

    /**
     * 构造方法
     */
    public function __construct() {
        parent:: __construct();
        $this->version = 2;
        $this->assign('act', 'one');
    }

    /**
     * 微信联合登陆
     *
     * @param $state
     */
    protected function _WeChatLogin($state) {
        $redirect_uri = urlencode(C('WEIXIN_BASE.public_number_url') . U('Index/callBackUrl'));
        $app_id       = C('WEIXIN_BASE.app_id');
        $auth_url     = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $app_id . "&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_base&state={$state}#wechat_redirect";
        redirect($auth_url);
    }

}
