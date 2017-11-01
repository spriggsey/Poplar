<?php


namespace Poplar;


use Poplar\Exceptions\MiddlewareException;

abstract class Middleware {
    // these are the routes on which an instance of middleware needs to be called
    static $routes = [];

    public static function register($base_uri, $class) {
        $class_string = "App\\Middleware\\" . $class;
        // first check that this is a real middleware class
        if (class_exists($class_string)) {
            try {
                $object = new $class_string();
            } catch (\Error $e) {
                throw new MiddlewareException ($e);
            }
            static::$routes[trim($base_uri, '/')] = $object;
        }
    }

    /**
     * @param string $base_uri
     *
     * @return bool
     */
    public static function checkMiddleware($base_uri) {
        // we need to check the full URI at the base upwards, we ONLY check for a str_pos and position 0
        // even if there's a match, we need to ensure its at the right place.
        foreach (array_keys(static::$routes) as $uri) {
            if (strpos($base_uri, $uri) === 0) {
                // we then call the execute on the stored route
                // call this instance of middleware. if it redirects or dies, then there was an issue
                self::$routes[$uri]->execute($base_uri);
            }
        }

        // return true if middleware found no issues or there was no middleware needed
        return TRUE;
    }

    // this needs to be created on subclasses as this is the main function that will be tested for truth.
    // subclasses can use any number of other functions to produce the false/truth as long as they all use this function
    abstract public function execute($base_uri);

    // we require a function to return the use either to a authentication or authenticated area if required.
    // this could simply throw a 403 error or have a custom login page.
    abstract public function redirect($redirect_from);
}
