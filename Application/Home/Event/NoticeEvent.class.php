<?php
/**
 * 通知事件处理
 * @version 2015092911
 * @author Justin <justin@jipu.com>
 */

namespace Home\Event;

class NoticeEvent{
   
  /**
   * 订单支付后的通知
   */
  function afterPaid($order){
    //管理员
    $this->_afterPaidToAdmin($order);
    //用户
    $this->_afterPaidToUser($order);
  }
  function toUser($order){
      $this->afterPaidToUser($order);
  }
  private function _afterPaidToAdmin($order){ 
    //短信通知供应商
    $where = array(
      'payment_id' => $order['payment_id'],
      'total_amount' => array('neq', 0.01),//过滤测试
    );
    $order_lists = M('Order')->field('supplier_ids')->where($where)->select();
    $supplier_ids = array_column($order_lists, 'supplier_ids');
    if(!empty($supplier_ids)){
      $map['supplier_id'] = array('in', $supplier_ids);
      $supplier_lists = M('Supplier')->field('notice_mobile,key')->where($map)->select();
      foreach($supplier_lists as $key=>$val){
        $url = SITE_URL.U('Supplier/index',array('key'=>$val['key']));
        //$country_id = M('User')->where(array('mobile'=>$val['notice_mobile']))->getField('countryid');
        send_yptpl_sms($val['notice_mobile'],'NEWORDER_NOTIFY' ,array('key'=>$url));
      }
    }
    if(in_array(0,$supplier_ids)){
        if(C('NEWORDER_SMS_NOTIFY_ADMIN') == 1 && C('SYSTEM_MOBILE_NO')){
            $url = SITE_URL.U('Order/index');
            send_yptpl_sms(C('SYSTEM_MOBILE_NO'), 'NEWORDER_NOTIFY' ,array('key'=>''));
     }
    }
  }
  
  private function _afterPaidToUser($order){
    //微信通知用户
    A('WechatTplMsg', 'Event')->wechatTplNotice('paid', $order);
    //订单付款成功通知
  }

  private function afterPaidToUser($order){
       $key = $order['order_sn'];
       $mobile = $order['mobile'];
       $tpl ='COMPLETE_PAY';
       if($tpl){
           send_yptpl_sms($mobile,$tpl,array('key'=>$key));
       }
  }

  /**
   * 三级分销：下级绑定上级，微信通知上级
   * @author buddha <[email address]>
   * @param  [type] $uid [description]
   * @return [type]      [description]
   */
  function bindNotice($data){
    //微信通知用户
    A('WechatTplMsg', 'Event')->wechatTplNotice('unionbind', $data);
  }
  /**
   * 三级分销：下级解绑 通知上级及其自己一级下级
   * @author buddha <[email address]>
   * @param  [type] $data [description]
   * @return [type]       [description]
   */
  function unbindNotice($data){
    //微信通知用户
     A('WechatTplMsg', 'Event')->wechatTplNotice('unbind', $data);
  }
}
