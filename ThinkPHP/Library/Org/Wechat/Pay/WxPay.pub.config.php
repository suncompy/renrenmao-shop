<?php

/**
 * 	配置账号信息
 */
class WxPayConf_pub{

  //=======【基本信息设置】=====================================
  //微信公众号身份的唯一标识。审核通过后，在微信发送的邮件中查看
  public $APPID = '6666666';
  //受理商ID，身份标识
  public $MCHID = '18888887';
  //商户支付密钥Key。审核通过后，在微信发送的邮件中查看
  public $KEY = '48888888888888888888888888888886';
  //JSAPI接口中获取openid，审核后在公众平台开启开发模式后可查看
  public $APPSECRET = '48888888888888888888888888888887';
  //=======【JSAPI路径设置】===================================
  //获取access_token过程中的跳转uri，通过跳转将code传入jsapi支付页面
  public $JS_API_CALL_URL = 'http://www.xxxxxx.com/demo/js_api_call.php';
  //=======【证书路径设置】=====================================
  //证书路径,注意应该填写绝对路径
  public $SSLCERT_DIR = '/Application/Common/Conf/Cacert/Wechat/';
  public $SSLCERT_PATH = 'apiclient_cert.pem';
  public $SSLKEY_PATH = 'apiclient_key.pem';
  //=======【异步通知url设置】===================================
  //异步通知url，商户根据实际开发过程设定
  public $NOTIFY_URL = 'http://www.xxxxxx.com/demo/notify_url.php';
  //=======【curl超时设置】===================================
  //本例程通过curl使用HTTP POST方法，此处可修改其超时时间，默认为30秒
  public $CURL_TIMEOUT = 30;

  function __construct($trade_type){
    $wechatpay = C('WECHATPAY');
      if($trade_type == "APP"){
          $wechatpay = C('WECHATAPPPAY');
          $this->SSLCERT_PATH = dirname(THINK_PATH).$this->SSLCERT_DIR.'wechatapp_cert.pem';
          //sslkey_path
          $this->SSLKEY_PATH = dirname(THINK_PATH).$this->SSLCERT_DIR.'wechatapp_key.pem';
      }else{
           //sslcert_path
          $this->SSLCERT_PATH = dirname(THINK_PATH).$this->SSLCERT_DIR.'apiclient_cert.pem';
          //sslkey_path
          $this->SSLKEY_PATH = dirname(THINK_PATH).$this->SSLCERT_DIR.'apiclient_key.pem';
      }
    $this->APPID = $wechatpay['app_id'];
    $this->MCHID = $wechatpay['mch_id'];
    $this->KEY = $wechatpay['app_key'];
    $this->APPSECRET = $wechatpay['app_secret'];
  }

}

?>