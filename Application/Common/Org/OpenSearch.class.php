<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/1 0001
 * Time: 下午 2:10
 */

namespace Common\Org;

use OpenSearch\Client\OpenSearchClient;
use OpenSearch\Client\SearchClient;
use OpenSearch\Util\SearchParamsBuilder;

require_once(__DIR__ . "/OpenSearch/Autoloader/Autoloader.php");

class OpenSearch {

    /**
     * 应用ID
     *
     * @var string
     */
    private $app_id = "LTAI8ixfEzOHRVVm";

    /**
     * 应用ID
     *
     * @var string
     */
    private $app_secret = "Z4Y6zwqp0cgmCAi4kIJENW7pqCS5ci";

    /**
     * 应用名称
     *
     * @var string
     */
    private $app_name = "zhaimiaosh";

    /**
     * 公网地址
     *
     * @var string
     */
    private $public_url = "http://opensearch-cn-hangzhou.aliyuncs.com";


    /**
     * @var null
     */
    static $client = null;

    /**
     * 构造函数
     * OpenSearch constructor.
     */
    public function __construct() {
        $option       = array('debug' => true);
        self::$client = new OpenSearchClient($this->app_id, $this->app_secret, $this->public_url, $option);
    }

    /**
     * 搜索
     *
     * @param      $keyword
     * @param      $sort
     * @param null $filter
     * @param int $start_num
     * @param int $page_size
     */
    public function search($keyword, $sort, $filter = null, $start_num = 0, $page_size = 20) {
        // 实例化一个搜索类
        $searchClient = new SearchClient(self::$client);
        // 实例化一个搜索参数类
        $params = new SearchParamsBuilder();
        //设置config子句的start值
        $params->setStart($start_num);
        //设置config子句的hit值
        $params->setHits($page_size);
        // 指定一个应用用于搜索
        $params->setAppName($this->app_name);
        // 指定搜索关键词
        $params->setQuery($keyword);
        // 指定过滤类型
        if ($filter !== null) {
            $params->setFilter($filter);
        }
        // 指定返回的搜索结果的格式为json
        $params->setFormat("fulljson");
        //添加排序字段

        foreach ($sort as $val) {
            $params->addSort($val['key'], $val['val']);
        }
        // 执行搜索，获取搜索结果
        $ret = $searchClient->execute($params->build())->result;
        // 将json类型字符串解码
        $res = json_decode($ret, true);
        if ($res['status'] == 'OK') {
            $data = array('status' => 'OK', 'data' => array());
            foreach ($res['result']['items'] as $item) {
                $data['data'][] = $item['fields'];
            }
            return $data;
        } else {
            return $res;
        }
    }

    /**
     * @param      $keyword
     * @param null $filter
     * @return int
     */
    public function searchCount($keyword, $filter = null) {
        // 实例化一个搜索类
        $searchClient = new SearchClient(self::$client);
        // 实例化一个搜索参数类
        $params = new SearchParamsBuilder();
        // 指定一个应用用于搜索
        $params->setAppName($this->app_name);
        // 指定搜索关键词
        $params->setQuery($keyword);
        // 指定过滤类型
        if ($filter !== null) {
            $params->setFilter($filter);
        }
        // 指定返回的搜索结果的格式为json
        $params->setFormat("fulljson");
        // 执行搜索，获取搜索结果
        $ret = $searchClient->execute($params->build())->result;
        // 将json类型字符串解码
        $res = json_decode($ret, true);
        if ($res['status'] == 'OK') {
            return $res['result']['total'];
        } else {
            return 0;
        }
    }
}