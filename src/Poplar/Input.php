<?php


namespace Poplar;


use Poplar\Support\Collection;

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
    public static function get($name, $default = ''): string {
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
     * @return Collection
     */
    public static function all(): Support\Collection {
        $object = self::$oldData;
        $object = array_merge($object, self::$data);

        return
        collect($object);
    }

    /**
     * Return only a set of inputs instead of all
     *
     * @param array $names
     *
     * @return Collection
     * @internal param $name
     *
     */
    public static function only(...$names): Support\Collection {
        $object = [];
        foreach ($names as $name) {
            $object[$name] = self::$data[$name];
        }

        return collect($object);
    }

    /**
     * Return everything but the data in the arguments.
     *
     * @param array $names
     *
     * @return Collection
     *
     */
    public static function except(...$names): Support\Collection {
        $object = self::$data;
        foreach ($names as $name) {
            unset($object[$name]);
        }

        return collect($object);
    }

    public static function processData() {
        // first look for and post data via json string input but only if request is empty
        if ( ! empty(json_decode(file_get_contents('php://input'), TRUE))) {
            foreach (json_decode(file_get_contents('php://input'), TRUE) as $key => $val) {
                $_REQUEST[$key] = $val;
            }
        }

        foreach ($_REQUEST as $key => $val) {
            self::setData($key, $val);
        }
        foreach ($_FILES as $name => $file) {
            self::setFile($name, $file);
        }

        // retrieve any old data from the last view if any
        if ( ! empty($_SESSION['flashData'])) {
            foreach ((array) $_SESSION['flashData'] as $key => $val) {
                self::$oldData[$key] = $val;
            }
            unset($_SESSION['flashData']);
        }
    }

    public static function setData($name, $val) {
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

    public static function flashOnly(...$names) {
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

    public static function flashExcept(...$names) {
        $diff_names = array_diff(array_keys(self::$data), $names);
        foreach ($diff_names as $name) {
            $_SESSION['flashData'][$name] = self::$data[$name];
        }
    }

    public static function storePreviousPage() {
        // first get the previous page from last time.
        Request::$previous_page = $_SESSION['previous_page'] ?? NULL;
        // then put a new previous page in from this session
        $_SESSION['previous_page'] = Request::getURI();
        return Request::$previous_page;
    }

    public static function flashErrorLog($validation_error_log): bool {
        if (empty($validation_error_log)) {
            return FALSE;
        }
        $_SESSION['validation_error_log'] = $validation_error_log;

        return TRUE;
    }

    public static function retrieveErrorLog(): bool {
        if (empty($_SESSION['validation_error_log'])) {
            return FALSE;
        }

        Application::bind('validation_errors', $_SESSION['validation_error_log']);
        unset($_SESSION['validation_error_log']);

        return TRUE;
    }
}

