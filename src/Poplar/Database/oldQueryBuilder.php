<?php


namespace Poplar\Database;

use Poplar\Model;
use PDO;
use PDOException;

class oldQueryBuilder {
    /** @var \PDOStatement $stmt */
    private $stmt;
    private $db;
    private $data;

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
     * @param             $table
     * @param array|bool  $params
     * @param array|bool  $where_clause
     * @param object|null $toClass
     *
     * @return array
     */
    public function browse($table, $params = FALSE, $where_clause = FALSE, $toClass = NULL) {
        $sql = sprintf("SELECT %s FROM `%s` %s", $this->prepareColumns($params), $table,
            ($where_clause) ? $this->prepareWhereClause($where_clause) : ';');
        try {
            $this->stmt = $this->db->prepare($sql);
            $this->bindValues($params, $where_clause);
            $this->stmt->execute();
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }
        if ($toClass) {
            $this->stmt->setFetchMode(PDO::FETCH_CLASS, $toClass);
        } else {
            $this->stmt->setFetchMode(PDO::FETCH_CLASS, "stdClass");
        }

        return $this->stmt->fetchAll();
    }

    private function prepareColumns($values) {
        if ($values) {
            return "`" . implode(",`", $values) . "`";
        } else {
            return '*';
        }
    }

    /**
     * @param array $where_clause
     *
     * @return string
     */
    private function prepareWhereClause($where_clause) {
        $whereString = [];
        foreach ($where_clause as $key => $val) {
            if (is_array($val)) {
                $whereString[] = "`{$val[0]}`{$val[1]}:{$val[0]}";
            } else {
                if (is_null($val)) {
                    $whereString[] = "`{$key}` IS NULL";
                } else {
                    $whereString[] = "`{$key}`=:{$key}";
                }
            }
        }

        return "WHERE " . implode(" AND ", $whereString);
    }

    /**
     * @param mixed $params
     * @param       $where_clause
     *
     * @internal param \PDOStatement $stmt
     */
    private function bindValues($params = FALSE, $where_clause = FALSE) {
        if ($params && is_array($params)) {
            foreach ($params as $key => $val) {
                $this->stmt->bindValue($key, $val);
            }
        }
        if ($where_clause && is_array($where_clause)) {
            foreach ($where_clause as $key => $val) {
                if (is_array($val)) {
                    $this->stmt->bindValue($val[0], $val[2]);
                } else {
                    $this->stmt->bindValue($key, $val);
                }
            }
        }
    }

    /**
     * @param string     $table
     * @param bool|array $params
     * @param bool|array $where_clause
     * @param bool|Model $classBinder
     *
     * @return array|bool
     */
    public function read($table, $params = FALSE, $where_clause = FALSE, $classBinder = FALSE) {
        $sql        = sprintf("SELECT %s FROM `%s` %s", $this->prepareColumns($params), $table,
            ($where_clause) ? $this->prepareWhereClause($where_clause) : ';');
        $this->stmt = $this->db->prepare($sql);
        $this->bindValues(FALSE, $where_clause);

        if ($classBinder) {
            $this->bindAttributes($classBinder);
        }
        $this->stmt->execute();

        if ($this->stmt->rowCount() === 0) {
            return FALSE;
        }
        // todo - this is returning bool and is pretty useless
        if ($classBinder) {
            return $this->data = $this->stmt->fetch(PDO::FETCH_BOUND);
        } else {
            return $this->data = $this->stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    /**
     * @param Model|object $class
     */
    public function bindAttributes(Model $class) {
        $vars = get_object_vars($class);
        foreach (array_keys($vars) as $array_key) {
            $this->stmt->bindColumn($array_key, $class->$array_key);
        }
    }

    public function edit($table, $params, $where_clause) {
        $sql = sprintf('UPDATE `%s` SET %s %s;', $table, $this->prepareValues($params, TRUE),
            $this->prepareWhereClause($where_clause));
        try {
            $this->stmt = $this->db->prepare($sql);
            $this->bindValues($params, $where_clause);

            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw new \PDOException($this->stmt->queryString);
        }
    }

    /**
     * @param array $values
     *
     * @return string
     */
    private function prepareValues($values, $advanced = FALSE) {
        if ($advanced) {
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

    /**
     * @param string $table
     * @param        $params
     *
     * @internal param array $parameters
     * @return bool
     */
    public function add($table, $params) {
        $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s);', $table, implode('`,`', array_keys($params)),
            $this->prepareValues(array_keys($params)));
        try {
            $this->stmt = $this->db->prepare($sql);
            $this->bindValues($params);

            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage());
        }
    }

    public function delete($table, $where_clause) {
        $sql = sprintf("DELETE FROM %s %s", $table, $this->prepareWhereClause($where_clause));
        try {
            $this->stmt = $this->db->prepare($sql);
            $this->bindValues(FALSE, $where_clause);

            return $this->stmt->execute();
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage());
        }
    }

    /**
     * @return string
     */
    public function lastInsertId() {
        return $this->db->lastInsertId();
    }

    public function setTable($name) {
        $this->table = $table;
    }

    public function rowCount() {
        return $this->stmt->rowCount();
    }

    public function raw($sql, $toClass = FALSE) {
        try {
            $this->stmt = $this->db->prepare($sql);
            if ($this->stmt->execute()) {
                if ($toClass) {
                    $this->stmt->setFetchMode(PDO::FETCH_CLASS, $toClass);
                } else {
                    $this->stmt->setFetchMode(PDO::FETCH_CLASS, "stdClass");
                }

                return $this->stmt->fetchAll();
            }
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }

        return FALSE;
    }

    public function get($column) {
        return $this->data[$column];
    }

    /**
     * @param string $table_name
     *
     * @return array|string
     */
    public function getColumnNames($table_name) {
        $sql = 'SHOW COLUMNS FROM ' . $table_name;

        $this->stmt   = $this->db->prepare($sql);
        $column_names = [];
        try {
            if ($this->stmt->execute()) {
                $raw_column_data = $this->stmt->fetchAll();

                foreach ($raw_column_data as $outer_key => $array) {
                    foreach ($array as $inner_key => $value) {

                        if ($inner_key === 'Field') {
                            if ( ! (int)$inner_key) {
                                $column_names[] = $value;
                            }
                        }
                    }
                }
            }

            return $column_names;
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }
    }

}
