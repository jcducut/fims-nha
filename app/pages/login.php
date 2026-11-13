<?php
session_start();
require_once(__DIR__ . "/../../_shared/auth.php");

// This page's own JS reads a fresh CSRF token pair from a live fetch() of
// /login before every sign-in attempt — a stale cached copy of THIS page
// (old HTML/JS) would keep retrying that flow the old way indefinitely, no
// matter how many times the actual login/CSRF logic gets fixed server-side.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (isAnyUserLoggedIn()) {
    $homeUrl = $_SESSION['nha_home_url'] ?? '/MainDashboard';
    header('Location: ' . $homeUrl);
    exit;
}

// Success flash after a completed password change (from /changepassword → back).
$pwdChanged = isset($_GET['pwd']) && $_GET['pwd'] === 'changed';

// Set by models/Login.php right after a failed credential check, instead of
// letting a POST to /login (which isn't rewritten to this page by nginx.conf,
// unlike "/") fall through and render the framework's own default
// views/Login.php template.
$loginFailed = isset($_GET['login']) && $_GET['login'] === 'failed';

// Set by ResetPassword.php right before it redirects here, after the user
// clicks the password-reset link emailed to them — same flag the framework's
// own IsPasswordReset() checks. Shows the embedded Change Password panel
// below instead of the normal sign-in form, since the framework itself won't
// let this user do anything else until they set a new password.
$showChangePassword = (($_SESSION['FIMS_Status'] ?? '') === 'passwordreset');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NHA FIMS — Sign In</title>
    <link rel="icon" type="image/png" href="/_shared/images/NHA.png">
    <link rel="apple-touch-icon" href="/_shared/images/NHA.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@100;300;400&family=Roboto:wght@400;500&family=Geist:wght@300;400&family=Syne:wght@400&family=IBM+Plex+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css') ?>" />
</head>
<body class="login-page">
<div class="page-bg"></div>
<div class="page-overlay"></div>
<div class="main-container">
<div class="content-box">

    <!-- ── LEFT: Branding ── -->
    <div class="logo-section">
        <div class="left-header">
            <div class="logo-wrap">
                <div class="logo-wrap-inner">
                    <?php
                        $svgContent = file_get_contents(__DIR__ . '/../../_shared/images/nha-logo-amin.svg');
                        echo str_replace('<svg id="eNuldLC5tMO1"', '<svg id="eNuldLC5tMO1" class="left-logo-svg"', $svgContent);
                    ?>
                </div>
            </div>
            <div class="left-headings">
                <h1 class="heading-main">National Housing Authority</h1>
                <h2 class="heading-sub">Financial Information Management System</h2>
            </div>
        </div>

        <div class="left-footer">
            <div class="nha-badge">
                <div class="badge-shield">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 14l-3-3 1.41-1.41L11 12.17l4.59-4.58L17 9l-6 6z"/>
                    </svg>
                </div>
                <span class="badge-label">Secure Login</span>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: Login form card ── -->
    <div class="login-container">
        <div class="login-form">
            <div class="form-margin">

                <div class="form-header">
                    <div class="secure-badge">
                        <i class="fas fa-lock"></i>
                        Secure Portal
                    </div>
                    <p class="form-subtitle">
                        <?= $showChangePassword
                            ? 'Set a new password to continue.'
                            : 'Use your NHA-issued credentials to proceed.' ?>
                    </p>
                </div>

                <?php if (isset($_SESSION['flash_failure']) || $loginFailed): ?>
                <div class="flash-error" id="flash-failure">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['flash_failure'] ?? 'Incorrect username or password.') ?>
                </div>
                <?php unset($_SESSION['flash_failure']); endif; ?>

                <div class="flash-success" id="flash-success" style="<?= $pwdChanged ? 'display:flex;' : 'display:none;' ?>">
                    <i class="fas fa-check-circle"></i>
                    <span id="flash-success-msg"><?= $pwdChanged ? 'Your password has been updated. Please sign in with your new password.' : '' ?></span>
                </div>

                <form name="flogin" id="flogin" action="/login" method="post" novalidate autocomplete="off" style="<?= $showChangePassword ? 'display:none;' : '' ?>">

                    <div class="field-group">
                        <div class="field-wrap">
                            <input class="field-input" type="text" name="username" id="username"
                                   placeholder=" " autocomplete="username" required>
                            <label class="field-label" for="username">Username</label>
                        </div>
                    </div>

                    <div class="field-group">
                        <div class="field-wrap">
                            <input class="field-input" type="password" name="password" id="password"
                                   placeholder=" " autocomplete="current-password" required>
                            <label class="field-label" for="password">Password</label>
                            <button type="button" class="toggle-password" onclick="togglePassword()" title="Toggle password">
                                <i class="fa-solid fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="retain-row">
                        <input type="checkbox" id="retain" name="retain_session">
                        <label for="retain">Retain Session</label>
                    </div>

                    <button type="submit" class="login-btn" id="btn-submit">
                        <span>Authenticate</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <div class="forgot-row">
                        <button type="button" class="forgot-link" id="forgot-link">Forgot password?</button>
                    </div>

                </form>

                <form name="freset" id="freset" action="/resetpassword" method="post" novalidate autocomplete="off" style="display:none;">

                    <div class="field-group" id="email-group">
                        <div class="field-wrap">
                            <input class="field-input" type="email" name="email" id="reset-email"
                                   placeholder=" " autocomplete="email" required
                                   maxlength="100">
                            <label class="field-label" for="reset-email">Email Address</label>
                        </div>
                    </div>
                    <div class="field-error" id="email-error">Please enter a valid email address.</div>

                    <button type="submit" class="login-btn" id="btn-reset">
                        <span>Send Reset Link</span>
                        <i class="fas fa-paper-plane"></i>
                    </button>

                    <div class="forgot-row" style="justify-content:flex-start;">
                        <button type="button" class="back-link" id="back-link">
                            <i class="fas fa-arrow-left"></i> Back to Sign In
                        </button>
                    </div>

                </form>

                <!-- Reached only via the emailed password-reset link (sets the
                     FIMS_Status "passwordreset" session flag checked above) —
                     not a normal logged-in user's own "change my password"
                     screen, which stays inside the main app. -->
                <form name="fchangepass" id="fchangepass" action="/changepassword" method="post" novalidate autocomplete="off" style="<?= $showChangePassword ? '' : 'display:none;' ?>">

                    <div class="field-group">
                        <div class="field-wrap">
                            <input class="field-input" type="password" name="npwd" id="new-password"
                                   placeholder=" " autocomplete="new-password" required>
                            <label class="field-label" for="new-password">New Password</label>
                            <button type="button" class="toggle-password" data-toggle-for="new-password" title="Toggle password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="field-group" id="confirm-password-group">
                        <div class="field-wrap">
                            <input class="field-input" type="password" name="cpwd" id="confirm-password"
                                   placeholder=" " autocomplete="new-password" required>
                            <label class="field-label" for="confirm-password">Confirm Password</label>
                            <button type="button" class="toggle-password" data-toggle-for="confirm-password" title="Toggle password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="field-error" id="confirm-password-error">Passwords do not match.</div>

                    <button type="submit" class="login-btn" id="btn-changepass">
                        <span>Set New Password</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                </form>
            </div>
        </div>

        <div class="login-footer">
            By using this service, you agree that your information may be collected, stored, used, and
            disclosed under the Data Privacy Act of 2012 (RA No. 10173). NHA is committed to protecting
            personal information and maintaining privacy, accuracy, and security.
        </div>
    </div>

</div>
</div>

<script>
    function togglePassword() {
        const f = document.getElementById('password');
        const i = document.getElementById('togglePasswordIcon');
        if (f.type === 'password') {
            f.type = 'text';
            i.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            f.type = 'password';
            i.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    document.getElementById('flogin').addEventListener('submit', function (e) {
        e.preventDefault();

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        if (!username || !password) {
            alert('Please enter both username and password.');
            return;
        }

        const btn = document.getElementById('btn-submit');
        btn.disabled = true;
        btn.innerHTML = '<span>Signing in…</span><i class="fas fa-spinner fa-spin"></i>';

        // X-Requested-With tells the server this is the internal CSRF-harvest
        // fetch, not real navigation — otherwise /login redirects plain GETs
        // back to "/", and this would harvest tokens from the wrong page.
        fetch('/login', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const csrfInputs = doc.querySelectorAll('input[name*="csrf"]');
                if (csrfInputs.length >= 2) {
                    [csrfInputs[0], csrfInputs[1]].forEach(src => {
                        const h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = src.name;
                        h.value = src.value;
                        this.appendChild(h);
                    });
                }
                this.submit();
            })
            .catch(() => {
                alert('Error connecting to login server. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<span>Authenticate</span><i class="fas fa-arrow-right"></i>';
            });
    });

    /* ══════════════════════════════════════
       FORGOT PASSWORD — toggle + submit flow
    ══════════════════════════════════════ */
    const loginForm  = document.getElementById('flogin');
    const resetForm  = document.getElementById('freset');
    const forgotLink = document.getElementById('forgot-link');
    const backLink   = document.getElementById('back-link');
    const emailInput = document.getElementById('reset-email');
    const emailGroup = document.getElementById('email-group');
    const emailError = document.getElementById('email-error');
    const flashOk    = document.getElementById('flash-success');
    const flashOkMsg = document.getElementById('flash-success-msg');
    const flashErr   = document.getElementById('flash-failure');

    // RFC5322-lite pattern — matches HTML5 built-in checker
    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function showReset() {
        loginForm.style.display = 'none';
        resetForm.style.display = 'flex';
        if (flashErr) flashErr.style.display = 'none';
        flashOk.style.display = 'none';
        emailInput.value = '';
        clearEmailError();
        setTimeout(() => emailInput.focus(), 0);
    }

    function showLogin() {
        resetForm.style.display = 'none';
        loginForm.style.display = 'flex';
        clearEmailError();
    }

    function clearEmailError() {
        emailGroup.classList.remove('invalid');
        emailInput.classList.remove('invalid');
        emailError.classList.remove('show');
    }

    function markEmailError(msg) {
        emailError.textContent = msg;
        emailGroup.classList.add('invalid');
        emailInput.classList.add('invalid');
        emailError.classList.add('show');
    }

    forgotLink.addEventListener('click', showReset);
    backLink.addEventListener('click', showLogin);

    emailInput.addEventListener('input', clearEmailError);

    resetForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const email = emailInput.value.trim();

        if (!email) {
            markEmailError('Please enter your email address.');
            emailInput.focus();
            return;
        }
        if (!EMAIL_RE.test(email) || email.length > 100) {
            markEmailError('Please enter a valid email address.');
            emailInput.focus();
            return;
        }

        const btn = document.getElementById('btn-reset');
        btn.disabled = true;
        btn.innerHTML = '<span>Sending…</span><i class="fas fa-spinner fa-spin"></i>';

        // PHPMaker's /resetpassword guards every POST with a pair of CSRF
        // tokens that are re-generated per request and validated against the
        // session (Slim-Csrf) — a fetch()+FormData POST built from scratch
        // has no way to obtain a valid pair. So, same approach as the real
        // login form above: fetch the page once to harvest its real field
        // name + fresh CSRF pair, graft them onto this form, then do a real
        // submit (full navigation) so the browser handles cookies/redirect
        // exactly as it would for a native form post.
        fetch('/resetpassword')
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');

                // Locate PHPMaker's email input to pick up its real field name
                // (in case it ever stops being the literal "email").
                const emailField = doc.querySelector('input[id$="email"][type="text"], input[name$="email"]');
                if (emailField && emailField.name && emailField.name !== emailInput.name) {
                    emailInput.name = emailField.name;
                }

                // Drop any stale hidden fields from a previous attempt, then
                // graft a fresh CSRF pair + "modal" flag from the fetched form.
                resetForm.querySelectorAll('input[type="hidden"][data-harvested]').forEach(el => el.remove());
                const hiddenFields = doc.querySelectorAll('input[type="hidden"][name*="csrf"], input[type="hidden"][name="modal"]');
                hiddenFields.forEach(src => {
                    const h = document.createElement('input');
                    h.type = 'hidden';
                    h.name = src.name;
                    h.value = src.value;
                    h.dataset.harvested = '1';
                    resetForm.appendChild(h);
                });

                resetForm.submit();
            })
            .catch(() => {
                markEmailError('Could not reach the server. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<span>Send Reset Link</span><i class="fas fa-paper-plane"></i>';
            });
    });

    /* ══════════════════════════════════════
       CHANGE PASSWORD — reached only via the
       emailed reset link (see $showChangePassword)
    ══════════════════════════════════════ */
    document.querySelectorAll('.toggle-password[data-toggle-for]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const field = document.getElementById(this.dataset.toggleFor);
            const icon = this.querySelector('i');
            if (!field || !icon) return;
            const show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
        });
    });

    const changePassForm = document.getElementById('fchangepass');
    if (changePassForm) {
        const newPasswordInput = document.getElementById('new-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const confirmPasswordGroup = document.getElementById('confirm-password-group');
        const confirmPasswordError = document.getElementById('confirm-password-error');

        function clearConfirmError() {
            confirmPasswordGroup.classList.remove('invalid');
            confirmPasswordInput.classList.remove('invalid');
            confirmPasswordError.classList.remove('show');
        }
        function markConfirmError(msg) {
            confirmPasswordError.textContent = msg;
            confirmPasswordGroup.classList.add('invalid');
            confirmPasswordInput.classList.add('invalid');
            confirmPasswordError.classList.add('show');
        }
        confirmPasswordInput.addEventListener('input', clearConfirmError);

        changePassForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (!newPassword || !confirmPassword) {
                markConfirmError('Please fill in both password fields.');
                return;
            }
            if (newPassword !== confirmPassword) {
                markConfirmError('Passwords do not match.');
                confirmPasswordInput.focus();
                return;
            }

            const btn = document.getElementById('btn-changepass');
            btn.disabled = true;
            btn.innerHTML = '<span>Saving…</span><i class="fas fa-spinner fa-spin"></i>';

            // Same CSRF-harvest-then-real-submit pattern as the forms above —
            // /changepassword is guarded by the same per-session Slim-Csrf pair.
            fetch('/changepassword', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');

                    changePassForm.querySelectorAll('input[type="hidden"][data-harvested]').forEach(el => el.remove());
                    const hiddenFields = doc.querySelectorAll('input[type="hidden"][name*="csrf"], input[type="hidden"][name="modal"]');
                    hiddenFields.forEach(src => {
                        const h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = src.name;
                        h.value = src.value;
                        h.dataset.harvested = '1';
                        changePassForm.appendChild(h);
                    });

                    changePassForm.submit();
                })
                .catch(() => {
                    markConfirmError('Could not reach the server. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = '<span>Set New Password</span><i class="fas fa-arrow-right"></i>';
                });
        });
    }
</script>
</body>
</html>
