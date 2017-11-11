<?php

namespace App\Auth\Social;


use App\App;
use App\Input;
use App\Notification;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Runner\Exception;

class Microsoft extends Social {
    public static $provider = 'microsoft';
    public static $tokenUrl = 'https://login.microsoftonline.com/common/oauth2/token';
    private $id_token;
    private $jwt;

    function __construct() {
        parent::__construct();
        $this->settings->callback="https://boilerplate.local/".$this->settings->callback;
    }

    public function redirect() {
        parent::redirect();
//        dd("Location: ". $this->settings->redirect."?client_id=".$this->settings->public_key."&redirect_uri=".urlencode($this->settings->callback)."&response_type=code&scope=openid%20offline_access%20https%3A%2F%2Fgraph.microsoft.com%2Fmail.read&response_mode=query");
        header("Location: ". $this->settings->redirect."?client_id=".$this->settings->public_key."&redirect_uri=".urlencode($this->settings->callback)."&response_type=code&scope=".urlencode('openid email profile')."&response_mode=query");
        die();
    }

    private function getUserInfo() {
        // decode the JWT token and grab the user information, also authenticate the token
        $raw_jwt = explode( '.', $this->id_token);
        $this->jwt['header'] = json_decode(base64_decode( $raw_jwt[0]));
        $this->jwt['body'] = json_decode( base64_decode( $raw_jwt[1]));
        $this->jwt['signature'] = json_decode( base64_decode( $raw_jwt[2]));

        // check that the issuer is correct
        if ($this->jwt['body']->aud !== $this->settings->public_key) {
            return false;
        }
        return [
            'email' => $this->jwt['body']->email,
            'first_name' => explode(' ',$this->jwt['body']->name)[0],
            'last_name' => explode( ' ', $this->jwt['body']->name)[1],
            'token' => NULL
        ];
    }

    private function getAccessToken() {
        $access_token = Input::get( 'code');
        try {
            $client = new Client([
                'base_uri' => 'https://login.microsoftonline.com'
            ]);
            $res = $client->post('common/oauth2/v2.0/token',[
                'form_params'=> [
                    'client_id'=> $this->settings->public_key,
                    'redirect_uri'=>$this->settings->callback,
                    'scope'=>'openid email profile',
                    'grant_type'=>'authorization_code',
                    'client_secret'=> $this->settings->secret_key,
                    'code'=>$access_token
                ]
            ]);
            if ($res->getStatusCode()===200) {
                $response = $res->getBody();
                // get the access token that should have been sent back here. we then need to get the user info from that token
                return $this->id_token = json_decode((string)$res->getBody())->id_token;
            }
        } catch (RequestException $e) {
            // bad response so just throw back to login page
            Notification::save('error','Error retrieving an access token from microsoft');
            header("Location: /dashboard/login");
            die();
        }
    }

    public function callback() {
        $this->getAccessToken();

        $user_obj = $this->getUserInfo();

        $this->findOrCreate($user_obj, FALSE);
    }
}
