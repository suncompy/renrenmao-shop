<?php
/**
 * 商品评价管理控制器
 * @version 2015051010 
 * @author Justin <justin@jipu.com>
 */

namespace Admin\Controller;

class ItemCommentController extends AdminController{

  public function index(){
      $keywords = I('keywords');
      if($keywords){
          $where['u.name|m.nickname'] = array('like', '%'.$keywords.'%');
      }
      $start_time = I('get.start_time', '');
      $end_time = I('get.end_time', '');
      $start_time = !empty($start_time) ? strtotime($start_time) : '';
      $end_time = !empty($end_time) ? strtotime($end_time) + 24 * 3600 : '';
      if(!empty($start_time)){
          $where[] = "I.`create_time` > $start_time";
      }
      if(!empty($end_time)){
          $where[] = "I.`create_time` < $end_time";
      }
      $model = M('ItemComment')->alias('I')->join('LEFT JOIN __MEMBER__ m ON m.uid = I.uid')->join('LEFT JOIN  __ITEM__ u ON u.id = I.item_id');
      $store_id = $_SESSION['jipushop_store']['seller_user']['store_id'];
      $where['I.store_id'] = $store_id;
      $where['I.pid'] = 0;
     // $where['I.status'] = 1;
      $order ='I.id desc';

      // ($item_id = I('get.item_id')) && $where['I.item_id'] = $item_id;
      //   dump($item_id);
      $field = 'I.*,u.id,m.uid'  ;
      $data = $this->lists($model,$where,$order,$field,10,array());
      $this->lists = $data;
      $this->meta_title = $this->meta[CONTROLLER_NAME].'列表';
      $this->display();

    /*$where['pid'] = 0;
    ($item_id = I('get.item_id')) && $where['item_id'] = $item_id;
    parent::index($where);*/
  }
  
  /**
   * 商品评价回复
   * @author Justin <9801836@qq.com>
   */
  function reply(){
    $id = I('id');
    if($id){
      $this->data = M('ItemComment')->getById($id);
      //回复内容
      $this->data_reply = get_item_comment_reply($id);
      $this->display();
    } 
  }
}
