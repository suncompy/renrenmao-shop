<?php
/**
 * 安装程序首页控制器
 * @version 2014040714
 * @author Max.Yu <max@jipu.com>
 */

namespace Install\Controller;

use Think\Controller;
use Think\Storage;

class IndexController extends Controller{

  /**
   * 安装首页
   * @author Max.Yu <max@jipu.com>
   */
  public function index(){
    if(is_file(APP_PATH.'Common/Conf/config.php')){
      //已经安装过了 执行更新程序
      session('update', true);
      $msg = '请删除install.lock文件后再运行升级!';
    }else{
      session('update', false);
      $msg = '已经成功安装了JipuShop，请不要重复安装!';
    }
    if(Storage::has('./Data/install.lock')){
      $this->error($msg);
    }
    $this->display();
  }

  /**
   * 安装完成
   * @author Max.Yu <max@jipu.com>
   */
  public function complete(){
    $step = session('step');
    if(!$step){
      $this->redirect('index');
    }elseif($step != 3){
      $this->redirect("Install/step{$step}");
    }
    //写入安装锁定文件
    Storage::put('./Data/install.lock', 'lock');
    if(!session('update')){
      //创建配置文件
      $this->assign('info', session('config_file'));
    }
    session('step', null);
    session('error', null);
    session('update', null);
    $this->display();
    $this->delFile();
  }

  public function delFile(){
      //删除系统安装包
      $path = substr(dirname(__FILE__),0,-18).'Install';
       delDirAndFile($path,$delDir = true);

  }


}
