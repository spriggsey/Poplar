<?php
namespace Poplar\Database\Translations;

use PDO;
use Poplar\Database\DB;

class Mysql extends Translation {
    static function columns($table) {
        $sql = <<<SQL
        SHOW COLUMNS FROM {$table};
SQL;
        $output = DB::raw($sql)->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($line){
            return $line['Field'];
        },$output);
    }
}
