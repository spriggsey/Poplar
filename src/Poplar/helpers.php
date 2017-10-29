<?php
if ( ! function_exists('is_assoc')) {
    function is_assoc($array) {
        $array = array_keys($array);

        return ($array !== array_keys($array));
    }
}
if ( ! function_exists('env')) {
    function env($key, $default = NULL) {
        return \Poplar\Config::env($key, $default);
    }
}
if ( ! function_exists('config')) {
    function config($key, $default) {
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
            } else {
                echo '<pre>';
                var_dump($x);
                echo '</pre>';
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
if ( ! function_exists('camel_case')) {
    function camel_case($string) {
        // TODO: complete this
        return $string;
    }
}

if ( ! function_exists('class_basename')) {
    function class_basename($path) {
        return basename($path);
    }
}

if ( ! function_exists('array_get')) {
    function array_get($array, $path) {
        $path = explode('.', $path); //if needed
        $temp = &$array;
        foreach ($path as $key) {
            $temp = isset($temp[$key]) ? $temp[$key] : NULL;
        }
        return $temp;
    }
}