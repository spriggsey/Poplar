<?php


namespace Poplar\Auth;


class Login {
    static public $redirect      = '/dashboard/';
    static public $fail_redirect = '/dashboard/login';

    /**
     * @param $user_email
     * @param $login_password
     *
     * @return bool
     */
    public static function logIn($user_email, $login_password) {
        $user        = new User();
        $user->email = $user_email;
        $user->setIdentifier('email');
        // this throws and exception if error so catch instead of ifcheck
        try {
            $user->read();
        } catch (ModelException $e) {
            Input::flashErrorLog(['email' => ['Unknown Email Address']]);

            return FALSE;
        }
        if ( ! self::checkPassword($login_password, $user->password)) {
            Input::flashErrorLog(['password' => ['Password Is Invalid']]);

            return FALSE;
        }
        // user has passed verification, create a new session for this
        if ( ! Session::store($user->id)) {
            Notification::save('error', 'Unable to create a session for you, please contact support');

            return FALSE;
        }
        // we need to check if there is already a user logged in, then purge that user from the session db
        if (App::get('user')->id) {
            Session::purge(App::get('user')->id);
        }
        if (Input::get('path')) {
            // need to add the base / back as this uri is trimmed
            header("Location: /" . urldecode(Input::get('path')));
        } else {
            header("Location: " . self::$redirect);
        }
        die();
    }

    // Login::class handles register since its the same functionality...

    /**
     * @param $login_password
     * @param $db_hash
     *
     * @return bool
     */
    public static function checkPassword($login_password, $db_hash): bool {
        if (password_verify($login_password, $db_hash)) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * @return bool
     * @internal param array $user_info
     */
    public static function register() {
        $user_details = Input::except('confirm_password');
        $user         = new User();
        foreach ($user_details as $deet => $val) {
            $user->$deet = $val;
        }
        try {
            $user->add();
        } catch (ModelException $e) {
            Notification::save('error', 'Failed to sign you up! Please try again later.');

            return FALSE;
        }
        // we need to check if there is already a user logged in, then purge that user from the session db
        if (App::get('user')->id) {
            Session::destroy();
        }
        // all is fine, the new user is inside $user, so generate them a session
        Session::store($user->id);
        header('Location: ' . self::$redirect);
        die();
    }

    public static function logOut() {
        Session::destroy();
        header('Location: ' . self::$fail_redirect);
        die();
    }


}
