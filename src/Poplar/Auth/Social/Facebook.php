<?php

namespace App\Auth\Social;

use App\App;
use App\Input;
use App\Notification;

class Facebook extends Social {
    public static $provider='facebook';

    function __construct() {
        parent::__construct();
        $this->settings->callback=App::get('url').$this->settings->callback;
    }

    public function redirect() {
        // call parent redirect first to do any global operations
        parent::redirect();
        // for facebook we can force reauthentication if needed.
        if (isset($_SESSION['reauth'])&&$_SESSION['reauth']) {
            header("Location: ".$this->settings->redirect."?client_id=".$this->settings->public_key."&redirect_uri=".$this->settings->callback."&auth_type=rerequest&scope=email");
        } else {
            header("Location: ".$this->settings->redirect."?client_id=".$this->settings->public_key."&redirect_uri=".$this->settings->callback."&scope=email");
        }
        die();
    }

    public function callback() {

        // get the code from FB via the get header
        $login_code=Input::get('code');
        $access_token=$this->getAccessToken($login_code);
        $social_object=$this->populateFromToken($access_token);

        // do a bit of translation on the ID given back by facebook, put this in the token area
        $social_object['token']=$social_object['id'];
        if ( ! isset($social_object['email'])) {
            // no email given, throw them back to the start and ask for an authorize
            $_SESSION['reauth']=TRUE;
            Notification::save('error', 'Facebook did not return an email, Please try again');
            header('Location: /dashboard/login');
            die();
        } else {
            // set false as they have given email
            $_SESSION['reauth']=FALSE;
        }
        // unset this as its interfering with the real ID
        unset($social_object['id']);
        // this just needs to be called as it will find, create and handle redirects all in one.
        // as long as it gets all the data it needs in the $social_object
        $this->findOrCreate($social_object, FALSE);
    }

    private function getAccessToken($login_code) {
        // curl the facebook api to request an access token for the user
        $curl=curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL           =>"https://graph.facebook.com/v2.9/oauth/access_token?client_id="
                                    .$this->settings->public_key.
                                    "&redirect_uri="
                                    .$this->settings->callback.
                                    "&client_secret="
                                    .$this->settings->secret_key.
                                    "&code={$login_code}",
            CURLOPT_RETURNTRANSFER=>TRUE,
            CURLOPT_ENCODING      =>"",
            CURLOPT_MAXREDIRS     =>10,
            CURLOPT_TIMEOUT       =>30,
            CURLOPT_HTTP_VERSION  =>CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>"GET",
            CURLOPT_HTTPHEADER    =>[
                "cache-control: no-cache",
            ],
        ]);

        $response=curl_exec($curl);
        $err=curl_error($curl);

        curl_close($curl);
        if ($err) {
            // todo - probably need to log this for debug mode
            return FALSE;
        } else {
            return json_decode($response)->access_token;
        }
    }

    private function populateFromToken($access_token) {
        $curl=curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL           =>"https://graph.facebook.com/v2.9/me?fields=email,first_name,last_name&input_token=&access_token="
                                    .$access_token,
            CURLOPT_RETURNTRANSFER=>TRUE,
            CURLOPT_ENCODING      =>"",
            CURLOPT_MAXREDIRS     =>10,
            CURLOPT_TIMEOUT       =>30,
            CURLOPT_HTTP_VERSION  =>CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST =>"GET",
            CURLOPT_HTTPHEADER    =>[
                "cache-control: no-cache",
            ],
        ]);

        $response=curl_exec($curl);
        $err=curl_error($curl);

        curl_close($curl);
        if ($err) {
            return FALSE;
        } else {
            $social_object=json_decode($response, TRUE);

            return $social_object;
        }
    }

    public function parse() {

    }
}
