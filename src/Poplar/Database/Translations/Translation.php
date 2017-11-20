<?php

namespace Poplar\Database\Translations;

use Poplar\Support\Str;

abstract class Translation implements TranslationInterface {
    /**
     * Find which translation is needed from a string obtained from driver
     *
     * @param $driver_string
     *
     * @return TranslationInterface
     */
    public static function getTranslation($driver_string) {
        $class_string = 'Poplar\\Database\\Translations\\'.Str::ucfirst($driver_string);
        return new $class_string();
    }
}
