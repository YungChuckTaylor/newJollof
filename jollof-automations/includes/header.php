<?php
/**
 * Jollof Automations — Site Header Template
 *
 * SEO notes:
 *  - canonical URLs live under /automations/ (see root .htaccess rewrite)
 *  - $pageTitle / $pageDesc / $ogImage may be set per page before including
 *  - structured data: HomeAndConstructionBusiness + WebSite (JSON-LD)
 */
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Turn Your Residence Into an Intelligent Smart Home · Jollof Automations';
$pageDesc  = $pageDesc  ?? 'A subsidiary of Jollof Living. We convert architectural residences, penthouses and luxury homes into intelligent smart homes with biometric access, autonomous climate, and circadian lighting.';
$activeNav = $activeNav ?? 'home';
$noindex   = $noindex   ?? false;

if (strlen($pageDesc) > 158) {
    $pageDesc = rtrim(substr($pageDesc, 0, 155)) . '…';
}

/* ---- canonical base: inherit the parent site URL when configured ------- */
$jaConfig     = function_exists('ja_config') ? (array) ja_config() : [];
$jaParentConf = $jaConfig;
if (defined('JL_ROOT') && is_file(JL_ROOT . '/includes/config.php')) {
    try {
        $jaParentConf = array_merge($jaConfig, (array) require JL_ROOT . '/includes/config.php');
    } catch (\Throwable $e) {
        /* fall through with the local config */
    }
}
$jaSiteUrl = trim((string) ($jaParentConf['site']['url'] ?? ''));
if ($jaSiteUrl === '') {
    $jaScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $jaSiteUrl = $jaScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'www.jollofliving.com');
}
$jaSiteUrl  = rtrim($jaSiteUrl, '/');
$jaScript   = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$jaSelfPath = '/automations/' . ($jaScript === 'index.php' ? '' : $jaScript);
$jaCanonical = $jaSiteUrl . $jaSelfPath;

$jaPageName = trim(explode('·', $pageTitle)[0]);
$jaEmail    = (string) ($jaConfig['site']['email'] ?? 'automations@jollofliving.com');
$jaPhone    = (string) ($jaConfig['site']['phone'] ?? '+234 1 888 5655');

$jaJsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'HomeAndConstructionBusiness',
    '@id'         => $jaSiteUrl . '/automations/#business',
    'name'        => 'Jollof Automations',
    'description' => $pageDesc,
    'url'         => $jaCanonical,
    'email'       => $jaEmail,
    'telephone'   => $jaPhone,
    'image'       => $jaSiteUrl . '/automations/assets/img/automations-logo-light.png',
    'areaServed'  => ['@type' => 'Country', 'name' => 'Nigeria'],
    'address'     => [
        '@type'           => 'PostalAddress',
        'addressLocality' => 'Victoria Island',
        'addressRegion'   => 'Lagos',
        'addressCountry'  => 'NG',
    ],
    'parentOrganization' => [
        '@type' => 'Organization',
        'name'  => 'Jollof Living',
        'url'   => $jaSiteUrl . '/',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= ja_e($pageTitle) ?></title>
  <meta name="description" content="<?= ja_e($pageDesc) ?>">
  <link rel="canonical" href="<?= ja_e($jaCanonical) ?>">
<?php if ($noindex): ?>
  <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
  <meta property="og:site_name" content="Jollof Automations">
  <meta property="og:locale" content="en_NG">
  <meta property="og:title" content="<?= ja_e($pageTitle) ?>">
  <meta property="og:description" content="<?= ja_e($pageDesc) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= ja_e($jaCanonical) ?>">
  <meta property="og:image" content="<?= ja_e($jaSiteUrl . '/automations/assets/img/automations-logo-light.png') ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= ja_e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= ja_e($pageDesc) ?>">
  <meta name="twitter:image" content="<?= ja_e($jaSiteUrl . '/automations/assets/img/automations-logo-light.png') ?>">
  <script type="application/ld+json"><?= json_encode($jaJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <!-- Cormorant Garamond is self-hosted (shared with the main site); Jakarta +
       Space Grotesk load from Google with preconnect + display=swap -->
  <link rel="preload" as="font" type="font/woff2" href="assets/fonts/cg-normal.woff2" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,500&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    @font-face{font-family:'Cormorant Garamond';font-style:normal;font-weight:400 700;font-display:swap;
      src:url('assets/fonts/cg-normal.woff2') format('woff2')}
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
  <link rel="stylesheet" href="assets/css/style.css">
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
    <div class="wrap nav-container">
      <a href="index.php" class="brand-logo" aria-label="Jollof Automations Home">
        <img src="assets/img/automations-logo.png" alt="Jollof Automations" class="brand-logo-img" style="height:36px;width:auto;max-width:180px;object-fit:contain;display:block;">
      </a>

      <nav class="nav-links">
        <a href="index.php" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>
        <a href="solutions.php" class="<?= $activeNav === 'solutions' ? 'active' : '' ?>">Solutions</a>
        <a href="lab.php" class="<?= $activeNav === 'lab' ? 'active' : '' ?>">Experience Lab</a>
        <a href="estimator.php" class="<?= $activeNav === 'estimator' ? 'active' : '' ?>">Cost Estimator</a>
        <a href="case-studies.php" class="<?= $activeNav === 'cases' ? 'active' : '' ?>">Case Studies</a>
        <a href="process.php" class="<?= $activeNav === 'process' ? 'active' : '' ?>">Process</a>
      </nav>

      <div class="header-actions">
        <a href="leads.php" style="font-size:12px;color:var(--muted);text-decoration:none;font-weight:600;padding:6px 10px;border-radius:6px;border:1px solid var(--line);white-space:nowrap;" title="Back-Office Leads">Leads Desk</a>
        <a href="book-survey.php" class="btn-corp btn-corp-primary" style="padding:9px 18px;font-size:13px;white-space:nowrap;">Book Site Survey</a>
      </div>
    </div>
  </header>
