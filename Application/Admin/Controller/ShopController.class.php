<?php
/**
 * 店铺控制器
 * @author Justin
 */

namespace Admin\Controller;

class ShopController extends AdminController{

  /**
   * 店铺列表
   */
  function index($status = 2, $start_time = '', $end_time = ''){
    $where['status'] = array('lt' , 2);
    if(I('get.keywords')){
      $where['name|audit_data|intro'] = array('like', '%'.I('get.keywords').'%');
    }
    if(I('get.uid')){
      $where['uid'] = I('get.uid');
    }
    if(in_array($status, array(1, 0, -1))){
      $where['status'] = $status;
    } 
    //过滤掉没有选商品的店
    // $where[] = "item_ids != '' ";
    $this->setListOrder();
    $this->assign('time_search', $start_time || $end_time);
    $this->assign('status', $status);
    parent::index($where , 'status asc , id desc');
  }
  
  /**
   * 店铺列表统计处理
   */
  function _before_index_display(&$lists){
    $start_time = I('get.start_time');
    $end_time = I('get.end_time');
    //默认值
    empty($start_time) && $start_time = date('2010-01-01');
    empty($end_time) && $end_time = date('Y-m-d'); 
    //合法性判断
    if(strtotime($end_time) < strtotime($start_time)){
      return false;
    }
    foreach($lists as &$v){
      $res = D('Shop')->getStatInfo($v['uid'], $start_time, $end_time);
      if($res){
        $v['stat_amount'] = $res['amount'];
        $v['stat_order_count'] = $res['order_count']?: '';
      }
    }
  }

  /**
   * 店铺详情
   */
  public function detail($id = 0){
    $data = M('Shop')->find($id);
    $data['audit_data'] = unserialize($data['audit_data']);
    $data['stat_info'] = D('Shop')->getStatInfo($data['uid']);
    $this->data = $data;
    $this->meta_title = '店铺详细信息';
    $this->display();
  }
    /**
     * 添加分销banner
     */
    public function addBanner(){
        if(IS_POST){
            $slide = I('post.slide');
            $slide_url = I('post.slide_url');
            if(!$slide){
                $this->error('请先上传图片');
            }
            $data = array(
                'slide' => $slide,
                'slide_url' => $slide_url,
                'status' => 1,
            );
            $result = M("ShopBanner")->add($data);
            if($result){
                $this->success('添加成功', U('Shop/banner'));
            }else{
                $this->error('添加失败');
            }
        }
        $this->meta_title = '新增幻灯片';
        $this->display("editBanner");
    }

    /**
     * 分销商店铺banner
     */
    public function banner(){
        $map = array(
            'status' => 1
        );
        $lists = $this->lists(M('ShopBanner'), $map);
        $lists = int_to_string($lists);
        $this->assign('meta_title','幻灯片');
        $this->assign('lists',$lists);
        $this->display();
    }

    /**
     * 编辑幻灯片
     */
    public function editBanner(){
        if(IS_POST){
            $slide = I('post.slide');
            $slide_url = I('post.slide_url');
            $map = array(
                'id' => I('post.id')
            );
            $data = array(
                'slide' => $slide,
                'slide_url' => $slide_url,
                'status' => 1,
            );
            $result = M('ShopBanner')->where($map)->save($data);
            if($result){
                $this->success('修改成功', U('Shop/banner'));
            }else{
                $this->error('修改失败');
            }
        }
        $id = I('get.id');
        $lists = M("ShopBanner")->where("id='{$id}'")->find();
        $this->assign('id',$id);
        $this->assign('lists',$lists);
        $this->meta_title = '编辑幻灯片';
        $this->display();
    }

    public function setStatus(){
        $ids = I('request.ids');
        $status = I('request.status');
        if(empty($ids)){
            $this->error('请选择要操作的数据');
        }
       // dump($ids);die;
        $map['id'] = array('in',$ids);
        $res = M('Shop')->where($map)->setField('status',$status);
        $res !== false ? $this->success('操作成功！') : $this->error('操作失败！');
        parent::setStatus("ShopBanner");
    }
  
}
