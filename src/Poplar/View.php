<?php


namespace Poplar;


use Poplar\Support\arr;

class View {
    public static  $view_data;
    private static $view_file;

    public static function process($route_data) {
        if (is_array($route_data)) {
            self::processArrayData($route_data[1]);
            // we have pushed the data to static var so we can replace it
            $route_data = $route_data[0];
        }
        self::setViewFile($route_data);

        // finally check if its a real file
        return is_file(self::getViewFile());
    }

    private static function processArrayData($data) {
        foreach ($data as $key => $val) {
            self::loadVar($key, $val);
        }
    }

    private static function setViewFile($view_file) {
        self::$view_file = $view_file;
    }

    /**
     * @return mixed
     */
    public static function getViewFile() {
        return self::$view_file;
    }

    /**
     * @param $key
     * @param $val
     */
    public static function loadVar($key, $val) {
        Arr::add(self::$view_data, $key, $val);
    }
}
