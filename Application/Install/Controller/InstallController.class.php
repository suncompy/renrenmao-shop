<?php
/**
 * 安装程序控制器
 * @version 2014040714
 * @author Max.Yu <max@jipu.com>
 */

namespace Install\Controller;

use Think\Controller;
use Think\Db;
use Think\Storage;

class InstallController extends Controller{

  protected function _initialize(){
    if(Storage::has('./Data/install.lock')){
      $this->error('已经成功安装了JipuShop，请不要重复安装!');
    }
  }

  /**
   * 安装第一步，检测运行所需的环境设置
   * @author Max.Yu <max@jipu.com>
   */
  public function step1(){
    session('error', false);

    //环境检测
    $env = check_env();

    //目录文件读写检测
    if(IS_WRITE){
      $dirfile = check_dirfile();
      $this->assign('dirfile', $dirfile);
    }

    //函数检测
    $func = check_func();

    session('step', 1);

    $this->assign('env', $env);
    $this->assign('func', $func);
    $this->display();
  }

  /**
   * 安装第二步，创建数据库
   * @author Max.Yu <max@jipu.com>
   */
  public function step2($db = null, $admin = null){

    $config = C('EMAIL_CONFIG');
      if(IS_POST){
      //检测管理员信息
      if(!is_array($admin) || empty($admin[0]) || empty($admin[1])){
        $this->error('请填写完整管理员信息');
      } else if($admin[1] != $admin[2]){
        $this->error('确认密码和密码不一致');
      }else{
        $info = array();
        list($info['username'], $info['password'], $info['repassword'], $info['email'])
        = $admin;
        //缓存管理员信息
        session('admin_info', $info);
      }

      //检测数据库配置
      if(!is_array($db) || empty($db[0]) ||  empty($db[1]) || empty($db[2]) || empty($db[3])){
        $this->error('请填写完整的数据库配置');
      }else{
        $DB = array();
        list($DB['DB_TYPE'], $DB['DB_HOST'], $DB['DB_NAME'], $DB['DB_USER'], $DB['DB_PWD'],
           $DB['DB_PORT'], $DB['DB_PREFIX']) = $db;
        //缓存数据库配置
        session('db_config', $DB);
        //创建数据库
        $dbname = $DB['DB_NAME'];
        unset($DB['DB_NAME']);
        $db = Db::getInstance($DB);
        $sql = "CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8";
        $db->execute($sql) || $this->error($db->getError());
      }
      $ip = get_ip();
      $title = I('request.title');
      $server = $_SERVER['HTTP_HOST'];
      $content = '您好，ip为'.$ip.'的用户正在安装极铺电商系统，网站域名为'.$server.',网站标题为'.$title.'。';
      $to = C('RECEIVE_EMAIL');
      $subject = '极铺电商系统安装提示';
      jipu_email($to,$subject,$content);
      //跳转到数据库安装页面
      $this->redirect('step3');
    }else{
      if(session('update')){
        session('step', 2);
        $this->display('update');
      }else{
        session('error') && $this->error('环境检测没有通过，请调整环境后重试！');

        $step = session('step');
        if($step != 1 && $step != 2){
          $this->redirect('step1');
        }

        session('step', 2);
        $this->display();
      }
    }
  }

  /**
   * 安装第三步，安装数据表，创建配置文件
   * @author Max.Yu <max@jipu.com>
   */
  public function step3(){
    if(session('step') != 2){
      $this->redirect('step2');
    }

    $this->display();

    if(session('update')){
      $db = Db::getInstance();

      //更新数据表
      if(!update_tables($db, C('DB_PREFIX'))){
        $this->error('无法升级，请联系开发人员....');
      }
    }else{
      //连接数据库
      $dbconfig = session('db_config');
      $db = Db::getInstance($dbconfig);
      //创建数据表
      create_tables($db, $dbconfig['DB_PREFIX']);
      //注册创始人帐号
      $auth = build_auth_key();
      $admin = session('admin_info');
      // 默认管理员是开发者，拥有最高权限
      $admin['email'] = 'jipushop@jipushop.com';
      register_administrator($db, $dbconfig['DB_PREFIX'], $admin, $auth);
      //注册开发者帐号
      //register_root($db,$dbconfig['DB_PREFIX'],$auth);
      //创建配置文件
      $conf = write_config($dbconfig, $auth);
      session('config_file',$conf);
    }

    if(session('error')){
      //show_msg();
    }else{
      session('step', 3);
      $this->redirect('Index/complete');
    }
  }
}
