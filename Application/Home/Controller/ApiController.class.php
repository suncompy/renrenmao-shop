<?php
/**
 * 前台API控制器
 * @version 2014100714
 * @author Max.Yu <max@jipu.com>
 */

namespace Home\Controller;

use Think\Controller;
use Common\Api\UserApi;

class ApiController extends Controller{
  public function _initialize(){
    $config = api('Config/lists');
    C($config);
  }
  /**
   * 微信支付异步通知
   */
  public function weixinNotify(){
    //订单类型
    $order_type = I('get.order_type', 'item_order');
    //引入微信支付通用类
    import('Org.Wechat.Pay.WxPayPubHelper', '', '.php');
    //使用通用通知接口
    $notify = new \Notify_pub();
    //存储微信的回调
    $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
    $notify->saveData($xml);
    //验证签名，并回应微信
    $signBool = $notify->checkSign();
    if($signBool == FALSE){
      $notify->setReturnParameter("return_code", "FAIL"); //返回状态码
      $notify->setReturnParameter("return_msg", "签名失败"); //返回信息
    }else{
      $notify->setReturnParameter("return_code", "SUCCESS"); //设置返回码
    }
    $returnXml = $notify->returnXml();
    echo $returnXml;
    //校验Sign
    if($signBool == TRUE){
      $data = $notify->data;
      if($data["return_code"] == "SUCCESS" && $data["result_code"] == "SUCCESS"){
        //处理支付通知
        $res = A('Pay', 'Event')->afterPaySuccess($data['out_trade_no'], $data, $order_type);
        if($res == 'unpay'){
           $this->error('订单支付失败，钱已充入余额！', U('Member/order'));
        }
      }else{
        $data['get'] = I('get.');
        //订单错误结果缓存
        F('WeixinPay/notify/'.date('Ym/d/').$data['out_trade_no'].'-'.date('-His'), $data);
      }
    }
  }
  /**
   * 微信支付异步通知
   */
  public function weixinAppNotify(){
      //订单类型
      $order_type = I('get.order_type', 'item_order');
      //引入微信支付通用类
      import('Org.Wechat.Pay.WxPayPubHelper', '', '.php');
      //使用通用通知接口
      $notify = new \Notify_pub("APP");
      //存储微信的回调
      $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
      $notify->saveData($xml);
      //验证签名，并回应微信
      $signBool = $notify->checkSign();
      if($signBool == FALSE){
          $notify->setReturnParameter("return_code", "FAIL"); //返回状态码
          $notify->setReturnParameter("return_msg", "签名失败"); //返回信息
      }else{
          $notify->setReturnParameter("return_code", "SUCCESS"); //设置返回码
      }
      $returnXml = $notify->returnXml();
      echo $returnXml;
      //校验Sign
      if($signBool == TRUE){
          $data = $notify->data;
          if($data["return_code"] == "SUCCESS" && $data["result_code"] == "SUCCESS"){
              //处理支付通知
              $res = A('Pay', 'Event')->afterPaySuccess($data['out_trade_no'], $data, $order_type);
              if($res == 'unpay'){
                  $this->error('订单支付失败，钱已充入余额！', U('Member/order'));
              }
          }else{
              $data['get'] = I('get.');
              //订单错误结果缓存
              F('WeixinPay/notify/'.date('Ym/d/').$data['out_trade_no'].'-'.date('-His'), $data);
          }
      }
  }


    /**
   *  微信维权通知URL 
   */
  public function support(){
    $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
    echo 'success';
  }

  /**
   *  微信告警通知URL 
   */
  public function notice(){
    $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
    $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
    echo 'success';
  }

  /**
   * 支付宝、网银等异步通知接口
   */
  public function payNotify(){
    if(IS_POST && !empty($_POST)){
      $notify = $_POST;
    }elseif(IS_GET && !empty($_GET)){
      $notify = $_GET;
      unset($notify['order_type']);
      unset($notify['method']);
      unset($notify['apitype']);
    }else{
      exit('Access Denied');
    }
    //获取支付类型
    $payment_type = I('get.apitype');
    if(I('get.method') == 'merchant'){
      //$this->success('支付取消！', U('Member/order'));
        redirect(U('Member/order'));
    }
    //获取支付类型
    $payment_type = I('get.apitype');
    //获取订单类型
    $order_type = I('get.order_type');
    //根据支付类型设置支付接口参数
    $payment_config = A('Pay', 'Event')->setPaymentConfig($payment_type);

    //实例化支付接口
    $pay = new \Think\Pay($payment_type, $payment_config);
    //验证支付通知
    if($pay->verifyNotify($notify)){
      //获取订单支付返回信息
      $info = $pay->getInfo();
      if($info['status']){
        //处理订单信息
        $res = A('Pay', 'Event')->afterPaySuccess($info['out_trade_no'], $info, $order_type);
        if($res == 'unpay'){
           $this->error('订单支付失败，钱已充入余额！', U('Member/order'));
        }
        if(I('get.method') == 'return'){
          $url = U('Order/info', array('order_type' => $order_type, 'order_sn' => $info['out_trade_no']));
          redirect($url);
        }else{
          $pay->notifySuccess();
        }
      }else{
        F('AliPay/notify/'.date('Ym/d/').$info['out_trade_no'].'-'.date('-His'), $info);
        $this->error('抱歉，支付失败！');
      }
    }else{
      E('Access Denied');
    }
  }

  /**
   * 支付宝app支付异步通知接口
   */
  public function AliAppPayNotify(){
      if(IS_POST && !empty($_POST)){
          $notify = $_POST;
      }elseif(IS_GET && !empty($_GET)){
          $notify = $_GET;
          unset($notify['order_type']);
          unset($notify['method']);
          unset($notify['apitype']);
      }else{
          exit('Access Denied');
      }
      //获取支付类型
      $payment_type = I('get.apitype');
      if(I('get.method') == 'merchant'){
          $this->success('支付取消！', U('Member/order'));
      }
      //获取支付类型
      $payment_type = I('get.apitype');
      //获取订单类型
      $order_type = I('get.order_type');
      //根据支付类型设置支付接口参数
      $payment_config = A('Pay', 'Event')->setAppPaymentConfig($payment_type);
      //实例化支付接口
      $pay = new \Think\Pay($payment_type, $payment_config);
      //验证支付通知
      if($pay->verifyAppNotify($notify)){
          //获取订单支付返回信息
          $info = $pay->getInfo();
          if($info['status']){
              //处理订单信息
              $res = A('Pay', 'Event')->afterPaySuccess($info['out_trade_no'], $info, $order_type);
              if($res == 'unpay'){
                  $this->error('订单支付失败，钱已充入余额！', U('Member/order'));
              }
              echo "success";
          }else{
              F('AliPay/notify/'.date('Ym/d/').$info['out_trade_no'].'-'.date('-His'), $info);
              $this->error('抱歉，支付失败！');
          }
      }else{
          E('Access Denied');
      }
  }

  /**
   * 支付宝微信退款异步通知接口
   */
  public function refundNotify(){
    if(IS_POST && !empty($_POST)){
      $notify = $_POST;
    }elseif(IS_GET && !empty($_GET)){
      $notify = $_GET;
      unset($notify['method']);
      unset($notify['apitype']);
    }else{
      exit('Access Denied');
    }
    add_wechat_log($_POST, 'refund-notify-post');
    //获取支付类型
    $apitype = I('get.apitype');
    //设置支付接口参数
    $refund_notify = array(
      'notify_url' => U('Home/Api/refundNotify', array('apitype' => $apitype, 'method' => 'notify'), false, true)
    );
    $pay_config=($apitype=='alipayrefund')?'alipay':$apitype;
    $refund_config = array_merge($refund_notify, C(strtoupper($pay_config)));

    //实例化支付接口
    $pay = new \Think\Pay($apitype, $refund_config);
    add_wechat_log($notify, 'refund-notify');
    //验证支付通知
    if($pay->verifyNotify($notify)){
      //获取订单支付返回信息
      $info = $pay->getInfo();
      add_wechat_log($info, 'refund-info');
      if($info['status']){
        //更新退款订单信息
        A('Admin/Refund', 'Event')->afterRefundSuccess($info['refund_no'], $info);
        $pay->notifySuccess();
      }else{
        //F('AliPay/notify/'.date('Ym/d/').$info['out_trade_no'].'-'.date('-His'), $info);
        $this->error('抱歉，退款失败！');
      }
    }else{
      E('Access Denied');
    }
  }
  
  /**
   * 获取分类下拉JS数据
   */
  public function getTypeData($type = null){
    if(!$type){return false;}
    $cache_name = 'typeData_'.$type.'_cache';
    if(S($cache_name)){
      $content = S($cache_name);
    }else{
      $content = "var $type=[];";
      $where = array();
      if(in_array($type, array('itemCategory'))){
        $where['status'] = 1;
      }
      $list = M($type)->where($where)->order('pid asc,sort asc')->select();
      $_showlist = array();
      foreach($list as $v){
        $name = $v['name'] ? $v['name'] : $v['title'];
        $_showlist[$v['pid']][] = array('id' => $v['id'], 'name' => $name, 'pid' => $v['pid']);
      }
      foreach($_showlist as $k => $v){
        $content .= "\r\n ".$type.'['.$k.'] = '.json_encode($v).';';
      }
      S($cache_name, $content);
    }
    die($content);
  }
  
  /**
   * 访问接口可获取微信openId
   */
  public function openId(){
    //允许的域名列表
    $domain_list = array(
      'www.jipushop.com',
    );
    $call_url = I('get.call_url');
    if($call_url){
      $_pu = parse_url($call_url);
      if(empty($_pu['host']) || !in_array($_pu['host'], $domain_list)){
        $return_data = array(
          'status' => 'error',
          'msg' => 'domain not in safe list'
        );
      }else{
        $open_id = A('Pay', 'Event')->getOpenId();
        $authkey = substr(md5($open_id . md5('jipukeji_2015_uhniw')), 10, 26);
        $return_data = array(
          'status' => 'success',
          'open_id' => $open_id,
          'authkey' => $authkey,
        );
      }
    }else{
      $return_data = array(
        'status' => 'error',
        'msg' => 'call_url can not be empty'
      );
    }
    redirect($call_url.(strpos($call_url, '?') > 0 ? '&' : '?').'return_data='.json_encode($return_data));
  }
  
  /**
   * 获取购物车商品数量
   */
  public function getCartCount(){
    //初始化当前用户统计数据
    if(!is_login()){
      $user_count['cart_count'] = (cookie('__cart__') !== null) ? count(json_decode(cookie('__cart__'), true)) : 0;
    }else{
      $user_count = D('Usercount')->getUserCount(is_login());
    }
    $data = array(
      'cart_count' => $user_count['cart_count'],
      'message_count' => A('Message', 'Event')->getUnreadNum(is_login())
    );
    $this->ajaxReturn($data);
  }
  
  /**
   * 返回请求数据对应的二维码
   */
  public function qrcode(){
    $data = I('get.data', $_SERVER['HTTP_REFERER']);
    $sec_code = I('get.sec_code', 'G');
    vendor('Qrcode.Phpqrcode');
    $QRcode = new \Vendor\Qrcode();
    ob_clean();
    $QRcode->png($data, false, $sec_code, 5, 1);
  }
  
  /**
   * 获取快递100查询接口
   */
    public function getDeliveryInfo($postid = '', $type){
        if (empty($postid) && empty($type)) {
            $this->error('请完善参数！');
        }
        if($type == '百世汇通'){
            $code = 'baishiwuliu';
        }elseif($type =='邮政快递'){
            $code = 'youzhengguonei';
        }else{
            $where['name'] = array('like', "%" . $type . "%");
            $code = D('express')->where($where)->getField('code');
        }
        if(!$code ){
            $this->error('获取快递信息失败！');
        }
        $url = 'http://www.kuaidi100.com/query?type=' . $code . '&postid='.$postid;
       //   $content = read_file($url, 10, 'http://www.kuaidi100.com/');
       $content = get_url_content($url);
        $data = json_decode($content, true);
        if ($data['message'] == 'ok' && is_array($data['data'])) {
            $info['result'] = $data['data'];
            //            foreach ($info['result'] as &$v) {
            //                $v['context'] = explode('|', $v['context']);
            //                $v['context'] = $v['context'][2];
            //            }
            $info['status'] = 1;
            $this->ajaxReturn($info);
        } else {
            $this->error('获取快递信息失败！');
        }
    }
  
  /*共享用户登陆*/
  public function loginCheack(){
    $user = new UserApi;
    $info=$user->info(I('get.uid'));
    $member = D('Member')->where("`uid`='" . I('get.uid'). "'")->find();
    $sign=md5(md5($info['username'].$info['password']).$info['last_login_time']);
    if(md5($sign.I('get.time'))==I('get.sign') && $member['last_login_time']>(time()-60*60*5)){
      $re=array('code'=>200,'title'=>'同步登陆成功','body'=>$info);
    }else{
      $re=array('code'=>'1','title'=>'同步登陆验证失败,登陆超时或不在同一台电脑登陆。');
    }
    return $this->ajaxReturn($re);
  }

  /**
   * Ts APP 获取商品信息
   * @author Foreach
   */
  public function tsGoods()
  {
    $item_model = D('Item');
    $field = 'id, name, thumb';
    $order = '`is_top` DESC, `sort` ASC';
    //获取首页商品
    $list = $item_model->lists($where, $field, $order, 8);
    foreach ($list as $key => &$value) {
      unset($value['thumb']);
      unset($value['discount']);
      unset($value['buynum']);
      $value['id'] = intval($value['id']);
      $value['cover_path'] = SITE_URL . $value['cover_path'];
    }

    exit(json_encode($list));
  }


  
  /*共享用户登陆扩展方法*/
  private function _checkUserType($username){
    return checkUserType($username);
  }


  /*Test*/
  public function test(){
    $x=array_intersect(array(1,2,3),array(5,4));
    print_r($x);
  }
/*============================EDU调用API start=====================================*/
  /**
   * EDU修改用户密码，同步到极铺商城
   * @return [type]               [description]
   */
  public function editPwdByEdu(){

    $uid = I('get.uid');
    $old_password = I('get.old');
    $password = I('get.new');

    $result = array('data'=>'','code'=>0,'msg'=>'');

    if (empty($uid)) {
      $result['msg']='缺少必须参数UID';
      echo json_encode( $result );
      exit;
    }
    $model = new \Common\Model\UserModel();
    $user = M('user')->where(array('id'=>$uid))->count();

    //$res = $model->eduCheckPasswd($old_password,$uid);

    if ( $user > 0) {
        $data['password'] = jiemi($password,'eduline');
        $_old = jiemi($old_password,'eduline');
        if($model->updateUserFieldsByEdu($uid,$_old,$data) > 0){
            $result['code'] = 1;$result['data'] = array('uid'=>$uid);$result['msg'] = 'ok';
            echo json_encode($result);
            exit;
        }
        else{
          switch ($model->updateUserFieldsByEdu($uid,$_old,$data)) {
            case '-1':
              $result['msg'] = '参数错误';
              break;
            case '-2':
              $result['msg'] = '验证出错：密码不正确';
              break;
            case '-3':
              $result['msg'] = '更新失败';
              break;
            default:
              
              break;
          }
          echo json_encode($result);
          exit;
        }
    }
    else{
      echo json_encode($result);
      exit;
    }
  }
  /**
   * EDU修改用户昵称、性别、联系电话同步到极铺商城
   * @return [type]           [description]
   */
  public function editInfoByEdu(){

    $uid = I('get.uid');

    //昵称
    if (!empty(I('get.n'))) {
      $data['nickname'] = I('get.n');
    }
    //性别
    if (!empty(I('get.s'))) {
      $data['sex'] = I('get.s');
    }
    //联系方式
    if (!empty(I('get.m'))) {
      $_data['phone'] = I('get.m');
    }

    $result = array('data'=>'','code'=>0,'msg'=>'');

    if (empty($uid)) {
      $result['msg']='缺少必须参数UID';
      echo json_encode( $result );
      exit;
    }

    $member = M('member');
    $user = M('user');

    if (!empty($data)) {
      $res = $member->where(array('uid'=>$uid))->save($data);
      if ($data['nickname']) {
        $_res = $user->where(array('id'=>$uid))->save(array('username'=>$data['nickname']));
      } 
    }

    if (!empty($phone)) {
       $_res = $user->where(array('id'=>$uid))->save($_data);
    }
    
    if ( ($res !== false) || ($_res !== false )) {
      $result['code'] = 1;$result['msg']= 'ok';$result['data'] = array('uid'=>$uid);
      echo json_encode($result);
      exit;
    }
    else{
      echo json_encode($result);
      exit;
    }
  }

  /**
   * EDU修改用户注册电话，同步到极铺商城
   * @return [type]        [description]
   */
  public function editPhoneByEdu(){

    $uid = I('get.uid');
    $phone = I('get.m');

    $result = array('data'=>'','code'=>0,'msg'=>'');

    if (empty($uid)) {
      $result['msg']='缺少必须参数UID';
      echo json_encode( $result );
      exit;
    }

    $user = M('user');
    $res = $user->where(array('id'=>$uid))->save(array(
            'mobile'=>$phone,
      ));

    if ($res !== false) {
      $result['code'] = 1;$result['msg']= 'ok';$result['data'] = array('uid'=>$uid);
      echo json_encode($result);
    }
    else{
      echo json_encode($result);
    }
  }

  /**
   * EDU注册用户信息，同步到极铺商城
   * @return [type] [description]
   */
  public function registerByEdu(){

    $data['phone'] = I('get.m');
    $data['username'] = I('get.u');

    if (I('get.p')) {
      $data['password'] = jiemi(I('get.p'),'eduline');
    }else{
      $data['password'] = '';
    }
    
    $result = array('data'=>'','code'=>0,'msg'=>'');

    if (empty($data['password'])) {
      $result['msg'] = '缺少注册密码';
      echo json_encode( $result );
      exit;
    }
    
    if (empty($data['phone']) || empty($data['username'])) {
      $result['msg']='缺少用户名或注册手机号';
      echo json_encode( $result );
      exit;
    }

    $model = new \Common\Model\UserModel();
    $res = $model->eduRegister($data);
  
    if ($res > 0) {
      $result['code'] = 1;$result['msg']= 'ok';$result['data'] = array('uid'=>$res);
      echo json_encode($result);
      exit;
    }
    else{
      if($res == -1){
        $result['msg'] = '该手机号码已存在';
      }else{
        $result['msg'] = '未知错误';
      }
      
      echo json_encode($result);
      exit;
    }
  }

  /**
   * EDU登陆成功之后，商城验证
   * @return [type] [description]
   */
  public function autoLogin(){
        $u = I('get.u');
        $e = I('get.e');
        $date = date('Ymd');
        $key = 'Z^#Yu&hWx6*AoqRj';
        $mk_string = $key.'|'.$date.'|'.$u;
        $en_value = strtolower(md5($mk_string));
        
        if($en_value == strtolower($e)){
            $userInfo = M('user')->where(array('id'=>$u))->find();
            if($userInfo){
                //账号登陆过
                $res = D('Member')->login($u);
                if ($res) {
                  $this->redirect('Index/index');
                }
            }else{
              $this->redirect('Index/index');
            }
        }

  }
/*============================EDU调用API end=====================================*/

    /**
     * app分享接口《原生调用》
     * @author buddha <[email address]>
     * @return [type] [description]
     */
    public function getShareParameters(){
      $url = I('post.url','');
        if(!$url){
            return false;
        }
        $res = array();
        $pic_url = '';  //分享图片链接
        $title = C('WEB_SITE_TITLE');  //分享标题
        $desc = '';  //分享图片描述
        if(strpos(strtolower($url),"item/detail")){ //商品分享
            $id = I('post.id','');
            //查询商品图片
            $result = M("Item")->where("id='{$id}'")->field('thumb,name,summary')->find();
            $img = M('picture')->where("id='{$result['thumb']}'")->getField('path');
            $pic_url = SITE_URL.'/'.get_image_thumb($img,200,200);
            $title = $result['name'];
            $desc = $result['summary'];
        }
        if(strpos(strtolower($url),"shop/sdpcode")){ //推广联盟 动态宣传页分享
            $uid = I('request.id','');

            $arr = explode('.',$url);
            $arr[2] = $arr[2].'/share_param/1';
            $url = implode('.',$arr);

            //查询图片 昵称
            $result = M('Member')->field('nickname, avatar')->where("uid = $uid")->find();
            $pic_url = SITE_URL."/Public/Home/default-mobile/images/logo-avatar.png";
            $title ='我是'.$result['nickname'].",邀请你加入".C('WEB_SITE_TITLE');
            $desc = "全新移动正品购物轻创业平台，无忧推广，打造你的个性小店。";
        }
        if(strpos(strtolower($url),"shop/detail")){ //店铺分享
            $id = I('post.id','');
            //查询店铺图片
            $result = M("Shop")->where("uid='{$id}'")->field('logo,intro,name')->find();
            if($result['logo']){
                //分享图片处理 图片缩略图
                $img = get_image_thumb($result['logo'],200,200);
                $pic_url = SITE_URL.'/'.$img;
            }else{
                //给一个默认图片路径
                $pic_url = SITE_URL."/Public/Home/default-mobile/images/logo-avatar.png";
            }
            $title = $result['name'];
            $desc = $result['intro'] ? $result['intro'] : $result['name'];
        }
        if (strpos(strtolower($url),"member/invite")) {//我的邀请
          //title
          if (C('WEB_INVITE_TITLE')) {
            $title = C('WEB_INVITE_TITLE');
          }else{
            $title = '速速入伙'.C('WEB_SITE_TITLE').'，注册即获现金奖励！';
          }
          //DESC
          if (C('WEB_INVITE_DESC')) {
            $desc = C('WEB_INVITE_DESC');
          }else{
            $desc = '如此安心健康又有品位的'.C('WEB_SITE_TITLE').'，就要与懂生活的人一起分享，赶紧加入吧！';
          } 
          //pic         
          if (C('WEB_INVITE_LOGO')) {
            $pic_url = SITE_URL.get_cover(C('WEB_INVITE_LOGO'), 'path');
          }else{
            $pic_url = SITE_URL.'/Public/Home/default-mobile/images/logo-avatar.png';
          }
          //URL
          $invite_code = invite_code(I('post.id'));
          $url = SITE_URL.'/User/invite/s/'.$invite_code.'.html';
        }
        $client = I('post.client','');
          $wechat_config = C("WECHATAPPPAY");//微信配置
          $qq_config = ( $client == 'ios') ? C("QQ_SHARE_IOS") : C('QQ_SHARE_ANDRION');//QQ配置
          $sina_config = C("SINA_SHARE");//新浪配置
          $res['app'] = array(
              'wechat_appid'  => $wechat_config['app_id'],
              'wechat_secret' => $wechat_config['app_secret'],
              'qq_appid'      => $qq_config['app_id'],
              'qq_key'     => $qq_config['app_key'],
              'sina_appid'    => $sina_config['app_id'],
              'sina_key'   => $sina_config['app_key']
            );
        $res['url'] = $url;
        $res['pic_url'] = $pic_url;
        $res['title'] = $title;
        $res['desc'] = $desc;
        echo json_encode($res);
        die;
    }

}
