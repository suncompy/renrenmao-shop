<?php
/**
 * 系统配置API接口
 * @version 2014091512
 */

namespace Common\Api;

class ConfigApi{

  /**
   * 获取数据库中的配置列表
   * @return array 配置数组
   */
  public static function lists(){
    $map = array('status' => 1);
    $data = M('Config')->where($map)->field('type,name,value')->select();
    $config = array();
    if($data && is_array($data)){
      foreach($data as $value){
        $config[$value['name']] = self::parse($value['type'], $value['value']);
      }
    }
    $configs = self::union_config() ;
    return array_merge($config ,$configs );
  }

  /**
   * 根据配置类型解析配置
   * @param integer $type 配置类型
   * @param string $value 配置值
   */
  private static function parse($type, $value){
    switch ($type){
      case 3: //解析数组
        $array = preg_split('/[,;\r\n]+/', trim($value, ",;\r\n"));
        if(strpos($value,':')){
          $value  = array();
          foreach ($array as $val){
            list($k, $v) = explode(':', $val);
            $value[$k] = $v;
          }
        }else{
          $value = $array;
        }
        break;
    }
    return $value;
  }

  /**
   * 获取推广联盟的配置
   * @param integer $type 配置类型
   * @param string $value 配置值
   */
  public function union_config(){
    $info = M('Distributconfig')->select();
    foreach($info as $k => $v){
      if(!empty($v['lname'])){
        $result[$v['name']][$v['lname']] = $v['value'] ;
      }else{
        $result[$v['name']] = $v['value'];
      }
    }
    return $result; 
  }
}
