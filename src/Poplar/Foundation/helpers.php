<?php
if (!function_exists('isAssoc')) {
    function isAssoc($array) {
        $array = array_keys($array);

        return ($array !== array_keys($array));
    }
}