<?php


namespace Poplar\Database;

use PDO;
use PDOException;

class Connection {
    private $pdo;
    private $type;
    private $host;
    private $name;
    private $username;
    private $password;
    private $options;

    private $statements = [];

    /**
     * Connection constructor.
     *
     * @param $type
     * @param $host
     * @param $name
     * @param $username
     * @param $password
     * @param $options
     */
    public function __construct($type, $host, $name, $username, $password, $options) {
        $this->type     = $type;
        $this->host     = $host;
        $this->name     = $name;
        $this->username = $username;
        $this->password = $password;
        $this->options  = $options;
        try {
            $this->pdo =
                new PDO("{$this->type}:host={$this->host};dbname={$this->name}", $this->username, $this->password,
                    $this->options);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * @param mixed $name
     *
     * @return Connection
     */
    public function setName($name) {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $username
     *
     * @return Connection
     */
    public function setUsername($username) {
        $this->username = $username;

        return $this;
    }

    /**
     * @param string $password
     *
     * @return Connection
     */
    public function setPassword($password) {
        $this->password = $password;

        return $this;
    }

    /**
     * @param array $options
     *
     * @return Connection
     */
    public function setOptions($options) {
        $this->options = $options;

        return $this;
    }

    /**
     * @return self
     */
    public function reconnect() {
        try {
            $this->pdo =
                new PDO("{$this->type}:host={$this->host};dbname={$this->name}", $this->username, $this->password,
                    $this->options);

            return $this;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * @param string $type
     *
     * @return Connection
     */
    public function setType($type) {
        $this->type = $type;

        return $this;
    }

    /**
     * @param string $host
     *
     * @return Connection
     */
    public function setHost($host) {
        $this->host = $host;

        return $this;
    }

    public function setTestMode() {
        return $this->pdo->beginTransaction();
    }

    public function exitTestMode() {
        return $this->pdo->rollBack();
    }
}
