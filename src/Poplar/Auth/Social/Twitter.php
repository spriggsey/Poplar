<?php

namespace App\Auth\Social;

use Abraham\TwitterOAuth\TwitterOAuth;
use App\App;
use App\Auth\Login;
use App\Input;
use App\Notification;

class Twitter extends Social {
    public static $provider='twitter';
    private $connection;
    private $token_object;

    function __construct() {
        parent::__construct();
        $this->settings->callback=App::get('url').$this->settings->callback;
        $this->connection=new TwitterOAuth(
            $this->settings->public_key,
            $this->settings->secret_key
        );
        if (Input::get('oauth')) {
            $this->token_object=(object) Input::get('oauth');
        } else {
            $this->token_object=(object) $this->connection->oauth('oauth/request_token', ['oauth_callback'=>$this->settings->callback]);
            // set this into data to be flashed forward
            Input::setData('oauth', $this->token_object);
        }
        // twitter requires the instance to get a request token, token will most likely be flashed once created to ensure persistence
        Input::flashOnly('oauth');
    }

    public function redirect() {
        // call parent redirect first to do any global operations
        parent::redirect();
        if (isset($_SESSION['reauth'])&&$_SESSION['reauth']) {
            $url=$this->connection->url('oauth/authorize', ['oauth_token'=>$this->token_object->oauth_token]);
        } else {
            $url=$this->connection->url('oauth/authenticate', ['oauth_token'=>$this->token_object->oauth_token]);
        }
        header("Location: $url");
        die();
    }

    public function callback() {
        if (Input::get('denied')) {
            Notification::save('error', 'Authentication was denied, please try again');
            header('Location: '.Login::$fail_redirect);
            die();
        }
        if ( ! Input::get('oauth_token')) {
            return FALSE;
        }
        if ($this->token_object->oauth_token!==Input::get('oauth_token')) {
            return FALSE;
        }
        $this->connection=new TwitterOAuth(
            $this->settings->public_key,
            $this->settings->secret_key,
            $this->token_object->oauth_token,
            $this->token_object->oauth_token_secret
        );

        $this->token_object=(object) $this->connection->oauth("oauth/access_token", ['oauth_verifier'=>Input::get('oauth_verifier')]);
        $social_object=$this->retrieveUserInfo();

        $this->findOrCreate($social_object, FALSE);
    }

    private function retrieveUserInfo() {
        $this->connection=new TwitterOAuth(
            $this->settings->public_key,
            $this->settings->secret_key,
            $this->token_object->oauth_token,
            $this->token_object->oauth_token_secret
        );
        $raw_user=(object) $this->connection->get("account/verify_credentials", ["include_email"=>"true"]);
        if ( ! isset($raw_user->email)) {
            // no email given, throw them back to the start and ask for an authorize
            $_SESSION['reauth']=TRUE;
            Notification::save('error', 'Twitter did not return an email, Please try again');
            header('Location: /dashboard/login');
            die();
        } else {
            // set false as they have given email
            $_SESSION['reauth']=FALSE;
        }
        $social_object['email']=$raw_user->email;
        $social_object['first_name']=explode(" ", $raw_user->name)[0];
        $social_object['last_name']=explode(" ", $raw_user->name)[1];
        $social_object['token']=$raw_user->id;

        return $social_object;
    }
}
