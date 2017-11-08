<?php


namespace Poplar;


class Request {
    public static  $allowed_methods = ['GET', 'POST', 'PUT', 'DELETE'];
    public static  $previous_page;
    public static  $client_ip;
    public static  $server_ip;
    public static  $route;
    private static $uri;


    /**
     * Store all necessary info from the request
     */
    public static function init() {
        self::$uri           = self::getURI();
        self::$previous_page = Input::storePreviousPage();
        self::$client_ip = $_SERVER['REMOTE_ADDR'];
        self::$server_ip = $_SERVER['HTTP_HOST'];
    }

    private static function getURI() {
        // we need to check if it is using ORIG_PATH or PATH
        if ( ! empty($_SERVER['ORIG_PATH_INFO'])) {
            $_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];
        } elseif ( ! empty($_SERVER['REQUEST_URI'])) {
            $_SERVER['PATH_INFO'] = $_SERVER['REQUEST_URI'];
        }
        if ( ! isset($_SERVER['PATH_INFO'])) {
            return '';
        }
        $path = $_SERVER['PATH_INFO'];
        $pos  = strpos($path, '?');
        if ($pos !== FALSE) {
            $path = substr($path, 0, $pos);
        }
        $_SERVER['PATH_INFO'] = $path;

        return trim($_SERVER['PATH_INFO'], '/');
    }

    public static function uri() {
        return self::$uri;
    }


    /**
     * Extends upon the validation engine to give back errors depending on if ajax or browser requests
     * If you require basic validation engine functionality, do not use this. Use `Validator::make` instead.
     *
     * @param $validation_array
     *
     * @return bool
     */
    public function validate($validation_array) {
        $validator = new Validator(Input::all());
        if ( ! $validator->validate($validation_array)) {
            // we need to throw errors here depending on what type of connection it is (axios or browser)
            if (Request::isAjax()) {
                // we will send them a specific error code along with dumping the log
                http_response_code(422);
                dd($validator->validation_error_log);
            }
            // flash the input vals just incase they are used later
            Input::flashExcept('password', 'confirm_password');
            Input::flashErrorLog($validator->validation_error_log);
            // we most likely want to go back the the previous page where the values were sent from
            // this only works with browser based form posting, ajax should never do this
            header("Location: /" . Request::$previous_page);
            die();
        }

        return TRUE;
    }

    public static function method() {
        // browsers do not support other methods by default so we need to check if
        // those are coming in with hidden inputs with the name _method
        // at this time they can only be POSTed and not GETed to keep the URI clean
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['_method'])) {
                if (in_array(strtoupper($_POST['_method']), self::$allowed_methods)) {
                    return strtoupper($_POST['_method']);
                }
            }
        }

        return $_SERVER['REQUEST_METHOD'];
    }

    public static function queryString() {
        return $_SERVER['QUERY_STRING']??null;
    }

    public static function isAJAX() {
        return ( ! empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    }

}
