<?php


namespace Poplar\Support;


use Doctrine\Common\Inflector\Inflector;

class Pluralizer {
    /**
     * Uncountable word forms.
     *
     * @var array
     */
    public static $uncountable = [
        'audio',
        'bison',
        'chassis',
        'compensation',
        'coreopsis',
        'data',
        'deer',
        'education',
        'emoji',
        'equipment',
        'evidence',
        'feedback',
        'firmware',
        'fish',
        'furniture',
        'gold',
        'hardware',
        'information',
        'jedi',
        'kin',
        'knowledge',
        'love',
        'metadata',
        'money',
        'moose',
        'news',
        'nutrition',
        'offspring',
        'plankton',
        'pokemon',
        'police',
        'rain',
        'rice',
        'series',
        'sheep',
        'software',
        'species',
        'swine',
        'traffic',
        'wheat',
    ];

    /**
     * Get the plural form of an English word.
     *
     * @param  string $value
     * @param  int    $count
     *
     * @return string
     */
    public static function plural($value, $count = 2): string {
        if ((int)$count === 1 || static::uncountable($value)) {
            return $value;
        }
        $plural = Inflector::pluralize($value);

        return static::matchCase($plural, $value);
    }

    /**
     * Get the singular form of an English word.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function singular($value): string {
        $singular = Inflector::singularize($value);

        return static::matchCase($singular, $value);
    }

    /**
     * Determine if the given value is uncountable.
     *
     * @param  string $value
     *
     * @return bool
     */
    protected static function uncountable($value): bool {
        return \in_array(strtolower($value), static::$uncountable, TRUE);
    }

    /**
     * Attempt to match the case on two strings.
     *
     * @param  string $value
     * @param  string $comparison
     *
     * @return string
     */
    protected static function matchCase($value, $comparison): string {
        $functions = ['mb_strtolower', 'mb_strtoupper', 'ucfirst', 'ucwords'];
        foreach ($functions as $function) {
            if ($function($comparison) === $comparison) {
                return $function($value);
            }
        }

        return $value;
    }
}
