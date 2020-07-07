<?php
namespace Firstphp\Wxapp\Providers;

use Illuminate\Support\ServiceProvider;
use Firstphp\Wxapp\Services\WxappService;
use Illuminate\Support\Facades\Config;

class WxappServiceProvider extends ServiceProvider
{

    protected $defer = false;

    protected $migrations = [
        'CreateWxappConf' => '2018_04_23_174241_create_wxapp_conf_table',
    ];


    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../Config/wxapp.php' => config_path('wxapp.php')
        ], 'config');

        $this->publishes([
            __DIR__.'/../migrations/' => database_path('migrations')
        ], 'migrations');
    }


    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('WxappService', function () {
            $config = Config::get('wxapp');
            return new WxappService($config['appid'], $config['appsecret']);
        });
    }


    /**
     * Publish migration files.
     *
     * @return void
     */
    protected function migration()
    {
        foreach ($this->migrations as $class => $file) {
            if (! class_exists($class)) {
                $this->publishMigration($file);
            }
        }
    }


    /**
     * Publish a single migration file.
     *
     * @param string $filename
     * @return void
     */
    protected function publishMigration($filename)
    {
        $extension = '.php';
        $filename = trim($filename, $extension).$extension;
        $stub = __DIR__.'/../migrations/'.$filename;
        $target = $this->getMigrationFilepath($filename);
        $this->publishes([$stub => $target], 'migrations');
    }


    /**
     * Get the migration file path.
     *
     * @param string $filename
     * @return string
     */
    protected function getMigrationFilepath($filename)
    {
        if (function_exists('database_path')) {
            return database_path('/migrations/'.$filename);
        }
        return base_path('/database/migrations/'.$filename); // @codeCoverageIgnore
    }

}
