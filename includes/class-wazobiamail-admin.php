<?php
/**
 * Admin interface, settings management, and AJAX handlers.
 *
 * @package    WazobiaMail
 * @subpackage WazobiaMail/includes
 */

defined('ABSPATH') || exit;

class WazobiaMail_Admin {

    /**
     * API Client.
     *
     * @var WazobiaMail_API_Client
     */
    private $client;

    /**
     * Domain Manager.
     *
     * @var WazobiaMail_Domain_Manager
     */
    private $domain_manager;

    /**
     * Constructor.
     *
     * @param WazobiaMail_API_Client|null      $client
     * @param WazobiaMail_Domain_Manager|null  $domain_manager
     */
    public function __construct(WazobiaMail_API_Client $client = null, WazobiaMail_Domain_Manager $domain_manager = null) {
        $this->client = $client ? $client : new WazobiaMail_API_Client();
        $this->domain_manager = $domain_manager ? $domain_manager : new WazobiaMail_Domain_Manager($this->client);
    }

    /**
     * Initialize WordPress admin hooks.
     */
    public function register() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_form_actions']);
        add_action('admin_notices', [$this, 'render_admin_notices']);

        // AJAX handlers
        add_action('wp_ajax_wazobiamail_register_domain', [$this, 'ajax_register_domain']);
        add_action('wp_ajax_wazobiamail_verify_dns', [$this, 'ajax_verify_dns']);
        add_action('wp_ajax_wazobiamail_send_test_email', [$this, 'ajax_send_test_email']);
        add_action('wp_ajax_wazobiamail_clear_logs', [$this, 'ajax_clear_logs']);
    }

    /**
     * Register Admin Menu item under Settings.
     */
    public function add_admin_menu() {
        add_options_page(
            __('WazobiaMail Settings', 'wazobiamail-transactional-email-deliverability'),
            __('WazobiaMail', 'wazobiamail-transactional-email-deliverability'),
            'manage_options',
            'wazobiamail',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Display configuration warning notice if API key is missing.
     */
    public function render_admin_notices() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        $is_wazobia_page = $screen && $screen->id === 'settings_page_wazobiamail';

        if (!$this->client->is_configured()) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('WazobiaMail is not configured.', 'wazobiamail-transactional-email-deliverability'); ?></strong>
                    <?php esc_html_e('An API key is mandatory to route WordPress transactional emails through WazobiaMail.', 'wazobiamail-transactional-email-deliverability'); ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=wazobiamail')); ?>">
                        <?php esc_html_e('Configure Settings &rarr;', 'wazobiamail-transactional-email-deliverability'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Handle manual settings form POST submissions.
     */
    public function handle_form_actions() {
        if (!isset($_POST['wazobiamail_save_settings'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized user action.', 'wazobiamail-transactional-email-deliverability'));
        }

        check_admin_referer('wazobiamail_settings_action', 'wazobiamail_nonce');

        $options = get_option('wazobiamail_options', []);

        // 1. Mandatory API Key
        $raw_key = isset($_POST['api_key']) ? trim((string) $_POST['api_key']) : '';
        // If the user left it masked (*** or ...), preserve existing saved key
        if (strpos($raw_key, '***') === false && strpos($raw_key, '...') === false && !empty($raw_key)) {
            $options['api_key'] = sanitize_text_field($raw_key);
        }

        // 2. From Name & Email
        $options['from_name']  = isset($_POST['from_name']) ? sanitize_text_field($_POST['from_name']) : get_bloginfo('name');
        $options['from_email'] = isset($_POST['from_email']) ? sanitize_email($_POST['from_email']) : get_option('admin_email');

        // 3. Base URL & Mail Mode
        $options['base_url']   = !empty($_POST['base_url']) ? esc_url_raw(trim($_POST['base_url'])) : WazobiaMail_API_Client::DEFAULT_BASE_URL;
        $options['mail_mode']  = (isset($_POST['mail_mode']) && $_POST['mail_mode'] === 'disabled') ? 'disabled' : 'api';
        $options['enable_logs'] = !empty($_POST['enable_logs']);

        update_option('wazobiamail_options', $options);

        // Auto-register domain if requested and not yet registered
        if (!empty($_POST['auto_register_on_save']) && empty($options['domain_id']) && !empty($options['api_key'])) {
            $dm = new WazobiaMail_Domain_Manager(new WazobiaMail_API_Client($options['api_key'], $options['base_url']));
            $dm->register_site_domain();
        }

        wp_safe_redirect(add_query_arg([
            'page'    => 'wazobiamail',
            'updated' => 'true',
        ], admin_url('options-general.php')));

        if (!defined('WAZOBIAMAIL_TESTING')) {
            exit;
        }
    }

    /**
     * Render the admin settings page view.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wazobiamail-transactional-email-deliverability'));
        }

        $options        = get_option('wazobiamail_options', []);
        $domain_info    = $this->domain_manager->get_domain_info();
        $logs           = get_option('wazobiamail_delivery_logs', []);
        $masked_api_key = WazobiaMail_API_Client::mask_secret(!empty($options['api_key']) ? $options['api_key'] : '');
        $dns_data       = !empty($domain_info['domain_id']) ? $this->domain_manager->get_dns_records() : null;

        include dirname(__DIR__) . '/templates/admin-settings.php';
    }

    /**
     * AJAX handler: Register site domain with WazobiaMail.
     */
    public function ajax_register_domain() {
        check_ajax_referer('wazobiamail_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized user.', 'wazobiamail-transactional-email-deliverability'), 403);
            return;
        }

        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : null;
        $res = $this->domain_manager->register_site_domain($domain);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res['error']);
        }
        return;
    }

    /**
     * AJAX handler: Trigger DNS verification.
     */
    public function ajax_verify_dns() {
        check_ajax_referer('wazobiamail_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized user.', 'wazobiamail-transactional-email-deliverability'), 403);
            return;
        }

        $res = $this->domain_manager->verify_dns();

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res['error']);
        }
        return;
    }

    /**
     * AJAX handler: Send instant test email.
     */
    public function ajax_send_test_email() {
        check_ajax_referer('wazobiamail_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized user.', 'wazobiamail-transactional-email-deliverability'), 403);
            return;
        }

        $recipient = isset($_POST['recipient']) ? sanitize_email($_POST['recipient']) : '';
        if (empty($recipient) || !is_email($recipient)) {
            wp_send_json_error(__('Please enter a valid recipient email address.', 'wazobiamail-transactional-email-deliverability'));
            return;
        }

        $mailer = new WazobiaMail_Mailer($this->client);
        /* translators: %s: WordPress site name. */
        $subject = sprintf(__('[WazobiaMail Test] Verification Email from %s', 'wazobiamail-transactional-email-deliverability'), get_bloginfo('name'));
        $body = sprintf(
            "<h2>%s</h2><p>%s</p><ul><li><strong>Site:</strong> %s</li><li><strong>Time:</strong> %s</li><li><strong>Status:</strong> Connected to WazobiaMail API Gateway</li></ul><p>%s</p>",
            __('Congratulations! WazobiaMail is working!', 'wazobiamail-transactional-email-deliverability'),
            __('Your WordPress site is now successfully configured to deliver transactional emails through the high-deliverability WazobiaMail REST API.', 'wazobiamail-transactional-email-deliverability'),
            esc_html(get_bloginfo('name')),
            esc_html(current_time('mysql')),
            __('Deliverability stats, read receipts, and click receipts will be tracked automatically.', 'wazobiamail-transactional-email-deliverability')
        );

        $res = $mailer->send($recipient, $subject, $body, ['Content-Type: text/html']);

        if ($res['success']) {
            wp_send_json_success([
                /* translators: 1: recipient email address, 2: tracking ID. */
                'message'     => sprintf(__('Test email sent to %1$s! Tracking ID: %2$s', 'wazobiamail-transactional-email-deliverability'), $recipient, $res['tracking_id']),
                'tracking_id' => $res['tracking_id'],
            ]);
        } else {
            wp_send_json_error($res['error']);
        }
        return;
    }

    /**
     * AJAX handler: Clear delivery logs.
     */
    public function ajax_clear_logs() {
        check_ajax_referer('wazobiamail_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized user.', 'wazobiamail-transactional-email-deliverability'), 403);
            return;
        }

        update_option('wazobiamail_delivery_logs', []);
        wp_send_json_success(__('Delivery logs cleared.', 'wazobiamail-transactional-email-deliverability'));
        return;
    }
}
