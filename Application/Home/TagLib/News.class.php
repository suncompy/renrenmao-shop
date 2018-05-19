<?php
namespace Home\Taglib;
use Think\Template\TagLib;
// defined('THINK_PATH') or exit();
class News extends TagLib{

  protected $tags = array(
      'test' => array('attr' => 'name,id','close' =>0) ,// attr 属性列表close 是否闭合（0 或者1 默认为1，表示闭合）
      'pagelist' => array('attr' => 'cid,is_topic,is_top,keywords,where,order,all,model','close','close' =>0) ,
      'list'     => array('attr' => 'cid,is_topic,is_top,keywords,where,order,all,model','close' =>0) ,
      'category'  => array('attr' => 'cid,flow,self','close' =>0) ,
      'area'  => array('attr' => 'pid ,name','close' =>0) ,
         
  );
  //地区标签
  public function _area($tag ,$content){
    $pid = $tag['pid'] ? $tag['pid']:'0';
    $name  = $tag['name'] ? $tag['name']:'area';
    $html  = '<?php $temp = '.$pid;
    $html .= ' ;$'.$name.'= M(\'Area\')->where("pid = \'$temp\' ")->field(\'id, title\')->select();';
    $html .= ' ?>';
    return $html ;
  }
  // 前端示例
    //   <taglib name='News' />
    // <news:list cid='4' is_top='1'/>
    // <php>var_dump($page);</php>
    // php示例
  public function _test ($tag,$content){
    $name  =   $tag['name'];
    $id    =   $tag['id'];
    $str   =   'heeheheheheh';
    $this->tpl->set('psd',$str);
    return '';
  }

  public function _category($tag ,$content){
    if($tag['cid']){
      $tree = M('article_category')->where('id ='.$tag['cid'])->getField('tree');
      $arr  = explode( ',' ,$tree);
      $arr = array_filter($arr);
      $key  = array_search( $tag['cid'] , $arr )  ;
      $flow = $tag['flow'] == 'up' ? 'up' : 'down' ;
      $self = $tag['self'] ;
      foreach($arr as $k => $v){
        if($self && $k== $key)$temp = $arr[$k];

          if( $key <= $k )unset($arr[$k]) ;
        
      }
      if($self)array_push($arr, $temp);
    }
    $this->tpl->set('catids' , $arr);
    return '' ;
  }
  // 获取新闻列表，带分页
  public function _pagelist($tag,$content) {
    $num   = $tag['num'] ;
    $where = $this->handlemap($tag);
    $order = 'sort desc ,id desc';
    !empty($tag['order']) && $order = $tag['order'] ;
    $model = $tag['model'] ? $tag['model'] : M('Article') ;
    $data  = self::_page( $model , $where ,$order ,$num);
    @extract ( $data );
    foreach($list as $k=>$v){
      $list[$k]['images'] && $list[$k]['images'] = get_cover($v['images'], 'path') ;
      $list[$k]['thumb'] && $list[$k]['thumb'] = get_cover($v['thumb'], 'path') ;
    }
    $this->tpl->set('page' , $list);
    $this->tpl->set('_p' , $list);
    return '';
  }
  // 列表 不带分页
  public function _list($tag,$content){
    $num = $tag['num'] ;
    $where = $this->handlemap($tag);
    $order = 'sort desc ,id desc';
    !empty($tag['order']) && $order = $tag['order'] ;
    $model  = $tag['model'] ? $tag['model'] : M('Article') ;
    $data  = $model->where($where)->order($order)->cache(true)->limit($num)->select();
    foreach($data as $k=>$v){
      $data[$k]['images'] && $data[$k]['images'] = get_cover($v['images'], 'path') ;
      $data[$k]['thumb'] && $data[$k]['thumb'] = get_cover($v['thumb'], 'path') ;
    } 
    $this->tpl->set('page' , $p);
    return '';
  }

  protected function handlemap($tag){
    if(!empty($tag['keywords'])){
        $where['title|description|category'] = array('like' , '%'.$tag['keywords'].'%');
    } 
    if(!empty($tag['cid'])){
      $id = $tag['cid'];
      if($tag['all']){
        $map['tree'] = array('like' , '%,'.$id.',%');
        $id = M('article_category')->where($map)->getField('id' ,true);
      }
      $where['cid'] = array('in' ,$id);
    }
    !empty($tag['where']) && $where[] = $tag['where'] ;
    !empty($tag['is_topic']) && $where['is_topic'] = 1;
    !empty($tag['is_top']) && $where['is_top'] = 1;
    $where['status'] = 1;
    return $where; 
  }
  /**
   * 分页数据
   */
  protected function _page($model, $where = array(), $order = 'id DESC' ,$num ){
    $num = $num ? $num :C('LIST_ROWS');
    $count = $model->where($where)->count();
    $page = new \Think\Page($count, $num);
    if($count > $this->listRows){
      $page->setConfig('theme', '%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
    }
    $p = $page->show();
    $first_row = $page->firstRow;
    $list = $model->where($where)->limit($first_row, $page->listRows)->cache(true)->order($order)->select();
    return array(
      'list' => $list,
      'p' => $p,
    );
  }

}