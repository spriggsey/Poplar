<?php


namespace Poplar;


use Poplar\Auth\Session;
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
        $this->registerConfig();
        // load whoops
        $this->loadWhoops();
        // register the database class but we do not connect until required
        $this->registerDatabase();

        Application::bind('CSRF', Application::user() ? Session::get('db') : Session::get('local'));
    }

    /**
     * @return bool
     * @throws \InvalidArgumentException
     */
    private function loadWhoops(): bool {
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
    public static function database(): Database\QueryBuilder {
        return self::get('database')->connect();
    }

    public static function get($key) {
        if ( ! array_key_exists($key, static::$registry)) {
            throw new \RuntimeException("$key does not exist.");
        }

        return static::$registry[$key];
    }

    public static function getApplicationStorage() {
        return self::get('config');
    }

    public static function user() {
        try {
            return self::get('user');
        } catch (\Exception $e) {
            return FALSE;
        }
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
                throw new \RuntimeException('View Processing failed');
            }
            require View::getViewFile();
        } catch (\Exception $e) {
            http_response_code(404);
            if (Config::get('app.debug_mode')) {
                // throw it if we are in debug mode
                throw $e;
            }
            include view('errors.404');
        }

    }

    private function registerDatabase() {
        self::bind('database', new Database\Connection());
    }

    private function registerConfig() {
        $this->config = new Config();
        self::bind('config',$this->config);
    }
}
