<?php
/**
 * 前台专场模型
 * @version 2014102014
 * @author Max.Yu <max@jipu.com>
 */

namespace Home\Model;

use Think\Model;

class ActivityModel extends Model{

  /**
   * 获取专场列表
   * @param array $map 查询条件参数
   * @param string $field 字段 true-所有字段
   * @param string $order 排序规则
   * @param string $limit 分页参数
   * @return array 商品列表
   * @author Max.Yu <max@jipu.com>
   */
  public function lists($map = array(), $field = true, $order = '`create_time` ASC', $limit = '40'){
    $list = $this->field($field)->where($map)->order($order)->limit($limit)->select();

    return $list;
  }

  /**
   * 获取专场详情
   * @param array $id 专场id
   * @param string $field 字段 true-所有字段
   * @return array 详情
   * @author Max.Yu <max@jipu.com>
   */
  public function detail($id, $field = true){
    $map['id'] = $id;
    $info = $this->field($field)->where($map)->find();
    if(!(is_array($info) || $info['status'] !== 1)){
      $this->error = '专场信息不存在！';
      return false;
    }

    if(!empty($info['items'])){
        $where['id'] = array('IN', $info['items']);
        $field = true;
        $order = '`is_top` DESC, `sort` ASC';
        $limit = is_mobile() ? 8 : 36;
        $info['items_list'] = D('Item')->lists($where, $field, $order, $limit);
        
        if($info['theme'] == 'join'){ //拼团
            $jMap['item_id'] = array('IN', $info['items']);
            $jField = 'item_id,join_num,first_price,join_price';
            $joinList = M('JoinItem')->where($jMap)->field($jField)->select();
            $newArr = array();
            foreach ($joinList as $k=>$v){
                $newArr[$v['item_id']] = $v;
            }
            
            foreach ($info['items_list'] as $key=>$val){
                $info['items_list'][$key]['join_price'] = $newArr[$val['id']]['join_price'];
                $info['items_list'][$key]['first_price'] = $newArr[$val['id']]['first_price'];
                $info['items_list'][$key]['join_num'] = $newArr[$val['id']]['join_num'];
            }
        }
      
    }

    return $info;
  }
  
}
