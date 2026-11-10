<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    WazobiaMail
 * @subpackage WazobiaMail/includes
 */

defined('ABSPATH') || exit;

class WazobiaMail_Deactivator {

    /**
     * Cleanups on deactivation.
     */
    public static function deactivate() {
        // Flush any active transients
        delete_transient('wazobiamail_dns_records_cache');
    }
}
