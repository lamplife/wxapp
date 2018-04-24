<?php
/**
 * Created by PhpStorm.
 * User: 狂奔的螞蟻 <www.firstphp.com>
 * Date: 2013/3/29
 * Time: 上午10:00
 */
namespace Firstphp\Wxapp\Bridge;

use Barryvdh\Debugbar\Debugbar;

class MsgCrypt {

    protected $token;
    protected $encodingAesKey;
    protected $appId;

    const BLOCK_SIZE = 32;

    const OK = 0;
    const VALIDATE_SIGNATURE_ERROR = -40001;
    const PARSE_XML_ERROR = -40002;
    const COMPUTE_SIGNATURE_ERROR = -40003;
    const ILLEGAL_AES_KEY = -40004;
    const VALIDATE_APPID_ERROR = -40005;
    const ENCRYPT_AES_ERROR = -40006;
    const DECRYPT_AES_ERROR = -40007;
    const ILLEGAL_BUFFER = -40008;
    const ENCODE_BASE64_ERROR = -40009;
    const DECODE_BASE64_ERROR = -40010;
    const GEN_RETURN_XML_ERROR = -40011;

    /**
     * 构造函数
     *
     * @param $token 公众号第三方平台消息校验Token
     * @param $encodingAesKey  公众号第三方平台消息加解密Key
     * @param $appId  公众号第三方平台的appId
     */
    public function __construct($token, $encodingAesKey, $appId)
    {
        $this->token = $token;
        $this->encodingAesKey = $encodingAesKey;
        $this->appId = $appId;
    }

    /**
     * 消息加密
     *
     * @param $replyMsg
     * @param $timeStamp
     * @param $nonce
     */
    public function encryptMsg($replyMsg, $timeStamp = null, $nonce = null)
    {
        //加密
        $array = $this->encrypt($replyMsg, $this->appId);
        if ($array[0] != 0) {
            return $array;
        }
        $timeStamp = $timeStamp ? : time();
        $encrypt = $array[1];

        //生成安全签名
        $array = $this->_getSHA1($this->token, $timeStamp, $nonce, $encrypt);
        if ($array[0] != 0) {
            return $array;
        }
        $signature = $array[1];

        //生成发送的xml
        $encryptMsg = $this->_generateXml($encrypt, $signature, $timeStamp, $nonce);

        return [static::OK, $encryptMsg];
    }


    /**
     * 消息解密
     *
     * @param $msgSignature
     * @param $timeStamp
     * @param $nonce
     * @param $postData
     * @return array
     */
    public function decryptMsg($msgSignature, $timeStamp, $nonce, $postData)
    {
        if (strlen($this->encodingAesKey) != 43) {
            return [static::ILLEGAL_AES_KEY, null];
        }

        //提取密文
        $pc = new Prpcrypt($this->encodingAesKey);

        $array = $this->_extractXml($postData);

        if ($array[0] != 0) {
            return $array;
        }

        $timeStamp = $timeStamp ? : time();

        $encrypt = $array[1];

        //验证安全签名
        $array = $this->_getSHA1($this->token, $timeStamp, $nonce, $encrypt);

        if ($array[0] != 0) {
            return $array;
        }

        $signature = $array[1];
        if ($signature != $msgSignature) {
            return [static::VALIDATE_SIGNATURE_ERROR, null];
        }

        $result = $pc->decrypt($encrypt, $this->appId);
        if ($result[0] != 0) {
            return $result;
        }

        return [static::OK, $result[1]];
    }


    /**
     * 明文加密
     *
     * @param $text
     * @param $appid
     * @return array
     */
    private function _encrypt($text, $appid)
    {
        try {
            //获得16位随机字符串，填充到明文之前
            $random = $this->_getRandomStr();
            $text = $random . pack("N", strlen($text)) . $text . $appid;

            // 网络字节序
            $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->encodingAesKey, 0, 16);

            //使用自定义的填充方式对明文进行补位填充
            $text = $this->_encode($text);
            mcrypt_generic_init($module, base64_decode($this->encodingAesKey . "="), $iv);

            //加密
            $encrypted = mcrypt_generic($module, $text);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module);

            //使用BASE64对加密后的字符串进行编码
            return [static::OK, base64_encode($encrypted)];
        } catch (Exception $e) {
            return [static::ENCRYPT_AES_ERROR, null];
        }
    }


    /**
     * 明文解密
     *
     * @param $encrypted
     * @param $appid
     * @return array
     */
    private function _decrypt($encrypted, $appid)
    {
        try {
            //使用BASE64对需要解密的字符串进行解码
            $ciphertext_dec = base64_decode($encrypted);
            $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->encodingAesKey, 0, 16);
            mcrypt_generic_init($module, base64_decode($this->encodingAesKey . "="), $iv);

            //解密
            $decrypted = mdecrypt_generic($module, $ciphertext_dec);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module);
        } catch (Exception $e) {
            return [static::DECRYPT_AES_ERROR, null];
        }

        try {
            //去除补位字符
            $result = $this->_decode($decrypted);
            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16)
                return "";
            $content = substr($result, 16, strlen($result));
            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
            $from_appid = substr($content, $xml_len + 4);
        } catch (Exception $e) {
            return [static::ILLEGAL_BUFFER, null];
        }
        if ($from_appid != $appid)
            return [static::VALIDATE_APPID_ERROR, null];
        return [0, $xml_content];
    }



    /**
     * 对明文进行加密  PHP7.1支持 http://www.thinkphp.cn/code/3141.html
     *
     * @param string $text 需要加密的明文
     * @return string 加密后的密文
     */
    public function encrypt($text, $appid) {
        try {
            //获得16位随机字符串，填充到明文之前
            $random = $this->_getRandomStr();
            $text = $random . pack("N", strlen($text)) . $text . $appid;
            $iv = substr($this->encodingAesKey, 0, 16);
            $pkc_encoder = new PKCS7Encoder();
            $text = $pkc_encoder->encode($text);
            $encrypted = openssl_encrypt($text,'AES-256-CBC',substr($this->encodingAesKey, 0, 32),OPENSSL_ZERO_PADDING,$iv);
            return array(ErrorCode::$OK, $encrypted);
//            return [static::OK, base64_encode($encrypted)];
        } catch (Exception $e) {
            return array(ErrorCode::$EncryptAESError, null);
//            return [static::ENCRYPT_AES_ERROR, null];
        }
    }



    /**
     * 对密文进行解密
     * @param string $encrypted 需要解密的密文
     * @return string 解密得到的明文
     */
    public function decrypt($encrypted, $appid) {
        try {
            $iv = substr($this->encodingAesKey, 0, 16);

            $decrypted = openssl_decrypt($encrypted,'AES-256-CBC', substr($this->encodingAesKey, 0, 32), OPENSSL_ZERO_PADDING, $iv);

        } catch (Exception $e) {
            return array(ErrorCode::$DecryptAESError, null);
        }


        try {
            //去除补位字符
            $pkc_encoder = new PKCS7Encoder();
            $result = $pkc_encoder->decode($decrypted);

            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16)
                return "";
            $content = substr($result, 16, strlen($result));
            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
            $from_appid = substr($content, $xml_len + 4);

            if (!$appid)
                $appid = $from_appid;
            //如果传入的appid是空的，则认为是订阅号，使用数据中提取出来的appid
        } catch (Exception $e) {
            //print $e;
            return array(ErrorCode::$IllegalBuffer, null);
        }


        if ($from_appid != $appid)
            return array(ErrorCode::$ValidateAppidError, null);
        //不注释上边两行，避免传入appid是错误的情况


        return array(0, $xml_content, $from_appid);
        //增加appid，为了解决后面加密回复消息的时候没有appid的订阅号会无法回复

    }




    /**
     * 加密明文补位填充
     *
     * @param $text
     * @return string
     */
    private function _encode($text)
    {
        //计算需要填充的位数
        $amount_to_pad = static::BLOCK_SIZE - (strlen($text) % static::BLOCK_SIZE);
        $amount_to_pad = $amount_to_pad ? : static::BLOCK_SIZE;

        //获得补位所用的字符
        $pad_chr = chr($amount_to_pad);
        $tmp = '';
        for ($index = 0; $index < $amount_to_pad; $index++) {
            $tmp .= $pad_chr;
        }
        return $text . $tmp;
    }


    /**
     * 解密明文补位删除
     *
     * @param $text
     * @return string
     */
    private function _decode($text)
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            $pad = 0;
        }
        return substr($text, 0, (strlen($text) - $pad));
    }


    /**
     * 对解密后的明文进行补位删除
     *
     * @return array
     */
    private function _getSHA1()
    {
        try {
            $array = func_get_args();
            sort($array, SORT_STRING);
            return [static::OK, sha1(implode($array))];
        } catch (Exception $e) {
            return [static::COMPUTE_SIGNATURE_ERROR, null];
        }
    }


    private function _getRandomStr()
    {
//        return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz'), 0, 16);
        $str = "";
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < 16; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }
        return $str;
    }


    /**
     * 提取出xml数据包中的加密消息
     * @param string $xmltext 待提取的xml字符串
     * @return string 提取出的加密消息字符串
     */
    public function _extractXml($xmltext)
    {
        try {
            $xml = new \DOMDocument();
            $xml->loadXML($xmltext);
            $array_e = $xml->getElementsByTagName('Encrypt');
            $encrypt = $array_e->item(0)->nodeValue;
            return [static::OK, $encrypt];
        } catch (Exception $e) {
            return [static::PARSE_XML_ERROR, null];
        }
    }


    /**
     * 生成xml消息
     * @param string $encrypt 加密后的消息密文
     * @param string $signature 安全签名
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     */
    private function _generateXml($encrypt, $signature, $timestamp, $nonce)
    {
        $format = "<xml>
        <Encrypt><![CDATA[%s]]></Encrypt>
        <MsgSignature><![CDATA[%s]]></MsgSignature>
        <TimeStamp>%s</TimeStamp>
        <Nonce><![CDATA[%s]]></Nonce>
        </xml>";
        return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
    }

}