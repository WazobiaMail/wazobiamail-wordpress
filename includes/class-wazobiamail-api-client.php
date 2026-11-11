<?php
/**
 * REST API Client for WazobiaMail using WordPress Native HTTP API.
 *
 * @package    WazobiaMail
 * @subpackage WazobiaMail/includes
 */

defined('ABSPATH') || exit;

class WazobiaMail_API_Client {

    const VERSION = '0.1.2';
    const DEFAULT_BASE_URL = 'https://app.wazobiamail.com/api/v1';
    const DEFAULT_TIMEOUT = 30;

    /**
     * API Key.
     *
     * @var string
     */
    private $api_key;

    /**
     * API Gateway Base URL.
     *
     * @var string
     */
    private $base_url;

    /**
     * Request Timeout in seconds.
     *
     * @var int
     */
    private $timeout;

    /**
     * Constructor.
     *
     * @param string|null $api_key
     * @param string|null $base_url
     * @param int|null    $timeout
     */
    public function __construct($api_key = null, $base_url = null, $timeout = null) {
        $options = get_option('wazobiamail_options', []);

        $this->api_key = $api_key !== null 
            ? (string) $api_key 
            : (isset($options['api_key']) ? (string) $options['api_key'] : '');

        $resolved_base = !empty($base_url) 
            ? (string) $base_url 
            : (!empty($options['base_url']) ? (string) $options['base_url'] : self::DEFAULT_BASE_URL);

        $this->base_url = rtrim($resolved_base, '/');
        $this->timeout  = $timeout !== null ? (int) $timeout : self::DEFAULT_TIMEOUT;
    }

    /**
     * Check if client has a configured API key.
     *
     * @return bool
     */
    public function is_configured() {
        return !empty(trim($this->api_key));
    }

    /**
     * Get the active API Key.
     *
     * @return string
     */
    public function get_api_key() {
        return $this->api_key;
    }

    /**
     * Mask API key for logs and admin display.
     *
     * @param string|null $key
     * @return string
     */
    public static function mask_secret($key) {
        if (empty($key)) {
            return '***';
        }
        $len = strlen($key);
        if ($len <= 8) {
            return '***';
        }
        $prefix = substr($key, 0, 5);
        $suffix = substr($key, -4);
        return $prefix . '...' . $suffix;
    }

    /**
     * Default HTTP request headers.
     *
     * @param string|null $idempotency_key
     * @param string|null $content_type    Content-Type header, or null to omit it.
     * @return array
     */
    protected function get_headers($idempotency_key = null, $content_type = 'application/json') {
        $headers = [
            'X-API-Key'     => $this->api_key,
            'User-Agent'    => 'wazobiamail-wordpress/' . self::VERSION . '; ' . get_bloginfo('url'),
            'Accept'        => 'application/json',
        ];

        if ($content_type !== null) {
            $headers['Content-Type'] = $content_type;
        }

        if (!empty($idempotency_key)) {
            $headers['Idempotency-Key'] = sanitize_text_field($idempotency_key);
        }

        return $headers;
    }

    /**
     * Build the public API URL for an endpoint.
     *
     * @param string $endpoint
     * @return string
     */
    protected function get_url($endpoint) {
        return $this->base_url . '/' . ltrim($endpoint, '/');
    }

    /**
     * Normalize a WordPress HTTP API response into the client result shape.
     *
     * @param array|WP_Error $response
     * @return array Array containing 'success', 'data', 'code', 'error'.
     */
    protected function decode_response($response) {
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'code'    => 0,
                'error'   => $response->get_error_message(),
                'data'    => null,
            ];
        }

        $code     = (int) wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $decoded  = json_decode($raw_body, true);

        if ($code >= 200 && $code < 300) {
            $payload = (is_array($decoded) && array_key_exists('data', $decoded))
                ? $decoded['data']
                : (is_array($decoded) ? $decoded : ['raw' => $raw_body]);

            return [
                'success' => true,
                'code'    => $code,
                'data'    => $payload,
                'error'   => null,
            ];
        }

        // Error handling
        $message = __('API request failed', 'wazobiamail-transactional-email-deliverability');
        if (is_array($decoded)) {
            if (!empty($decoded['message'])) {
                $message = $decoded['message'];
            } elseif (!empty($decoded['error'])) {
                $message = is_string($decoded['error']) ? $decoded['error'] : wp_json_encode($decoded['error']);
            }
        }

        return [
            'success' => false,
            'code'    => $code,
            'error'   => $message,
            'data'    => $decoded,
        ];
    }

    /**
     * Execute a native WordPress HTTP request.
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $body
     * @param string|null $idempotency_key
     * @return array Array containing 'success', 'data', 'code', 'error'.
     */
    public function request($method, $endpoint, $body = [], $idempotency_key = null) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'code'    => 401,
                'error'   => __('WazobiaMail API Key is mandatory and not configured.', 'wazobiamail-transactional-email-deliverability'),
                'data'    => null,
            ];
        }

        $args = [
            'method'  => strtoupper($method),
            'timeout' => $this->timeout,
            'headers' => $this->get_headers($idempotency_key),
        ];

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true) && !empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        return $this->decode_response(wp_remote_request($this->get_url($endpoint), $args));
    }

    /**
     * Send transactional email.
     *
     * @param array       $message
     * @param string|null $idempotency_key
     * @return array
     */
    public function send_message(array $message, $idempotency_key = null) {
        return $this->request('POST', '/sending/messages', $message, $idempotency_key);
    }

    /**
     * Upload one local file to WazobiaMail attachment storage.
     *
     * The backend route is multipart/form-data with a single "file" part. The
     * response contains the public HTTPS URL that can be supplied as a URL
     * attachment on the send request.
     *
     * @param string $path Absolute path to a readable local file.
     * @return array Array containing 'success', 'data', 'code', 'error'.
     */
    public function upload_attachment($path) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'code'    => 401,
                'error'   => __('WazobiaMail API Key is mandatory and not configured.', 'wazobiamail-transactional-email-deliverability'),
                'data'    => null,
            ];
        }

        if (!is_string($path) || trim($path) === '') {
            return [
                'success' => false,
                'code'    => 400,
                'error'   => __('No attachment file path was provided.', 'wazobiamail-transactional-email-deliverability'),
                'data'    => null,
            ];
        }

        $path = trim($path);

        if (!file_exists($path) || !is_readable($path)) {
            return [
                'success' => false,
                'code'    => 400,
                'error'   => sprintf(
                    /* translators: %s: attachment file path. */
                    __('Attachment "%s" does not exist or is not readable.', 'wazobiamail-transactional-email-deliverability'),
                    $path
                ),
                'data'    => null,
            ];
        }

        if (!is_file($path)) {
            return [
                'success' => false,
                'code'    => 400,
                'error'   => sprintf(
                    /* translators: %s: attachment file path. */
                    __('Attachment "%s" is not a regular file.', 'wazobiamail-transactional-email-deliverability'),
                    $path
                ),
                'data'    => null,
            ];
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return [
                'success' => false,
                'code'    => 400,
                'error'   => sprintf(
                    /* translators: %s: attachment file path. */
                    __('Attachment "%s" is empty or could not be read.', 'wazobiamail-transactional-email-deliverability'),
                    $path
                ),
                'data'    => null,
            ];
        }

        // Mirrors the backend MAX_SINGLE_UPLOAD_BYTES limit.
        if ($size > 10 * 1024 * 1024) {
            return [
                'success' => false,
                'code'    => 400,
                'error'   => sprintf(
                    /* translators: %s: attachment file name. */
                    __('Attachment "%s" is larger than the 10MB upload limit.', 'wazobiamail-transactional-email-deliverability'),
                    basename($path)
                ),
                'data'    => null,
            ];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [
                'success' => false,
                'code'    => 400,
                'error'   => sprintf(
                    /* translators: %s: attachment file name. */
                    __('Attachment "%s" could not be read.', 'wazobiamail-transactional-email-deliverability'),
                    basename($path)
                ),
                'data'    => null,
            ];
        }

        $file_name = basename($path);
        $boundary  = '----WazobiaMailBoundary' . md5(uniqid((string) wp_rand(), true));
        $mime_type = $this->detect_mime_type($path);

        // WordPress has no native file multipart helper, so build the body
        // manually to keep using the native HTTP API.
        $multipart  = '--' . $boundary . "\r\n";
        $multipart .= 'Content-Disposition: form-data; name="file"; filename="' . $this->escape_multipart_filename($file_name) . '"' . "\r\n";
        $multipart .= 'Content-Type: ' . $mime_type . "\r\n\r\n";
        $multipart .= $contents . "\r\n";
        $multipart .= '--' . $boundary . "--\r\n";

        $args = [
            'method'  => 'POST',
            'timeout' => $this->timeout,
            'headers' => $this->get_headers(null, 'multipart/form-data; boundary=' . $boundary),
            'body'    => $multipart,
        ];

        $result = $this->decode_response(wp_remote_request($this->get_url('/sending/attachments/upload'), $args));

        if (!$result['success']) {
            return $result;
        }

        $data = isset($result['data']) && is_array($result['data']) ? $result['data'] : [];
        $file = isset($data['file']) && is_array($data['file']) ? $data['file'] : null;

        if ($file === null) {
            return [
                'success' => false,
                'code'    => 0,
                'error'   => __('Attachment upload succeeded but the API did not return file metadata.', 'wazobiamail-transactional-email-deliverability'),
                'data'    => $data,
            ];
        }

        if (empty($file['publicUrl'])) {
            return [
                'success' => false,
                'code'    => 0,
                'error'   => __('Attachment upload succeeded but no public URL was returned. Ask the WazobiaMail administrator to configure attachment storage.', 'wazobiamail-transactional-email-deliverability'),
                'data'    => $data,
            ];
        }

        return $result;
    }

    /**
     * Detect a file MIME type, falling back to a binary stream.
     *
     * @param string $path
     * @return string
     */
    protected function detect_mime_type($path) {
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if (!empty($detected) && is_string($detected)) {
                return $detected;
            }
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = @finfo_file($finfo, $path);
                finfo_close($finfo);
                if (!empty($detected) && is_string($detected)) {
                    return $detected;
                }
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Escape a filename for a Content-Disposition header.
     *
     * @param string $file_name
     * @return string
     */
    protected function escape_multipart_filename($file_name) {
        return str_replace(
            ["\\", '"', "\r", "\n"],
            ["\\\\", '\\"', '', ''],
            (string) $file_name
        );
    }

    /**
     * Create a sending domain in WazobiaMail.
     *
     * @param string $domain
     * @param string $mail_mode
     * @return array
     */
    public function create_domain($domain, $mail_mode = 'hosted') {
        return $this->request('POST', '/sending/domains', [
            'domain'   => sanitize_text_field($domain),
            'mailMode' => $mail_mode,
        ]);
    }

    /**
     * List all sending domains.
     *
     * @return array
     */
    public function list_domains() {
        return $this->request('GET', '/sending/domains');
    }

    /**
     * Get domain metadata.
     *
     * @param string $domain_id
     * @return array
     */
    public function get_domain($domain_id) {
        return $this->request('GET', '/sending/domains/' . rawurlencode($domain_id));
    }

    /**
     * Get required DNS setup records for a domain.
     *
     * @param string $domain_id
     * @return array
     */
    public function get_domain_dns($domain_id) {
        return $this->request('GET', '/sending/domains/' . rawurlencode($domain_id) . '/dns-records');
    }

    /**
     * Trigger DNS verification check.
     *
     * @param string $domain_id
     * @param bool   $force
     * @return array
     */
    public function verify_domain($domain_id, $force = true) {
        return $this->request('POST', '/sending/domains/' . rawurlencode($domain_id) . '/verify', [
            'force' => (bool) $force,
        ]);
    }

    /**
     * Get tracked message receipt and event timeline.
     *
     * @param string $tracking_id
     * @return array
     */
    public function get_tracking($tracking_id) {
        return $this->request('GET', '/sending/tracking/messages/' . rawurlencode($tracking_id));
    }

    /**
     * Query account health and status.
     *
     * @return array
     */
    public function check_status() {
        return $this->request('GET', '/status');
    }
}
