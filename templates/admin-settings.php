<?php
/**
 * Admin Settings Template.
 *
 * @package    WazobiaMail
 * @subpackage WazobiaMail/templates
 */

defined('ABSPATH') || exit;

$wazobiamail_allowed_tabs = ['general', 'domain', 'test', 'logs'];
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation parameter validated against an explicit allowlist.
$wazobiamail_raw_tab      = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
$wazobiamail_active_tab   = in_array($wazobiamail_raw_tab, $wazobiamail_allowed_tabs, true) ? $wazobiamail_raw_tab : 'general';

$wazobiamail_settings_updated = false;
if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'wazobiamail_settings_updated')) {
    $wazobiamail_settings_updated = isset($_GET['updated']) && sanitize_text_field(wp_unslash($_GET['updated'])) === 'true';
}
?>

<div class="wrap wazobiamail-settings-wrap">
    <h1>
        <span class="dashicons dashicons-email-alt wazobiamail-heading-icon"></span>
        <?php esc_html_e('WazobiaMail Transactional Email Settings', 'wazobiamail-transactional-email-deliverability'); ?>
        <span class="wazobiamail-badge version-badge">v<?php echo esc_html(WAZOBIAMAIL_VERSION); ?></span>
    </h1>

    <p class="description">
        <?php esc_html_e('Reliable, high-deliverability transactional email infrastructure for WordPress. Replaces local mail with direct WazobiaMail REST API dispatch.', 'wazobiamail-transactional-email-deliverability'); ?>
        <a href="https://docs.wazobiamail.com" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Platform Documentation', 'wazobiamail-transactional-email-deliverability'); ?> &rarr;
        </a>
    </p>

    <?php if ($wazobiamail_settings_updated) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('WazobiaMail settings updated successfully.', 'wazobiamail-transactional-email-deliverability'); ?></p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('options-general.php?page=wazobiamail&tab=general')); ?>" class="nav-tab <?php echo $wazobiamail_active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('API & Sender', 'wazobiamail-transactional-email-deliverability'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('options-general.php?page=wazobiamail&tab=domain')); ?>" class="nav-tab <?php echo $wazobiamail_active_tab === 'domain' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-networking"></span> <?php esc_html_e('Site Domain & DNS', 'wazobiamail-transactional-email-deliverability'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('options-general.php?page=wazobiamail&tab=test')); ?>" class="nav-tab <?php echo $wazobiamail_active_tab === 'test' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-arrow-right-alt"></span> <?php esc_html_e('Send Test Email', 'wazobiamail-transactional-email-deliverability'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('options-general.php?page=wazobiamail&tab=logs')); ?>" class="nav-tab <?php echo $wazobiamail_active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Delivery Logs', 'wazobiamail-transactional-email-deliverability'); ?>
        </a>
    </h2>

    <div class="wazobiamail-tab-content">
        <?php if ($wazobiamail_active_tab === 'general') : ?>
            <!-- Tab 1: General API & Sender Settings -->
            <form method="post" action="<?php echo esc_url(admin_url('options-general.php?page=wazobiamail')); ?>">
                <?php wp_nonce_field('wazobiamail_settings_action', 'wazobiamail_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="api_key"><?php esc_html_e('WazobiaMail API Key', 'wazobiamail-transactional-email-deliverability'); ?> <span class="required wazobiamail-required">*</span></label>
                            </th>
                            <td>
                                <input name="api_key" type="password" id="api_key" value="<?php echo !empty($wazobiamail_options['api_key']) ? esc_attr($wazobiamail_masked_api_key) : ''; ?>" class="regular-text" placeholder="wzbm_live_..." required autocomplete="new-password" />
                                <button type="button" class="button button-secondary" id="wazobiamail-toggle-key"><?php esc_html_e('Show/Hide', 'wazobiamail-transactional-email-deliverability'); ?></button>
                                <p class="description">
                                    <?php esc_html_e('Mandatory. Enter your API Key prefixed with wzbm_. You can generate or copy keys in your', 'wazobiamail-transactional-email-deliverability'); ?>
                                    <a href="https://app.wazobiamail.com/dashboard/smtp-api" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e('WazobiaMail Dashboard', 'wazobiamail-transactional-email-deliverability'); ?>
                                    </a>.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="from_name"><?php esc_html_e('Default From Name', 'wazobiamail-transactional-email-deliverability'); ?></label>
                            </th>
                            <td>
                                <input name="from_name" type="text" id="from_name" value="<?php echo esc_attr(!empty($wazobiamail_options['from_name']) ? $wazobiamail_options['from_name'] : get_bloginfo('name')); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Display name used on outbound emails (e.g. My WordPress Store).', 'wazobiamail-transactional-email-deliverability'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="from_email"><?php esc_html_e('Default From Email', 'wazobiamail-transactional-email-deliverability'); ?></label>
                            </th>
                            <td>
                                <input name="from_email" type="email" id="from_email" value="<?php echo esc_attr(!empty($wazobiamail_options['from_email']) ? $wazobiamail_options['from_email'] : get_option('admin_email')); ?>" class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e('Email address used as the sender. Must match a verified domain in WazobiaMail.', 'wazobiamail-transactional-email-deliverability'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Mail Dispatch Mode', 'wazobiamail-transactional-email-deliverability'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="mail_mode" value="api" <?php checked(!isset($wazobiamail_options['mail_mode']) || $wazobiamail_options['mail_mode'] === 'api'); ?> />
                                    <strong><?php esc_html_e('Active (WazobiaMail REST API)', 'wazobiamail-transactional-email-deliverability'); ?></strong> &mdash; <?php esc_html_e('Routes all wp_mail emails through WazobiaMail.', 'wazobiamail-transactional-email-deliverability'); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="radio" name="mail_mode" value="disabled" <?php checked(isset($wazobiamail_options['mail_mode']) && $wazobiamail_options['mail_mode'] === 'disabled'); ?> />
                                    <?php esc_html_e('Disabled', 'wazobiamail-transactional-email-deliverability'); ?> &mdash; <?php esc_html_e('Fall back to default WordPress mailer.', 'wazobiamail-transactional-email-deliverability'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Delivery Logging', 'wazobiamail-transactional-email-deliverability'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_logs" value="1" <?php checked(!isset($wazobiamail_options['enable_logs']) || !empty($wazobiamail_options['enable_logs'])); ?> />
                                    <?php esc_html_e('Capture outgoing email tracking IDs, delivery receipts, and timestamps locally (keeps latest 50).', 'wazobiamail-transactional-email-deliverability'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="base_url"><?php esc_html_e('API Gateway Base URL', 'wazobiamail-transactional-email-deliverability'); ?></label>
                            </th>
                            <td>
                                <input name="base_url" type="url" id="base_url" value="<?php echo esc_attr(!empty($wazobiamail_options['base_url']) ? $wazobiamail_options['base_url'] : WazobiaMail_API_Client::DEFAULT_BASE_URL); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Advanced: Default is https://app.wazobiamail.com/api/v1.', 'wazobiamail-transactional-email-deliverability'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" name="wazobiamail_save_settings" class="button button-primary"><?php esc_html_e('Save Changes', 'wazobiamail-transactional-email-deliverability'); ?></button>
                </p>
            </form>

        <?php elseif ($wazobiamail_active_tab === 'domain') : ?>
            <!-- Tab 2: Site Domain & DNS Records -->
            <div class="card wazobiamail-card">
                <h2><?php esc_html_e('Site Domain Registration & Verification', 'wazobiamail-transactional-email-deliverability'); ?></h2>
                <p>
                    <?php esc_html_e('WazobiaMail requires sending domains to be registered and verified with SPF and DKIM DNS records to ensure 100% inbox deliverability.', 'wazobiamail-transactional-email-deliverability'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Detected Site Domain', 'wazobiamail-transactional-email-deliverability'); ?></th>
                        <td>
                            <code><?php echo esc_html($wazobiamail_domain_info['detected']); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Registered Domain ID', 'wazobiamail-transactional-email-deliverability'); ?></th>
                        <td>
                            <?php if (!empty($wazobiamail_domain_info['domain_id'])) : ?>
                                <code><?php echo esc_html($wazobiamail_domain_info['domain_id']); ?></code>
                            <?php else : ?>
                                <em><?php esc_html_e('Not yet registered with WazobiaMail.', 'wazobiamail-transactional-email-deliverability'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Verification Status', 'wazobiamail-transactional-email-deliverability'); ?></th>
                        <td>
                            <?php if ($wazobiamail_domain_info['domain_status'] === 'verified') : ?>
                                <span class="wazobiamail-badge wazobiamail-badge--verified">
                                    &#10003; <?php esc_html_e('Verified & Active', 'wazobiamail-transactional-email-deliverability'); ?>
                                </span>
                            <?php elseif ($wazobiamail_domain_info['domain_status'] === 'pending_dns') : ?>
                                <span class="wazobiamail-badge wazobiamail-badge--pending">
                                    &#9203; <?php esc_html_e('Pending DNS Setup / Propagation', 'wazobiamail-transactional-email-deliverability'); ?>
                                </span>
                            <?php else : ?>
                                <span class="wazobiamail-badge wazobiamail-badge--unregistered">
                                    <?php esc_html_e('Unregistered', 'wazobiamail-transactional-email-deliverability'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div class="wazobiamail-domain-actions">
                    <button type="button" class="button button-primary" id="btn-register-domain">
                        <span class="dashicons dashicons-cloud-upload wazobiamail-btn-icon"></span>
                        <?php esc_html_e('Register Site Domain with WazobiaMail', 'wazobiamail-transactional-email-deliverability'); ?>
                    </button>

                    <button type="button" class="button button-secondary" id="btn-verify-dns" <?php echo empty($wazobiamail_domain_info['domain_id']) ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-update wazobiamail-btn-icon"></span>
                        <?php esc_html_e('Verify DNS Records Now', 'wazobiamail-transactional-email-deliverability'); ?>
                    </button>
                </div>

                <div id="domain-action-result" class="wazobiamail-action-result"></div>

                <?php if (!empty($wazobiamail_dns_data['records'])) : ?>
                    <hr class="wazobiamail-divider" />
                    <h3><?php esc_html_e('Required DNS Records', 'wazobiamail-transactional-email-deliverability'); ?></h3>
                    <p class="description">
                        <?php esc_html_e('Add the following DNS records to your domain DNS provider (e.g., Cloudflare, Route53, Namecheap, cPanel):', 'wazobiamail-transactional-email-deliverability'); ?>
                    </p>
                    <div class="wazobiamail-table-scroll">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Type', 'wazobiamail-transactional-email-deliverability'); ?></th>
                                    <th><?php esc_html_e('Host / Name', 'wazobiamail-transactional-email-deliverability'); ?></th>
                                    <th><?php esc_html_e('Value / Target', 'wazobiamail-transactional-email-deliverability'); ?></th>
                                    <th><?php esc_html_e('Status', 'wazobiamail-transactional-email-deliverability'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wazobiamail_dns_data['records'] as $wazobiamail_record) : ?>
                                    <?php
                                    $wazobiamail_record_type  = isset($wazobiamail_record['recordType']) ? $wazobiamail_record['recordType'] : (isset($wazobiamail_record['type']) ? $wazobiamail_record['type'] : '');
                                    $wazobiamail_record_name  = isset($wazobiamail_record['name']) ? $wazobiamail_record['name'] : (isset($wazobiamail_record['host']) ? $wazobiamail_record['host'] : '');
                                    $wazobiamail_record_value = isset($wazobiamail_record['value']) ? $wazobiamail_record['value'] : (isset($wazobiamail_record['target']) ? $wazobiamail_record['target'] : '');
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($wazobiamail_record_type); ?></strong></td>
                                        <td><code><?php echo esc_html($wazobiamail_record_name); ?></code></td>
                                        <td class="wazobiamail-dns-value"><code><?php echo esc_html($wazobiamail_record_value); ?></code></td>
                                        <td>
                                            <?php if (!empty($wazobiamail_record['verified']) || (isset($wazobiamail_record['status']) && $wazobiamail_record['status'] === 'verified')) : ?>
                                                <span class="wazobiamail-status wazobiamail-status--ok">&#10003; <?php esc_html_e('Verified', 'wazobiamail-transactional-email-deliverability'); ?></span>
                                            <?php else : ?>
                                                <span class="wazobiamail-status wazobiamail-status--pending">&#9203; <?php esc_html_e('Pending', 'wazobiamail-transactional-email-deliverability'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($wazobiamail_active_tab === 'test') : ?>
            <!-- Tab 3: Send Test Email -->
            <div class="card wazobiamail-card">
                <h2><?php esc_html_e('Send a Test Email', 'wazobiamail-transactional-email-deliverability'); ?></h2>
                <p>
                    <?php esc_html_e('Dispatch a test email through WazobiaMail to verify your API credentials, DNS records, and delivery receipt tracking.', 'wazobiamail-transactional-email-deliverability'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_recipient"><?php esc_html_e('Recipient Email Address', 'wazobiamail-transactional-email-deliverability'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="test_recipient" class="regular-text" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" placeholder="you@example.com" />
                            <p class="description"><?php esc_html_e('Enter the email address where the test email should be delivered.', 'wazobiamail-transactional-email-deliverability'); ?></p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="button" class="button button-primary" id="btn-send-test-email">
                        <span class="dashicons dashicons-email wazobiamail-btn-icon"></span>
                        <?php esc_html_e('Send Test Email', 'wazobiamail-transactional-email-deliverability'); ?>
                    </button>
                    <span class="spinner wazobiamail-spinner" id="test-email-spinner"></span>
                </p>

                <div id="test-email-result" class="wazobiamail-action-result"></div>
            </div>

        <?php elseif ($wazobiamail_active_tab === 'logs') : ?>
            <!-- Tab 4: Delivery Logs & Receipts -->
            <div class="card wazobiamail-card">
                <div class="wazobiamail-logs-header">
                    <h2><?php esc_html_e('Recent Transactional Email Receipts', 'wazobiamail-transactional-email-deliverability'); ?></h2>
                    <button type="button" class="button button-secondary" id="btn-clear-logs"><?php esc_html_e('Clear Logs', 'wazobiamail-transactional-email-deliverability'); ?></button>
                </div>
                <p class="description">
                    <?php esc_html_e('Real-time delivery receipts recorded for emails sent from WordPress.', 'wazobiamail-transactional-email-deliverability'); ?>
                </p>

                <?php if (empty($wazobiamail_logs) || !is_array($wazobiamail_logs)) : ?>
                    <p><em><?php esc_html_e('No emails dispatched yet.', 'wazobiamail-transactional-email-deliverability'); ?></em></p>
                <?php else : ?>
                    <div class="wazobiamail-table-scroll">
                        <table class="widefat striped wazobiamail-logs-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Date/Time', 'wazobiamail-transactional-email-deliverability'); ?></th>
                                    <th><?php esc_html_e('Recipient', 'wazobiamail-transactional-email-deliverability'); ?></th>
                                    <th><?php esc_html_e('Subject', 'wazobiamail-transactional-email-deliverability'); ?></th>
                                    <th><?php esc_html_e('Tracking ID', 'wazobiamail-transactional-email-deliverability'); ?></th>
                                    <th><?php esc_html_e('Status', 'wazobiamail-transactional-email-deliverability'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wazobiamail_logs as $wazobiamail_log) : ?>
                                    <?php
                                    $wazobiamail_log_status = isset($wazobiamail_log['status']) ? (string) $wazobiamail_log['status'] : 'unknown';
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html(isset($wazobiamail_log['timestamp']) ? $wazobiamail_log['timestamp'] : ''); ?></td>
                                        <td><?php echo esc_html(isset($wazobiamail_log['recipient']) ? $wazobiamail_log['recipient'] : ''); ?></td>
                                        <td><?php echo esc_html(isset($wazobiamail_log['subject']) ? $wazobiamail_log['subject'] : ''); ?></td>
                                        <td><code><?php echo esc_html(isset($wazobiamail_log['tracking_id']) ? $wazobiamail_log['tracking_id'] : ''); ?></code></td>
                                        <td>
                                            <?php if ($wazobiamail_log_status === 'queued' || $wazobiamail_log_status === 'delivered') : ?>
                                                <span class="wazobiamail-status wazobiamail-status--ok">&#10003; <?php echo esc_html(ucfirst($wazobiamail_log_status)); ?></span>
                                            <?php else : ?>
                                                <span class="wazobiamail-status wazobiamail-status--error">&#9888; <?php echo esc_html(ucfirst($wazobiamail_log_status)); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
