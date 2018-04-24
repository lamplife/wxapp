<?php

namespace Firstphp\Wxapp\Facades;

use Illuminate\Support\Facades\Facade;

class WxappFactory extends Facade
{

    protected static function getFacadeAccessor()
    {
        return 'WxappService';
    }

}

