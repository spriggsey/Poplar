<?php

use Poplar\Support\str;

if ( ! function_exists('is_assoc')) {
    function is_assoc($array) {
        $array = array_keys($array);

        return ($array !== array_keys($array));
    }
}
if ( ! function_exists('env')) {
    function env($key, $default = NULL) {
        $value = \Poplar\Config::env($key, $default);
        if ($value === FALSE) {
            return value($default);
        }
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return TRUE;
            case 'false':
            case '(false)':
                return FALSE;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return;
        }
        if (Str::startsWith($value, '"') && Str::endsWith($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
if ( ! function_exists('config')) {
    function config($key, $default = NULL) {
        return \Poplar\Config::get($key, $default);
    }
}
if ( ! function_exists('_e')) {
    function _e($e, $default = '') {
        if (isset($e) || $e || ! empty($e)) {
            return $e;
        } else {
            if ($default !== '') {
                return $default;
            } else {
                return NULL;
            }
        }
    }
}
if ( ! function_exists('base_path')) {
    function base_path() {
        return \Poplar\Application::basePath();
    }
}
if ( ! function_exists('dd')) {
    function dd() {
        array_map(function ($x) {
            if (is_array($x)) {
                // echo out a json object if we can
                header('Content-Type: application/json');
                echo json_encode($x);
            } elseif ($x instanceof \Illuminate\Support\Collection) {
                header('Content-Type: application/json');
                echo $x->toJson();
            } else {
                dump($x);
            }
        }, func_get_args());
        die;
    }
}
if ( ! function_exists('en')) {
    // small echo new line function
    function en($str, $n = 1) {
        echo $str;

        while ($n > 0) {
            echo "\n";
            $n--;
        }
    }
}

if ( ! function_exists('class_basename')) {
    function class_basename($path) {
        return basename($path);
    }
}

if ( ! function_exists('array_get')) {
    function array_get($array, $path) {
        return \Illuminate\Support\Arr::get($array, $path);
    }
}
if ( ! function_exists('view')) {
    /**
     * @param            $url - this will always point to views folder
     * @param bool|array $values
     *
     * @return array|string
     */
    function view($url, $values = FALSE) {
        // convert dot notation to slashes
        $url = str_replace('.', '/', $url);
        $url = trim($url, '/');
        if ($values) {
            return [\Poplar\Application::basePath() . "/resources/views/{$url}.view.php", $values];
        } else {
            return \Poplar\Application::basePath() . "/resources/views/{$url}.view.php";
        }
    }
}
if ( ! function_exists('collect')) {
    function collect($array) {
        return new \Poplar\Support\Collection($array);
    }
}
if ( ! function_exists('database')) {
    /**
     * @return \Poplar\Database\QueryBuilder
     */
    function database() {
        return \Poplar\Application::database();
    }
}
