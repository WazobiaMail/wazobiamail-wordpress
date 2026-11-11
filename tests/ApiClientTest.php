<?php
/**
 * Test API Client functionality.
 */

namespace WazobiaMail\WordPress\Tests;

use PHPUnit\Framework\TestCase;
use WazobiaMail_API_Client;
use WPMockState;
use WP_Error;

class ApiClientTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        WPMockState::reset();
    }

    public function test_is_configured_returns_false_when_key_is_empty() {
        $client = new WazobiaMail_API_Client('');
        $this->assertFalse($client->is_configured());

        $client_null = new WazobiaMail_API_Client(null);
        $this->assertFalse($client_null->is_configured());
    }

    public function test_is_configured_returns_true_when_key_is_set() {
        $client = new WazobiaMail_API_Client('wzbm_live_1234567890');
        $this->assertTrue($client->is_configured());
        $this->assertEquals('wzbm_live_1234567890', $client->get_api_key());
    }

    public function test_mask_secret() {
        $this->assertEquals('***', WazobiaMail_API_Client::mask_secret(null));
        $this->assertEquals('***', WazobiaMail_API_Client::mask_secret(''));
        $this->assertEquals('***', WazobiaMail_API_Client::mask_secret('short'));
        $this->assertEquals('wzbm_...7890', WazobiaMail_API_Client::mask_secret('wzbm_live_1234567890'));
    }

    public function test_request_aborts_when_client_is_unconfigured() {
        $client = new WazobiaMail_API_Client('');
        $res = $client->send_message(['to' => 'test@example.com']);

        $this->assertFalse($res['success']);
        $this->assertEquals(401, $res['code']);
        $this->assertStringContainsString('mandatory', $res['error']);
        $this->assertNull(WPMockState::$last_remote_request);
    }

    public function test_send_message_dispatches_with_correct_headers_and_payload() {
        $client = new WazobiaMail_API_Client('wzbm_live_secretkey_999');

        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => [
                'trackingId' => 'trk_wp_123456',
                'status'     => 'queued',
            ],
        ]);

        $payload = [
            'to'             => ['buyer@example.com'],
            'cc'             => [],
            'bcc'            => [],
            'content'        => [
                'from'     => 'Store <orders@example.com>',
                'subject'  => 'Order #101',
                'htmlBody' => '<p>Your order</p>',
                'textBody' => 'Your order',
            ],
            'envelopeSender' => 'orders@example.com',
            'attachments'    => [],
        ];

        $res = $client->send_message($payload, 'idemp_key_101');

        $this->assertTrue($res['success']);
        $this->assertEquals(200, $res['code']);
        $this->assertEquals('trk_wp_123456', $res['data']['trackingId']);

        $req = WPMockState::$last_remote_request;
        $this->assertNotNull($req);
        $this->assertEquals('https://app.wazobiamail.com/api/v1/sending/messages', $req['url']);
        $this->assertEquals('POST', $req['args']['method']);
        $this->assertEquals('wzbm_live_secretkey_999', $req['args']['headers']['X-API-Key']);
        $this->assertEquals('idemp_key_101', $req['args']['headers']['Idempotency-Key']);
        $this->assertStringContainsString('wazobiamail-wordpress/0.1.1', $req['args']['headers']['User-Agent']);

        $body = json_decode($req['args']['body'], true);
        $this->assertEquals('Store <orders@example.com>', $body['content']['from']);
        $this->assertEquals('Order #101', $body['content']['subject']);
        $this->assertEquals(['buyer@example.com'], $body['to']);
        $this->assertEquals('orders@example.com', $body['envelopeSender']);
    }

    public function test_handles_wp_error() {
        $client = new WazobiaMail_API_Client('wzbm_live_secret');

        WPMockState::$remote_responses[] = new WP_Error('http_request_failed', 'cURL error 28: Connection timed out');

        $res = $client->send_message(['to' => 'test@example.com']);

        $this->assertFalse($res['success']);
        $this->assertEquals(0, $res['code']);
        $this->assertStringContainsString('Connection timed out', $res['error']);
    }

    public function test_handles_api_error_response_422() {
        $client = new WazobiaMail_API_Client('wzbm_live_secret');

        WPMockState::queue_response(422, [
            'message' => 'Unverified sender domain: store.com',
            'errors'  => ['from' => 'Sender domain is not verified'],
        ]);

        $res = $client->send_message(['to' => 'test@example.com']);

        $this->assertFalse($res['success']);
        $this->assertEquals(422, $res['code']);
        $this->assertStringContainsString('Unverified sender domain', $res['error']);
    }

    public function test_domain_endpoints() {
        $client = new WazobiaMail_API_Client('wzbm_live_secret');

        // Create domain
        WPMockState::queue_response(201, ['success' => true, 'data' => ['id' => 'dom_123', 'domain' => 'mysite.com']]);
        $res = $client->create_domain('mysite.com');
        $this->assertTrue($res['success']);
        $this->assertEquals('https://app.wazobiamail.com/api/v1/sending/domains', WPMockState::$last_remote_request['url']);

        // Get DNS
        WPMockState::queue_response(200, ['success' => true, 'data' => ['domain' => ['records' => []]]]);
        $dns = $client->get_domain_dns('dom_123');
        $this->assertTrue($dns['success']);
        $this->assertEquals('https://app.wazobiamail.com/api/v1/sending/domains/dom_123/dns-records', WPMockState::$last_remote_request['url']);

        // Verify DNS
        WPMockState::queue_response(200, ['success' => true, 'data' => ['verified' => true]]);
        $ver = $client->verify_domain('dom_123');
        $this->assertTrue($ver['success']);
        $this->assertEquals('https://app.wazobiamail.com/api/v1/sending/domains/dom_123/verify', WPMockState::$last_remote_request['url']);
    }

    public function test_upload_attachment_posts_multipart_and_returns_public_url() {
        $client = new WazobiaMail_API_Client('wzbm_live_secret');

        $temp_file = tempnam(sys_get_temp_dir(), 'wzbm_upload_');
        file_put_contents($temp_file, 'attachment body');
        $file_name = basename($temp_file);

        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => [
                'file' => [
                    'fileName'    => $file_name,
                    'contentType' => 'text/plain',
                    'sizeBytes'   => 15,
                    'publicUrl'   => 'https://cdn.example.com/attachments/' . $file_name,
                ],
            ],
        ]);

        $res = $client->upload_attachment($temp_file);

        $this->assertTrue($res['success']);
        $this->assertEquals('https://cdn.example.com/attachments/' . $file_name, $res['data']['file']['publicUrl']);
        $this->assertEquals($file_name, $res['data']['file']['fileName']);

        $req = WPMockState::$last_remote_request;
        $this->assertEquals('https://app.wazobiamail.com/api/v1/sending/attachments/upload', $req['url']);
        $this->assertEquals('POST', $req['args']['method']);
        $this->assertEquals('wzbm_live_secret', $req['args']['headers']['X-API-Key']);
        $this->assertStringContainsString('multipart/form-data; boundary=', $req['args']['headers']['Content-Type']);
        $this->assertStringContainsString('name="file"; filename="' . $file_name . '"', $req['args']['body']);
        $this->assertStringContainsString('attachment body', $req['args']['body']);
        $this->assertStringContainsString('--' . trim(substr($req['args']['headers']['Content-Type'], strlen('multipart/form-data; boundary='))) . '--', $req['args']['body']);

        unlink($temp_file);
    }

    public function test_upload_attachment_fails_without_public_url() {
        $client = new WazobiaMail_API_Client('wzbm_live_secret');

        $temp_file = tempnam(sys_get_temp_dir(), 'wzbm_upload_');
        file_put_contents($temp_file, 'attachment body');

        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => [
                'file' => [
                    'fileName'  => basename($temp_file),
                    'sizeBytes' => 15,
                ],
            ],
        ]);

        $res = $client->upload_attachment($temp_file);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('no public URL', $res['error']);

        unlink($temp_file);
    }

    public function test_upload_attachment_rejects_missing_file_without_http_request() {
        $client = new WazobiaMail_API_Client('wzbm_live_secret');

        $res = $client->upload_attachment('/nonexistent/attachment.pdf');

        $this->assertFalse($res['success']);
        $this->assertEquals(400, $res['code']);
        $this->assertStringContainsString('does not exist or is not readable', $res['error']);
        $this->assertNull(WPMockState::$last_remote_request);
    }

    public function test_upload_attachment_propagates_api_error() {
        $client = new WazobiaMail_API_Client('wzbm_live_secret');

        $temp_file = tempnam(sys_get_temp_dir(), 'wzbm_upload_');
        file_put_contents($temp_file, 'attachment body');

        WPMockState::queue_response(400, ['message' => 'File size must not exceed 10MB']);

        $res = $client->upload_attachment($temp_file);

        $this->assertFalse($res['success']);
        $this->assertEquals(400, $res['code']);
        $this->assertStringContainsString('10MB', $res['error']);

        unlink($temp_file);
    }
}
