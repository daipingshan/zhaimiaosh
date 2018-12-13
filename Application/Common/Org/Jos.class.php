<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 2018/5/21
 * Time: 11:00
 */

namespace Common\Org;
require __DIR__ . '/Jd/autoload.php';

use Jd\JdClient;
use Jd\request\ServicePromotionCreatePromotionSiteBatchRequest;
use Jd\request\UnionServiceQueryCommissionOrdersRequest;
use Jd\request\UnionServiceQueryImportOrdersRequest;
use Jd\request\UnionServiceQueryOrderListRequest;
use Think\Exception;

/**
 * Class Jos
 *
 * @package Common\Org\Jd
 */
Class Jos {

    /* @var $jd JdClient */
    private $jd;

    private $appKey;
    private $appSecret;
    private $accessToken;

    //需要一个周更换一次
    private $key;

    //联盟id 一般不需要修改
    private $unionId;
    private $serverUrl = "https://api.jd.com/routerjson";

    //推广位前缀
    public $prefix = "ZM";

    /**
     * Jos constructor.
     *
     * @param array $params
     */
    public function __construct($params = []) {
        //获取系统参数
        $this->key         = isset($params['key']) ? $params['key'] : C('BASE.jingdong_union_key');
        $this->unionId     = isset($params['unionId']) ? $params['unionId'] : C('BASE.jingdong_union_id');
        $this->appKey      = isset($params['appKey']) ? $params['appKey'] : C('BASE.jingdong_jos_app_key');
        $this->appSecret   = isset($params['appSecret']) ? $params['appSecret'] : C('BASE.jingdong_jos_app_secret');
        $this->accessToken = isset($params['accessToken']) ? $params['accessToken'] : C('BASE.jingdong_jos_access_token');
        $this->serverUrl   = isset($params['serverUrl']) ? $params['serverUrl'] : $this->serverUrl;

        $this->initJos();
    }

    /**
     * 实例化宙斯客户端
     *
     * @return JdClient
     */
    private function initJos() {
        // 实例化客户端
        $jd = new JdClient();
        //设定系统参数
        $jd->appKey      = $this->appKey;
        $jd->appSecret   = $this->appSecret;
        $jd->serverUrl   = $this->serverUrl;
        $jd->accessToken = $this->accessToken;

        return $this->jd = $jd;
    }

    /**
     * 查询订单
     * @param $param
     * @param string $type queryOrder|importOrder|commissionOrder
     * @return mixed|\SimpleXMLElement|string
     * @throws Exception
     */
    public function queryOrder($param, $type = 'queryOrder') {
        if (empty($param)) {
            throw new Exception('参数不能为空');
        }
        $param['page']     = isset($param['page']) ? $param['page'] : 1;
        $param['pageSize'] = isset($param['pageSize']) ? $param['pageSize'] : 400;
        if($type == "queryOrder"){
            $class = 'Jd\request\UnionServiceQueryOrderListRequest';
        } else if ($type == "commissionOrder") {
            $class = 'Jd\request\UnionServiceQueryCommissionOrdersRequest';
        } else if ($type == "importOrder") {
            $class = 'Jd\request\UnionServiceQueryImportOrdersRequest';
        }
        /* @var $req UnionServiceQueryImportOrdersRequest|UnionServiceQueryCommissionOrdersRequest|UnionServiceQueryOrderListRequest */
        $req = new $class();
        $req->setTime($param['time']);
        $req->setUnionId($this->unionId);
        $req->setPageIndex($param['page']);
        $req->setPageSize($param['pageSize']);

        return $this->jd->execute($req, $this->jd->accessToken);
    }

    /**
     * 批量创建推广位
     *
     * @param $param
     * @return mixed|\SimpleXMLElement
     * @throws Exception
     */
    public function createPromotion($mobile) {
        $this->jd->appKey      = C('BASE.jingdong_jtt_jos_app_key');
        $this->jd->appSecret   = C('BASE.jingdong_jtt_jos_app_secret');
        $this->jd->accessToken = C('BASE.jingdong_jtt_jos_access_token');

        if (!$mobile) {
            throw new Exception('参数不能为空');
        }
        $req = new ServicePromotionCreatePromotionSiteBatchRequest();
        $req->setType(4);
        $req->setKey($this->key);
        $req->setUnionType(1);
        $req->setUnionId($this->unionId);
        $req->setSpaceName($this->prefix . $mobile);

        return $this->jd->execute($req, $this->jd->accessToken);
    }
}