<?php


namespace Poplar\Database;


use PDO;
use Poplar\Support\Collection;

/**
 * Class QueryBuilder
 *
 * @package Poplar\Database
 */
class QueryBuilder {
    /**
     * @var PDO
     */
    private $db;
    private $table;
    private $query = [
        "action"  => NULL,
        "columns" => NULL,
        "where"   => NULL,
        "table"   => NULL,
        "order"   => NULL,
    ];
    /** @var \PDOStatement $stmt */
    private $stmt;
    private $value_binds = [];
    private $model;
    private $update_array;
    private $insert_array;

    /**
     * QueryBuilder constructor.
     *
     * @param \PDO $db
     */
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @param $name
     *
     * @return QueryBuilder
     */
    public function setTable($name): QueryBuilder {
        $this->table = $name;

        return $this;
    }

    /**
     * @param array $params
     *
     * @return QueryBuilder
     */
    public function where(...$params): QueryBuilder {
        // set the where string
        $this->query['where'] = $this->processWhereClause($params);
        // bind the values
        $this->preBindValues($params);

        return $this;
    }

    /**
     * @param $params
     *
     * @return string
     */
    private function processWhereClause($params) {
        if (\count($params) === 1) {
            return $this->processWhereClauseArray($params[0]);
        }

        return $this->processWhereClauseSingle($params);
    }

    /**
     * Set what needs to be bound before statement is prepared
     *
     * @param array ...$params
     */
    private function preBindValues($params) {
        if (\count($params) === 1) {
            foreach ((array)$params[0] as $key => $val) {
                $this->value_binds[$key] = $val;
            }
        } else {
            $this->value_binds[reset($params)] = end($params);
        }
    }

    /**
     * @param $array
     *
     * @return string
     */
    private function processWhereClauseArray($array): string {
        $whereString = [];
        if (\count($array) === 1) {
            foreach ((array)$array as $key => $val) {
                if (\is_array($val)) {
                    $whereString[] = "{$val[0]}{$val[1]}:{$val[0]}";
                } else {
                    if (NULL === $val) {
                        $whereString[] = "{$key} IS NULL";
                    } else {
                        $whereString[] = "{$key}=:{$key}";
                    }
                }
            }
        }

        return 'WHERE ' . implode(' AND ', $whereString);
    }

    /**
     * @param $params
     *
     * @return string
     */
    private function processWhereClauseSingle($params): string {
        if (\count($params) === 3) {
            return 'WHERE ' . "`{$params[0]}` {$params[1]} :{$params[0]}";
        }

        if (NULL === end($params)) {
            return "{$params[0]} IS NULL";
        }

        return 'WHERE ' . "{$params[0]}=:{$params[0]}";
    }

    /**
     * @param $statement
     *
     * @return \PDOStatement
     */
    public function raw($statement): \PDOStatement {
        $output = $this->db->query($statement);
        if ( ! $output) {
            return $output;
        }

        return $output;
    }

    /**
     * @param array ...$columns
     *
     * @return QueryBuilder
     */
    public function select(...$columns): QueryBuilder {
        $this->query['columns'] = $columns;

        return $this;
    }

    /**
     * @return mixed
     */
    public function first() {
        return $this->get()->first();
    }

    /**
     * @param array $columns
     *
     * @return Collection
     */
    public function get(array $columns = ['*']): Collection {
        $original_columns = $this->query['columns'];
        if (NULL === $original_columns) {
            $this->query['columns'] = $columns;
        }

        return $this->processSelect();

    }

    /**
     * @return Collection
     */
    private function processSelect(): Collection {
        $this->stmt = $this->db->prepare($this->buildQueryString());
        $this->stmt->setFetchMode(PDO::FETCH_CLASS, \stdClass::class);
        if ( ! empty($this->model)) {
            $this->stmt->setFetchMode(PDO::FETCH_CLASS, $this->model);
        }

        $this->bindValues();
        $this->stmt->execute();

        $result = $this->stmt->fetchAll();

        return collect($result);
    }

    /**
     * @param string $type
     *
     * @return string
     *
     * @uses QueryBuilder::queryStringSELECT()
     * @uses QueryBuilder::queryStringINSERT()
     * @uses QueryBuilder::queryStringDELETE()
     * @uses QueryBuilder::queryStringUPDATE()
     */
    private function buildQueryString($type = 'SELECT'): string {
        $type_func = "queryString{$type}";

        return $this->$type_func();
    }

    /**
     * @param array $params
     */
    private function bindValues(array $params = []) {
        if ( ! empty($params)) {
            $this->value_binds = array_merge($params, $this->value_binds);
        }
        foreach ($this->value_binds as $key => $value) {
            $this->stmt->bindValue(':' . $key, $value);
        }
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function value($value) {
        return $this->get()->pluck($value)->first();
    }

    /**
     * @param array ...$value
     *
     * @return Collection
     */
    public function pluck(...$value): Collection {
        return $this->get()->pluck(...$value);
    }

    /**
     * @param int      $count
     * @param callable $closure
     *
     * @return bool
     */
    public function chunk(int $count, callable $closure): bool {
        foreach ((array) $this->get()->chunk($count) as $chunk_array) {
            if ($closure($chunk_array) === FALSE) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * @return int
     */
    public function count(): int {
        return $this->get()->count();
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function sum($value) {
        return $this->get()->sum($value);
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function min($value) {
        return $this->get()->min($value);
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function max($value) {
        return $this->get()->max($value);
    }

    /**
     * @param $fully_qualified_class_name
     *
     * @return QueryBuilder
     */
    public function bindModel($fully_qualified_class_name): QueryBuilder {
        $this->model = $fully_qualified_class_name;

        return $this;
    }

    /**
     * @param array ...$columns
     *
     * @return QueryBuilder
     */
    public function addSelect(...$columns): QueryBuilder {
        $this->query['columns'] = array_merge($this->query['columns'], $columns);

        return $this;
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function avg($value) {
        return $this->get()->avg($value);
    }

    public function offset() { }

    /**
     * @param $column
     * @param $values
     *
     * @return QueryBuilder
     */
    public function whereNotIn($column, $values): QueryBuilder {
        return $this->whereIn($column, $values, TRUE);
    }

    /**
     * @param       $column
     * @param array $values
     * @param bool  $not
     *
     * @return $this
     */
    public function whereIn($column, array $values, $not = FALSE) {
        $notSQL         = $not ? 'NOT' : '';
        $bind_string = static::bindParamArray($column, $values, $this->value_binds);

        $this->query['where'] = "WHERE {$column} {$notSQL} IN ($bind_string)";


        return $this;
    }

    /**
     * @param string $prefix
     * @param array $values
     * @param $bindArray
     *
     * @return string
     */
    public static function bindParamArray($prefix, $values, &$bindArray): string {
        $str = '';
        foreach ($values as $index => $value) {
            $str                         .= ':' . $prefix . $index . ',';
            $bindArray[$prefix . $index] = $value;
        }

        return rtrim($str, ',');
    }

    /**
     * @param       $column
     * @param array $values
     * @param bool  $not
     *
     * @return QueryBuilder
     * @throws QueryException
     */
    public function whereBetween($column, array $values, $not = FALSE): QueryBuilder {
        $notSQL                  = $not ? 'NOT' : '';
        if (\count($values)!==2) {
            throw new QueryException('invalid amount of values in array, 2 only');
        }
        static::bindParamArray($column, [$values[0], $values[1]], $this->value_binds);
        $this->query['where'] = "WHERE {$column} {$notSQL} BETWEEN :{$column}0 AND :{$column}1";
        return $this;
    }

    /**
     * @param       $column
     * @param array $values
     *
     * @return QueryBuilder
     * @throws \Poplar\Database\QueryException
     */
    public function whereNotBetween($column, array $values): QueryBuilder {
        return $this->whereBetween($column, $values,TRUE);
    }

    public function whereDate($column,$date) {

    }

    public function groupBy() { }

    public function having() { }

    public function inRandomOrder() { }

    public function latest() { }

    public function orderBy() { }

    public function limit() { }

    public function take() { }

    public function skip() { }

    /**
     * @param array $insert_array
     *
     * @return int
     * @throws QueryException
     */
    public function insertGetId(Array $insert_array): int {
        if ( ! $this->insert($insert_array)) {
            throw new QueryException('Error inserting while getting ID');
        }

        return (int)$this->db->lastInsertId();
    }

    /**
     * @param array $insert_array
     *
     * @return bool
     * @throws QueryException
     */
    public function insert(Array $insert_array) {
        try {
            $this->insert_array = $insert_array;
            $this->stmt         = $this->db->prepare($this->buildQueryString('INSERT'));
            $this->bindValues($insert_array);

            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw new QueryException($e);
        }
    }

    /**
     * @return int
     * @throws QueryException
     */
    public function delete() {
        try {
            $this->stmt = $this->db->prepare($this->buildQueryString('DELETE'));
            $this->bindValues();
            if ( ! $this->stmt->execute()) {
                throw new QueryException('Deletion query failure');
            }

            return $this->stmt->rowCount();
        } catch (\PDOException $e) {
            throw new QueryException($e);
        }

    }

    /**
     * @param array $update_array
     *
     * @return bool
     * @throws QueryException
     */
    public function update(array $update_array) {
        try {
            // process the update array
            $this->update_array = $update_array;
            $this->stmt         = $this->db->prepare($this->buildQueryString('UPDATE'));
            $this->bindValues($update_array);

            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw new QueryException($e);
        }

    }

    /**
     * @return bool
     * @throws QueryException
     */
    public function truncate() {
        try {
            $this->stmt = $this->db->prepare("TRUNCATE {$this->table}");

            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw new QueryException($e);
        }
    }

    /**
     * @param $column
     * @param $amount
     *
     * @return bool
     * @throws \PDOException
     */
    public function increment($column, $amount) {
        try {
            $this->stmt =
                $this->db->prepare("UPDATE {$this->table} SET {$column} = {$column} + {$amount} {$this->query['where']}");
            $this->bindValues();

            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * @param $column
     * @param $amount
     *
     * @return bool
     * @throws \PDOException
     */
    public function decrement($column, $amount) {
        try {
            $this->stmt =
                $this->db->prepare("UPDATE {$this->table} SET {$column} = {$column} - {$amount} {$this->query['where']}");
            $this->bindValues();

            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * @return string
     */
    private function queryStringSELECT(): string {
        $string = sprintf('SELECT %s FROM %s %s;', $this->prepareColumns($this->query['columns']), $this->table,
            $this->query['where']);

        return $string;
    }

    /**
     * @param $values
     *
     * @return string
     */
    private function prepareColumns($values): string {
        if ($values) {
            return implode(',', $values);
        }

        return '*';
    }

    /**
     * @return string
     */
    private function queryStringINSERT(): string {
        $string = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s);', $this->table,
            implode('`,`', array_keys($this->insert_array)), $this->prepareValues(array_keys($this->insert_array)));

        return $string;
    }

    /**
     * @param array $values
     *
     * @param bool  $assoc
     *
     * @return string
     */
    private function prepareValues($values, $assoc = FALSE): string {
        if ($assoc) {
            $paramString = [];
            foreach ($values as $key => $val) {
                $paramString[] = "`{$key}`=:{$key}";
            }

            return implode(',', $paramString);
        }
        if ($values) {
            return ':' . implode(',:', $values);
        }
        return '';
    }

    /**
     * @return string
     */
    private function queryStringUPDATE(): string {
        $string = sprintf('UPDATE `%s` SET %s %s;', $this->table, $this->prepareValues($this->update_array, TRUE),
            $this->query['where']);

        return $string;
    }

    /**
     * @return string
     */
    private function queryStringDELETE(): string {
        return sprintf('DELETE FROM %s %s', $this->table, $this->query['where']);
    }

}
