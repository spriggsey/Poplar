<?php


namespace Poplar\Database;


class DB {
    private static $db;

    /**
     * @param $name
     *
     * @return QueryBuilder
     */
    public static function table($name) {
        return self::getDB()->setTable($name);
    }

    private static function getDB() {
        if (empty(self::$db)) {
            return self::$db = database();
        } else {
            return self::$db;
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

}
