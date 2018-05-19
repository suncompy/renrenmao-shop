<?php

/**
 * 互亿短信平台业务类
 */

namespace Org\SMSHuyi;

class Sms{

    //接口类型：互亿无线触发短信接口，支持发送验证码短信、订单通知短信等。
    // 账户注册：请通过该地址开通账户http://sms.ihuyi.com/register.html
    // 注意事项：
    //（1）调试期间，请用默认的模板进行测试，默认模板详见接口文档；
    //（2）请使用APIID（查看APIID请登录用户中心->验证码短信->产品总览->APIID）及 APIkey来调用接口
    //（3）该代码仅供接入互亿无线短信接口参考使用，客户可根据实际需要自行编写；

    function send($param = array()){
        //短信接口地址
        $target = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";
        $mobile = $param['mobile'];
        $content = $param['text'];
        //测试
       // $mobile_code =  preg_match('/([0-9]{6})/',$content,$a) ? $a[1] : 0;
       // $content ="您的验证码是：".$mobile_code."。请不要把验证码泄露给其他人。";
        $apiid = C('SMS_YUNPIAN.HUYI_APIID');
        $apikey = C('SMS_YUNPIAN.HUYI_APIKEY');
        $post_data = "account=".$apiid."&password=".$apikey."&mobile=".$mobile."&content=".rawurlencode($content);
        $data = $this-> Post($post_data, $target);
        $gets = $this->xml_to_array($data);
        $res = '';
        if($gets['SubmitResult']['code'] == 2){
            $res['code'] = 0; //发送成功
        }else{
            $res['code'] = $gets['SubmitResult']['code']; //发送失败
        }
        $res['msg'] = $gets['SubmitResult']['msg'];
        return $res;
    }

    //请求数据到短信接口，检查环境是否开启curl init。
    function Post($curlPost,$url){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        $return_str = curl_exec($curl);
        curl_close($curl);
        return $return_str;
    }

    //将xml数据转换为数组格式。
    function xml_to_array($xml){
        $reg = "/<(\w+)[^>]*>([\\x00-\\xFF]*)<\\/\\1>/";
        if(preg_match_all($reg, $xml, $matches)){
            $count = count($matches[0]);
            for($i = 0; $i < $count; $i++){
                $subxml= $matches[2][$i];
                $key = $matches[1][$i];
                if(preg_match( $reg, $subxml )){
                    $arr[$key] = $this->xml_to_array($subxml);
                }else{
                    $arr[$key] = $subxml;
                }
            }
        }
        return $arr;
    }

    //根据模板id获取模板内容
    function getContent($tpl_id){
        $con = array(
            2 => '您的验证码是：#code#。如非本人操作，可不用理会！',
            5=>'感谢您注册#app#，您的验证码是#code#。',
            7 => '正在找回密码，您的验证码是#code#。',
            9=>'您的验证码是：#code#。如非本人操作，可不用理会！',
            1777792=>'您有新的待发货订单，请及时处理#key#。',
            1825078=>'您有新的发货订单，请及时处理#key#。',
            1825080=>'您的订单号为#key#的订单已经支付成功，如非本人操作，请忽略本短信',
        );
        return $con[$tpl_id];
    }

}


