<?php

namespace Poplar;

use Poplar\Database;
use Poplar\Database\QueryBuilder;
use Poplar\Exceptions\ModelException;
use Poplar\Support\Str;

trait Relational {
    /**
     * @param        $class
     * @param null   $foreign_key
     * @param string $local_key
     *
     * @return Model|bool
     * @throws \Exception
     */
    protected function hasOne($class, $foreign_key = NULL, $local_key = 'id') {
        if (is_null($foreign_key)) {
            // we assume that the class name is the foreign key appended with '_id'
            $orig_class  = explode('\\', static::class);
            $class_end   = Str::snake(end($orig_class));
            $foreign_key = $class_end . "_id";
        }
        // instantiate the class given from the arg
        $class_string = "App\\Models\\" . $class;
        if (class_exists($class_string)) {
            try {
                /** @var Model $object */
                $object = new $class_string();
            } catch (\Error $e) {
                throw new \Exception($e->getMessage());
            }
        } else {
            throw new \Exception('Unknown model provided in argument');
        }

        return $object::where([$foreign_key => $this->{$local_key}])->first();
    }

    /**
     * @param        $class
     * @param null   $foreign_key
     * @param string $local_key
     *
     * @return Support\Collection
     * @throws \Exception
     */
    protected function hasMany($class, $foreign_key = NULL, $local_key = 'id') {
        if (is_null($foreign_key)) {
            // we assume that the class name is the foreign key appended with '_id'
            $orig_class  = explode('\\', static::class);
            $class_end   = Str::snake(end($orig_class));
            $foreign_key = $class_end . "_id";
        }
        // instantiate the class given from the arg
        $class_string = "App\\Models\\" . $class;
        if (class_exists($class_string)) {
            try {
                /** @var Model $object */
                $object = new $class_string();
            } catch (\Error $e) {
                throw new \Exception($e->getMessage());
            }
        } else {
            throw new \Exception('Unknown model provided in argument');
        }

        return $object::where([$foreign_key => $this->{$local_key}])->get();
    }

    /**
     * @param        $class
     * @param string $foreign_key
     * @param null   $local_key
     *
     * @return bool|Support\Collection
     * @throws \Exception
     */
    protected function belongsTo(
        $class,
        $foreign_key = 'id',
        $local_key = NULL
    ) {
        if (is_null($local_key)) {
            $local_key = Str::snake($class) . "_id";
        }
        // instantiate the class given from the arg
        $class_string = "App\\Models\\" . $class;
        if (class_exists($class_string)) {
            try {
                /** @var Model $object */
                $object = new $class_string();
            } catch (\Error $e) {
                throw new \Exception($e->getMessage());
            } catch (ModelException $e) {
                return FALSE;
            }
        } else {
            throw new \Exception('Unknown model provided in argument');
        }

        try {
            return $object::where([$foreign_key => $this->{$local_key}])->get();
        } catch (ModelException $e) {
            return FALSE;
        }
    }

    /**
     * @param        $class
     * @param null   $intermediate_table
     * @param string $local_key
     *
     * @return mixed
     */
    protected function belongsToMany(
        $class,
        $intermediate_table = NULL,
        $local_key = 'id'
    ) {
        $lowercase_target_class = Str::snake($class);
        $orig_class             = explode('\\', static::class);
        $class_end              = Str::snake(end($orig_class));
        $class_string           = "App\\Models\\" . $class;
        if (is_null($intermediate_table)) {
            // no intermediate table was given so
            $array = [$lowercase_target_class, $class_end];
            sort($array);
            $intermediate_table = implode("_", $array);
        }

        return Database\DB::table($intermediate_table)->bindModel($class_string)
            ->join($class_string::table(), "`{$class_string::table()}`.id",
                "`{$intermediate_table}`.{$lowercase_target_class}_id")
            ->where(["{$class_end}_id" => $this->{$local_key}])->get();
    }

    /**
     * This Function should only be used with relationships that have many to
     * many pivot tables
     *
     * @param string|array $model_identifier
     * @param string       $target_model
     *
     * @param array|null   $extra_fields
     *
     * @return bool
     */
    protected function attach(
        $model_identifier,
        $target_model,
        array $extra_fields = NULL
    ) {
        // reflect on the class that called this so we can grab the
        // we need to find the identifier of this class, if not set then is should be ID
        $class_ident = (! empty($this->identifier) ? $this->identifier : 'id');
        $pivot_table = $this->getPivotTable($target_model);
        $pivod_ids   = $this->getPivotIds($target_model);

        if ( ! is_array($model_identifier)) {
            $model_identifier = [$model_identifier];
        }
        foreach ($model_identifier as $identifier) {
            $props = [
                $pivod_ids['self']   => $this->$class_ident,
                $pivod_ids['target'] => $identifier,
            ];
            if ( ! empty($extra_fields)) {
                $props = array_merge($props, $extra_fields);
            }
            try {
                Application::database()->setTable($pivot_table)->insert($props);
            } catch (\Exception $e) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * @param $target_model
     *
     * @return string
     */
    static private function getPivotTable($target_model) {
        $lowercase_target_class = Str::snake($target_model);
        $orig_class             = explode('\\', static::class);
        $class_end              = Str::snake(end($orig_class));
        $array                  = [$lowercase_target_class, $class_end];
        sort($array);

        return implode("_", $array);
    }

    /**
     * @param $target_model
     *
     * @return array
     */
    static private function getPivotIds($target_model) {
        $lowercase_target_class = Str::snake($target_model);
        $orig_class             = explode('\\', static::class);
        $class_end              = Str::snake(end($orig_class));

        return [
            'self'   => "{$class_end}_id",
            'target' => "{$lowercase_target_class}_id"
        ];
    }

    /**
     * Detach an model from a pivot table with the ID's provided
     *
     * @param $model_identifier
     * @param $target_model
     *
     * @return bool
     */
    protected function detach($model_identifier, $target_model) {
        // we need to find the identifier of this class, if not set then is should be ID
        $class_ident = (! empty($this->identifier) ? $this->identifier : 'id');
        $pivot_table = $this::getPivotTable($target_model);
        $pivot_ids   = $this::getPivotIds($target_model);

        if ( ! is_array($model_identifier)) {
            $model_identifier = [$model_identifier];
        }
        foreach ($model_identifier as $identifier) {
            try {
                Application::database()->setTable($pivot_table)->where([
                    $pivot_ids['self']   => $this->$class_ident,
                    $pivot_ids['target'] => $identifier,
                ])->delete();
            } catch (\Exception $e) {
                return FALSE;
            }
        }

        return TRUE;
    }
}
