<?php

namespace Poplar;


use Poplar\Exceptions\RepositoryException;

abstract class Repository implements RepositoryInterface {

    /**
     * @var
     */
    protected $model;

    /**
     * Repository constructor.
     *
     * @throws RepositoryException
     */
    public function __construct() {
        $this->makeModel();
    }

    /**
     * @return mixed
     */
    abstract function model();

    /**
     * @throws RepositoryException
     */
    public function makeModel() {
        $model = new $this->model();

        if (!$model instanceof Model) {
            throw new RepositoryException("Class {$this->model()} must be an instance of \\Poplar\\Model");
        }
        $this->model = $model;
    }
}
