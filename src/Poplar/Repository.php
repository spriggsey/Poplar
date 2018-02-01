<?php

namespace Poplar;


use Poplar\Exceptions\RepositoryException;

abstract class Repository implements RepositoryInterface {

    /**
     * @var Model
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

    public function all($columns = ['*']) {
        return $this->model->get($columns);
    }

    public function paginate($perPage = 15, $columns = ['*']) {
        // TODO: Implement paginate() method.
    }

    /**
     * @param array $data
     *
     * @return bool|Model
     * @throws Database\QueryException
     * @throws \ReflectionException
     */
    public function create(array $data) {
        return $this->model::create($data);
    }

    /**
     * @param array $data
     * @param       $id
     *
     * @return Model
     * @throws Database\QueryException
     * @throws Exceptions\ModelException
     * @throws \ReflectionException
     */
    public function update(array $data, $id) {
        return $this->model::update($data,$id);
    }

    /**
     * @param $id
     *
     * @return int
     * @throws Database\QueryException
     */
    public function delete($id) {
        return $this->model::destroy($id);
    }

    /**
     * @param $id
     *
     * @return mixed|static
     * @throws \ReflectionException
     */
    public function find($id) {
        return $this->model::find($id);
    }

    public function findBy($field, $value, $columns = ['*']) {
        return $this->model::where([$field=>$value])->get($columns);
    }
}
