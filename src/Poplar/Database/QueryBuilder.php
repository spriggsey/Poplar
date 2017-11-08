<?php


namespace Poplar\Database;


use PDO;
use Poplar\Support\Collection;

class QueryBuilder {
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
    public function setTable($name) {
        $this->table = $name;

        return $this;
    }

    /**
     * @param array $params
     *
     * @return QueryBuilder
     */
    public function where(...$params) {
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
        if (count($params) === 1) {
            return $this->processWhereClauseArray($params[0]);
        } else {
            return $this->processWhereClauseSingle($params);
        }
    }

    /**
     * Set what needs to be bound before statement is prepared
     *
     * @param array ...$params
     */
    private function preBindValues($params) {
        if (count($params) === 1) {
            foreach ($params[0] as $key => $val) {
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
    private function processWhereClauseArray($array) {
        $whereString = [];
        if (count($array) === 1) {
            foreach ($array as $key => $val) {
                if (is_array($val)) {
                    $whereString[] = "{$val[0]}{$val[1]}:{$val[0]}";
                } else {
                    if (is_null($val)) {
                        $whereString[] = "{$key} IS NULL";
                    } else {
                        $whereString[] = "{$key}=:{$key}";
                    }
                }
            }
        }

        return "WHERE " . implode(" AND ", $whereString);
    }

    /**
     * @param $params
     *
     * @return string
     */
    private function processWhereClauseSingle($params) {
        if (count($params) === 3) {
            return "WHERE " . "`{$params[0]}` {$params[1]} :{$params[0]}";
        } else {
            if (is_null(end($params))) {
                return "{$params[0]} IS NULL";
            }

            return "WHERE " . "{$params[0]}=:{$params[0]}";
        }
    }

    /**
     * @param array ...$columns
     *
     * @return QueryBuilder
     */
    public function select(...$columns) {
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
     * @return \Poplar\Support\Collection
     */
    public function get($columns = ['*']) {
        if (!is_array($columns)) {$columns = [$columns];}
        $original_columns = $this->query['columns'];
        if (is_null($original_columns)) {
            $this->query['columns'] = $columns;
        }

        return $this->processSelect();

    }

    /**
     * @return \Poplar\Support\Collection
     */
    private function processSelect() {
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
    private function buildQueryString($type = 'SELECT') {
        $type_func = "queryString{$type}";

        return $this->$type_func();
    }

    private function bindValues($params = []) {
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
    public function pluck(...$value) {
        return $this->get()->pluck(...$value);
    }

    /**
     * @param int      $count
     * @param callable $closure
     *
     * @return bool
     */
    public function chunk(int $count, callable $closure) {
        foreach ($this->get()->chunk($count) as $chunk_array) {
            if ($closure($chunk_array) === FALSE) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * @return int
     */
    public function count() {
        return $this->get()->count();
    }

    public function sum($value) {
        return $this->get()->sum($value);
    }

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
    public function bindModel($fully_qualified_class_name) {
        $this->model = $fully_qualified_class_name;

        return $this;
    }

    public function addSelect(...$columns) {
        $this->query['columns'] = array_merge($this->query['columns'], $columns);

        return $this;
    }

    public function avg($value) {
        return $this->get()->avg($value);
    }

    public function offset() { }

    public function orWhere() { }

    public function whereIn() { }

    public function whereNotIn() { }

    public function whereBetween() { }

    public function whereNotBetween() { }

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
    public function insertGetId(Array $insert_array) {
        if ( ! $this->insert($insert_array)) {
            throw new QueryException();
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

    public function truncate() {
        try {
            $this->stmt = $this->db->prepare("TRUNCATE {$this->table}");

            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw new QueryException($e);
        }
    }

    public function increment($column, $amount) {
        try {
            $this->stmt =
                $this->db->prepare("UPDATE {$this->table} SET {$column} = {$column} + {$amount} {$this->query['where']}");
            $this->bindValues();
            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw new \PDOException($e);
        }
    }

    public function decrement($column, $amount) {
        try {
            $this->stmt =
                $this->db->prepare("UPDATE {$this->table} SET {$column} = {$column} - {$amount} {$this->query['where']}");
            $this->bindValues();
            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw new \PDOException($e);
        }
    }

    /**
     * Check if the query string is valid sql
     *
     * @param $statement
     */
    private function validateQueryString($statement) {

    }

    /**
     * @return string
     */
    private function queryStringSELECT() {
        $string = sprintf('SELECT %s FROM %s %s;', $this->prepareColumns($this->query['columns']), $this->table,
            $this->query['where']);

        return $string;
    }

    /**
     * @param $values
     *
     * @return string
     */
    private function prepareColumns($values) {
        if ($values) {
            return implode(",", $values);
        } else {
            return '*';
        }
    }

    private function queryStringINSERT() {
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
    private function prepareValues($values, $assoc = FALSE) {
        if ($assoc) {
            $paramString = [];
            foreach ($values as $key => $val) {
                $paramString[] = "`{$key}`=:{$key}";
            }

            return implode(',', $paramString);
        }
        if ($values) {
            return ":" . implode(",:", $values);
        }
    }

    private function queryStringUPDATE() {
        $string = sprintf('UPDATE `%s` SET %s %s;', $this->table, $this->prepareValues($this->update_array, TRUE),
            $this->query['where']);

        return $string;
    }

    private function queryStringDELETE() {
        $string = sprintf("DELETE FROM %s %s", $this->table, $this->query['where']);

        return $string;
    }

}
