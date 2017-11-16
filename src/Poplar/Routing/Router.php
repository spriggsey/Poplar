<?php


namespace Poplar\Routing;


use Poplar\Application;
use Poplar\Exceptions\ModelException;
use Poplar\Exceptions\RouterException;
use Poplar\Input;
use Poplar\Middleware;
use Poplar\Model;
use Poplar\Notification;
use Poplar\Request;

class Router {
    protected $routes         = [];
    protected $uri;
    protected $method;
    protected $query_string;
    protected $model_bindings = [];
    private   $all_methods    = ['GET', 'POST', 'PUT', 'DELETE'];

    function __construct() {
        $this->uri          = Request::getURI();
        $this->method       = Request::method();
        $this->query_string = Request::queryString();
    }

    /**
     * @param  $file
     *
     * @return Router
     */
    public static function load($file) {
        $router = new static;
        require Application::basePath() . "/routes/$file";

        return $router;
    }

    public static function isInDir($uri) {
        $uri = trim($uri, '/');
        if (empty($uri)) {
            return TRUE;
        }
        // we need to check the full URI at the base upwards, we ONLY check for a str_pos and position 0
        // even if there's a match, we need to ensure its at the right place.
        if (strpos(Request::uri(), $uri) === 0) {
            return TRUE;
        }

        return FALSE;
    }

    public static function isInFile($uri) {
        $uri = trim($uri, '/');
        if (empty($uri)) {
            if (Request::uri() === $uri) {
                return TRUE;
            }

            return FALSE;
        }
        $uri   = explode('/', trim($uri, '/'));
        $match = explode('/', Request::uri());
        if (strpos(end($match), end($uri)) === 0) {
            return TRUE;
        }

        return FALSE;
    }

    public function pathFind() {
        $explode_uri = explode('/', $this->uri);
        if ( ! in_array($this->method, array_keys($this->routes))) {
            throw new RouterException('Nothing found at this location');
        }
        $all_routes = array_keys($this->routes[$this->method]);
        // this should only ever pass an assoc with 1 entry.
        // If it does then it will default to the highest route in the list.
        $successful_route = array_filter($all_routes, function ($route) use ($explode_uri) {
            $regex = '#{(.*?)}#';
            foreach (explode('/', $route) as $key => $slug) {
                if (count(explode('/', $route)) !== count($explode_uri)) {
                    return FALSE;
                }
                if ( ! isset($slug)) {
                    return FALSE;
                }
                if ($slug !== $explode_uri[$key]) {
                    if (preg_match($regex, $slug) === 0) {
                        return FALSE;
                    }
                }
            }

            return TRUE;
        });

        // no route was found, throw a 404!
        if (empty($successful_route)) {
            throw new RouterException('Nothing found at this location');
        }
        // grab inputs as CSRF might be required regardless of if this is a real route
        Input::processData();
        // fire a middleware check, if nothing happens then it has no problems
        // any redirecting or errors should be handled at the class level
        Middleware::checkMiddleware(array_values($successful_route)[0]);

        $route_info = $this->routes[$this->method][array_values($successful_route)[0]];
        // we check here if the route is bound to a model
        $model_bound = in_array(array_values($successful_route)[0], array_keys($this->model_bindings))
            ? $this->model_bindings[array_values($successful_route)[0]] : FALSE;

        // this needs to be returned as we are processing data/view via the controller it calls
        return $this->processRoute($route_info, $model_bound);
    }


    private function processRoute($route_info, $model_bound = FALSE) {
        // check if we need to grab any information from the slugs
        $vars = [];

        Notification::retrieve();

        if ( ! empty($route_info['vars'])) {
            $exploded_uri = explode('/', $this->uri);
            $positions    = array_keys($route_info['vars']);

            foreach ($positions as $pos) {
                $trimmed_var = trim($route_info['vars'][$pos], '{}');
                // check for model bindings on this var
                if ($model_bound && isset($model_bound->$trimmed_var)) {
                    // we then need to pass the value from the uri to the object
                    // then give the $vars the object;
                    $vars[$trimmed_var] = $this->buildModel($model_bound->$trimmed_var, $exploded_uri[$pos]);

                } else {
                    $vars[$trimmed_var] = $exploded_uri[$pos];
                }
            }

        }


        // if the route controller is simply a callback function, then give that instead.
        if (is_callable($route_info['controller'])) {
            return $route_info['controller']($vars);
        }
        // now we need to instantiate the class given by the route
        $controller_string = "App\\Controllers\\" . $route_info['controller'];

        $parsed_vars      = $this->buildCalledVars($vars, $controller_string, $route_info);
        $controller_class = new $controller_string;

        return call_user_func_array([
            $controller_class,
            $route_info['function']
        ], $parsed_vars);

    }


    /**
     * @param Model      $model
     * @param string|int $id
     *
     * @return Model
     * @throws ModelException
     */
    private function buildModel($model, $id) {
        try {
            $model->find($id);
        } catch (ModelException $e) {
            throw new ModelException($e);
        }

        return $model;
    }

    private function buildCalledVars($vars, $controller_string, $route_info) {
        // we need to check if the function demands the request var
        // we can do this by looking at the functions arguments
        $class_reflect = new \ReflectionClass($controller_string);
        $parsed_vars   = [];
        // build the list of parameters this object needs and see if it requires Request
        foreach (
            $class_reflect->getMethod($route_info['function'])->getParameters() as $key => $parameter
        ) {
            if ($parameter->hasType()
                && $parameter->getClass()->getShortName() === 'Request'
            ) {
                // we need to build the request object and pass it to the func
                $parsed_vars[] = (new Request());
            } elseif ($parameter->hasType() && $parameter->getClass()) {
                // look for a variable in the list that matches the one asked for
                $filtered_var  = array_filter($vars, function (Model $array) use ($parameter) {
                    return get_class($array) === $parameter->getClass()->name;
                });
                $parsed_vars[] = array_shift($filtered_var);
            } else {
                $parsed_vars[] = $parameter;
            }
        }

        return $parsed_vars;
    }

    /**
     * @param string   $basepath
     * @param string   $controller
     * @param callable $model
     */
    public function resource($basepath, $controller, $model) {

        // this is the base bath that will return all of this resource
        $this->get("{$basepath}", "{$controller}@browse");

        // this route is used for displaying the form that will allow the user to create the object
        $this->get("{$basepath}/create", "{$controller}@create");

        // this route will be create the new object, most likely coming from GET /create
        $this->post("{$basepath}", "{$controller}@add");

        // this route will display the requested object using an id
        // this route requires model binding to work effectively so pass it from the arg
        $this->bind("{$basepath}/{id}", 'id', $model);
        $this->get("{$basepath}/{id}", "{$controller}@read");

        // this route will display the form that can edit the object
        // this route requires model binding to work effectively so pass it from the arg
        $this->bind("{$basepath}/{id}/edit", 'id', $model);
        $this->get("{$basepath}/{id}/edit", "{$controller}@edit");

        // this route both can be called to update the object
        // THIS ROUTE WILL USE THE READ BINDING FROM ABOVE
        $this->put("{$basepath}/{id}", "{$controller}@update");

        // this route will delete the object given an id
        $this->bind("{$basepath}/{id}/delete", 'id', $model);
        $this->get("{$basepath}/{id}/delete", "{$controller}@delete");
        $this->delete("{$basepath}/{id}", "{$controller}@postDelete");
    }

    // Shorthand functions for all routes

    public function get(string $path, $controller) {
        $this->makeRoute($path, ['GET'], $controller);
    }

    /**
     * @param string $path
     * @param array  $methods
     * @param        $controller
     */
    public function makeRoute(string $path, $methods = [], $controller) {
        // place the route on all methods if none supplied
        if (empty($methods)) {
            $methods = $this->all_methods;
        }
        // first explode the path as we need to find any dynamic vars
        $explode_path = explode('/', trim($path, '/'));

        $regex   = '#{(.*?)}#';
        $dynamic = preg_grep($regex, $explode_path);
        foreach ($methods as $method) {
            $this->routes[$method][trim($path, '/')] = [
                'vars' => $dynamic,
            ];
            if ( ! is_callable($controller)) {
                $explode_controller                                    = explode('@', $controller);
                $this->routes[$method][trim($path, '/')]['controller'] = $explode_controller[0];
                if (isset($explode_controller[1])) {
                    $this->routes[$method][trim($path, '/')]['function'] = $explode_controller[1];
                }
            } else {
                $this->routes[$method][trim($path, '/')]['controller'] = $controller;
            }
        }
    }

    public function post($path, $controller) {
        $this->makeRoute($path, ['POST'], $controller);
    }
    // end shorthand functions

    // resource will build a bunch of paths dynamically using a base url and controller
    // the controller still needs to be created with the routes specified

    public function bind($url, $slug, $class) {
        $class_string = "App\\Models\\" . $class;
        // first check that this is a real model class
        if (class_exists($class_string)) {
            try {
                $object = new $class_string();
            } catch (\Error $e) {
                throw new RouterException($e);
            }
            $this->model_bindings[trim($url, '/')] = (object)[$slug => $object];
        }
    }

    public function put($path, $controller) {
        $this->makeRoute($path, ['PUT'], $controller);
    }

    public function delete($path, $controller) {
        $this->makeRoute($path, ['DELETE'], $controller);
    }


}
