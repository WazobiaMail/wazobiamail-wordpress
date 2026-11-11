# WazobiaMail for WordPress

Official WazobiaMail WordPress plugin. It replaces the default WordPress mail
transport with the WazobiaMail API — a Resend alternative that also supports
SMTP. Start free with 3,000 emails/month, and automate sending-domain
registration, DNS verification, attachment uploads, and delivery receipts.

**Version:** 0.1.0  
**Requires WordPress:** 5.8 or newer  
**Requires PHP:** 7.4 or newer  
**License:** MIT

## Features

- Routes every `wp_mail()` call through the WazobiaMail transactional API.
- Sends the backend's nested message contract (`content.from`,
  `content.subject`, `content.textBody`, `content.htmlBody`,
  `content.replyTo`) with `to`, `cc`, `bcc`, `envelopeSender`, and URL
  attachments.
- Maps WordPress `Cc`, `Bcc`, `From`, `Reply-To`, `Content-Type`, and
  `Idempotency-Key` headers to their API equivalents.
- Registers the site domain with WazobiaMail, displays the required SPF, DKIM,
  DMARC, MX, and ownership records, and triggers DNS verification.
- Uploads local `wp_mail()` attachments to WazobiaMail attachment storage and
  sends them as public URL attachments. Upload failures abort the send instead
  of silently dropping files.
- Keeps a local log of the latest 50 delivery receipts.
- Provides an admin settings screen, a test-email sender, and an "Active /
  Disabled" dispatch switch.

## Installation

### Install from a ZIP archive

1. Download or build the plugin ZIP.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Select the ZIP, click **Install Now**, then **Activate**.
4. Open **Settings → WazobiaMail** and enter your API key.

### Install from source

Copy (or symlink) the `sdks/wordpress` directory into
`wp-content/plugins/wazobiamail`, then activate **WazobiaMail** from the
WordPress Plugins screen.

The plugin has no runtime Composer dependencies. Composer is only needed for
development so PHPUnit is available (`composer install` in the development
section below).

## Configuration

Open **Settings → WazobiaMail** and configure the **API & Sender** tab.

| Setting | Description |
| --- | --- |
| **WazobiaMail API Key** | Required. The key is sent as the `X-API-Key` header. Keys look like `wzbm_live_...`. |
| **Default From Name** | Display name used when a `From:` header does not provide one. |
| **Default From Email** | Sender address used when a `From:` header does not provide one. The address must belong to a verified WazobiaMail sending domain. |
| **Mail Dispatch Mode** | `Active` intercepts `wp_mail()` and uses WazobiaMail. `Disabled` leaves WordPress' normal mailer in place. |
| **Delivery Logging** | Stores the latest 50 delivery receipts in the `wazobiamail_delivery_logs` option. |
| **API Gateway Base URL** | Advanced setting. Defaults to `https://app.wazobiamail.com/api/v1`. |

The values are stored in the `wazobiamail_options` WordPress option. The API key
is displayed masked; saving the masked placeholder preserves the stored key.

## Sending email

The plugin registers a `pre_wp_mail` filter. When WazobiaMail is active, the
message is sent with `POST /sending/messages` and the normal WordPress PHPMailer
path is short-circuited.

```php
wp_mail(
    'customer@example.com',
    'Your order is ready',
    '<p>Thanks for your order.</p>',
    [
        'Content-Type: text/html; charset=UTF-8',
        'From: Example Store <orders@example.com>',
        'Reply-To: Support <support@example.com>',
        'Cc: accounts@example.com',
        'Bcc: archive@example.com',
        'Idempotency-Key: order-1042-ready',
    ],
    ['/var/www/uploads/invoice-1042.pdf']
);
```

### Payload mapping

The plugin sends the nested contract expected by the backend. Flat top-level
`from`, `subject`, `html`, and `text` fields are not sent.

| WordPress input | WazobiaMail API field |
| --- | --- |
| `$to` | `to[]` (email addresses only) |
| `Cc:` header | `cc[]` |
| `Bcc:` header | `bcc[]` |
| `From:` header or configured sender | `content.from` (`Name <email>`) and `envelopeSender` (email only) |
| `$subject` | `content.subject` |
| HTML `$message` | `content.htmlBody`; `content.textBody` contains a tag-stripped version |
| Plain-text `$message` | `content.textBody` |
| `Reply-To:` header | `content.replyTo` |
| Local attachments | `attachments[].url` and `attachments[].fileName` after upload |
| `Idempotency-Key:` header | `Idempotency-Key` request header, forwarded unchanged |

HTML is detected from a `Content-Type: text/html` header or from common HTML
markup (`<html`, `<p>`, `<br`). A message with no usable sender address omits
`content.from` and leaves `envelopeSender` empty so the backend can fall back to
the API key's mailbox.

If delivery fails, the plugin fires the standard `wp_mail_failed` action with a
`WP_Error` and returns `false` from the `pre_wp_mail` filter. If WazobiaMail is
disabled, the filter returns `null` and WordPress continues with its default
mailer.

## Attachments

WordPress passes local file paths to `wp_mail()`. The plugin:

1. Uploads each file with a multipart `POST /sending/attachments/upload`
   request. The multipart part is named `file`.
2. Reads `data.file.publicUrl` and `data.file.fileName` from the response.
3. Adds `{ "url": "...", "fileName": "..." }` to the send payload's
   `attachments` array.

If a file is missing, unreadable, empty, larger than the 10 MB single-upload
limit, fails to upload, or the upload response has no public URL, the send is
aborted with an error such as:

```text
Failed to upload email attachment "invoice.pdf": Attachments storage is not configured
```

The failure is also written to the delivery log. Attachments are never silently
dropped.

Backend limits to keep in mind:

- One file per upload request, maximum 10 MB.
- The send endpoint accepts at most 10 URL attachments.
- The combined size of all attachments on a send is limited to 5 MB by the
  backend, even though a single upload may be up to 10 MB.
- URL attachments must be public HTTPS URLs. This means the WazobiaMail
  deployment must have attachment storage and a public attachment base URL
  configured. If that is missing, uploads succeed without a `publicUrl` and the
  plugin reports a clear error.
- The plugin uses the upload + URL-attachment flow and does not call
  `POST /sending/messages/with-attachments`.

## Domain registration and DNS automation

The **Site Domain & DNS** tab uses the following backend routes:

| Action | Route |
| --- | --- |
| List sending domains | `GET /sending/domains` |
| Register a sending domain | `POST /sending/domains` |
| Read domain DNS records | `GET /sending/domains/{id}/dns-records` |
| Verify DNS | `POST /sending/domains/{id}/verify` |

When you register the site domain, the plugin reuses an existing matching domain
or creates a new one and stores its ID in `wazobiamail_options`. The DNS table
reads the backend's `data.domain.records` payload and displays each record's
type, host/name, value, and verification status. DNS verification updates the
stored `domain_status` to `verified` or keeps it at `pending_dns` while records
propagate.

The plugin does not modify your DNS provider directly; it shows the records that
must be added at your DNS host (Cloudflare, Route 53, Namecheap, cPanel, and so
on).

## Hooks and filters

WazobiaMail integrates with standard WordPress hooks:

| Hook | Type | Purpose |
| --- | --- | --- |
| `pre_wp_mail` | filter | Intercepts `wp_mail()` before WordPress sends. Returning `false` fails the send; returning `null` lets WordPress' default mailer run (used when dispatch is disabled). |
| `wp_mail_failed` | action | Fired with a `WP_Error` when WazobiaMail is unconfigured or a send fails. |
| `plugin_action_links_{basename}` | filter | Adds the **Settings** link on the Plugins screen. |

The plugin exposes no custom WazobiaMail filters in 0.1.0. Use `pre_wp_mail` to
conditionally route, alter, or short-circuit messages before WazobiaMail builds
its request.

Stored options:

- `wazobiamail_options` — API key, sender defaults, gateway URL, dispatch mode,
  domain metadata, and logging preference.
- `wazobiamail_delivery_logs` — the latest 50 delivery receipts (when logging is
  enabled).

## Delivery logs

The **Delivery Logs** tab lists the timestamp, recipient, subject, tracking ID,
and status (`queued`, `delivered`, or `failed`) for recent sends. Logging can be
disabled on the settings tab, and the log can be cleared from the admin screen.

## Development and tests

```bash
cd sdks/wordpress
composer install
vendor/bin/phpunit
```

The suite uses a WordPress function mock in `tests/bootstrap.php`; there is no
WordPress installation or database required. `WPMockState` provides mocked
options, actions, filters, JSON responses, and a FIFO HTTP response queue via
`WPMockState::queue_response()`.

Useful checks before submitting a change:

```bash
cd sdks/wordpress
vendor/bin/phpunit
php -l includes/class-wazobiamail-api-client.php
php -l includes/class-wazobiamail-mailer.php
php -l includes/class-wazobiamail-domain-manager.php
composer validate
```

The test files are:

- `tests/MailerTest.php` — recipient/header parsing, nested send payload,
  attachment upload/failure flow, interception, and log rotation.
- `tests/ApiClientTest.php` — auth headers, JSON requests, multipart attachment
  uploads, error handling, and DNS/domain routes.
- `tests/DomainManagerTest.php` — domain registration, DNS record parsing, and
  verification status.
- `tests/AdminSettingsTest.php` — activation defaults, admin AJAX handlers,
  settings persistence, and escaped settings output.

When adding a new backend route, add a client method, keep the response shape
consistent with the backend (`data` wrapper), and queue a mocked response in the
corresponding test.

## Troubleshooting

- **"WazobiaMail API key is mandatory but not configured."** Add a key on the
  settings screen. The plugin does not fall back to PHPMailer while active.
- **"Unverified sender domain."** Register the domain on the DNS tab, add the
  listed records at your DNS provider, and run verification.
- **"Attachment upload succeeded but no public URL was returned."** The backend
  deployment needs attachment storage and a public attachment base URL.
- **Messages are not intercepted.** Check that **Mail Dispatch Mode** is
  `Active` and that another plugin is not returning a non-null value from
  `pre_wp_mail` first.
- **DNS records are empty.** The domain may still be provisioning; refresh the
  DNS tab after the backend finishes creating records.

## License

MIT. See [LICENSE](LICENSE).
