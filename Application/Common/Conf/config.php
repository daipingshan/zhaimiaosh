<?php
$config = array(
    'password_key'    => 'zm',  //密码加密key （不能修改）
    'token_key'       => 'zm',  //token加密key （不能修改）
    'sms_expire_time' => 300, //短信验证码有效期 （默认5分钟，非技术人员不能修改）

    'SHOW_PAGE_TRACE' => false,              // 显示页面Trace信息
    'LOAD_EXT_CONFIG' => 'setting',
    'COOKIE_PREFIX'   => 'zm_',
    'COOKIE_EXPIRE'   => 86400 * 7,
    'COOKIE_PATH'     => '/',
    //'COOKIE_DOMAIN'   => $_SERVER['HTTP_HOST'],
    //'配置项'=>'配置值'
    'SESSION_OPTIONS' => array(//'domain' => $_SERVER['HTTP_HOST'],
    ),
    /* URL设置 */
    'URL_MODEL'       => 2,                  //URL模式
    /*图片地址前缀*/
    'IMG_PREFIX'      => 'http://pic.taodianke.com/',

    'MODULE_ALLOW_LIST' => array('Home', 'Api', 'Admin', 'Partner', 'Data'),
    'DEFAULT_MODULE'    => 'Home',

    'DATA_CACHE_PREFIX' => 'ZM_',//缓存前缀
    'DATA_CACHE_TIME'   => 604800,//AUTH认证密码
    'DATA_CACHE_TYPE'   => 'Redis',//默认动态缓存为Redis
    /*'REDIS_HOST'            => 'r-bp1526b5feedcee4.redis.rds.aliyuncs.com', //redis服务器ip，多台用逗号隔开；读写分离开启时，第一台负责写，其它[随机]负责读；
    'REDIS_PORT'            => '6379',//端口号
    'REDIS_AUTH'            => 'Sdwl2017',//AUTH认证密码*/

    'REDIS_HOST'            => '47.96.78.2', //redis服务器ip，多台用逗号隔开；读写分离开启时，第一台负责写，其它[随机]负责读；
    'REDIS_PORT'            => '6379',//端口号
    'REDIS_AUTH'            => 'redis-pass',//AUTH认证密码
    /*自定义配置*/
    'LOAD_EXT_CONFIG'       => 'db,cache',
    /* 子域名配置 */
    'APP_SUB_DOMAIN_DEPLOY' => 1,             // 开启子域名配置
    'APP_SUB_DOMAIN_RULES'  => array(
        'api'     => 'Api',
        'admin'   => 'Admin',
        'partner' => 'Partner',
        'wx'      => 'Api',
        'jd'      => 'Api',
    ),
);
return $config;

