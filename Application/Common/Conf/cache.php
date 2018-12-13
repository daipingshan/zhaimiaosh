<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/17
 * Time: 11:59
 */

$data = S('config');
if (!$data) {
    $content = M('config')->getFieldById(1, 'content');
    $data    = unserialize($content);
    if ($data) {
        S('config', $data);
    } else {
        $data = array();
    }
}
return $data;