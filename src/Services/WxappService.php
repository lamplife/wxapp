<?php
/**
 * Created by PhpStorm.
 * User: 狂奔的螞蟻 <www.firstphp.com>
 * Date: 2018/3/29
 * Time: 上午10:00
 */

namespace Firstphp\Wxapp\Services;

use Firstphp\Wxapp\Bridge\Http;

class WxappService
{

    const OK = 0;
    const ILLEGAL_AES_KEY = -40001;
    const ILLEGAL_IV = -40002;
    const ILLEGAL_BUFFER = -40003;
    const DECODE_BASE64_ERROR = -40004;

    protected $appId;
    protected $appSecret;
    protected $client;

    public function __construct($appId = '', $appSecret = '')
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->http = new Http();
    }


    /**
     * 登录凭证校验
     */
    public function authLogin($code = '')
    {
        return $this->http->post('sns/jscode2session', [
            'form_params' => [
                'appid' => $this->appId,
                'secret' => $this->appSecret,
                'js_code' => $code,
                'grant_type' => 'authorization_code'
            ]
        ]);

    }


    /**
     * 获取access_token
     */
    public function getAccessToken()
    {
        return $this->http->post('cgi-bin/token', [
            'form_params' => [
                'appid' => $this->appId,
                'secret' => $this->appSecret,
                'grant_type' => 'client_credential'
            ]
        ]);
    }



    /**
     * 生成小程序二维码
     */
    public function getwxacodeunlimit($args = [], $accessToken = '') {
        $params = [
            'scene' => isset($args['scene']) ? $args['scene'] : "",
            'path' => isset($args['path']) ? $args['path'] : "/",
            'width' => isset($args['width']) ? $args['width'] : 430,
            'is_hyaline' => isset($args['is_hyaline']) ? $args['is_hyaline'] : false,
        ];
        $res = $this->httpPostJson('https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.$accessToken, json_encode($params));
        $decodeRes = json_decode($res[1], true);
        if (isset($decodeRes['errcode'])) {
            return ['code' => $decodeRes['errcode'], 'msg' =>$decodeRes['errmsg']];
        } else {
            return ['code' => 200, 'data' => $res[1]];
        }
    }


    /**
     * 发送POST请求
     */
    private function httpPostJson($url, $jsonStr)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            )
        );
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array($httpCode, $response);
    }


    /**
     * 检验数据的真实性，并且获取解密后的明文.
     *
     * @param $encryptedData string 加密的用户数据
     * @param $iv string 与用户数据一同返回的初始向量
     * @param $data string 解密后的原文
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function decryptData($encryptedData = '', $iv = '', $sessionKey = '')
    {
        if (strlen($sessionKey) != 24) {
            return ['code' => self::ILLEGAL_AES_KEY, 'msg' => 'sessionKey is error'];
        }
        $aesKey = base64_decode($sessionKey);
        if (strlen($iv) != 24) {
            return ['code' => self::ILLEGAL_IV, 'msg' => 'iv is error'];
        }
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encryptedData);
        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        $data = json_decode($result);

        if ($data == NULL) {
            return ['code' => self::ILLEGAL_BUFFER, 'msg' => 'buffer is error'];
        }

        if ($data->watermark->appid != $this->appId) {
            return ['code' => self::ILLEGAL_BUFFER, 'msg' => 'Hacking Attempt'];
        }
        return ['code' => self::OK, 'msg' => 'success', 'data' => $data];
    }


    /**
     * 获取签名
     *
     * @param array $params
     * @param string $key
     * @return string
     */
    public function makeSign(array $params, string $key)
    {
        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }

        $str .= 'key=' . $key;
        $sign = strtoupper(md5($str));
        return $sign;
    }



    /**
     * 统一下单
     *
     * @param array $orderData
     * @param int $second
     * @return int|mixed
     */
    public function unifiedorder(array $orderData, int $second = 30) {
        $xmlData = $this->dataToXml($orderData);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch,CURLOPT_URL, $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder');
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            return $error;
        }
    }


    /**
     * 企业付款到零钱
     *
     * @param $params 数据
     * @param $apiclientCert
     * @param $apiclientKey
     * @param int $second 执行时间
     * @param array $aHeader
     * @return bool|mixed
     */
    function promotionTransfers($params, $apiclientCert, $apiclientKey, $second = 30, $aHeader = array()){
        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        $xmldata = $this->dataToXml($params);
        $ch = curl_init();//初始化curl

        curl_setopt($ch, CURLOPT_TIMEOUT, $second);//设置执行最长秒数
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');//证书类型
        curl_setopt($ch, CURLOPT_SSLCERT, $apiclientCert);//证书位置
        curl_setopt($ch, CURLOPT_SSLKEY, $apiclientKey);//证书位置
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');//CURLOPT_SSLKEY中规定的私钥的加密类型
        curl_setopt($ch, CURLOPT_CAINFO, 'PEM');
//        curl_setopt($ch, CURLOPT_CAINFO, $isdir . 'rootca.pem');
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);//设置头部
        }
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmldata);//全部数据使用HTTP协议中的"POST"操作来发送


        $data = curl_exec($ch);//执行回话

        if ($data) {
            curl_close($ch);
            return $this->fromXml($data);
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }


    /**
     * 申请退款
     *
     * @param array $params
     * @param int $second
     * @return int|mixed
     */
    public function apyRefund(array $params, $apiclientCert, $apiclientKey, $second = 30, $aHeader = array()) {
        $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
        $xmldata = $this->dataToXml($params);
        $ch = curl_init();//初始化curl

        curl_setopt($ch, CURLOPT_TIMEOUT, $second);//设置执行最长秒数
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');//证书类型
        curl_setopt($ch, CURLOPT_SSLCERT, $apiclientCert);//证书位置
        curl_setopt($ch, CURLOPT_SSLKEY, $apiclientKey);//证书位置
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');//CURLOPT_SSLKEY中规定的私钥的加密类型
        curl_setopt($ch, CURLOPT_CAINFO, 'PEM');
//        curl_setopt($ch, CURLOPT_CAINFO, $isdir . 'rootca.pem');
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);//设置头部
        }
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmldata);//全部数据使用HTTP协议中的"POST"操作来发送


        $data = curl_exec($ch);//执行回话

        if ($data) {
            curl_close($ch);
            return $this->fromXml($data);
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }


    /**
     * 发送订阅消息
     *
     * @param string $accessToken
     * @param array $params
     * @return mixed
     */
    public function subscribeMessage($accessToken = '', $params = []) {
        return $this->http->post('cgi-bin/message/subscribe/send?access_token='.$accessToken, [
            'json' => [
                'touser' => $params['touser'],
                'template_id' => $params['template_id'],
                'page' => $params['page'],
                'data' => $params['data']
            ]
        ]);
    }


    /**
     * 创建被分享动态消息的 activity_id
     *
     * @param string $accessToken
     * @return mixed
     */
    public function wxopenActivityIdCreate($accessToken = '') {
        return $this->http->get('cgi-bin/message/wxopen/activityid/create?access_token='.$accessToken, [
            'form_params' => []
        ]);
    }



    /**
     * @param string $xml
     * @return mixed
     */
    public function fromXml(string $xml) {
        libxml_disable_entity_loader(true);
        $this->values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $this->values;
    }


    /**
     * dataToXml
     *
     * @param array $data
     * @return string
     */
    public function dataToXml(array $data) {
        if ($data) {
            $xml = "<xml>";
            foreach ($data as $key => $val) {
                if (is_numeric($val)){
                    $xml.="<".$key.">".$val."</".$key.">";
                }else{
                    $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
                }
            }
            $xml.="</xml>";
            return $xml;
        }
    }

}