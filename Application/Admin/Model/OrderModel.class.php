<?php
/**
 * 订单模型
 * @author Max.Yu <max@jipu.com>
 */

namespace Admin\Model;

class OrderModel extends AdminModel {

  /**
   * 自动验证规则
   * @var array
   * @author Max.Yu <max@jipu.com>
   */
  protected $_validate = array(
  );

  /**
   * 自动完成规则
   * @var array
   * @author Max.Yu <max@jipu.com>
   */
  protected $_auto = array(
  );

  /**
   * 获取订单列表
   * @param $order_sn
   */
  public function lists($map, $order = '`id` DESC', $field = true, $limit = '10'){
    $result = $this->field($field)->where($map)->order($order)->limit($limit)->select();
    return $result;
  }

  /**
   * 根据order_sn 获取 order_id
   * @param $order_sn
   */
  public function getIdBySn($order_sn = null){
    if(empty($order_sn)){
      return false;
    }

    $order_id = $this->getFieldByOrderSn($order_sn, 'id');

    if($order_id){
      return $order_id;
    }else{
      return false;
    }
  }
  
  /**
   * 获取灵通打单所需数据
   * @param array $ids 描述信息
   */
  public function getBestMartData($ids = array()){
    if(empty($ids)){
      return false;
    }
    $data = array();
    $orders = $this->field('id, order_sn, total_quantity, payment_id,create_time')->where(array('id' => array('IN', $ids)))->select();
    foreach($orders as $v){
      $ship  = M('OrderShip')->getByPaymentId($v['payment_id']);
      $area  = explode(' ', $ship['ship_area']);
      $goods = M('Order_item')->where('order_id='.$v['id'])->getField('name', true);
      $goodsname = implode(',' , $goods);
      $data[] = array(
        $v['order_sn'], $ship['ship_uname'], $ship['ship_mobile'],
        $area[0], $area[1], $area[2], $ship['ship_address'], $goodsname, $v['total_quantity'], ($ship['ship_phone'] ?: '') ,date('Ymd H:i:s' , $v['create_time'])
      );
    }
    return $data;
  }
  // .-----------------------------------------------------------------------------------
  // | Version: 2017
  // |-----------------------------------------------------------------------------------
  // | Author: Fourth Pu <540690666@qq.com|ppssone@live.cn>
  // |-----------------------------------------------------------------------------------
  // | 按条件搜索出数据 超出1000条 将不能搜索出结果
  // .-----------------------------------------------------------------------------------
  
  function getExcelData($where ,$show_payment){
    $order = (-3 == $o_status) ? 'o.payment_time desc, o.id desc' : 'o.id desc';
    $model = M('Order')->alias('o')->join ( ' LEFT JOIN __PAYMENT__ p ON p.id=o.payment_id')->join('LEFT JOIN __ORDER_SHIP__ r on p.id = r.payment_id ')->join('LEFT JOIN __ORDER_ITEM__ oi on o.id = oi.order_id');
    $res  = $model->where($where)->order($order)->field('o.id , o.order_type , o.order_sn ,oi.name as good_name, oi.number , oi.item_id , oi.supplier_id,oi.price , oi.quantity ,o.total_price,o.total_amount , o.o_status ,o.create_time, o.uid,r.ship_uname,r.ship_mobile,r.ship_area,r.ship_address,p.payment_status,p.payment_type,p.finance_amount,p.coupon_amount,p.card_amount,p.score_amount,p.create_time as ptime,p.payment_sn,oi.sdp_code,p.payment_amount')->limit(1001)->select();

    if(count($res) > 1000){
      return 1 ;
    }
    foreach ($res as $k => $v) {
      $data[] = array(
        0 =>  $this->checkOrderType($v['id']) == 'crowdfunding' ? '众筹' :($v['order_type'] == 2 ?'团购订单' : '普通订单' ), //订单类型
        1 => $v['order_sn'].' ' ? : '',//订单编号
        2 => $v['number'].' '  ? : '',//商品编号
        3 => $this->getCate($v['item_id']),//商品类别
        4 => $v['good_name'], //商品名称
        5 => get_supplier_text($v['supplier_id']), //供应商
        6 => $v['price'] , //商品单价
        7 => $v['quantity'] , // 商品数量,
        8 => sprintf('%.2f' , $v['price'] * $v['quantity']), // 订单总额
        9 => $this->getAmount($v['order_sn']),//支付金额
        10 => $this->get_o_status_text($v['o_status']),//订单状态
        11 => date('Y-m-d H:i:s' , $v['create_time']),//下单时间
        12 => get_username($v['uid']) , //下单用户
        13 => $v['ship_uname'],//收货人姓名
        14 => $v['ship_mobile'].' ',//收货人联系方式
        15 => $v['ship_area'].$v['ship_address'],//收货地址
        16 => $v['payment_status'] == 0 ? '待支付' : '已支付',//支付状态
        17 => $show_payment[$v['payment_type']],//支付方式
        18 => $v['payment_amount'] ,//现金支付
        19 => $v['finance_amount'] + $v['coupon_amount']+ $v['card_amount']+ $v['score_amount'],//其他支付
        20 => date('Y-m-d H:i:s' , $v['ptime']),//支付时间
        21 => $v['payment_sn'] .' ',//支付单号
        22 => $this->getshop($v['sdp_code']),//分销店
        23 => $this->sdpfinance($v['id'] , $v['item_id']),//分销返现
      );
    }
    return $data ;
  }
  /**
   * 获取订单类型
   */
  public function checkOrderType($order_id){
    if(!$order_id){
      return false;
    }
    $map_order['order_id'] = $order_id;
    //直接使用detail方法都会输出数据
    $check = M('CrowdfundingOrder')->where($map_order)->count();
    return $check ? 'crowdfunding' : 'item';
  }

  public function sdpfinance($order_id , $item_id){
    return M('SdpRecord')->where('order_id ='.$order_id.' and item_id='.$item_id)->getField('cashback_amount') ? : '0.00';
  }
  //分销店
  public function getshop($secret){
    if(!$secret) return '';
    return M('Shop')->where('secret = "'.$secret.'"')->getField('name');
  }

  //支付金额
  public function getAmount($order_sn){
    return M('Order')->where('order_sn= "'.$order_sn.'"')->sum('total_price');
  }
  //商品分类
  public function getCate($item_id){
    return M('Item')->where('id = '.$item_id)->getField('category');
  }
  public function get_o_status_text($o_status=''){
    $o_status_text = array(
      0 => '待付款',
      -1 => '交易取消',
      200 => '已付款',
      201 => '已发货，待买家收货',
      202 => '交易成功',
      205 => '交易完成',
      300 => '申请退款',
      301 => '待买家退货',
      302 => '已退货',
      303 => '已退款，交易关闭',
      304 => '退款处理中',
      404 => '系统取消订单',
      405 => '>退款驳回',
    );
    return (mb_strlen($o_status, 'UTF8') > 0) ? $o_status_text[$o_status] : $o_status_text;
  }
}