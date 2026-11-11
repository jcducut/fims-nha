<?php
session_start();
require_once(__DIR__ . "/../../_shared/auth.php");

// Same reasoning as login.php — this page's own JS harvests a fresh CSRF
// token pair via fetch() before every submit, so a stale cached copy of this
// page would keep failing no matter how many times the server-side logic
// gets fixed.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// This page is the logged-in, voluntary "change my own password" screen —
// not the forced password-reset flow (that one is embedded directly in
// login.php's own Change Password panel, shown via the FIMS_Status session
// flag set by ResetPassword.php). A user must already be signed in to reach
// this page at all.
if (!isAnyUserLoggedIn()) {
    header('Location: /');
    exit;
}

$homeUrl = $_SESSION['nha_home_url'] ?? '/MainDashboard';
$currentUsername = getLoggedInUsername();

$pwdChanged = isset($_GET['pwd']) && $_GET['pwd'] === 'changed';

// Set by models/ChangePassword.php right after a failed credential/validation
// check, instead of letting it fall through and render the framework's own
// default views/ChangePassword.php template as the POST response.
$changeError = $_GET['err'] ?? '';
$changeErrorMessage = match ($changeError) {
    'oldpwd' => 'Could not update your password. Please check your current password and try again.',
    'newpwd' => 'Could not update your password. Please choose a different new password.',
    default => '',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NHA FIMS — Change Password</title>
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

    <!-- ── RIGHT: Change Password card ── -->
    <div class="login-container">
        <div class="login-form">
            <div class="form-margin">

                <div class="form-header">
                    <div class="secure-badge">
                        <i class="fas fa-lock"></i>
                        Secure Portal
                    </div>
                    <p class="form-subtitle">
                        <?= $currentUsername
                            ? 'Update the password for @' . htmlspecialchars($currentUsername) . '.'
                            : 'Update your account password.' ?>
                    </p>
                </div>

                <div class="flash-error" id="flash-error" style="<?= $changeErrorMessage ? 'display:flex;' : 'display:none;' ?>">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="flash-error-msg"><?= htmlspecialchars($changeErrorMessage) ?></span>
                </div>

                <div class="flash-success" id="flash-success" style="<?= $pwdChanged ? 'display:flex;' : 'display:none;' ?>">
                    <i class="fas fa-check-circle"></i>
                    <span id="flash-success-msg"><?= $pwdChanged ? 'Your password has been updated.' : '' ?></span>
                </div>

                <form name="fchangepass" id="fchangepass" action="/changepassword" method="post" novalidate autocomplete="off">

                    <div class="field-group">
                        <div class="field-wrap">
                            <input class="field-input" type="password" name="opwd" id="old-password"
                                   placeholder=" " autocomplete="current-password" required>
                            <label class="field-label" for="old-password">Current Password</label>
                            <button type="button" class="toggle-password" data-toggle-for="old-password" title="Toggle password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

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
                        <span>Save New Password</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <div class="forgot-row" style="justify-content:flex-start;">
                        <a class="back-link" href="<?= htmlspecialchars($homeUrl) ?>">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>

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
    const oldPasswordInput = document.getElementById('old-password');
    const newPasswordInput = document.getElementById('new-password');
    const confirmPasswordInput = document.getElementById('confirm-password');
    const confirmPasswordGroup = document.getElementById('confirm-password-group');
    const confirmPasswordError = document.getElementById('confirm-password-error');
    const flashOk = document.getElementById('flash-success');
    const flashErr = document.getElementById('flash-error');
    const flashErrMsg = document.getElementById('flash-error-msg');

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

    let changePassSubmitting = false;
    changePassForm.addEventListener('submit', function (e) {
        e.preventDefault();

        // Guards against a second "submit" event (Enter key in a password
        // field right after clicking Save, or a double-click before the
        // button's disabled state paints) re-entering with the SAME old
        // password value. Without this, two requests can race: the first
        // actually changes the password, but if the second (now stale
        // old-password) request's "invalid old password" redirect responds
        // first, the browser lands on that error page even though the save
        // already went through.
        if (changePassSubmitting) return;
        changePassSubmitting = true;

        flashOk.style.display = 'none';
        flashErr.style.display = 'none';

        const oldPassword = oldPasswordInput.value;
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (!oldPassword || !newPassword || !confirmPassword) {
            markConfirmError('Please fill in all three fields.');
            changePassSubmitting = false;
            return;
        }
        if (newPassword !== confirmPassword) {
            markConfirmError('Passwords do not match.');
            confirmPasswordInput.focus();
            changePassSubmitting = false;
            return;
        }

        const btn = document.getElementById('btn-changepass');
        btn.disabled = true;
        btn.innerHTML = '<span>Saving…</span><i class="fas fa-spinner fa-spin"></i>';

        // /changepassword guards every POST with a per-session Slim-Csrf pair
        // that's re-generated on each request — a fetch()+FormData POST built
        // from scratch has no way to obtain a valid one, so fetch the page
        // once first to harvest a fresh pair, graft it onto this form, then do
        // a REAL submit (full navigation), same pattern as login.php. (This
        // used to fetch()+parse the re-rendered failure HTML for an error
        // message client-side, but that's fragile against markup/CSS changes
        // in the framework's own template; models/ChangePassword.php now
        // redirects back to this page with the reason on failure instead, same
        // as the /login fix.)
        fetch('/changepassword', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
                        changePassForm.appendChild(h);
                    });
                }
                changePassForm.submit();
            })
            .catch(() => {
                flashErrMsg.textContent = 'Could not reach the server. Please try again.';
                flashErr.style.display = 'flex';
                btn.disabled = false;
                btn.innerHTML = '<span>Save New Password</span><i class="fas fa-arrow-right"></i>';
                changePassSubmitting = false;
            });
    });
</script>
</body>
</html>
