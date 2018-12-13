<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/25 0025
 * Time: 上午 9:39
 */

namespace Common\Org;

use OSS\OssClient;
use OSS\Core\OssException;

require_once(__DIR__ . '/OSS/autoload.php');

class AliYunOss {

    /**
     * 错误信息显示
     * @var string
     */
    protected $error = 'error';

    /**
     * @var null
     */
    protected $ossClient = null;

    /**
     * 您从OSS获得的AccessKeyId
     * @var string
     */
    protected $accessKeyId = "LTAI8ixfEzOHRVVm";

    /**
     * 您从OSS获得的AccessKeySecret
     * @var string
     */
    protected $accessKeySecret = "Z4Y6zwqp0cgmCAi4kIJENW7pqCS5ci";

    /**
     * 您选定的OSS数据中心访问域名，例如http://oss-cn-hangzhou.aliyuncs.com
     * @var string
     */
    protected $endpoint = "http://oss-cn-hangzhou.aliyuncs.com";

    /**
     * 您使用的存储空间名称，注意命名规范
     * @var string
     */
    protected $bucket = "taodianke";

    /**
     * @var array
     */
    protected $config = array('size' => 2 * 1024 * 1024);

    /**
     * 私有构造函数，防止外界实例化对象
     * AliYunOss constructor.
     */
    public function __construct($config = array()) {
        try {
            $this->ossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
            $this->ossClient->setTimeout(3600);
            $this->ossClient->setConnectTimeout(60);
            if ($config['size']) {
                $this->config['size'] = $config['size'];
            }
        } catch (OssException $e) {
            $this->_addLog($e->getMessage());
        }
    }

    /**
     * 上传文件
     * @param $ossClient
     * @param $bucket
     */
    function uploadMoreObject() {
        $object = date('Y') . '/' . date('md');
        $files  = $_FILES;
        if (empty($files)) {
            $this->error = '没有上传的文件！';
            return array('code' => -1, 'info' => $this->error, 'url' => '');
        }
        $data  = array();
        $files = $this->_dealFiles($files);
        foreach ($files as $key => $file) {
            /* 文件上传检测 */
            if (!$this->_check($file)) {
                $data[] = array('code' => -1, 'info' => $this->error, 'url' => '');
                continue;
            }
            /* 获取上传文件后缀，允许上传无后缀文件 */
            $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
            $save_path = $object . '/' . uniqid() . '.' . $ext;
            //检测文件是否存在
            while ($this->ossClient->doesObjectExist($this->bucket, $save_path)) {
                $save_path = $object . '/' . uniqid() . '.' . $ext;
            }
            try {
                $this->ossClient->uploadFile($this->bucket, $save_path, $file['tmp_name']);
                $data[] = array('code' => 1, 'info' => 'ok', 'url' => $save_path);
            } catch (OssException $e) {
                $this->_addLog($e->getMessage());
                $this->error = $e->getMessage();
                $data[]      = array('code' => -1, 'info' => $this->error, 'url' => '');
            }
        }
        return $data;
    }

    /**
     * 上传文件
     * @param $ossClient
     * @param $bucket
     */
    function uploadObject() {
        $object = date('Y') . '/' . date('md');
        $files  = $_FILES;
        if (empty($files)) {
            $this->error = '没有上传的文件！';
            return array('code' => -1, 'info' => $this->error, 'url' => '');
        }
        $file = '';
        foreach ($files as $val) {
            $file = $val;
        }
        /* 文件上传检测 */
        if (!$this->_check($file)) {
            return array('code' => -1, 'info' => $this->error, 'url' => '');
        }
        /* 获取上传文件后缀，允许上传无后缀文件 */
        $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
        $save_path = $object . '/' . uniqid() . '.' . $ext;
        //检测文件是否存在
        while ($this->ossClient->doesObjectExist($this->bucket, $save_path)) {
            $save_path = $object . '/' . uniqid() . '.' . $ext;
        }
        try {
            $this->ossClient->uploadFile($this->bucket, $save_path, $file['tmp_name']);
            $data = array('code' => 1, 'info' => 'ok', 'url' => $save_path);
        } catch (OssException $e) {
            $this->_addLog($e->getMessage());
            $this->error = $e->getMessage();
            $data        = array('code' => -1, 'info' => $this->error, 'url' => '');
        }
        return $data;
    }

    /**
     * 保存文件
     * @return array
     */
    function saveObject($path, $file_name) {
        $object = date('Y') . '/' . date('md');
        /* 获取上传文件后缀，允许上传无后缀文件 */
        $save_path = $object . '/' . $file_name;
        try {
            $this->ossClient->uploadFile($this->bucket, $save_path, $path);
            $data = array('code' => 1, 'info' => 'ok', 'url' => $save_path);
        } catch (OssException $e) {
            $this->_addLog($e->getMessage());
            $this->error = $e->getMessage();
            $data        = array('code' => -1, 'info' => $this->error, 'url' => '');
        }
        return $data;
    }

    /**
     * 保存文件
     * @return array
     */
    function deleteObject($file_name) {
        try {
            $this->ossClient->deleteObject($this->bucket, $file_name);
            $data = array('code' => 1, 'info' => 'ok');
        } catch (OssException $e) {
            $this->_addLog($e->getMessage());
            $this->error = $e->getMessage();
            $data        = array('code' => -1, 'info' => $this->error);
        }
        return $data;
    }

    /**
     * 检查上传的文件
     * @param array $file 文件信息
     */
    private function _check($file) {
        /* 文件上传失败，捕获错误代码 */
        if ($file['error']) {
            $this->_getErrorInfo($file['error']);
            return false;
        }

        /* 无效上传 */
        if (empty($file['name'])) {
            $this->error = '未知上传错误！';
            return false;
        }

        /* 检查文件大小 */
        if (!$this->_checkSize($file['size'])) {
            $this->error = '上传文件大小不符！';
            return false;
        }

        /* 通过检测 */
        return true;
    }

    /**
     * 获取错误代码信息
     * @param string $errorNo 错误号
     */
    private function _getErrorInfo($errorNo) {
        switch ($errorNo) {
            case 1:
                $this->error = '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值！';
                break;
            case 2:
                $this->error = '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值！';
                break;
            case 3:
                $this->error = '文件只有部分被上传！';
                break;
            case 4:
                $this->error = '没有文件被上传！';
                break;
            case 6:
                $this->error = '找不到临时文件夹！';
                break;
            case 7:
                $this->error = '文件写入失败！';
                break;
            default:
                $this->error = '未知上传错误！';
        }
    }

    /**
     * 检查文件大小是否合法
     * @param integer $size 数据
     */
    private function _checkSize($size) {
        return !($size > $this->config['size']) || (0 == $this->config['size']);
    }

    /**
     * 转换上传文件数组变量为正确的方式
     * @access private
     * @param array $files 上传的文件变量
     * @return array
     */
    private function _dealFiles($files) {
        $fileArray = array();
        $n         = 0;
        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                $keys  = array_keys($file);
                $count = count($file['name']);
                for ($i = 0; $i < $count; $i++) {
                    $fileArray[$n]['key'] = $key;
                    foreach ($keys as $_key) {
                        $fileArray[$n][$_key] = $file[$_key][$i];
                    }
                    $n++;
                }
            } else {
                $fileArray = $files;
                break;
            }
        }
        return $fileArray;
    }

    /**
     * 记录日志
     * @param $file_name
     * @param $data
     */
    protected function _addLog($data) {
        $path = "/home/order_log/oss-error.log";
        file_put_contents($path, var_export($data, true) . "\r\n", FILE_APPEND);
    }

}