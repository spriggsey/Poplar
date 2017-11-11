<?php


namespace Poplar;


class Notification {
    public static $log   = [];
    public static $types = ['error', 'notification'];

    public static function push($type, $message) {
        if ( ! isset(static::$log[$type])) {
            static::$log[$type] = [];
        }
        static::$log[$type][] = $message;
    }

    public static function get($type) {
        if ( ! empty(static::$log[$type])) {
            return static::$log[$type];
        }

        return FALSE;
    }

    // used to build out the html for the front end
    public static function build() {
        if ( ! empty(static::$log)) {
            $html = "<div class='notify_block'>";
            foreach (static::$types as $type) {
                if ( ! empty(static::$log[$type])) {
                    foreach ((array) static::$log[$type] as $msg) {
                        $html .= "<div class='{$type}_block'><i class='icon-megaphone'></i>";
                        $html .= "<p class='{$type}_message'>{$msg}</p>";
                        $html .= "<i id class='icon-cancel {$type}_close'></i></div>";
                    }
                }
            }
            $html .= '</div>';
            echo $html;
        }
    }

    // ability to save an error or notification to the next view

    /**
     * @param 'error'|'notification' $type
     * @param $message
     */
    public static function save($type, $message) {
        if ( ! isset($_SESSION['notification'])) {
            $_SESSION['notification'] = [];
        }
        if ( ! isset($_SESSION['notification'][$type])) {
            $_SESSION['notification'][$type] = [];
        }
        $_SESSION['notification'][$type][] = $message;
    }

    // get the notifications out of the session storage and unset them.
    public static function retrieve() {
        if ( ! empty($_SESSION['notification'])) {
            foreach (static::$types as $type) {
                if ( ! empty($_SESSION['notification'][$type])) {
                    if ( ! isset(static::$log[$type])) {
                        static::$log[$type] = [];
                    }
                    foreach ((array) $_SESSION['notification'][$type] as $key => $item) {
                        static::$log[$type][] = $item;
                        unset($_SESSION['notification'][$type][$key]);
                    }
                }
            }
        }
    }
}
