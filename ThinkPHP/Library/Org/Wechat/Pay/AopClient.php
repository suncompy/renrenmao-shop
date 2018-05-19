<?php


class AopClient {
    //应用ID
    public $appId;

    //私钥文件路径
    public $rsaPrivateKeyFilePath;

    //私钥值
    public $rsaPrivateKey;

    //网关
    public $gatewayUrl = "https://openapi.alipay.com/gateway.do";
    //返回数据格式
    public $format = "json";
    //api版本
    public $apiVersion = "1.0";

    // 表单提交字符集编码
    public $postCharset = "UTF-8";

    //使用文件读取文件格式，请只传递该值
    public $alipayPublicKey = null;

    //使用读取字符串格式，请只传递该值
    public $alipayrsaPublicKey;


    public $debugInfo = false;

    private $fileCharset = "UTF-8";

    private $RESPONSE_SUFFIX = "_response";

    private $ERROR_RESPONSE = "error_response";

    private $SIGN_NODE_NAME = "sign";


    //加密XML节点名称
    private $ENCRYPT_XML_NODE_NAME = "response_encrypted";

    private $needEncrypt = false;


    //签名类型
    public $signType = "RSA";


    //加密密钥和类型

    public $encryptKey;

    public $encryptType = "AES";

    protected $alipaySdkVersion = "alipay-sdk-php-20161101";

    public function getSignContent($params) {
        ksort($params);
        reset($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {

                // 转换成目标字符集
                $v = $this->characet($v, $this->postCharset);

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }


    //此方法对value做urlencode
    public function getSignContentUrlencode($params) {
        ksort($params);
        reset($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {

                // 转换成目标字符集
                $v = $this->characet($v, $this->postCharset);

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . urlencode($v);
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . urlencode($v);
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }

    /**
     * RSA签名
     * @param $data 待签名数据
     * @param $private_key_path 商户私钥文件路径
     * return 签名结果
     */
    public function alonersaSign($data, $private_key_path) {
        if(file_exists($private_key_path)){
            $priKey = file_get_contents($private_key_path);
        }else{
            $priKey = $private_key_path;
        }
        $res = openssl_get_privatekey($priKey);
        openssl_sign($data, $sign, $res);
        openssl_free_key($res);
        //base64编码
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function characet($data, $targetCharset) {

        if (!empty($data)) {
            $fileType = $this->fileCharset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //				$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }


        return $data;
    }


    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }
}