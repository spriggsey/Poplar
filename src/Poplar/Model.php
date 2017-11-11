<?php


namespace Poplar;


use PDO;
use Poplar\Database\DB;
use Poplar\Exceptions\ModelException;
use Poplar\Support\Str;

class Model {
    protected $table;
    protected $columns;
    protected $QB;

    static private $untouchable = [
        'id',
        'created_at',
        'updated_at'
    ];

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
        $output = DB::raw("SELECT * FROM {$table} LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        return array_keys($output);
    }

    /**
     * @param array $columns
     *
     * @return Support\Collection|Model[]
     */
    public static function all(array $columns = []) {
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
     */
    public static function find($id) {
        $object = static::buildModel()->where(['id' => $id])->first();
        if (empty($object)) {
            return new static();
        }

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

    public static function findOrFail($id) {
        $object = $object = static::buildModel()->where(['id' => $id])->first();

        if (NULL === $object) {
            // get the model name
            $name = (new \ReflectionClass(static::class))->getShortName();
            throw new ModelException($name . ' could not be found');
        }

        return $object;
    }

    public static function destroy($ids) {
    }

    private static function findIdent() {

    }

    public function save(): bool {
        return $this->update();
    }

    public function update(): bool {
        $where_arr = $this->checkIdentifier();
        $arr       = $this->generateSaveArray();

        return static::buildModel()->where($where_arr)->update($arr);
    }

    /**
     * @param bool|array $where_clause
     *
     * @return bool
     * @throws ModelException
     */
    private function checkIdentifier(array $where_clause = []): bool {
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
            $out[$column] = $this->$column;
        }
        // unset any untouchables out of the dynamic save array
        foreach (self::$untouchable as $item) {
            unset($this->$item);
        }

        return $out;
    }

    public function toJson() {

    }

    public function getKey() {

    }

    public function delete() {

    }

}
