<?php
/**
 * 安装程序配置文件
 * @author Max.Yu <max@jipu.com>
 */

define('INSTALL_APP_PATH', realpath('./') . '/');

return array(
  
  /**
   * URL配置
   */
  'URL_CASE_INSENSITIVE'  => true, //默认false 表示URL区分大小写 true则表示不区分大小写
  'URL_MODEL'             => 3, //URL模式 使用兼容模式安装
  'VAR_URL_PARAMS'        => '', // PATHINFO URL参数变量
  'URL_PATHINFO_DEPR'     => '/', //PATHINFO URL分割符

  'ORIGINAL_TABLE_PREFIX' => 'jipu_', //默认表前缀

  /**
   * 模板相关配置
   */
  'TMPL_PARSE_STRING' => array(
    '__IMG__' => __ROOT__ . '/Public/' . MODULE_NAME . '/images',
    '__CSS__' => __ROOT__ . '/Public/' . MODULE_NAME . '/css',
    '__JS__'  => __ROOT__ . '/Public/' . MODULE_NAME . '/js',
  ),

  'RECEIVE_EMAIL' => '540690666@qq.com',
  /**
     * 邮件配置
     */
  'JIPU_EMAIL' => array(
      'SMTP_HOST' => 'smtp.163.com', //SMTP服务器
      'SMTP_PORT' => '465', //SMTP服务器端口
      'SMTP_USER' => '18782440803@163.com', //SMTP服务器用户名
      'SMTP_PASS' => 'wulai197588', //SMTP服务器密码
      'FROM_EMAIL' => '18782440803@163.com', //发件人EMAIL
      'FROM_NAME' => '[ezhu]', //发件人名称
      'REPLY_EMAIL' => '', //回复EMAIL（留空则为发件人EMAIL）
      'REPLY_NAME' => '', //回复名称（留空则为发件人名称）
  ),

);