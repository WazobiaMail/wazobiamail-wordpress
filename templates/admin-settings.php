<?php
/**
 * Admin Settings Template.
 *
 * @package    WazobiaMail
 * @subpackage WazobiaMail/templates
 */

defined('ABSPATH') || exit;

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
$nonce = wp_create_nonce('wazobiamail_admin_nonce');
?>

<div class="wrap wazobiamail-settings-wrap">
    <style>
        .wazobiamail-table-scroll { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .wazobiamail-table-scroll > table { min-width: 680px; }
    </style>
    <h1>
        <span class="dashicons dashicons-email-alt" style="font-size:32px; width:32px; height:32px; vertical-align:middle; margin-right:8px; color:#2271b1;"></span>
        <?php esc_html_e('WazobiaMail Transactional Email Settings', 'wazobiamail-transactional-email-deliverability'); ?>
        <span class="wazobiamail-badge version-badge">v0.1.0</span>
    </h1>

    <p class="description">
        <?php esc_html_e('Reliable, high-deliverability transactional email infrastructure for WordPress. Replaces local mail with direct WazobiaMail REST API dispatch.', 'wazobiamail-transactional-email-deliverability'); ?>
        <a href="https://docs.wazobiamail.com" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Platform Documentation &rarr;', 'wazobiamail-transactional-email-deliverability'); ?>
        </a>
    </p>

    <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('WazobiaMail settings updated successfully.', 'wazobiamail-transactional-email-deliverability'); ?></p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=wazobiamail&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('API & Sender', 'wazobiamail-transactional-email-deliverability'); ?>
        </a>
        <a href="?page=wazobiamail&tab=domain" class="nav-tab <?php echo $active_tab === 'domain' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-networking"></span> <?php esc_html_e('Site Domain & DNS', 'wazobiamail-transactional-email-deliverability'); ?>
        </a>
        <a href="?page=wazobiamail&tab=test" class="nav-tab <?php echo $active_tab === 'test' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-arrow-right-alt"></span> <?php esc_html_e('Send Test Email', 'wazobiamail-transactional-email-deliverability'); ?>
        </a>
        <a href="?page=wazobiamail&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Delivery Logs', 'wazobiamail-transactional-email-deliverability'); ?>
        </a>
    </h2>

    <div class="wazobiamail-tab-content" style="margin-top:20px; max-width:1000px;">
        <?php if ($active_tab === 'general') : ?>
            <!-- Tab 1: General API & Sender Settings -->
            <form method="post" action="">
                <?php wp_nonce_field('wazobiamail_settings_action', 'wazobiamail_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="api_key"><?php esc_html_e('WazobiaMail API Key', 'wazobiamail-transactional-email-deliverability'); ?> <span class="required" style="color:#d63638;">*</span></label>
                            </th>
                            <td>
                                <input name="api_key" type="password" id="api_key" value="<?php echo !empty($options['api_key']) ? esc_attr($masked_api_key) : ''; ?>" class="regular-text" placeholder="wzbm_live_..." required autocomplete="off" />
                                <button type="button" class="button button-secondary" id="wazobiamail-toggle-key" onclick="toggleApiKeyVisibility()"><?php esc_html_e('Show/Hide', 'wazobiamail-transactional-email-deliverability'); ?></button>
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
                                <input name="from_name" type="text" id="from_name" value="<?php echo esc_attr(!empty($options['from_name']) ? $options['from_name'] : get_bloginfo('name')); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Display name used on outbound emails (e.g. My WordPress Store).', 'wazobiamail-transactional-email-deliverability'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="from_email"><?php esc_html_e('Default From Email', 'wazobiamail-transactional-email-deliverability'); ?></label>
                            </th>
                            <td>
                                <input name="from_email" type="email" id="from_email" value="<?php echo esc_attr(!empty($options['from_email']) ? $options['from_email'] : get_option('admin_email')); ?>" class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e('Email address used as the sender. Must match a verified domain in WazobiaMail.', 'wazobiamail-transactional-email-deliverability'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Mail Dispatch Mode', 'wazobiamail-transactional-email-deliverability'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="mail_mode" value="api" <?php checked(!isset($options['mail_mode']) || $options['mail_mode'] === 'api'); ?> />
                                    <strong><?php esc_html_e('Active (WazobiaMail REST API)', 'wazobiamail-transactional-email-deliverability'); ?></strong> &mdash; <?php esc_html_e('Routes all wp_mail emails through WazobiaMail.', 'wazobiamail-transactional-email-deliverability'); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="radio" name="mail_mode" value="disabled" <?php checked(isset($options['mail_mode']) && $options['mail_mode'] === 'disabled'); ?> />
                                    <?php esc_html_e('Disabled &mdash; Fall back to default WordPress mailer.', 'wazobiamail-transactional-email-deliverability'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Delivery Logging', 'wazobiamail-transactional-email-deliverability'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_logs" value="1" <?php checked(!isset($options['enable_logs']) || !empty($options['enable_logs'])); ?> />
                                    <?php esc_html_e('Capture outgoing email tracking IDs, delivery receipts, and timestamps locally (keeps latest 50).', 'wazobiamail-transactional-email-deliverability'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="base_url"><?php esc_html_e('API Gateway Base URL', 'wazobiamail-transactional-email-deliverability'); ?></label>
                            </th>
                            <td>
                                <input name="base_url" type="url" id="base_url" value="<?php echo esc_attr(!empty($options['base_url']) ? $options['base_url'] : WazobiaMail_API_Client::DEFAULT_BASE_URL); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Advanced: Default is https://app.wazobiamail.com/api/v1.', 'wazobiamail-transactional-email-deliverability'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" name="wazobiamail_save_settings" class="button button-primary"><?php esc_html_e('Save Changes', 'wazobiamail-transactional-email-deliverability'); ?></button>
                </p>
            </form>

        <?php elseif ($active_tab === 'domain') : ?>
            <!-- Tab 2: Site Domain & DNS Records -->
            <div class="card" style="margin-top:0;">
                <h2><?php esc_html_e('Site Domain Registration & Verification', 'wazobiamail-transactional-email-deliverability'); ?></h2>
                <p>
                    <?php esc_html_e('WazobiaMail requires sending domains to be registered and verified with SPF and DKIM DNS records to ensure 100% inbox deliverability.', 'wazobiamail-transactional-email-deliverability'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Detected Site Domain', 'wazobiamail-transactional-email-deliverability'); ?></th>
                        <td>
                            <code><?php echo esc_html($domain_info['detected']); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Registered Domain ID', 'wazobiamail-transactional-email-deliverability'); ?></th>
                        <td>
                            <?php if (!empty($domain_info['domain_id'])) : ?>
                                <code><?php echo esc_html($domain_info['domain_id']); ?></code>
                            <?php else : ?>
                                <em><?php esc_html_e('Not yet registered with WazobiaMail.', 'wazobiamail-transactional-email-deliverability'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Verification Status', 'wazobiamail-transactional-email-deliverability'); ?></th>
                        <td>
                            <?php if ($domain_info['domain_status'] === 'verified') : ?>
                                <span class="badge" style="background:#00a32a; color:#fff; padding:4px 10px; border-radius:12px; font-weight:600;">
                                    &#10003; <?php esc_html_e('Verified & Active', 'wazobiamail-transactional-email-deliverability'); ?>
                                </span>
                            <?php elseif ($domain_info['domain_status'] === 'pending_dns') : ?>
                                <span class="badge" style="background:#dba617; color:#fff; padding:4px 10px; border-radius:12px; font-weight:600;">
                                    &#9203; <?php esc_html_e('Pending DNS Setup / Propagation', 'wazobiamail-transactional-email-deliverability'); ?>
                                </span>
                            <?php else : ?>
                                <span class="badge" style="background:#72aee6; color:#fff; padding:4px 10px; border-radius:12px; font-weight:600;">
                                    <?php esc_html_e('Unregistered', 'wazobiamail-transactional-email-deliverability'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div style="margin: 20px 0;">
                    <button type="button" class="button button-primary" id="btn-register-domain" onclick="handleRegisterDomain()">
                        <span class="dashicons dashicons-cloud-upload" style="vertical-align:text-bottom;"></span>
                        <?php esc_html_e('Register Site Domain with WazobiaMail', 'wazobiamail-transactional-email-deliverability'); ?>
                    </button>

                    <button type="button" class="button button-secondary" id="btn-verify-dns" onclick="handleVerifyDns()" <?php echo empty($domain_info['domain_id']) ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-update" style="vertical-align:text-bottom;"></span>
                        <?php esc_html_e('Verify DNS Records Now', 'wazobiamail-transactional-email-deliverability'); ?>
                    </button>
                </div>

                <div id="domain-action-result" style="margin-top:15px; display:none;"></div>

                <?php if (!empty($dns_data['records'])) : ?>
                    <hr style="margin:25px 0;" />
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
                                <?php foreach ($dns_data['records'] as $record) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html(isset($record['recordType']) ? $record['recordType'] : $record['type']); ?></strong></td>
                                        <td><code><?php echo esc_html(isset($record['name']) ? $record['name'] : $record['host']); ?></code></td>
                                        <td style="word-break:break-all;"><code><?php echo esc_html(isset($record['value']) ? $record['value'] : $record['target']); ?></code></td>
                                        <td>
                                            <?php if (!empty($record['verified']) || (isset($record['status']) && $record['status'] === 'verified')) : ?>
                                                <span style="color:#00a32a; font-weight:600;">&#10003; <?php esc_html_e('Verified', 'wazobiamail-transactional-email-deliverability'); ?></span>
                                            <?php else : ?>
                                                <span style="color:#dba617; font-weight:600;">&#9203; <?php esc_html_e('Pending', 'wazobiamail-transactional-email-deliverability'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'test') : ?>
            <!-- Tab 3: Send Test Email -->
            <div class="card" style="margin-top:0;">
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
                    <button type="button" class="button button-primary" id="btn-send-test-email" onclick="handleSendTestEmail()">
                        <span class="dashicons dashicons-email" style="vertical-align:text-bottom;"></span>
                        <?php esc_html_e('Send Test Email', 'wazobiamail-transactional-email-deliverability'); ?>
                    </button>
                    <span class="spinner" id="test-email-spinner" style="float:none; margin-top:4px;"></span>
                </p>

                <div id="test-email-result" style="margin-top:15px; display:none;"></div>
            </div>

        <?php elseif ($active_tab === 'logs') : ?>
            <!-- Tab 4: Delivery Logs & Receipts -->
            <div class="card" style="margin-top:0;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h2><?php esc_html_e('Recent Transactional Email Receipts', 'wazobiamail-transactional-email-deliverability'); ?></h2>
                    <button type="button" class="button button-secondary" onclick="handleClearLogs()"><?php esc_html_e('Clear Logs', 'wazobiamail-transactional-email-deliverability'); ?></button>
                </div>
                <p class="description">
                    <?php esc_html_e('Real-time delivery receipts recorded for emails sent from WordPress.', 'wazobiamail-transactional-email-deliverability'); ?>
                </p>

                <?php if (empty($logs)) : ?>
                    <p><em><?php esc_html_e('No emails dispatched yet.', 'wazobiamail-transactional-email-deliverability'); ?></em></p>
                <?php else : ?>
                    <div class="wazobiamail-table-scroll">
                        <table class="widefat striped" style="margin-top:15px;">
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
                                <?php foreach ($logs as $log) : ?>
                                    <tr>
                                        <td><?php echo esc_html($log['timestamp']); ?></td>
                                        <td><?php echo esc_html($log['recipient']); ?></td>
                                        <td><?php echo esc_html($log['subject']); ?></td>
                                        <td><code><?php echo esc_html($log['tracking_id']); ?></code></td>
                                        <td>
                                            <?php if ($log['status'] === 'queued' || $log['status'] === 'delivered') : ?>
                                                <span style="color:#00a32a; font-weight:600;">&#10003; <?php echo esc_html(ucfirst($log['status'])); ?></span>
                                            <?php else : ?>
                                                <span style="color:#d63638; font-weight:600;">&#9888; <?php echo esc_html(ucfirst($log['status'])); ?></span>
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

<script>
function toggleApiKeyVisibility() {
    var field = document.getElementById('api_key');
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}

function handleRegisterDomain() {
    var btn = document.getElementById('btn-register-domain');
    var resDiv = document.getElementById('domain-action-result');
    btn.disabled = true;
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div class="notice notice-info"><p><?php esc_html_e('Registering site domain with WazobiaMail API...', 'wazobiamail-transactional-email-deliverability'); ?></p></div>';

    var data = new URLSearchParams();
    data.append('action', 'wazobiamail_register_domain');
    data.append('nonce', '<?php echo esc_js($nonce); ?>');

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled = false;
        if (res.success) {
            resDiv.innerHTML = '<div class="notice notice-success"><p>' + res.data.message + '</p></div>';
            setTimeout(function() { window.location.reload(); }, 1500);
        } else {
            resDiv.innerHTML = '<div class="notice notice-error"><p>' + res.data + '</p></div>';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        resDiv.innerHTML = '<div class="notice notice-error"><p>' + err.message + '</p></div>';
    });
}

function handleVerifyDns() {
    var btn = document.getElementById('btn-verify-dns');
    var resDiv = document.getElementById('domain-action-result');
    btn.disabled = true;
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div class="notice notice-info"><p><?php esc_html_e('Querying DNS verification...', 'wazobiamail-transactional-email-deliverability'); ?></p></div>';

    var data = new URLSearchParams();
    data.append('action', 'wazobiamail_verify_dns');
    data.append('nonce', '<?php echo esc_js($nonce); ?>');

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled = false;
        if (res.success) {
            resDiv.innerHTML = '<div class="notice notice-success"><p>' + res.data.message + '</p></div>';
            setTimeout(function() { window.location.reload(); }, 1500);
        } else {
            resDiv.innerHTML = '<div class="notice notice-error"><p>' + res.data + '</p></div>';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        resDiv.innerHTML = '<div class="notice notice-error"><p>' + err.message + '</p></div>';
    });
}

function handleSendTestEmail() {
    var btn = document.getElementById('btn-send-test-email');
    var spinner = document.getElementById('test-email-spinner');
    var resDiv = document.getElementById('test-email-result');
    var email = document.getElementById('test_recipient').value;

    if (!email) {
        alert('<?php esc_html_e('Please enter a recipient email address.', 'wazobiamail-transactional-email-deliverability'); ?>');
        return;
    }

    btn.disabled = true;
    spinner.classList.add('is-active');
    resDiv.style.display = 'block';
    resDiv.innerHTML = '<div class="notice notice-info"><p><?php esc_html_e('Dispatching test email via WazobiaMail REST API...', 'wazobiamail-transactional-email-deliverability'); ?></p></div>';

    var data = new URLSearchParams();
    data.append('action', 'wazobiamail_send_test_email');
    data.append('nonce', '<?php echo esc_js($nonce); ?>');
    data.append('recipient', email);

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled = false;
        spinner.classList.remove('is-active');
        if (res.success) {
            resDiv.innerHTML = '<div class="notice notice-success"><p>' + res.data.message + '</p></div>';
        } else {
            resDiv.innerHTML = '<div class="notice notice-error"><p><strong><?php esc_html_e('Failed:', 'wazobiamail-transactional-email-deliverability'); ?></strong> ' + res.data + '</p></div>';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        spinner.classList.remove('is-active');
        resDiv.innerHTML = '<div class="notice notice-error"><p>' + err.message + '</p></div>';
    });
}

function handleClearLogs() {
    if (!confirm('<?php esc_html_e('Are you sure you want to clear delivery logs?', 'wazobiamail-transactional-email-deliverability'); ?>')) {
        return;
    }

    var data = new URLSearchParams();
    data.append('action', 'wazobiamail_clear_logs');
    data.append('nonce', '<?php echo esc_js($nonce); ?>');

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
    })
    .then(function() { window.location.reload(); });
}
</script>
