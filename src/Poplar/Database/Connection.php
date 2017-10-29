<?php


namespace Poplar\Database;

use PDO;

class Connection {
    public static function make($db_config) {
        try {
            $env = App::get('env');

            return new PDO("{$db_config->{$env}->type}:host={$db_config->{$env}->host};dbname={$db_config->{$env}->name}",
                $db_config->{$env}->username, $db_config->{$env}->password, $db_config->{$env}->opts);
        } catch (\PDOException $e) {
            throw new ConnectionException();
        }
    }
}