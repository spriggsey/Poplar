<?php


namespace Poplar;


use Poplar\Support\Collection;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;

class Application {

    private static $base_path;
    public         $directory;
    private        $config;
    private        $whoops;

    public function __construct($directory) {
        error_reporting(E_ALL);
        ini_set('display_errors', TRUE);
        ini_set('display_startup_errors', TRUE);


        self::$base_path = $directory;
        // init up the request static class
        Request::init();
        // load the config into the environment
        $this->config = new Config();
        // load whoops
        $this->loadWhoops();
    }

    /**
     * @return bool
     */
    private function loadWhoops() {
        $whoops = new \Whoops\Run();
        // we need to have different handlers depending on web or API
        if (env('APP_ENV') === 'dev') {
            if (Request::isAJAX()) {
                $whoops->pushHandler(new JsonResponseHandler());
            } else {
                $whoops->pushHandler(new PrettyPageHandler());
            }
        } else {
            // give a generic page
            $whoops->pushHandler(function () {
                require view('error');
                die();
            });
        }
        $this->whoops = $whoops->register();

        return TRUE;
    }

    /**
     * @return mixed
     */
    public static function basePath() {
        return self::$base_path;
    }

    public function captureRequest() {

    }

    public function output() {
        // run the routing system here and return a view/data
    }
}
