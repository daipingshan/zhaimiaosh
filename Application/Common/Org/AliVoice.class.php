<?php
/**
 * 阿里云语音服务
 */
namespace Common\Org;
class AliVoice {
    /**
     * 文本转语音外呼
     */
    public static function singleCallByTts($CalledNumber, $TtsCode, $TtsParam, $OutId = '') {

        $params = array ();

        // *** 需用户填写部分 ***

        // fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
        $accessKeyId = "LTAI2NQjIamFLQQY";
        $accessKeySecret = "rYcwCBuO8ht2Kp1XfKqUlxguUFghuj";

        // fixme 必填: 被叫显号
        $params["CalledShowNumber"] = "02937187096";

        // fixme 必填: 被叫显号
        $params["CalledNumber"] = $CalledNumber;

        // fixme 必填: Tts模板Code
        $params["TtsCode"] = $TtsCode;

        // fixme 选填: Tts模板中的变量替换JSON,假如Tts模板中存在变量，则此处必填
        $params["TtsParam"] = $TtsParam;

        // fixme 选填: 音量
        $params["Volume"] = 100;

        // fixme 选填: 播放次数
        $params["PlayTimes"] = 3;

        // fixme 选填: 音量, 取值范围 0~200
        $params["Volume"] = 100;

        // fixme 选填: 预留给调用方使用的ID, 最终会通过在回执消息中将此ID带回给调用方
        $params["OutId"] = $OutId;

        // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***

        if(!empty($params["TtsParam"]) && is_array($params["TtsParam"])) {
            $params["TtsParam"] = json_encode($params["TtsParam"], JSON_UNESCAPED_UNICODE);
        }

        // 此处可能会抛出异常，注意catch
        $content = self::request(
            $accessKeyId,
            $accessKeySecret,
            "dyvmsapi.aliyuncs.com",
            array_merge($params, array(
                "RegionId" => "cn-hangzhou",
                "Action" => "SingleCallByTts",
                "Version" => "2017-05-25",
            ))
            // fixme 选填: 启用https
            // ,true
        );

        return $content;
    }

    /**
     * 生成签名并发起请求
     *
     * @param $accessKeyId string AccessKeyId (https://ak-console.aliyun.com/)
     * @param $accessKeySecret string AccessKeySecret
     * @param $domain string API接口所在域名
     * @param $params array API具体参数
     * @param $security boolean 使用https
     * @return bool|\stdClass 返回API接口调用结果，当发生错误时返回false
     */
    public static function request($accessKeyId, $accessKeySecret, $domain, $params, $security=false) {
        $apiParams = array_merge(array (
            "SignatureMethod" => "HMAC-SHA1",
            "SignatureNonce" => uniqid(mt_rand(0,0xffff), true),
            "SignatureVersion" => "1.0",
            "AccessKeyId" => $accessKeyId,
            "Timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
            "Format" => "JSON",
        ), $params);
        ksort($apiParams);

        $sortedQueryStringTmp = "";
        foreach ($apiParams as $key => $value) {
            $sortedQueryStringTmp .= "&" . self::encode($key) . "=" . self::encode($value);
        }

        $stringToSign = "GET&%2F&" . self::encode(substr($sortedQueryStringTmp, 1));

        $sign = base64_encode(hash_hmac("sha1", $stringToSign, $accessKeySecret . "&",true));

        $signature = self::encode($sign);

        $url = ($security ? 'https' : 'http')."://{$domain}/?Signature={$signature}{$sortedQueryStringTmp}";

        try {
            $content = self::fetchContent($url);
            return json_decode($content);
        } catch( \Exception $e) {
            return false;
        }
    }

    public static function encode($str)
    {
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;
    }

    public static function fetchContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "x-sdk-client" => "php/2.0.0"
        ));

        if(substr($url, 0,5) == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $rtn = curl_exec($ch);

        if($rtn === false) {
            trigger_error("[CURL_" . curl_errno($ch) . "]: " . curl_error($ch), E_USER_ERROR);
        }
        curl_close($ch);

        return $rtn;
    }
}