<?php
/**
 * Fired during plugin activation.
 *
 * @package    WazobiaMail
 * @subpackage WazobiaMail/includes
 */

defined('ABSPATH') || exit;

class WazobiaMail_Activator {

    /**
     * Default options for the WazobiaMail plugin.
     *
     * @var array
     */
    public static function get_default_options() {
        return [
            'api_key'              => '',
            'from_name'            => get_bloginfo('name'),
            'from_email'           => get_option('admin_email'),
            'base_url'             => 'https://app.wazobiamail.com/api/v1',
            'mail_mode'            => 'api',
            'domain_id'            => '',
            'domain_name'          => '',
            'domain_status'        => 'unregistered',
            'auto_register_domain' => true,
            'enable_logs'          => true,
        ];
    }

    /**
     * Execute activation routines.
     */
    public static function activate() {
        $existing = get_option('wazobiamail_options', []);
        $defaults = self::get_default_options();

        if (empty($existing) || !is_array($existing)) {
            add_option('wazobiamail_options', $defaults);
        } else {
            $merged = array_merge($defaults, $existing);
            update_option('wazobiamail_options', $merged);
        }

        // Initialize empty logs transient/option if absent
        if (false === get_option('wazobiamail_delivery_logs')) {
            add_option('wazobiamail_delivery_logs', []);
        }
    }
}
