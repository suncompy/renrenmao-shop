<?php
// .-----------------------------------------------------------------------------------
// | Version: 2017
// |-----------------------------------------------------------------------------------
// | Author: Fourth Pu <540690666@qq.com|ppssone@live.cn>
// |-----------------------------------------------------------------------------------
// | 百度地图
// .-----------------------------------------------------------------------------------
namespace Org\Baidu;

class BaiduMap{

  private $ak  = '2bmy982rYMg1GPvGFpBokV0C';
  private $url = 'http://api.map.baidu.com/geocoder/v2/';
  private $sk  = 'q3BYfi8BLTmbLGUeMPdYGVK0cayCBqF3';


  public function getmap($address){
    $url  = self::get_sn($address);
    $data = self::http($url);
    return $data ;
  }

  private function get_sn( $address = '成都'){
    $ak = $this->ak;
    $sk = $this->sk;
    //以Geocoding服务为例，地理编码的请求url，参数待填
    $url = "http://api.map.baidu.com/geocoder/v2/?address=%s&output=%s&ak=%s&sn=%s";
    $uri = '/geocoder/v2/';
    $output = 'json';
    $querystring_arrays = array (
      'address' => $address,
      'output' => $output,
      'ak' => $ak
    );  
    $sn = self::caculateAKSN($ak, $sk, $uri, $querystring_arrays);
    $target = sprintf($url, urlencode($address), $output, $ak, $sn);
    return $target;
  }
  
  function caculateAKSN($ak, $sk, $url, $querystring_arrays, $method = 'GET')
  {  
    if ($method === 'POST'){  
        ksort($querystring_arrays);  
    }  
    $querystring = http_build_query($querystring_arrays);  
    return md5(urlencode($url.'?'.$querystring.$sk));  
  }
    /**
   * 发送HTTP请求方法，目前只支持CURL发送请求
   * @param string $url    请求URL
   * @param array  $param  GET参数数组
   * @param array  $data   POST的数据，GET请求时该参数无效
   * @param string $method 请求方法GET/POST
   * @return array          响应数据
   */
  protected static function http($url, $param = '', $data = '', $method = 'GET'){
    $opts = array(
      CURLOPT_TIMEOUT => 30,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false,
    );
    if(!empty($param))$url.'?'.http_build_query($param);
    /* 根据请求类型设置特定参数 */
    $opts[CURLOPT_URL] = $url ;

    if(strtoupper($method) == 'POST'){
      $opts[CURLOPT_POST] = 1;
      $opts[CURLOPT_POSTFIELDS] = $data;

      if(is_string($data)){ //发送JSON数据
        $opts[CURLOPT_HTTPHEADER] = array(
          'Content-Type: application/json; charset=utf-8',
          'Content-Length: '.strlen($data),
        );
      }
    }

    /* 初始化并执行curl请求 */
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $data = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    //发生错误，抛出异常
    if($error)
      throw new \Exception('请求发生错误：'.$error);

    return $data;
  }

}
