<?php
/**
* 扫描带参数的二维码日志模型
* @version 2015091611 
* @author Justin <justin@jipu.com>
*/

namespace Home\Model;

class WechatQrcodeLogModel extends HomeModel{
  
  /**
   * 自动完成规则
   * @var array
   */
  protected $_auto = array(
    array('create_time', NOW_TIME, self::MODEL_BOTH),
  );
  
  /**
   * 自动映射
   * @var array
   */
  protected $_map = array(
    'FromUserName' =>'open_id',
  );
   
  function update($data = null){
    $event_key = str2arr($data['EventKey'], '_');
    $data['union_id'] = $event_key[1];
    //验证桌面商家状态 || 记录已存在
    if((1 != M('Union')->getFieldByQrcodeId($data['union_id'], 'status')) || $this->getByOpenId($data['FromUserName'])){
      return;
    }
    parent::update($data);
  }
  
  /**
   * 插入成功后的回调方法
   */
  protected function _after_insert($data, $options) {
    $union_id = $data['union_id'];
    //获取推广者uid
    $uid = M('Union')->getFieldById($union_id, 'uid');
    
    $amount = C('UNION_SUBSCRIBE_CASHBACK');
    if($amount > 0 ){
      //增加推广余额
      M('Member')->where('uid='.$uid)->setInc('finance', $amount); 
      //获取微信用户基本信息 
      import('Org.ThinkSDK.ThinkOauth');
      import('Org.Wechat.WechatAuth');
      //加载微信SDK
      $wechat = new WechatAuth(C('WECHAT_APPID'), C('WECHAT_SECRET'), C('WECHAT_TOKEN'));
      $token = $wechat->getAccessToken('code');
      $userinfo = $wechat->getUserInfo($token);

      $data_finance = array(
        'uid' => $uid,
        'order_id' => '',
        'type' => 'union_subscribe',
        'amount' => $amount,
        'flow' => 'in',
      'memo' => '微信用户昵称：'.$userinfo['nickname'].'，推广联盟关注返现',
        'create_time' => NOW_TIME
      );
      $update = M('Finance')->add($data_finance);
    }
  }
  
}
