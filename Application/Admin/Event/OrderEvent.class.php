<?php
/**
 * 订单数据处理事件接口
 * @author Max.Yu <max@jipu.com>
 */

namespace Admin\Event;

class OrderEvent extends \Think\Controller{

// .-----------------------------------------------------------------------------------
// | Version: 2017
// |-----------------------------------------------------------------------------------
// | Author: Fourth Pu <540690666@qq.com|ppssone@live.cn>
// |-----------------------------------------------------------------------------------
// | 组成搜索条件
// .-----------------------------------------------------------------------------------
  public function searchFormat()
  {
    $where = array(
      // 'o.groupby' => 'payment_id',
      'o.status' => array('egt', 1)
    );
    //订单状态过滤
    $o_status = I('get.o_status', -2);
    if($o_status > -2){
      $where['o.o_status'] = array('eq', $o_status);
      
    }elseif($o_status == -3){
      $where['o.payment_time'] = array('gt', 0);
    }
    $this->o_status = $o_status;
    
    //用户ID过滤
    $uid = I('get.uid', 0);
    if($uid > 0){
      $where['o.uid'] = $uid;
    }
    //分销过滤
    $sdp_uid = I('get.sdp_uid', 0);
    if(1 == I('is_sdp', 0)){
      $where['o.sdp_uid'] = array('gt', 0);
    }
    if($sdp_uid > 0){
      $where['o.sdp_uid'] = $sdp_uid;
      $this->sdp_uid = $sdp_uid;
    }
    //供应商过滤
    if(IS_SUPPLIER){
      $where['o.supplier_ids'] = UID;
      $where['o.payment_time'] = array('gt', 0);
    }else{
      $supplier_id = I('get.supplier_id',0);
      if(I('supplier_id') != '')$where['o.supplier_ids'] = $supplier_id;
      if(I('supplier_id') != '')$this->supplier_id = $supplier_id; 
    }
    //时间过滤
    $time_type = I('get.time_type', null);
    $start_time = I('get.start_time', '');
    $end_time = I('get.end_time', '');
    if(isset($time_type) && in_array($time_type, array('create_time', 'payment_time'))){
      $start_time = !empty($start_time) ? strtotime($start_time) : '';
      $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
      if(!empty($start_time)){
        $where[] = " o.$time_type > $start_time";
      }
      if(!empty($end_time)){
        $where[] = " o.$time_type < $end_time";
      }
      if($time_type == 'payment_time' && ($start_time + $end_time) > 0){
        $where['o.payment_time'] = array('gt', 0);
      }
      $this->time_type = $time_type;
    }
    //字符串过滤 按订单号 或者支付单号 或者 收货人
    $keywords = I('get.keywords', '', trim);
    if(!empty($keywords)){
      $where['o.order_sn|p.payment_sn|r.ship_uname|r.ship_mobile'] = array('like', '%'.$keywords.'%');
      $this->keywords = $keywords;
    }
    if(I('order_type' , 'int' ,0) > 0){
         $where['o.order_type'] = I('order_type' );
         $this->order_type  = I('order_type' );
      }
    // //支付方式
    $pay_type = I('pay_type');
    if($pay_type && $pay_type !='支付类型'){
      $this->pay_type = $where['p.payment_type'] = $pay_type;
    }
    session('where' , $where);
    return $where;
  }
  /**
   * 获取分页列表
   */
  public function getPageList($where = array() ){
    
    //按条件查询结果并分页
    $list = array();
    C('LIST_ROWS', 10);
    // //收货人名字或者手机搜索
    // $ship = I('get.ship');
    // if($ship){
    //   $ship_where['ship_uname|ship_mobile'] = array('like', '%'.$ship.'%');
    //   $ship_payment_ids = M('OrderShip')->where($ship_where)->getField('payment_id', true);
    //   $order_sn = M('Order')->where(array('order_sn'=>array('LIKE','%'.$ship.'%')))->getField('order_sn',true);
    //     if(empty($order_sn) && empty($ship_payment_ids)){
    //         return false;
    //     }
    //    $where['o.payment_id'] = array('IN', array_unique($ship_payment_ids));
     //   $where['order_sn'] = array('IN',array_unique($order_sn));
   //    $where['_string'] = " (payment_id in ('.$ship_payment_ids.') OR (order_sn in ('.$order_sn.')))";
     //  $where['_logic'] = 'OR';
    // }
    $where = array_merge($where , $this->searchFormat()) ;
    $map['order_type'] = $where['o.order_type'] ;
    $order = (-3 == $o_status) ? 'o.payment_time desc, o.id desc' : 'o.id desc';
    
    $model = M('Order')->alias('o')->join ( '__PAYMENT__ p ON p.id=o.payment_id')->join('__ORDER_SHIP__ r on p.id = r.payment_id ');
    $payment_list = A('Home/Page', 'Event')->lists($model, $where, $order, 10, array(), 'o.payment_id');
    if($payment_list){
      $payment_ids = array_column($payment_list, 'payment_id');
      $map = array(
        'payment_id' => array('IN', array_unique($payment_ids)),
        'status' => array('egt', 1)
      );
      //供应商过滤
      if(IS_SUPPLIER){
        $map['supplier_ids'] = UID;
      }else{
        if (I('supplier_id') != '')$map['supplier_ids'] = I('supplier_id',0);
      }
      $list = M('Order')->field('*')->where($map)->order('field(payment_id,'.implode(',', $payment_ids).')')->select();
    }
    return $list;
  }

  /**
   * 订单数据按支付单号分组
   */
  public function orderFormat($order_list){
    //空数据直接返回
    if(empty($order_list)){
      return array();
    }
    $list = array();
    foreach($order_list as $order){
      $payment_id = $order['payment_id'];
      if(!isset($list[$payment_id])){
        $list[$payment_id] = array(
          'num' => 0,
          'is_sdp' => $order['sdp_uid'] != 0,
          'invoice_need' => $order['invoice_need'],
          'payment' => M('Payment')->field('uid, payment_sn, payment_status, payment_type, payment_amount, finance_amount, create_time')->getById($payment_id),
          'ship' => M('OrderShip')->getByPaymentId($payment_id),
        );
      }
      $order['item_ids_arr'] = explode(',', $order['item_ids']);
      //查询所属供应商
      $order['supplier_name'] = get_supplier_text($order['supplier_ids']);
      $list[$payment_id]['order'][$order['id']] = $order;
      $list[$payment_id]['num'] = count($list[$payment_id]['order']);
    }
    //dump($list);
    return $list;
  }

}
