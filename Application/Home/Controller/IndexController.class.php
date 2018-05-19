<?php
/**
 * 前台首页控制器
 * 主要获取首页聚合数据
 * @version 2014091618
 * @author Max.Yu <max@jipu.com>
 */

namespace Home\Controller;

class IndexController extends HomeController{

  //系统首页
  public function index(){
      if(!is_login()){
        //自动登录
        $auto_uid = cookie('__auto_uid__');
        if($auto_uid){
            $uid =explode('.',think_decrypt($auto_uid));
            $uid = end($uid);
            D('Member')->login($uid);
        }
    }
    if(isset($_GET['is_app']) && $_GET['is_app'] == 'y'){
          session('IS_APP',true);
    }
    Cookie('__forward__', $_SERVER['REQUEST_URI']);
    $share = array(
      'title' => C('WECHAT_INDEX_SHARE_TITLE'),
      'desc' => C('WECHAT_INDEX_SHARE_DESC'),
      'img_url' => SITE_URL.__IMG__.'/logo-avatar.png',
      'link' => SITE_URL.U('Index/index', array('sdp_secret' => SHOP_SECRET))
    );

    $this->meta_share = $share;
    $this->display();
  }

  public function ui(){
    $this->display();
  }
  
}
