<?php

namespace Poplar;

interface RepositoryInterface {

    public function all($columns = ['*']);

    public function paginate($perPage = 15, $columns = ['*']);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findBy($field, $value, $columns = ['*']);
}
