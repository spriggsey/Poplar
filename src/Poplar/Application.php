<?php


namespace Poplar;

use Poplar\Database\Connection;
use Poplar\Database\oldQueryBuilder;
use Poplar\Database\QueryBuilder;
use Poplar\Routing\Router;
use Whoops\Handler\JsonResponseHandler as WhoopsJson;
use Whoops\Handler\PrettyPageHandler as WhoopsPretty;
use Whoops\Run as Whoops;

class Application {

    protected static $registry = [];
    private static   $base_path;
    public           $directory;
    private          $config;
    private          $whoops;

    public function __construct($directory) {
        self::$base_path = $directory;
        // init up the request static class
        Request::init();
        // load the config into the environment
        $this->config = new Config();
        // load whoops
        $this->loadWhoops();
        // register the database class but we do not connect until required
        $this->registerDatabase();

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
                require view('errors.404');
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

    /**
     * grabs the database class and returns the live connection
     *
     * @return QueryBuilder
     */
    public static function database() {
        return self::get('database')->connect();
    }

    public static function get($key) {
        if ( ! array_key_exists($key, static::$registry)) {
            throw new \Exception("$key does not exist.");
        }

        return static::$registry[$key];
    }

    public static function user() {
        return self::get('user');
    }

    public static function bind($key, $value) {
        static::$registry[$key] = $value;

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
            include view('errors.404');
        }

    }

    private function registerDatabase() {
        Application::bind('database', new Database\Connection());
    }
}
