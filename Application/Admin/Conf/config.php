<?php
/**
 * Created by PhpStorm.
 * User: daipingshan
 * Date: 2018/1/22
 * Time: 11:35
 */
$config = array(
    'AUTH_ON'             => true,
    'AUTH_ID'             => array(7),
    'AUTH_COMMON'         => array('/Common/uploadImg', '/Index/index', '/Index/getMenu', '/Index/updatePassword', '/Index/main', '/Index/getConsume'),
    'TMPL_PARSE_STRING'   => array(
        '__JS__'   => '/Public/Admin/js',
        '__CSS__'  => '/Public/Admin/css',
        '__IMG__'  => '/Public/Admin/images',
        '__JSON__' => '/Public/Admin/images',
        '__LAY__'  => '/Public/Admin/layui'
    ),
    'style_ver'           => 1,
    'TMPL_ACTION_SUCCESS' => 'Common:dispatch_jump',
    'TMPL_ACTION_ERROR'   => 'Common:dispatch_jump',
);
return $config;