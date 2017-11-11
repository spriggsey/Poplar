<?php

namespace App\Auth\Social;
// interface is added to keep functions uniform across all providers.
// these functions are the bare minimum required by the system to properly function
interface SocialInterface {
    public function redirect();

    public function callback();
}
