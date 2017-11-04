<?php


namespace Poplar\Database;

use PDO;

class Connection {

    private $config;
    private $connection = NULL;
    private $driver;

    public function __construct() {
        // we don't want to instantly make a connection as it might never be used
        // so we will simply store on construct and await usage in the query builder

        $this->driver = env('DB_CONNECTION');
        // look for the env vars, they do not need to be set.
        $this->config = config("database.{$this->driver}", '');
    }

    public function connect() {
        try {
            return $this->connection ?? $this->connection = $this->buildQB();
        } catch (\PDOException $e) {
            throw new ConnectionException($e);
        }
    }

    private function buildQB() {
        $pdo =
            new PDO("{$this->config['driver']}:dbname={$this->config['database']};host={$this->config['host']};port={$this->config['port']}",
                $this->config['username'], $this->config['password'], $this->config['opts']);

        return new QueryBuilder($pdo);
    }
}
