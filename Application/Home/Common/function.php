<?php
/**
 * 前台公共函数
 * 主要定义前台公共函数库
 * @version 2014100714
 * @author Max.Yu <max@jipu.com>
 */

/**
 * 检测验证码
 * @param integer $id 验证码ID
 * @param boolean $reset 验证后是否重置
 * @return boolean 检测结果
 * @author Max.Yu <max@jipu.com>
 */
function check_verify($code, $id = 1, $reset = true){
  $config = array();
  $config['reset'] = $reset;
  $verify = new \Think\Verify($config);
  return $verify->check($code, $id);
}

/**
 * 获取列表总行数
 * @param string $category 分类ID
 * @param integer $status 数据状态
 * @author Max.Yu <max@jipu.com>
 */
function get_list_count($category, $status = 1){
  static $count;
  if(!isset($count[$category])){
    $count[$category] = D('Document')->listCount($category, $status);
  }
  return $count[$category];
}

/**
 * 获取段落总数
 * @param string $id 文档ID
 * @return integer 段落总数
 * @author Max.Yu <max@jipu.com>
 */
function get_part_count($id){
  static $count;
  if(!isset($count[$id])){
    $count[$id] = D('Document')->partCount($id);
  }
  return $count[$id];
}

/**
 * 获取导航URL
 * @param string $url 导航URL
 * @return string 解析或的url
 * @author Max.Yu <max@jipu.com>
 */
function get_nav_url($url){
  switch($url){
    case 'http://' === substr($url, 0, 7):
    case '#' === substr($url, 0, 1):
      break;
    default:
      $url = U($url);
      break;
  }
  return $url;
}

/**
 * 根据商品id获取库存
 * @param int $item_id 商品id
 * @param string $item_code 商品编码
 * @return string 库存
 * @version 2015070817
 * @author Justin <justin@jipu.com>
 */
function get_item_stock($item_id, $item_code){
  return D('Order')->getItemStock($item_id, $item_code);
}

/**
 * 保存图片到本地
 * @param string $type 保存类型
 * @param string $file_path 保存路径
 * @version 2015070817
 * @author Max.Yu <max@jipu.com>
 */
function save_image($type, $file_path,$width=0,$height=0){
  $path = 'Uploads/'.$type.'/'.date('Y/m/d').'/';
  $f = mkdir($path, 0777, true);
  $new_filepath = $path.md5($file_path).'.png';
  $file_content = file_get_contents($file_path);
  $file_json = json_decode($file_content, true);
  if(is_array($file_json)){
    return '';
  }else{
    file_put_contents($new_filepath, $file_content);
    //if($width>0 && $height>0)my_image_resize($file_path,$file_path,$width,$height);
  }
  return $new_filepath;
}

/*重设图片尺寸*/
function my_image_resize($src_file, $dst_file , $new_width , $new_height) {
  $new_width= intval($new_width);
  $new_height=intval($new_height);
  if($new_width <1 || $new_height <1) {
    echo "params width or height error !";
    exit();
  }
  if(!file_exists($src_file)) {
    echo $src_file . " is not exists !";
    exit();
  }
  // 图像类型
  
  if(function_exists(exif_imagetype)){
      $type=exif_imagetype($src_file);
  }else{
      $typeInfo = getimagesize($src_file);
      $type = $typeInfo[2];
  }
  $support_type=array(IMAGETYPE_JPEG , IMAGETYPE_PNG , IMAGETYPE_GIF);
  if(!in_array($type, $support_type,true)) {
    echo "不支持此类型";
    exit();
  }
  //Load image
  switch($type) {
    case IMAGETYPE_JPEG :
      $src_img=imagecreatefromjpeg($src_file);
      break;
    case IMAGETYPE_PNG :
      $src_img=imagecreatefrompng($src_file);
      break;
    case IMAGETYPE_GIF :
      $src_img=imagecreatefromgif($src_file);
      break;
    default:
      echo "Load image error!";
      exit();
  }
  $w=imagesx($src_img);
  $h=imagesy($src_img);
  $ratio_w=1.0 * $new_width / $w;
  $ratio_h=1.0 * $new_height / $h;
  // 生成的图像的高宽比原来的都小，或都大 ，原则是 取大比例放大，取大比例缩小（缩小的比例就比较小了）
  if($ratio_w < $ratio_h) {
    $ratio = $ratio_h ; // 情况一，宽度的比例比高度方向的小，按照高度的比例标准来裁剪或放大
  }else {
    $ratio = $ratio_w ;
  }

  // 定义一个中间的临时图像，该图像的宽高比 正好满足目标要求
  $inter_w=(int)($new_width / $ratio);
  $inter_h=(int) ($new_height / $ratio);
  $inter_img=imagecreatetruecolor($inter_w , $inter_h);
  //var_dump($inter_img);
  imagecopy($inter_img, $src_img, 0,0,($w-$inter_w)/2,($h-$inter_h)/2,$inter_w,$inter_h);
  // 生成一个以最大边长度为大小的是目标图像$ratio比例的临时图像
  // 定义一个新的图像
  $new_img=imagecreatetruecolor($new_width,$new_height);
  //var_dump($new_img);exit();
  imagecopyresampled($new_img,$inter_img,0,0,0,0,$new_width,$new_height,$inter_w,$inter_h);
  switch($type) {
    case IMAGETYPE_JPEG :
      imagejpeg($new_img, $dst_file); // 存储图像
      break;
    case IMAGETYPE_PNG :
      imagepng($new_img,$dst_file);
      break;
    case IMAGETYPE_GIF :
      imagegif($new_img,$dst_file);
      break;
    default:
      break;
  }
}// end function

/**
 * 判断商品是否限购
 * @param int $item_id 商品ID
 * @return bool 限购状态
 * @author Max.Yu <max@jipu.com>
 */
function get_quota_status($item_id = 0){
  $item = M('Item')->field('id, quota_num')->where(array('id' => $item_id))->find();
  return $item['quota_num'] > 0;
}

/**
 * 获取当前身份限购数量
 * @param int $item_id 商品ID
 * @return int 限购数量
 * @author Max.Yu <max@jipu.com>
 */
function get_quota_num($item_id = 0){
  $item = M('Item')->field('id, stock, quota_hours, quota_num')->where(array('id' => $item_id))->find();
  if($item['quota_num'] > 0){
    $num = min($item['quota_num'], $item['stock']);
    if(UID){
      $order_items = M('OrderItem')->field('quantity, order_id')->where(array('item_id' => $item_id))->select();
      $oids = array_column($order_items, 'order_id');
      if($oids){
        $where = array(
          'status' => 1,
          'id' => array('IN', $oids),
          'uid' => UID,
          'payment_time' => array('gt', 0),
          'create_time' => array('gt', NOW_TIME - $item['quota_hours'] * 3600)
        );
        $order_lists = M('Order')->field('id')->where($where)->select();
        $order_ids = array_column($order_lists, 'id');
        $buynum_exists = 0;
        foreach($order_items as $li){
          if(in_array($li['order_id'], $order_ids)){
            $buynum_exists+= $li['quantity'];
          }
        }
        if($buynum_exists > 0){
          $_new_num = $num - $buynum_exists;
          $num = $_new_num > 0 ? $_new_num : 0;
        }
      }
    }
  }else{
    $num = $item['stock'];
  }
  return intval($num);
}
/**
 * 检查当前团购是否超出限购数量
 * @param int $item_id 商品ID
 * @return int 限购数量
 * @author Max.Yu <max@jipu.com>
 */
function get_quota_joinnum($item_id = 0){
  return M('join_item')->alias('a')->join('RIGHT JOIN __JOIN__ b on b.id=a.join_id')->where('a.item_id ='.$item_id.' and b.status =1 ')->getField('a.stock');
}
/**
 * 限购文字提示
 * @param int $item_id 商品ID
 * @return string 提示文字
 * @author Justin <justin@jipu.com>
 */
function quota_text($item_id = 0){
  $text = '';
  $item = M('Item')->field('id, stock, quota_hours, quota_num')->where(array('id' => $item_id))->find();
  if($item['quota_num'] > 0){
    switch($item['quota_hours']){
      case 0: $text = '每订单';
        break;
      case 24: $text = '每天';
        break;
      case 24 * 7: $text = '每周';
        break;
      case 24 * 30: $text = '每月';
        break;
      case 24 * 365: $text = '每年';
        break;
      case 24 * 3650: $text = '每用户';
        break;
    }
  }
  return $text;
}

/**
 * 获取供应商包邮最低限额
 * @param int $supplier_id 供应商ID
 * @return int 最低限额
 * @author Justin <justin@jipu.com>
 */
function get_supplier_free_amount($supplier_id = 0){
  if(empty($supplier_id))return C('DELIVERY_FREE_AMOUNT');
  $free_amount = M('Supplier')->getFieldBySupplierId($supplier_id, 'free_amount');
  return $free_amount ? $free_amount : 0;
}

// .-----------------------------------------------------------------------------------
// | Version: 2017
// |-----------------------------------------------------------------------------------
// | Author: Fourth Pu <540690666@qq.com|ppssone@live.cn>
// |-----------------------------------------------------------------------------------
// | 该供应商是否必须运费
// .-----------------------------------------------------------------------------------

function NoFreeAmount($supplier_id = 0){
  if(empty($supplier_id))return false;
  return M('Supplier')->getFieldBySupplierId($supplier_id, 'no_free') == 0 ?  false : true;
}


/**
 * 获取订单状态文字
 * @param int $o_status 订单状态
 * @return string 订单状态标识文字
 * @author Max.Yu <max@jipu.com>
 */
function get_order_status_text($o_status){
  $status_text = array(
    0 => '待付款',
    -1 => '交易关闭',
    200 => '已付款，待发货',
    201 => '卖家已发货',
    202 => '交易成功',
    300 => '已申请退款，待审核',
    301 => '卖家同意退款，请退货',
    302 => '已退货，待卖家退款',
    303 => '已退款，交易关闭',
    304 => '同意退款，平台退款处理中',
    404 => '交易关闭',
    405 => '退款驳回',
    205 => '交易完成'
  );
  return isset($o_status) ? $status_text[$o_status] : $status_text;
}

/**
 * 发送站内消息
 * @param $data = array(
 *    'to_uid' => 30,    //可省略 默认为当前用户UID
 *    'title'  => '标题', //必填  消息标题
 *    'content'=> '内容'  //必填  消息内容
 * )
 * @return boolean 发送状态（true 成功，false 失败）
 * @author Max.Yu <max@jipu.com>
 */
function send_message($data = array()){
  $send_status = false;
  $data && empty($data['to_uid']) && $data['to_uid'] = UID;
  //必要条件
  if($data && $data['to_uid'] && $data['title'] && $data['content']){
    $send_data = array(
      'uid' => 1,
      'to_uid' => $data['to_uid'],
      'title' => $data['title'],
      'content' => $data['content'],
      'create_time' => NOW_TIME
    );
    $message_id = M('Message')->add($send_data);
    $send_status = $message_id > 0;
  }
  return $send_status;
}


/**
 * 内容懒加载替换
 * @author Max.Yu <max@jipu.com>
 */
function img_lazy_replace($content = ''){
  $html = '<img src="'.__IMG__.'/img-pai-bg.png" data-url="';
  return str_replace('<img src="', $html, $content);
}

/**
 * 根据商品ID获取商品名称
 * @author Max.Yu <max@jipu.com>
 */
function get_item_name($item_id = 0){
  return M('Item')->getFieldById($item_id, 'name');
}

/**
  * 获取订单返现提成
  * @param  [type] $users [description]
  * @return [type]        [description]
  */
function distribute( $orderid , $uid ){
    $people = M('Distribution')->where('user_id='.$uid)->find();
    // 如果没开通返现 或者这个人没加入推广联盟
    if(C('DIS_START') == 0 || !$people){
      return false;
    }
    $orders = M('Order')->alias('a')->join('__ORDER_ITEM__ b on a.id=b.order_id')->join('__ITEM__ c on c.id= b.item_id')->where('a.id='.$orderid)->field('a.order_sn ,b.price,b.quantity,c.sdp1,c.sdp2,sdp3,a.payment_type')->select();
    if($people['oneagents'] > 0 ){
      $right['one'] = M('User')->alias('a')->join('__UNION__ b on a.id=b.uid')->where('a.status =1 and b.status=1 and a.form_union_status=1 and a.id='.$people['oneagents'])->getField('a.id');
    }
    if($people['twoagents'] > 0 && C('DIS_CLASS') > 1 ){
      $right['two'] = M('User')->alias('a')->join('__UNION__ b on a.id=b.uid')->where('a.status =1 and b.status=1 and a.form_union_status=1 and a.id='.$people['twoagents'])->getField('a.id');
    }
    if($people['threeagents'] > 0 && C('DIS_CLASS') > 2 ){
      $right['three'] = M('User')->alias('a')->join('__UNION__ b on a.id=b.uid')->where('a.status =1 and b.status=1 and a.form_union_status=1 and a.id='.$people['threeagents'])->getField('a.id');
    } 
    foreach($orders  as $k => $v){
      if($right['one'] && $v['sdp1'] != '0'){
        $tem[$k][0] = (($v['sdp1'] == '' || is_null($v['sdp1']))  ? $v['price'] *C('FIRISTNAME') : $v['price'] * $v['sdp1'] )/100 ;
        $order[$k][0] = sprintf('%.2f', $tem[$k][0]*$v['quantity']);
      }
      if($right['two'] && $v['sdp2'] != '0'){
        $tem[$k][1] = (($v['sdp2'] == '' || is_null($v['sdp2'])) ? $v['price'] *C('SECONDNAME') : $v['price'] *$v['sdp2'])/100 ;
        $order[$k][1] = sprintf('%.2f', $tem[$k][1]*$v['quantity']);
      }
      if($right['three'] && $v['sdp3'] != '0'){
        $tem[$k][2] = (($v['sdp3'] == '' || is_null($v['sdp3'])) ? $v['price'] * C('THIRDNAME') : $v['price'] * $v['sdp3']  ) /100;
        $order[$k][2] = sprintf('%.2f', $tem[$k][2]*$v['quantity']);
      }
    }
    foreach($order as $k => $v){
      $v[0] && $coast['onebonus']    += $v[0] ;
      $v[1] && $coast['twobonus']    += $v[1] ;
      $v[2] && $coast['threebonus']  += $v[2] ;
    }
    if(empty($coast['onebonus']) && empty($coast['twobonus']) && empty($coast['threebonus'])){
      return false;
    }
    $log = array(
      'uid'        => $uid ,
      'order_id'   => $orderid,
      'onebonus'   => $coast['onebonus']   ? $coast['onebonus'] : 0,
      'twobonus'   => $coast['twobonus']   ? $coast['twobonus'] : 0 ,
      'threebonus' => $coast['threebonus'] ? $coast['threebonus'] : 0,
      'create_time'=> time(),
    );
    M('Distributlog')->add($log);
    $payway = get_payment_type_text($orders['payment_type'] );
    $coast = array_filter($coast) ;
    foreach($coast as $k => $v){
      $nickname = get_nickname($uid);
      $kk = substr($k , 0 ,strlen($k)-5);
      M('Member')->where('uid='.$people[$kk.'agents'])->setInc('finance', $v);
      $data_finance[] = array(
        'uid'       => $people[$kk.'agents'],
        'order_id'  => $orderid,
        'type'      => 'union_order',
        'amount'    => $v,
        'flow'      => 'in',
        'memo'      => $nickname .'的推广联盟订单返现，订单编号：'.$orders[0]['order_sn'].'。',
        'create_time' => NOW_TIME,
        'status'    => 0 ,
      );
      $show_union = array(
        'reason' => $nickname .'的推广联盟订单返现，订单编号：'.$orders[0]['order_sn'].'。',
        'money' => $v,
        'way'   => $payway,
        'uid'   => $people[$kk.'agents'],
      ) ;
      A('WechatTplMsg', 'Event')->wechatTplNotice('union', $show_union);
    }
    return M('Finance')->addAll($data_finance) ? true : false;
}

/**
 * 获取用户头像
 * @author buddha <[email address]>
 * @param  [type] $uid [description]
 * @return [type]      [description]
 */
function getAvatar($uid){
  if(empty($uid)){
    return false;
  }
  $pic = M('Member')->where('uid ='.$uid)->getField('avatar');
  return $pic ? $pic : __IMG__.'/avatar-default.png';
}

/**
 * 获取用户下线人数
 * @author buddha <[email address]>
 * @param  [type] $uid [description]
 * @return [type]      [description]
 */
function myflowers($uid){
  return M('Distribution')->where(' oneagents ='.$uid.' or twoagents='.$uid.' or threeagents='.$uid )->count();
}

/**
 * 获取用户注册时间
 * @author buddha <[email address]>
 * @param  [type] $uid [description]
 * @return [type]      [description]
 */
function regtime($uid){
  $time = M('User')->where('id='.$uid )->getField('reg_time');
  return date('Y-m-d H:i:s' ,$time);
}
/**
 * 获取团购参团价格
 * @param  [type] $joid join_order 的ID
 * @param  [type] $type 参团类型1 为开团 2 为参团
 */
function getjoinprice( $joid , $type = 2){
  $field = $type == 2 ? 'join_price' : 'first_price' ;
  $price = M('Join_order')->alias('a')->join('LEFT JOIN  __JOIN_ITEM_SPEC__ b on b.spc_code=a.item_code and a.item_id=b.item_id')->join('LEFT JOIN __JOIN__ c on c.id=b.activity_id')->where('c.status =1 and a.id='.$joid)->getField('b.'.$field);
  if(empty($price)){
    $price = M('join_item')->alias('a')->join(' LEFT JOIN __JOIN_ORDER__ jo on jo.item_id=a.item_id')->join('LEFT JOIN __JOIN__ b on b.id = a.join_id')->where('b.status=1  and jo.id ='.$joid)->getField($field);
  }
  return $price;
}

/**
 * 物品属性值分割开来
 * @author buddha <[email address]>
 * @param  [type] $data [description]
 * @return [type]       [description]
 */
function kaspec($data){
      if(!empty($data)){
          $i = 0 ;
          $data = unserialize($data);
          foreach($data as $k => $v){
              $i++;
              $last .= "key[".$k."]=".$v['key']."&val[".$k."]=".$v['val']."&code[".$i."]=".$k."&" ;
          }
          return rtrim($last ,'&');
      }
}
/**
 * 根据商品得到活动时间
 * @return [type] 
 */
function gethdtime($item_id){
  return  M('Join_item')->alias('a')->join('LEFT JOIN __JOIN__ b on b.id=a.join_id')->where('b.status=1 and a.item_id='.$item_id)->getField('a.etime') ;
}
/**
 *分隔数组 获得第一部分
 * @return [type] 
 */
function partarr($arr , $pa = ' ' , $k = 0){
  $temp = explode( $pa,$arr  );
  return $temp[$k];
}
/**
 *获取团购订单数
 * @return $join_id =>　ｊｏｉｎ＿ｌｉｓｔ　的ｉｄ
 */
function joincount($join_id){
  return M('Join_order')->where('status = 1 and join_id='.$join_id)->count(); 
}
/**
 * 获取活动团购ID
 */
function getactivityid($item_id){
  return M('Join_item')->alias('a')->join('LEFT JOIN __JOIN__ b on b.id=a.join_id')->where('a.item_id='.$item_id.' and b.status=1 ')->getField('a.join_id');
}

/**
 * 验证是否是来自app 目前通过特殊的参数来验证
 */
function is_app(){
    if(session('IS_APP')){
        return true;
    }
    return false;
}

// .-----------------------------------------------------------------------------------
// | Version: 2017
// |-----------------------------------------------------------------------------------
// | Author: Fourth Pu <540690666@qq.com|ppssone@live.cn>
// |-----------------------------------------------------------------------------------
// | 返回图片尺寸大小函数
// |-----------------------------------------------------------------------------------
//       (\_/)
//       ( '_')
//    /""""""""""""\======░ ▒▓▓█D
//　/"""""""""""""""""""\
//    \_@_@_@_@_@_/
/**
 * The soy sauce is well played and go home early every day
 * @author  Fourth Pu <540690666@qq.com>
 */
// |-----------------------------------------------------------------------------------
function picinfo($src){
  if(substr($src, 0, 1) == '/'){
    $src = substr($src, 1, strlen($src));
  }
  if(!is_file($src) || !$src)return false;
  $image = new \Think\Image(); 

  $image->open($src);
  $width = $image->width(); // 返回图片的宽度
  $height = $image->height(); // 返回图片的高度
  return $width.'x'.$height;
}

/**
 * @param $member
 * 获取余额中不能使用的余额总和（分销订单和推广联盟订单）
 */
function get_finance($member){
    //余额中分销订单未完成的金额总和（暂时不能使用）
    $map = array(
        'uid' => UID,
        'flow' => 'in', 
        // 'type' => array('in', array('union_order', 'union_subscribe', 'sdp_order', 'withdraw_refuse_cashback')),
        'status' => 0
    );
    $member['total_finance'] = $member['finance'];
    $arr = M('Finance')->where($map)->select();
    if(!empty($arr)){
        $sdp_count = array_column($arr,'amount');
        $sum = array_sum($sdp_count);
        $member['finance'] -= $sum;
        $member['finance'] = strval($member['finance']);
    }
    return $member;
}


/**
 * 获取用户总余额
 * @author buddha <[email address]>
 * @return [type] [description]
 */
function get_total_finance(){
  $where = array(
    'uid' => UID,
    'flow' => 'in',
    'status' => array('neq',2) ,
  );
  $in_finance  = M('finance')->where($where)->sum('amount');
  $out_finance = M('finance')->where(array('flow'=>'out','uid'=>UID))->sum('amount');
  return sprintf('%.2f' , $in_finance - $out_finance);
}

/**
* 自定义函数 模仿MySQL ORDER BY
* @author BUDDHA <[email address]>
* @DateTime 2017-08-13T10:49:28+0800
* @param    [type]                   $list  [description]
* @param    [type]                   $field [description]
* @return   [type]                          [description]
*/
function sortByCols($list,$field){  
  $sort_arr=array();  
  $sort_rule='';  
  foreach($field as $sort_field=>$sort_way){  
      foreach($list as $key=>$val){  
          $sort_arr[$sort_field][$key]=$val[$sort_field];  
      }  
      $sort_rule .= '$sort_arr["' . $sort_field . '"],'.$sort_way.',';  
  }  
  if(empty($sort_arr)||empty($sort_rule)){ return $list; }  
  eval('array_multisort('.$sort_rule.' $list);');  
  return $list;  
}

/**
 * 根据URL地址返回JSON数据
 * @author buddha <[email address]>
 * @param  [type] $url [description]
 * @return [type]      [description]
 */
function getJson($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output, true);
}

/**
 * 初始化用户信息 member
 * @author buddha <[email address]>
 * @param  [type] $uid 用户ID
 * @return [type]      array
 */
function get_member_info($uid){
  $member_model = D('Member');
  $member = $member_model->info($uid);
  //$member['finance'] = get_total_finance();
  $member = get_finance($member);
  $member['score_amount'] = C('SCORE_EXCHANGE_NUMBER')>0 ? sprintf('%.2f', $member['score']/C('SCORE_EXCHANGE_NUMBER')) : 0;
  $member['finance'] = sprintf('%.2f',$member['finance']);
  $member['total_finance'] = sprintf('%.2f',$member['total_finance']);
  return $member;
}

/**
 * 初始化用户信息 users
 * @author buddha <[email address]>
 * @param  [type] $uid [description]
 * @return [type]      [description]
 */
function get_user_info($uid){
  //初始化用户
  $user_api = new Common\Api\UserApi();
  $users = $user_api->info($uid);
  return $users;
}

