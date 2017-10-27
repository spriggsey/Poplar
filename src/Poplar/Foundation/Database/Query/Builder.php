<?php


namespace Poplar\Database\Query;

use PDO;
use Poplar\Database\Connection;
use Poplar\Foundation\Database\QueryException;


/**
 * Class Builder
 *
 *
 * @package Poplar\Foundation\Database\Query
 */
class Builder {

    private $connection;
    /** @var \PDOStatement $stmt */
    private $stmt;
    private $action;
    private $columns;
    private $values;
    private $where_clause;
    private $ordering;
    private $from;
    private $binding_list;

    private $test_mode = FALSE;

    /**
     * Builder constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection) {
        $this->connection = $connection;
    }

    /**
     * @param string|bool         $mode
     * @param string|callable|int $value
     *
     * @return $this
     */
    public function fetchMode($value = 'stdClass', $mode = FALSE) {
        $fetch_modes = [
            'class'  => PDO::FETCH_CLASS,
            'object' => PDO::FETCH_OBJ,
            'assoc'  => PDO::FETCH_ASSOC,
            'column' => PDO::FETCH_COLUMN,
        ];
        if ( ! $mode) {
            $mode = 'class';
        }
        $this->stmt->setFetchMode($fetch_modes[$mode], $value);

        return $this;
    }

    public function insert($table) {
        $this->action = "INSERT INTO {$table}";

        return $this;
    }

    public function select($columns = ['*']) {
        $this->action = "SELECT ";

    }

    public function where($clause = []) {
        if ( ! isAssoc($clause)) {
            throw new QueryException('Associative array required');
        }

        $whereString = [];
        foreach ($clause as $key => $val) {
            if (is_array($val)) {
                $whereString[] = "`{$val[0]}`{$val[1]}:{$val[0]}";
                $this->setBinding($val[0], $val[2]);
            } else {
                if (is_null($val)) {
                    $whereString[] = "`{$key}` IS NULL";
                } else {
                    $whereString[] = "`{$key}`=:{$key}";
                    $this->setBinding($key, $val);
                }
            }
        }

        $this->where_clause = "WHERE " . implode(" AND ", $whereString);
    }

    private function setBinding($key, $value) {
        $this->binding_list[] = [$key => $value];

        return $this;
    }

    /**
     * @param array $values
     *
     * @return $this
     * @throws QueryException
     */
    public function values($values = []) {
        // assoc only
        if ( ! isAssoc($values)) {
            throw new QueryException('Associative array required');
        }

        $imploded_array_keys = implode(',', array_keys($values));
        $this->columns       = "($imploded_array_keys)";
        $imploded_array_keys = ":" . implode(',:', array_values($values));
        $this->values        = "VALUES ({$imploded_array_keys})";
        $this->setBindings($values);

        return $this;
    }

    /**
     * @param $array
     *
     * @return $this
     * @throws QueryException
     */
    private function setBindings(array $array) {
        if ( ! isAssoc($array)) {
            throw new QueryException('Associative array required');
        }
        foreach ($array as $key => $value) {
            $this->setBinding($key, $value);
        }

        return $this;
    }

    public function from($table) {
        $this->from = "FROM {$table}";
    }

    public function orderBy() { }

    public function addOrderBy() { }

    public function innerJoin() { }

    public function outerJoin() { }

    public function having() { }

    public function groupBy() { }

    public function addGroupBy() { }

    public function setParameter() { }

    public function setFirstResult() { }

    public function setMaxResults() { }

    public function execute() {


        if ($this->test_mode) {
            return $this->testExecute();
        }
    }

    private function testExecute() {
        return $this->connection->setTestMode();
    }
}