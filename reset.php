<?php
/**
 * Jollof Living — Password Recovery & Reset (WP12 / F16)
 *
 * Handles password reset token validation, password updating,
 * and session invalidation.
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require JL_INC . '/view.php';

$token = trim((string) ($_GET['token'] ?? ''));
$siteName = config('site.name', 'Jollof Living');
$siteUrl  = config('site.url', '');

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password · <?= e($siteName) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body {
      background: #0d120a;
      color: #ede8dc;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      margin: 0;
    }
    .reset-shell {
      max-width: 480px;
      width: 100%;
      background: #161c12;
      border: 1px solid rgba(233,198,103,0.25);
      border-radius: 16px;
      padding: 40px 32px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.45);
    }
    .brand-mark {
      font-family: Georgia, serif;
      font-size: 26px;
      color: #e9c667;
      letter-spacing: .02em;
      text-align: center;
      margin-bottom: 24px;
    }
    h1 {
      font-family: Georgia, serif;
      font-size: 24px;
      margin: 0 0 10px;
      color: #ffffff;
      text-align: center;
      font-weight: 500;
    }
    p.lead {
      color: #b5b0a4;
      font-size: 14px;
      line-height: 1.5;
      text-align: center;
      margin: 0 0 24px;
    }
    .frm-group {
      margin-bottom: 18px;
    }
    label {
      display: block;
      font-size: 13px;
      color: #c9c4b5;
      margin-bottom: 6px;
      font-weight: 500;
    }
    .inp-field {
      width: 100%;
      box-sizing: border-box;
      padding: 12px 14px;
      background: #0d120a;
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 8px;
      color: #ffffff;
      font-size: 14px;
      outline: none;
      transition: border-color .15s ease;
    }
    .inp-field:focus {
      border-color: #e9c667;
    }
    .btn-gold {
      display: block;
      width: 100%;
      background: #e9c667;
      color: #12180f;
      padding: 13px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 700;
      font-size: 15px;
      border: none;
      cursor: pointer;
      margin-top: 10px;
      transition: background .15s ease;
      text-align: center;
    }
    .btn-gold:hover {
      background: #d8b24e;
    }
    .btn-gold:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .status-msg {
      font-size: 13.5px;
      margin-top: 14px;
      text-align: center;
      padding: 10px;
      border-radius: 6px;
      display: none;
    }
    .status-error {
      background: rgba(231,76,60,0.15);
      border: 1px solid rgba(231,76,60,0.3);
      color: #e74c3c;
      display: block;
    }
    .status-ok {
      background: rgba(46,204,113,0.15);
      border: 1px solid rgba(46,204,113,0.3);
      color: #2ecc71;
      display: block;
    }
    .back-link {
      display: block;
      text-align: center;
      font-size: 13px;
      color: #a5851f;
      text-decoration: none;
      margin-top: 20px;
    }
    .back-link:hover {
      text-decoration: underline;
    }
    .footer-note {
      text-align: center;
      margin-top: 28px;
      font-size: 12px;
      color: #6b665c;
    }
  </style>
</head>
<body>

  <div class="reset-shell">
    <div class="brand-mark"><?= e($siteName) ?></div>

    <?php if ($token !== ''): ?>
      <h1>Set New Password</h1>
      <p class="lead">Please enter a new password for your account (at least 8 characters).</p>

      <form id="newPassForm" onsubmit="handleReset(event)">
        <input type="hidden" id="resetToken" value="<?= e($token) ?>">

        <div class="frm-group">
          <label for="newPass">New Password</label>
          <input type="password" id="newPass" class="inp-field" placeholder="At least 8 characters" minlength="8" required autofocus>
        </div>

        <div class="frm-group">
          <label for="confirmPass">Confirm New Password</label>
          <input type="password" id="confirmPass" class="inp-field" placeholder="Repeat your password" minlength="8" required>
        </div>

        <button type="submit" id="submitBtn" class="btn-gold">Update Password</button>
        <div id="statusMsg" class="status-msg"></div>
      </form>

    <?php else: ?>
      <h1>Forgot Password</h1>
      <p class="lead">Enter your email address and we'll send you a secure recovery link.</p>

      <form id="forgotForm" onsubmit="handleForgot(event)">
        <div class="frm-group">
          <label for="userEmail">Account Email</label>
          <input type="email" id="userEmail" class="inp-field" placeholder="you@example.com" required autofocus>
        </div>

        <button type="submit" id="submitBtn" class="btn-gold">Send Recovery Link</button>
        <div id="statusMsg" class="status-msg"></div>
      </form>
    <?php endif; ?>

    <a href="auth.php" class="back-link">Return to Sign In</a>
    <div class="footer-note">
      <?= e($siteName) ?> · Luxury Living, African Soul
    </div>
  </div>

  <script>
    async function handleForgot(e) {
      e.preventDefault();
      const btn = document.getElementById('submitBtn');
      const msg = document.getElementById('statusMsg');
      const email = document.getElementById('userEmail').value.trim();

      btn.disabled = true;
      btn.textContent = 'Sending…';
      msg.className = 'status-msg';
      msg.style.display = 'none';

      try {
        const res = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'forgot_password', email: email })
        });
        const data = await res.json();
        if (data.ok) {
          msg.className = 'status-msg status-ok';
          msg.textContent = data.message || 'Recovery email sent! Please check your inbox and spam folder.';
          btn.textContent = 'Link Sent';
        } else {
          msg.className = 'status-msg status-error';
          msg.textContent = data.message || 'Could not send recovery link.';
          btn.disabled = false;
          btn.textContent = 'Send Recovery Link';
        }
      } catch (err) {
        msg.className = 'status-msg status-error';
        msg.textContent = 'Network error. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Send Recovery Link';
      }
    }

    async function handleReset(e) {
      e.preventDefault();
      const btn = document.getElementById('submitBtn');
      const msg = document.getElementById('statusMsg');
      const token = document.getElementById('resetToken').value;
      const pass = document.getElementById('newPass').value;
      const confirm = document.getElementById('confirmPass').value;

      if (pass !== confirm) {
        msg.className = 'status-msg status-error';
        msg.textContent = 'Passwords do not match.';
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Updating…';
      msg.className = 'status-msg';
      msg.style.display = 'none';

      try {
        const res = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'reset_password', token: token, password: pass })
        });
        const data = await res.json();
        if (data.ok) {
          msg.className = 'status-msg status-ok';
          msg.textContent = 'Password updated successfully! Redirecting…';
          btn.textContent = 'Success!';
          setTimeout(() => {
            window.location.href = (data.data && data.data.redirect) || 'account.php';
          }, 1200);
        } else {
          msg.className = 'status-msg status-error';
          msg.textContent = data.message || 'Could not reset password.';
          btn.disabled = false;
          btn.textContent = 'Update Password';
        }
      } catch (err) {
        msg.className = 'status-msg status-error';
        msg.textContent = 'Network error. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Update Password';
      }
    }
  </script>
</body>
</html>
