# WordPress.org submission guide

This directory is ready for the WordPress.org plugin directory. The remaining
steps require a WordPress.org account and the directory review team; they cannot
be completed from this machine.

## What is already prepared

- Plugin header and `Stable tag: 0.1.1`.
- `readme.txt` with description, installation, FAQ, external-service disclosure,
  privacy section, changelog and upgrade notice.
- `uninstall.php` that removes settings, delivery logs and cached DNS data
  (including multisite).
- WordPress.org assets in `.wordpress-org/`:
  - `icon-128x128.png`
  - `icon-256x256.png`
  - `banner-772x250.png`
  - `banner-1544x500.png`
- `.distignore` and `build.sh` for a clean plugin ZIP.
- GitHub Actions workflow for SVN deployment after approval
  (`.github/workflows/deploy-wordpress-org.yml`).
- Plugin tests and PHP lint.

## 1. Before submitting

```bash
cd sdks/wordpress
php -l wazobiamail.php
find includes templates -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit
bash build.sh
```

Validate `readme.txt` with the official validator:

https://wordpress.org/plugins/developers/readme-validator/

Recommended: install the **Plugin Check** plugin on a disposable WordPress site
and run it against the ZIP:

https://wordpress.org/plugins/plugin-check/

## 2. Check the contributor name

`readme.txt` currently lists `Contributors: oyenet1`. Replace it with your
WordPress.org username if different. The submitting account becomes the plugin
owner.

## 3. Submit

1. Sign in at https://wordpress.org/ and open
   https://wordpress.org/plugins/developers/.
2. Click **Submit a Plugin**.
3. Upload `dist/wazobiamail-0.1.1.zip` (built by `bash build.sh`).
4. Fill in the form:
   - Plugin name: **WazobiaMail - Transactional Email & Deliverability**
   - Description: the short description from `readme.txt`.
   - External service: **WazobiaMail API**, `https://app.wazobiamail.com/api/v1`.
   - Terms: https://wazobiamail.com/terms/
   - Privacy: https://wazobiamail.com/privacy/
   - Account required: yes, a WazobiaMail API key and verified sending domain.
   - The plugin sends email content and recipient data to WazobiaMail; it does
     not add analytics or third-party scripts.
5. Submit and watch the email address on your WordPress.org account.

The review team may ask for changes. Reply in the same ticket; do not create a
second submission.

## 4. After approval: SVN

WordPress.org gives SVN access to:

```text
https://plugins.svn.wordpress.org/wazobiamail/
```

Use your WordPress.org username and password (not GitHub credentials). The first
checkout may be empty.

```bash
svn checkout https://plugins.svn.wordpress.org/wazobiamail/ wazobiamail-svn
cd wazobiamail-svn
```

Copy the plugin files into `trunk`, excluding development files:

```bash
rsync -a --delete \
  --exclude-from=/path/to/sdks/wordpress/.distignore \
  /path/to/sdks/wordpress/ trunk/
```

Copy the directory assets into the SVN `assets` directory:

```bash
mkdir -p assets
cp /path/to/sdks/wordpress/.wordpress-org/* assets/
```

Create the tag from `trunk` and commit:

```bash
svn add --force trunk assets
svn cp trunk tags/0.1.1
svn add --force tags/0.1.1
svn commit -m "Release WazobiaMail 0.1.1" --username YOUR_WORDPRESS_ORG_USERNAME
```

The SVN tag **must** match `Stable tag: 0.1.1` in `readme.txt`. WordPress.org
builds the download ZIP from that tag.

## 5. Alternative: GitHub Actions

After approval, add repository secrets:

- `SVN_USERNAME` — WordPress.org username
- `SVN_PASSWORD` — WordPress.org password

Then run **Actions → Deploy to WordPress.org → Run workflow** from the plugin
repository. The workflow uses `10up/action-wordpress-plugin-deploy`, deploys
`trunk`, creates the `0.1.1` tag, and copies `.wordpress-org` into SVN `assets`.

The workflow is manual (`workflow_dispatch`) so it will not fail before the
account is approved and secrets exist.

## 6. Version bumps

For the next release:

1. Bump `Version:` and `WAZOBIAMAIL_VERSION` in `wazobiamail.php`.
2. Bump `Stable tag` and add a changelog/upgrade entry in `readme.txt`.
3. Bump the API client version and its test assertion.
4. Run the tests and `bash build.sh`.
5. Commit, tag, push, then deploy through SVN/Actions.
