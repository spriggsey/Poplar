<?php


namespace Poplar;


use Poplar\Config;

class Application {

    private static $base_path;
    public         $directory;
    private        $config;

    public function __construct($directory) {
        self::$base_path = $directory;
        // load the config into the environment
        $this->config = new Config();

    }

    public function captureRequest() {
        return true;
    }


    public function output() {
        return true;
    }

    public static function basePath() {
        return self::$base_path;
    }
}