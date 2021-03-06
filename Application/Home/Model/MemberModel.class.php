<?php
/**
 * 用户个人模型
 * @version 2014102014
 * @author Max.Yu <max@jipu.com>
 */

namespace Home\Model;

use Think\Model;
use Common\Api\UserApi;

class MemberModel extends Model{

  /**
   * 自动完成规则
   * @var array
   */
  protected $_auto = array(
    array('login', 0, self::MODEL_INSERT),
    array('reg_ip', 'get_client_ip', self::MODEL_INSERT, 'function', 1),
    array('reg_time', NOW_TIME, self::MODEL_INSERT),
    array('last_login_ip', 0, self::MODEL_INSERT),
    array('last_login_time', 0, self::MODEL_INSERT),
    array('update_time', NOW_TIME),
    array('status', 1, self::MODEL_INSERT),
  );

  protected $_validate = array(
    array('nickname','','昵称已存在，换个试试',0,'unique',3), 
  );
  /**
   * 登录指定用户
   * @param integer $uid 用户ID
   * @return boolean ture-登录成功，false-登录失败
   */
  public function login($uid){
    //检测是否在当前应用注册
    $user = $this->field(true)->find($uid);
    $Api = new UserApi();
    $info = $Api->info($uid);
    if(!$user){ //未注册
      //在当前应用中注册用户
      if(empty($info) || $info < 0 ){
          $this->error = '用户已删除！';
          return false;
      }
      $user = $this->create(array('nickname' => $info['username'], 'status' => 1));
      $user['uid'] = $uid;
      if(!$this->add($user)){
        $this->error = '前台用户信息注册失败，请重试！';
        return false;
      }
    }elseif(1 != $user['status']){
      $this->error = '用户未激活或已禁用！'; //应用级别禁用
      return false;
    }
    $user['username'] = $user['nickname'];
    //登录用户
    $this->autoLogin($user);
    //记录行为
    action_log('user_login', 'member', $uid, $uid);

    return true;
  }

  /**
   * 更新会员信息
   * @param string  $data 更新的数据
   * @return array 用户信息
   */
  public function update($data = null){
    $data = $this->create($data);
    if(!$data){
      return false;
    }
    return ($data['uid']) ? $this->save() : $this->add();
  }

  /**
   * 更新会员信息，需要支付密码
   * @param string  $data 更新的数据
   * @return array 用户信息
   */
  public function updateFields($uid, $payment_pwd, $data = null){
    if(empty($uid) || empty($payment_pwd) || empty($data)){
      $this->error = '参数错误！';
      return false;
    }
    //更新前检查用户支付密码
    if(!$this->verifyUser($uid, $payment_pwd)){
      $this->error = '验证出错：原支付密码不正确！';
      return false;
    }
    //更新用户信息
    $data = $this->create($data);
    if($data){
      return $this->where(array('uid' => $uid))->save($data);
    }
    return false;
  }

  /**
   * 获取会员信息
   * @param string  $uid 会员ID
   * @param string  $field 获取字段信息
   * @return array 用户信息
   */
  public function info($uid, $field = ''){
    $map['uid'] = $uid;
    $result = $this->where($map)->field($field)->find();
    if(is_array($result) && $result['status'] = 1){
      return $result;
    }else{
      return -1; //用户不存在或被禁用
    }
  }

  /**
   * 注销当前用户
   * @return void
   */
  public function logout(){
    session('user_auth', null);
    session('user_auth_sign', null);
    cookie('user_openid', null);
    cookie('__auto_uid__',null);
  }

  /**
   * 自动登录用户
   * @param integer $user 用户信息数组
   * @author Max.Yu <max@jipu.com>
   */
  private function autoLogin($user){
    //更新登录信息
    $data = array(
      'uid' => $user['uid'],
      'nickname' => $user['username'],
      'login' => array('exp', '`login`+1'),
      'last_login_time' => NOW_TIME,
      'last_login_ip' => get_client_ip(1),
    );

    $this->save($data);
    //记录登录SESSION和COOKIES
    $auth = array(
      'uid' => $user['uid'],
      'group_id' => M('User')->where('id='.$user['uid'])->getField('group_id'),
      'username' => $user['username'],
      'last_login_time' => $user['last_login_time'],
      'mobile' => M('User')->where('id='.$user['uid'])->getField('mobile'),
      'nickname' => $user['nickname'],
      'expire'=>3600
    );
    if (is_app()) {
      $auth['expire'] = 3600*24*365;
    }
    session('user_auth', $auth);
    session('user_auth_sign', data_auth_sign($auth));
    $_now = date('Ymd');
    $_key = 'Z^#Yu&hWx6*AoqRj';
    $mk_string = $_key.'|'.$_now.'|'.$uid;
    $e = md5($mk_string);
    $url = C('EDU_LOGIN_DOMAIN') . '/UserSync.html?u='.$uid.'&e='.$e;
    session('auto_login_url',$url);
  }

  /**
   * 验证用户支付密码
   * @param int $uid 用户id
   * @param string $password_in 支付密码
   * @return true 验证成功，false 验证失败
   * @author Max.Yu <max@jipu.com>
   */
  protected function verifyUser($uid, $password_in){
    $payment_pwd = $this->getFieldByUid($uid, 'payment_pwd');
    if(think_ucenter_md5($password_in) === $payment_pwd){
      return true;
    }
    return false;
  }

  /**
   * 获取账户余额
   * @param int $uid 用户ID
   * @return numeric 账户余额
   * @author Max.Yu <max@jipu.com>
   */
  public function getFinance($uid){
    $finance = 0.00;
    if($uid){
      $finance = $this->getFieldByUid($uid, 'finance');
      if($finance){
        return $finance;
      }
    }
    return $finance;
  }

  /**
   * 在线充值成功后更新账户余额
   * @param int uid 用户ID
   * @param numeric $amount 充值金额
   * @param $type 更新方式：inc 增加，dec 扣减，默认为增加
   * @author Max.Yu <max@jipu.com>
   */
  public function updateFinance($uid, $amount, $type = 'inc'){
    if($uid && $amount && is_numeric($amount)){
      $result = null;
      if($type == 'inc'){
        $result = $this->where(array('uid' => $uid))->setInc('finance', sprintf('%.2f', $amount));
      }elseif($type == 'dec'){
        $result = $this->where(array('uid' => $uid))->setDec('finance', sprintf('%.2f', $amount));
      }

      if($result){
        return $result;
      }else{
        return false;
      }
    }else{
      return false;
    }
  }

  /**
   * 检测手机号
   * @author Max.Yu <max@jipu.com>
   */
  public function checkMobile($mobile = ''){
    $return_data = array('status' => 0, 'info' => '非法的手机号码格式');
    //检测手机号合法性
    if(preg_match("/1[34578]{1}\d{9}$/", $mobile)){
      $user = M('User')->getByMobile($mobile);
      if($user){
        if($user['status'] == 1){
          $return_data = array(
            'status' => 1,
            'info' => '用户已存在',
            'uid' => $user['id']
          );
        }else{
          $return_data = array(
            'status' => -2,
            'info' => '用户已被禁用'
          );
        }
      }else{
        $return_data = array(
          'status' => -1,
          'info' => '用户不存在'
        );
      }
    }
    return $return_data;
  }

  /**
   * 设置手机号码
   * @author Max.Yu <max@jipu.com>
   */
  public function setMobile($mobile = '', $code = ''){
    $return_data = array('status' => 0, 'info' => '手机号不能与原号码相同');
    $res = $this->checkMobile($mobile);
   
    if($res['status'] == -1){
      if(check_ypsms_code($mobile, 2, $code)){
        if (C('TS_LOGIN')) {
          //修改用户资料
          $user_api = new UserApi();
          $rs = $user_api->tsRequest('save_user', array('uid'=>TID, 'phone'=>$mobile));
          if ($rs['status'] == 0) {
            $return_data['info'] = $rs['msg'];
            $return_data['status'] = 0;
            return $return_data;
          }
        }
        // EDU同步修改手机号码
        if (C('EDU_LOGIN')) {
          //修改用户资料
          $user_api = new UserApi();
          $rs = $user_api->eduRequest('edit_mobile', array('usid'=>TID, 'phone'=>$mobile));
          if ($rs['code'] == 0) {
            $return_data['info'] = $rs['msg'];
            $return_data['status'] = 0;
            return $return_data;
          }
        }

        $upd = M('User')->where(array('id' => UID))->setField('mobile', $mobile);
        if($upd){
          $return_data = array('status' => 1, 'info' => '更换成功，请重新登录');
          $this->logout();
        }else{
          $return_data['info'] = '修改绑定失败';
        }
      }else{
        $return_data['info'] = '短信验证码错误';
      }
    }elseif($res['status'] == 1 && $res['uid'] == UID){
      $return_data['info'] = '手机号不能与原号码相同';
    }elseif(in_array($res['status'], array(1, -2))){
      $return_data['info'] = '手机号已被占用';
    }else{
      $return_data['info'] = $res['info'];
    }
    return $return_data;
  }
  
  /**
   * 设置个人资料
   * @author Max.Yu <max@jipu.com>
   */
  public function updateProfile(){
    $nickname = I('post.nickname', '', trim);
    $avatar = $this->_get_avatar();
    $sex = I('post.sex', 0, intval);
    if(empty($nickname)){
      $this->error = '昵称不能为空！';
      return false;
    }
    if (C('TS_LOGIN')) {
        //修改用户资料
       /* $user_api = new UserApi();
        $rs = $user_api->tsRequest('save_user', array('uid'=>TID, 'uname'=>$nickname, 'sex'=> $sex));
        if ($rs['status'] == 0) {
          $this->error = $rs['msg'];
          return false;
        }*/
    }

    // 同步修改EDU个人资料
    if (C('EDU_LOGIN')) {
        //修改用户资料
        $user_api = new UserApi();
        $rs = $user_api->eduRequest('edit_info', array('usid'=>UID, 'username'=>$nickname, 'sex'=> $sex));
        if ($rs['code'] == 0) {
          $this->error = $rs['msg'];
          return false;
        }
    }
      $data =array(
          'nickname' => $nickname,
          'avatar' => $avatar,
          'sex' => $sex ,
      );
    if (!$this->create($data)){
      return false;
    }
    $res = $this->where(array('uid' => UID))->save($data);
    $res && $this->login(UID);
    return true;
  }
  
  /**
   * 获取上传的头像
   * @return string 个人头像路径
   * @author Max.Yu <max@jipu.com>
   */
  protected function _get_avatar(){
    //保存图片到服务器
    $serverid = I('post.avatar_serverid', '');
    $avatar_id = I('post.avatar_id', 0);
    if($serverid){
      $auth = new \Org\Wechat\WechatAuth(C('WECHAT_APPID'), C('WECHAT_SECRET'));
      $token = $auth->getAccessToken();
      if($token['access_token']){
        $url_path = $auth->mediaGet($serverid, $token['access_token']);
        $new_path = '/'.save_image('Avatar', $url_path,200,200);
        return $new_path;
      }
    }
    if($avatar_id){
      $path=is_numeric($avatar_id) ? get_cover($avatar_id, 'path') : $avatar_id;
      $file_path=str_replace('/Application/Home/Model','',dirname(__FILE__)).$path;
      if(file_exists($file_path))my_image_resize($file_path,$file_path,200,200);
      return $path;
    }
    return $this->getFieldByUid(UID, 'avatar');
  }

}
