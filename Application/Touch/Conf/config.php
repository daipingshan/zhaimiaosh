<?php
$config = array(
    //模板样式路径配置
    'TMPL_PARSE_STRING' => array(
        '__JS__'    => C('IMG_PREFIX') . '/static/Touch/js',
        '__CSS__'   => C('IMG_PREFIX') . '/static/Touch/css',
        '__IMG__'   => C('IMG_PREFIX') . '/static/Touch/images',
    ),
    'version'           => '2',
);
return $config;

