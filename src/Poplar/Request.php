<?php


namespace Poplar;


use Poplar\Auth\Session;
use Poplar\Support\Str;

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
        self::$client_ip     = $_SERVER['REMOTE_ADDR'];
        self::$server_ip     = $_SERVER['HTTP_HOST'];
        // grab inputs
        Input::processData();
    }

    /**
     * @return string
     */
    public static function getURI(): string {
        if (NULL !== self::$uri) {
            return self::$uri;
        }
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

    public static function method() {
        // browsers do not support other methods by default so we need to check if
        // those are coming in with hidden inputs with the name _method
        // at this time they can only be POSTed and not GETed to keep the URI clean
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['_method']) && \in_array(strtoupper($_POST['_method']), self::$allowed_methods, TRUE)) {
                return strtoupper($_POST['_method']);
            }
        }

        return $_SERVER['REQUEST_METHOD'];
    }

    public static function queryString() {
        return $_SERVER['QUERY_STRING'] ?? NULL;
    }

    /**
     * @return bool
     */
    public static function isWeb() {
        if (self::isAJAX()) {
            return FALSE;
        }
        if ( ! isset($_SERVER['HTTP_USER_AGENT'])) {
            return FALSE;
        }
        // if the request allows text/html then we can assume its a browser request
        if (!Str::contains($_SERVER['HTTP_ACCEPT'],'text/html')) {
            return FALSE;
        }
        // if the request is asking for json, we can easily assume that it isnt a browser
        if (!Str::contains($_SERVER['CONTENT_TYPE'],'application/json')) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Extends upon the validation engine to give back errors depending on if ajax or browser requests
     * If you require basic validation engine functionality, do not use this. Use `Validator::make` instead.
     *
     * @param $validation_array
     *
     * @return bool
     */
    public function validate($validation_array): bool {
        $validator = new Validator(Input::all());
        if ( ! $validator->validate($validation_array)) {
            // we need to throw errors here depending on what type of connection it is (axios or browser)
            if (self::isAJAX()) {
                // we will send them a specific error code along with dumping the log
                http_response_code(422);
                dd($validator->validation_error_log);
            }
            // flash the input vals just incase they are used later
            Input::flashExcept('password', 'confirm_password');
            Input::flashErrorLog($validator->validation_error_log);
            // we most likely want to go back the the previous page where the values were sent from
            // this only works with browser based form posting, ajax should never do this
            header('Location: /' . self::$previous_page);
            die();
        }

        return TRUE;
    }

    public static function isAJAX(): bool {

        if ( ! empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            return TRUE;
        }

        try {
            return Application::get('isAPI');
        } catch (\RuntimeException $e) {
            // continue here as its not set and we need to try other things
        }

        return FALSE;
    }

}
