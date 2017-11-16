<?php

namespace Poplar\Database\Translations;

use Poplar\Support\Str;

abstract class Translation implements TranslationInterface {
    /**
     * Find which translation is needed from a string obtained from driver
     * @param $driver_string
     */
    public static function getTranslation($driver_string) {



    }
}
