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

    protected $untouchable = [
        'id',
        'created_at',
        'updated_at'
    ];

    public function __construct() {
        // set the table if empty
        $this->table = (!empty($this->table)) ?: static::table();
        $this->columns = (!empty($this->columns))?:static::getColumns();

        $this->QB = DB::table($this->table)->bindModel(static::class);
    }

    private static function buildModel() {
        return DB::table(static::table())->bindModel(static::class);
    }

    /**
     * Grab the assumed name of the table
     *
     * @return string
     */
    public static function table() {
        $reflect = new \ReflectionClass(static::class);
        $lower  = Str::lower($reflect->getShortName());
        $plural = Str::plural($lower);
        return $plural;
    }

    public static function all($columns = []) {
        return static::buildModel()
            ->get($columns);
    }

    public static function where($clause) {
        return static::buildModel()
            ->where($clause);
    }

    public static function find($id) {
        $object = static::buildModel()
            ->where(['id'=>$id])
            ->first();
        return new static();
    }

    public static function findOrFail($id) {
        $object = $object = static::buildModel()->where(['id' => $id])->first();

        if (is_null($object)) {
            // get the model name
            $name = (new \ReflectionClass(static::class))->getShortName();
            throw new ModelException($name . ' could not be found');
        }
        return $object;
    }

    private static function getColumns() {
        $table = self::table();
        $output = DB::raw("SELECT * FROM {$table} LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);
        return array_keys($output);
    }

    private static function findIdent() {

    }

    public function save() {
        return $this->update();
    }

    public function update() {
        $where_arr = $this->checkIdentifier();
        $arr = $this->generateSaveArray();

        return static::buildModel()
            ->where($where_arr)
            ->update($arr);
    }

    private function generateSaveArray () {
        $out = [];
        foreach ($this->columns as $column) {
            $out[$column] = $this->$column;
        }
        // unset any untouchables out of the dynamic save array
        foreach ($this->untouchable as $item) {
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

    public static function destroy($ids) {
    }

    /**
     * @param bool|array $where_clause
     *
     * @return bool
     * @throws ModelException
     */
    private function checkIdentifier($where_clause= []) {
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

}
