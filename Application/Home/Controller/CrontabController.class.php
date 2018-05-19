<?php
/**
 * 计划任务控制器
 *
 * 1.订单自动确认收货 AutoConfirmReceipt()
 * 2.自动下载远程头像 DownLoadAvatar()
 * 3.行为日志转存SQL actionLogRestore()
 * 
 * @version 2015012712
 * @author Max.Yu <max@jipu.com>
 */

namespace Home\Controller;

use Think\Controller;

class CrontabController extends Controller{

  public function __construct(){
    //关闭代码超时
    set_time_limit(3600);
    //读取站点配置
    $config = api('Config/lists');
    C($config);
  }
  
  /**
   * 测试定时任务
   */
  public function test(){
  	set_log('cron','定时任务'.date('H:i:s'),'cron');
  }

  public function doright(){
    $info = M('OrderItem')->field('id,price,quantity')->select();
    foreach($info as $k => $v){
      $data = array(
        'id' => $v['id'],
        'sub_total' => sprintf('%.2f', $v['price'] * $v['quantity']),
      );
      M('OrderItem')->save($data);
    }
  }
  /**
   * 执行计划任务
   * 每天23点30分，执行一次
   */
  public function init(){
    //订单自动确认收货
    $this->AutoConfirmReceipt();
    //自动下载远程头像
    $this->DownLoadAvatar();
    //行为日志转存sql
    $this->actionLogRestore();
    // 微信
    $this->weixintask(); 
    // 定时过期团购失败
    $this->joinfail();
    // 定时众筹订单失败 退款
    $this->Crowdfail();
    // 代理自动返现
    $this->distributeTast(); 
    // 分销店铺自动返现
    $this->SdpRecordTast();
    //订单执行变为订单成功
    $this->doOrderFinsh();
    // 已发货申请退款驳回 => 订单完成 
    $this->orderFinshTast();
    //记录日志 
    // F('crontab/'.date('Y_m_d_H_i_s'), NOW_TIME);
  }
// .-----------------------------------------------------------------------------------
// | Version: 2017
// |-----------------------------------------------------------------------------------
// | Author: Fourth Pu <540690666@qq.com|ppssone@live.cn>
// |-----------------------------------------------------------------------------------
// | 订单状态变为205 订单完成
// .-----------------------------------------------------------------------------------
  public function doOrderFinsh()
  {
    $map['o_status'] = 202;
    $map['complete_time'] = array('lt'  , (time() - C('MAX_REFUND_CASH_DAY')*3600*24) );
    $orders = M('Order')->where($map)->getField('id' ,true);
    if($orders){
      M('Order')->where(array('id' => array('in' , $orders)))->setField('o_status' , 205);
      $this->autoScoreTast($orders);
    }
  }

  /**
   * 微信，每小时执行一次
   */
  public function weixintask(){
    S('accessToken_client' ,null);
  }

  /**
   * 用户订单 已付款 已发货 申请退款驳回 ，订单状态修改成205 订单完成
   * @author buddha <[email address]>
   * @return [type] [description]
   */
  public function orderFinshTast(){
    $map['o_status'] = 405;
    $map['complete_time'] = array('lt'  , (time() - C('MAX_REFUND_CASH_DAY')*3600*24) );
    $orders = M('Order')->where($map)->getField('id' ,true);
    if($orders){
      M('Order')->where(array('id' => array('in' , $orders)))->setField('o_status' , 205);
      $this->autoScoreTast($orders);
    }
  }

  /**
   * 用户获得积分自动修改状态
   * @author buddha <[email address]>
   * @return [type] [description]
   */
  public function autoScoreTast($orders){
    $where = array(
        'status' => 0,
        'order_id' => array('IN',$orders)
      );
    $list = M('ScoreLog')->where($where)->select();
    if ($list) {
      foreach ($list as $key => $value) {
        M('Member')->where('uid='.$value['uid'])->setInc('score',$value['amount']);
      }
      M("ScoreLog")->where($where)->setField('status',1);
    }
  }

  /**
   * 代理自动返现
   * 
   */
  public function distributeTast(){
    $max_day = C('DIS_TIME');
    empty($max_day) && $max_day =15 ;
   $where = array(
      'f.status'      => 0 ,
      'f.flow'        => 'in',
      'f.type'        => 'union_order',
      'o.o_status'    => 202,
      'o.complete_time' => array(array('gt' ,0) ,array('lt' , (time() - C('MAX_REFUND_CASH_DAY')*3600*24 )) ,'and' ),
    );
    $data = M('Finance')->alias('f')->join('__ORDER__ o on o.id =f.order_id')->where($where)->field('f.id,f.uid,f.amount')->select();
    
    if($data){
      foreach($data as $k => $v){
        $count[$v['uid']] +=$v['amount'] ;
        $ids[] = $v['id'];
      }

      $map['id'] = array('in', $ids);
      M('Finance')->where($map)->setField('status',1);
    }
  }
  /**
   * 分销自动返现
   * @author buddha <[email address]>
   */
  public function SdpRecordTast(){
    $max_day = C('MAX_REFUND_CASH_DAY');
    empty($max_day) && $max_day =15 ;
    $where = array(
      'f.status'      => 0 ,
      'f.flow'        => 'in',
      'f.type'        => 'sdp_order',
      'o.o_status'    => array('in' ,array(202,205)),
      'o.complete_time' => array(array('gt' ,0) ,array('lt' , (time() - C('MAX_REFUND_CASH_DAY')*3600*24 )) ,'and' ),
    );

    $data = M('Finance')->alias('f')->join('__ORDER__ o on o.id =f.order_id')->where($where)->field('f.id,f.uid,f.amount')->select();
    if($data){
      foreach($data as $k => $v){
        $count[$v['uid']] +=$v['amount'] ;
        $ids[] = $v['id'];
      }
      $map['id'] = array('in', $ids);
      M('Finance')->where($map)->setField('status',1);
    }
  }
  /**
   * 自动更新参团失败列表
   * @author buddha <[email address]>
   * @return [type] [description]
   */
  public function joinfail(){
    $ids = M('Join')->where('etime < '.time())->getField('id' ,true);
    if($ids){
      $ids = implode(',' ,$ids) ;
      M('Join')->where('id in ('.$ids.')')->save(array('status' => 0));
    }
    $this->handlenopay();
    $this->handlepayed(); 
  }
  /**
   * 处理团购中未付款的订单
   * @author buddha <[email address]>
   * @return [type] [description]
   */
  public function handlenopay(){
    $where['status']  = 0 ;
    $where['join_id'] = 0 ;
    $where['ltime']   = array( 'lt' ,time()) ; 
    $result = M('Join_order')->where($where)->getField('id' ,true);
    if(!$result){
      return false;
    }
    $arr = implode(',' ,$result);
    M('Join_order')->where(' id in ('.$arr.')')->setField(array('status' =>2));
  }
  /**
   * 处理团购中付了款但未成功拼团的订单
   * @author buddha <[email address]>
   * @return [type] [description]
   */
  public function handlepayed(){
    $where['etime']  = array('lt' , time());
    $where['status'] = array('eq' , 0); 
    $result = M('Join_list')->where($where)->getField('id' ,true);
     if(!$result){
      return false;
    }
    $arr = implode(',' ,$result);
    M('Join_list')->where(' id in ('.$arr.')')->setField(array('status' =>2));
    M('Join_order')->where(' join_id in ('.$arr.') and paytime = 0')->setField(array('status' =>2));
    $need = M('Join_order')->where('join_id in ('.$arr.') and paytime > 0')->getField('id' ,true);
    $this->doit($need);
  }
  /**
   * 处理退款
   * @author buddha <[email address]>
   * @param  [type] $ids [description]
   * @return [type]      [description]
   */
  protected function doit($ids){
    if(empty($ids)){
      return false;
    }
    $where['a.id']       = $map['id']      = array('in', $ids);
    $where['a.paytime']  = $map['paytime'] = array('gt', 0);
    if( M('Join_order')->where($map)->setField('status' ,2 ) ){
      //处理库存和退款
      $order_lists = D('Join_order')->alias('a')->join('LEFT JOIN __JOIN_LIST__ b on b.id=a.join_id')->where($where)->field('a.*,b.activity_id')->select();
      if($order_lists){
        foreach($order_lists as $k => $v){
          //商品库存处理
          $mapp['item_id'] = $requst['item_id']     = $v['item_id'];
          $mapp['join_id'] = $requst['activity_id'] = $v['activity_id'];
          $map['spc_code'] = $v['item_code'];
          $demo =  M('Join_item_spec')->where($requst)->find();
          if($demo ){
            M('Join_item_spec')->where($requst)->setInc('quantity', $v['total_quantity']);
          }
          M('Join_item')->where($mapp)->setInc('stock', $v['total_quantity']);
          M('Item')->where('id ='.$v['item_id'])->setDec('buynum', $v['total_quantity']);
          //第三方退款处理
          if($v['total_amount'] > 0){
            $payment = M('Payment')->getById($v['payment_id']);
            
            $payment_return = json_decode($payment['payment_return'], true);
            $trade_no = $payment_return['trade_no'];
            if(empty($trade_no)){
              $trade_no = $payment_return['transaction_id'];
            }
            if(empty($trade_no)){
              $trade_no = $payment('payment_sn');
            }
            $refund_data = array(
              'uid'          => $v['uid'],
              'order_id'     => $v['id'],
              'payment_type' => $payment['payment_type'],
              'trade_no'     => $trade_no,
              'refund_type'  => 'item',
              'amount'       => $v['total_amount'],
              'detail'       => '团购失败退款',
              'type'         => 2,
            );
            D('Refund')->update($refund_data);
          }
        }
      }
     
    }
  }
  /**
   * 订单自动确认收货
   */
  public function AutoConfirmReceipt(){
    //限制天数
    //$max_day = intval(C('MAX_CONFIRM_RECEIPT_DAY'));
    $max_day = C('MAX_CONFIRM_RECEIPT_DAY');
    $max_day == 0 && $max_day = 7;
    if($max_day > 0){
      $map = array(
        'o_status' => 201, //待确认收货
        'shipping_time' => array('between', array(strtotime('2014-11-11 00:00:00'), time() - $max_day * 86400)), //限制时间内
      );
      $save_data = array(
        'o_status' => 202,
        'complete_time' => NOW_TIME
      );
      M('Order')->where($map)->save($save_data);
    }
  }
  /**
   * 自动更新众筹订单
   * @author buddha <[email address]>
   */
  public function Crowdfail(){
    //众筹时间
    $max_day = C('CROWDFUNDING_EXPIRE_TIME');
    if ($max_day > 0) {
      $map = array(
          'create_time'=> array('lt',time() - $max_day * 86400),//超过规定时间，自动失败
          'success'    => 0
        );
      $save_data = array(
          'success'     => 2,
          'expire_time' => time(),
        );
      M('CrowdfundingOrder')->where($map)->save($save_data);
      $this->CrowdOrderFail();
    }
  }
  /**
   * 众筹订单所在订单 状态更改
   * @author buddha <[email address]>
   */
  public function CrowdOrderFail(){
    //订单ID合集
    $order_ids = M('CrowdfundingOrder')->where('success=2')->getfield('order_id',true);
    foreach ($order_ids as $key => $id) {
      $save_data = array(
          'update_time' => time() 
        );
      //查询订单状态
      $order = M('Order')->where('id='.$id)->getField('o_status');
      if ($order == 0) {
        $save_data['o_status'] = 404;
      }
      M('Order')->where('id='.$id)->save($save_data);
      //更改商品库存
      $map['order_id'] = array('eq', $id);
      $order_items = M('OrderItem')->where($map)->field('item_id,quantity')->select();
      if($order_items){
        $item_model = M('Item');
        foreach($order_items as $item){
          $item_model->where('id='.$item['item_id'])->setInc('stock', $item['quantity']);
        }
      }
      //查询众筹用户表
      $crowd_info = M('CrowdfundingUsers')->where('order_id='.$id)->find();
      if ($crowd_info['payment_status'] == 1 && $crowd_info['pay_money'] > 0) {
        $this->crowd_refund($crowd_info,$id);
      }
    }
    //修改众筹用户订单状态
    M('CrowdfundingOrder')->where('success=2')->setField('success',3);//防止多次调用
  }
  /**
   * 众筹订单退款
   * @author buddha <[email address]>
   * @param  [type] $pay_money [description]
   * @return [type]            [description]
   */
  protected function crowd_refund($pay,$order_id){
    //退款订单
    $order = M('Order')->where('id='.$order_id)->find();
    $uid = $order['uid'];
      $couwd_map = array(
          'order_id' => $order_id,
          'payment_status' => array('in' , array( 1 , 3 ) ) ,
          'pay_money' => array('gt' , 0 ),
      );
     //查询订单每个众筹用户的付款记录
      $crowds = M('CrowdfundingUsers')->where($couwd_map)->field('id,payment_status,pay_money,payment_return,payment_type')->order('pay_money asc')->select();
      if($crowds){
          //待退款订单总金额
          $refund_amount = $order['total_amount'];
          //众筹付款记录循环写入退款表中
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
                      'uid' =>$uid,
                      'order_id' => $order_id,
                      'payment_type' => $cv['payment_type'],
                      'trade_no' => $trade_no,
                      'refund_type' => 'item',
                      'amount' => min($refund_amount , $cv['pay_money'] ),
                      'detail' => '众筹退款',
                      'refund_return' => $cv['payment_return'] ,
                  );
                  D('Refund')->update($refund_data);
                  //众筹退款： 修改订单支付类型 crowdfunding
                  M('Order')->where('id='.$order_id)->setField('payment_type','crowdfunding');
                  // 记录退款日志
                  $data = array(
                      'uid'    => $uid,
                      'row_id' => $cv['id'],
                      'orderid'=> $order_id,
                      'type'   => 'crowdfunding',
                      'amount' => sprintf('%.2f' , min($refund_amount , $cv['pay_money']) ),
                      'create_time'=>NOW_TIME,
                  );
                  $payment_log = M('PaymentLog')->add($data);
                  if($refund_amount - $cv['pay_money'] < 0 ){
                      M('CrowdfundingUsers')->where('id ='.$cv['id'])->setField('payment_status' , 3 );
                  }else{
                      M('CrowdfundingUsers')->where('id ='.$cv['id'])->setField('payment_status' , 2 );
                  }
                  $refund_amount -=  min($refund_amount , $cv['pay_money'] ) ;
                  if($refund_amount < 0.01)break;
              }
          }
      }
  }
  /**
   * 自动下载非本地头像
   */
  public function DownLoadAvatar(){
    $where = array(
      'avatar' => array('LIKE', 'http%'),
    );
    $list = M('Member')->field('uid, avatar,reg_time')->where($where)->select();
    foreach($list as $line){
      if(@fopen($line['avatar'], 'r')){
        $info = read_file($line['avatar']);
        $file = '/Uploads/Avatar/'.date('Y/m/d', $line['reg_time']).'/'.$line['uid'].'.png';
        mkdir('.'.dirname($file), 0777, true);
        if(strlen($info) > 100){
          if(file_put_contents('.'.$file, $info)){
            M('Member')->where('uid='.$line['uid'])->save(array('avatar' => $file));
          }
        }
      }
    }
  }  
  /**
   * 行为日志转存SQL
   * @author Justin <justin@jipu.com> 2015.5.13
   */
  public function actionLogRestore(){
    $table = 'jipu_action_log';
    $start = 0;
    $db = M('');
    //数据总数
    $result = $db->query("SELECT COUNT(*) AS count FROM `{$table}`");
    $count  = $result['0']['count'];
    if($count <= 50000){
      return;
    }
    while($count > $start){
      $this->_actionLog2sql($start);
      $start += 10000;
    }
    $sql = "TRUNCATE TABLE `{$table}`";
    $db->execute($sql);
    $db->execute("alter table `{$table}` AUTO_INCREMENT=".($count+1).";");
  }
  /**
   * 行为日志转存SQL
   * @author Justin <justin@jipu.com> 2015.5.13
   */
  private function _actionLog2sql($start = 0){
    $path = C('DATA_BACKUP_PATH');
    if(!is_dir($path)){
      mkdir($path, 0755, true);
    }
    //读取备份配置
    $config = array(
      'path'     => realpath($path) . DIRECTORY_SEPARATOR,
    );
    $table = 'jipu_action_log';
    $db = M('');
    $result = $db->query("SELECT * FROM `{$table}` LIMIT {$start}, 1000");
    foreach ($result as $row) {
        $row = array_map('addslashes', $row);
        $sql = "INSERT INTO `{$table}` VALUES ('" . str_replace(array("\r","\n"),array('\r','\n'),implode("', '", $row)) . "');\n";
        file_put_contents($config['path'].'./actionLog-'.date('Ymd-His', NOW_TIME).'.sql', $sql,FILE_APPEND);
    }
  }
  
}
