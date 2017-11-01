<?php


namespace Poplar;

use Dotenv\Dotenv;
use Dotenv\Exception\ValidationException;
use Poplar\Exceptions\ConfigException;

class Config {

    private static $config_storage;
    private static $environment_variables;
    private static $env_required_vars = [
        'APP_NAME',
        'APP_ENV',
    ];
    private        $config_dir = '';
    private        $config_files;

    public function __construct() {
        $this->config_dir   = base_path().'/config';
        $this->storeEnvironmentVariables(base_path().'/');
        $this->loadConfigurationFiles();
    }

    public function storeConfig(string $file_path) {
        $file_contents = include $file_path;
        $filename      = basename($file_path, '.php');
        foreach ($file_contents as $setting => $value) {
            self::set($filename, $setting, $value);
        }
    }

    public static function set($base, $key, $value) {
        self::$config_storage[$base][$key] = $value;
    }

    public static function get(string $path,$default='') {
        return array_get(self::$config_storage, $path)??$default;
    }

    public static function env(string $key, $default='') {
        return self::$environment_variables[$key] ?? $default;
    }

    private function storeEnvironmentVariables($environment_path) {
        $dotenv = new Dotenv($environment_path);
        $environment_variables = $dotenv->load();
        try {
            $dotenv->required(self::$env_required_vars);
        } catch (ValidationException $e) {
            throw new ConfigException();
        }
        // we need to explode them out and store them
        array_map(function ($environment_var) {
            $exploded_var                                  = explode('=', $environment_var);
            self::$environment_variables[$exploded_var[0]] = $exploded_var[1];
        }, $environment_variables);
    }

    private function loadConfigurationFiles() {
        $this->config_files = glob($this->config_dir . "/*.php");

        foreach ($this->config_files as $config_file) {
            $this->storeConfig($config_file);
        }
    }
}
