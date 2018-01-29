<?php


namespace Poplar;


use Poplar\Database\DB;
use Poplar\Database\QueryException;
use Poplar\Exceptions\SessionException;

class Session {
    public static  $session;
    public static  $table_name = 'sessions';
    public static  $hash = NULL;
    public static  $debug_mode;
    private static $db_entry;

    private static function getLocalHash() {
        if ( ! isset($_SESSION)) {
            session_start();
        }
        // cascade down and check all places that we will store hash, if not set then go to next and populate the parent
        return  self::$hash ?? self::$hash = $_SESSION['identifier'] ?? self::$hash = $_SESSION['identifier'] = self::generateHash();
    }

    public static function store($user_id = FALSE) {
        if ($user_id) {
            return self::storeInDB($user_id);
        }
        return false;
    }

    private static function storeInDB($user_id) {
        if ( ! isset($_SESSION)) {
            session_start();
        }
        // first generate a hash for this session
        $client_ip = $_SERVER['REMOTE_ADDR'];

        // save it into the db then give the hash to the session
        // if the prev session is up to date. update instead.
        if (self::dbCheck()) {
            return self::dbUpdateHashEntry();
        }

        self::purge($user_id);
        if (self::dbCreateNewEntry($user_id, $client_ip)) {
            $_SESSION['identifier'] = self::$hash;
        }

        return TRUE;
    }

    /**
     * @return array|bool
     */
    public static function dbCheck() {
        if ( ! isset($_SESSION)) {
            session_start();
        }
        if (empty($_SESSION['identifier'])) {
            return FALSE;
        }
        $client_id  = $_SERVER['REMOTE_ADDR'];
        $time_check = date('Y-m-d G:i:s', strtotime(date(date('Y-m-d G:i:s'))) - (40 * 60));
        $db_check   = DB::table(self::$table_name)->where([
            ['updated_at', '>', $time_check],
            ['hash', '=', $_SESSION['identifier']],
            ['client_ip', '=', $client_id],
        ])->get();
        if ($db_check->isEmpty()) {
            return FALSE;
        }
        self::$db_entry = $db_check->first();

        if ($_SESSION['identifier'] !== self::$hash = self::$db_entry->hash) {
            return FALSE;
        }

        // if it gets here we need to update the timer on the db as the session is still actively being used.
        DB::table(self::$table_name)->where(['id' => self::$db_entry->id])
            ->update(['updated_at' => date('Y-m-d H:i:s')]);
        return [
            'hash'    => self::$hash,
            'user_id' => self::$db_entry->user_id,
        ];
    }

    /**
     * @return bool
     * @throws SessionException
     */
    public static function dbUpdateHashEntry() {
        try {
            self::generateHash();

            return DB::table(self::$table_name)->where(['id' => self::$db_entry->id])->update(['hash' => self::$hash]);
        } catch (QueryException $e) {
            throw new SessionException($e);
        }
    }

    /**
     * @param int|string $user_id
     *
     * @return bool
     */
    public static function purge($user_id = 0) {
        $time_check = date('Y-m-d G:i:s', strtotime(date(date('Y-m-d G:i:s'))) - (40 * 60));

        if ($user_id) {
            return DB::table(self::$table_name)->where([
                ['updated_at', '<', $time_check],
                ['user_id', '=', $user_id],
            ])->delete();
        }

        return DB::table(self::$table_name)->where([
            ['updated_at', '<', $time_check]
        ])->delete();
    }

    public static function dbCreateNewEntry($user_id, $client_ip) {
        try {
            return DB::table(self::$table_name)->insert([
                'hash'      => self::$hash,
                'user_id'   => $user_id,
                'client_ip' => $client_ip
            ]);
        } catch (QueryException $e) {
            throw new SessionException($e);
        }
    }

    private static function generateHash() {
        return  self::$hash = hash('sha256', date('Y-m-d G:i:s') . random_bytes(10));
    }

    public static function get($type) {
        if ($type == 'local') {
            return self::$hash??self::getLocalHash();
        }
        if ($type == 'db') {
            return self::$hash??self::dbCheck()['hash'];
        }
    }

    public static function destroy() { }
}
