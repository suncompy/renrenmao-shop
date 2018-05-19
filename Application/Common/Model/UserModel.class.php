<?php
/**
 * 用户模型
 */

namespace Common\Model;

use Think\Model;

class UserModel extends Model{

  /**
   * 自动验证规则
   * @var array
   * @author Max.Yu <max@jipu.com>
   */
  protected $_validate = array(
    array('password', 'require', -1, self::EXISTS_VALIDATE, 'regex', self::MODEL_BOTH),
    array('repassword', 'require', -2, self::EXISTS_VALIDATE, 'regex', self::MODEL_BOTH),
    array('password', '6,30', -3, self::EXISTS_VALIDATE, 'length'),
    array('repassword', 'password', -4, self::EXISTS_VALIDATE, 'confirm'),
    //array('mobile', '', -11, 0, 'unique', 1), //在新增的时候验证mobile字段是否唯一 TODO:用户被删除了还能注册
  );

  /**
   * 用户模型自动完成
   * @var array
   * @author Max.Yu <max@jipu.com>
   */
  protected $_auto = array(
    array('password', 'think_ucenter_md5', self::MODEL_BOTH, 'function'),
    array('reg_time', NOW_TIME, self::MODEL_INSERT),
    array('reg_ip', 'get_client_ip', self::MODEL_INSERT, 'function', 1),
    array('update_time', NOW_TIME),
    array('status', 'getStatus', self::MODEL_BOTH, 'callback'),
  );

  /**
   * 检测手机是不是被禁止注册
   * @param  string $mobile 手机
   * @return boolean ture - 未禁用，false - 禁止注册
   */
  protected function checkMobile($mobile){
    
  }

  /**
   * 根据配置指定用户状态
   * @return integer 用户状态
   */
  protected function getStatus(){
    return true; //TODO: 暂不限制，下一个版本完善
  }

  /**
   * 注册一个新用户
   * @param string $username 用户名
   * @param string $password 用户密码
   * @param string $email 用户邮箱
   * @param string $mobile 用户手机号码
   * @return integer 注册成功-用户信息，注册失败-错误编号
   */
  public function register($group_id, $username, $password, $type){
    $data['group_id'] = $group_id;
    ($type == 1) ? $data['email'] = $username : $data['mobile'] = $username;
    $data['password'] = $password;
    //邮箱注册，截取邮箱@前作为用户名
    $data['username'] = ($type == 1) ? $this->getUserName($username) : $username;
    //判断是否扫描带参数的微信二维码加入
    $openid = A('Home/Pay', 'Event')->getOpenId();
    if($openid){
      //获取所扫描的二维码ID
      $where['open_id'] = $openid;
      $wechat_qrcode_log_lists = M('WechatQrcodeLog')->where($where)->select();
    }
    //添加用户
    if($this->create($data)){
      $uid = $this->add(); 
      //二维码扫描方式 返现
      $union_id = $wechat_qrcode_log_lists[0]['union_id'];
      if(!empty($union_id) && C('REWARDINVITE')){
          $this->regPromote($union_id,1,$uid);
      }
      //二维码扫描方式成为其推广下级
      if(!empty($union_id)  && C('DIS_START') == 1){
          $this->regPromote($union_id,2,$uid);
      }elseif(I('union_code')){//直接输入推广码成为其下级
        $this->regPromote($union_id,3,$uid);
      }
      $this->regInvite($uid); //邀请奖励
      return $uid ? $uid : 0; //0-未知错误，大于0-注册成功
    }else{
      return $this->getError(); //错误详情见自动验证注释
    }
  }

  /**
   * 微信消息通知
   * @param 
   */
  public function toweixin($data){
    $name = $info['nickname'] = get_nickname($data['user_id']) ;
    unset($data['user_id']);
    $i = 1 ;
    foreach($data as $k=>$v){
      if($v){
        $myname = get_nickname($v);
        $info['title']  = '您好，【'.$myname.'】。'.$name.'通过扫描您的专属二维码成为您的'.$i.'级代理成员';
        $info['uid']    = $v;
        A('WechatTplMsg', 'Event')->wechatTplNotice('applyer', $info);
      }
      ++$i;
    }
  }

  /**
   * 邀请注册，奖励
   * @param int $uid 新用户ID
   */
  public function regInvite($uid){
    $invite_user = session('invite_user');
    
    if(empty($invite_user) || empty($uid)){
      return false;
    }
    $invite_uid = invite_code($invite_user['invite_code']);
    $money = C('INVITE_REWARD_MONEY');
    //合法性的邀请session
    if($invite_uid == $invite_user['invite_uid'] && $money > 0){
      //邀请返现限制人数
      $where = array(
        'invite_uid' => $invite_uid, 
        'invite_code' => $invite_user['invite_code'],
        'create_time' => array('gt', strtotime(date('Y-m-d')))
      );
      $reged = M('Invite')->where($where)->count();
      if($reged >= intval(C('INVITE_MAX_PEOPLE'))){
        return false;
      }
      //销毁邀请sessin
      session('invite_user', null);
      $invite_data = array(
        'invite_uid' => $invite_uid,
        'invite_code' => $invite_user['invite_code'],
        'reg_uid' => $uid,
        'reward_status' => 0,
        'reward_amount' => $money,
        'create_time' => time()
      );
      $invite_id = M('Invite')->add($invite_data);
      if($invite_id){
        //现金流水表-邀请注册奖励
        $in_data = array(
          'uid' => $invite_uid,
          'order_id' => $invite_id,
          'type' => 'invite_reward',
          'amount' => $money,
          'flow' => 'in',
          'memo' => '邀请注册奖励',
          'create_time' => time()
        );
        $finance_id = M('finance')->add($in_data);
        if($finance_id){
          $res = D('Home/Member')->updateFinance($invite_uid, $money, 'inc');
          if($res){
            return M('Invite')->where('id='.$invite_id)->setField('reward_status', 1);
          }
        }
      }
    }
    return false;
  }

   /**
   * 推广注册，奖励
   * @author buddha <[email address]>
   * @param  [type] $union_id 二维码
   * @param  [type] $type 类型：1、二维码推广返现 2、二维码推广下级 3、直接通过输入推广码加入
   * @param  [type] $uid 用户ID
   * @return [type]       [description]
   */
  public function regPromote( $union_id,$type,$uid ){
    switch ($type) {
      case 1:
        $un_uid = M('Union')->where('id ='.$union_id)->getField('uid');
        if($un_uid){
          $in_data = array(
            'uid' => $un_uid,
            'order_id' => '',
            'type' => 'invite_register',
            'amount' => C('REWARDINVITE'),
            'flow' => 'in',
            'memo' => '推广注册成功返现',
            'create_time' => time(),
            'status' => 1 
          );
          M('finance')->add($in_data);
          D('Home/Member')->updateFinance($un_uid, C('REWARDINVITE'), 'inc');
        }
        break; 
      case 2:
        $recomender = M('Distribution')->alias('a')->join('__UNION__ b on b.uid=a.user_id')->where('b.id='.$union_id)->field('a.*')->find();
        if($recomender){
          $userdata = array(
            'user_id'     => $uid ,
            'oneagents'   => $recomender['user_id'],
            'twoagents'   => $recomender['oneagents'],
            'threeagents' => $recomender['twoagents']
          );
          M('Distribution')->add($userdata);
          $this->toweixin($userdata);
        }
        break;
      default:
        $ulast = handle_union($uid , I('union_code'));
        $code  = union_code(I('union_code') , 1);
        if($ulast == true && $code){
          $in_data = array(
            'uid' => $code,
            'order_id' => '',
            'type' => 'invite_register',
            'amount' => C('REWARDINVITE'),
            'flow' => 'in',
            'memo' => '推广注册成功返现',
            'create_time' => time(),
            'status' => 1 
          );
          M('finance')->add($in_data);
          D('Home/Member')->updateFinance($code, C('REWARDINVITE'), 'inc');
        }
        break;
    }
  }

  /**
   * 检测用户邮箱或手机是否可以注册
   * @param string $username 邮箱或手机
   * @param string $type 邮箱或手机类型
   * @return boolean true - 可注册，false - 已注册过
   */
  public function checkUsername($username, $type){
    $map = array();
    switch($type){
      case 1:
        $map['email'] = $username;
        break;
      case 2:
        $map['mobile'] = $username;
        break;
      default:
        return false;
    }
    //获取用户数据
    $user = $this->where($map)->count();

    return $user > 0 ? true : false;
  }

  /**
   * 用户登录认证
   * @param string $username 用户名
   * @param string $password 用户密码
   * @param integer $type 用户名类型 （1-邮箱，2-手机, 3-用户名）
   * @return integer 登录成功-用户ID，登录失败-错误编号
   */
  public function login($username, $password, $type = 1){
    $map = array();
    $map['u.status'] = array('egt',0);
    switch($type){
      case 1:
        $map['u.email|u.username']    = $username;
        break;
      case 2:
        $map['u.mobile|u.username']   = $username;
        break;
      case 3:
        $map['m.nickname|u.username'] = $username;
        break;
      // case 4:
      //   $map['id'] = $username;
      //   break;
      default:
        return 0; //参数错误
    }
    /* 获取用户数据 */
    $user = $this->alias('u')->join('LEFT JOIN __MEMBER__ m on m.uid=u.id')->where($map)->field('u.*')->find();
    if(is_array($user) && $user['status']){
      /* 验证用户密码 */
      if(think_ucenter_md5($password) === $user['password'] || (C('TS_LOGIN') && $this->tsCheckPasswd($user['id'], $password))){
        $this->updateLogin($user['id']); //更新用户登录信息
        return $user['id']; //登录成功，返回用户ID
      }else{
        return -2; //密码错误
      }
    }else{
      //集成TS用户系统 Foreach
      if(C('TS_LOGIN')){
        $domain = C('TS_LOGIN_DOMAIN');
        //登录接口
        $rs = $this->tsRequest('authorize', array('login'=>$username,'password'=>$password));
        if ($rs['status'] == 1){
          $uid = $this->checkTsUser($rs['user']);
          if ($uid == 0) {
            return -1;
          } else {
            return $uid;
          }
        }else{
          $this->error = $rs['msg'];
          return -3;
        }
      }
      return -1; //用户不存在或被禁用
    }
  }

  /**
   * 获取用户信息
   * @param string  $uid 用户ID或用户名
   * @param boolean $is_username 是否使用用户名查询
   * @return array 用户信息
   */
  public function info($uid, $is_email = false){
    $map = array();
    if($is_email){ //通过邮箱获取
      $map['email'] = $uid;
    }else{
      $map['id'] = $uid;
    }
    $user = $this->where($map)->field('id, group_id, from_union_id, username, password, email, mobile, mobile_bind_status,last_login_time, last_login_ip, status')->find();
    if(is_array($user) && $user['status'] == 1){
      $user['avatar'] = ($user['avatar']) ? $user['avatar'] : __ROOT__.'/Public/'.MODULE_NAME.'/'.C('DEFAULT_THEME').'/images/avatar-default.png';
      return $user;
    }else{
      return -1; //用户不存在或被禁用
    }
  }

  /**
   * 获取用户信息
   * @param string  $uid 用户ID或用户名
   * @param boolean $is_username 是否使用用户名查询
   * @return array 用户信息
   */
  public function infoByMobile($mobile = ''){
    $map = array();
    $map['mobile'] = $mobile;
    $user = $this->where($map)->field('id,username,password,email,mobile,status')->find();
    if(is_array($user) && $user['status'] == 1){
      $user['avatar'] = ($user['avatar']) ? $user['avatar'] : __ROOT__.'/Public/'.MODULE_NAME.'/'.C('DEFAULT_THEME').'/images/avatar-default.png';
      return $user;
    }else{
      return -1; //用户不存在或被禁用
    }
  }

  /**
   * 检测用户信息
   * @param string $field  用户名
   * @param integer $type 用户名类型 1-用户邮箱，2-手机注册，3-用户名注册
   * @return integer 错误编号
   */
  public function checkField($field, $type = 1){
    $data = array();
    switch($type){
      case 1:
        $data['email'] = $field;
        break;
      case 2:
        $data['mobile'] = $field;
        break;
      case 3:
        $data['username'] = $field;
        break;
      default:
        return 0; //参数错误
    }
    return $this->create($data) ? 1 : $this->getError();
  }

  /**
   * 更新用户登录信息
   * @param integer $uid 用户ID
   */
  protected function updateLogin($uid){
    $data = array(
      'id' => $uid,
      'last_login_time' => NOW_TIME,
      'last_login_ip' => get_client_ip(1),
    );
    $this->save($data);
  }

  /**
   * 更新用户信息
   * @param int $uid 用户id
   * @param string $password 密码，用来验证
   * @param array $data 修改的字段数组
   * @return true 修改成功，false 修改失败
   * @author huajie <banhuajie@163.com>
   */
  public function updateUserFields($uid, $password, $data){
    if(empty($uid) || empty($password) || empty($data)){
      $this->error = '参数错误！';
      return false;
    }

    //更新前检查用户密码
    if(!$this->verifyUser($uid, $password) && !$this->tsCheckPasswd($uid, $password)){
      $this->error = '验证出错：密码不正确！';
      return false;
    }

    //更新用户信息
    $data = $this->create($data);
    if($data){
      return $this->where(array('id' => $uid))->save($data);
    }
    return false;
  }

  /**
   * 更新用户信息
   * @param  [type] $uid      [description]
   * @param  [type] $password [description]
   * @param  [type] $data     [description]
   * @return [type]           [description]
   */
  public function updateUserFieldsByEdu($uid, $password, $data){
    if(empty($uid) || empty($password) || empty($data)){
      return -1;//参数错误
    }

    //更新前检查用户密码
    if(!$this->verifyUser($uid, $password)){
      return -2;//验证出错：密码不正确！
    }

    //更新用户信息
    $data = $this->create($data);
    if($data){
      return $this->where(array('id' => $uid))->save($data);
    }

    return -3;//更新失败
  }
  
  /**
   * 更新数据
   * @param int $uid 用户id
   * @param string $field 字段名
   * @param string $value 字段值
   * @return true 修改成功，false 修改失败
   * @version 2015071715
   * @author Justin
   */
  function update($uid, $field, $value){
    if(empty($uid) || empty($field)){
      $this->error = '参数错误！';
      return false;
    }
    //更新用户信息
    $data = $this->create();
    if($data){
      $data[$field] = $value;
      return $this->where(array('id' => $uid))->save($data);
    }
    return false;
  }

  /**
   * 根据邮箱截取@前作为用户名
   * @return integer 用户状态
   */
  protected function getUserName($username){
    return preg_replace('/@.*/', '', $username);
  }

  /**
   * 验证用户密码
   * @param int $uid 用户id
   * @param string $password_in 密码
   * @return true 验证成功，false 验证失败
   * @author huajie <banhuajie@163.com>
   */
  protected function verifyUser($uid, $password_in){
    $password = $this->getFieldById($uid, 'password');
    if(think_ucenter_md5($password_in) === $password){
      return true;
    }
    return false;
  }

  /**
   * 检查TS用户
   * @param array $user 用户信息
   * @return integer 极铺用户id
   * @author Foreach
   */
  public function checkTsUser($user){
    $uid = $user['uid'];

    //查询关联表中是否有记录
    $ts_user = D('ts_user')->where(array('tid'=>$uid))->find();
    if ($ts_user && $ts_user['status'] > 0 ){
      return $ts_user['uid'];
    } else if ($ts_user && $ts_user['status'] <= 0){//用户禁用
      return 0;
    } else {
      //注册极铺用户
      $uid = $this->tsRegister($user);
      return $uid;
    }
  }

  /**
   * 获取TS用户信息并注册
   * @param string oauth_token
   * @param string oauth_token_secret
   * @param int uid ts用户id
   * @return integer 极铺用户id
   */
  public function getTsUser($oauth_token, $oauth_token_secret, $uid){
    $rs = $this->tsRequest('login_check', array('oauth_token'=>$oauth_token, 'oauth_token_secret'=>$oauth_token_secret, 'uid'=>$uid));
    if($rs['status'] == 1){
      $tsuser = D('TsUser')->where(array('tid'=>$uid))->find();
      if($tsuser){
        $this->tsLogin($tsuser['uid']);
        return $tsuser['uid'];
      }else{
        $uid = $this->tsRegister($rs['user']);
        $this->tsLogin($uid);
        return $uid;
      }
    }else{
      return 0;
    }
  }  


  /**
   * TS注册新用户
   * @param array $user 用户信息
   * @return integer 极铺用户id
   */
  public function tsRegister($user){
    $data['group_id'] = 0;
    $data['username'] = $user['uname'];
    $data['email'] = $user['email'] ? $user['email'] : '';
    $data['mobile'] = $user['phone'] ? $user['phone'] : '';
    $data['form'] = 'ts';
    //添加用户
    if ($this->create($data)) {
      $uid = $this->add();

      //0-未知错误，大于0-注册成功
      if ($uid) {
        //ts用户关联
        $ts_data['tid'] = $user['uid'];
        $ts_data['uid'] = $uid;
        $ts_data['password'] = $user['password'];
        $ts_data['login_salt'] = $user['login_salt'];
        D('ts_user')->add($ts_data);
        return $uid;
      }else{
        return 0;
      }
    } else {
      return $this->getError(); //错误详情见自动验证注释
    }
  }

  /**
   * Ts API
   * @param int type 类型
   * @return array 结果
   */
  public function tsRequest($type, $params){
    $domain = C('TS_LOGIN_DOMAIN');
    $params ['api_type'] = 'jipu';
    $params ['mod'] = 'Jipu';
    switch ($type) {
      case 'authorize':
        $params['act'] = 'authorize';
        break;
      case 'save_user':
        $params['act'] = 'save_user_info';
        break;
      case 'login_check':
        $params['act'] = 'login_check';
        break;
    }

    //拼接请求链接
    $url = $domain . '?' . http_build_query($params);
    $rs = file_get_contents($url);
    $rs = json_decode($rs, true);
    return $rs;
  }

    /**
   * 登录
   * @param int uid 用户id
   */
  protected function tsLogin($uid){
    //登录用户
    $member_model = D('Member');
    if ($member_model->login($uid)) { //登录用户
      //TODO:跳转到登录前页面
      //是否设置自动登录
      if ($remember == 1) {
        $remember_data = think_encrypt($username);
        //将remember_data存入数据库
        $auto_data = array(
          'uid' => $uid,
          'auto_login_token' => $remember_data
        );
        $member_model->update($auto_data);
        Cookie('__autologin__', $remember_data, C('USER_AUTO_LOGIN_DAYS') * 24 * 3600 + time());
      }
    }
  }

  /**
   * TS账号密码验证
   * @param int uid 用户id
   */
  protected function tsCheckPasswd($uid, $password){
    $ts_user = D('ts_user')->where(array('uid'=>$uid))->find();
    if($ts_user) {
      return md5(md5($password).$ts_user['login_salt']) == $ts_user['password'];
    } else {
      return false;
    }
  }

  /**
   * TS账号密码修改
   * @param int uid 用户id
   */
  public function tsUpdatePasswd($uid, $password, $login_salt){
    $data ['password'] = $password;
    $data ['login_salt'] = $login_salt;
    $rs = D('ts_user')->where(array('uid'=>$uid))->save($data);
    return $rs;
  }

   /**
   * EDU注册新用户
   * @param array $user 用户信息
   * @return integer 极铺用户id
   */
  public function eduRegister($data){
    $data['group_id'] = 0;
    $data['username'] = $data['username'];
    $data['mobile'] = $data['phone'] ? $data['phone'] : '';
    $data['from'] = 'edu';

    //检测手机号码是否已存在
    $_res = $this->checkUsername($data['mobile'],2);

    if ($_res == true) {
      return -1;
    }
    else{
      //添加用户
      if ($this->create($data)) {
        $uid = $this->add();
        //0-未知错误，大于0-注册成功
        if ( 0 < $uid) {
          $user = array('uid' => $uid, 'nickname' => $data['username'], 'status' => 1);
          if(!M('Member')->add($user)){
            return 0;
          }else{
            return $uid;
          }
        }else{
          return 0;
        }
      } else {
        return 0; 
      }
    }
    
  }
  /**
   * EDU API
   * @param  string $type   API类型
   * @param  array  $params API数组
   * @return array       数组结果
   */
  public function eduRequest( $type,$params ){
    $domain = C('EDU_LOGIN_DOMAIN');
    $params['app'] = 'api';
    $params['mod'] = 'Jipu';
    switch ($type) {
      case 'authorize':
        $params['act'] = 'authorize';
        break;
      case 'edit_pwd':   //修改密码
        $params['act'] = 'setPassword';
        $params['old_password'] = jiami($params['old_password'],'eduline');
        $params['password'] = jiami($params['password'],'eduline');
        break;
      case 'edit_mobile'://修改绑定手机号码
        $params['act'] = 'setUserInfo';
        break;
      case 'edit_info'://修改用户信息
        $params['act'] = 'setUserInfo';
        break; 
      case 'add_user':// 添加用户注册信息
        $params['act'] = 'register';
        $params['password'] = jiami($params['password'],'eduline');
        break;  
      default:
        break;
    }
    
    if ($type == 'authorize') {
      $_str = $this->eduLoginChecked($params);
      $url = $domain . $_str;
    }
    else{
      //拼接请求链接
      $url = $domain . '?' . ToUrlParams($params);
      file_put_contents('aa.txt',$url.PHP_EOL,FILE_APPEND);
    }
      $rs = file_get_contents($url);
      $rs = json_decode($rs, true);
    return $rs;
  }

  /**
   * 获取Edu用户信息并注册
   * @param  [type] $user_token [description]
   * @param  [type] $str        [description]
   * @return [type]             [description]
   */
  public function getEduUser($user_token,$str){

    $_token = 'Z^#Yu&hWx6*AoqRj';
    $_now = date('Ymd');
    $_str = $_token . '|' . $_now . '|' . $user_token;
    if (md5($_str) == $str) {
      $member_model = D('Member');
      if ($member_model->login($user_token)) { //登录用户
        return $user_token;
      }
    }
    return 0;
  }

  /**
   * edu验证函数
   * @param  [type] $params [description]
   * @return [type]         [description]
   */
  protected function eduLoginChecked( $params ){
    $edu_user = $params['login'];
    $_now = data('Ymd');
    $_key = 'Z^#Yu&hWx6*AoqRj';
    $str = md5($_key . '|' .$_now . '|' . $edu_user);
    $_str = '?u=' . $params['login'] . '&e=' .$str;

    return $_str;
  }

  /**
   * edu修改信息，同步处理
   * @param  [type] $uid    [description]
   * @param  [type] $params [description]
   * @return [type]         [description]
   */
  public function updateInfoByEdu($uid,$params){

    //检测用户数据
    $user = $this->where(array('id'=>$uid))->count();
    $map = $arr = array();
    $result = array('code'=>'','msg'=>'','data'=>'');
    if ($user) {
      if (in_array('nickname',$params) || in_array('sex',$params)) {
        
      }
      switch ($type) {
          case '1'://更换手机号码
            $map['mobile'] = $params['mobile'];
            break;
          case '2'://更换登录密码
            $res = $this->eduCheckPasswd($params['old_password'],$uid);
            if ($res) {
              $map['password'] = think_ucenter_md5($params['new_password']);
            }
            break;
          case '3'://更换昵称性别
            $arr['nickname'] = $params['nickname'];
            $arr['sex'] = $params['sex'];
            break;
          default: break;
        }
      if ($arr) {
        $member = M('member')->where(array('id'=>$uid))->save($arr);
        if ($member) {
            $result['code'] = 1;$result['data'] = array($uid);$result['msg'] = 'ok';
            return json_encode($result);
        }else{
            return json_encode($result);
        }
      }else{
        $user = $this->where(array('id'=>$uid))->save($map);
        if ($user) {
          $result['code'] = 1;$result['data'] = array($uid);$result['msg'] = 'ok';
            return json_encode($result);
        }
        else{
            return json_encode($result);
        }
      }

    }
  }

  /**
   * 检测回传的数据是否正确
   * @param  [type] $old_password [description]
   * @param  [type] $uid          [description]
   * @return [type]               [description]
   */
  public function eduCheckPasswd( $old_password,$uid ){
    $_password = jiemi($old_password,'eduline');

    return $this->verifyUser($uid,$_password);

  }
  
  
  /**
   * 通过手机号，自动创建用户
   */
  public function autoCreateUser($mobile,$from='home'){
      $pwd = get_randstr(8);
      $data['username'] = $mobile;
      $data['password'] = think_ucenter_md5($pwd);
      $data['mobile'] = $mobile;
      $data['mobile_bind_status'] = 1;
      $data['reg_time'] = NOW_TIME;
      $data['reg_ip'] = get_client_ip();
      $data['last_login_time'] = NOW_TIME;
      $data['last_login_ip'] = get_client_ip();
      $data['from'] = $from;
      $data['status'] = 1;
      $model = M('User');
      $model->startTrans();
      $res = $model->add($data);
      if($res){
          //用户自动登录
          $rst = D('Member')->login($res);
          if(!$rst){
              $model->rollback();
              $this->error = '用户新增失败！';
              return false;
          }
      }else{
          $model->rollback();
          $this->error = '新增失败！';
          return false;
      }
      $model->commit();
      return $pwd;
  }

  /**
   * 检查用户是否是推广者
   * @author buddha <[email address]>
   * @return [type] [description]
   */
  public function checkUnionInfo($uid){
    $union_info = M('Union')->where('uid='.$uid)->find();
    if (empty($union_info)) {
      return -2;//用户不存在，不是推广者
    }
    if ($union_info['status'] == '0') {
      return -3;//禁用
    }
    if (empty($union_info['qrcode_url'])) {
      $wechat = new \Org\Wechat\WechatAuth(C('WECHAT_APPID'), C('WECHAT_SECRET'));
      $res = $wechat->getQrcodeTicket($union_info['id']);
      $res_arr = json_decode($res, true);
      $union_info['qrcode_url'] = $res_arr['url'];
      $union_info['qrcode_code'] = get_qrcode($res_arr['url'], 0, 10, 1, 2);
    }
    return $union_info;
  }

}
