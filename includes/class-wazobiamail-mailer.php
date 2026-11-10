<?php
/**
 * Intercepts WordPress wp_mail and delivers messages via WazobiaMail REST API.
 *
 * @package    WazobiaMail
 * @subpackage WazobiaMail/includes
 */

defined('ABSPATH') || exit;

class WazobiaMail_Mailer {

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
     * Register WordPress mail hooks.
     */
    public function register() {
        // WordPress 5.7+ pre_wp_mail filter
        add_filter('pre_wp_mail', [$this, 'intercept_wp_mail'], 10, 2);
    }

    /**
     * Intercept wp_mail execution.
     *
     * @param null|bool $return Short-circuit return value.
     * @param array     $atts   wp_mail arguments array [to, subject, message, headers, attachments].
     * @return bool|null True on success, false on failure, null to let default wp_mail handle if disabled.
     */
    public function intercept_wp_mail($return, $atts) {
        $options = get_option('wazobiamail_options', []);

        // If plugin mail mode is disabled, let default WordPress mail handle it
        if (isset($options['mail_mode']) && $options['mail_mode'] === 'disabled') {
            return null;
        }

        // If API key is missing, fail and trigger admin notification
        if (!$this->client->is_configured()) {
            $error = new WP_Error(
                'wazobiamail_unconfigured',
                __('WazobiaMail API key is mandatory but not configured. Email delivery aborted.', 'wazobiamail')
            );
            do_action('wp_mail_failed', $error);
            return false;
        }

        $result = $this->send(
            $atts['to'],
            $atts['subject'],
            $atts['message'],
            $atts['headers'],
            $atts['attachments']
        );

        if (!$result['success']) {
            $error = new WP_Error(
                'wazobiamail_delivery_failed',
                $result['error'],
                $atts
            );
            do_action('wp_mail_failed', $error);
            return false;
        }

        return true;
    }

    /**
     * Normalize and dispatch an email via WazobiaMail.
     *
     * @param string|array $to
     * @param string       $subject
     * @param string       $message
     * @param string|array $headers
     * @param string|array $attachments
     * @return array Array with 'success', 'tracking_id', 'error'.
     */
    public function send($to, $subject, $message, $headers = '', $attachments = []) {
        $options = get_option('wazobiamail_options', []);

        // 1. Parse recipients
        $recipients = $this->parse_recipients($to);
        if (empty($recipients)) {
            return [
                'success' => false,
                'error'   => __('Recipient address ($to) cannot be empty.', 'wazobiamail'),
            ];
        }

        // 2. Parse headers
        $parsed_headers = $this->parse_headers($headers);

        // 3. Sender resolution
        $from_email = !empty($parsed_headers['from_email']) 
            ? $parsed_headers['from_email'] 
            : (!empty($options['from_email']) ? $options['from_email'] : get_option('admin_email'));

        $from_name = !empty($parsed_headers['from_name']) 
            ? $parsed_headers['from_name'] 
            : (!empty($options['from_name']) ? $options['from_name'] : get_bloginfo('name'));

        // Only send a sender when we actually have a usable address. Leaving it
        // out lets the backend fall back to the credential's mailbox.
        if (!empty($from_email) && is_email($from_email)) {
            $from_header = !empty($from_name) 
                ? sprintf('%s <%s>', $from_name, $from_email) 
                : $from_email;
        } else {
            $from_email  = '';
            $from_header = '';
        }

        // 4. Content formatting (HTML vs Text)
        $is_html = $parsed_headers['is_html'] || (strpos($message, '<html') !== false) || (strpos($message, '<p>') !== false) || (strpos($message, '<br') !== false);

        $content = [];
        if (!empty($from_header)) {
            $content['from'] = $from_header;
        }
        $content['subject'] = (string) $subject;

        if ($is_html) {
            $content['textBody'] = wp_strip_all_tags($message);
            $content['htmlBody'] = (string) $message;
        } else {
            $content['textBody'] = (string) $message;
        }

        if (!empty($parsed_headers['reply_to'])) {
            $content['replyTo'] = $parsed_headers['reply_to'];
        }

        // 5. Attachments: upload local files first, then send public URLs.
        $prepared_attachments = $this->prepare_attachments($attachments);
        if (!$prepared_attachments['success']) {
            return $this->failure_result(
                $prepared_attachments['error'],
                $recipients,
                (string) $subject
            );
        }

        // 6. Build the nested backend payload.
        $payload = [
            'to'             => $recipients,
            'cc'             => $parsed_headers['cc'],
            'bcc'            => $parsed_headers['bcc'],
            'content'        => $content,
            'envelopeSender' => (string) $from_email,
            'attachments'    => $prepared_attachments['attachments'],
        ];

        // 7. Execute send via API client
        $response = $this->client->send_message($payload, $parsed_headers['idempotency_key']);

        if (!$response['success']) {
            return $this->failure_result($response['error'], $recipients, (string) $subject);
        }

        $tracking_id = isset($response['data']['trackingId']) 
            ? $response['data']['trackingId'] 
            : (isset($response['data']['result']['trackingId']) ? $response['data']['result']['trackingId'] : '');

        // 7. Store delivery log
        $this->log_delivery([
            'tracking_id' => $tracking_id,
            'recipient'   => implode(', ', $recipients),
            'subject'     => $subject,
            'status'      => 'queued',
            'error'       => null,
            'timestamp'   => current_time('mysql'),
        ]);

        return [
            'success'     => true,
            'tracking_id' => $tracking_id,
            'data'        => $response['data'],
        ];
    }

    /**
     * Parse recipients into clean email address array.
     *
     * @param string|array $to
     * @return array
     */
    public function parse_recipients($to) {
        if (is_array($to)) {
            $emails = $to;
        } elseif (is_string($to)) {
            $emails = explode(',', $to);
        } else {
            return [];
        }

        $clean = [];
        foreach ($emails as $email) {
            $extracted = $this->extract_email(trim($email));
            if (!empty($extracted) && is_email($extracted)) {
                $clean[] = $extracted;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * Parse custom email headers.
     *
     * @param string|array $headers
     * @return array
     */
    public function parse_headers($headers) {
        $parsed = [
            'from_name'       => '',
            'from_email'      => '',
            'reply_to'        => '',
            'cc'              => [],
            'bcc'             => [],
            'is_html'         => false,
            'idempotency_key' => null,
        ];

        if (empty($headers)) {
            return $parsed;
        }

        $lines = is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", (string) $headers));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, ':') === false) {
                continue;
            }

            list($name, $value) = explode(':', $line, 2);
            $name  = strtolower(trim($name));
            $value = trim($value);

            switch ($name) {
                case 'from':
                    if (preg_match('/(.*)<(.+)>/', $value, $matches)) {
                        $parsed['from_name']  = trim($matches[1], " \t\n\r\0\x0B\"'");
                        $parsed['from_email'] = sanitize_email($matches[2]);
                    } else {
                        $parsed['from_email'] = sanitize_email($value);
                    }
                    break;

                case 'reply-to':
                    $parsed['reply_to'] = $value;
                    break;

                case 'content-type':
                    if (strpos(strtolower($value), 'text/html') !== false) {
                        $parsed['is_html'] = true;
                    }
                    break;

                case 'cc':
                    $parsed['cc'] = array_merge($parsed['cc'], $this->parse_recipients($value));
                    break;

                case 'bcc':
                    $parsed['bcc'] = array_merge($parsed['bcc'], $this->parse_recipients($value));
                    break;

                case 'idempotency-key':
                case 'x-idempotency-key':
                    $parsed['idempotency_key'] = sanitize_text_field($value);
                    break;
            }
        }

        return $parsed;
    }

    /**
     * Extract raw email from 'Name <email@domain.com>' format.
     *
     * @param string $address
     * @return string
     */
    private function extract_email($address) {
        if (preg_match('/<(.+)>/', $address, $matches)) {
            return sanitize_email($matches[1]);
        }
        return sanitize_email($address);
    }

    /**
     * Upload local attachments and return URL attachment payloads.
     *
     * The send endpoint accepts URL attachments, so each file is uploaded to
     * /sending/attachments/upload first. Upload failures are returned to the
     * caller instead of silently dropping the attachment.
     *
     * @param string|array $attachments
     * @return array {success: bool, attachments: array, error: string|null}
     */
    public function prepare_attachments($attachments) {
        $result = [
            'success'     => true,
            'attachments' => [],
            'error'       => null,
        ];

        if (empty($attachments)) {
            return $result;
        }

        $files = is_array($attachments) ? $attachments : explode("\n", (string) $attachments);

        foreach ($files as $file) {
            $file = trim((string) $file);
            if ($file === '') {
                continue;
            }

            if (!file_exists($file) || !is_readable($file) || !is_file($file)) {
                return [
                    'success'     => false,
                    'attachments' => [],
                    'error'       => sprintf(
                        __('Attachment "%s" does not exist or is not readable.', 'wazobiamail'),
                        $file
                    ),
                ];
            }

            $upload = $this->client->upload_attachment($file);
            if (!$upload['success']) {
                return [
                    'success'     => false,
                    'attachments' => [],
                    'error'       => sprintf(
                        __('Failed to upload email attachment "%1$s": %2$s', 'wazobiamail'),
                        basename($file),
                        $upload['error']
                    ),
                ];
            }

            $uploaded = isset($upload['data']['file']) && is_array($upload['data']['file'])
                ? $upload['data']['file']
                : (isset($upload['data']) && is_array($upload['data']) ? $upload['data'] : []);

            $public_url = isset($uploaded['publicUrl']) ? (string) $uploaded['publicUrl'] : '';
            $file_name  = !empty($uploaded['fileName'])
                ? (string) $uploaded['fileName']
                : basename($file);

            if ($public_url === '') {
                return [
                    'success'     => false,
                    'attachments' => [],
                    'error'       => sprintf(
                        __('Failed to upload email attachment "%s": the API did not return a public URL.', 'wazobiamail'),
                        basename($file)
                    ),
                ];
            }

            $result['attachments'][] = [
                'url'      => $public_url,
                'fileName' => $file_name,
            ];
        }

        return $result;
    }

    /**
     * Record and return a send failure.
     *
     * @param string $error
     * @param array  $recipients
     * @param string $subject
     * @return array
     */
    private function failure_result($error, array $recipients, $subject) {
        $this->log_delivery([
            'tracking_id' => 'err_' . wp_generate_password(8, false),
            'recipient'   => implode(', ', $recipients),
            'subject'     => $subject,
            'status'      => 'failed',
            'error'       => $error,
            'timestamp'   => current_time('mysql'),
        ]);

        return [
            'success' => false,
            'error'   => $error,
        ];
    }

    /**
     * Append to recent delivery logs (keeps latest 50).
     *
     * @param array $entry
     */
    private function log_delivery(array $entry) {
        $options = get_option('wazobiamail_options', []);
        if (isset($options['enable_logs']) && !$options['enable_logs']) {
            return;
        }

        $logs = get_option('wazobiamail_delivery_logs', []);
        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, $entry);
        if (count($logs) > 50) {
            $logs = array_slice($logs, 0, 50);
        }

        update_option('wazobiamail_delivery_logs', $logs);
    }
}
