<?php

namespace App\Auth\Social;

use App\App;
use App\Input;
use App\Notification;
use \Guzzlehttp\Client;
use GuzzleHttp\Exception\RequestException;

class Google extends Social {
    public static $provider = 'google';
    private $client;
    private $access_token;

    function __construct() {
        parent::__construct();
        $this->client = new Client([
            'base_uri' => 'https://www.googleapis.com'
        ]);
        $this->settings->callback = 'http://boilerplate.com/' . $this->settings->callback;
    }

    public function redirect() {
        parent::redirect();

        header("Location: " . $this->settings->redirect . "?scope=" . urlencode('profile email') . "&access_type=online&include_granted_scopes=true&redirect_uri=" . urlencode($this->settings->callback) . "&response_type=code&client_id={$this->settings->public_key}");
        die();
    }

    private function getUserInfo() {
        try {
            $res = $this->client->get('userinfo/v2/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->access_token
                ]
            ]);

            if ($res->getStatusCode() === 200) {
                $response = $res->getBody();
                $json = json_decode((string)$response);
                return [
                    'email'=> $json->email,
                    'first_name' => $json->given_name,
                    'last_name' => $json->family_name,
                    'token' => NULL
                ];
            }
        } catch (RequestException $e) {
            Notification::save('error','Error retrieving user information from google');
            header("Location: /dashboard/login");
            die();
        }
    }

    private function getAccessToken() {
        $access_token = Input::get('code');
        try {
            $res = $this->client->post('oauth2/v4/token', [
                'form_params' => [
                    'client_id' => $this->settings->public_key,
                    'redirect_uri' => $this->settings->callback,
                    'client_secret' => $this->settings->secret_key,
                    'code' => $access_token,
                    'grant_type' => 'authorization_code'
                ]
            ]);
            if ($res->getStatusCode() === 200) {
                $response = $res->getBody();
                // get the access token that should have been sent back here. we then need to get the user info from that token
                return $this->access_token = json_decode((string)$res->getBody())->access_token;
            }
        } catch (RequestException $e) {
            // bad response so just throw back to login page
            Notification::save('error','Error retrieving access token from google');
            header("Location: /dashboard/login");
            die();
        }
    }

    public function callback() {
        if (Input::get('error')) {
            Notification::save('error',Input::get('error'));
            header('Location: /dashboard/login');
            die();
        }
        if (empty(Input::get('code'))) {
            Notification::save('error','No Code given back from google');
            header('Location: /dashboard/login');
            die();
        }
        $this->getAccessToken();

        $user_info = $this->getUserInfo();

        $this->findOrCreate($user_info);
    }
}
