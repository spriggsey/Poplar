<?php


namespace Poplar\Auth;


class OldSession {
    public static $session;
    public static $table_name = 'sessions';
    public static $hash;
    public static $debug_mode;

    public static function store($user_id=false) {
        if ( ! isset($_SESSION)) {
            session_start();
        }
        // first generate a hash for this session
        $client_ip = $_SERVER['REMOTE_ADDR'];

        /** @var QueryBuilder $QB */
        $QB = App::get('database');
        // save it into the db then give the hash to the session
        // if the prev session is up to date. update instead.
        if (self::dbCheck()) {
            self::generateHash();
            $id = $QB->get('id');
            // update hash where id is what was given back on the checker
            if ( ! $QB->edit(self::$table_name, ['hash' => self::$hash], ['id' => $id])) {
                throw new \Exception('Error updating new hash to the existing entry');
            }
        } else {
            self::generateHash();
            // throw a purge as we are adding a new one and we may have left one behind
            self::purge();
            if ( ! $QB->add(self::$table_name,
                ['hash' => self::$hash, 'user_id' => $user_id, 'client_ip' => $client_ip])
            ) {
                throw new \Exception('Error inserting new hash into the DB');
            }
        }
        $_SESSION['identifier'] = self::$hash;

        return TRUE;
    }

    public function token() {

    }

    /**
     * @return array|bool
     */
    public static function dbCheck() {
        if ( ! isset($_SESSION)) {
            session_start();
        }
        if (empty($_SESSION['identifier'])) {

            //            _log('false @ empty ident: Session', E_USER_NOTICE);

            return FALSE;
        }
        $client_id  = $_SERVER['REMOTE_ADDR'];
        $time_check = date('Y-m-d G:i:s', strtotime(date(date('Y-m-d G:i:s'))) - (40 * 60));
        /** @var QueryBuilder $QB */
        $QB = App::get('database');
        if ( ! $QB->read(self::$table_name, FALSE, [
            ['updated_at', '>', $time_check],
            ['hash', '=', $_SESSION['identifier']],
            ['client_ip', '=', $client_id],
        ])
        ) {
            //            _log('database failure @ check: Session', E_USER_NOTICE);

            return FALSE;
        }
        if ($_SESSION['identifier'] !== self::$hash = $QB->get('hash')) {
            //            _log('hash did not match @ check: Session', E_USER_NOTICE);

            return FALSE;
        }

        // if it gets here we need to update the timer on the db as the session is still actively being used.
        $QB->edit(self::$table_name, ['updated_at' => date('Y-m-d G:i:s')], ['id' => $QB->get('id')]);

        return [
            'hash'    => self::$hash,
            'user_id' => $QB->get('user_id'),
        ];
    }

    private static function generateHash() {
        self::$hash = hash('sha256', date('Y-m-d G:i:s') . random_bytes(10));
    }

    /**
     * @param int|string $user_id
     *
     * @return bool
     */
    public static function purge($user_id = 0) {
        $time_check = date('Y-m-d G:i:s', strtotime(date(date('Y-m-d G:i:s'))) - (40 * 60));
        /** @var QueryBuilder $QB */
        $QB = App::get('database');
        if ($user_id) {
            return $QB->delete(self::$table_name, [
                ['updated_at', '<', $time_check],
                ['user_id', '=', $user_id],
            ]);
        } else {
            return $QB->delete(self::$table_name, [
                ['updated_at', '<', $time_check],
            ]);
        }
    }

    public static function get() {
        return self::check();
    }

    public static function check() {
        if ( ! isset($_SESSION['identifier']) || ($_SESSION['identifier'] !== self::$hash)) {
            //            _log('hash did not match @ check: Session', E_USER_NOTICE);

            return FALSE;
        }

        return self::$hash;
    }

    public static function IPCheck($hash) {
        // use the hash to check if the IP is right for this users request
        $client_ip = $_SERVER['REMOTE_ADDR'];
        try {
            /** @var QueryBuilder $QB */
            $QB = App::get('database');
            $QB->read(self::$table_name, ['client_ip'], [
                ['client_ip', '=', $client_ip],
                ['hash', '=', $hash],
            ]);
            if ($QB->rowCount() == 1) {
                return TRUE;
            }
        } catch (\PDOException $e) {
            return FALSE;
        }

        return FALSE;
    }

    public static function destroy() {
        $ident = $_SESSION['identifier'];
        unset($_SESSION['identifier']);
        $QB = App::get('database');

        return $QB->delete(self::$table_name, ['hash' => $ident]);
    }
}
