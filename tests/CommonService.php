<?php
/**
 * Author: 狂奔的螞蟻 <www.firstphp.com>
 * Date: 2018/4/4
 * Time: 上午11:05
 */

namespace App\Services;

use App\Models\WxappConf;
use Carbon\Carbon;
use Firstphp\Wxapp\Facades\WxappFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;


class CommonService {



    /**
     * 获取access_token
     *
     * @author 狂奔的螞蟻 <www.firstphp.com>
     */
    public static function getAccessToken() {
        $config = Config::get('wxapp');
        $info = WxappConf::where('app_id', $config['appid'])->first();
        if (!$info) {
            $res = WxappFactory::getAccessToken();
            $accessToken = $expiresIn = '';
            if (isset($res['errcode'])) {
                return ['code' => $res['errcode'], 'msg' => $res['errmsg']];
            } else {
                $accessToken = $res['access_token'];
                $expiresIn = time() + $res['expires_in'] - 240;
            }
            $data = [
                'name' => '名片小程序',
                'app_id' => $config['appid'],
                'app_secret' => $config['appsecret'],
                'access_token' => $accessToken,
                'expires_in' => date('Y-m-d H:i:s', $expiresIn),
            ];
            DB::beginTransaction();
            try {
                WxappConf::create($data);
                DB::commit();
                return ['code' => 200, 'msg' => 'success', 'data' => $accessToken];
            }catch(\Exception $e){
                DB::rollback();
                return ['code' => 400, 'msg' => 'access_token 更新失败'];
            }
        } else {
            if (empty($info['access_token']) || $info['expires_in'] <= Carbon::now()) {
                $res = WxappFactory::getAccessToken();
                $accessToken = $expiresIn = '';
                if (isset($res['errcode'])) {
                    return ['code' => $res['errcode'], 'msg' => $res['errmsg']];
                } else {
                    $accessToken = $res['access_token'];
                    $expiresIn = time() + $res['expires_in'] - 240;
                }

                $data = [
                    'access_token' => $accessToken,
                    'expires_in' => date('Y-m-d H:i:s', $expiresIn),
                ];
                WxappConf::where('app_id', $config['appid'])->update($data);
            } else {
                $accessToken = $info['access_token'];
            }

            return ['code' => 200, 'msg' => 'success', 'data' => $accessToken];

        }
    }



}