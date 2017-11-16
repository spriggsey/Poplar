<?php


namespace Poplar;


use PDO;
use Poplar\Database\DB;
use Poplar\Exceptions\ModelException;
use Poplar\Support\Str;

class Model {
    static private $untouchable = [
        'id',
        'created_at',
        'updated_at'
    ];
    protected      $exists      = FALSE;
    protected      $table;
    protected      $columns;
    protected      $QB;
    protected      $primary_key = 'id';

    public function __construct() {
        // set the table if empty
        $this->table   = ( ! empty($this->table)) ?: static::table();
        $this->columns = ( ! empty($this->columns)) ?: static::getColumns();

        $this->QB = DB::table($this->table)->bindModel(static::class);
    }

    /**
     * Grab the assumed name of the table
     *
     * @return string
     * @throws \ReflectionException
     */
    public static function table(): string {
        $reflect = new \ReflectionClass(static::class);
        $lower   = Str::lower($reflect->getShortName());

        return Str::plural($lower);
    }

    private static function getColumns() {
        $table  = self::table();

        $db_driver = DB::driver();

        $translation = Database\Translations\Translation::getTranslation($db_driver);

        $output = DB::raw("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);

//        $output = DB::raw("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);

        dd($output);

        return array_keys($output);
    }

    /**
     * @return Model[]|Support\Collection
     */
    public static function all() {
        return static::buildModel()->get();
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
     */
    public static function find($id) {
        $object = static::buildModel()->where(['id' => $id])->first();
        if (empty($object)) {
            return new static();
        }

        $object->setExists(TRUE);

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
     */
    public static function findOrFail($id) {
        $object = $object = static::buildModel()->where(['id' => $id])->first();

        if (NULL === $object) {
            // get the model name
            $name = (new \ReflectionClass(static::class))->getShortName();
            throw new ModelException($name . ' could not be found');
        }

        // set that the object exists in the DB
        $object->setExists(TRUE);

        return $object;
    }

    /**
     * statically destroy a model object
     *
     * @param $ids
     *
     * @return int
     */
    public static function destroy($ids): int {
        $ids = is_array($ids) ? $ids : func_get_args();

        $identifier = static::findIdentifier();

        return static::buildModel()->whereIn($identifier, $ids)->delete();
    }

    private static function findIdentifier() {
        return 'id';
    }

    public function save(): bool {
        if ($this->exists()) {
            return $this->update();
        }

        return $this->add();
    }

    /**
     * @return bool
     */
    public function exists(): bool {
        return $this->exists;
    }

    public function update(): bool {
        if ( ! $this->exists()) {
            return FALSE;
        }

        $where_arr = $this->checkIdentifier();
        $arr       = $this->generateSaveArray();

        return static::buildModel()->where($where_arr)->update($arr);
    }

    /**
     * @return bool
     */
    private function add() {

        $arr = $this->generateSaveArray();

        if ($this->{$this->primary_key} = static::buildModel()->insertGetId($arr)) {
            $this->setExists(TRUE);

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
            if (in_array($column, self::$untouchable)) {
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
    public function setExists($boolean) {
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
