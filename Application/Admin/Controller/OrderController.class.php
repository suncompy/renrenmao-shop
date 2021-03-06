<?php
/**
 * 后台订单控制器
 * @author Max.Yu <max@jipu.com>
 */

namespace Admin\Controller;

class OrderController extends AdminController{

  private $payment_type;
  private $o_status_text;
  private $is_packed_text = array(
    0 => '<span class="text-warning">未打包</span>',
    1 => '<span class="text-success">已打包</span>',
  );

  protected function _initialize(){
    parent::_initialize();
    //支付方式
    $this->payment_type = get_payment_type_text();
    $this->o_status_text = get_o_status_text();
  }

  /**
   * 订单列表
   * @author Max.Yu <max@jipu.com>
   */
  public function index(){
    // 记录当前列表页的Cookie
    Cookie('__forward__', $_SERVER['REQUEST_URI']);
    //订单列表
    $o_list = A('Order', 'Event')->getPageList();
    $map = array(
      'payment_type' => $this->payment_type,
      'o_status' => $this->o_status_text,
      'is_packed' => $this->is_packed_text,
    );
    int_to_string($o_list, $map);
    $list = A('Order', 'Event')->orderFormat($o_list);
    $this->list = $list;

    $this->assign('payment_type' , $this->payment_type) ;
    //供应商列表
    $lists_supplier = M('supplier')->where('status=1')->field('id,supplier_id,name,link_name')->select();
    $this->lists_supplier = $lists_supplier;
    // 增加排序
    $this->setListOrder();
    $this->meta_title = '订单列表';
    $this->display();
  }

  /**
   * 编辑订单
   * @author Max.Yu <max@jipu.com>
   */
  public function edit($id = 0){
    if(IS_POST){
      $Order = D('Order');
      $data = $Order->create();
      if($data){
        if($Order->save() !== false){
          //记录行为
          //action_log('update_order', '$Order', $data['id'], UID);
          $this->success('更新成功', Cookie('__forward__'));
        }else{
          $this->error('更新失败');
        }
      }else{
        $this->error($Order->getError());
      }
    }else{
      $info = array();
      // 获取数据
      $info = M('Order')->field(true)->find($id);
      // 检测订单是否已取消
      if(!(is_array($info) || -1 == $info['status'])){
        $this->error = '订单已取消，不能编辑！';
        return false;
      }

      if(false === $info){
        $this->error('获取订单信息错误');
      }
      $this->assign('info', $info);
      $this->meta_title = '编辑订单信息';
      $this->display();
    }
  }

  /**
   * 更新部分字段前置方法：如果更改打包状态，验证订单状态
   * @author justin <justin@jipu.com>
   * @version 2015071010
   */
  function _before_updateField(){
    $is_packed = I('get.is_packed', null);
    if(isset($is_packed)){
      $order=M('Order')->where(array('id'=>I('get.id')))->find();
      //$o_status = M('Order')->getFieldById(I('get.id'), 'o_status');
      //$o_status != 200 && $this->error('当前订单状态不允许更新打包状态！');
      !in_array($order['o_status'] ,array(200 ,405) )  && $this->error('当前订单状态不允许更新打包状态！');
      $order['is_packed'] == 1 && $this->error('商品已打包！');
    }
  }

  /**
   * 更新部分数据
   * @author Max.Yu <max@jipu.com>
   */
  public function updateField(){
    $data = I('request.');
    if(!$data['is_packed']){
      (is_numeric($data['value'])) ? :$this->error('请输入数字！');
      ($data['value']< 0) ?$this->error('不能输入负数值！') : null;
    }
    
    $res = D('Order')->updateField();
    if(!$res){
      $this->error(D('Order')->getError());
    }else{
      S('weixin_prepay_id_item_order_'.I('id'), null);
      //记录行为
      action_log('update_order', 'order', I('id'), UID);
      $this->success('修改成功', Cookie('__forward__'));
    }
  }

  /**
   * 查看订单
   * @author Max.Yu <max@jipu.com>
   */
  public function view($id = 0){
    //获取订单信息
    $info = M('Order')->find($id);
    if(false === $info){
      $this->error('获取订单信息错误');
    }
    // 计算优惠金额
    $info['discount_amount'] = sprintf("%.2f", ($info['total_price'] + $info['delivery_fee'] - $info['total_amount'] - $info['finance_amount'] - $info['score_amount'] - $info['coupon_amount'] - $info['card_amount'] - $info['manjian_amount']));
    // 获取支付方式
    $info['payment_type_text'] = get_payment_type_text(M('Payment')->getFieldById(M('Order')->getFieldById($info['id'], 'payment_id'), 'payment_type'));
    //订单商品信息
    $info['itemList'] = get_order_item($id);
    $info['itemCount'] = count_order_item($id);
    //付款信息
    $info['payment'] = M('Payment')->getById($info['payment_id']);
    //收货人信息
    $info['ship'] = M('OrderShip')->getByPaymentId($info['payment_id']);
    //物流信息
    $info['delivery'] = M('Ship')->where('status = 1')->getByOrderId($info['id']);
    //物流模板信息 buddha 金海
    $delivery = unserialize($info['payment']['delivery_data']);
    $info['delivery_id'] = $delivery[$info['supplier_ids']]['delivery_id'];
    ($info['delivery_id'] > 0) && $info['delivery_tpl'] = M('DeliveryTpl')->getFieldById($info['delivery_id'], 'company');
    //分销店铺信息
    $info['sdp_shop'] = $info['sdp_uid'] > 0 ? M('Shop')->getByUid($info['sdp_uid']) : '';
    if($info['o_status'] == 0 || $info['o_status'] == -1 || $info['o_status'] == 404){
      if ($info['o_status'] != 404) {
        $info['memo'] = handlememo($info['memo']);
      } 
    }else{
      if(!empty($info['memo'])){
        $name = $info['supplier_ids'] == 0 ? C('WEB_SITE_TITLE') : get_supplier_text($info['supplier_ids']);
        $temp = $info['memo'];unset($info['memo']);
        $info['memo'][$name] = $temp;
      } 
    }
    //退款信息
    if ($info['o_status'] <=304 && $info['o_status'] >=300 ) {
      $info['refund'] = M('refund')->getByOrderId($info['id']);
    }
    //退货物流
    if ($info['o_status'] == 304 || $info['o_status'] == 303) {
       $info['return_delivery'] = json_decode($info['reship_info'],true);
     } 
    //查询订单所属供应商
    if (strpos($info['supplier_ids'],',') != false) {
      $supplier_arr = str2arr($info['supplier_ids']);
      foreach ($supplier_arr as $key => $value) {
        $arr[] = get_supplier_text($value);
      }
      $info['supplier_name'] =arr2str($arr);
    }else{
      $info['supplier_name'] = get_supplier_text($info['supplier_ids']);
    }
    $this->assign('info', $info);
    $this->meta_title = '订单详情';
    $this->display();
   
  }

  /**
   * 回收站
   * @author Max.Yu <max@jipu.com>
   */
  public function recycle(){
    $where['status'] = -1;
    $list = $this->lists('Order', $where, 'id desc', null);
    int_to_string($list, array('o_status' => $this->o_status_text));
    foreach($list as &$value){
      $value['payment_type'] = M('Payment')->getFieldById($value['payment_id'], 'payment_type');
    }
    
    //记录当前列表页的Cookie
    Cookie('__forward__', $_SERVER['REQUEST_URI']);
    $this->assign('list', $list);
    $this->meta_title = '订单回收站';
    $this->display();
  }

  /**
   * 删除订单（物理删除）
   * @author Max.Yu <max@jipu.com>
   */
  public function del(){
    $ids = I('request.ids');

    if(empty($ids)){
      $this->error('请选择要操作的数据!');
    }
    $map['id'] = array('in', $ids);
    if(M('Order')->where($map)->delete()){
      //删除订单项目信息
      $where['order_id'] = array('in', $ids);
      //删除订单项目表记录
      M('OrderItem')->where($where)->delete();
      //删除物流信息表记录
      M('Ship')->where($where)->delete();
      //删除支付记录表
      M('Payment')->where($where)->delete();
      //删除现金流水表
      M('Finance')->where($where)->where(array('type' => 'website'))->delete();
      //记录行为
      action_log('del_order_recycle', 'order', $ids, UID);
      $this->success('删除成功！');
    }else{
      $this->error('删除失败！');
    }
  }

  /**
   * 取消订单
   * @author Max.Yu <max@jipu.com>
   */
  public function cancel(){
    $ids = I('request.ids');
    if(empty($ids)){
      $this->error('请选择要操作的数据!');
    }

    // 初始化退款事件
    $cancel_return = A('Refund', 'Event')->doit($ids, 'cancel');
    if($cancel_return['code'] == 200){
      $this->success($cancel_return['info']);
    }else{
      $this->error($cancel_return['info']);
    }
  }

  /**
   * 处理退款
   * @author Max.Yu <max@jipu.com>
   */
  public function refund(){
    $order_id = I('order_id');
    $order = M('Order')->find($order_id);
    if(empty($order)){
      die('订单不存在');
    }
    //申请退款，已退货
    if(in_array($order['o_status'], array(300, 301, 302))){
      if(IS_POST){
        $new_status = I('post.to_status');
        if($new_status == 301){
          M('Order')->save(array('id' => $order_id, 'o_status' => $new_status));
          $this->success('退款申请已审核');
        }elseif($new_status == 303){
          // 初始化退款事件
          $refund_return = A('Refund', 'Event')->doit(array($order_id), 'refund');
          if($refund_return['code'] == 200){
            $this->success($refund_return['info']);
          }else{
            $this->error($refund_return['info']);
          }
        }elseif($new_status == -1){
          $to_status = 0;
          //已支付
          $order['payment_time'] > 0 && $to_status = 200;
          //已发货
          $order['shipping_time'] > 0 && $to_status = 201;
          //已完成
          $order['complete_time'] > 0 && $to_status = 202;
          M('Order')->save(array('id' => $order_id, 'o_status' => $new_status));
          $this->success('已取消退款');
        //驳回处理
        }elseif($new_status == 405){
            $reason = I('post.reason');
            $rst = M('Order')->save(array('id' => $order_id, 'o_status' => $new_status));
            if(!empty($reason) && $rst){
                $msgData['uid'] = UID;
                $msgData['to_uid'] = $order['uid'];
                $msgData['title'] = '退单驳回';
                $msgData['content'] = $reason;
                $msgData['status'] = 1;
                $msgData['create_time'] = NOW_TIME;
                $messagedata = array('refuse_message' => $reason); 
                M('Order')->where('id='.$order_id)->setField($messagedata);
                M('Message')->add($msgData);
                action_log('update_'.CONTROLLER_NAME, CONTROLLER_NAME, $order_id, UID);
            }
            ($rst !== false) && $this->success('退款已驳回');
        }else{
          $this->error('请选择操作');
        }
      }
      if($order['reship_info']){
        $order['reship_info_text'] = json_decode($order['reship_info'], true);
      }
      $this->order = $order;
      $this->display();
    }else{
      die('当前状态不允许操作');
    }
  }
  
  /**
   * 打印请货单（商品清单）
   * @author Max.Yu <max@jipu.com>
   */
  public function printItem($id = 0){
    R('Order/view', array('id' => $id));
  }
  /**
   * 灵通打单
   */
  public function bestmart($ids = array()){
    $headArr = '业务单号;收件人姓名;收件人手机;收件省;收件市;收件区/县;收件人地址;品名;数量;备注';
    $filename = "灵通打单".date('Ymd-H');
    $data = D('Order')->getBestMartData($ids);
    $data = array_merge(array(explode(';', $headArr)), $data);
    /* 执行下载 */
    header('Content-type: text/csv;');
    header('Cache-Control:must-revalidate,post-check=0,pre-check=0');   
    header('Expires:0');   
    header('Pragma:public');
    if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) { //for IE
      header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '.csv"');
    }else{
      header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    }
    foreach($data as $v){
      foreach($v as &$vv){
        $vv = mb_convert_encoding($vv, 'gbk', 'utf-8');
      }
      echo implode(',', $v) ."\r\n";
    }
    exit;
  }
  /**
   * excel打单
   */
  public function excel_port(){
    //订单列表
    $where = session('where');
    $info = D('Order')->getExcelData($where , $this->payment_type);
    if($info === 1){
      $this->error('数量超过1000条，请根据日期来减少搜索结果');
    }

    $data[0] = array('订单类型','订单编号','商品编号','商品类别','商品名称','供应商','商品单价','下单数量','订单总额','支付金额','订单状态','下单时间','下单用户','收货人姓名','收货人联系方式','收货地址','支付状态','支付方式','现金支付','其他支付','支付时间','支付单号','分销店','分销返现'
    );
    $data  = array_merge($data, $info) ;
    $title = date('ymd',time()).'订单';
    $filename = date('ymd',time());
    $width = array( array('B', 50 ), array('C', 50) ,array('M', 50) ,array('O',50) ,array('V',50) ,array('P',50)  );
    D('Excel')->export($data , $filename ,$title , $width);
  }

  
}
