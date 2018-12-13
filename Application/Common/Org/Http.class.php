<?php

/**
 * Created by PhpStorm.
 * User: zhoujz
 * Date: 15-3-13
 * Time: 上午11:42
 */

namespace Common\Org;

/**
 * Class Http
 *
 * @package Common\Org
 */
class Http {

    /**
     * @var int
     */
    public $timeOut = 30;

    /**
     * @var null
     */
    private $ch = null;

    public function get($url = '', $data = array()) {
        if (!trim($url)) {
            return array('error' => '地址不能为空！');
        }
        if ($data) {
            $data_str = '';
            foreach ($data as $k => $v) {
                trim($data_str) ? $data_str .= "&$k=$v" : $data_str .= "$k=$v";
            }
            $url .= "?$data_str";
        }
        $this->ch = curl_init();
        // 设置基本属性

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("Accept-Charset: utf-8"));
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Safari/537.36');
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeOut);

        // 发送
        $result = curl_exec($this->ch);
        if (@curl_errno($this->ch)) {
            $result = array('error' => '错误提示：' . curl_error($this->ch));
        }
        curl_close($this->ch);
        return $result;
    }

    /**
     * 通过cookie请求
     *
     * @param string $url
     * @param        $cookie
     * @param array $data
     * @return array|mixed
     */
    public function getCookie($url = '', $cookie, $data = array()) {
        if (!trim($url)) {
            return array('error' => '地址不能为空！');
        }
        if (!trim($cookie)) {
            return array('error' => 'cookie信息不存在！');
        }
        if ($data) {
            $data_str = '';
            foreach ($data as $k => $v) {
                trim($data_str) ? $data_str .= "&$k=$v" : $data_str .= "$k=$v";
            }
            $url .= "?$data_str";
        }
        $this->ch = curl_init();
        // 设置基本属性

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("Accept-Charset: utf-8"));
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Safari/537.36');
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Cookie:{' . $cookie . '}',));
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeOut);

        // 发送
        $result = curl_exec($this->ch);
        if (@curl_errno($this->ch)) {
            $result = array('error' => '错误提示：' . curl_error($this->ch));
        }
        curl_close($this->ch);
        return $result;
    }

    /**
     * @param string $url
     * @param array $data
     * @return array|mixed
     */
    public function post($url = '', $data = array()) {

        // 参数判断
        if (!trim($url)) {
            return array('error' => '地址不能为空！');
        }
        $this->ch = curl_init();
        // 设置基本属性

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("Accept-Charset: utf-8"));
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.75 Safari/537.36');
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeOut);

        // 发送
        $result = curl_exec($this->ch);
        if (@curl_errno($this->ch)) {
            $result = array('error' => '错误提示：' . curl_error($this->ch));
        }
        curl_close($this->ch);
        return $result;
    }

    /**
     * 通过cookie请求
     *
     * @param string $url
     * @param        $cookie
     * @param array $data
     * @param string $refer
     * @return array|mixed
     */
    public function postCookie($url = '', $cookie, $data = array(), $refer = "") {
        // 参数判断
        if (!trim($url)) {
            return array('error' => '地址不能为空！');
        }
        if (!$refer) {
            $refer = "http://www.alimama.com/index.htm";
        }
        $this->ch = curl_init();
        // 设置基本属性
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("Accept-Charset: utf-8"));
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Safari/537.36');
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_REFERER, $refer);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Cookie:{' . $cookie . '}',));
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeOut);
        // 发送
        $result = curl_exec($this->ch);
        if (@curl_errno($this->ch)) {
            $result = array('error' => '错误提示：' . curl_error($this->ch));
        }
        curl_close($this->ch);
        return $result;

    }

    /**
     * @param $url
     * @param $cookie
     * @param $filename
     * @return array
     */
    public function downFile($url, $cookie, $filename) {
        // 参数判断
        if (!trim($url)) {
            return array('error' => '地址不能为空！');
        }
        if (!trim($cookie)) {
            return array('error' => 'cookie不能为空！');
        }
        $this->ch = curl_init();
        // 设置基本属性

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("Accept-Charset: utf-8"));
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Safari/537.36');
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Cookie:{' . $cookie . '}',));
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeOut);
        // 发送
        $result = curl_exec($this->ch);
        if (@curl_errno($this->ch)) {
            $res = array('error' => '错误提示：' . curl_error($this->ch));
        } else {
            $res = array('success' => '下载成功');
        }
        if (file_exists($filename)) {
            unlink($filename);
        }
        if ($res['success'] && $result) {
            file_put_contents($filename, $result);
        }
        curl_close($this->ch);
        return $res;
    }

}
