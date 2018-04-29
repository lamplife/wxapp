# wxapp
微信小程序开发

安装扩展:

	composer require firstphp/wxapp:"2.3"


注册服务:

    Firstphp\Wxapp\Providers\WxappServiceProvider::class


发布配置:

	php artisan vendor:publish
	选择: [2 ] Provider: Firstphp\Wxapp\Providers\WxappServiceProvider


数据表迁移:

    php artisan migrate


编辑.env配置：

	COMPONENT_ID=1
	WECHAT_APPID=wxda93db123lafdu83d
	WECHAT_APPSECRET=87afeef9df90b74g4a8l9ca8d67b5742
	WECHAT_TOKEN=b5pxmw4bglFeh7Cd
	WECHAT_AES_KEY=mWm1DkAVBAZD2L5rs3QWKeoWa62wLumjqCXG9HifLdM


示例代码：

    use Firstphp\Wxapp\Facades\WxappFactory;

    ......

    $code = isset($this->params['code']) && $this->params['code'] ? trim($this->params['code']) : '';
    if (!$code) {
        return $this->responseJson(400, '参数错误');
    }

    $res = WxappFactory::authLogin($code);
    if (isset($res['errcode'])) {
        return $this->responseJson($res['errcode'], $res['errmsg']);
    }
