<?php
/**
 * Test Admin Settings & Activator routines.
 */

namespace WazobiaMail\WordPress\Tests;

use PHPUnit\Framework\TestCase;
use WazobiaMail_Activator;
use WazobiaMail_Deactivator;
use WazobiaMail_Admin;
use WazobiaMail_API_Client;
use WPMockState;

class AdminSettingsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        WPMockState::reset();
    }

    public function test_activator_creates_default_options() {
        $this->assertFalse(get_option('wazobiamail_options'));

        WazobiaMail_Activator::activate();

        $options = get_option('wazobiamail_options');
        $this->assertIsArray($options);
        $this->assertEquals('', $options['api_key']);
        $this->assertEquals('https://app.wazobiamail.com/api/v1', $options['base_url']);
        $this->assertEquals('api', $options['mail_mode']);
        $this->assertEquals('unregistered', $options['domain_status']);
    }

    public function test_deactivator_cleans_transients() {
        set_transient('wazobiamail_dns_records_cache', ['some' => 'data']);
        $this->assertNotEmpty(get_transient('wazobiamail_dns_records_cache'));

        WazobiaMail_Deactivator::deactivate();
        $this->assertFalse(get_transient('wazobiamail_dns_records_cache'));
    }

    public function test_ajax_send_test_email_rejects_empty_email() {
        $admin = new WazobiaMail_Admin();

        $_POST['recipient'] = 'not-valid';
        $admin->ajax_send_test_email();

        $this->assertNotEmpty(WPMockState::$json_responses);
        $last = end(WPMockState::$json_responses);
        $this->assertFalse($last['success']);
        $this->assertStringContainsString('valid recipient', $last['data']);
    }

    public function test_ajax_send_test_email_succeeds() {
        update_option('wazobiamail_options', ['api_key' => 'wzbm_live_test']);
        $client = new WazobiaMail_API_Client('wzbm_live_test');
        $admin = new WazobiaMail_Admin($client);

        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => ['trackingId' => 'trk_test_email_777'],
        ]);

        $_POST['recipient'] = 'owner@example.com';
        $admin->ajax_send_test_email();

        $this->assertNotEmpty(WPMockState::$json_responses);
        $last = end(WPMockState::$json_responses);
        $this->assertTrue($last['success']);
        $this->assertEquals('trk_test_email_777', $last['data']['tracking_id']);
    }

    public function test_ajax_clear_logs() {
        update_option('wazobiamail_delivery_logs', [['tracking_id' => 'trk_1']]);
        $this->assertCount(1, get_option('wazobiamail_delivery_logs'));

        $admin = new WazobiaMail_Admin();
        $admin->ajax_clear_logs();

        $this->assertEmpty(get_option('wazobiamail_delivery_logs'));
        $last = end(WPMockState::$json_responses);
        $this->assertTrue($last['success']);
    }

    public function test_handle_form_actions_saves_clean_settings() {
        $admin = new WazobiaMail_Admin();

        $_POST['wazobiamail_save_settings'] = 1;
        $_POST['api_key'] = 'wzbm_live_fresh_key_9999';
        $_POST['from_name'] = 'My Fancy Store';
        $_POST['from_email'] = 'orders@fancystore.com';
        $_POST['base_url'] = 'https://custom.wazobiamail.com/api/v1';
        $_POST['mail_mode'] = 'api';
        $_POST['enable_logs'] = 1;

        // handle_form_actions ends with exit, but in tests exit can be suppressed or wrapped
        // In our mock, wp_safe_redirect returns true, but let's test options saving
        try {
            $admin->handle_form_actions();
        } catch (\Exception $e) {
            // caught exit if any
        }

        $options = get_option('wazobiamail_options');
        $this->assertEquals('wzbm_live_fresh_key_9999', $options['api_key']);
        $this->assertEquals('My Fancy Store', $options['from_name']);
        $this->assertEquals('orders@fancystore.com', $options['from_email']);
        $this->assertEquals('https://custom.wazobiamail.com/api/v1', $options['base_url']);
        $this->assertEquals('api', $options['mail_mode']);
    }

    public function test_handle_form_actions_preserves_masked_key() {
        update_option('wazobiamail_options', [
            'api_key'    => 'wzbm_live_super_secret_original_key',
            'from_name'  => 'Original Store',
            'from_email' => 'admin@example.com',
        ]);

        $admin = new WazobiaMail_Admin();

        $_POST['wazobiamail_save_settings'] = 1;
        $_POST['api_key'] = 'wzbm_..._original_key'; // user left masked placeholder
        $_POST['from_name'] = 'Renamed Store';
        $_POST['from_email'] = 'admin@example.com';

        try {
            $admin->handle_form_actions();
        } catch (\Exception $e) {
            // caught exit
        }

        $options = get_option('wazobiamail_options');
        // Original secret key must NOT be overwritten by the mask!
        $this->assertEquals('wzbm_live_super_secret_original_key', $options['api_key']);
        $this->assertEquals('Renamed Store', $options['from_name']);
    }

    public function test_render_settings_page_outputs_clean_html() {
        $admin = new WazobiaMail_Admin();

        update_option('wazobiamail_options', [
            'api_key'       => 'wzbm_live_mock12345678',
            'domain_status' => 'verified',
        ]);

        ob_start();
        $admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('WazobiaMail Transactional Email Settings', $output);
        $this->assertStringContainsString('wzbm_...5678', $output);

        // Render the DNS tab so the verified badge is actually output, then
        // check that esc_html_e() escaped the ampersand.
        $_GET['tab'] = 'domain';
        ob_start();
        $admin->render_settings_page();
        $domain_output = ob_get_clean();
        unset($_GET['tab']);

        $this->assertStringContainsString('Verified &amp; Active', $domain_output);
        $this->assertStringNotContainsString('Verified & Active', $domain_output);
    }
}
