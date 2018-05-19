<?php
/**
 * 后台用户控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */

namespace Admin\Controller;

use Common\Api\UserApi;

class UserController extends AdminController{
    //用户来源
    public $from = array(
        ''     => '系统',
        'home' => '<span class="text-danger">官网</span>',
        'ts'   => '<span class="text-danger">thinksns</span>',
        'edu'  => '<span class="text-danger">eduline</span>'
    );

  /**
   * 用户管理首页
   * @author 麦当苗儿 <zuojiazi@vip.qq.com>
   */
  public function index($keyword = ''){
    //根据昵称过滤uid
    if($keyword){
      if(strpos($keyword, '=') === false){
        $member_search = array(
          'nickname' => array('LIKE', '%'.$keyword.'%'),
        );
        $member = D('Member')->lists($member_search, 'uid DESC', 'uid');
        $shop_search = array('name'=>array('LIKE','%'.$keyword.'%'));
        $shop = M('Shop')->field('uid')->where($shop_search)->order('uid DESC')->select();
        if($member){
          $uid_mem_arr = get_sub_by_key($member, 'uid');
          $map['u.id'] = array('IN', $uid_mem_arr);
        }
       if ($shop) {
          $uid_shop_arr = get_sub_by_key($shop, 'uid');
          if ($uid_mem_arr) {
            $uids = array_merge($uid_mem_arr,$uid_shop_arr);
            $map['u.id'] = array('IN',$uids);
          }else{
            $map['u.id'] = array('IN',$uid_shop_arr);
          }
        }
      }else{
        $map['u.id'] = array('IN', str_replace('=', '', $keyword));
      }
    }
    $map['u.id|u.username|u.mobile'] = array('like', '%'.$keyword.'%');
    $map['_logic'] = 'OR';
    $map_st['u.status'] = array('egt', 0);
    $map_st['_complex'] = $map;
    $prefix = C('DB_PREFIX');
    $l_table = $prefix.'user';
    $r_table = $prefix.'member';
    $d_table = $prefix . 'distribution';
    $model = M()->table($l_table.' u')->join($r_table.' m ON u.id = m.uid','LEFT')->join($d_table . ' d ON u.id = d.user_id','LEFT');
    $order = '';
    if(I('get._field') && I('get._order')){
      $order = 'm.'.I('get._field').' '.I('get._order');
    }
    $list = $this->lists($model, $map_st, $order, 'u.*, u.status, m.finance, m.score, m.login, m.avatar,d.oneagents', null, null);
    //查询推荐人手机
      $userTable = M('user');
      foreach ($list as &$val) {
          if ($val['oneagents']) {
              $val['agents'] = $userTable->where(array('id' => $val['oneagents']))->getField('mobile');
              $count =  M('Union')->where('uid='.$val['oneagents'])->count();
              $val['is_union'] = ($count > 0) ? 1 : 0;
          } else {
              $val['agents'] = '';
              $val['is_union'] = 0;
          }
      }
      unset($val);
    int_to_string($list,$statusText = array('from' => $this->from));
    int_to_string($list);
    //记录当前列表页的Cookie
    Cookie('__forward__', $_SERVER['REQUEST_URI']);
    $this->setListOrder();
    $this->assign('_list', $list);
    $this->meta_title = '用户列表';
    $this->display();
  }

  /**
   * 用户个人信息详情
   * @author Max.Yu <max@jipu.com>
   */
  public function view($id){
    $user_api = new UserApi();
    $data['user'] = $user_api->info($id);
    $data['member'] = M('Member')->where('uid = '.$id)->find();
    $data['order'] = M('Order')->where(array('uid' => $id, 'status' => 1))->count();
    $this->assign('data', $data);
    $this->meta_title = '用户信息';
    $this->display();
  }

  /**
   * 用户回收站
   * @author Max.Yu <max@jipu.com>
   */
  public function recycle(){
    $map['u.status'] = array('lt', 0);
    //根据昵称过滤uid
    $keyword = I('request.keyword');
     if($keyword){
         $map['_string'] = '(m.nickname like "%'.$keyword.'%") OR (u.username like "%'.$keyword.'%")';
     }
    $prefix = C('DB_PREFIX');
    $l_table = $prefix.'user';
    $r_table = $prefix.'member';
    $model = M()->table($l_table.' u')->join($r_table.' m ON u.id = m.uid');
    $order = 'update_time desc';
    $list = $this->lists($model, $map, $order, 'u.*, u.status,u.username,m.nickname, m.finance, m.score, m.login, m.avatar', null, null);
    int_to_string($list);

    //记录当前列表页的Cookie
    Cookie('__forward__', $_SERVER['REQUEST_URI']);
    $this->assign('list', $list);
    $this->meta_title = '用户回收站';
    $this->display();
  }

  /**
   * 修改昵称初始化
   * @author huajie <banhuajie@163.com>
   */
  public function updateNickname(){
    $nickname = M('Member')->getFieldByUid(UID, 'nickname');
    $this->assign('nickname', $nickname);
    $this->meta_title = '修改昵称';
    $this->display();
  }

  /**
   * 修改昵称提交
   * @author huajie <banhuajie@163.com>
   */
  public function submitNickname(){
    //获取参数
    $nickname = I('post.nickname');
    $password = I('post.password');
    empty($nickname) && $this->error('请输入昵称');
    empty($password) && $this->error('请输入密码');

    //密码验证
    $User = new UserApi();
    $uid = $User->login(UID, $password, 4);
    ($uid == -2) && $this->error('密码不正确');

    $Member = D('Member');
    $data = $Member->create(array('nickname' => $nickname));
    if(!$data){
      $this->error($Member->getError());
    }

    $res = $Member->where(array('uid' => $uid))->save($data);

    if($res){
      $user = session('user_auth');
      $user['username'] = $data['nickname'];
      session('user_auth', $user);
      session('user_auth_sign', data_auth_sign($user));
      $this->success('修改昵称成功！');
    }else{
      $this->error('修改昵称失败！');
    }
  }

  /**
   * 修改密码初始化
   * @author huajie <banhuajie@163.com>
   */
  public function updatePassword(){
    $this->meta_title = '修改密码';
    $this->display();
  }

  /**
   * 修改密码提交
   * @author huajie <banhuajie@163.com>
   */
  public function submitPassword(){
    //获取参数
    $password = I('post.old');
    empty($password) && $this->error('请输入原密码');
    $data['password'] = I('post.password');
    empty($data['password']) && $this->error('请输入新密码');
    $repassword = I('post.repassword');
    empty($repassword) && $this->error('请输入确认密码');

    if($data['password'] !== $repassword){
      $this->error('您输入的新密码与确认密码不一致');
    }

    $Api = new UserApi();
    $res = $Api->updateInfo(UID, $password, $data);
    if($res['status']){
      $this->success('修改密码成功！');
    }else{
      $this->error($res['info']);
    }
  }

  /**
   * 用户行为列表
   * @author huajie <banhuajie@163.com>
   */
  public function action(){
    //获取列表数据
    $Action = M('Action')->where(array('status' => array('gt', -1)));
    $list = $this->lists($Action);
    int_to_string($list);
    // 记录当前列表页的cookie
    Cookie('__forward__', $_SERVER['REQUEST_URI']);

    $this->assign('_list', $list);
    $this->meta_title = '用户行为';
    $this->display();
  }

  /**
   * 新增行为
   * @author huajie <banhuajie@163.com>
   */
  public function addAction(){
    $this->meta_title = '新增行为';
    $this->assign('data', null);
    $this->display('editAction');
  }

  /**
   * 编辑行为
   * @author huajie <banhuajie@163.com>
   */
  public function editAction(){
    $id = I('get.id');
    empty($id) && $this->error('参数不能为空！');
    $data = M('Action')->field(true)->find($id);

    $this->assign('data', $data);
    $this->meta_title = '编辑行为';
    $this->display();
  }

  /**
   * 更新行为
   * @author huajie <banhuajie@163.com>
   */
  public function saveAction(){
    $res = D('Action')->update();
    if(!$res){
      $this->error(D('Action')->getError());
    }else{
      $this->success($res['id'] ? '更新成功！' : '新增成功！', Cookie('__forward__'));
    }
  }

  /**
   * 会员状态修改
   * @author 朱亚杰 <zhuyajie@topthink.net>
   */
  public function changeStatus($method = null){
    $id = array_unique((array) I('id', 0));
    if(in_array(C('USER_ADMINISTRATOR'), $id)){
      $this->error("不允许对超级管理员执行该操作!");
    }
    $id = is_array($id) ? implode(',', $id) : $id;
    if(empty($id)){
      $this->error('请选择要操作的数据!');
    }
    $map['uid'] = array('in', (array)$id);
    switch(strtolower($method)){
      case 'forbiduser':
        if (C('TS_LOGIN')){
          $this->afterForbidUser($id);
        }
        //$this->forbid('Member', $map );
        $this->forbid('User', $map);
        // 禁用用户后续操作
        //TS用户系统
        break;
      case 'resumeuser':
        //用户回收操作
        $rst = M('Member')->where($map)->setField('status',1);
        if($rst !== false){
            $userMap['id'] = array('in',(array)$id);
            //恢复分销店铺状态
            M('Shop')->where($map)->setField('status',1);
            $rst = M('User')->where($userMap)->setField('status',1);
        }
        action_log('recycle_user', 'User', $id, UID);
        //TS用户系统
        if (C('TS_LOGIN')){
          $this->afterResumeUser($id);
        }
        $msg   = array( 'success'=>'操作成功！', 'error'=>'操作失败！', 'url'=>'' ,'ajax'=>IS_AJAX);
        ($rst !== false) ? $this->success($msg['success'],$msg['url'],$msg['ajax']) : $this->error($msg['error'],$msg['url'],$msg['ajax']);
        // 启用用户后续操作
        break;
      case 'deleteuser':
        // 删除用户后续操作 20160412 @Max
        $delete_status = $this->afterDeleteUser($id);
        ($delete_status == 1) ? $this->success('删除成功') : $this->error('删除失败');
        break;
      default:
        $this->error('参数非法');
    }
  }

  /**
   * 新增用户
   */
  public function add($group_id = null, $username = '', $password = '', $repassword = '', $email = ''){
    if(IS_POST){
      /* 检测密码 */
      if($password != $repassword){
        $this->error('密码和重复密码不一致！');
      }

      /* 调用注册接口注册用户 */
      $User = new UserApi;
      $uid = $User->register($group_id, $username, $password, 2);
      if(0 < $uid){ //注册成功
        $user = array('uid' => $uid, 'nickname' => $username, 'status' => 1);
        if(!M('Member')->add($user)){
          $this->error('用户添加失败！');
        }else{
          $this->success('用户添加成功！', U('index'));
        }
      }else{ //注册失败，显示错误信息
        $this->error($this->showRegError($uid));
        //$this->error($uid);
      }
    }else{
      //获取会员等级
      $this->lists_user_group = D('UserGroup')->getUserGroup();
      //p($this->lists_user_group);
      $this->meta_title = '新增用户';
      $this->display();
    }
  }

  /**
   * 更新用户字段值
   */
  public function setValue($key, $value, $id){
    $where['uid'] = $id;
    $data[$key] = $value;
    if($key == 'finance'){
      // 账户余额加入余额表
      $amount = M('Member')->where('uid = '.$id)->getField('finance');
      if($amount > 0){
        $data_finance = array(
          'uid' => $id,
          'order_id' => '',
          'type' => 'website_deduct',
          'amount' => $amount,
          'flow' => 'out',
          'memo' => '网站协议扣款',
          'create_time' => NOW_TIME
        );
        $update = M('Finance')->add($data_finance);
        if($update){
          // 加入日志
          action_log('update_'.$key, 'Member', $id, UID);
          return $this->editRow('Member', $data, $where);
        }
      }
    }
    $this->error('非法操作');
  }

  /**
   * 给用户充/扣值
   */
  public function rechangeAdd(){
    $uid = I('request.uid', 0);
    $this->member = M('Member')->field('uid, nickname, finance')->find($uid);
    if(IS_POST){
      $money = I('post.money', 0, floatval);
      $type = I('type', 0);
      !$type && $this->error('请选择操作类型');
      if($money <= 0){
        $this->error('操作金额必须大于零');
      }
      if($type == 'in'){
        $type_text = '充值';
        $res = M('Member')->where('uid = '.$uid)->setInc('finance', $money);
      }else{
        $money > $this->member['finance'] && $this->error('扣款金额不能大于余额');
        $type_text = '扣款';
        $res = M('Member')->where('uid = '.$uid)->setDec('finance', $money);
      }
      if($res){
        $data_finance = array(
          'uid' => $uid,
          'order_id' => '',
          'type' => 'website_rechange',
          'amount' => $money,
          'flow' => $type,
          'memo' => '后台'.$type_text,
          'create_time' => NOW_TIME
        );
        M('Finance')->add($data_finance);
        // 加入日志
        action_log('update_finance', 'Member', $uid, UID);
        $this->success($type_text.'成功');
      }else{
        $this->error($type_text.'失败');
      }
    }else{
      $this->display('rechangeAdd');
    }
  }

  /**
   * 给用户充/扣积分
   */
  public function scoreAdd($uid = 0){
    $this->member = M('Member')->field('uid, nickname, score')->find($uid);
    if(IS_POST){
      $score = I('post.score', 0, intval);
      $type = I('type', 0);
      !$type && $this->error('请选择操作类型');
      if($score <= 0){
        $this->error('操作积分值必须大于零');
      }
      if($type == 'in'){
        $type_text = '赠送';
        $res = M('Member')->where('uid = '.$uid)->setInc('score', $score);
      }else{
        $score > $this->member['score'] && $this->error('扣除的积分不能大于现有积分');
        $type_text = '扣除';
        $res = M('Member')->where('uid = '.$uid)->setDec('score', $score);
      }
      if($res){
        $data = array(
          'uid' => $uid,
          'order_id' => 0,
          'order_sn' => 0,
          'type' => $type,
          'amount' => $score,
          'memo' => '系统'.$type_text,
          'create_time' => NOW_TIME
        );
        M('ScoreLog')->add($data);
        // 加入日志
        action_log('update_score', 'Member', $uid, UID);
        $this->success($type_text.'成功');
      }else{
        $this->error($type_text.'失败');
      }
    }else{
      $this->display('scoreAdd');
    }
  }

  /**
   * 用户预存款充值管理
   * @author Max.Yu <max@jipu.com>
   */
  public function recharge($uid = null, $mode = null, $keywords = null){
    //充值方式配置
    $mode_config = array('alipay' => '支付宝', 'alipaywap' => '手机支付宝', 'bankpay' => '网银支付', 'wechatpay' => '微信支付');

    //实例化现金交易模型
    $Transaction = M('Transaction');

    //查询条件初始化
    $where['type'] = '充值';
    $where['status'] = 1;

    //查询条件：充值方式
    if($uid){
      $where['uid'] = $uid;
    }

    //查询条件：充值方式
    if($mode){
      $where['mode'] = $mode;
    }

    //查询条件：流水号，交易号（第三方支付返回）
    if($keywords){
      $where['_string'] = '(flowid like "%'.$keywords.'%") OR (number like "%'.$keywords.'%")';
    }

    //排序条件
    $order = 'id desc';

    //按条件查询结果并分页
    $list = $this->lists($Transaction, $where, $order);
    //记录当前列表页的Cookie
    Cookie('__forward__', $_SERVER['REQUEST_URI']);

    //模板输出变量赋值
    $this->assign('list', $list);
    $this->assign('mode_config', $mode_config);
    $this->assign('uid', $uid);
    $this->assign('mode', $mode);
    $this->assign('keywords', $keywords);
    $this->meta_title = '用户预存款充值管理';
    $this->display();
  }

  /**
   * 用户賬戶流水
   */
  public function finance($uid = '', $type = '', $start_time = null, $end_time = null){
    $where = array();
    //类型过滤
    $keyword = I('request.keyword');

      if($keyword){
          $mobile = M('User')->where(array('username'=>array('LIKE','%'.$keyword.'%')))->field('id')->select();
          $user_ids = array_column($mobile,'id');
          $nickname = M('Member')->where(array('nickname'=>array('LIKE','%'.$keyword.'%')))->field('uid')->select();
          $uids = array_column($nickname,'uid');
          $uid = array_merge($uids,$user_ids);
          if(!$uid)$uid=array(0);
      }

    if($uid)$where['uid'] = array('in',$uid);
    if($type){
      $where['type'] = $type;
    }

    // 时间过滤
    $start_time = !empty($start_time) ? strtotime($start_time) : '';
    $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
    if(!empty($start_time)){
      $where[] = "`create_time` > $start_time";
    }
    if(!empty($end_time)){
      $where[] = "`create_time` < $end_time";
    }
    //按条件查询结果并分页
    $order = 'id desc';
    $list = $this->lists('Finance', $where, $order);
    $this->list = $list;
    $this->mode_text = get_finance_type_name();
    $this->meta_title = '用户账户流水';
    $this->display();
  }
  /**
   * 用户現金流水
   */
  public function accountcost(){
    $where = array();
     //类型过滤
    if(in_array( I('type') ,array('充值','消費' ,'退款'))){
      $where['type'] = I('type');
    }
    //按用户过滤
    $keyword = I('request.keyword');
    if($keyword){
        $mobile = M('User')->where(array('username'=>array('LIKE','%'.$keyword.'%')))->field('id')->select();
        $user_ids = array_column($mobile,'id');
        $nickname = M('Member')->where(array('nickname'=>array('LIKE','%'.$keyword.'%')))->field('uid')->select();
        $uids = array_column($nickname,'uid');
        $uids = array_merge($uids,$user_ids);
        $uid = array_merge($uids,$user_ids);
        if(!$uid)$uid=array(0);
    }

      if($uid)$where['uid'] = array('in',$uid);

    // 时间过滤
    $start_time = I('start_time','');
    $start_time = !empty($start_time) ? strtotime($start_time) : '';
    
    $end_time = I('end_time','');
    $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
    if(!empty($start_time)){
      $where[] = "`create_time` > $start_time";
    }
    if(!empty($end_time)){
      $where[] = "`create_time` < $end_time";
    }
    //按条件查询结果并分页
    $order = 'id desc';
    $list = $this->lists('Transaction', $where, $order);
    $this->list = $list;
    $this->mode_text = get_accountcost_type_name();
    $this->meta_title = '用户现金流水';
    $this->display();
  }
  
  /**
   * 用户积分流水
   */
  public function score($uid = '', $type = '', $start_time = '', $end_time = ''){
    $where = array();
    //类型过滤
    $keyword = I('request.keyword');
    if($keyword){
        $mobile = M('User')->where(array('username'=>array('LIKE','%'.$keyword.'%')))->field('id')->select();
        $user_ids = array_column($mobile,'id');
        $nickname = M('Member')->where(array('nickname'=>array('LIKE','%'.$keyword.'%')))->field('uid')->select();
        $uids = array_column($nickname,'uid');
        $uids = array_merge($uids,$user_ids);
        $uidss = array_merge($uids,$user_ids);
        if(!$uidss)$uidss=array(0);
        $where['uid'] = array('in',$uidss);
    }
    if ($uid)$where['uid'] = $uid;
    if ($uid && $keyword) $where['uid'] = $uid;
    if($type == 'in+'){
      $type = 'in';$_GET['type'] = 'in';
    }
    if($type)$where['type'] = array('eq',$type);
    // 时间过滤
    $start_time = !empty($start_time) ? strtotime($start_time) : '';
    $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
    if(!empty($start_time)){
      $where[] = "`create_time` > $start_time";
    }
    if(!empty($end_time)){
      $where[] = "`create_time` < $end_time";
    }
    //按条件查询结果并分页
    $order = 'id desc';
    $list = $this->lists('ScoreLog', $where, $order);
    $this->list = $list;
    $this->meta_title = '用户积分记录';
    $this->display();
  }

    /**
     * @param string $uid
     * @param string $start_time
     * @param string $end_time
     * 用户优惠券流水
     */
    public function coupon($uid = '', $start_time = '', $end_time = ''){
        $where = array();
        //关键词搜索
        $keywords = I('keyword');
        if($keywords){
            $where['c.order_sn|u.nickname|m.name|m.number'] = array('like', '%'.$keywords.'%');
        }
        $start_time = I('get.start_time', '');
        $end_time = I('get.end_time', '');
        $start_time = !empty($start_time) ? strtotime($start_time) : '';
        $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
        if(!empty($start_time)){
            $where[] = "c.`use_time` > $start_time";
        }
        if(!empty($end_time)){
            $where[] = "c.`use_time` < $end_time";
        }
        $model = M('CouponUser')->alias('c')->join(' __COUPON__ m ON c.coupon_id = m.id ')->join('LEFT JOIN __MEMBER__ u ON u.uid = c.uid');
        $filed = 'c.*,m.name,m.number';
        $where['c.status'] = 1;
        //按条件查询结果并分页
        $order = 'c.id desc';
        $list = $this->lists($model, $where, $order,$filed,15,array());
        $this->list = $list;
        $this->meta_title = '用户优惠券记录';
        $this->display();
    }

    /**
     * 用户礼品卡流水
     */
    public function card(){
        $where = array();
        //关键词搜索
        $keywords = I('keyword');
        if($keywords){
            $where['c.order_sn|c.card_name|c.card_number|u.nickname'] = array('like', '%'.$keywords.'%');
        }
       //使用时间过滤
        $start_time = I('get.start_time', '');
        $end_time = I('get.end_time', '');
        $start_time = !empty($start_time) ? strtotime($start_time) : '';
        $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
        if(!empty($start_time)){
            $where[] = "c.`create_time` > $start_time";
        }
        if(!empty($end_time)){
            $where[] = "c.`create_time` < $end_time";
        }
        $model = M('CardLog')->alias('c')->join('__MEMBER__ u ON u.uid = c.uid');
        $filed = 'c.*';
        //按条件查询结果并分页
        $order = 'c.id desc';
        $list = $this->lists($model, $where, $order,$filed,15,array());
       
        $this->list = $list;
        $this->meta_title = '用户礼品卡记录';
        $this->display();
    }

  function edit(){
    $uid = I('request.uid', 0);
    $model = M('Member');
    $this->member = $model->field('uid, nickname')->find($uid);
    $this->display();
  }

  /**
   * 用于修改昵称和密码
   */
  function update(){
    if(IS_POST){
      if(check_form_hash()){
        $uid = I('request.uid', 0);
        $nickname = I('post.nickname', null);
        $password = I('post.password', null);
        $model = M('Member');
        $model_user = M('User');
        !$nickname && !$password && $this->error('参数错误!');
        //修改昵称
        if($nickname){
          $model->where('uid='.$uid)->setField('nickname', $nickname);
          S('sys_user_nickname_list', null);
        }
        //修改密码
        if($password){
          $model_user->where('id='.$uid)->setField('password', think_ucenter_md5($password));
        }
        $this->success('修改成功！');
      }else{
        $this->error('非法提交！');
      }
    }
  }

  /**
   * 获取用户注册错误信息
   * @param integer $code 错误编码
   * @return string 错误信息
   */
  private function showRegError($code = 0){
    switch($code){
      case -1: $error = '用户名长度必须在16个字符以内！';
        break;
      case -2: $error = '用户名被禁止注册！';
        break;
      case -3: $error = '用户名被占用！';
        break;
      case -4: $error = '密码长度必须在6-30个字符之间！';
        break;
      case -5: $error = '邮箱格式不正确！';
        break;
      case -6: $error = '邮箱长度必须在1-32个字符之间！';
        break;
      case -7: $error = '邮箱被禁止注册！';
        break;
      case -8: $error = '邮箱被占用！';
        break;
      case -9: $error = '手机格式不正确！';
        break;
      case -10: $error = '手机被禁止注册！';
        break;
      case -11: $error = '手机号被占用！';
        break;
      case -12: $error = '该手机号在EDULINE已存在！';
        break;
      default: $error = '未知错误';
    }
    return $error;
  }

  /**
   * 删除用户后的操作
   * @param integer $code 错误编码
   * @return string 错误信息
   */
  private function afterDeleteUser($uid = 0){
    if($uid){
      $map_user['id'] = array('in', $uid);
      $map_action['user_id'] = array('in', $uid);
      $map['uid'] = $uid;
      // user
      M('User')->where($map_user)->setField('status', -1);
      // 更新member表的记录状态
      M('Member')->where($map)->setField('status', -1);
      // 更新action_log表的记录状态
      M('ActionLog')->where($map_action)->setField('status', -1);
      // 更新发放的礼品卡状态
      M('CardUser')->where($map)->setField('status', -1);
      // 更新发放的优惠券状态
      M('CouponUser')->where($map)->setField('status', -1);
      // 更新发放的优惠券状态
      M('CouponUser')->where($map)->setField('status', -1);
      // 更新众筹订单状态
      M('CrowdfundingOrder')->where($map)->setField('status', -1);
      // 更新收藏状态
      M('Fav')->where($map)->setField('status', -1);
      // 更新商品评价表记录状态
      M('ItemComment')->where($map)->setField('status', -1);
      // 更新消息表记录状态
      M('Message')->where($map)->setField('status', -1);
      // 更新消息表记录状态
      M('TsUser')->where($map)->setField('status', -1);
      //更新分销店铺状态
      M('Shop')->where($map)->setField('status',-2);
      return 1;
    }else{
      return 0;
    }
  }


  /**
   * 真正删除用户信息
   * @param  integer $uid [description]
   * @return [type]       [description]
   */
  public function removeUser(){
    $id = array_unique((array) I('id', 0));
    if(in_array(C('USER_ADMINISTRATOR'), $id)){
      $this->error("不允许对超级管理员执行该操作!");
    }
    $id = is_array($id) ? implode(',', $id) : $id;
    if(empty($id)){
      $this->error('请选择要操作的数据!');
    }
    $delete_status = $this->afterRemoveUser($id);
    ($delete_status == 1) ? $this->success('删除成功') : $this->error('删除失败');
  }
  /**
   * 真正删除用户信息后操作
   * @param  integer $uid [description]
   * @return [type]       [description]
   */
  private function afterRemoveUser($uid = 0){
    if($uid){
      $map_user['id'] = array('in', $uid);
      $map_action['user_id'] = array('in', $uid);
      $map['uid'] =  array('in', $uid);
      // user
      M('User')->where($map_user)->delete();
      // 删除member表的记录
      M('Member')->where($map)->delete();
      // 删除action_log表的记录
      M('ActionLog')->where($map_action)->delete();
      // 删除发放的礼品卡
      M('CardUser')->where($map)->delete();
      // 删除发放的优惠券
      M('CouponUser')->where($map)->delete();
      // 删除发放的优惠券
      M('CouponUser')->where($map)->delete();
      // 删除众筹订单
      M('CrowdfundingOrder')->where($map)->delete();
      // 删除收藏
      M('Fav')->where($map)->delete();
      // 删除商品评价表记录
      M('ItemComment')->where($map)->delete();
      // 删除消息表记录
      M('Message')->where($map)->delete();
      // 微信登录
      M('login')->where($map)->delete();
      // 邀请
      M('union')->where($map)->delete();
      // 删除消息表记录
      M('TsUser')->where($map)->delete();
      return 1;
    }else{
      return 0;
    }
  }
  /**
   * 回收用户后的操作
   * @param integer $code 错误编码
   * @return string 错误信息
   */
  private function afterResumeUser($uid){
    if($uid){
      $map['uid'] = array('IN', explode(',', $uid));
      M('TsUser')->where($map)->setField('status', 1);
      return 1;
    }else{
      return 0;
    }
  }

  /**
   * 禁用用户后的操作
   * @param integer $code 错误编码
   * @return string 错误信息
   */
  private function afterForbidUser($uid){
    if($uid){
      $map['uid'] = array('IN', explode(',', $uid));
      M('TsUser')->where($map)->setField('status', 0);
      return 1;
    }else{
      return 0;
    }
  }


 public function export($keyword=null,$type=null,$start_time=null,$end_time=null,$model = null){
     $where = array();
     if($keyword){
         $mobile = M('User')->where(array('username'=>array('LIKE','%'.$keyword.'%')))->field('id')->select();
         $user_ids = array_column($mobile,'id');
         $nickname = M('Member')->where(array('nickname'=>array('LIKE','%'.$keyword.'%')))->field('uid')->select();
         $uids = array_column($nickname,'uid');
         $uids = array_merge($uids,$user_ids);
         if(!$uids)$uids=array(0);
         $where['uid'] = array('in',$uids);
     }
     $uid = I('request.uid');
     if($uid){
         $where['uid'] = $uid;
     }
     if($type){
         $where['type'] = $type;
     }
     // 时间过滤
     $start_time = !empty($start_time) ? strtotime($start_time) : '';
     $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
     if(!empty($start_time)){
         $where[] = "`create_time` > $start_time";
     }
     if(!empty($end_time)){
         $where[] = "`create_time` < $end_time";
     }

   //  $data = M($model)->where($where)->order('id asc')->select();
     if($model=='Finance'){
         $filename = "用户账户流水";
         $headArr = array('ID', '用户', '交易类型', '收入/支出(元)', '交易时间', '备注信息');
         $field = 'id,uid,type,amount,create_time,memo,flow';
         $data = M($model)->where($where)->order('id desc')->field($field)->select();
         foreach($data as $k => $v){
             $data[$k]['uid'] = get_nickname($v['uid']);
             $data[$k]['type'] = get_finance_type_name($v['type']);
             $data[$k]['create_time'] = date("Y-m-d H:i:s",$v['create_time']);
             $data[$k]['amount']  = $v['flow'] == 'in' ? '+'.$v['amount'] : '-'.$v['amount'];
             unset($data[$k]['flow']);
         }

     }else if($model == 'Transaction'){
         $filename = "用户现金流水";
         $headArr = array('ID', '用户', '交易类型', '收入/支出(元)', '交易时间', '备注信息');
         $field = 'id,uid,type,amount,create_time,memo,flow';
         $data = M($model)->where($where)->order('id desc')->field($field)->select();
         foreach($data as $k => $v){
             $data[$k]['uid'] = get_nickname($v['uid']);
             $data[$k]['type'] = get_accountcost_type_name($v['type']);
             $data[$k]['create_time'] = date("Y-m-d H:i:s",$v['create_time']);
             $data[$k]['amount']  = $v['flow'] == 'in' ? '+'.$v['amount'] : '-'.$v['amount'];
             unset($data[$k]['flow']);
         }

     }else if($model == 'ScoreLog'){
         $filename = "用户积分流水";
         $headArr = array('ID', '用户', '奖励/消费（积分）',  '备注信息','交易时间');
         $field = 'id,uid,amount,memo,create_time,type';
         $data = M($model)->where($where)->order('id desc')->field($field)->select();
         foreach($data as $k => $v){
             $data[$k]['uid'] = get_nickname($v['uid']);
             $data[$k]['amount']  = $v['type'] == 'in' ? '+'.$v['amount'] : '-'.$v['amount'];
             $data[$k]['create_time'] = date("Y-m-d H:i:s",$v['create_time']);
             unset($data[$k]['type']);

         }
     }else if($model == 'CouponUser'){
         $filename= '用户优惠券流水';
         $where = '';
         //关键词搜索
         $keywords = I('keyword');
         if($keywords){
             $where['c.order_sn|u.nickname|m.name|m.number'] = array('like', '%'.$keywords.'%');
         }
         $start_time = I('get.start_time', '');
         $end_time = I('get.end_time', '');
         $start_time = !empty($start_time) ? strtotime($start_time) : '';
         $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
         if(!empty($start_time)){
             $where[] = "c.`use_time` > $start_time";
         }
         if(!empty($end_time)){
             $where[] = "c.`use_time` < $end_time";
         }
         $where['c.status'] = 1;
         //按条件查询结果并分页
         $order = 'c.id desc';
         $headArr = array('ID','用户','优惠券','优惠券编号','使用金额','使用时间','订单编号');
         $field = 'c.id,c.uid,m.name,m.number,c.use_amount,c.use_time,c.order_sn';
         $data = M('CouponUser')->alias('c')->join(' __COUPON__ m ON c.coupon_id = m.id ')->join('LEFT JOIN __MEMBER__ u ON u.uid = c.uid')->where($where)->order($order)->field($field)->select();
           foreach($data as $k => $v){
                 $data[$k]['uid'] = get_nickname($v['uid']);
                 $data[$k]['use_time'] = date("Y-m-d H:i:s",$v['use_time']);
               //  $data[$k]['coupon_id'] = M('Coupon')->where(array('id'=>$v['coupon_id']))->getField('name');
             }
     }else if($model = 'Card'){
         $filename= '用户礼品卡流水';
         $where = '';
         //关键词搜索
         $keywords = I('keyword');
         if($keywords){
             $where['c.order_sn|u.nickname|c.card_name|c.card_number'] = array('like', '%'.$keywords.'%');
         }
         $start_time = I('get.start_time', '');
         $end_time = I('get.end_time', '');
         $start_time = !empty($start_time) ? strtotime($start_time) : '';
         $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
         if(!empty($start_time)){
             $where[] = "c.`create_time` > $start_time";
         }
         if(!empty($end_time)){
             $where[] = "c.`create_time` < $end_time";
         }
         //按条件查询结果并分页
         $order = 'c.id desc';
         $headArr = array('ID','用户','礼品卡','礼品卡卡号','使用金额','使用时间','订单编号');
         $field = 'c.id,c.uid,c.card_name,c.card_number,c.amount,c.create_time,c.order_sn';
         $data = M('CardLog')->alias('c')->join('LEFT JOIN __MEMBER__ u ON u.uid = c.uid')->where($where)->order($order)->field($field)->select();
         foreach($data as $k => $v){
             $data[$k]['uid'] = get_nickname($v['uid']);
             $data[$k]['create_time'] = date("Y-m-d H:i:s",$v['create_time']);
         }
     }
     $num = count($data);
     $num > 5000 ? $this->error('每次导出数据条数最多为5000条！') : null ;
     //调用Excel文件生成并导出函数
     createExcel($filename, $headArr, $data);

 }



  
}
