<?php
/**
 * Jollof Living — Email Verification Landing Page (WP12)
 *
 * Verifies email confirmation tokens, grants verified badges,
 * and handles link expiry/resends.
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require JL_INC . '/view.php';

$token = trim((string) ($_GET['token'] ?? ''));
$result = null;
$verifiedUser = null;

if ($token !== '') {
    $result = VerifyService::verifyEmailToken($token);
    if ($result['ok'] && !empty($result['user'])) {
        $verifiedUser = $result['user'];
        // Auto sign-in if not currently signed in or signed in as someone else
        if (!Auth::check() || Auth::id() !== (int) $verifiedUser['id']) {
            Auth::login((int) $verifiedUser['id'], ($verifiedUser['role'] ?? '') === 'admin', $verifiedUser);
        }
    }
}

$siteName = config('site.name', 'Jollof Living');
$siteUrl  = config('site.url', '');
$accountUrl = url('account.php');
$staysUrl   = url('stays.php');

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email Verification · <?= e($siteName) ?></title>
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
    .verify-shell {
      max-width: 520px;
      width: 100%;
      background: #161c12;
      border: 1px solid rgba(233,198,103,0.25);
      border-radius: 16px;
      padding: 40px 32px;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0,0,0,0.45);
    }
    .brand-mark {
      font-family: Georgia, serif;
      font-size: 26px;
      color: #e9c667;
      letter-spacing: .02em;
      margin-bottom: 24px;
    }
    .icon-badge {
      width: 68px;
      height: 68px;
      margin: 0 auto 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
    }
    .icon-success {
      background: rgba(46, 204, 113, 0.15);
      border: 2px solid #2ecc71;
      color: #2ecc71;
    }
    .icon-fail {
      background: rgba(231, 76, 60, 0.15);
      border: 2px solid #e74c3c;
      color: #e74c3c;
    }
    .icon-pending {
      background: rgba(233, 198, 103, 0.15);
      border: 2px solid #e9c667;
      color: #e9c667;
    }
    h1 {
      font-family: Georgia, serif;
      font-size: 26px;
      margin: 0 0 12px;
      color: #ffffff;
      font-weight: 500;
    }
    p {
      color: #b5b0a4;
      font-size: 15px;
      line-height: 1.6;
      margin: 0 0 24px;
    }
    .btn-gold {
      display: inline-block;
      background: #e9c667;
      color: #12180f;
      padding: 13px 26px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 700;
      font-size: 15px;
      transition: background .15s ease;
      border: none;
      cursor: pointer;
    }
    .btn-gold:hover {
      background: #d8b24e;
    }
    .btn-ghost {
      display: inline-block;
      background: transparent;
      color: #e9c667;
      border: 1px solid rgba(233,198,103,0.3);
      padding: 12px 24px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 14px;
      margin-left: 10px;
      transition: border-color .15s ease;
    }
    .btn-ghost:hover {
      border-color: #e9c667;
    }
    .resend-box {
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid rgba(255,255,255,0.08);
      text-align: left;
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
      margin-bottom: 12px;
    }
    .footer-note {
      margin-top: 28px;
      font-size: 12px;
      color: #6b665c;
    }
  </style>
</head>
<body>

  <div class="verify-shell">
    <div class="brand-mark"><?= e($siteName) ?></div>

    <?php if ($result && $result['ok']): ?>
      <div class="icon-badge icon-success">✓</div>
      <h1>Email Verified ✨</h1>
      <p>Thank you<?= $verifiedUser ? ', <b>' . e((string) $verifiedUser['name']) . '</b>' : '' ?>. Your email address has been successfully verified. Your account is now fully active with escrow-protected booking privileges.</p>
      
      <div>
        <a href="<?= e($accountUrl) ?>" class="btn-gold">Go to your Account</a>
        <a href="<?= e($staysUrl) ?>" class="btn-ghost">Browse Residences</a>
      </div>

    <?php elseif ($result && !$result['ok']): ?>
      <div class="icon-badge icon-fail">✕</div>
      <h1>Verification Link Invalid</h1>
      <p><?= e($result['message']) ?></p>

      <div class="resend-box">
        <p style="font-size:13px;margin-bottom:12px">Need a new verification link? Enter your email address below:</p>
        <form id="resendForm" onsubmit="handleResend(event)">
          <input type="email" id="resendEmail" class="inp-field" placeholder="you@example.com" required value="<?= e(Auth::user()['email'] ?? '') ?>">
          <button type="submit" id="resendBtn" class="btn-gold" style="width:100%">Send New Verification Link</button>
        </form>
        <div id="resendStatus" style="font-size:13px;margin-top:10px;text-align:center"></div>
      </div>

    <?php else: ?>
      <div class="icon-badge icon-pending">✉</div>
      <h1>Verify Your Email</h1>
      <p>Enter your 6-digit confirmation code or request a fresh verification link below.</p>

      <div class="resend-box" style="border-top:none;padding-top:0">
        <form id="codeForm" onsubmit="handleCode(event)" style="margin-bottom:20px">
          <input type="text" id="verifCode" class="inp-field" placeholder="Enter 6-digit code (e.g. 123456)" maxlength="6" inputmode="numeric" required style="text-align:center;font-size:18px;letter-spacing:4px">
          <button type="submit" id="codeBtn" class="btn-gold" style="width:100%">Verify Code</button>
        </form>
        <div id="codeStatus" style="font-size:13px;margin-bottom:14px;text-align:center"></div>

        <form id="resendForm" onsubmit="handleResend(event)">
          <input type="email" id="resendEmail" class="inp-field" placeholder="you@example.com" required value="<?= e(Auth::user()['email'] ?? '') ?>">
          <button type="submit" id="resendBtn" class="btn-ghost" style="width:100%;margin-left:0">Send Link to Email</button>
        </form>
        <div id="resendStatus" style="font-size:13px;margin-top:10px;text-align:center"></div>
      </div>
    <?php endif; ?>

    <div class="footer-note">
      <?= e($siteName) ?> · Luxury Living, African Soul
    </div>
  </div>

  <script>
    async function handleResend(e) {
      e.preventDefault();
      const btn = document.getElementById('resendBtn');
      const status = document.getElementById('resendStatus');
      const email = document.getElementById('resendEmail').value.trim();

      btn.disabled = true;
      btn.textContent = 'Sending…';
      status.style.color = '#e9c667';
      status.textContent = 'Contacting server…';

      try {
        const res = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'resend_verification', email: email })
        });
        const data = await res.json();
        if (data.ok) {
          status.style.color = '#2ecc71';
          status.textContent = data.message || 'Verification link sent! Please check your inbox and spam folder.';
          btn.textContent = 'Link Sent';
        } else {
          status.style.color = '#e74c3c';
          status.textContent = data.message || 'Could not send verification email.';
          btn.disabled = false;
          btn.textContent = 'Send New Verification Link';
        }
      } catch (err) {
        status.style.color = '#e74c3c';
        status.textContent = 'Network error. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Send New Verification Link';
      }
    }

    async function handleCode(e) {
      e.preventDefault();
      const btn = document.getElementById('codeBtn');
      const status = document.getElementById('codeStatus');
      const code = document.getElementById('verifCode').value.trim();

      btn.disabled = true;
      btn.textContent = 'Verifying…';
      status.style.color = '#e9c667';
      status.textContent = 'Validating code…';

      try {
        const res = await fetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'verify_email', code: code })
        });
        const data = await res.json();
        if (data.ok) {
          status.style.color = '#2ecc71';
          status.textContent = 'Success! Redirecting to your account…';
          setTimeout(() => { window.location.href = 'account.php'; }, 1000);
        } else {
          status.style.color = '#e74c3c';
          status.textContent = data.message || 'Invalid code.';
          btn.disabled = false;
          btn.textContent = 'Verify Code';
        }
      } catch (err) {
        status.style.color = '#e74c3c';
        status.textContent = 'Network error. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Verify Code';
      }
    }
  </script>
</body>
</html>
