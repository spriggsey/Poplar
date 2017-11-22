<?php


namespace Poplar\Middleware;


use Poplar\Application;
use Poplar\Auth\Session;
use Poplar\Exceptions\MiddlewareException;
use Poplar\Input;
use Poplar\Request;

class VerifyCSRF extends Middleware {
    public function execute($base_uri) {
        // get methods do no require a CSRF for web routes
        if (Request::method()==='GET') {
            return TRUE;
        }
        // any other methods need one, throw error here
        if (empty(Input::CSRF())) {
            $this->handleError('Missing CSRF Input');
        }
        $session_token = Application::user() ? Session::get('db') : Session::get('local');
        if ($session_token !== Input::CSRF()) {
            $this->handleError('CSRF Mismatch');
        }
        return TRUE;
    }

    protected function handleError($reason) {
        if (Request::isAJAX()) {
            http_response_code(403);
            dd($reason);
        }
        throw new MiddlewareException($reason);
    }
}
