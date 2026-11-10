<?php
/**
 * Test Mailer functionality & wp_mail interception.
 */

namespace WazobiaMail\WordPress\Tests;

use PHPUnit\Framework\TestCase;
use WazobiaMail_API_Client;
use WazobiaMail_Mailer;
use WPMockState;

class MailerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        WPMockState::reset();
    }

    public function test_parse_recipients() {
        $mailer = new WazobiaMail_Mailer();

        // Single string
        $this->assertEquals(['alice@example.com'], $mailer->parse_recipients('alice@example.com'));

        // Name wrapper
        $this->assertEquals(['bob@example.com'], $mailer->parse_recipients('Bob Jones <bob@example.com>'));

        // Comma-separated list with spaces
        $this->assertEquals(
            ['carol@example.com', 'dave@example.com'],
            $mailer->parse_recipients('carol@example.com, Dave <dave@example.com>')
        );

        // Array with duplicate and invalid entries
        $this->assertEquals(
            ['eve@example.com', 'frank@example.com'],
            $mailer->parse_recipients(['eve@example.com', 'not-an-email', 'frank@example.com', 'eve@example.com'])
        );
    }

    public function test_parse_headers() {
        $mailer = new WazobiaMail_Mailer();

        $headers = [
            'From: Acme Store <orders@store.com>',
            'Reply-To: Support <support@store.com>',
            'Content-Type: text/html; charset=UTF-8',
            'Cc: audit@store.com, manager@store.com',
            'Bcc: archive@store.com',
            'Idempotency-Key: msg_order_9981',
        ];

        $parsed = $mailer->parse_headers($headers);

        $this->assertEquals('Acme Store', $parsed['from_name']);
        $this->assertEquals('orders@store.com', $parsed['from_email']);
        $this->assertEquals('Support <support@store.com>', $parsed['reply_to']);
        $this->assertTrue($parsed['is_html']);
        $this->assertEquals(['audit@store.com', 'manager@store.com'], $parsed['cc']);
        $this->assertEquals(['archive@store.com'], $parsed['bcc']);
        $this->assertEquals('msg_order_9981', $parsed['idempotency_key']);
    }

    public function test_send_html_message() {
        $client = new WazobiaMail_API_Client('wzbm_live_key');
        $mailer = new WazobiaMail_Mailer($client);

        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => [
                'result' => [
                    'trackingId' => 'trk_msg_1042',
                    'status'     => 'queued',
                ],
            ],
        ]);

        $res = $mailer->send(
            'customer@example.com',
            'Your Invoice',
            '<h1>Thank You</h1><p>Invoice #1042</p>',
            [
                'Content-Type: text/html',
                'From: Billing <billing@example.com>',
                'Reply-To: Help <help@example.com>',
                'Cc: audit@example.com',
                'Bcc: archive@example.com',
                'Idempotency-Key: msg_invoice_1042',
            ]
        );

        $this->assertTrue($res['success']);
        $this->assertEquals('trk_msg_1042', $res['tracking_id']);

        $req = WPMockState::$last_remote_request;
        $body = json_decode($req['args']['body'], true);

        // Nested content contract from the backend SendMessageRequest DTO.
        $this->assertEquals('Billing <billing@example.com>', $body['content']['from']);
        $this->assertEquals('Your Invoice', $body['content']['subject']);
        $this->assertEquals('<h1>Thank You</h1><p>Invoice #1042</p>', $body['content']['htmlBody']);
        $this->assertEquals('Help <help@example.com>', $body['content']['replyTo']);
        $this->assertStringContainsString('Thank You', $body['content']['textBody']);
        $this->assertStringContainsString('Invoice #1042', $body['content']['textBody']);

        $this->assertEquals(['customer@example.com'], $body['to']);
        $this->assertEquals(['audit@example.com'], $body['cc']);
        $this->assertEquals(['archive@example.com'], $body['bcc']);
        $this->assertEquals('billing@example.com', $body['envelopeSender']);
        $this->assertSame([], $body['attachments']);
        $this->assertEquals('msg_invoice_1042', $req['args']['headers']['Idempotency-Key']);

        // No flat top-level message fields are sent.
        $this->assertArrayNotHasKey('from', $body);
        $this->assertArrayNotHasKey('subject', $body);
        $this->assertArrayNotHasKey('html', $body);
        $this->assertArrayNotHasKey('text', $body);
        $this->assertArrayNotHasKey('replyTo', $body);

        // Check delivery log entry was recorded
        $logs = get_option('wazobiamail_delivery_logs');
        $this->assertNotEmpty($logs);
        $this->assertEquals('trk_msg_1042', $logs[0]['tracking_id']);
        $this->assertEquals('customer@example.com', $logs[0]['recipient']);
        $this->assertEquals('queued', $logs[0]['status']);
    }

    public function test_send_with_attachments_uploads_then_sends_url_attachments() {
        $client = new WazobiaMail_API_Client('wzbm_live_key');
        $mailer = new WazobiaMail_Mailer($client);

        // Create temporary test file
        $temp_file = tempnam(sys_get_temp_dir(), 'wzbm_att_');
        file_put_contents($temp_file, 'Invoice PDF Content Sample');
        $file_name = basename($temp_file);

        // 1. Upload response, 2. send response.
        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => [
                'file' => [
                    'fileName'    => $file_name,
                    'contentType' => 'text/plain',
                    'sizeBytes'   => 26,
                    'publicUrl'   => 'https://cdn.example.com/attachments/' . $file_name,
                ],
            ],
        ]);
        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => ['result' => ['trackingId' => 'trk_att_99', 'status' => 'queued']],
        ]);

        $res = $mailer->send(
            'client@example.com',
            'Attached Document',
            'Plain text message',
            '',
            [$temp_file]
        );

        $this->assertTrue($res['success']);
        $this->assertEquals('trk_att_99', $res['tracking_id']);

        $req = WPMockState::$last_remote_request;
        $body = json_decode($req['args']['body'], true);

        $this->assertEquals(
            [[
                'url'      => 'https://cdn.example.com/attachments/' . $file_name,
                'fileName' => $file_name,
            ]],
            $body['attachments']
        );
        $this->assertArrayNotHasKey('content', $body['attachments'][0]);
        $this->assertEquals('Plain text message', $body['content']['textBody']);

        unlink($temp_file);
    }

    public function test_send_fails_when_attachment_upload_fails() {
        $client = new WazobiaMail_API_Client('wzbm_live_key');
        $mailer = new WazobiaMail_Mailer($client);

        $temp_file = tempnam(sys_get_temp_dir(), 'wzbm_att_');
        file_put_contents($temp_file, 'Attachment bytes');
        $file_name = basename($temp_file);

        WPMockState::queue_response(400, [
            'message' => 'Attachments storage is not configured',
        ]);

        $res = $mailer->send(
            'client@example.com',
            'Attached Document',
            'Plain text message',
            '',
            [$temp_file]
        );

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Failed to upload email attachment', $res['error']);
        $this->assertStringContainsString($file_name, $res['error']);
        $this->assertStringContainsString('Attachments storage is not configured', $res['error']);

        // Only the upload request happened; the send request was not attempted.
        $req = WPMockState::$last_remote_request;
        $this->assertStringEndsWith('/sending/attachments/upload', $req['url']);

        $logs = get_option('wazobiamail_delivery_logs');
        $this->assertNotEmpty($logs);
        $this->assertEquals('failed', $logs[0]['status']);

        unlink($temp_file);
    }

    public function test_send_fails_when_attachment_is_missing() {
        $client = new WazobiaMail_API_Client('wzbm_live_key');
        $mailer = new WazobiaMail_Mailer($client);

        $missing = '/nonexistent/path/file.pdf';
        $res = $mailer->send(
            'client@example.com',
            'Attached Document',
            'Plain text message',
            '',
            [$missing]
        );

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('does not exist or is not readable', $res['error']);
        $this->assertStringContainsString($missing, $res['error']);
        $this->assertNull(WPMockState::$last_remote_request);
    }

    public function test_intercept_wp_mail_short_circuits() {
        $client = new WazobiaMail_API_Client('wzbm_live_key');
        $mailer = new WazobiaMail_Mailer($client);

        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => ['trackingId' => 'trk_inter_123'],
        ]);

        $atts = [
            'to'          => 'user@example.com',
            'subject'     => 'Reset Password',
            'message'     => 'Click here to reset your password',
            'headers'     => '',
            'attachments' => [],
        ];

        // Should return true and prevent default WordPress PHPMailer
        $short_circuit = $mailer->intercept_wp_mail(null, $atts);
        $this->assertTrue($short_circuit);
    }

    public function test_intercept_wp_mail_returns_null_when_disabled() {
        update_option('wazobiamail_options', ['mail_mode' => 'disabled']);

        $client = new WazobiaMail_API_Client('wzbm_live_key');
        $mailer = new WazobiaMail_Mailer($client);

        $atts = [
            'to'          => 'user@example.com',
            'subject'     => 'Test',
            'message'     => 'Hello',
            'headers'     => '',
            'attachments' => [],
        ];

        $short_circuit = $mailer->intercept_wp_mail(null, $atts);
        $this->assertNull($short_circuit);
        $this->assertNull(WPMockState::$last_remote_request);
    }

    public function test_intercept_wp_mail_fails_when_api_key_is_missing() {
        update_option('wazobiamail_options', ['api_key' => '']);

        $client = new WazobiaMail_API_Client('');
        $mailer = new WazobiaMail_Mailer($client);

        $atts = [
            'to'          => 'user@example.com',
            'subject'     => 'Test',
            'message'     => 'Hello',
            'headers'     => '',
            'attachments' => [],
        ];

        $short_circuit = $mailer->intercept_wp_mail(null, $atts);
        $this->assertFalse($short_circuit);
    }

    public function test_send_plain_text_only() {
        update_option('wazobiamail_options', [
            'from_name'  => 'Plain Sender',
            'from_email' => 'sender@example.com',
        ]);

        $client = new WazobiaMail_API_Client('wzbm_live_key');
        $mailer = new WazobiaMail_Mailer($client);

        WPMockState::queue_response(200, [
            'success' => true,
            'data'    => ['trackingId' => 'trk_text_only'],
        ]);

        $res = $mailer->send('user@example.com', 'Hello', 'Just a plain text message', ['Content-Type: text/plain']);
        $this->assertTrue($res['success']);

        $req = WPMockState::$last_remote_request;
        $body = json_decode($req['args']['body'], true);
        $this->assertEquals('Just a plain text message', $body['content']['textBody']);
        $this->assertEquals('Plain Sender <sender@example.com>', $body['content']['from']);
        $this->assertEquals('sender@example.com', $body['envelopeSender']);
        $this->assertArrayNotHasKey('htmlBody', $body['content']);
        $this->assertArrayNotHasKey('text', $body);
    }

    public function test_log_rotation_caps_at_50_entries() {
        $client = new WazobiaMail_API_Client('wzbm_live_key');
        $mailer = new WazobiaMail_Mailer($client);

        // Prepopulate with 55 mock sends
        for ($i = 1; $i <= 55; $i++) {
            WPMockState::queue_response(200, [
                'success' => true,
                'data'    => ['trackingId' => 'trk_' . $i],
            ]);
            $mailer->send('user@example.com', 'Test ' . $i, 'Body');
        }

        $logs = get_option('wazobiamail_delivery_logs');
        $this->assertCount(50, $logs);
        // The most recent should be at the front
        $this->assertEquals('trk_55', $logs[0]['tracking_id']);
    }

    public function test_prepare_attachments_returns_empty_list_for_no_attachments() {
        $mailer = new WazobiaMail_Mailer(new WazobiaMail_API_Client('wzbm_live_key'));

        $result = $mailer->prepare_attachments([]);

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['attachments']);
        $this->assertNull($result['error']);
    }

    public function test_prepare_attachments_rejects_directory_paths() {
        $mailer = new WazobiaMail_Mailer(new WazobiaMail_API_Client('wzbm_live_key'));

        $dir = sys_get_temp_dir();
        $result = $mailer->prepare_attachments([$dir]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('does not exist or is not readable', $result['error']);
        $this->assertNull(WPMockState::$last_remote_request);
    }
}
