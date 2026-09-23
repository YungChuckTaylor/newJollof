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
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,500&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .brand-logo-img {
      height: 44px;
      width: auto;
      display: block;
      object-fit: contain;
    }
    @media (max-width: 768px) {
      .brand-logo-img {
        height: 36px;
      }
    }
  </style>
</head>
<body>

  <!-- Top Announcement Bar -->
  <div style="background:var(--forest);color:#fff;font-size:12px;font-weight:600;padding:8px 0;text-align:center;letter-spacing:0.02em;">
    <div class="wrap" style="display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;">
      <span>⚡ A Subsidiary of <a href="../" style="color:#ffffff;text-decoration:underline;text-underline-offset:2px"><b>Jollof Living</b></a> · Matter 1.3, KNX &amp; Apple HomeKit Certified</span>
      <span style="opacity:0.6">|</span>
      <span>Direct Engineering Desk: <a href="tel:+23418885655" style="color:var(--gold);text-decoration:none;font-weight:700">+234 1 888 5655</a></span>
    </div>
  </div>

  <!-- Main Corporate Navigation -->
  <header class="site-header">
    <div class="wrap" style="display:flex;align-items:center;justify-content:space-between;width:100%;">
      <a href="index.php" class="brand-logo" aria-label="Jollof Automations Home">
        <img src="assets/img/automations-logo.png" alt="Jollof Automations" class="brand-logo-img">
      </a>

      <nav class="nav-links">
        <a href="index.php" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>
        <a href="solutions.php" class="<?= $activeNav === 'solutions' ? 'active' : '' ?>">Solutions</a>
        <a href="lab.php" class="<?= $activeNav === 'lab' ? 'active' : '' ?>">Experience Lab</a>
        <a href="estimator.php" class="<?= $activeNav === 'estimator' ? 'active' : '' ?>">Cost Estimator</a>
        <a href="case-studies.php" class="<?= $activeNav === 'cases' ? 'active' : '' ?>">Case Studies</a>
        <a href="process.php" class="<?= $activeNav === 'process' ? 'active' : '' ?>">Our Process</a>
      </nav>

      <div class="header-actions" style="display:flex;align-items:center;gap:12px;">
        <a href="leads.php" style="font-size:12.5px;color:var(--muted);text-decoration:none;font-weight:600;padding:6px 10px;border-radius:6px;border:1px solid var(--line);" title="Back-Office Leads">Leads Desk</a>
        <a href="book-survey.php" class="btn-corp btn-corp-primary" style="padding:10px 18px;font-size:13.5px">Book Site Survey</a>
      </div>
    </div>
  </header>
