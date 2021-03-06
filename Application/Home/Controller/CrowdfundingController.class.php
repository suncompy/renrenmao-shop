<?php
/**
 * 众筹控制器
 * @version 20150123
 * @author Tony <Tony@jipu.com>
 */

namespace Home\Controller;

class CrowdfundingController extends HomeController {

  public function _initialize(){
    parent::_initialize();
    //跳过验证登录
    $jump_loginlist = array('join');
    if(!in_array(ACTION_NAME, $jump_loginlist)){
      parent::login();
    }
  }

  /**
   * 发起众筹展示设置页面
   * @author Tony <Tony@jipu.com>
   */
  public function detail(){
    $id = trim(I('id'));
    //获取订单信息
    $info = $this->getOrderData($id);
    $this->assign('info', $info);
    $this->meta_title = '发起众筹';
    $data = A('Order', 'Event')->checkOrderType($id);
    //众筹订单已存在时，跳到join页面
    if($info['name'] == 'crowdfunding'){
      //优惠券id
      $arr['id'] = $info['id'];
      $arr['oid'] = $info['order_id'];
      redirect(U('doing', $arr));
    }
    $this->display();
  }

  /**
   * 提交众筹
   * @author Tony <Tony@jipu.com>
   */
  public function add(){
    $data['order_id'] = trim(I('id'));
    $this->checkEmpty($data['order_id'], '订单id');
    $data['msg'] = trim(I('msg'));
    //对众筹数据进行更新
    $crowdfunding_id = D('CrowdfundingOrder')->update($data);
    if($crowdfunding_id){
      //众筹id
      $arr['id'] = $crowdfunding_id;
      $arr['oid'] = $data['order_id'];
      $this->ajaxReturn(array('code'=>200,'title'=>'众筹成功','url'=>U('doing', $arr)));
    }else{
      $this->ajaxReturn(array('code'=>0,'title'=>'发起众筹失败'));
    }
  }

  /**
   * 获取众筹数据信息
   * @author Tony <Tony@jipu.com>
   */
  public function doing(){
    $info = $this->getData();
    $order = $this->getOrderData($info['order_id']);
    $meta_share = array(
      'title' => $info['msg'],
      'desc' => '我正在'.C('WEB_SITE_TITLE').'发起购买“'.$order['OrderItem']['name'].'”的众筹，就差'.$info['surplus_amount'].'元了，快来帮我付一发啦！',
      'img_url' => SITE_URL.$order['OrderItem']['cover_path'],
      'link' => SITE_URL.U('Crowdfunding/doing', array('id' => $info['id'], 'oid' => $info['order_id']))
    );
    $profile = M('Member')->field('uid, sex, nickname, avatar')->getByUid(UID);
    $this->profile = $profile;
	$this->assign('order', $order);
    $this->assign('info', $info);
    $this->meta_title = $info['surplus_amount'] <= 0 ? '众筹已完成' : $order['OrderItem']['name'];
    $this->meta_share = $meta_share;
    $this->display();
  }

  /**
   * 参与众筹
   * @author Tony <Tony@jipu.com>
   */
  public function join(){
    $open_id = A('Pay', 'Event')->getOpenId();
    $oid = trim(I('oid'));
    $info = $this->getOrderData($oid);
    $data = $this->getData();
    if($info['o_status'] != 0){
      $arr['id'] = $data['id'];
      $arr['oid'] = $data['order_id'];
      redirect(U('doing', $arr));
    }
    $info['surplus_amount'] = $data['surplus_amount'];
    $info['crowdfunding_id'] = $data['id'];
    $info['open_id'] = $open_id;
    $meta_share = array(
      'title' => $data['msg'],
      'desc' => '我正在'.C('WEB_SITE_TITLE').'发起购买“'.$info['OrderItem']['name'].'”的众筹，就差'.$info['surplus_amount'].'元了，快来帮我付一发啦！',
      'img_url' => SITE_URL.$info['OrderItem']['cover_path'],
      'link' => SITE_URL.U('Crowdfunding/doing', array('id' => $data['id'], 'oid' => $data['order_id']))
    );
    $this->assign('info', $info);
    $this->meta_title = '参与众筹';
    $this->meta_share = $meta_share;
    $this->display();
  }

  /**
   * 取消众筹订单
   * @author Tony <Tony@jipu.com>
   */
  public function remove(){
    if(IS_POST){
      $map['id'] = trim(I('id'));
      $this->checkEmpty($map['id'], '众筹id');
      $map['status'] = 0;
      $list = D('CrowdfundingOrder')->update($map);
      if($list){
        $result = array('status' => 1, 'msg' => '取消成功');          
      }else{
        $result = array('status' => 0, 'msg' => '取消失败');
      }
      $this->ajaxReturn($result);
    } 
  }

  /**
   * 众筹失败或众筹金额超出订单金额，转出到个人余额帐户
   * @param $data：提交的数据
   * @param $msg：提交的数据描述
   * @author Tony <Tony@jipu.com>
   */
  public function fail(){
    //等于0时为不限制众筹时间
    if(C('CROWDFUNDING_EXPIRE_TIME') > 0){
      $map['create_time'] = array('lt',NOW_TIME - (int)C('CROWDFUNDING_EXPIRE_TIME') * 86400);
    }
    $map['success'] = 0;
    $CrowdfundingOrder = D('CrowdfundingOrder');
    $list = $CrowdfundingOrder->lists($map, true, 'create_time DESC', '1000');
    if(!$list){
      return false;
    }
    $Order = M('Order');
    $where_order['o_status'] = 0;
    $data_order['o_status'] = -1;
    $CrowdfundingUsers = D('CrowdfundingUsers');
    $map_user['payment_status'] = 1;
    $map_user['roll_out'] = 'in';
    foreach ($list as $k => $v) {
      $payedMoney = 0;
      $where_order['id'] = $v['order_id'];
      $where_order['uid'] = $v['uid'];
      $map_user['crowdfunding_id'] = $v['id'];
      $payedMoney = $CrowdfundingUsers->sumPayed($map_user);
      if($payedMoney > 0){
        $out = $this->rollOutBalance($CrowdfundingUsers, $v['uid'], $v['order_id'], $payedMoney);
        if($out > 0){
          $data_crowdfunding['id'] = $v['id'];
          $data_crowdfunding['success'] = 2;
          $data_crowdfunding['expire_time'] = NOW_TIME;
          $result = $CrowdfundingOrder->save($data_crowdfunding);
          $res = $Order->where($where_order)->save($data_order);
          if(!$result || !$res){
            $data['id'] = $v['id'];
            $data['uid'] = $v['uid'];
            $data['sqlCrowdfundingOrder'] = $CrowdfundingOrder->getLastSql();
            $data['sqlOrder'] = $Order->getLastSql();
            F('rollOutCrowdfunding/'.date('Ym/d/').$v['id'].'-'.date('-His'), $data);
          }
        }
      }
    }
  }

  /**
   * 获取众筹对应订单数据的方法
   * @author Tony <Tony@jipu.com>
   */
  private function getOrderData($id = ''){
    if(IS_GET){
      $map['id'] = $id;
      $this->checkEmpty($map['id'], 'id');
      $field = 'id, uid, item_ids, o_status, total_amount';
      $data = D('Order')->detail($map, $field);
      // $data['ship'] = $data['ship_area'].$data['ship_address'];
      $data['OrderItem'] = $data['items'][0];
      // print_r($data);
      
      return $data;
    }
  }

  /**
   * 用来获取众筹数据的方法
   * @author Tony <Tony@jipu.com>
   */
  private function getData(){
    if(IS_GET){
      $map['id'] = trim(I('id'));
      $oid = trim(I('oid'));
      $this->checkEmpty($map['id'], '众筹id');
      $this->checkEmpty($oid, '订单id');
      $map['status'] = 1;
      $map['order_id'] = $oid;
      $field = 'id, uid, order_id, msg';
      $data = D('CrowdfundingOrder')->detail($map, $field);
      if($data){
        $data['is_self'] = $data['uid'] == UID ? 1 : 0;
      }
      return $data;
    } 
  }

  /**
   * 处理余额转入个人余额帐户的方法
   * @param $CrowdfundingUsers：众筹用户表对象
   * @param $uid：用户id
   * @param $id：所购买商品的订单id
   * @param $balance：转出金额
   * @author tony <tony@jipu.com>
   */
  private function rollOutBalance($CrowdfundingUsers, $uid, $id, $balance){
    $data['uid'] = $uid;
    $res = M('Member')->where($data)->setInc('finance', $balance);
    if($res){
      $where['order_id'] = $id;
      $arr['roll_out'] = 'out';
      $arr['roll_out_time'] = NOW_TIME;
      $where['payment_status'] = 1;
      $result = $CrowdfundingUsers->where($where)->save($arr);
      if($result){
        $data['order_id'] = $id;
        $data['type'] = 'crowdfunding';
        $data['amount'] = $balance;
        $data['flow'] = 'in';
        $data['create_time'] = NOW_TIME;
        $out = M('Finance')->add($data);
      }else{
        $data['id'] = $id;
        $data['finance'] = $balance;
        F('rollOutCrowdfunding/'.date('Ym/d/').$id.'-'.date('-His'), $data);
      }
    }
    return $out ? $out : 0;
  }

  /**
   * 用来检测提交的数据是否为空的方法
   * @param $data：提交的数据
   * @param $msg：提交的数据描述
   * @author tony <tony@jipu.com>
   */
  private function checkEmpty($data, $msg = ''){
    if(empty($data)){
      $this->error($msg.'不能为空');
    }
  }

}
