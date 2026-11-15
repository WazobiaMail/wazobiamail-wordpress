<?php
/**
 * Uninstall routine for the WazobiaMail plugin.
 *
 * Removes plugin settings, delivery logs and cached DNS data. Multisite
 * installations are cleaned per site.
 *
 * @package WazobiaMail
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Delete WazobiaMail data for the current site.
 *
 * @return void
 */
function wazobiamail_uninstall_site() {
    delete_option('wazobiamail_options');
    delete_option('wazobiamail_delivery_logs');
    delete_transient('wazobiamail_dns_records_cache');
}

if (is_multisite()) {
    $wazobiamail_site_ids = get_sites(
        [
            'fields' => 'ids',
            'number' => 0,
        ]
    );

    foreach ($wazobiamail_site_ids as $wazobiamail_site_id) {
        switch_to_blog((int) $wazobiamail_site_id);
        wazobiamail_uninstall_site();
        restore_current_blog();
    }
} else {
    wazobiamail_uninstall_site();
}
