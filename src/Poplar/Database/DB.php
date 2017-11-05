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

    public static function select($statement) {

    }

    public static function update($statement) {

    }

    public static function delete($statement) {

    }

    public static function insert($statement) { }

    public static function raw($statement) {

    }

}
