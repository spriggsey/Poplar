<?php

namespace App\Auth\Social;

use App\App;
use App\Auth\Login;
use App\Auth\Session;
use App\Exceptions\SocialException;
use App\Input;
use App\Models\User;

// Abstract class builds any information required by all Social media logins
abstract class Social implements SocialInterface {
    public static $table_name='social_tokens';
    protected static $provider;
    /** @var \App\Database\QueryBuilder $QB */
    protected $QB;
    protected $settings;

    function __construct() {
        // trigger a unrecoverable error if no provider is set as we cannot get the settings for this provider
        if ( ! isset(static::$provider)) {
            trigger_error("No provider variable set in class ".static::class, E_ERROR);
        }
        // we use late static binding to grab the $provider arg which is required for any instances of Social
        if (empty(App::get('config')->social->{static::$provider})) {
            trigger_error("No settings found for this provider ".static::class, E_ERROR);
        } else {
            $this->settings=App::get('config')->social->{static::$provider};
        }
        // set the DB as this is needed for all instances of Social
        $this->QB=App::get('database');
    }

    public function redirect() {
        if (Input::get('path')) {
            // if this is set, flash it forward one more time
            Input::flashOnly('path');
        }
    }


    // this function closely works with User model to find or create a user based on the email address returned.
    // It belongs here primarily because we need to look into `social_tokens` first before falling back to users table.
    // If this does not find a user via social_tokens table OR users table. it will create a brand new user with the given details
    // from a social media platform.

    // NOTE: if you are looking to bind an existing user to a new social_token, provide a user object that is used as the existing user
    // this will then create a new social_token column and link it to the existing user.
    // THIS MUST BE AN EXISTING USER, most cases this is the global $user object.
    /**
     * @param array       $social_object
     * @param bool|object $existing_user
     *
     * @throws SocialException
     * @internal param $social_email
     */
    public function findOrCreate($social_object, $existing_user=FALSE) {
        // first try find the social token before going to find the user in the users table

        $this->QB->read(self::$table_name, ['user_id'], ['social_email'=>$social_object['email'],'provider'=>static::$provider]);
        if ($this->QB->rowCount()===1) {
            // check if a user already exists and destroy it if that is the case
            if (App::get('user')->id) {
                Session::destroy();
            }
            // user has come back in the social_tokens. no need to do anything else. build the session and redirect
            // redirect with the same login redirect as it should be the same
            Session::store($this->QB->get('user_id'));
            if (Input::get('path')) {
                header('Location: /'.Input::get('path'));
            } else {
                header('Location: '.Login::$redirect);
            }
            die();
        } else {
            if ($existing_user) {
                $user=$existing_user;
            } else {
                $user=User::findOrCreate($social_object);
            }
            // now the user is given back. we need to make the token column
            try {
                $this->QB->add(self::$table_name, [
                    'user_id'     =>$user->id,
                    'provider'    =>static::$provider,
                    'token'       =>$social_object['token'],
                    'social_email'=>$user->email,
                ]);
                // now this is done, simply use the $user->id to make a new session
                // redirect with the same login redirect as it should be the same
                Session::store($user->id);
                if (Input::get('path')) {
                    header('Location: /'.Input::get('path'));
                } else {
                    header('Location: '.Login::$redirect);
                }
                die();
            } catch (\PDOException $e) {
                throw new SocialException('Cannot create new social token');
            }
        }

    }
}
