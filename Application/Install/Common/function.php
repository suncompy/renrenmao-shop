<?php
/**
 * 安装程序公共函数
 * @author Max.Yu <max@jipu.com>
 */

// 检测环境是否支持可写
define('IS_WRITE', APP_MODE !== 'sae');

/**
 * 系统环境检测
 * @return array 系统环境数据
 */
function check_env(){
  $items = array(
    'os' => array('操作系统', '不限制', '类Unix', PHP_OS, 'success'),
    'php' => array('PHP版本', '5.5+', '5.5+', PHP_VERSION, 'success'),
    'upload' => array('附件上传', '不限制', '2M+', '未知', 'success'),
    'gd' => array('GD库', '2.0', '2.0+', '未知', 'success'),
    'disk' => array('磁盘空间', '5M', '不限制', '未知', 'success'),
  );

  //PHP环境检测
  if($items['php'][3] < $items['php'][1]){
    $items['php'][4] = 'error';
    session('error', true);
  }

  //附件上传检测
  if(@ini_get('file_uploads'))
    $items['upload'][3] = ini_get('upload_max_filesize');

  //GD库检测
  $tmp = function_exists('gd_info') ? gd_info() : array();
  if(empty($tmp['GD Version'])){
    $items['gd'][3] = '未安装';
    $items['gd'][4] = 'error';
    session('error', true);
  }else{
    $items['gd'][3] = $tmp['GD Version'];
  }
  unset($tmp);

  //磁盘空间检测
  if(function_exists('disk_free_space')){
    $items['disk'][3] = floor(disk_free_space(INSTALL_APP_PATH) / (1024 * 1024)).'M';
  }

  return $items;
}

/**
 * 目录，文件读写检测
 * @return array 检测数据
 */
function check_dirfile(){
  $items = array(
    array('dir', '可写', 'success', './Uploads/Download'),
    array('dir', '可写', 'success', './Uploads/Picture'),
    array('dir', '可写', 'success', './Uploads/Editor'),
    array('dir', '可写', 'success', './Uploads/QRcode'),
    array('dir', '可写', 'success', './Runtime'),
    array('dir', '可写', 'success', './Data'),
    array('file', '可写', 'success', './Application/Common/Conf'),
  );

  foreach($items as &$val){
    $item = INSTALL_APP_PATH.$val[3];
    if('dir' == $val[0]){
      mkdir($item, 0777, true);
      if(!is_writable($item)){
        if(is_dir($items)){
          $val[1] = '可读';
          $val[2] = 'error';
          session('error', true);
        }else{

          $val[1] = '不存在';
          $val[2] = 'error';
          session('error', true);
        }
      }
    }else{
      if(file_exists($item)){
        if(!is_writable($item)){
          $val[1] = '不可写';
          $val[2] = 'error';
          session('error', true);
        }
      }else{
        if(!is_writable(dirname($item))){
          $val[1] = '不存在';
          $val[2] = 'error';
          session('error', true);
        }
      }
    }
  }

  return $items;
}

/**
 * 函数检测
 * @return array 检测数据
 */
function check_func(){
  $items = array(
    array('pdo', '支持', 'success', '类'),
    array('pdo_mysql', '支持', 'success', '模块'),
    array('file_get_contents', '支持', 'success', '函数'),
    array('mb_strlen', '支持', 'success', '函数'),
  );

  foreach($items as &$val){
    if(('类' == $val[3] && !class_exists($val[0])) || ('模块' == $val[3] && !extension_loaded($val[0])) || ('函数' == $val[3] && !function_exists($val[0]))
    ){
      $val[1] = '不支持';
      $val[2] = 'error';
      session('error', true);
    }
  }

  return $items;
}

/**
 * 写入配置文件
 * @param  array $config 配置信息
 */
function write_config($config, $auth){
  if(is_array($config)){
    //读取配置内容
    $conf = file_get_contents(MODULE_PATH.'Data/conf.tpl');
    //替换配置项
    foreach($config as $name => $value){
      $conf = str_replace("[{$name}]", $value, $conf);
    }

    $conf = str_replace('[AUTH_KEY]', $auth, $conf);

    //写入应用配置文件
    if(!IS_WRITE){
      return '由于您的环境不可写，请复制下面的配置文件内容覆盖到相关的配置文件，然后再登录后台。<p>'.realpath(APP_PATH).'/Common/Conf/config.php</p>
      <textarea name="" style="width:650px;height:185px">'.$conf.'</textarea>';
    }else{
      if(file_put_contents(APP_PATH.'Common/Conf/config.php', $conf)){
        show_msg('配置文件写入成功');
      }else{
        show_msg('配置文件写入失败！', 'error');
        session('error', true);
      }
      return '';
    }
  }
}

/**
 * 创建数据表
 * @param  resource $db 数据库连接资源
 */
function create_tables($db, $prefix = ''){
  //读取SQL文件
  $sql = file_get_contents(MODULE_PATH.'Data/install.sql');
  $sql = str_replace("\r", "\n", $sql);
  $sql = explode(";\n", $sql);
  //替换表前缀
  $orginal = C('ORIGINAL_TABLE_PREFIX');
  $sql = str_replace(" `{$orginal}", " `{$prefix}", $sql);
  //开始安装
  show_msg('开始安装数据库...');
  foreach($sql as $value){
    $value = trim($value);
    if(empty($value))
      continue;
    if(substr($value, 0, 12) == 'CREATE TABLE'){
      $name = preg_replace("/^CREATE TABLE `(\w+)` .*/s", "\\1", $value);
      $msg = "创建数据表{$name}";
      if(false !== $db->execute($value)){
        show_msg($msg.'...成功');
      }else{
        show_msg($msg.'...失败！', 'error');
        session('error', true);
      }
    }else{
      $db->execute($value);
    }
  }
}

/**
 * 注册创始人账号
 */
function register_administrator($db, $prefix, $admin, $auth){
  show_msg('开始注册管理员帐号...');
  $sql = "INSERT INTO `[PREFIX]user` VALUES ".
      "('1', null, '[NAME]', '[PASS]', '[EMAIL]', '',0, 0, 0, '[TIME]', '[IP]', 0, 0, '[TIME]','sys', '1')";

  $password = user_md5($admin['password'], $auth);
  $sql = str_replace(
      array('[PREFIX]', '[NAME]', '[PASS]', '[EMAIL]', '[TIME]', '[IP]'), array($prefix, $admin['username'], $password, $admin['email'], NOW_TIME, get_client_ip(1)), $sql);
  //执行sql
  $db->execute($sql);
  
  $sql = "INSERT INTO `[PREFIX]member` VALUES ".
      "('1', '', '[NAME]', '0', '0000-00-00', '', '0.00', '0', '', '', '1', '0', '[IP]' , '[TIME]', '0', '[TIME]', '', '1');";
  $sql = str_replace(array('[PREFIX]', '[NAME]', '[TIME]' , '[IP]'), array($prefix, $admin['username'], NOW_TIME,get_client_ip(1)), $sql);
  $db->execute($sql);
  show_msg('创始人帐号注册完成！');
}

/**
 * 注册开发者 ROOT账号
 */
function register_root($db, $prefix, $auth){
  show_msg('开始创建开发者帐号...');
  $sql = "INSERT INTO `[PREFIX]user` VALUES ".
      "('2', null, '[NAME]', '[PASS]', '[EMAIL]', '', 0, 0, '[TIME]', '[IP]', 0, 0, '[TIME]', '1')";

  $password = user_md5('123456', $auth);
  $sql = str_replace(
      array('[PREFIX]', '[NAME]', '[PASS]', '[EMAIL]', '[TIME]', '[IP]'), array($prefix, 'jipushop', $password, 'admin@jipushop.com', NOW_TIME, get_client_ip(1)), $sql);
  //执行sql
  $db->execute($sql);

  $sql = "INSERT INTO `[PREFIX]member` VALUES ".
      "('2', '', '[NAME]', '0', '0', '', '0.00', '0', '', '', '0', '1', '0', '[TIME]', '0', '[TIME]', '', '1');";
  $sql = str_replace(
      array('[PREFIX]', '[NAME]', '[TIME]'), array($prefix, 'jipushop', NOW_TIME), $sql);
  $db->execute($sql);
  show_msg('开发者帐号注册完成！');
}

/**
 * 更新数据表
 * @param  resource $db 数据库连接资源
 * @author lyq <605415184@qq.com>
 */
function update_tables($db, $prefix = ''){
  //读取SQL文件
  $sql = file_get_contents(MODULE_PATH.'Data/update.sql');
  if(strlen($sql) < 10 || empty($sql)){
    return false;
  }
  $sql = str_replace("\r", "\n", $sql);
  $sql = explode(";\n", $sql);

  //替换表前缀
  $sql = str_replace(" `jipushop_", " `{$prefix}", $sql);

  //开始安装
  show_msg('开始升级数据库...');
  foreach($sql as $value){
    $value = trim($value);
    if(empty($value))
      continue;
    if(substr($value, 0, 12) == 'CREATE TABLE'){
      $name = preg_replace("/^CREATE TABLE `(\w+)` .*/s", "\\1", $value);
      $msg = "创建数据表{$name}";
      if(false !== $db->execute($value)){
        show_msg($msg.'...成功');
      }else{
        show_msg($msg.'...失败！', 'error');
        session('error', true);
      }
    }else{
      if(substr($value, 0, 8) == 'UPDATE `'){
        $name = preg_replace("/^UPDATE `(\w+)` .*/s", "\\1", $value);
        $msg = "更新数据表{$name}";
      }else if(substr($value, 0, 11) == 'ALTER TABLE'){
        $name = preg_replace("/^ALTER TABLE `(\w+)` .*/s", "\\1", $value);
        $msg = "修改数据表{$name}";
      }else if(substr($value, 0, 11) == 'INSERT INTO'){
        $name = preg_replace("/^INSERT INTO `(\w+)` .*/s", "\\1", $value);
        $msg = "写入数据表{$name}";
      }
      if(($db->execute($value)) !== false){
        show_msg($msg.'...成功');
      }else{
        show_msg($msg.'...失败！', 'error');
        session('error', true);
      }
    }
  }
}

/**
 * 及时显示提示信息
 * @param  string $msg 提示信息
 */
function show_msg($msg, $class = ''){
  echo "<script type=\"text/javascript\">showmsg(\"{$msg}\", \"{$class}\")</script>";
  flush();
  ob_flush();
}

/**
 * 生成系统AUTH_KEY
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function build_auth_key(){
  $chars = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $chars .= '`~!@#$%^&*()_+-=[]{};:"|,.<>/?';
  $chars = str_shuffle($chars);
  return substr($chars, 0, 40);
}

/**
 * 系统非常规MD5加密方法
 * @param  string $str 要加密的字符串
 * @return string
 */
function user_md5($str, $key = ''){
  return '' === $str ? '' : md5(sha1($str).$key);
}

/**
 * 系统邮件发送函数
 * @param string $to 接收邮件者邮箱
 * @param array $subject 邮件标题数据
 * @param array $content 邮件内容数据
 * @return boolean
 * @author Max.Yu <max@jipu.com>
 */
function jipu_email($to, $subject = '', $content = ''){
   // $config = C('EMAIL_CONFIG');
    $config = C('JIPU_EMAIL');
    vendor('PHPMailer.Phpmailer');
    //邮件模板
    $site_tel = C('WEB_SITE_TEL');
    $site_logo = SITE_URL.'/Public/Home/images/logo.png';
    $body = '<style>a.btn-email,a.btn-email:link,a.btn-email:visited{margin:10px 0;background:#c41921;padding:10px 20px;color:#fff;width:120px;text-align:center;text-decoration:none;font-size:16px;}</style><div style="padding:10px;width:540px;border:#c41921 solid 2px;margin:0 auto"><div style="color:#bbb;overflow:hidden;zoom:1"><div style="overflow:hidden;position:relative;padding-bottom:5px;border-bottom:1px solid #ddd;"><a href="'.SITE_URL.'"><img style="border:0 none" src="'.$site_logo.'"></a></div></div><div style="background:#fff;padding:0;min-height:240px;position:relative"><div style="margin:0;font-size:14px;line-height:24px;">'.$content.'<p>如有任何疑问，请联系客服，客服热线：028-82884828'.'。</p></div></div><div style="border-top:1px dashed #ddd;background:#fff;"><div style="line-height:18px;"><p style="margin:0;padding:10px 0 0 0;color:#999;font-size:12px">您之所以收到这封邮件，是因为您曾经注册成为我们的用户。<br>本邮件由系统自动发出，请勿直接回复！<br>如果您有任何疑问或建议，请联系我们。</p></div></div></div>';
    $mail = new \Vendor\PHPMailer(); //PHPMailer对象
    $mail->CharSet = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->SMTPDebug = 0; //关闭SMTP调试功能
    $mail->isSMTP();
    $mail->SMTPAuth = true; //启用 SMTP 验证功能
    /*$mail->Host = 'smtp.qq.com'; //SMTP 服务器
    $mail->Username = $config['SEND_EMAIL']; //SMTP服务器用户名
    $mail->Password = $config['PASSWORD']; //SMTP服务器密码
    $mail->SetFrom($config['SEND_EMAIL']);*/
    $mail->MsgHTML($body);
    $mail->Host = $config['SMTP_HOST']; //SMTP 服务器
    $mail->Port = $config['SMTP_PORT']; //SMTP服务器的端口号
    $mail->Username = $config['SMTP_USER']; //SMTP服务器用户名
    $mail->Password = $config['SMTP_PASS']; //SMTP服务器密码
    $mail->SetFrom($config['FROM_EMAIL'], $config['FROM_NAME']);
    $mail->AddAddress($to);
    $mail->SMTPSecure = 'ssl';
  //  $mail->Port       = 465;
    $mail->isHTML(true);                                  // Set email format to HTML
    $mail->Subject = $subject;
   // $mail->Body    = $content;
    $mail->CharSet = 'UTF-8';

    /* if(is_array($attachment)){ //添加附件
         foreach($attachment as $file){
             is_file($file) && $mail->AddAttachment($file);
         }
     }*/

    return $mail->Send() ? true : $mail->ErrorInfo;
}