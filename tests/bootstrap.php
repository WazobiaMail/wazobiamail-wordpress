<?php
/**
 * PHPUnit test bootstrap for WazobiaMail WordPress Plugin.
 */

define('WAZOBIAMAIL_TESTING', true);

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/wordpress/');
}

// Global mock storage
class WPMockState {
    public static $options = [];
    public static $transients = [];
    public static $actions = [];
    public static $filters = [];
    public static $remote_responses = [];
    public static $last_remote_request = null;
    public static $current_user_can_manage_options = true;
    public static $current_user_email = 'admin@example.com';
    public static $json_responses = [];

    public static function reset() {
        self::$options = [];
        self::$transients = [];
        self::$actions = [];
        self::$filters = [];
        self::$remote_responses = [];
        self::$last_remote_request = null;
        self::$current_user_can_manage_options = true;
        self::$current_user_email = 'admin@example.com';
        self::$json_responses = [];
    }

    public static function queue_response($code, $body = [], $headers = []) {
        self::$remote_responses[] = [
            'response' => ['code' => $code],
            'body'     => is_string($body) ? $body : json_encode($body),
            'headers'  => $headers,
        ];
    }
}

class WP_Error {
    public $code;
    public $message;
    public $data;

    public function __construct($code = '', $message = '', $data = '') {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_message() {
        return $this->message;
    }

    public function get_error_code() {
        return $this->code;
    }
}

// WordPress mock functions
function get_option($key, $default = false) {
    return array_key_exists($key, WPMockState::$options) ? WPMockState::$options[$key] : $default;
}

function update_option($key, $value) {
    WPMockState::$options[$key] = $value;
    return true;
}

function add_option($key, $value) {
    if (!array_key_exists($key, WPMockState::$options)) {
        WPMockState::$options[$key] = $value;
        return true;
    }
    return false;
}

function delete_option($key) {
    unset(WPMockState::$options[$key]);
    return true;
}

function get_transient($key) {
    return array_key_exists($key, WPMockState::$transients) ? WPMockState::$transients[$key] : false;
}

function set_transient($key, $value, $expiration = 0) {
    WPMockState::$transients[$key] = $value;
    return true;
}

function delete_transient($key) {
    unset(WPMockState::$transients[$key]);
    return true;
}

function get_site_url() {
    return 'https://example.com';
}

function get_bloginfo($show = 'name') {
    if ($show === 'url') {
        return 'https://example.com';
    }
    return 'My WordPress Test Site';
}

function admin_url($path = '') {
    return 'https://example.com/wp-admin/' . ltrim($path, '/');
}

function add_query_arg($args, $url = '') {
    if (is_array($args)) {
        $sep = strpos($url, '?') !== false ? '&' : '?';
        return $url . $sep . http_build_query($args);
    }
    return $url;
}

function current_time($type = 'mysql') {
    return date('Y-m-d H:i:s');
}

function wp_parse_url($url, $component = -1) {
    return parse_url($url, $component);
}

function sanitize_text_field($str) {
    return trim(strip_tags((string) $str));
}

function sanitize_email($email) {
    return filter_var(trim((string) $email), FILTER_SANITIZE_EMAIL);
}

function is_email($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function esc_url_raw($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

function wp_strip_all_tags($text) {
    return trim(strip_tags((string) $text));
}

function wp_rand($min = 0, $max = 0) {
    if ($max <= 0) {
        return random_int(0, max(1, $min));
    }
    return random_int(min($min, $max), max($min, $max));
}

function wp_generate_password($length = 12, $special = true) {
    return substr(md5(uniqid((string) mt_rand(), true)), 0, $length);
}

function wp_json_encode($data) {
    return json_encode($data);
}

function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

function wp_remote_request($url, $args = []) {
    WPMockState::$last_remote_request = [
        'url'  => $url,
        'args' => $args,
    ];

    if (!empty(WPMockState::$remote_responses)) {
        return array_shift(WPMockState::$remote_responses);
    }

    return [
        'response' => ['code' => 200],
        'body'     => json_encode(['success' => true]),
        'headers'  => [],
    ];
}

function wp_remote_retrieve_response_code($response) {
    if (is_wp_error($response) || !isset($response['response']['code'])) {
        return 0;
    }
    return (int) $response['response']['code'];
}

function wp_remote_retrieve_body($response) {
    if (is_wp_error($response) || !isset($response['body'])) {
        return '';
    }
    return (string) $response['body'];
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    WPMockState::$actions[$hook][] = $callback;
}

function do_action($hook, ...$args) {
    if (!empty(WPMockState::$actions[$hook])) {
        foreach (WPMockState::$actions[$hook] as $callback) {
            call_user_func_array($callback, $args);
        }
    }
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
    WPMockState::$filters[$hook][] = $callback;
}

function apply_filters($hook, $value, ...$args) {
    if (!empty(WPMockState::$filters[$hook])) {
        foreach (WPMockState::$filters[$hook] as $callback) {
            $value = call_user_func_array($callback, array_merge([$value], $args));
        }
    }
    return $value;
}

function current_user_can($cap) {
    if ($cap === 'manage_options') {
        return WPMockState::$current_user_can_manage_options;
    }
    return false;
}

function wp_create_nonce($action = -1) {
    return 'mock_nonce_' . $action;
}

function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
    $field = '<input type="hidden" name="' . esc_attr($name) . '" value="' . wp_create_nonce($action) . '" />';
    if ($echo) {
        echo $field;
    }
    return $field;
}

function wp_verify_nonce($nonce, $action = -1) {
    return true;
}

function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
    return true;
}

function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
    return true;
}

function wp_send_json_success($data = null) {
    WPMockState::$json_responses[] = ['success' => true, 'data' => $data];
}

function wp_send_json_error($data = null, $status_code = null) {
    WPMockState::$json_responses[] = ['success' => false, 'data' => $data, 'status' => $status_code];
}

function wp_safe_redirect($location, $status = 302) {
    return true;
}

function wp_die($message = '') {
    throw new Exception("wp_die: " . $message);
}

function wp_unslash($val) {
    return is_string($val) ? stripslashes($val) : $val;
}

function __($text, $domain = 'default') {
    return $text;
}

function _e($text, $domain = 'default') {
    echo $text;
}

function esc_html__($text, $domain = 'default') {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_html_e($text, $domain = 'default') {
    echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_html($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_url($url) {
    return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
}

function esc_js($text) {
    return addslashes((string) $text);
}

function checked($checked, $current = true, $echo = true) {
    $result = ((string) $checked === (string) $current) ? "checked='checked'" : '';
    if ($echo) {
        echo $result;
    }
    return $result;
}

function is_admin() {
    return true;
}

function get_current_screen() {
    $screen = new stdClass();
    $screen->id = 'settings_page_wazobiamail';
    return $screen;
}

function wp_get_current_user() {
    $user = new stdClass();
    $user->user_email = WPMockState::$current_user_email;
    return $user;
}

function plugin_dir_path($file) {
    return dirname(__DIR__) . '/';
}

function plugin_dir_url($file) {
    return 'https://example.com/wp-content/plugins/wazobiamail/';
}

function plugin_basename($file) {
    return 'wazobiamail/wazobiamail.php';
}

function register_activation_hook($file, $callback) {}
function register_deactivation_hook($file, $callback) {}

// Load plugin files
require_once __DIR__ . '/../includes/class-wazobiamail-activator.php';
require_once __DIR__ . '/../includes/class-wazobiamail-deactivator.php';
require_once __DIR__ . '/../includes/class-wazobiamail-api-client.php';
require_once __DIR__ . '/../includes/class-wazobiamail-domain-manager.php';
require_once __DIR__ . '/../includes/class-wazobiamail-mailer.php';
require_once __DIR__ . '/../includes/class-wazobiamail-admin.php';
require_once __DIR__ . '/../wazobiamail.php';
