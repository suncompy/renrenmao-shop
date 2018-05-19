<?php
/**
 * 退款事件模型
 * @author Max.Yu <max@jipu.com>
 */

namespace Admin\Event;

class RefundEvent{

  /**
   * 执行退款
   * @param array $ids 订单编号集合
   * @param string $dotype 执行类型（cancel取消订单，refund退款）
   * @return array 执行结果
   */
  public function doit($ids, $dotype = 'cancel'){
    $return_data = array('code' => 300);
    $map['id'] = array('in', $ids);
    if(M('Order')->where($map)->setField('o_status', ($dotype == 'cancel' ? 404 : 304))){
      //处理库存和退款
      $order_lists = D('Order')->lists($map);
      if($order_lists){

        foreach($order_lists as $key => $value){
          //商品库存处理
          $order_items = get_order_item($value['id']);
          if($order_items){
            $item_model = M('Item');
            foreach($order_items as $item){
              if($spec = M('ItemSpecifiction')->where('item_id ='.$item['item_id'].' and spc_code = "'.$item['item_code'].'"')->getField('id')){
                M('ItemSpecifiction')->where('id ='.$spec)->setInc('stock', $item['quantity']);
              }
              $item_model->where('id='.$item['item_id'])->setInc('stock', $item['quantity']);
            }
          }
          //第三方退款处理
          if($value['total_amount'] > 0){
            $payment = M('Payment')->getById($value['payment_id']);
            //众筹订单退款原路返回 要考虑拆单情况
            if($value['payment_type'] == 'crowdfunding'){
              $order_id = $value['id'];
              //查询拆分订单前订单
              $order_count = M('Order')->where('payment_id='.$value['payment_id'])->count();
              $old_map = array('payment_id' => $value['payment_id']);
              if ($order_count > 1) {
                $old_map['status'] = -2;
                $order_id = M('Order')->where($old_map)->getField('id');
              }
              $couwd_map = array(
                'order_id' => $order_id,
                'payment_status' => array('in' , array( 1 , 3 ) ) ,
                'pay_money' => array('gt' , 0 ),
              );
              $crowds = M('CrowdfundingUsers')->where($couwd_map)->field('id,payment_status,pay_money,payment_return,payment_type')->order('pay_money asc')->select();
              if($crowds){
                //待退款金额 
                $refund_amount = $value['total_amount'];
                foreach($crowds as $ck => $cv){
                  $payment_return = json_decode($cv['payment_return'], true);
                  $trade_no = $payment_return['out_trade_no'];
                  if ($refund_amount >= 0.01) {
                    if($cv['payment_status'] == 3){
                      $pc_map = array(
                        'row_id' => $cv['id'],
                        'orderid'=> $order_id,
                        'type'   => 'crowdfunding',
                      );
                      $payed_crownd =  M('PaymentLog')->where($pc_map)->sum('amount');
                      if($cv['pay_money'] - $payed_crownd > 0 ){
                        $cv['pay_money'] -= $payed_crownd;
                      }else{
                        M('CrowdfundingUsers')->where('id ='.$cv['id'])->setField('payment_status' ,2 );
                        continue;
                      }
                    }
                    $refund_data = array(
                      'uid' => $value['uid'],
                      'order_id' => $value['id'],
                      'payment_type' => $cv['payment_type'],
                      'trade_no' => $trade_no,
                      'refund_type' => 'item',
                      'amount' => min($refund_amount , $cv['pay_money'] ),
                      'detail' => '购物退款',
                      'refund_return' => $cv['payment_return'] ,
                    ); 
                    D('Refund')->update($refund_data);
                    if($refund_amount - $cv['pay_money'] < 0 ){
                      M('CrowdfundingUsers')->where('id ='.$cv['id'])->setField('payment_status' , 3 );
                    }else{
                      M('CrowdfundingUsers')->where('id ='.$cv['id'])->setField('payment_status' , 2 );
                    }
                    //写入记录表中
                    $data = array(
                      'uid'    => $value['uid'],
                      'row_id' => $cv['id'],
                      'orderid'=> $order_id,
                      'type'   => 'crowdfunding',
                      'amount' => sprintf('%.2f' , min($refund_amount , $cv['pay_money']) ),
                      'create_time'=>NOW_TIME,
                    );
                    $payment_log = M('PaymentLog')->add($data);
                    $refund_amount -=  min($refund_amount , $cv['pay_money'] ) ;
                    if($refund_amount < 0.01)break;
                  }
                }
              }
            }else{
              $payment_return = json_decode($payment['payment_return'], true);
              $trade_no = $payment_return['trade_no'];
              if(empty($trade_no)){
                $trade_no = $payment_return['transaction_id'];
              }
              if(empty($trade_no)){
                $trade_no = M('Payment')->getFieldByOrderId($value['id'], 'payment_sn');
              }
              $refund_data = array(
                'uid' => $value['uid'],
                'order_id' => $value['id'],
                'payment_type' => $payment['payment_type'],
                'trade_no' => $trade_no,
                'refund_type' => 'item',
                'amount' => $value['total_amount'],
                'detail' => '购物退款'
              ); 
              D('Refund')->update($refund_data);
            }
          }else{
            M('Order')->where($map)->setField('o_status', ($dotype == 'cancel' ? 404 : 303));
          }
          //账户余额返还
          if($value['finance_amount'] > 0){
            $this->refundFinance($value['id']);
          }
          //礼品卡返还
          $this->refundCard($value['id']);
          //消费积分返还用户 赠送积分返还平台
          $this->refundScore($value['id']);
          //推广分销返现 取消
          $this->series_refund($value['id']);
          //分销店铺订单取消返现
          if($value['sdp_uid'] > 0){
            $this->sdp_order($value['id']);        
          }
        }
      }
      if ($order_lists[0]['total_amount'] == 0) {
        $return_data = array('code' => 200, 'info' => '订单'.($dotype == 'cancel' ? '取消' : '退款').'成功！');
      }else{
        $return_data = array('code' => 200, 'info' => '订单'.($dotype == 'cancel' ? '取消成功！' : '退款处理中'));
      }
    }else{
      $return_data = array('code' => 300, 'info' => '订单'.($dotype == 'cancel' ? '取消' : '退款').'失败！');
    }
    return $return_data;
  }


  /**
   * 退款成功后，更新退款订单
   */
  public function afterRefundSuccess($refund_no, $refund_info){
    //获取退款订单信息
    $refund_details = explode('#', $refund_info['details']);
    // add_wechat_log($refund_details, 'refund-details');
    if($refund_details){
      foreach($refund_details as $key => $value){
        $refund_item = explode('^', $value);
        $refund_map = array(
          'trade_no' => $refund_item[0],
          'refund_no' => $refund_no
        );
        $refund_orders = M('Refund')->where($refund_map)->select();
        foreach($refund_orders as $refund_order){
          if(($refund_item[2] == 'SUCCESS') && ($refund_order['refund_status'] == 0)){
            $refund_data = array(
              'refund_status' => 1,
              'refund_return' => json_encode($refund_info)
            );
            // add_wechat_log($refund_data, 'refund-data');
            M('Refund')->where('id = '.$refund_order['id'])->save($refund_data);
            //更改订单状态
            M('Order')->where('id='.$refund_order['order_id'])->setField('o_status',303);
            // if($refund_order['type'] == 1){
            //   $this->series_refund($refund_order['order_id']);
            // }else
            if($refund_order['type'] == 2){
              $this->handlejoin($refund_order['order_id']);
            }
          }
        }
      }
    }
  }

  /**
   * 处理团购退款
   * @author buddha <[email address]>
   * @param  [type] $order_id [description]
   * @return [type]           [description]
   */
  function handlejoin($order_id){
    if($result = M('Join_order')->alias('a')->join('RIGHT JOIN __JOIN_LIST__ b on a.join_id=b.id')->where('a.id='.$order_id.' and b.status=2')->getField('a.join_id')){
      M('Join_list')->where('id='.$result)->setField('status' ,3);
    }
  }

  /**
   * 推广分销返现 取消
   * @author buddha <[email address]>
   * @param  [type] $orderid [description]
   * @return [type]          [description]
   */
  function series_refund($orderid){
    $where['type']   = array('in' ,array('union_order')) ;
    $where['flow']   = 'in' ;
    $where['status'] = 0 ;
    $where['order_id']     = $orderid ;
    $result = M('Finance')->where($where)->getField('id' ,true);
    if(!$result){
      return false;
    }
    $map = implode(',' , $result);
    M('Finance')->where('id in ('.$map.')')->setField('status' , '2');
    $users = M('Finance')->where('id in ('.$map.')')->field('uid,amount')->select();
    foreach($users as $k => $v){
      M('Member')->where('uid ='.$v['uid'])->setDec('finance' ,$v['amount']);
    }
    M('Distributlog')->where('order_id ='. $orderid)->setField('status' , '-1' );
  }
// .-----------------------------------------------------------------------------------
// | Version: 2017
// |-----------------------------------------------------------------------------------
// | Author: Fourth Pu <540690666@qq.com|ppssone@live.cn>
// |-----------------------------------------------------------------------------------
// | 分销店铺退款
// .-----------------------------------------------------------------------------------
  public function sdp_order($order_id){
    $order_sn = M('Order')->where('id ='.$order_id)->getField('order_sn');
    $finances = M('Finance')->where(array('order_id' => $order_id , 'type' => 'sdp_order'))->select();
    if($finances){
      $finance_ids = array_column($finances ,'id');
      $map['id'] = array('in' , $finance_ids );
      M('Finance')->where($map)->setField('status' , 1);
      foreach($finances as $k => $v){
        unset($v['id']);
        $v['flow'] = 'out';
        $v['memo'] = '分销订单退款,订单编号：'.$order_sn;
        $v['create_time'] = NOW_TIME;
        $v['status'] = 1;
        $v['type'] = 'sdp_refund';
        M('Finance')->add($v);
        M('Member')->where('uid = '.$v['uid'])->setDec('finance', $v['amount']);
        //分销店铺累计收入扣除
        M('Shop')->where('uid='.$v['uid'])->setDec('total_revenue', $v['amount']);
      }
      M('SdpRecord')->where('order_id ='.$order_id)->delete();
    }
  }
  /**
   * 账户余额返还处理
   */
  public function refundFinance($order_id, $memo = '订单退款'){
    $order = M('Order')->find($order_id);
    if($order && $order['finance_amount'] > 0){
      //更新用户账户余额
      $update_member = M('Member')->where('uid = '.$order['uid'])->setInc('finance', $order['finance_amount']);
      if($update_member){
        //增加更新记录
        $data_finance = array(
          'uid' => $order['uid'],
          'order_id' => $order['id'],
          'type' => 'refund',
          'amount' => $order['finance_amount'],
          'flow' => 'in',
          'memo' => $memo.',订单编号：'.$order['order_sn'],
          'create_time' => NOW_TIME
        );
        $update_finance = M('Finance')->add($data_finance);
      }else{
        return false;
      }
      return true;
    }else{
      return false;
    }
  }

  /**
   * 优惠券返还处理
   */
  public function refundCoupon($order_id){
    //优惠券暂无须处理，不返还
  }

  /**
   * 礼品卡返还处理
   */
  public function refundCard($order_id){
    $order = M('Order')->where('id='.$order_id)->find();
    $payment = M('Payment')->find($order['payment_id']);
    if($order['card_amount'] > 0 && $payment['is_use_card'] == 1){
      $map = array(
        'uid'    => $payment['uid'],
        'row_id' => $payment['id'],
        'type'   => 'card',
      );
      $card_amount = M('PaymentLog')->where($map)->sum('amount');
      $data_card  = array(
        'balance' => array('exp', 'balance + '.$order['card_amount']),
      );
      $card_id  = $payment['card_id'];
      if($card_amount + $order['card_amount'] == $payment['card_amount']){
        $data_card['use_time'] = '';
        $data_card['is_use'] = 0;
        //更新card_user表
        $map_user = array(
          'card_id' => array('IN', $card_id),
          'uid'     => $payment['uid'],
        );
        $data_user = array(
          'status' => 0,
          'use_time' => ''
        );
        $update_card_user = D('CardUser')->where($map_user)->save($data_user);
      }
      $update_card = M('Card')->where(array('id' => $card_id))->save($data_card);
      //写入记录表中
      $data = array(
        'uid'    => $payment['uid'],
        'row_id' => $payment['id'],
        'orderid'=> $order_id,
        'type'   => 'card',
        'amount' => $order['card_amount'],
        'create_time'=>NOW_TIME,
      );
      $payment_log = M('PaymentLog')->add($data);
      if($update_card && $payment_log){
        return true;
      }
      M('CardLog')->where('order_id ='.$order_id)->delete();
    }
    return false;

  }
  
  /**
   * 积分消费返还处理 积分赠送返还处理
   * @author buddha <[email address]>
   * @param  [type] $order_id 订单编号
   * @return [type]           [description]
   */
  public function refundScore($order_id){
    $model = M('ScoreLog');
    $info = M('Order')->where('id ='.$order_id)->field('order_sn,uid')->find();
    $order_sn = $info['order_sn'] ; 
    $uid = $info['uid'];
    //查询积分详情 平台赠送的
    $maps = array('type'=>'in','order_id'=>$order_id);
    $refund_info = $model->where($maps)->find();
    if ($refund_info && $refund_info['amount'] > 0) {
      //记录积分返还日志
      $cdata = array(
          'uid'      => $refund_info['uid'],
          'order_id' => $order_id,
          'order_sn' => $refund_info['order_sn'],
          'type'     => 'out',
          'amount'   => $refund_info['amount'],
          'memo'     => '购物退款返还赠送积分,订单编号：'.$order_sn,
          'create_time'=> NOW_TIME,
        );
      //开启事务
      $model->startTrans();
      $res = $model->add($cdata);
      //更新积分记录
      $re = $model->where($maps)->setField('status',1);
      // M('Member')->where(array('uid' => $refund_info['uid']))->setDec('score', $refund_info['amount']);
      if ($res && $re ) {
        $model->commit();
      }else{
        $model->rollback();
      }
    }
    if( ($score_amount = M('Order')->where('id ='.$order_id)->getField('score_amount')) > 0 ){
      //查询积分详情 消费的
 
      $scores = sprintf('%.2f' ,$score_amount*C('SCORE_EXCHANGE_NUMBER')) ;
      //记录积分返还日志
      $data = array(
          'uid'      => $uid,
          'order_id' => $order_id,
          'order_sn' => $order_sn,
          'type'     => 'in',
          'amount'   =>  $scores ,
          'memo'     => '购物订单退款返还积分,订单编号：'.$order_sn,
          'create_time'=> NOW_TIME,
        );
      //开启事务
      $model->startTrans();
      $res = $model->add($data);
      //更新用户记录表
      $result = M('Member')->where(array('uid' => $uid))->setInc('score', $scores );
      if ($res  && $result) {
        $model->commit();
      }else{
        $model->rollback();
      }

    }
    
  }
  /**
   * 单笔退款
   * @param int $refund_id 退款记录ID
   */
  public function dealRefund($refund_id){
    $return_data = array('status' => 0, 'info' => '退款出错了');
    $data = M('Refund')->find($refund_id);
    if($data['type'] == 1 ){
      $data['order'] = M('Order')->find($data['order_id']);
    }else{
      $data['order'] = M('Join_order')->find($data['order_id']);
    }
    if($data['refund_satus'] != 0){
      $return_data['info'] = '当前状态，不可进行退款操作';
      return $return_data;
    }
    //微信退款
    if($data['payment_type'] == 'wechatpay'){
      $pay_data = M('Payment')->getById($data['order']['payment_id']);
      $payment_return = $pay_data['payment_return'];
      $payment_return = json_decode($payment_return,true);
      $trade_type = $payment_return['trade_type'];
      $out_refund_no = date('YmdHis').mt_rand(100000, 999999);
      import('Org.Wechat.Pay.WxPayPubHelper', '', '.php');
      $payment_way = C('WECHATPAY');
        if($trade_type == "APP"){
            $payment_way = C('WECHATAPPPAY');
        }
      $refund_api = new \Refund_pub($trade_type);
      //众筹退款 主要是普通订单
      if($data['order']['payment_type'] == 'crowdfunding'){
        $crowd_return = $data['refund_return'];
        $crowd_return = json_decode($crowd_return,true);
        $map['payment_return'] = $data['refund_return'];
        $crowd_total = M('CrowdfundingUsers')->where($map)->getField('pay_money');
        $refund_api->setParameter("out_trade_no", $data['trade_no']); //
        $refund_api->setParameter('total_fee', $crowd_total*100);
        $refund_api->setParameter('refund_fee', $data['amount']*100);
      }else{
        $refund_api->setParameter("transaction_id", $pay_data['payment_sn']); //
        $refund_api->setParameter('total_fee', $pay_data['payment_amount']*100);
        //$refund_api->setParameter('refund_fee', $data['order']['total_amount']*100);
        $refund_api->setParameter('refund_fee', $data['amount']*100);//
      }
      $refund_api->setParameter('out_refund_no', $out_refund_no);
      $refund_api->setParameter('op_user_id', $payment_way['mch_id']);
      //退款判断 当日下单当日退款（使用未结算资金退款【默认】）|| 非当日下单退款（可用余额退款）
       //查询当前退款订单的支付时间 
      if($data['order']['payment_type'] == 'crowdfunding'){
          $map['order_id'] = $data['order']['id'];
          $crowd_order = M('CrowdfundingUsers')->where($map)->getField('payment_time',true);
          $pos = array_search(max($crowd_order), $crowd_order);
          $pay_time = $crowd_order[$pos];
          $payment_day = date('Ymd',$pay_time);
      }else{
          $payment_day = date('Ymd',$data['order']['payment_time']);
      }
      $now_day = date('Ymd',NOW_TIME);
      if ($now_day > $payment_day) {
         $refund_api->setParameter('refund_account', 'REFUND_SOURCE_RECHARGE_FUNDS' );
       }
      //退款结果
      $res = $refund_api->getResult();
      
      if($res['return_code'] == 'FAIL'){
        $refund_query_api = new \RefundQuery_pub($trade_type);
        $refund_query_api->setParameter("transaction_id", $pay_data['payment_sn']); //openid
        //退款状态
        $res_query = $refund_query_api->getResult();
        //判断 如果微信退款 余额、交易未结算资金不足，平台状态不变 BUDDHA 20170517
        if ($res['err_code'] == 'NOTENOUGH') {
          return array('status'=>0,'info'=>'商户可用退款余额不足');
        }else{
          return array('status'=>0,'info'=>$res_query['err_code_des']);
        }
      }
      if($res['return_code'] == 'SUCCESS' || $res['result_code'] == 'SUCCESS'){
        //添加判断 result_code 业务结果
        if ($res['result_code'] == 'FAIL') {
          return array('status'=>0,'info'=>$res['err_code_des']);
        }
        $save_data = array(
          'refund_no' => $out_refund_no,
          'refund_status' => 1,
          'refund_return' => json_encode($res),
        );
        M('Refund')->where(array('id' => $data['id']))->save($save_data);
        $order_id = M('Refund')->where('id='.$data['id'])->getField('order_id');
        //推广分销返现 取消
        //$this->series_refund($order_id);
        // 订单状态更改
        M('Order')->where('id='.$order_id)->setField('o_status',303);
        return array('status' => 1, 'info' => '退款发起成功');
      }else{
        $return_data['info'] = $res['return_msg'];
      }
    }
    return $return_data;
  }

}
