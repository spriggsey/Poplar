<?php


namespace Poplar;


class Input {
    static private $data     = [];
    static private $fileData = [];

    static private $oldData = [];

    /**
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public static function get($name, $default = '') {
        if (array_key_exists($name, self::$data)) {
            return self::$data[$name];
        }
        // check the old data after so it doesn't override new stuff
        if (array_key_exists($name, self::$oldData)) {
            return self::$oldData[$name];
        }
        if ( ! empty($default)) {
            return $default;
        }

        return NULL;
    }

    public static function unsetCSRF() {
        unset(self::$data['X-CSRF-TOKEN']);
    }

    public static function unsetMethod() {
        unset(self::$data['_method']);
    }


    /**
     * Return old data first then merge in the new to ensure the old data
     * does not overwrite the new stuff
     *
     * @return object
     */
    public static function all() {
        $object = self::$oldData;
        $object = array_merge($object, self::$data);

        return (object)$object;
    }

    /**
     * Return only a set of inputs instead of all
     *
     * @param $name
     *
     * @return object
     */
    public static function only($name) {
        $names  = func_get_args();
        $object = [];
        foreach ($names as $name) {
            $object[$name] = self::$data[$name];
        }

        return (object)$object;
    }

    /**
     * Return everything but the data in the arguments.
     *
     * @param $name
     *
     * @return object
     */
    public static function except($name) {
        $names  = func_get_args();
        $object = self::$data;
        foreach ($names as $name) {
            unset($object[$name]);
        }

        return (object)$object;
    }

    public static function processData() {
        // first look for and post data via json string input but only if request is empty
        if ( ! empty(json_decode(file_get_contents('php://input'), TRUE))) {
            foreach (json_decode(file_get_contents('php://input'), TRUE) as $key => $val) {
                $_REQUEST[$key] = $val;
            }
        }

        // on the live system we may need to trim off the first request if it is set
        foreach ($_REQUEST as $key => $val) {
            self::setData(str_replace(Request::uri(), '', $key), $val);
        }
        foreach ($_FILES as $name => $file) {
            self::setFile($name, $file);
        }

        // retrieve any old data from the last view if any
        if ( ! empty($_SESSION['flashData'])) {
            foreach ($_SESSION['flashData'] as $key => $val) {
                self::$oldData[$key] = $val;
            }
            unset($_SESSION['flashData']);
        }
    }

    public static function setData($name, $val) {
        if (empty($name)) {
            return TRUE;
        }
        self::$data[$name] = $val;
    }

    private static function setFile($name, $file) {
        self::$fileData[$name] = $file;
    }

    public static function flash() {
        foreach (self::$data as $key => $val) {
            $_SESSION['flashData'][$key] = $val;
        }
    }

    public static function flashOnly($name) {
        $names = func_get_args();
        foreach ($names as $name) {
            // check it lives in data
            if (isset(self::$data[$name])) {
                $_SESSION['flashData'][$name] = self::$data[$name];
            } elseif (isset(self::$oldData[$name])) {
                // flash old data if there is no new
                $_SESSION['flashData'][$name] = self::$oldData[$name];
            }
        }
    }

    public static function flashExcept($name) {
        $names      = func_get_args();
        $diff_names = array_diff(array_keys(self::$data), $names);
        foreach ($diff_names as $name) {
            $_SESSION['flashData'][$name] = self::$data[$name];
        }
    }

    public static function previousPage() {
        return (isset($_SESSION['previous_page'])) ? $_SESSION['previous_page'] : NULL;
    }

    public static function storePreviousPage() {
        $_SESSION['previous_page'] = Request::uri();
    }
}