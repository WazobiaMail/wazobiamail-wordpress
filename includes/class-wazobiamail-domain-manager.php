<?php
/**
 * Handles WordPress site domain registration and DNS verification with WazobiaMail.
 *
 * @package    WazobiaMail
 * @subpackage WazobiaMail/includes
 */

defined('ABSPATH') || exit;

class WazobiaMail_Domain_Manager {

    /**
     * API Client.
     *
     * @var WazobiaMail_API_Client
     */
    private $client;

    /**
     * Constructor.
     *
     * @param WazobiaMail_API_Client|null $client
     */
    public function __construct(WazobiaMail_API_Client $client = null) {
        $this->client = $client ? $client : new WazobiaMail_API_Client();
    }

    /**
     * Automatically extract the current WordPress site host/domain.
     *
     * @return string
     */
    public static function detect_site_domain() {
        $url = get_site_url();
        $host = wp_parse_url($url, PHP_URL_HOST);

        if (empty($host)) {
            $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : 'localhost';
        }

        // Strip port if present
        if (strpos($host, ':') !== false) {
            $parts = explode(':', $host);
            $host = $parts[0];
        }

        return strtolower(trim($host));
    }

    /**
     * Get saved domain options.
     *
     * @return array
     */
    public function get_domain_info() {
        $options = get_option('wazobiamail_options', []);
        $detected = self::detect_site_domain();

        return [
            'domain_id'     => !empty($options['domain_id']) ? $options['domain_id'] : '',
            'domain_name'   => !empty($options['domain_name']) ? $options['domain_name'] : $detected,
            'domain_status' => !empty($options['domain_status']) ? $options['domain_status'] : 'unregistered',
            'detected'      => $detected,
        ];
    }

    /**
     * Register the detected or specified WordPress site domain in WazobiaMail.
     *
     * @param string|null $domain
     * @return array
     */
    public function register_site_domain($domain = null) {
        if (!$this->client->is_configured()) {
            return [
                'success' => false,
                'error'   => __('Cannot register site domain: WazobiaMail API Key is mandatory and not set.', 'wazobiamail'),
            ];
        }

        $target_domain = !empty($domain) ? strtolower(trim($domain)) : self::detect_site_domain();

        // 1. Check if domain already exists on the customer account
        $list = $this->client->list_domains();
        if (!empty($list['success']) && isset($list['data']['domains']) && is_array($list['data']['domains'])) {
            foreach ($list['data']['domains'] as $existing) {
                if (isset($existing['domain']) && strtolower($existing['domain']) === $target_domain) {
                    $this->update_domain_options(
                        $existing['id'],
                        $target_domain,
                        !empty($existing['verified']) ? 'verified' : 'pending_dns'
                    );
                    return [
                        'success' => true,
                        'message' => sprintf(__('Site domain %s is already registered in WazobiaMail.', 'wazobiamail'), $target_domain),
                        'domain'  => $existing,
                    ];
                }
            }
        }

        // 2. Register domain via API
        $res = $this->client->create_domain($target_domain, 'hosted');

        if (!$res['success']) {
            return [
                'success' => false,
                'error'   => $res['error'],
            ];
        }

        $domain_id = isset($res['data']['domain']['id']) 
            ? $res['data']['domain']['id'] 
            : (isset($res['data']['id']) ? $res['data']['id'] : '');

        $verified = !empty($res['data']['domain']['verified']) || !empty($res['data']['verified']);
        $status = $verified ? 'verified' : 'pending_dns';

        $this->update_domain_options($domain_id, $target_domain, $status);

        return [
            'success'   => true,
            'message'   => sprintf(__('Successfully registered %s with WazobiaMail!', 'wazobiamail'), $target_domain),
            'domain_id' => $domain_id,
            'status'    => $status,
        ];
    }

    /**
     * Fetch required DNS records for the registered site domain.
     *
     * @return array
     */
    public function get_dns_records() {
        $info = $this->get_domain_info();
        if (empty($info['domain_id'])) {
            return [
                'success' => false,
                'error'   => __('Site domain is not registered yet.', 'wazobiamail'),
                'records' => [],
            ];
        }

        $res = $this->client->get_domain_dns($info['domain_id']);

        if (!$res['success']) {
            return [
                'success' => false,
                'error'   => $res['error'],
                'records' => [],
            ];
        }

        // The backend wraps the DNS payload as data.domain; accept the legacy
        // data.records shape as well for older installations.
        $data = isset($res['data']) && is_array($res['data']) ? $res['data'] : [];
        $payload = isset($data['domain']) && is_array($data['domain']) ? $data['domain'] : $data;
        $records = isset($payload['records']) && is_array($payload['records']) ? $payload['records'] : [];

        return [
            'success' => true,
            'records' => $records,
            'domain'  => $info['domain_name'],
        ];
    }

    /**
     * Trigger DNS verification check for the registered site domain.
     *
     * @return array
     */
    public function verify_dns() {
        $info = $this->get_domain_info();
        if (empty($info['domain_id'])) {
            return [
                'success' => false,
                'error'   => __('Site domain is not registered yet.', 'wazobiamail'),
            ];
        }

        $res = $this->client->verify_domain($info['domain_id'], true);

        if (!$res['success']) {
            return [
                'success' => false,
                'error'   => $res['error'],
            ];
        }

        $verified = !empty($res['data']['verified']) || (isset($res['data']['verificationStatus']) && $res['data']['verificationStatus'] === 'verified');
        $new_status = $verified ? 'verified' : 'pending_dns';

        $this->update_domain_options($info['domain_id'], $info['domain_name'], $new_status);

        return [
            'success'  => true,
            'verified' => $verified,
            'status'   => $new_status,
            'message'  => $verified 
                ? __('Site domain DNS is fully verified and active!', 'wazobiamail')
                : __('DNS records are still propagating. Please allow 5-15 minutes and retry.', 'wazobiamail'),
        ];
    }

    /**
     * Helper to update options.
     *
     * @param string $id
     * @param string $name
     * @param string $status
     */
    private function update_domain_options($id, $name, $status) {
        $options = get_option('wazobiamail_options', []);
        $options['domain_id']     = sanitize_text_field($id);
        $options['domain_name']   = sanitize_text_field($name);
        $options['domain_status'] = sanitize_text_field($status);
        update_option('wazobiamail_options', $options);
    }
}
