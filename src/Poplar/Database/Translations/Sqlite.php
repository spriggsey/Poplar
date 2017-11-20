<?php
namespace Poplar\Database\Translations;

use PDO;
use Poplar\Database\DB;

class Sqlite extends Translation {
    static function columns($table) {
        $sql = <<<SQL
PRAGMA table_info({$table});
SQL;
        $output =
        DB::raw($sql)->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($line) {
            return $line['name'];
        },$output);
    }
}
