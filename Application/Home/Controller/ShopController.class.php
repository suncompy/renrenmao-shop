<?php
/**
 * 店铺控制器
 * @version 2015080610
 * @author Max.Yu <max@jipu.com>
 */

namespace Home\Controller;

class ShopController extends HomeController{

  protected function _initialize(){
    //记录当前页URL地址Cookie，点击我的登录完成后跳转至个人中心
    Cookie('__forward__', $_SERVER['REQUEST_URI']);
    parent::_initialize();
    //跳过验证登录
    $jump_loginlist = array('detail');
    /*if(!in_array(ACTION_NAME, $jump_loginlist)){
      parent::login();
    }*/
    $this->assign('user', get_user_info(UID));
    $this->assign('member', get_member_info(UID));
  }

  /**
   * 店铺详情
   * @author Max.Yu <max@jipu.com>
   */
  public function detail($uid = 0){
    $uid = I('request.uid');
    //店铺信息
    $data = M('Shop')->where(array('uid' => $uid, 'status' => 1))->find();
    //用户信息
    $user_status = M('Member')->where("uid = $uid")->getField('status');

    if($data && $user_status == 1 ){

      //记录分销店铺sdp_uid到SESSION
      session('sdp_uid', $data['uid']);
      //商品列表
      if($data['item_ids']){
         $where = array(
             'status' => 1,
             'id' => array('IN', $data['item_ids']),
             'sdp' => array('gt', 0)
         );
         $order = "INSTR('{$data['item_ids']}', id)";
         $lists = $this->lists('Item', $where, $order, '*', 40);
         foreach($lists as &$value){
             if($value['thumb']){
                 $value['cover_path'] = get_cover($value['thumb'], 'path');
             }
         } 
      }
      //判断浏览器来源
      if (is_mobile()) {
        if($data['wap_pic'])$banner = $data['wap_pic'];
      }else{
        if($data['pc_pic'])$banner = $data['pc_pic'];
      }
      $this->banner = $banner;
      if($data['item_ids']){
          $item_ids = explode(',', $data['item_ids']);
          foreach ($item_ids as $k => $v) {
              $obj = M('Item')->where("id = $v")->find();
              if ($obj['status'] == 0 || $obj['sdp'] == 0) {
                  unset($item_ids[$k]);
              }
          }
      }
        //查询商品数量
      $item_count =  ($data['item_ids'] !==false) ? count($item_ids) : 0;
      $this->item_count = $item_count;
      //查询商户是否开通推广联盟
      $status = M('User')->where('id='.$uid)->getfield('form_union_status');
      if ($status > 0) {
        $map['uid'] = array('eq',$uid);
        $map['status'] = array('gt',0);
        $data_union = M('Union')->where($map)->find();
      }else{
        $data_union = array();
      }


        $shopper_code = M('Shop')->where(array('uid' => $uid, 'status' => 1))->getField('shopper_code');
      $str = $_SERVER['PATH_INFO'];
      $s_uid = explode('/',$str)[3];
      $this->data_union = $data_union;
      $this->shopper_code = $shopper_code;
      $this->lists = $lists;
      $this->s_uid = $s_uid;
      $this->uid = $uid;
      $this->uids = UID;
      $this->data = $data;
      $this->meta_title = $data['name'] ? $data['name'] : '无名店铺';
      //分享图片处理 图片缩略图
      $img = get_image_thumb($data['logo'],200,200);
      $share = array(
        'title' => $this->meta_title,
        'desc' => $data['intro'] ? $data['intro'] : C('WEB_SITE_DESCRIPTION'),
        'img_url' => $data['logo'] ? SITE_URL.$img : null,
        'link' => SITE_URL.U('Shop/detail', array('uid' => $uid))
      );
      $this->meta_share = $share;

    }else{
      $this->error('不存在的店铺！',U('/'));
    } 
    $this->display(IS_AJAX ? 'Item/itemList' : null);
  }

  /**
   * 推广联盟二维码
   * @author buddha <[email address]>
   * @return [type] [description]
   */
  public function sdpCode(){
    $uid = I('get.uid') ?: UID;
    $share_param = I('request.share_param');
    //查询商户是否开通推广联盟
    $status = M('User')->where('id='.$uid)->getfield('form_union_status');
    $this->invite_code = union_code($uid);
    if ($status > 0) {
      $map['uid'] = array('eq',$uid);
      $map['status'] = array('gt',0);
      $data_union = M('Union')->where($map)->find();
    }else{
      $data_union = array();
    }
    //查询用户昵称及其头像
    $minfo['nickname'] = get_nickname($uid,true);
    $minfo['avatar'] = get_avatar($uid) ?: __ROOT__.'/Public/'.MODULE_NAME.'/'.C('DEFAULT_THEME').'/images/avatar-default.png'; ;
    //微信分享内容
    $nickname = get_nickname($uid);
    $pic_url = SITE_URL."/Public/Home/default-mobile/images/logo-avatar.png";
    $this->meta_title = '我是'.$nickname.',邀请你加入'.C('WEB_SITE_TITLE');
    $desc = "全新移动正品购物轻创业平台，无忧推广，打造你的个性小店。";
    $pic_url = get_image_thumb($pic_url,200,200);
    $share = array(
        'title' => $this->meta_title,
        'desc' => $desc ? $desc :null,
        'img_url' => $pic_url ? $pic_url: null,
       // 'link' => SITE_URL.U('Shop/sdpCode', array('uid' => $uid,'share_param'=>1))
    );
    $this->share_param = $share_param;
    $this->meta_share = $share;
    $this->uid = $uid;
    $this->uids = UID;
    $this->minfo = $minfo;
    $this->data_union = $data_union;
    $this->display('sdpCode');
  }



    public function sdpShare(){
        $uid = I('get.uid') ?: UID;
        $share_param = I('request.share_param');
        //查询商户是否开通推广联盟
        $status = M('User')->where('id='.$uid)->getfield('form_union_status');
        $this->invite_code = union_code($uid);
        if ($status > 0) {
            $map['uid'] = array('eq',$uid);
            $map['status'] = array('gt',0);
            $data_union = M('Union')->where($map)->find();
        }else{
            $data_union = array();
        }
        //查询用户昵称及其头像
        $minfo['nickname'] = get_nickname($uid,true);
        $minfo['avatar'] = get_avatar($uid) ?: __ROOT__.'/Public/'.MODULE_NAME.'/'.C('DEFAULT_THEME').'/images/avatar-default.png'; ;
        //微信分享内容
        $nickname = get_nickname($uid);
        $pic_url = SITE_URL."/Public/Home/default-mobile/images/logo-avatar.png";
        $this->meta_title = '我是'.$nickname.',邀请你加入M宝商城!';
        $desc = "全新移动正品购物轻创业平台，无忧推广，打造你的个性小店。";
        $pic_url = get_image_thumb($pic_url,200,200);
        $share = array(
            'title' => $this->meta_title,
            'desc' => $desc ? $desc :null,
            'img_url' => $pic_url ? $pic_url: null,
            'link' => SITE_URL.U('Shop/sdpCode', array('uid' => $uid))
        );
        $this->share_param = $share_param;
        $this->meta_share = $share;
        $this->uid = $uid;
        $this->uids = UID;
        $this->minfo = $minfo;
        $this->data_union = $data_union;
        $this->display('');
    }


  /**
   * 店铺管理
   * @author Max.Yu <max@jipu.com>
   */
  public function manage(){
    if(IS_POST){
      //直接加判断 BUDDHA
      if (empty(I('post.name'))) {
        $this->error('请输入店铺名称！');
      }
      if (empty(I('post.intro'))) {
        $this->error('请输入店铺简介！');
      }
      $res = D('Shop')->update();
      if($res){
        $this->success('店铺信息设置成功！',U('Shop/detail/uid/'.UID));exit;
      }else{
        $this->error(D('Shop')->getError());
      }
    }else{
      $data = M('Shop')->getByUid(UID);

     /* if(!empty($data['shopper_code'])){
          $data['shopper_code'] = get_cover($data['shopper_code'])['path'];
      }*/
      if($data['status'] != 1){
        $this->redirect('Shop/add');
      }
      //店铺背景图处理 PC wap
      if ( empty($data['pc_pic']) ) {
        $data['pc_pic'] = get_cover(C('SDP_PC_BANNER'),'path');
      }
      if ( empty($data['wap_pic']) ) {
        $data['wap_pic'] = get_cover(C('SDP_WAP_BANNER'),'path');
      }
      $this->data = $data;
    }

    $this->meta_title = '店铺设置';
    $this->display();
  }

  /**
   * 商品管理
   * @author Max.Yu <max@jipu.com>
   */
  public function item(){
    $shop_data = M('Shop')->where(array('uid' => UID, 'status' => 1))->find();
    $where = array(
      'id' => array('IN', $shop_data['item_ids']),
      'status' => 1,
    );
    $order = "INSTR('{$shop_data['item_ids']}', id)";
    if($shop_data['item_ids'])$lists = $this->lists('Item', $where, $order, 'id, name, price, images, thumb, sdp_type, sdp', 24);
    if($lists)$lists = A('Item', 'Event')->formatList($lists);
    if($shop_data){
      $shop_data['items'] = explode(',', $shop_data['item_ids']);
    }
	 foreach($lists as $k => $v){
        if($v['sdp_type'] == '1'){
            $lists[$k]['sdp'] = sprintf("%.2f", $v['price'] * $v['sdp']/100)  ;
        }
    }
  
    $this->shop_data = $shop_data;
    $this->lists = $lists;
    $this->meta_title = '商品管理';
    $this->display(IS_AJAX ? 'itemList' : null);
  }

  /**
   * 选择商品
   * @author Max.Yu <max@jipu.com>
   */
    public function selectItem($cid = ''){
        $where = array(
            'status' => 1,
            'sdp' => array('gt', 0)
        );
        if($cid){
            $where['cid_1|cid_2|cid_3'] = $cid;
        }
        //获取当前类所在层级
        $cates[]=array('item'=>D('ItemCategory')->getChild($cid),'pid'=>$cid,'cid'=>0);
        $cate_active[$cid][0] = 'current';
        if(count($cates[0]['item'])==0)$cates=array();
        $pid=$cid;
        while($pid>0){
            $item_info=D('ItemCategory')->info($pid);
            $item_tmp=D('ItemCategory')->getBrother($pid);
            $cate_active[$item_tmp[0]['pid']][$pid] = 'current';
            $cates[]=array('item'=>$item_tmp,'cid'=>$pid,'cname'=>$item_info['name'],'pid'=>$item_tmp[0]['pid']);
            $pid=$item_tmp[0]['pid'];
        }
        $cates=array_reverse($cates);
        //删掉没有分销商品的分类
        foreach($cates as $key => $value){
          foreach ($value['item'] as $k => $v) {
            $result = M('Item')->where("status = 1 and sdp > 0 and (cid_1='{$v['id']}' OR cid_2='{$v['id']}') ")->count('id');
            if($result == 0){
                unset($cates[$key]['item'][$k]);
            }
          }
        }
        $shop_data = M('Shop')->where(array('uid' => UID, 'status' => 1))->find();
        //去掉已添加商品的ID
       if($shop_data){
           $item_ids = M('Shop')->where('uid='.UID)->getField('item_ids');
          if($item_ids){
              $where['id'] = array('NOT IN',$item_ids);
              $lists = $this->lists('Item', $where, 'id desc', true ,24);
              $lists = A('Item', 'Event')->formatList($lists);
          }else{
              $lists = $this->lists('Item', $where, 'id desc', true ,24);
              $lists = A('Item', 'Event')->formatList($lists);
          }
           $shop_data['items'] = explode(',', $shop_data['item_ids']);
       }
        $this->category = $cates;
        //手机站分类展现
        $cateTree = D('ItemCategory')->getTree();
        foreach ($cateTree as $key => $value) {
            $res1 = M('Item')->where("status = 1 and sdp > 0 and cid_1='{$value['id']}'")->count('id');
            if ($res1 == 0) unset($cateTree[$key]);
            foreach ($value['_'] as $k => $v) {
              $res2 = M('Item')->where("status = 1 and sdp > 0 and cid_2='{$v['id']}'")->count('id');
              if ($res2 == 0) unset($cateTree[$key]['_'][$k]); 
            }
        }
      	   foreach($lists as $k => $v){
        if($v['sdp_type'] == '1'){
            $lists[$k]['sdp'] = sprintf("%.2f", $v['price'] * $v['sdp']/100)  ;
        }
    }
        $this->cateTree= $cateTree;
        $this->cate_active = $cate_active;
        $this->shop_data = $shop_data;
        $this->lists = $lists;
        $this->meta_title = '选择商品';
        $this->display(IS_AJAX ? 'itemList' : 'selectItem');
    }

  /**
   * 添加商品到店铺
   * @author Max.Yu <max@jipu.com>
   */
  public function addItem(){
    $itemid = I('post.itemid', 0);
    if(empty($itemid)){
      $this->error('非法请求！');
    }
    $shop_model = D('Shop');
    $res = $shop_model->addItem($itemid);
    if($res){
      $this->success('成功添加到您的店铺！');
    }else{
      $this->error($shop_model->getError());
    }
  }

  /**
   * 删除店铺商品
   * @author Max.Yu <max@jipu.com>
   */
  public function removeItem(){
    $itemid = I('post.itemid', 0);
    if(empty($itemid)){
      $this->error('非法请求！');
    }
    $shop_model = D('Shop');
    $res = $shop_model->removeItem($itemid);
    if($res){
      $this->success('删除成功！');
    }else{
      $this->error($shop_model->getError());
    }
  }

  /**
   * 订单管理
   * @author Max.Yu <max@jipu.com>
   */
  public function order($uid = 0){
    $prefix= C('DB_PREFIX');
    $model = M()->table($prefix."order_item a")->join($prefix."order b on b.id= a.order_id")->join($prefix."shop c on c.secret=a.sdp_code")->join($prefix."finance d on b.id=d.order_id");
    $where  = array(
      'c.uid'       => UID ,
      'b.o_status'  => array('in' , array(202,205)) ,
      'd.type' => array('in',array('sdp_order','sdp_refund'))
    );
    $field = 'b.order_sn,a.item_id,b.create_time,a.price,a.item_code,d.amount,a.thumb,b.id,a.name' ;
    $order = 'a.id desc' ;
    $lists = A('Page', 'Event')->lists($model, $where, $order, 6 ,'', $field);
    if($lists){
      foreach($lists as $k => $v){
            //不存在则为封面图片
        $lists[$k]['cover_path'] =  get_cover($v['thumb'], 'path');
      }
    }
    $this->lists = $lists;
    $this->meta_title = '订单管理';
    $this->display(IS_AJAX ? 'orderList' : null);
  }

  /**
   * 店铺统计
   * @author Max.Yu <max@jipu.com>
   */
  public function stat($type = 'days'){
    if($type == 'days'){
      $stat_data = D('Shop')->getMonthData(); //获取月数据
    }else{
      $stat_data = D('Shop')->getYearData(); //获取年数据
    }
    $this->data = $stat_data;
    $this->type = $type;
    $this->meta_title = '店铺统计';
    $this->display();
  }
  
  /**
   * 开店引导
   * @author Max.Yu <max@jipu.com>
   */
  public function guide(){
    $shop = M('Shop')->getByUid(UID);
    if($shop){
      $this->redirect('Shop/add');
    }
    $this->meta_title = '分销店铺';
    $this->display();
  }
  
  /**
   * 提交开店申请
   * @author Max.Yu <max@jipu.com>
   */
  public function add(){
    $shop_secret = SHOP_SECRET;
    if(!empty($shop_secret)){
      $this->redirect('Member/sdp');
    }
    if(IS_POST){
      if(D('Shop')->update() !== false){
        $this->success('提交成功，等待审核！');
      }else{
        $this->error(D('Shop')->getError());
      }
    }else{
      
      $shop = M('Shop')->getByUid(UID);
      $this->audit_data = unserialize($shop['audit_data']);
      $this->shop = $shop;
      $this->meta_title = '提交申请信息';
      $this->display();
    }
  }
   /**
   * 绑定推荐人
   * @author yusheng
   */
   public function SetRecommender(){
       if(IS_POST){
           $data = I('post.');
           $remember = $data['remember'];
           if($remember == 0){
               $this->error('请阅读并勾选邀请码详细规则');
           }
           $mobile = M('User')->getFieldById(UID, 'mobile');
           $country_info = M('User')->where(array('mobile'=>$mobile))->find();
           //$prefix = M('Country')->where("countryid='{$country_info['countryid']}'")->getField('prefix');
           //if($country_info['countryid'] && $country_info['countryid'] != '9001'){
           //   $mobile = '+'.$prefix.$mobile;
           //}

           if(substr($mobile,0,1) == '+') {
               $tpl_id = 1867730;
           }else{
               $tpl_id = 2;
           }
          $data['username'] == $mobile ? :$this->error('请输入正确的手机号码！');
          //$rs =  check_ypsms_code($mobile, $tpl_id, $data['randcode']);
          //$rs !== false ? :$this->error('验证码不正确！');
           $invite_code = $data['invite'];
           if($invite_code){
               if(!$my_union = union_code($invite_code , 1 )){
                   $this->error('该推广码无效!');
               }
               if(!M('User')->where('id ='.$my_union.' and status = 1')->find()){
                   $this->error('该推广用户或被禁用');
               }
           }
            // 解绑微信通知 上级及其自己的一级下级
            $Model =  M('Distribution');
            $rst =  handle_union(UID,$invite_code);
            if (is_numeric($rst)) {
              $this->error(union_error($rst));
            }else{
              //更改绑定次数
              $Model->where('user_id='.UID)->setField('check_bind',1);
              $this->success('绑定成功！',U('Shop/sdpCode',array('uid'=>$my_union,'share_param'=>1)));
            }
          //  $rst == true ? $this->success('绑定成功！',U('Member/qr',array('uid'=>$my_union))) :$this->error('绑定失败！');
       }else{
           //解绑相关
           if (I('type')) {
             $union = M('Distribution')->where('user_id='.UID)->find();
             $arr['type'] = I('type');
             $arr['one_name'] = get_nickname($union['oneagents']);
             $this->arr = $arr;
           }
           $mobile = get_mobile(UID);
           $data = R('Member/checkuser');
           $cdata = M('ArticleSystem')->where(array('code'=>'Invitation_aggrement'))->find();
           $content = $cdata['content'];
           $this->assign('mess' , $data['mess']);
           $this->mobile = $mobile;
           $data['status'] && $this->assign('status' ,$data['status']);
           $this->assign('user_agreement',$content);
           $this->display('Set_recommender');
       }

  }

  /**
   * 我上传的二维码
   * @author yusheng
   */
   public function barcode(){
       $uid = I('request.uid');
       $info = array();
       $info['shopper_code'] = M('Shop')->where(array('uid' => $uid, 'status' => 1))->getField('shopper_code');
       $info['path'] = SITE_URL.$info['shopper_code'];
    //   $info['nickname'] = get_nickname($uid,true);
    //   $info['avatar'] = get_avatar($uid);
    //   $info['mobile'] =get_mobile($uid);
       $this->meta_title = "联系店长客服微信";
       $this->assign('info',$info);
       $this->display();
  }

  public function sdpCodeto(){
    $this->display();
  }

}
