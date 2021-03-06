<?php

/**
 * 云片短信平台业务类
 * 主要功能：
 * 1、
 * @link yunpian.com 云片网
 * @version 20150609
 * @author Max.Yu <max@jipu.com>
 */

namespace Org\SMSYunpian;

class Sms{
  
  /**
   * http_post请求
   * @param string $action post请求动作
   * @param string/array $param 请求参数（query字符串或参数数组）
   * @return string 响应数据
   * @author Max.Yu <max@jipu.com>
   */
  function doit($action = '', $param = array()){
    //接口url地址
    //$base_url = 'http://yunpian.com/';
    $base_url = 'https://sms.yunpian.com/';
    //版本号
    $version = 'v1';
    //返回数据格式
    $format = 'json';
    //apikey
    $apikey = C('SMS_YUNPIAN.YUNPIAN_APIKEY');
    $data = '';
    if(empty($action)){
      return array('code' => '-998', 'msg' => '请求动作不能为空');
    }
    $url = $base_url.$version.'/'.$action.'.'.$format;
    if(is_array($param)){
      $param['apikey'] = $apikey;
      $param = http_build_query($param);
    }elseif(is_string($param)){
      $param.='&apikey='.$apikey;
    }
    $info = parse_url($url);
    $fp = fsockopen($info["host"], 80, $errno, $errstr, 30);
    if(!$fp){
      return array('code' => '-999', 'msg' => '短信服务器无法连接');
    }
    $head = "POST ".$info['path']." HTTP/1.0\r\n";
    $head.="Host: ".$info['host']."\r\n";
    $head.="Referer: http://".$info['host'].$info['path']."\r\n";
    $head.="Content-type: application/x-www-form-urlencoded\r\n";
    $head.="Content-Length: ".strlen(trim($param))."\r\n";
    $head.="\r\n";
    $head.=trim($param);
    $write = fputs($fp, $head);
    $header = "";
    while($str = trim(fgets($fp, 4096))){
      $header.=$str;
    }
    while(!feof($fp)){
      $data .= fgets($fp, 4096);
    }
    return json_decode($data, true);
  }

}
