<?php
/**
 * Plugin Name:       WazobiaMail - Transactional Email & Deliverability
 * Plugin URI:        https://docs.wazobiamail.com/docs/guides/sdks/wordpress
 * Description:       Send WordPress email through the WazobiaMail API. Start free with 3,000 emails/month. A Resend alternative that also supports SMTP, with automatic domain setup, DNS verification, and open/click receipts.
 * Version:           0.1.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WazobiaMail
 * Author URI:        https://wazobiamail.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wazobiamail
 * Domain Path:       /languages
 *
 * @package           WazobiaMail
 */

defined('ABSPATH') || exit;

define('WAZOBIAMAIL_VERSION', '0.1.1');
define('WAZOBIAMAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WAZOBIAMAIL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WAZOBIAMAIL_BASENAME', plugin_basename(__FILE__));

// Require class files
require_once WAZOBIAMAIL_PLUGIN_DIR . 'includes/class-wazobiamail-activator.php';
require_once WAZOBIAMAIL_PLUGIN_DIR . 'includes/class-wazobiamail-deactivator.php';
require_once WAZOBIAMAIL_PLUGIN_DIR . 'includes/class-wazobiamail-api-client.php';
require_once WAZOBIAMAIL_PLUGIN_DIR . 'includes/class-wazobiamail-domain-manager.php';
require_once WAZOBIAMAIL_PLUGIN_DIR . 'includes/class-wazobiamail-mailer.php';
require_once WAZOBIAMAIL_PLUGIN_DIR . 'includes/class-wazobiamail-admin.php';

// Activation & Deactivation hooks
register_activation_hook(__FILE__, ['WazobiaMail_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['WazobiaMail_Deactivator', 'deactivate']);

/**
 * Bootstrap the WazobiaMail plugin runtime.
 */
function wazobiamail_init() {
    // 1. Initialize and register the mail interceptor
    $mailer = new WazobiaMail_Mailer();
    $mailer->register();

    // 2. Initialize WordPress admin components
    if (is_admin()) {
        $admin = new WazobiaMail_Admin();
        $admin->register();

        // Add settings shortcut link to plugins table
        add_filter('plugin_action_links_' . WAZOBIAMAIL_BASENAME, 'wazobiamail_add_action_links');
    }
}
add_action('plugins_loaded', 'wazobiamail_init');

/**
 * Load bundled translations.
 */
function wazobiamail_load_textdomain() {
    load_plugin_textdomain('wazobiamail', false, dirname(WAZOBIAMAIL_BASENAME) . '/languages');
}
add_action('init', 'wazobiamail_load_textdomain');

/**
 * Add settings action link to the Plugins listing table.
 *
 * @param array $links
 * @return array
 */
function wazobiamail_add_action_links($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('options-general.php?page=wazobiamail')),
        esc_html__('Settings', 'wazobiamail')
    );
    array_unshift($links, $settings_link);
    return $links;
}
