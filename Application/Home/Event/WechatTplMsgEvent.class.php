<?php
/**
 * 微信模板消息事件控制器
 *
 * @author Justin <justin@jipu.com>
 */

namespace Home\Event;

class WechatTplMsgEvent{
  
  /**
   * 微信模板消息通知调用
   * @author Justin <justin@jipu.com>
   */
  function wechatTplNotice($type = null, $data = null){
    if($type && $data){
      switch($type){
        case 'paid':
          //已付款
          $payment_type = M('Payment')->getFieldById($data['payment_id'], 'payment_type');
          //分销商拆分订单，订单号多个 buddha 金海 20170724
          if (count(str2arr($data['supplier_ids'])) > 1) {
            $map['_string'] = 'id <> '.$data['id'].' AND payment_id = '.$data['payment_id'];
            $order_info = M("Order")->where($map)->getField('order_sn',true);
            $order_sn = arr2str($order_info);
          }else{
            $order_sn = $data['order_sn'];
          }
          $send_data = array(
            'first' => '亲，您的订单已付款成功',
            'keyword1' => $order_sn,//订单号
            'keyword2' => time_format(), //支付时间
            'keyword3' => $data['total_amount'] > 0 ? $data['total_amount'] : M('Payment')->getFieldById($data['payment_id'], 'finance_amount'),//支付金额
            'keyword4' => $data['total_amount'] > 0 ? get_payment_type_text($payment_type) : '余额支付',//支付方式
            'remark' => '点击查看订单详情'
          );
          $url = SITE_URL.U('Member/order');

          A('WechatTplMsg', 'Event')->send('order.pay_success', $url, $data['uid'], $send_data);
          
          break;    
        case 'shipped':
          $order_sn = M('Order')->getFieldById($data['order_id'], 'order_sn');
          //已发货
          $send_data = array(
            'first' => '亲，您的订单已发货',
            'keyword1' => $order_sn,//订单号
            'keyword2' => $data['delivery_name'], //快递公司
            'keyword3' => $data['delivery_sn'],//快递单号
            'remark' => '点击查看订单详情'
          );
          $url = SITE_URL.U('/Order/detail', array('order_sn' => $order_sn));
          A('Home/WechatTplMsg', 'Event')->send('order.delivery_notice', $url, I('post.uid'), $send_data);
    
          break;
        case 'union':
          $send_data = array(
            'first' => '亲，您的代理返现到了',
            'keyword1' => $data['reason'],//返现原因
            'keyword2' => $data['money'], //返现金额
            'keyword3' => $data['way'],//返现方式
            'remark' => '点击查看订单详情'
          );
          $url = SITE_URL.U('/Member/union');
          A('Home/WechatTplMsg', 'Event')->send('order.union_back', $url, $data['uid'], $send_data);
          break;
        case 'applyer':
          $send_data = array(
            'first' => $data['title'] ,
            'keyword1' => $data['nickname'],//姓名
            'keyword2' => date('Y-m-d H:i:s'), //时间
            'remark' => '点击详情，查看好友'
          );
          A('Home/WechatTplMsg', 'Event')->send('union.new_applyer', $data['url'], $data['uid'], $send_data);
          break;
        case 'unionbind':
          $send_data = array(
              'first'    => '您好，您又有新的一级代理',
              'keyword1' => $data['nickname'],
              'keyword2' => date('Y-m-d H:i:s'),
              'remark' => '点击详情，查看好友'
          );
          $url = SITE_URL.U('/Member/unionerlist',array('type'=>1));
          A('Home/WechatTplMsg', 'Event')->send('union.new_applyer', $url, $data['uid'], $send_data);
          break;
        case 'unbind':
          $name = ($data['type'] == 1) ? ('您好，您的好友：'.$data['nickname'].'离开了您，你要加倍关心您的好友哦～') : ('恭喜您，您已成功解除绑定！');
          $send_data = array(
              'first'    =>  $name,
              'keyword1' =>  $data['mobile'],
              'keyword2' => date('Y-m-d H:i:s'),
              'remark'=>'点击查看详情'
          );
          $url = SITE_URL.U('/Member/unionerlist',array('type'=>1));
          A('Home/WechatTplMsg', 'Event')->send('union.unbind', $url, $data['uid'], $send_data);
          break;
      }
    }
  }
  
  /**
   * 发送模板消息
   * @param string $tpl 网站后台模板消息的调用标识 例如：order.delivery_notice
   * @param string $url 点击消息的跳转地址  例如：http://www.jipusop.com/
   * @param int $uid 用户UID  例如：30
   * @param data $data 替换字符数组 例如：array('first' => '亲，您的订单发货了', 'keyword1' => '312344214332')
   * @return boolean 发送状态
   * @author Max.Yu <max@jipu.com>
   * @version 15080417
   * ****** 实例代码 **************************************
   * *
   * *  $send_data = array(
   * *    'first' => '亲，您的订单已发货',
   * *    'keyword1' => '1342421412412',
   * *    'keyword2' => '中通快递',
   * *    'keyword3' => '97442112124',
   * *    'remark' => '点击查看订单详情'
   * *  );
   * *  $url = 'http://www.jipukeji.com/';
   * *  $res = A('WechatTplMsg', 'Event')->send('order.delivery_notice', $url, 30, $send_data);
   * *
   * ***************************************************** 
   */
  public function send($tpl = '', $url = '', $uid = 0, $data = array()){
    if(empty($tpl) || empty($url) || empty($uid) || empty($data)){
      return false;
    }
    //获取用户OPENID
    $where = array(
      'type' => 'wechat',
      'uid' => $uid
    );
    $open_id = M('Login')->where($where)->getField('type_uid');
    if(empty($open_id)){
      return false;
    }
    //获取模板ID
    $tpl_s = explode('.', $tpl);
    $where = array(
      'type' => $tpl_s[0],
      'tpl_key' => $tpl_s[1],
      'status' => 1
    );
    $tpl_id = M('WechatTplMsg')->where($where)->getField('tpl_id');
    if(empty($tpl_id)){
      return false;
    }
    $font_color = C('WECHATTPLMSGCOLOR');
    $send_data = array(
      'touser' => $open_id,
      'template_id' => $tpl_id,
      'url' => $url,
      'topcolor' => $font_color,
      'data' => array()
    );
    foreach($data as $k => $v){
      $send_data['data'][$k] = array(
        'value' => $v,
        'color' => $font_color
      );
    }
    $wechat = new \Org\Wechat\WechatAuth(C('WECHAT_APPID'), C('WECHAT_SECRET'));
    $res = $wechat->sendTplMsg($send_data);
    $res_arr = json_decode($res, true);
    //返回发送状态
    return $res_arr['errcode'] == 0 && $res_arr['errmsg'] == 'ok';
  }
}
