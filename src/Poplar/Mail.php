<?php

namespace App;

use App\Exceptions\ModelException;
use App\Models\User;
use PHPMailer\PHPMailer\PHPMailer;
use Poplar\Config;

class Mail extends PHPMailer {
    public static $disable;

    public function __construct($exceptions=NULL) {
        parent::__construct($exceptions);
        $config=Config::get('mail');
        $this->isSMTP();
        $this->Host=$config->{$env}->host;
        $this->SMTPAuth=TRUE;
        $this->Username=$config->{$env}->username;
        $this->Password=$config->{$env}->password;
        $this->SMTPSecure=$config->{$env}->enc_type;
        $this->Port=$config->{$env}->port;
        $this->setFrom($config->from, $config->from_name);
    }


    /**
     * @param object|string $user_id - this can either be an instance of User or a user ID
     *
     * @return bool
     */
    public function sendVerification($user_id) {
        $vars=['site_name', 'user_name', 'auth_url'];
        $template_url= __DIR__ . '/../email_templates/verify.temp.php';
        $site_name=App::get('config')->site_name;
        if ($user_id instanceof User) {
            // skip getting user via ID as the whole object was returned instead
            $user=$user_id;
        } else {
            $user=new Models\User();
            $user->id=$user_id;
            try {
                $user->read();
            } catch (ModelException $e) {
                return FALSE;
            }
        }
        $user_name="{$user->first_name} {$user->last_name}";
        $verify_hash=$user->verification_token;
        $auth_url=App::get('url')."verify-email?token={$verify_hash}";

        $tpl=file_get_contents($template_url);
        foreach ($vars as $var) {
            $tpl=str_replace("{{{$var}}}", ${$var}, $tpl);
        }
        $this::clearAddresses();
        $this->addAddress($user->email, $user_name);
        $this->Body=$tpl;
        $this->Subject="{$site_name} - Verify your email address";
        $this->AltBody="Hello {$user_name}, Please verify your email address for {$site_name} by clicking the link below.

$auth_url

If you did not sign up to {$site_name} then please ignore this email.";
        if ( ! $this->send()) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    // override the send function to allow us to not send any mail if set
    public function send() {
        if (static::$disable) {
            return TRUE;
        }

        return parent::send();
    }
}
