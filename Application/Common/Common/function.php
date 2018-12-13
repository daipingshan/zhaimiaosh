<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/17
 * Time: 17:56
 */

/**
 * @param $mobile
 * @return bool
 */
function is_mobile($mobile) {
    return preg_match('/^1\d{10}$/', $mobile) ? true : false;
}

/**
 * @param $mobile
 * @return string
 */
function hide_mobile($mobile) {
    return substr($mobile, 0, 3) . "****" . substr($mobile, -4, 4);
}

/**
 * @param $mobile
 * @param $code
 * @param string $sign
 * @return array
 */
function send_sms($mobile, $code, $sign = "宅喵生活") {
    if (!is_mobile($mobile)) {
        return array('status' => 0, 'info' => '手机号码格式不正确！');
    }
    if (!preg_match('/^\d{4}$/', $code)) {
        return array('status' => 0, 'info' => '验证码格式不正确！');
    }
    $smm_num       = "SMS_109420386"; // 短信模板编号
    $templateParam = array("code" => $code);
    $result        = Common\Org\AliSms::sendSms($sign, $smm_num, $mobile, $templateParam);
    $res           = object_to_array($result);
    if (strtolower($res['Code']) == 'ok') {
        return array('status' => 1, 'info' => '发送成功');
    } else {
        return array('status' => 0, 'info' => '发送失败！');
    }
}

/**
 * @param $mobile
 * @param $code 验证码
 * @param string $type 注册register 忘记密码forget 设置提现账号set_bank_account
 * @return array
 */
function send_voice($mobile, $code, $type = 'register', $app_name = "宅喵生活") {
    if (!is_mobile($mobile)) {
        return array('status' => 0, 'info' => '手机号码格式不正确！');
    }

    if (!preg_match('/^\d{4}$/', $code)) {
        return array('status' => 0, 'info' => '验证码格式不正确！');
    }

    $template_code  = "TTS_134895012"; // 模板编号
    if ($type == 'forget') {
        $template_code  = "TTS_134895010";
    } else if ($type == 'set_bank_account') {
        $template_code  = "TTS_134895009";
    }

    $template_param = array('product' => $app_name, "code" => $code);
    $result         = \Common\Org\AliVoice::singleCallByTts($mobile, $template_code, $template_param);
    $res            = object_to_array($result);

    if (strtolower($res['Code']) == 'ok') {
        return array('status' => 1, 'info' => '发送成功');
    } else {
        return array('status' => 0, 'info' => '发送失败！');
    }
}

/**
 * @param $e
 *
 * @return array|void
 */
function object_to_array($e) {
    $e = (array)$e;
    foreach ($e as $k => $v) {
        if (gettype($v) == 'resource')
            return;
        if (gettype($v) == 'object' || gettype($v) == 'array')
            $e[$k] = (array)object_to_array($v);
    }
    return $e;
}

/**
 * 格式化价格
 *
 * @param $price     原始金额
 * @param int $point 保留几位小数
 * @param int $rate  分成比例
 * @return float
 */
function computed_price($price, $point = 2, $rate = 50, $mall_platform = 'taobao') {
    $real_commission_rate = 0.9;
    if ('pinduoduo' == $mall_platform) {
        $real_commission_rate = 1;
    }
    return format_view_money(round($price * $real_commission_rate * $rate / 100, $point));
}

/**
 * 格式化显示金额
 * 整数显示整数 有一位小数的就显示一位小数 有两位的就显示两位小数
 */
function format_view_money($money) {
    $money = round($money, 2);
    $int_money = intval($money);
    if ($money == $int_money) {
        return $int_money;
    } else {
        $a_decimal_money = round($money, 1);
        if ($money == $a_decimal_money) {
            return $a_decimal_money;
        } else {
            return $money;
        }
    }
}

/**
 * 格式化URL，使所有http变为https
 *
 * 为保证小程序能下载图片。修改了京东拼多多的域名
 */
function format_url_to_https($string) {
    if (is_string($string)) {
        $pos = stripos($string, 't00img.yangkeduo.com');
        if (false === $pos) {
            return str_ireplace(array('http://', 'https://m.360buyimg.com') , array('https://', 'https://img14.360buyimg.com'), $string);
        } else {
            return 'https://' . substr($string, $pos + 21, 6) . '.yangkeduo.com' . substr($string, $pos + 27);
        }
    } else if (is_array($string)) {
        return array_map(function($item) {
            $pos = stripos($item, 't00img.yangkeduo.com');
            if (false === $pos) {
                return str_ireplace(array('http://', 'https://m.360buyimg.com') , array('https://', 'https://img14.360buyimg.com'), $item);
            } else {
                return 'https://' . substr($item, $pos + 21, 6) . '.yangkeduo.com' . substr($item, $pos + 27);
            }
        }, $string);
    } else {
        return $string;
    }
}

/**
 * 生成邀请码
 */
function create_invite_code() {
    $str_arr = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'm', 'n', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
    $str_len = count($str_arr) - 1;
    $output  = '';
    for ($i = 0; $i < 3; $i++) {
        $output .= $str_arr[mt_rand(0, $str_len)];
    }
    $num_arr = range(0, 9);
    $num_len = count($num_arr) - 1;
    for ($i = 0; $i < 4; $i++) {
        $output .= $num_arr[mt_rand(0, $num_len)];
    }
    return $output;
}

/**
 * 获取图片路径
 *
 * @param $path
 * @return string
 */
function get_img_url($path) {
    if (!trim($path)) {
        return '';
    }
    if (strpos($path, 'http') !== false) {
        return $path;
    }
    return C('IMG_PREFIX') . $path;
}

/**
 * 用户昵称下更多提示语
 *
 * @param $level
 * @param $invite_num
 * @return mixed
 */
function get_more_tip($level, $invite_num) {
    $r = mt_rand(0, 5);
    $data = C('COMMISSION_RATE');
    switch ($level) {
        case 0 :
            if ($invite_num > 0) {
                $tip = "等待管理员审核，审核通过后即可获取收益";
            } else {
                $tip = "邀请注册1人即可申请成为推广员，享受更低价格和平台补贴";
            }
            break;
        case 1 :
            $num     = C('COMMISSION_RATE.v2_people_num');
            $tmp_num = $num - $invite_num;
            $tip = '尊敬的V1推广员，您只需再邀请' . $tmp_num . '位新用户就可以升级到V2推广员了，成为V2推广员每单可多得' . $data['v2_award'] / $data['base_commission_rate'] * 100 . '%奖励，V2推广员平均周收益约' . ($data['v2_week_income_money'] + $r) . '元，赶快去邀请吧！';
            break;
        case 2 :
            $num     = C('COMMISSION_RATE.v3_people_num');
            $tmp_num = $num - $invite_num;
            $tip = '尊敬的V2推广员，您只需再邀请' . $tmp_num . '位新用户就可以升级到V3推广员了，成为V3推广员每单可多得' . $data['v3_award'] / $data['base_commission_rate'] * 100 . '%奖励，V3推广员平均周收益约' . ($data['v3_week_income_money'] + $r) . '元，赶快去邀请吧！';
            break;
        case 3 :
            $num     = C('COMMISSION_RATE.v4_people_num');
            $tmp_num = $num - $invite_num;
            $tip = '尊敬的V3推广员，您只需再邀请' . $tmp_num . '位新用户就可以升级到V4推广员了，成为V4推广员每单可多得' . $data['v4_award'] / $data['base_commission_rate'] * 100 . '%奖励，V4推广员平均周收益约' . ($data['v4_week_income_money'] + $r) . '元，赶快去邀请吧！';
            break;
        case 4 :
            $num     = C('COMMISSION_RATE.group_leader_num');
            $tmp_num = $num - $invite_num;
            $r = mt_rand(3, 20);
            if ($tmp_num > 0) {
                $tip = '尊敬的V4推广员，您只需再邀请' . $tmp_num . '位新用户就可以升级到团长了，成为团长后可得到团队所有成员下单的' . $data['group_leader'] / $data['base_commission_rate'] * 100 . '%奖励，团长平均周收益约' . ($data['group_leader_week_income_money'] + $r) . '元，赶快去邀请吧！';
            } else {
                $tip = '尊敬的V4推广员，间接邀请人数只需达到' . $data['group_leader_parent_num'] . '就可以升级到团长了，成为团长后可得到团队所有成员下单的' . $data['group_leader'] / $data['base_commission_rate'] * 100 . '%奖励，团长平均周收益约' . ($data['group_leader_week_income_money'] + $r) . '元，赶快去让下级积极邀请新用户吧！';
            }
            break;
        case 5 :
            $tip = '';
            break;
        default :
            if ($invite_num > 0) {
                $tip = "等待管理员审核，审核通过后即可获取收益";
            } else {
                $tip = "邀请注册1人即可申请成为推广员，享受更低价格";
            }
            break;
    }
    return $tip;
}

/**
 * 邀请提示语
 *
 * @param $level
 * @param $invite_num
 * @return mixed
 */
function get_invite_tip($level, $invite_num) {
    $base_rate = C('COMMISSION_RATE.base_commission_rate');
    switch ($level) {
        case 0 :
            $tip = C('BASE.invite_tip');
            break;
        case 1 :
            $num             = C('COMMISSION_RATE.v2_people_num');
            $rate            = C('COMMISSION_RATE.v2_award');
            $commission_rate = $rate / ($base_rate / 100);
            $tmp_num         = $num - $invite_num;
            $tip             = '升级V2推广员，每单再奖' . $commission_rate . "%";
            break;
        case 2 :
            $num             = C('COMMISSION_RATE.v3_people_num');
            $rate            = C('COMMISSION_RATE.v3_award');
            $commission_rate = $rate / ($base_rate / 100);
            $tmp_num         = $num - $invite_num;
            $tip             = '升级V3推广员，每单再奖' . $commission_rate . "%";
            break;
        case 3 :
            $num             = C('COMMISSION_RATE.v4_people_num');
            $rate            = C('COMMISSION_RATE.v4_award');
            $commission_rate = $rate / ($base_rate / 100);
            $tmp_num         = $num - $invite_num;
            $tip             = '升级V4推广员，每单再奖' . $commission_rate . "%";
            break;
        case 4 :
            $num             = C('COMMISSION_RATE.group_leader_num');
            $rate            = C('COMMISSION_RATE.group_leader');
            $commission_rate = $rate / ($base_rate / 100);
            $tmp_num         = $num - $invite_num;
            if ($tmp_num > 0) {
                $tip = '升级为团长，获得所有下线的' . $commission_rate . "%";
            } else {
                $tip = '';
            }
            break;
        case 5 :
            $tip = '';
            break;
        default :
            $tip = C('BASE.invite_tip');
            break;
    }
    return $tip;
}

/**
 * 获取用户身份
 *
 * @param $level
 * @return string
 */
function get_level($level) {
    $level_arr = array('消费者', 'V1推广员', 'V2推广员', 'V3推广员', 'V4推广员', '团长');
    if (isset($level_arr[$level])) {
        return $level_arr[$level];
    } else {
        return '消费者';
    }
}

/**
 * 把长链接转换为短链接
 *
 * @param $long_url
 * @return string
 */
function get_short_url($long_url) {
    $httpObj          = new \Common\Org\Http();
    $httpObj->timeOut = 3;
    $time             = time();
    if (true) {//$time % 2 == 0
        //  新浪url
        //  新浪url
        $sina_url   = 'http://api.t.sina.com.cn/short_url/shorten.json';
        $sina_param = array('source' => '3271760578', 'url_long' => $long_url);
        $tmp        = json_decode($httpObj->get($sina_url, $sina_param), true);
        $short_url  = isset($tmp[0]['url_short']) ? $tmp[0]['url_short'] : urldecode($long_url);
    } else {
        // 生成短链接url 缩我  get
        $suo_url   = 'http://suo.im/api.php';
        $suo_param = array('url' => urldecode($long_url));
        $tmp       = $httpObj->get($suo_url, $suo_param);
        $short_url = $tmp ? : urldecode($long_url);
    }
    return $short_url;
}

/**
 * @param $html
 * @param $star
 * @param $end
 * @return mixed
 */
function get_word($html, $star, $end) {
    $wd  = '';
    $pat = '/' . $star . '(.*?)' . $end . '/s';
    if (preg_match_all($pat, $html, $mat)) {
        $wd = $mat[1][0];
    }
    return $wd;
}

/**
 * @param $cookie
 * @return array
 */
function get_cookie($cookie) {
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
 * 下载xls文件
 *
 * @param type $data
 * @param type $key_name
 * @param type $name
 */
function download_xls($data, $key_name, $name = 'data_xls') {
    $csv = implode(",", array_values($key_name)) . "\n";
    foreach ($data as $item) {
        foreach ($key_name as $key => $value) {
            $csv .= str_ireplace(',', ' ', $item[$key]) . ',';
        }

        $csv .= "\n";
    }

    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");
    header('Content-Disposition: attachment; filename="' . $name . '.csv"');
    header("Content-Transfer-Encoding: binary");
    die(mb_convert_encoding($csv, 'GBK', 'UTF-8'));
}

/**
 * 二维数组排序
 * @param $array
 * @param $field
 * @param string $sort
 * @return mixed
 */
function array_sequence($array, $field, $sort = 'SORT_DESC') {
    $arr_sort = array();
    foreach ($array as $uniqid => $row) {
        foreach ($row as $key => $value) {
            $arr_sort[$key][$uniqid] = $value;
        }
    }
    array_multisort($arr_sort[$field], constant($sort), $array);
    return $array;
}

/**
 * 截取字符串（中文）
 * @param $str
 * @param int $start
 * @param $length
 * @param string $charset
 * @param string $suffix
 * @return string
 */
function substr_text($str, $start = 0, $length, $charset = "utf-8", $suffix = "") {
    if (function_exists("mb_substr")) {
        return mb_substr($str, $start, $length, $charset) . $suffix;
    } elseif (function_exists('iconv_substr')) {
        return iconv_substr($str, $start, $length, $charset) . $suffix;
    }
    $re['utf-8']  = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
    $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
    $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
    $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
    preg_match_all($re[$charset], $str, $match);
    $slice = join("", array_slice($match[0], $start, $length));
    return $slice . $suffix;
}