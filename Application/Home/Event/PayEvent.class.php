<?php
/**
 * 订单事件模型
 * @author Max.Yu <max@jipu.com>
 */

namespace Home\Event;

use Org\Wechat\WechatAuth;

class PayEvent{

  public function __construct(){
    //读取站点配置
    $config = api('Config/lists');
    C($config); //添加配置
  }

  /**
   * 修改订单支付方式(添加订单类型后需修改)
   * @author Max.Yu <max@jipu.com>
   */
  public function updateOrderPayment($order_id, $payment_type = 'wechatpay', $order_type = 'item_order'){
    switch($order_type){
      case 'item_order' : //商品订单
        $where['id'] = M('Order')->getFieldById($order_id, 'payment_id');
       $rst =  M('Payment')->where($where)->setField('payment_type', $payment_type);
       if($rst !== false){
           return true;
       }
        break;
      case 'crowdfunding_order' : //众筹订单
        M('CrowdfundingUsers')->where(array('id' => $order_id))->setField('payment_type', $payment_type);
        break;
      case 'redpacket_order' : //红包订单
        M('Redpacket')->where(array('id' => $order_id))->setField('payment_type', $payment_type);
        break;
      case 'join_order' : //拼团订单
        $where['id'] = M('join_order')->getFieldById($order_id, 'payment_id');
        M('Payment')->where($where)->setField('payment_type', $payment_type);
        break;
    }
  }

  /**
   * 获取预付单号所需订单信息
   * @author Max.Yu <max@jipu.com>
   */
  public function getOrderInfoToPrePayId($order_id, $order_type = 'item_order', $create_newsn = false){
    $order_info = array();
    switch($order_type){
      //商品订单
      case 'item_order' :
        $order = M('Order')->find($order_id);
        $item_names = D('Item')->getItemNamesForPay($order['item_ids']); //获取订单商品名称
        if($order['o_status'] == 0 && $create_newsn){
          $order['order_sn'] = create_sn();
          M('Order')->where('id='.$order['id'])->setField('order_sn', $order['order_sn']);
        }
        $order_info = array(
          'body' => '网站购物-'.$item_names, //商品描述
          'order_sn' => $order['order_sn'], //商户订单号
          'amount' => $order['total_amount'], //总金额
        );
        break;
      //众筹订单
      case 'crowdfunding_order' :
        $order = M('CrowdfundingUsers')->find($order_id);
        $order_info = array(
          'body' => '众筹购物', //商品描述
          'order_sn' => $order['pay_id'], //商户订单号
          'amount' => $order['pay_money'], //总金额
        );
        break;
      //红包订单
      case 'redpacket_order' :
        $order = M('Redpacket')->find($order_id);
        $order_info = array(
          'body' => '发红包', //商品描述
          'order_sn' => $order['order_sn'], //商户订单号
          'amount' => $order['amount'], //总金额
        );
        break;
        //拼团订单
        case 'join_order' :
            $order = M('JoinOrder')->find($order_id);
            if($order['status'] == 0 && $create_newsn){
                $order['join_sn'] = create_sn();
                M('JoinOrder')->where('id='.$order['id'])->setField('join_sn', $order['join_sn']);
            }
            $order_info = array(
                'body' => '网站购物-'.$order['item_name'], //商品描述
                'order_sn' => $order['join_sn'], //商户订单号
                'amount' => $order['total_amount'], //总金额
            );
            break;
    }
    return $order_info;
  }

  /**
   * 微信-支付订单状态查询
   * @author Max.Yu <max@jipu.com>
   */
  public function weixinOrderQuery($order_id, $order_type = 'item_order'){
    $return_data = array('code' => 300, 'error' => '处理失败', 'data' => array());
    $order_info = $this->getOrderInfoToPrePayId($order_id, $order_type);
    $order_sn = $order_info['order_sn'];
    if(empty($order_sn)){
      $return_data['error'] = '订单号不能为空！';
    }else{
      //引入微信支付通用类
      import('Org.Wechat.Pay.WxPayPubHelper', '', '.php');
      //使用通用通知接口
      $orderQuery = new \OrderQuery_pub();
      $orderQuery->setParameter("out_trade_no", $order_sn); //商户订单号 
      //获取订单查询结果
      $result = $orderQuery->getResult();
      if($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS'){
        if($result['trade_state'] == 'SUCCESS'){
          $this->afterPaySuccess($order_sn, $result, $order_type);
          //支付成功
          $return_data = array('code' => 200, 'data' => $result);
        }else{
          $return_data['error'] = '未支付的订单';
        }
      }else{
        $return_data['error'] = '业务逻辑错误';
      }
      //支付未成功，记录日志信息
      if($return_data['code'] == 300){
        //订单结果缓存
        $result['error_msg'] = $return_data['error'];
        F('WeixinPay/order_query/'.date('Ym/d/').$order_sn.'-'.date('-His'), $result);
      }
    }
    return $return_data;
  }

  /**
   * 支付成功订单类型路由
   * @param string $order_sn 支付长订单号
   * @param array $order_info 支付接口返回的数据
   * @param string $order_type 订单类型  item_order 商品订单[默认]
   * @return void 返回类型为空
   * @author Max.Yu <max@jipu.com>
   */
  public function afterPaySuccess($order_sn, $order_info, $order_type = 'item_order'){
    //根据订单类型，处理业务逻辑
    $return = null;
    switch($order_type){
      case 'item_order' : //商品订单
        $return = $this->afterPayItemOrder($order_sn, $order_info);
        break;
      case 'crowdfunding_order' : //众筹订单
        $return = $this->afterPayCrowdfundingOrder($order_sn, $order_info);
        break;
      case 'redpacket_order' : //红包订单
        $return = $this->afterPayRedpacketOrder($order_sn, $order_info);
        break;
      case 'join_order' : //拼团订单
        $return = $this->afterPayJoinOrder($order_sn, $order_info);
        break;
    }
    return $return;
  }

  /**
   * 红包订单支付成功后业务逻辑处理
   * @param string $order_sn 支付长订单号
   * @param array $order_info 支付接口返回的数据
   * @return void 返回类型为空
   * @author Max.Yu <max@jipu.com>
   */
  public function afterPayRedpacketOrder($order_sn, $order_info){
    $Redpacket = M('Redpacket');
    $order_users = $Redpacket->getByOrderSn($order_sn);
    if($order_users && $order_users['payment_status'] == 0){
      //更新订单支付状态
      $order_map = array(
        'order_sn' => $order_sn,
      );
      $order_data = array(
        'payment_status' => 1,
        'payment_return' => json_encode($order_info),
        'payment_time' => NOW_TIME
      );
      $update_order = $Redpacket->where($order_map)->setField($order_data);
    }
  }

  /**
   * 众筹订单支付成功后业务逻辑处理
   * @param string $order_sn 支付长订单号
   * @param array $order_info 支付接口返回的数据
   * @return void 返回类型为空
   * @author Max.Yu <max@jipu.com>
   */
  public function afterPayCrowdfundingOrder($order_sn, $order_info){
    $order_users = D('CrowdfundingUsers')->getOrderInfo($order_sn);
    if($order_users && $order_users['payment_status'] == 0){
      //更新订单支付状态
      $order_map = array(
        'pay_id' => $order_sn,
      );
      $order_data = array(
        'payment_status' => 1,
        'payment_return' => json_encode($order_info),
        'payment_time' => NOW_TIME
      );
      $update_order = M('CrowdfundingUsers')->where($order_map)->setField($order_data);
    }
    //已支付金额合法性检测
    $map = array(
      'order_id' => $order_users['order_id'],
      'payment_status' => 1
    );
    $sum_payed = D('CrowdfundingUsers')->sumPayed($map);
    $order = M('order')->field('id, uid, total_amount, order_sn,payment_id')->getById($order_users['order_id']);
    if($sum_payed >= $order['total_amount']){
      $balance = (float) ($sum_payed - $order['total_amount']);
      //如果支付金额超出，超出部分转入到个人余额帐户
      if($balance > 0){
        $this->rollOutBalance($order['uid'], $order['id'], $balance);
      }
      $data['payment_type'] = 'crowdfunding';
      $result = D('Order')->updateByField(array('id' => $order_users['order_id']), $data);
      if($result){
        $data_success['id'] = $order_users['crowdfunding_id'];
        $data_success['success'] = 1;
        $data_success['expire_time'] = NOW_TIME;
        $res = M('CrowdfundingOrder')->save($data_success);
        if($res){
          $order_info['total_fee'] = $order['total_amount'];
          $order_info['trade_no'] = $order['order_sn'];
          M('Payment')->where(array('id' => $order['payment_id']))->save(array('payment_type' => 'crowdfunding'));
          $this->afterPayItemOrder($order['order_sn'], $order_info);
        }else{
          F('crowdfundingSuccess/'.date('Ym/d/').$order_users['crowdfunding_id'].'-'.date('-His'), $data_success);
        }
      }
    }
  }

  /**
   * 处理余额转入个人余额帐户的方法
   * $uid：用户id
   * $id：所购买商品的订单id
   * $balance：转出金额
   * @author tony <tony@jipu.com>
   */
  private function rollOutBalance($uid, $id, $balance){
    $data['uid'] = $uid;
    $res = M('Member')->where($data)->setInc('finance', $balance);
    if($res){
      $data['order_id'] = $id;
      $data['type'] = 'crowdfunding';
      $data['amount'] = $balance;
      $data['flow'] = 'in';
      $data['create_time'] = NOW_TIME;
      $data['memo'] = '众筹支付超出金额';
      $out = M('Finance')->add($data);
    }else{
      $data['id'] = $id;
      $data['finance'] = $balance;
      F('rollOutCrowdfunding/'.date('Ym/d/').$id.'-'.date('-His'), $data);
    }
    return $out ? $out : 0;
  }
  
  /**
   * 拼团订单支付成功后业务逻辑处理
   * @author buddha <[email address]>
   * @param  [type] $order_sn   [description]
   * @param  [type] $order_info [description]
   * @return [type]             [description]
   */
  public function afterPayJoinOrder($order_sn, $order_info){
      //获取订单信息
      $order = M('join_order')->where(array('join_sn' => $order_sn))->find();
      $payment = M('Payment')->find($order['payment_id']);
      
      //处理不同支付方式的返回数据
      $payment_time = '';
      $payment_amount = '';
      $payment_sn = '';
      //微信支付
      if($payment['payment_type'] == 'wechatpay'){
          $payment_time = strtotime($order_info['time_end']);
          $payment_amount = $order_info['total_fee'] / 100;
          $payment_sn = $order_info['transaction_id'];
          //支付宝
      }elseif($payment['payment_type'] == 'alipay' || $payment['payment_type'] == 'bankpay'){
          $payment_time = NOW_TIME;
          $payment_amount = $order_info['money'];
          $payment_sn = $order_info['trade_no'];
          //支付宝手机版
      }elseif($payment['payment_type'] == 'alipaywap'){
          $payment_time = NOW_TIME;
          $payment_amount = ($order_info['total_fee']) ? $order_info['total_fee'] : $order['total_amount'];
          $payment_sn = $order_info['trade_no'];
      }
      //订单支付记录写入数据库
      $payment_data = array(
          'id' => $order['payment_id'],
          'uid' => $order['uid'],
          'order_id' => $order['id'],
          'order_sn' => $order['order_sn'],
          'payment_sn' => $payment_sn,
          'payment_amount' => $payment_amount,
          'payment_account' => get_nickname($order['uid']),
          'payment_return' => json_encode($order_info),
          'payment_status' => 1
      );
      
      //如果存在支付记录 & 返回
      if(M('Payment')->getByPaymentSn($payment_sn)){
          if($order['status'] == 0){ //且订单状态为待支付 & 说明存入了余额
              return 'unpay';
          }else{ //且订单状态为非待支付 & 说明已经处理过订单
              return 'is_payed';
          }
          //如果不存在支付记录 & 保存支付记录
      }else{
          D('Payment')->update($payment_data);
      }
      
      //更新订单状态，TODO:活动时间判断
      if($order && $order['status'] == 0){
          
          $list = M('join_list')->where(array('id'=>$order['join_id']))->find();
          $item = M('join_item')->alias('a')->join(' LEFT JOIN __JOIN__ b on a.join_id=b.id')->where('a.item_id='.$order['item_id'].' and b.status = 1 ')->field('a.*')->find();
          if(!$item){
            $item = M('join_item')->where('item_id='.$order['item_id'])->order('id desc')->find();
          }
          $model = M('join_order');
          //TODO:所有的都开启事物
          $model->startTrans();
          //开团
          if(empty($list)){
              $joinData['reg_uid']     = $order['uid'];
              $joinData['item_id']     = $order['item_id'];
              $joinData['join_uids']   = $order['uid'];
              $joinData['stime']       = time();
              $joinData['activity_id'] = $item['join_id'];
              //根据限制时间，计算拼团活动结束时间
              // $joinMap['id'] = $order['active_id'];
              $joinMap['stime'] = array('elt',time());
              $joinMap['etime'] = array('gt',time());
              $joinMap['status'] = 1;
              $joinMap[]  = 'FIND_IN_SET('.$order['item_id'].',item_ids)';
              $joinActive = M('join')->where($joinMap)->find();
              // 活动时间
              $endtime    = $joinData['etime'] = empty($joinActive['limit']) || is_null($joinActive['limit']) || $joinActive['limit'] == 0 ? $joinActive['etime'] : intval($joinActive['limit'])*60+time();
              //需要参团人数
              $joinData['num'] = $item['join_num'];
              $jrst = M('join_list')->add($joinData);
              
          //参团
          }else{
              $saveData['join_uids'] = $list['join_uids'].','.$order['uid'];
              $jrst = M('join_list')->where(array('id'=>$order['join_id']))->save($saveData);
              $endtime = M('join_list')->where('id='.$order['join_id'])->getField('etime');
          }
          
          //更新订单支付状态
          $order_map = array(
              'id' => $order['id'],
          );
          $order_data = array(
              'status'  => 1,
              'paytime' => $payment_time,
              'join_id' => $order['join_id'] ? $order['join_id'] : $jrst,
              'ltime'   => $endtime
          );
          
          if($jrst){
              $update_order = $model->where($order_map)->setField($order_data);
              if($update_order){
                  $model->commit();
                  //新增交易记录
                  $transaction_data = array(
                      'uid' => $order['uid'],
                      'order_id' => $order['id'],
                      'order_sn' => $order['join_sn'],
                      'flowid' => create_sn(),
                      'number' => $payment_sn,
                      'type' => '消费',
                      'mode' => $payment['payment_type'],
                      'amount' => $payment_amount,
                      'flow' => 'out',
                      'memo' => '拼团订单消费',
                      'status' => 1,
                  );
                  D('Transaction')->update($transaction_data);
                   
                  //单独的支付完成回调
                  $order['join_id'] = $order_data['join_id'];
                  A('Join','Event')->payCallBack($order);
                  return 'success';
              }else{
                  $model->rollback();
              }
          }else{
              $model->rollback();
          }
      }else{
          //充入余额
          $uid = $order['uid'];
          $order_id = $order['id'];
          $order_sn = $order['join_sn'];
      
          $member_model = D('Member');
          $balance = $member_model->getFinance($uid);
          //更新帐号余额
          $result = $member_model->updateFinance($uid, $payment_amount);
          if($result){
              //新增交易记录
              $transaction_data = array(
                  'uid' => $uid,
                  'order_id' => $order_id,
                  'order_sn' => $order_sn,
                  'flowid' => create_sn(),
                  'type' => '充值',
                  'mode' => 'finance',
                  'amount' => $payment_amount,
                  'balance' => $balance,
                  'flow' => 'in',
                  'memo' => '余额充值',
                  'status' => 1,
              );
              D('Transaction')->update($transaction_data);
              $finance_data = array(
                  'uid' => $uid,
                  'order_id' => $order_id,
                  'type' => 'website',
                  'amount' => $payment_amount,
                  'flow' => 'in',
                  'create_time' => NOW_TIME,
                  'memo' => '余额充值'
              );
              M('Finance')->add($finance_data);
          }
          return 'unpay';
      }  
  }

  /**
   * 商品订单支付成功后业务逻辑处理
   * @param string $order_sn 支付长订单号
   * @param array $order_info 支付接口返回的数据
   * @return void 返回类型为空
   * @author Max.Yu <max@jipu.com>
   */
  public function afterPayItemOrder($order_sn, $order_info){
    //获取订单信息
    $order = M('Order')->where(array('order_sn' => $order_sn))->find();
    $payment = M('Payment')->find($order['payment_id']);
    //处理不同支付方式的返回数据
    $payment_time = '';
    $payment_amount = '';
    $payment_sn = '';
    //微信支付
    if($payment['payment_type'] == 'wechatpay'){
      $payment_time = strtotime($order_info['time_end']);
      $payment_amount = $order_info['total_fee'] / 100;
      $payment_sn = $order_info['transaction_id'];
      //支付宝
    }elseif($payment['payment_type'] == 'alipay' || $payment['payment_type'] == 'bankpay'){
      $payment_time = NOW_TIME;
      $payment_amount = $order_info['money'];
      $payment_sn = $order_info['trade_no'];
      //支付宝手机版
    }elseif($payment['payment_type'] == 'alipaywap'){
      $payment_time = NOW_TIME;
      $payment_amount = ($order_info['total_fee']) ? $order_info['total_fee'] : $order['total_amount'];
      $payment_sn = $order_info['trade_no'];
      //众筹方式支付（众筹成功后进行）
    }elseif($payment['payment_type'] == 'crowdfunding'){
      $payment_time = NOW_TIME;
      $payment_amount = $order_info['total_fee'];
      $payment_sn = $order_info['trade_no'];
    }
    
    //订单支付记录写入数据库
    $payment_data = array(
      'id' => $order['payment_id'],
      'uid' => $order['uid'],
      'order_id' => $order['id'],
      'order_sn' => $order['order_sn'],
      'payment_sn' => $payment_sn,
      'payment_amount' => $payment_amount,
      'payment_account' => get_nickname($order['uid']),
      'payment_return' => json_encode($order_info),
      'payment_status' => 1
    );
    //如果存在支付记录 & 返回
    if(M('Payment')->getByPaymentSn($payment_sn)){
      if($order['o_status'] == 0){ //且订单状态为待支付 & 说明存入了余额
        return 'unpay';
      }else{ //且订单状态为非待支付 & 说明已经处理过订单
        return 'is_payed';
      }
    //如果不存在支付记录 & 保存支付记录
    }else{
      D('Payment')->update($payment_data);
    }
    
    if($order && $order['o_status'] == 0){
      //验证账号余额是否扣成功，如果失败，则不改订单状态，把第三方支付的钱充入余额 @Justin 2015.5.9
      $payment['finance_amount'] = 0.00 && $payment['is_use_finance'] = 0;
      $stock = $this->checkItemStock($order['id']);
      if($stock && (!$payment['is_use_finance'] && !$payment['is_use_coupon'] && !$payment['is_use_card'] && $payment['use_score'] == 0) || (($payment['is_use_finance'] || $payment['is_use_coupon'] || $payment['is_use_card'] || $payment['use_score']) && $this->doQuickPay($order))){
        //更新订单支付状态
        $order_map = array(
          'id' => $order['id'],
        );
        $order_data = array(
          'o_status' => 200,
          'payment_time' => $payment_time
        );
        $update_order = M('Order')->where($order_map)->setField($order_data);
        //$user_auth = session('user_auth');
        if($update_order){
          //新增交易记录
          $transaction_data = array(
            'uid' => $order['uid'],
            'order_id' => $order['id'],
            'order_sn' => $order['order_sn'],
            'flowid' => create_sn(),
            'number' => $payment_sn,
            'type' => '消费',
            'mode' => $payment['payment_type'],
            'amount' => $payment_amount,
            'flow' => 'out',
            'memo' => '购物消费',
            'status' => 1,
          );
          D('Transaction')->update($transaction_data);
          //支付完成回调
          $this->payCallBack($order);
        }
      }else{
        //充入余额
        $uid = $order['uid'];
        $order_id = $order['id'];
        $order_sn = $order['order_sn'];

        $member_model = D('Member');
        $balance = $member_model->getFinance($uid);
        //更新帐号余额
        $result = $member_model->updateFinance($uid, $payment_amount);
        if($result){
          //新增交易记录
          $transaction_data = array(
            'uid' => $uid,
            'order_id' => $order_id,
            'order_sn' => $order_sn,
            'flowid' => create_sn(),
            'type' => '充值',
            'mode' => 'finance',
            'amount' => $payment_amount,
            'balance' => $balance,
            'flow' => 'in',
            'memo' => !$stock ? '库存不足冲入余额' : '余额充值',
            'status' => 1,
          );
          D('Transaction')->update($transaction_data);
          $finance_data = array(
            'uid' => $uid,
            'order_id' => $order_id,
            'type' => 'website',
            'amount' => $payment_amount,
            'flow' => 'in',
            'create_time' => NOW_TIME,
            'memo' => !$stock ? '库存不足冲入余额' : '余额充值'
          );
          M('Finance')->add($finance_data);
          //return $result;
        }
        return 'unpay';
      }
    }
  }

  /**
   * 执行快速支付
   * @author Max.Yu <max@jipu.com>
   */
  public function doQuickPay($order){
    if(empty($order)){
      return false;
    }
    $payment_data = M('Payment')->find($order['payment_id']);

    //优惠券
    if($payment_data['is_use_coupon'] == 1){
      $update_coupon = $this->doCouponPay($order['uid'], $order['id'], $order['order_sn'], $payment_data['coupon_id'], $payment_data['coupon_amount']);

      if(!$update_coupon){
        return false;
      }
    }

    //礼品卡
    if($payment_data['is_use_card'] == 1){
      $update_card = $this->doCardPay($order['uid'], $order['id'], $order['order_sn'], $payment_data['card_id'], $payment_data['card_amount']);
      if(!$update_card){
        return false;
      }
    }
    
    //积分支付
    if($payment_data['use_score'] > 0){
      $update_score = $this->doScorePay($order['uid'], $order['id'], $order['order_sn'], $payment_data['use_score']);
      if(!$update_score){
        return false;
      }
    }
    
    //账户余额
    if($payment_data['is_use_finance'] == 1 && $payment_data['finance_amount'] > 0.00){
      $update_finance = $this->doFinancePay($order['uid'], $order['id'], $order['order_sn'], $payment_data['finance_amount']);
      if(!$update_finance){
        return false;
      }
    }
    return true;
  }

  /**
   * 优惠券无余额，更新优惠券状态为已使用
   * @author Max.Yu <max@jipu.com>
   */
  public function doCouponPay($uid, $order_id, $order_sn, $coupon_id, $amount){
    //判断优惠券的使用状态是否为未使用
    $check_coupon = $this->checkCoupon($uid, $coupon_id);
    if($amount <= 0){
      return false;
    }
    //更新优惠券使用情况
    $map_coupon = array(
      'uid' => $uid,
      'coupon_id' => $coupon_id,
    );
    $data_coupon = array(
      'status' => 1,
      'use_time' => NOW_TIME,
      'use_amount' => $amount,
      'order_id' => $order_id,
      'order_sn' => $order_sn,
    );
    $result = D('CouponUser')->updateFeilds($map_coupon, $data_coupon);
    if($result){
      return $result;
    }else{
      return false;
    }
  }

  /**
   * 扣减礼品卡余额
   * @author Max.Yu <max@jipu.com>
   */
  public function doCardPay($uid, $order_id, $order_sn, $card_id, $amount){
    //判断当前礼品卡余额是否大于订单中礼品卡使用额度
    if(!$this->checkCard($uid, $card_id, $amount)){
      return false;
    }
    if($amount <= 0){
      return false;
    }
    //扣除礼品卡余额
    $card_model = D('Card');
    $map_card['id'] = array('IN', $card_id);
    $map_order = array(
      'expire_time' => 'ASC',
      'balance' => 'ASC',
    );
    $card = $card_model->lists($map_card, $map_order, true);
    if($card){
      //遍历订单中所有礼品卡，扣减余额
      foreach($card as $key => $val){
        $dec_amount = ($amount > $val['balance']) ? $val['balance'] : sprintf('%.2f', $amount);
        $update = $card_model->where('id = '.$val['id'])->setDec('balance', $dec_amount);
        if(!$update){
          return false;
        }
        //记录礼品卡支付日志
        $data = array(
          'uid' => $uid,
          'order_id' => $order_id,
          'order_sn' => $order_sn,
          'card_id' => $val['id'],
          'card_number' => $val['number'],
          'card_name' => $val['name'],
          'amount' => $dec_amount,
        );
        D('CardLog')->update($data);
        //更新礼品卡使用状态
        if($val['is_use'] == 0){
          $card_data = array(
            'is_use' => 1,
            'use_time' => NOW_TIME,
          );
          $card_model->updateFeilds(array('id' => $val['id']), $card_data);
        }
        //计算下一次扣减金额
        $amount = $amount - $dec_amount;
        if($amount <= 0){
          break;
        }
      }

      return true;
    }
    return false;
  }
  
  /**
   * 扣减积分
   * @author Max.Yu <max@jipu.com>
   */
  public function doScorePay($uid, $order_id, $order_sn, $use_score){
    $score = M('Member')->getFieldByUid($uid, 'score');
    if($score >= $use_score){
      $result = M('Member')->where(array('uid' => $uid))->setDec('score', $use_score);
      if($result){
        //记录积分日志
        $data = array(
          'uid' => $uid,
          'order_id' => $order_id,
          'order_sn' => $order_sn,
          'type' => 'out',
          'amount' => $use_score,
          'memo' => '购物订单完成消费积分',
          'create_time' => NOW_TIME,
        );
        $res = M('ScoreLog')->add($data);
        if($res){
          return true;
        }
      }
    }
    return false;
  }

  /**
   * 扣减账户余额
   * @param integer $uid 用户UID
   * @param integer $order_id 订单ID
   * @param string $order_sn 订单编号
   * @param double $amount 账户余额使用金额
   * @return boolean
   * @author Max.Yu <max@jipu.com>
   */
  public function doFinancePay($uid, $order_id, $order_sn, $amount){
    //判断当前账户余额是否大于订单中的账户使用额度
    if(!$this->checkFinance($uid, $amount)){
      return false;
    }
    //获取账户充值前余额
    $member_model = D('Member');
    $balance = $member_model->getFinance($uid);
    //更新帐号余额
    $result = $member_model->updateFinance($uid, $amount, 'dec');
    if($result){
      //新增交易记录
      $transaction_data = array(
        'uid' => $uid,
        'order_id' => $order_id,
        'order_sn' => $order_sn,
        'flowid' => create_sn(),
        'type' => '消费',
        'mode' => 'finance',
        'amount' => $amount,
        'balance' => $balance,
        'flow' => 'out',
        'memo' => '购物消费',
        'status' => 1,
      );
      D('Transaction')->update($transaction_data);
      $finance_data = array(
        'uid' => $uid,
        'order_id' => $order_id,
        'type' => 'website',
        'amount' => $amount,
        'flow' => 'out',
        'create_time' => NOW_TIME,
        'memo' => '购物消费'
      );
      M('Finance')->add($finance_data);
      return $result;
    }
    return false;
  }

  /**
   * 判断当前礼品卡余额是否大于订单中礼品卡使用额度
   * @param integer $uid 用户UID
   * @param integer $card_id 礼品卡ID
   * @param double $use_amount 礼品卡使用金额
   * @return boolean
   * @author Max.Yu <max@jipu.com>
   */
  public function checkCard($uid, $card_id = null, $use_amount = 0.00){
    if($uid && $card_id){
      //定义礼品卡余额总和
      $total_balance = 0;
      //实例化礼品卡模型
      $card_model = M('Card');
      //定义返回或者操作的字段
      $field = 'SUM(balance) AS total_balance';
      //查询条件初始化
      $where = array(
        'uid' => $uid,
        'id' => array('IN', $card_id),
        'expire_time' => array('GT', (NOW_TIME - 86400)),
      );
      //获取订单数量，金额统计信息
      $data = $card_model->where($where)->field($field)->find();
      if($data){
        $total_balance = $data['total_balance'];
      }
      if($total_balance && $total_balance >= $use_amount){
        return true;
      }else{
        return false;
      }
    }else{
      return false;
    }
  }

  /**
   * 判断当前账户余额是否大于订单中的账户使用额度
   * @param integer $uid(用户ID)
   * @param double $use_amount 账户余额使用金额
   * @return boolean
   * @author Max.Yu <max@jipu.com>
   */
  public function checkFinance($uid = null, $use_amount = 0.00){
    if($uid){
      $finance = D('Member')->getFinance($uid);
      if($finance && $finance >= $use_amount){
        return true;
      }else{
        return false;
      }
    }else{
      return false;
    }
  }

  /**
   * 更新订单中商品库存数和销量
   * @param integer $order_id 订单ID
   * @return boolean
   * @author Max.Yu <max@jipu.com>
   */
  public function updteStockAndBuy($order_id){
    if(empty($order_id)){
      return false;
    }elseif(is_nan($order_id)){
      return false;
    }
    //检测订单中商品的当前库存是否大于等于客户订单中购买的数量
    if(!$this->checkItemStock($order_id)){
      return false;
    }
    //获取订单项目数据
    $where['order_id'] = $order_id;
    $field = 'item_id, item_code, number, quantity';
    $order_item = D('OrderItem')->where($where)->field($field)->select();
    if($order_item){
      $item_model = M('Item');
      foreach($order_item as $key => $val){
        if($val['item_code']){
          //判断是否配置了规格分库存
          if($val['item_code'] !== $val['number']){
            //更新规格分库存
            M('ItemSpecifiction')->where(array('spc_code' => $val['item_code'],'item_id'=> $val['item_id']))->setDec('quantity', $val['quantity']);
          }
          //更新总库存
          $item_model->where(array('id' => $val['item_id']))->setDec('stock', $val['quantity']);
          //更新购买数量
          $item_model->where(array('id' => $val['item_id']))->setInc('buynum', $val['quantity']);
        }
      }
    }
  }

  /**
   * 判断优惠券的使用状态是否为未使用
   * @param integer $coupon_id 优惠券ID
   * @return boolean
   * @author Max.Yu <max@jipu.com>
   */
  public function checkCoupon($uid, $coupon_id){
    if($uid && $coupon_id){
      //获取用户优惠券数据
      $where = array(
        'uid' => $uid,
        'coupon_id' => $coupon_id,
      );
      $coupons = D('CouponUser')->lists($where);
      if($coupons){
        foreach($coupons as $key => $value){
          //判断优惠券是否过期
          if($value['Coupon']['is_expire'] == 1){
            return false;
          }

          //判断是否已使用
          if($value['status'] == 1){
            return false;
          }
        }
        return true;
      }else{
        return false;
      }
    }else{
      return false;
    }
  }

  /**
   * 检测订单中商品的当前库存是否大于等于客户订单中购买的数量
   * @param integer $order_id 订单ID
   * @return boolean
   * @author Max.Yu <max@jipu.com>
   */
  public function checkItemStock($order_id){
    //获取订单项
    $order_item = D('OrderItem')->getOrderItem($order_id);

    //遍历订单商品，逐个检测库存
    if($order_item && is_array($order_item)){
      $item_model = D('Item');
      $item_spc_model = D('ItemSpecifiction');
      foreach($order_item as $key => $val){
        //判断是否配置了规格分库存
        $quantity = $item_spc_model->where(array('item_id' => $val['item_id']))->getFieldBySpcCode($val['item_code'], 'quantity');
        if($quantity){
          if($quantity < $val['quantity']){
            return false;
          }
        }else{
          $quantity = $item_model->getFieldById($val['item_id'], 'stock');
          if($quantity < $val['quantity']){
            return false;
          }
        }
      }
      return true;
    }
  }
  
  /**
   * 检测订单中商品的限购情况
   * @param integer $order_id 订单ID
   * @return boolean
   * @author Max.Yu <max@jipu.com>
   */
  public function checkItemQuota($order_id){
    //获取订单项
    $order_item = D('OrderItem')->getOrderItem($order_id);

    //遍历订单商品，逐个检测库存
    if($order_item && is_array($order_item)){
      foreach($order_item as $val){
        $quota_num = get_quota_num($val['item_id']);
        if($quota_num < $val['quantity']){
          return false;
        }
      }
      return true;
    }
    return false;
  }
  
  /**
   * 获取weixin-JS配置Sign
   * @author Max.Yu <max@jipu.com>
   */
  public function getConfigSign($data){
    $url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $string = "jsapi_ticket={$data['jsapiticket']}&noncestr={$data['nonceStr']}&timestamp={$data['timestamp']}&url=$url";
    $signStr = sha1($string);
    return $signStr;
  }

  /**
   * 获取预付订单号
   * @param array $data 待支付订单信息
   * @param string $order_type 订单类型
   * @author Max.Yu <max@jipu.com>
   */
  public function getPrePayId($data, $order_type = 'item_order',$trade_type = 'JSAPI'){
    $order_id = $data['orderid'];
    if(empty($order_id)){
      return '';
    }
    $cache_name = 'no-clear:weixin_prepay_id_'.$order_type.'_'.$order_id;
    if(!S($cache_name)){
      import('Org.Wechat.Pay.WxPayPubHelper', '', '.php');
      //使用统一支付接口
      $unifiedOrder = new \UnifiedOrder_pub($trade_type);
      //获取订单信息
      $order_info = $this->getOrderInfoToPrePayId($order_id, $order_type);
      $total_fee = $order_info['amount'] * 100; //参数规定最小单位为分
      $notify_url = SITE_URL.U('Api/weixinNotify', array('order_type' => $order_type)); //回调地址
        if($trade_type == "APP"){
            $notify_url = SITE_URL.U('Api/weixinAppNotify', array('order_type' => $order_type)); //回调地址
        }
      if($trade_type != "APP"){
          $unifiedOrder->setParameter("openid", $data['openid']); //openid
      }
      $unifiedOrder->setParameter("body", $order_info['body']); //商品描述
      $unifiedOrder->setParameter("out_trade_no", $order_info['order_sn']); //商户订单号 
      $unifiedOrder->setParameter("total_fee", $total_fee); //总金额
      $unifiedOrder->setParameter("notify_url", $notify_url); //通知地址 
      $unifiedOrder->setParameter("trade_type", $trade_type); //交易类型
      //预付订单号
      $prepay_id = $unifiedOrder->getPrepayId();
      if($prepay_id){
        S($cache_name, $prepay_id, 6000);
      }
    }else{
      $prepay_id = S($cache_name);
    }
      return $prepay_id;
  }

  /**
   * 获取用户微信OpenId
   * @author Max.Yu <max@jipu.com>
   */
  public function getOpenId(){
    if(is_weixin()){
      if(cookie('user_openid')){
        $openid = cookie('user_openid');
      }else{
        if(C('WECHAT_USERINFO_BY_API') == true){
          $openid = A('User', 'Event')->getOpenIdByApi();
        }else{
          import('Org.Wechat.Pay.WxPayPubHelper', '', '.php');
          $jsApi = new \JsApi_pub();
          //获取OpenId
          $openid = '';
          if(!isset($_GET['code'])){
            $call_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $url = $jsApi->createOauthUrlForCode(urlencode($call_url));
            redirect($url);
          }else{
            $code = I('get.code');
            $jsApi->setCode($code);
            $openid = $jsApi->getOpenId();
          }
        }
        if($openid){
          //加入cookie
          C('COOKIE_EXPIRE', 3600*24*365);
          cookie('user_openid', $openid);
        }
      }
      return $openid;
    }
  }

  /**
   * 微信支付参数生成
   * @param integer $orderid 订单ID
   * @param string $order_type 订单类型
   * @author Max.Yu <max@jipu.com>
   */
  public function getWeixinPayParam($orderid, $order_type = 'item_order'){
    //获取OpenId
    $openid = $this->getOpenId();
    //获取预付订单号
    $get_data = array(
      'orderid' => $orderid,
      'openid' => $openid
    );
    import('Org.Wechat.Pay.WxPayPubHelper', '', '.php');
    $jsApi = new \JsApi_pub();

    $prepay_id = $this->getPrePayId($get_data, $order_type);
    //获取js订单提交参数
    $signArr = $jsApi->getParametersArr($prepay_id);
    $signArr['timestamp'] = $signArr['timeStamp'];
    //config初始化参数
    $configJson = get_jsapi_config('chooseWXPay');
    //返回需要的参数
    return array(
      'c' => $configJson,
      'w' => json_encode($signArr),
    );
  }

  /**
   * 根据支付类型设置支付接口参数
   * @param string $payment_type 支付类型
   * @param string $order_type 订单类型
   * @return array
   * @author Max.Yu <max@jipu.com>
   */
  public function setPaymentConfig($payment_type, $order_type = 'item_order'){
    $payment_config = array();
    if($payment_type){
      $payment_config['notify_url'] = U('Api/payNotify', array('apitype' => $payment_type, 'method' => 'notify', 'order_type' => $order_type), false, true);
      $payment_config['return_url'] = U('Api/payNotify', array('apitype' => $payment_type, 'method' => 'return', 'order_type' => $order_type), false, true);
      $payment_config['merchant_url'] = U('Api/payNotify', array('apitype' => $payment_type, 'method' => 'merchant', 'order_type' => $order_type), false, true);
      $payment_config = array_merge($payment_config, C(strtoupper($payment_type)));
    }
    return $payment_config;
  }

  /**
   * 根据支付类型设置支付接口参数
   * @param string $payment_type 支付类型
   * @param string $order_type 订单类型
   * @return array
   * @author Max.Yu <max@jipu.com>
   */
  public function setAppPaymentConfig($payment_type, $order_type = 'item_order'){
      $payment_config = array();
      if($payment_type){
          $payment_config['notify_url'] = U('Api/AliAppPayNotify', array('apitype' => $payment_type, 'method' => 'notify', 'order_type' => $order_type), false, true);
          $payment_config['private_key_path'] = APP_PATH.'Common/Conf/Cacert/Ali/rsa_private_key.pem'; //支付宝开发者的私钥文件路径
          $payment_config['public_key_path'] = APP_PATH.'Common/Conf/Cacert/Ali/alipay_public_key.pem'; //支付宝的公钥文件路径
          $payment_config['return_url'] = U('Api/AliAppReturnNotify', array('apitype' => $payment_type, 'method' => 'return', 'order_type' => $order_type), false, true);
          $payment_config['merchant_url'] = U('Api/AliAppPayNotify', array('apitype' => $payment_type, 'method' => 'merchant', 'order_type' => $order_type), false, true);
          $payment_config = array_merge($payment_config, C(strtoupper($payment_type)));
      }
      return $payment_config;
  }
  
  /**
   * 支付完成回调方法
   * @param array $order 订单信息
   * @version 2015080614
   * @author Justin <justin@jipu.com>
   */
  function payCallBack($order){
    //更新订单商品库存和销量
    $this->updteStockAndBuy($order['id']);
    //先判断是否需要按供应商拆分订单
    A('Order', 'Event')->splitOrderBySupplier($order['id']);
    //订单支付后通知
    A('Notice', 'Event')->afterPaid($order);
  }

  /**
   * 推广联盟订单返钱(桌面商家)psd
   * @param array $order 订单信息
   * @version 2015101915
   * @author Justin <justin@jipu.com>
   */
  private function _union_order_cash_back($order){
    $union_id = M('User')->getFieldById($order['uid'], 'from_union_id');
    if($union_id){
      //获取推广者uid
      $union_data = M('Union')->field('uid, type')->find($union_id);
      if(1 == $union_data['type']){
        //桌面商家，优先采用百分比
        if(C('UNION_ORDER_RATEBACK')){
            $total_price = M('Order')->where(array('id'=>$order['id']))->getField('total_price');
            $total_price = (C('UNION_ORDER_RATEBACK')/100)*$total_price;
            $amount = sprintf("%.2f",substr(sprintf("%.3f", $total_price), 0, -1));
        }else{
            $amount = C('UNION_ORDER_CASHBACK') ?: 0.01;
        }
        //增加推广余额
        M('Member')->where('uid='.$union_data['uid'])->setInc('finance', $amount); 

        $data_finance = array(
          'uid' => $union_data['uid'],
          'order_id' => $order['id'],
          'type' => 'union_order',
          'amount' => $amount,
          'flow' => 'in',
          'memo' => '推广联盟订单返现',
          'create_time' => NOW_TIME
        );
        $update = M('Finance')->add($data_finance);
      } 
    }
  }

  /**
   * 订单中商品赠送积分
   * @author buddha <[email address]>
   * @param  [type] $order [description]
   * @return [type]           [description]
   */
  function doScoreGive( $order, $type = 1 ){
    //查询订单中商品 不管商品个数只赠送一次
    if ($type == 1) {
      $pos = strpos($order['item_ids'],',');
      if ($pos !== false) {
        $score = M('Item')->where(array('id'=>array('in',$order['item_ids'])))->sum('credit');
      }else{
        $score = M('Item')->where(array('id='.$order['item_ids']))->getField('credit');
      }
    }else{
      //获取订单商品ID和对应的购买数量
      $order_items = M('OrderItem')->where(array('order_id' => $order['order_id']))->getField('item_id,quantity', true);
      //根据订单商品ID和购买数量计算订单总积分
      if($order['uid'] && $order_items && is_array($order_items)){
        foreach($order_items AS $key => $val){
          $item_credit = M('Item')->getFieldById($key, 'credit');
          if($item_credit && is_numeric($item_credit)){
            $score = $item_credit * $val;
          }
        }
      }
    }
    if (!empty($score) && ($score > 0)) {
      //记录积分日志
      $data = array(
          'uid'=>$order['uid'],
          'order_id'=>$order['id'],
          'order_sn'=>$order['order_sn'],
          'type'=>'in',
          'amount'=>$score,
          'memo'=>'购物赠送积分',
          'create_time'=>NOW_TIME,
          'status'=>0//状态为0 不可用
        );
      $res = M('ScoreLog')->add($data);
      //$result = M('Member')->where(array('uid' => $order['uid']))->setInc('score', $score);
    }
  }
  
}
