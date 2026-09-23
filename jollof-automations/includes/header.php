<?php
/**
 * Jollof Automations — Site Header Template
 */
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Turn Your Residence Into an Intelligent Smart Home · Jollof Automations';
$activeNav = $activeNav ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= ja_e($pageTitle) ?></title>
  <meta name="description" content="A subsidiary of Jollof Living. We convert architectural residences, penthouses and luxury homes into intelligent smart homes with biometric access, autonomous climate, and circadian lighting.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,500&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <!-- Top Announcement Bar -->
  <div style="background:var(--forest);color:#fff;font-size:12px;font-weight:600;padding:8px 0;text-align:center;letter-spacing:0.02em;">
    <div class="wrap">
      ⚡ A Subsidiary of <a href="../" style="color:#ffffff;text-decoration:underline;text-underline-offset:2px"><b>Jollof Living</b></a> · Matter 1.3, KNX &amp; Apple HomeKit Certified Smart Home Engineering
    </div>
  </div>

  <!-- Main Corporate Navigation -->
  <header class="site-header">
    <div class="wrap" style="display:flex;align-items:center;justify-content:space-between;width:100%;">
      <a href="index.php" class="brand-logo" aria-label="Jollof Automations Home">
        <div class="brand-icon">⚡</div>
        <div class="brand-text">
          <span class="brand-title">JOLLOF AUTOMATIONS</span>
          <span class="brand-sub">Smart Homes · A Jollof Living Company</span>
        </div>
      </a>

      <nav class="nav-links">
        <a href="index.php" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>
        <a href="index.php#solutions" class="<?= $activeNav === 'solutions' ? 'active' : '' ?>">Solutions</a>
        <a href="index.php#simulator" class="<?= $activeNav === 'simulator' ? 'active' : '' ?>">Experience Lab</a>
        <a href="index.php#configurator" class="<?= $activeNav === 'estimator' ? 'active' : '' ?>">Cost Estimator</a>
        <a href="index.php#cases" class="<?= $activeNav === 'cases' ? 'active' : '' ?>">Case Studies</a>
        <a href="leads.php" class="<?= $activeNav === 'leads' ? 'active' : '' ?>" style="font-size:13px;opacity:0.85">Admin Leads</a>
      </nav>

      <div class="header-actions">
        <a href="index.php#survey" class="btn-corp btn-corp-primary" style="padding:10px 18px;font-size:13.5px">Book Site Survey</a>
      </div>
    </div>
  </header>
