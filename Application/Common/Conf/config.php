<?php
/**
 * 系统配置文件模板
 * @version 2014040714
 * @author Max.Yu <max@jipu.com>
 */

//定义URL通用的URL
define('URL_CALLBACK', 'http://'.SITE_DOMAIN.U('User/callback').'&type=');

/**
 * 系统配文件
 * 所有系统级别的配置
 */
return array(
    'APP_AUTOLOAD_PATH' => '@.TagLib',
    /**
     * 模块相关配置
     */
    'AUTOLOAD_NAMESPACE' => array('Addons' => ONETHINK_ADDON_PATH), //扩展模块列表
    'DEFAULT_MODULE'     => 'Home',
    'MODULE_DENY_LIST'   => array('Common', 'Install'),
    'MODULE_ALLOW_LIST'  => array('Home', 'Admin'),

    /**
     * 系统数据加密设置
     */
    'DATA_AUTH_KEY' => '<rlvXL^B3YM~u2%|7]m9$IG_o)ADFNd:j*"J5zh&', //默认数据加密KEY

    /**
     * 用户相关设置
     */
    'USER_MAX_CACHE'     => 1000, //最大缓存用户数
    'USER_ADMINISTRATOR' => 1, //管理员用户ID

    /**
     * URL配置
     */
    'URL_CASE_INSENSITIVE' => true, //默认false 表示URL区分大小写 true则表示不区分大小写
    'URL_MODEL'            => 2, //URL模式
    'VAR_URL_PARAMS'       => '', //PATHINFO URL参数变量
    'URL_PATHINFO_DEPR'    => '/', //PATHINFO URL分割符


    /**
     * 数据库配置
     */
    'DB_TYPE'   => 'mysql', //数据库类型
    'DB_HOST'   => 'localhost', //服务器地址
    'DB_NAME'   => 'db_jipushop', //数据库名
    'DB_USER'   => 'jipushop', //用户名
    'DB_PWD'    => 'Jipu163',  //密码
    // 'DB_NAME'   => 'jipu', //数据库名
    // 'DB_USER'   => 'root', //用户名
    // 'DB_PWD'    => 'root',  //密码
    'DB_PORT'   => '3306', //端口
    'DB_PREFIX' => 'jipu_', //数据库表前缀


    /**
     * 二维码存放相关配置
     */
    'QRCODE_CONFIG' => array(
        'exts'     => 'png', //文件后缀
        'rootPath' => './Uploads/QRcode/', //保存根路径
    ),


    /**
     * 微信通过接口获取授权  域名配置
     */
    'WEXIN_API_AUTH_URL' => '[]',

    /**
     * 是否从自定义接口获取微信用户信息
     */
    'WECHAT_USERINFO_BY_API' => false,

    /**
     * 邮件配置
     */
    'THINK_EMAIL' => array(
        'SMTP_HOST' => 'smtp.163.com', //SMTP服务器
        'SMTP_PORT' => '465', //SMTP服务器端口
        'SMTP_USER' => '18782440803@163.com', //SMTP服务器用户名
        'SMTP_PASS' => 'wulai197588', //SMTP服务器密码
        'FROM_EMAIL' => '18782440803@163.com', //发件人EMAIL
        'FROM_NAME' => '[ezhu]', //发件人名称
        'REPLY_EMAIL' => '', //回复EMAIL（留空则为发件人EMAIL）
        'REPLY_NAME' => '', //回复名称（留空则为发件人名称）
    ),

    /**
     * 上传图片尺寸约定
     */
    'UPLOAD_PIC_SIZE_CONVRNTION' => array(
        //广告图片
        'AD_PIC' => array(
            1 => '宽度为：1920px，高度为：450px', //首页商品调用广告
            2 => '宽度为：200px，高度为：200px', //首页中部横幅广告
            3 => '宽度为：750px，高度为：350px', //首页底部横幅广告
        ),
        //专题图片
        'TOPIC_PIC' => array(
            'BG_PIC' => '宽度为：720px，高度为：320px',  //专题背景图片
        ),
    ),

    /**
     * 上传图片缩略图规格配置（后台上传图片的时候将根据以下配置生成对应的缩略图规格图片供前端调用）
     */
    'UPLOAD_PIC_THUMB_SIZE' => array(
        //商品图片
        'ITEM_PIC' => array(
            array(
                'WIDTH' => 400,
                'HEIGHT' => 400,
            ),
            array(
                'WIDTH' => 220,
                'HEIGHT' => 220,
            ),
            array(
                'WIDTH' => 100,
                'HEIGHT' => 100,
            ),
        ),
        //广告图片
        'AD_PIC' => array(
            'INDEX_SLIDE' => array(
                array(
                    'WIDTH' => 720,
                    'HEIGHT' => 320,
                ),
                array(
                    'WIDTH' => 100,
                    'HEIGHT' => 40,
                ),
            ),
            'INDEX_MIDDLE_BANNER' => array(
                array(
                    'WIDTH' => 720,
                    'HEIGHT' => 160,
                ),
                array(
                    'WIDTH' => 100,
                    'HEIGHT' => 40,
                ),
            ),
            'INDEX_BOTTOM_BANNER' => array(
                array(
                    'WIDTH' => 720,
                    'HEIGHT' => 160,
                ),
                array(
                    'WIDTH' => 100,
                    'HEIGHT' => 40,
                ),
            ),
        ),
        //专题图片
        'TOPIC_PIC' => array(
            array(
                'WIDTH' => 720,
                'HEIGHT' => 320,
            ),
            array(
                'WIDTH' => 120,
                'HEIGHT' => 53,
            ),
            array(
                'WIDTH' => 70,
                'HEIGHT' => 30,
            ),
        ),
    ),
    /**
     * APP微信支付配置
     */
    'WECHATAPPPAY' => array(
        'app_id' => 'wxf6d9741507f2d8fb',   //应用id
        'app_secret' => '93e86a670a3a701b726d93ae1f3611bb',   //应用key
        'mch_id' => '1480765682',   //商户id
        'app_key' => '62wx1A21b577Sp7BD04c50Jp28OvwaGl',  //api加密key
    ),
    /**
     * app分享到QQ，QQ配置 {安卓}
     */
    'QQ_SHARE_ANDRION' => array(
        'app_id' => '1106124357', //应用id
        'app_key' => 'euTWEN07rJUyyelN',    //应用key
        'app_name' => 'shop.jipu.keji.com'  //应用包名
    ),
    /**
     * app分享到QQ，QQ配置 {ios}
     */
    'QQ_SHARE_IOS' => array(
        'app_id' => '1105210037', //应用id
        'app_key' => '68dqYf6xXhFIYX5l',    //应用key
    ),
    /**
     * app分享到sina sina配置
     */
    'SINA_SHARE' => array(
        'app_id' => '1198699228', //应用id
        'app_key' => '81dceff246e0a440107b1f5be2273f2f',    //应用key
    ),
);

