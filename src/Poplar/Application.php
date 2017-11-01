<?php


namespace Poplar;

use Poplar\Routing\Router;
use Whoops\Handler\JsonResponseHandler as WhoopsJson;
use Whoops\Handler\PrettyPageHandler as WhoopsPretty;
use Whoops\Run as Whoops;

class Application {

    private static $base_path;
    public         $directory;
    private        $config;
    private        $whoops;

    public function __construct($directory) {
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
        $whoops = new Whoops();
        // we need to have different handlers depending on web or API
        if (env('APP_ENV') === 'dev') {
            if (Request::isAJAX()) {
                $whoops->pushHandler(new WhoopsJson());
            } else {
                $whoops->pushHandler(new WhoopsPretty());
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
        try {
            $router = Router::load('main.routes.php')->pathFind();

            if ( ! View::process($router)) {
                throw new \Exception('View Processing failed');
            }
            require View::getViewFile();
        } catch (\Exception $e) {
            http_response_code(404);
            if (Config::get('app.debug_mode')) {
                dd($e);
            }
            include view('404');
        }

    }
}
