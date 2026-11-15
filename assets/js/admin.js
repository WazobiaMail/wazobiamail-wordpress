(function () {
    'use strict';

    var config = window.wazobiamailAdmin || {};
    var i18n = config.i18n || {};
    var ajaxUrl = config.ajaxUrl || window.ajaxurl || '';
    var nonce = config.nonce || '';

    function renderNotice(container, type, message, boldPrefix) {
        if (!container) {
            return;
        }
        container.textContent = '';
        var notice = document.createElement('div');
        notice.className = 'notice notice-' + type;
        var p = document.createElement('p');
        if (boldPrefix) {
            var strong = document.createElement('strong');
            strong.textContent = boldPrefix + ' ';
            p.appendChild(strong);
        }
        p.appendChild(document.createTextNode(String(message || '')));
        notice.appendChild(p);
        container.appendChild(notice);
    }

    function postAjax(action, extraParams) {
        var data = new URLSearchParams();
        data.append('action', action);
        data.append('nonce', nonce);
        if (extraParams) {
            Object.keys(extraParams).forEach(function (key) {
                data.append(key, extraParams[key]);
            });
        }

        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: data.toString()
        }).then(function (response) {
            return response.json();
        });
    }

    function toggleApiKeyVisibility() {
        var field = document.getElementById('api_key');
        if (!field) {
            return;
        }
        field.type = field.type === 'password' ? 'text' : 'password';
    }

    function handleRegisterDomain() {
        var btn = document.getElementById('btn-register-domain');
        var resDiv = document.getElementById('domain-action-result');
        if (!btn || !resDiv) {
            return;
        }

        btn.disabled = true;
        resDiv.style.display = 'block';
        renderNotice(resDiv, 'info', i18n.registeringDomain || 'Registering site domain with WazobiaMail API...');

        postAjax('wazobiamail_register_domain')
            .then(function (res) {
                btn.disabled = false;
                if (res && res.success) {
                    renderNotice(resDiv, 'success', res.data && res.data.message);
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else {
                    renderNotice(resDiv, 'error', res && res.data);
                }
            })
            .catch(function (err) {
                btn.disabled = false;
                renderNotice(resDiv, 'error', err && err.message ? err.message : (i18n.requestFailed || 'Request failed.'));
            });
    }

    function handleVerifyDns() {
        var btn = document.getElementById('btn-verify-dns');
        var resDiv = document.getElementById('domain-action-result');
        if (!btn || !resDiv) {
            return;
        }

        btn.disabled = true;
        resDiv.style.display = 'block';
        renderNotice(resDiv, 'info', i18n.verifyingDns || 'Querying DNS verification...');

        postAjax('wazobiamail_verify_dns')
            .then(function (res) {
                btn.disabled = false;
                if (res && res.success) {
                    renderNotice(resDiv, 'success', res.data && res.data.message);
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else {
                    renderNotice(resDiv, 'error', res && res.data);
                }
            })
            .catch(function (err) {
                btn.disabled = false;
                renderNotice(resDiv, 'error', err && err.message ? err.message : (i18n.requestFailed || 'Request failed.'));
            });
    }

    function handleSendTestEmail() {
        var btn = document.getElementById('btn-send-test-email');
        var spinner = document.getElementById('test-email-spinner');
        var resDiv = document.getElementById('test-email-result');
        var emailInput = document.getElementById('test_recipient');
        if (!btn || !spinner || !resDiv || !emailInput) {
            return;
        }

        var email = emailInput.value;
        if (!email) {
            resDiv.style.display = 'block';
            renderNotice(resDiv, 'error', i18n.missingRecipient || 'Please enter a recipient email address.');
            return;
        }

        btn.disabled = true;
        spinner.classList.add('is-active');
        resDiv.style.display = 'block';
        renderNotice(resDiv, 'info', i18n.sendingTestEmail || 'Dispatching test email via WazobiaMail REST API...');

        postAjax('wazobiamail_send_test_email', { recipient: email })
            .then(function (res) {
                btn.disabled = false;
                spinner.classList.remove('is-active');
                if (res && res.success) {
                    renderNotice(resDiv, 'success', res.data && res.data.message);
                } else {
                    renderNotice(resDiv, 'error', res && res.data, i18n.failedPrefix || 'Failed:');
                }
            })
            .catch(function (err) {
                btn.disabled = false;
                spinner.classList.remove('is-active');
                renderNotice(resDiv, 'error', err && err.message ? err.message : (i18n.requestFailed || 'Request failed.'));
            });
    }

    function handleClearLogs() {
        if (!window.confirm(i18n.confirmClearLogs || 'Are you sure you want to clear delivery logs?')) {
            return;
        }

        postAjax('wazobiamail_clear_logs')
            .then(function () {
                window.location.reload();
            })
            .catch(function () {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        var toggleBtn = document.getElementById('wazobiamail-toggle-key');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleApiKeyVisibility);
        }

        var registerBtn = document.getElementById('btn-register-domain');
        if (registerBtn) {
            registerBtn.addEventListener('click', handleRegisterDomain);
        }

        var verifyBtn = document.getElementById('btn-verify-dns');
        if (verifyBtn) {
            verifyBtn.addEventListener('click', handleVerifyDns);
        }

        var sendTestBtn = document.getElementById('btn-send-test-email');
        if (sendTestBtn) {
            sendTestBtn.addEventListener('click', handleSendTestEmail);
        }

        var clearLogsBtn = document.getElementById('btn-clear-logs');
        if (clearLogsBtn) {
            clearLogsBtn.addEventListener('click', handleClearLogs);
        }
    });
})();
