<?php
/**
 * Created by PhpStorm.
 * User: 狂奔的螞蟻 <www.firstphp.com>
 * Date: 2013/3/29
 * Time: 上午10:00
 */

namespace Firstphp\Wxapp\Services;

use Firstphp\Wxapp\Bridge\Http;

class WxappService {

    const OK = 0;
    const ILLEGAL_AES_KEY = -40001;
    const ILLEGAL_IV = -40002;
    const ILLEGAL_BUFFER = -40003;
    const DECODE_BASE64_ERROR = -40004;


    protected $appId;
    protected $appSecret;
    protected $appKey;

    protected $client;

    public function __construct($appId='', $appSecret='', $appKey='')
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->appKey = $appKey;

        $this->http = new Http();
    }



    /**
     * 登录凭证校验
     */
    public function authLogin($code = '') {
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
    public function getAccessToken() {
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
    public function getQrcode($path = '/', $accessToken = '') {
        $params = [
            'path' => $path,
            'width' => 430,
        ];
        $res = $this->httpPostJson('https://api.weixin.qq.com/wxa/getwxacode?access_token='.$accessToken, json_encode($params));
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
    private function httpPostJson($url, $jsonStr) {
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
    public function decryptData($encryptedData = '', $iv = '', $sessionKey = '') {
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


}