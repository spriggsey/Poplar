<?php


namespace Poplar;

class Asset {
    public static function script($name) {
        $cb = config('app.cache_bust','0');
        if ($cb) {
            echo "<script src='{$name}?v={$cb}' type='application/javascript'></script>";
        } else {
            echo "<script src='{$name}' type='application/javascript'></script>";
        }
    }

    public static function style($name) {
        $cb = config('app.cache_bust', '0');
        if ($cb) {
            echo "<link rel='stylesheet' href='{$name}?v={$cb}' type='text/css'>";
        } else {
            echo "<link rel='stylesheet' href='{$name}' type='text/css'>";
        }
    }
}
