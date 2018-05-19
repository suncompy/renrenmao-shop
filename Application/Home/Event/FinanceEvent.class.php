<?php
/**
 * 余额事件处理
 * @version 2015102015
 * @author Justin <justin@jipu.com>
 */

namespace Home\Event;

class FinanceEvent{
  
  /**
   * 获取可提现的余额
   * @author Justin <justin@jipu.com>
   */
  function getWithDrawFinance($uid = 0){
    $uid = $uid ? : UID;
    $finance = M('Member')->getFieldByUid($uid, 'finance');
    //可提现金额类型
    $available = array('union_order', 'invite_reward','union_subscribe', 'sdp_order', 'withdraw_refuse_cashback' , 'invite_register' , 'website');
    $where = array(
      'uid' => $uid,
      'flow' => 'in',
      'type' => array('in', $available),
      'status' => 1,
    );
    $withdraw_amount = M('Finance')->where($where)->sum('amount');
    //已提现的需要减去
    $where = array(
      'uid' => $uid,
      'flow' => 'out',
      'type' => array('in', array('withdraw', 'sdp_refund')),
     // 'status'=>array('neq',1),
    );
    $withdrawed = M('Finance')->where($where)->sum('amount');
    $money = $withdraw_amount - $withdrawed;//可提现部分

    /*需要扣除消费的*/
    $where_xiaofei = array(
      'uid' => $uid,
      'flow' => 'out',
      'type' => array('in', array('website'))
    );
    $xiaofei = M('Finance')->where($where_xiaofei)->sum('amount');
    //不可提部分
    $Notwithdraw_map = array(
      'uid' => $uid,
      'flow' => 'in',
      'type' => array('notin', $available),
      'status' => 1,
    );
    $Notwithdraw_amount =  M('Finance')->where($Notwithdraw_map)->sum('amount');

    if($Notwithdraw_amount - $xiaofei < 0){
      $money += $Notwithdraw_amount - $xiaofei ;
    }
    $money = ($money >= 0) ? (sprintf('%.2f',$money)) :0 ;
  //  return sprintf('%.2f', min($money, $finance));
    return $money;
  }
  
// .-----------------------------------------------------------------------------------
// | Version: 2017
// |-----------------------------------------------------------------------------------
// | Author: Fourth Pu <540690666@qq.com|ppssone@live.cn>
// |-----------------------------------------------------------------------------------
// | 总余额 如果member中的余额正确 就不使用这个方法 后续纠正member后再修改
// .-----------------------------------------------------------------------------------
  function get_total_finance($uid){
    $where = array(
      'uid' => $uid,
      'flow' => 'in',
      'status' => array('neq',2) ,
    );
    $in_finance  = M('finance')->where($where)->sum('amount');
    $out_finance = M('finance')->where(array('flow'=>'out','uid'=>$uid))->sum('amount');
    return sprintf('%.2f' , $in_finance - $out_finance);
  }

// .-----------------------------------------------------------------------------------
// | Version: 2017
// |-----------------------------------------------------------------------------------
// | Author: Fourth Pu <540690666@qq.com|ppssone@live.cn>
// |-----------------------------------------------------------------------------------
// | 可用余额
// .-----------------------------------------------------------------------------------

  function available_finance($uid){
    $total_amount = $this->get_total_finance($uid);
    $where = array(
      'uid' => $uid,
      'flow' => 'in',
      'status' => 0 ,
    );
    //暂不能使用金额
    $NotTimefinance  = M('finance')->where($where)->sum('amount');
    return sprintf('%.2f' , $total_amount - $NotTimefinance);
  }


}
