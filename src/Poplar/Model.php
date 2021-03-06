<?php


namespace Poplar;


use Poplar\Database\DB;
use Poplar\Database\Translations\Translation;
use Poplar\Exceptions\ModelException;
use Poplar\Support\Str;

class Model {
    static protected $untouchable = [
        'id',
        'created_at',
        'updated_at'
    ];
    public           $id;
    protected        $exists      = FALSE;
    static protected $table;
    protected        $columns;
    protected        $QB;
    protected        $primary_key = 'id';

    /**
     * Model constructor.
     *
     * @throws \ReflectionException
     */
    public function __construct() {
        // set the table if empty
        $table         = static::table();
        $this->columns = ( ! empty($this->columns)) ?: static::getColumns();


        $this->QB = DB::table($table)->bindModel(static::class);
    }

    /**
     * Grab the assumed name of the table
     *
     * @return string
     * @throws \ReflectionException
     */
    public static function table(): string {
        if ( ! empty(static::$table)) {
            return static::$table;
        }

        $reflect = new \ReflectionClass(static::class);
        $lower   = Str::lower($reflect->getShortName());

        return Str::plural($lower);
    }

    private static function getColumns() {
        $table       = static::table();
        $db_driver   = DB::driver();
        $translation = Translation::getTranslation($db_driver);

        return $translation->columns($table);
    }

    /**
     * @param array $columns
     *
     * @return Model[]|Support\Collection
     */
    public static function all($columns = ['*']) {
        return static::buildModel()->get($columns);
    }

    private static function buildModel(): Database\QueryBuilder {
        return DB::table(static::table())->bindModel(static::class);
    }

    /**
     * @param array ...$clause
     *
     * @return Database\QueryBuilder
     */
    public static function where(...$clause): Database\QueryBuilder {
        return static::buildModel()->where(...$clause);
    }

    /**
     * @param $column
     * @param $values
     *
     * @return Database\QueryBuilder
     */
    public static function whereIn($column, $values): Database\QueryBuilder {
        return static::buildModel()->whereIn($column, $values);
    }

    /**
     * @param $column
     * @param $values
     *
     * @return Database\QueryBuilder
     */
    public static function whereNotIn($column, $values): Database\QueryBuilder {
        return static::buildModel()->whereNotIn($column, $values);
    }

    /**
     * @param $id
     *
     * @return mixed|static
     * @throws \ReflectionException
     */
    public static function find($id) {
        $object = static::buildModel()->where(['id' => $id])->first();
        if (empty($object)) {
            return new static();
        }

        $object->setExists();

        return $object;
    }

    /**
     * @param      $column
     * @param      $values
     * @param bool $not
     *
     * @return Database\QueryBuilder
     * @throws \Poplar\Database\QueryException
     */
    public static function whereBetween($column, $values, $not = FALSE): Database\QueryBuilder {
        return static::buildModel()->whereBetween($column, $values, $not);
    }

    /**
     * @param $column
     * @param $values
     *
     * @return Database\QueryBuilder
     */
    public static function whereNotBetween($column, $values): Database\QueryBuilder {
        return static::buildModel()->whereNotIn($column, $values);
    }

    /**
     * @param $id
     *
     * @return static
     * @throws ModelException
     * @throws \ReflectionException
     */
    public static function findOrFail($id) {
        $object = $object = static::buildModel()->where(['id' => $id])->first();

        if (NULL === $object) {
            // get the model name
            $name = (new \ReflectionClass(static::class))->getShortName();
            throw new ModelException($name . ' could not be found');
        }

        // set that the object exists in the DB
        $object->setExists();

        return $object;
    }

    /**
     * @param string $identifier
     * @param array  $values
     *
     * @return Model
     *
     * @throws Database\QueryException
     */
    public static function firstOrCreate($identifier, $values) {
        $object = $object = static::buildModel()->where([$identifier => $values[$identifier]])->first();

        if (NULL === $object) {
            // create a new entry in the database for this model
            $id = static::buildModel()->insertGetId($values);

            return static::buildModel()->where(['id' => $id])->first()->setExists();
        }

        $object->setExists();

        return $object;
    }

    /**
     * statically destroy a model object
     *
     * @param $ids
     *
     * @return int
     * @throws Database\QueryException
     */
    public static function destroy($ids): int {
        $ids = is_array($ids) ? $ids : func_get_args();

        $identifier = static::findIdentifier();

        return static::buildModel()->whereIn($identifier, $ids)->delete();
    }

    /**
     * @param array $data
     *
     * @return bool|Model
     * @throws \ReflectionException
     * @throws Database\QueryException
     */
    public static function create(array $data) {
        $model = new static();
        dd($model);
        foreach ($data as $key => $value) {
            $model->$key = $value;
        }
        if ( ! $model->add()) {
            return FALSE;
        }

        return $model;
    }

    /**
     * @param array $data
     * @param       $id
     *
     * @return Model
     * @throws ModelException
     * @throws \ReflectionException
     * @throws Database\QueryException
     */
    public static function update(array $data, $id) {
        $model = static::findOrFail($id);
        foreach ($data as $key => $value) {
            $model->$key = $value;
        }
        $model->save();

        return $model;
    }


    private static function findIdentifier() {
        return 'id';
    }

    /**
     * @return bool
     * @throws Database\QueryException
     * @throws ModelException
     */
    public function save(): bool {
        if ($this->exists()) {
            return $this->updateModel();
        }

        return $this->add();
    }

    /**
     * @return bool
     */
    public function exists(): bool {
        return $this->exists;
    }

    public function get($columns = ['*']) {
        return static::all($columns);
    }

    /**
     * @return bool
     * @throws Database\QueryException
     * @throws ModelException
     */
    private function updateModel(): bool {
        if ( ! $this->exists()) {
            return FALSE;
        }

        $where_arr = $this->checkIdentifier();
        $arr       = $this->generateSaveArray();

        return static::buildModel()->where($where_arr)->update($arr);
    }

    /**
     * @return bool
     * @throws Database\QueryException
     */
    private function add() {

        $arr = $this->generateSaveArray();

        if ($this->{$this->primary_key} = static::buildModel()->insertGetId($arr)) {
            $this->setExists();

            return TRUE;
        }

        return FALSE;
    }

    /**
     * @param bool|array $where_clause
     *
     * @return array
     * @throws ModelException
     */
    private function checkIdentifier(array $where_clause = []): array {
        if (isset($this->identifier)) {
            if (empty($this->{$this->identifier})) {
                throw new ModelException('Identifier set for model but no value found');
            }
            $where_clause[$this->identifier] = $this->{$this->identifier};

            return $where_clause;
        }

        // check that where clause is set before returning false
        return $where_clause ? $where_clause : FALSE;
    }

    private function generateSaveArray(): array {
        $out = [];
        foreach ($this->columns as $column) {
            if (in_array($column, static::$untouchable)) {
                continue;
            }
            if ( ! isset($this->$column)) {
                continue;
            }
            $out[$column] = $this->$column ?? NULL;
        }

        return $out;
    }

    /**
     * @param boolean $boolean
     *
     * @return static
     */
    public function setExists($boolean = TRUE) {
        $this->exists = $boolean;

        return $this;
    }

    /**
     * Return the model to JSON string
     *
     * @return string
     */
    public function toJson() {
        return json_encode($this);
    }

    public function getKey() {

    }

    /**
     *
     */
    public function delete() {
        // if this doesn't exist, don't delete it
        if ( ! $this->exists()) {
            return;
        }

    }


}
