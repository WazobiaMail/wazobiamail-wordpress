<?php
/**
 * Test Domain Manager functionality.
 */

namespace WazobiaMail\WordPress\Tests;

use PHPUnit\Framework\TestCase;
use WazobiaMail_API_Client;
use WazobiaMail_Domain_Manager;
use WPMockState;

class DomainManagerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        WPMockState::reset();
    }

    public function test_detect_site_domain() {
        $domain = WazobiaMail_Domain_Manager::detect_site_domain();
        $this->assertEquals('example.com', $domain);
    }

    public function test_register_site_domain_fails_if_unconfigured() {
        $client = new WazobiaMail_API_Client('');
        $manager = new WazobiaMail_Domain_Manager($client);

        $res = $manager->register_site_domain();
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('API Key is mandatory', $res['error']);
    }

    public function test_register_site_domain_creates_new_domain() {
        $client = new WazobiaMail_API_Client('wzbm_live_valid');
        $manager = new WazobiaMail_Domain_Manager($client);

        // 1. list_domains returns empty list
        WPMockState::queue_response(200, ['success' => true, 'data' => ['domains' => []]]);
        // 2. create_domain returns new domain
        WPMockState::queue_response(201, [
            'success' => true,
            'data'    => [
                'id'       => 'dom_site_123',
                'domain'   => 'example.com',
                'verified' => false,
            ],
        ]);

        $res = $manager->register_site_domain();

        $this->assertTrue($res['success']);
        $this->assertEquals('dom_site_123', $res['domain_id']);
        $this->assertEquals('pending_dns', $res['status']);

        // Check options were updated
        $options = get_option('wazobiamail_options');
        $this->assertEquals('dom_site_123', $options['domain_id']);
        $this->assertEquals('example.com', $options['domain_name']);
        $this->assertEquals('pending_dns', $options['domain_status']);
    }

    public function test_register_site_domain_reuses_existing_domain() {
        $client = new WazobiaMail_API_Client('wzbm_live_valid');
        $manager = new WazobiaMail_Domain_Manager($client);

        // list_domains returns already registered domain
        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => [
                'domains' => [
                    [
                        'id'       => 'dom_existing_888',
                        'domain'   => 'example.com',
                        'verified' => true,
                    ],
                ],
            ],
        ]);

        $res = $manager->register_site_domain();

        $this->assertTrue($res['success']);
        $this->assertStringContainsString('already registered', $res['message']);

        $options = get_option('wazobiamail_options');
        $this->assertEquals('dom_existing_888', $options['domain_id']);
        $this->assertEquals('verified', $options['domain_status']);
    }

    public function test_get_dns_records() {
        $client = new WazobiaMail_API_Client('wzbm_live_valid');
        $manager = new WazobiaMail_Domain_Manager($client);

        // Set registered domain id in options
        update_option('wazobiamail_options', [
            'domain_id'   => 'dom_123',
            'domain_name' => 'example.com',
        ]);

        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => [
                'domain' => [
                    'provisioning'       => false,
                    'domainId'           => 'dom_123',
                    'domain'             => 'example.com',
                    'verificationStatus' => 'verified',
                    'records'            => [
                        ['recordType' => 'TXT', 'name' => 'mail', 'value' => 'v=spf1 include:_spf.wazobiamail.com ~all'],
                        ['recordType' => 'TXT', 'name' => 'wzbm._domainkey.mail', 'value' => 'k=rsa; p=MIGf...'],
                    ],
                ],
            ],
        ]);

        $res = $manager->get_dns_records();
        $this->assertTrue($res['success']);
        $this->assertCount(2, $res['records']);
        $this->assertEquals('TXT', $res['records'][0]['recordType']);
        $this->assertEquals('https://app.wazobiamail.com/api/v1/sending/domains/dom_123/dns-records', WPMockState::$last_remote_request['url']);
    }

    public function test_verify_dns_updates_status() {
        $client = new WazobiaMail_API_Client('wzbm_live_valid');
        $manager = new WazobiaMail_Domain_Manager($client);

        update_option('wazobiamail_options', [
            'domain_id'     => 'dom_123',
            'domain_name'   => 'example.com',
            'domain_status' => 'pending_dns',
        ]);

        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => [
                'verified' => true,
            ],
        ]);

        $res = $manager->verify_dns();
        $this->assertTrue($res['success']);
        $this->assertTrue($res['verified']);
        $this->assertEquals('verified', $res['status']);

        $options = get_option('wazobiamail_options');
        $this->assertEquals('verified', $options['domain_status']);
    }
}
