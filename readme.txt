=== WazobiaMail - Transactional Email & Deliverability ===
Contributors: oyenet1
Tags: transactional email, email delivery, dns, deliverability, wp_mail
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Send WordPress email through the WazobiaMail API. Start free with 3,000 emails/month. A Resend alternative that also supports SMTP, with automatic domain setup, DNS verification, URL attachments, and delivery receipts.

== Description ==

WazobiaMail is the official WordPress plugin for the WazobiaMail transactional
email platform. It replaces the default WordPress mail transport with the
WazobiaMail REST API so contact forms, order notifications, password resets, and
other transactional messages are delivered through a dedicated sending
infrastructure.

Features:

* Intercepts `wp_mail()` through the `pre_wp_mail` filter and sends through the
  WazobiaMail API.
* Uses the backend's nested message payload with `to`, `cc`, `bcc`, `content`,
  `envelopeSender`, and URL attachments.
* Maps WordPress `From`, `Reply-To`, `Cc`, `Bcc`, `Content-Type`, and
  `Idempotency-Key` headers to their API equivalents.
* Registers the site domain and displays the SPF, DKIM, DMARC, MX, and ownership
  DNS records required for verification.
* Uploads local `wp_mail()` attachments to WazobiaMail storage and sends them as
  public URL attachments. A failed upload fails the send with a clear error.
* Shows a configurable test-email form and keeps the latest 50 delivery receipts
  in the WordPress admin.
* Includes an active/disabled dispatch switch so the default WordPress mailer can
  be restored without deleting the plugin.

The plugin does not modify DNS records directly. It displays the records to add
at your DNS provider and triggers WazobiaMail verification after they propagate.

== Installation ==

1. Upload the `wazobiamail` plugin directory to `/wp-content/plugins/`, or install
   the plugin ZIP from **Plugins → Add New → Upload Plugin**.
2. Activate **WazobiaMail** from the Plugins screen.
3. Go to **Settings → WazobiaMail**.
4. Enter your WazobiaMail API key.
5. Optionally set the default From name and From email, then save the settings.
6. Open the **Site Domain & DNS** tab, register the site domain, add the listed
   DNS records at your DNS provider, and run **Verify DNS Records Now**.
7. Send a test email from the **Send Test Email** tab.

== Frequently Asked Questions ==

= Do I need a WazobiaMail account? =

Yes. The plugin requires an API key from a WazobiaMail account and at least one
verified sending domain.

= Does this plugin replace SMTP? =

Yes, while **Mail Dispatch Mode** is set to **Active**. The plugin sends through
the WazobiaMail REST API instead of WordPress' local PHPMailer transport. Set the
mode to **Disabled** to restore the default WordPress mailer.

= How are attachments handled? =

Each attachment is uploaded to `POST /sending/attachments/upload` as a multipart
form with a single `file` part. The returned `publicUrl` and `fileName` are added
to the send request's `attachments` array as a URL attachment. Missing files,
upload errors, or responses without a public URL abort the send and are written
to the delivery log. Files are never silently dropped.

= Why do I see an error that no public URL was returned for an attachment? =

The WazobiaMail backend needs attachment storage and a public attachment base URL
to expose uploaded files. Ask the WazobiaMail administrator to configure those
settings, or send the message without attachments.

= Where do I find the DNS records? =

Open **Settings → WazobiaMail → Site Domain & DNS**. After the domain is
registered, the tab lists the records from
`GET /sending/domains/{id}/dns-records`. Add them at your DNS provider, then run
the verification button.

= Will the plugin work if the API key is missing? =

No. Email delivery is aborted and an admin notice plus a `wp_mail_failed` action
are emitted. Disable the plugin or switch **Mail Dispatch Mode** to **Disabled**
to fall back to WordPress' mailer.

= Are delivery receipts tracked? =

The send response's tracking ID is stored in the local delivery log. Open the
**Delivery Logs** tab to view recent receipts, or disable logging on the settings
tab.

== External services ==

This plugin connects to the WazobiaMail transactional email service to deliver
the messages WordPress generates.

* **Service:** WazobiaMail
* **API endpoint:** `https://app.wazobiamail.com/api/v1`
* **What it does:** sends transactional email, registers the site domain, reads
  DNS requirements and verification status, uploads attachments, and reads
  delivery/tracking receipts.
* **Data sent:** the API key, sender and recipient addresses, CC/BCC, Reply-To,
  subject, HTML/text bodies, attachment filenames and contents, the site domain,
  and the DNS/tracking queries needed for the requested operation.
* **When data is sent:** when `wp_mail()` is dispatched while the plugin is
  Active, when an administrator saves settings, registers or verifies the site
  domain, sends a test email, or uploads an attachment. No email data is sent
  until an API key is saved and dispatch is enabled.
* **Account required:** yes. The plugin does not work without a WazobiaMail
  account and API key.
* **Terms of service:** https://wazobiamail.com/terms/
* **Privacy policy:** https://wazobiamail.com/privacy/
* **Support:** https://docs.wazobiamail.com/

== Privacy ==

WazobiaMail sends the email content and metadata described above to the
WazobiaMail API so the message can be delivered. The plugin does not add
analytics, advertising trackers or third-party scripts to your site.

The plugin stores its settings and the latest 50 delivery log entries in the
WordPress options table (`wazobiamail_options` and
`wazobiamail_delivery_logs`). Logging can be disabled in settings. Uninstalling
the plugin removes these options for the current site, or for every site on a
multisite installation. Cached DNS records are stored in a short-lived
transient and removed on deactivation/uninstall.

See the WazobiaMail privacy policy for how the service processes email data:
https://wazobiamail.com/privacy/

== Changelog ==

= 0.1.1 =
* Prepare the WordPress.org submission: uninstall cleanup, external-service and
  privacy disclosures, plugin icons/banners, and a clean distribution build.
* Load bundled translations from the plugin `languages` directory.
* Bump the API client user agent to 0.1.1.

= 0.1.0 =
* Initial release.
* Route `wp_mail()` through the WazobiaMail nested message API.
* Map WordPress `From`, `Reply-To`, `Cc`, `Bcc`, `Content-Type`, and
  `Idempotency-Key` headers.
* Add attachment upload support with URL attachments and explicit upload
  failures.
* Add site domain registration and DNS record display using the
  `/sending/domains/{id}/dns-records` route.
* Add the admin settings screen, test email sender, and delivery log.

== Upgrade Notice ==

= 0.1.1 =
Metadata and WordPress.org submission preparation; no breaking changes.

= 0.1.0 =
Initial public release.
