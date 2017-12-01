<?php

namespace Poplar\Middleware;

class Web extends Middleware {

    public function execute($base_uri) {
        // call the verify CSRF middleware
        VerifyCSRF::call($base_uri);
    }
}
