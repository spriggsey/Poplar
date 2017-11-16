<?php


namespace Poplar\Database;


use Poplar\Application;

class DB {
    /** @var QueryBuilder self::$db */
    private static $db;

    /**
     * @param $name
     *
     * @return QueryBuilder
     */
    public static function table($name) {
        return self::getDB()->setTable($name);
    }

    /**
     * // retrieve the query builder but reset all data first
     *
     * @return QueryBuilder
     */
    private static function getDB() {
        if (empty(self::$db)) {
            return self::$db = database()->resetData();
        } else {
            return self::$db->resetData();
        }
    }

    /**
     * @param $statement
     *
     * @return \PDOStatement
     */
    public static function raw($statement) {
        return
        self::getDB()->raw($statement);
    }

    public static function driver() {
        return
        Application::get('database')->getDriver();
    }

}
