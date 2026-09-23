<?php
/**
 * Jollof Automations — Solutions Catalog Page
 * In-depth technical breakdown of all 6 residential automation disciplines.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Smart Home Solutions & Engineering Pillars · Jollof Automations';
$activeNav = 'solutions';

$services = AutoRepo::getServices();

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Solutions Hero -->
  <section class="hero-section" style="padding:50px 0 40px;text-align:left;">
    <div class="wrap">
      <div class="hero-badge">
        <span class="pulse-indicator"></span>
        <span>Turnkey Residential Automation Solutions</span>
      </div>
      <h1 class="hero-title" style="max-width:850px;margin-bottom:16px;">
        Six Pillars of <span class="highlight">Architectural Intelligence</span>
      </h1>
      <p class="hero-lead" style="max-width:760px;margin-left:0;">
        From biometric hospitality access to sub-10ms microgrid failover, every system is engineered to integrate invisibly into luxury Nigerian residences with open protocol standards and zero cloud dependency.
      </p>

      <div style="display:flex;gap:12px;margin-top:28px;flex-wrap:wrap;">
        <a href="#access" class="corp-tag" style="background:#e4efe7;color:var(--green);text-decoration:none;font-weight:700">1. Access Control</a>
        <a href="#lighting" class="corp-tag" style="background:#e4efe7;color:var(--green);text-decoration:none;font-weight:700">2. Circadian Lighting</a>
        <a href="#climate" class="corp-tag" style="background:#e4efe7;color:var(--green);text-decoration:none;font-weight:700">3. Climate &amp; Air Quality</a>
        <a href="#power" class="corp-tag" style="background:#e4efe7;color:var(--green);text-decoration:none;font-weight:700">4. Microgrids &amp; Generators</a>
        <a href="#audio" class="corp-tag" style="background:#e4efe7;color:var(--green);text-decoration:none;font-weight:700">5. Multi-Room Acoustics</a>
        <a href="#security" class="corp-tag" style="background:#e4efe7;color:var(--green);text-decoration:none;font-weight:700">6. AI Perimeter Defense</a>
      </div>
    </div>
  </section>

  <!-- Detailed Solutions Directory -->
  <section class="solutions-section" style="padding:40px 0 80px;">
    <div class="wrap">
      <?php foreach ($services as $idx => $srv): 
        $anchor = match($idx) {
          0 => 'access',
          1 => 'lighting',
          2 => 'climate',
          3 => 'power',
          4 => 'audio',
          default => 'security'
        };
      ?>
        <div id="<?= $anchor ?>" style="background:#ffffff;border:1px solid var(--line);border-radius:16px;padding:36px;margin-bottom:40px;box-shadow:0 6px 24px rgba(0,0,0,0.03);">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;border-bottom:1px solid var(--line);padding-bottom:20px;margin-bottom:24px;">
            <div>
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                <span style="font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:800;color:var(--green)">0<?= $idx + 1 ?> · <?= ja_e($srv['category']) ?></span>
                <?php if (!empty($srv['badge'])): ?>
                  <span class="solution-badge"><?= ja_e($srv['badge']) ?></span>
                <?php endif; ?>
              </div>
              <h2 style="font-size:26px;font-weight:800;color:var(--forest);margin:0;"><?= ja_e($srv['title']) ?></h2>
              <div style="font-size:14px;color:var(--muted);margin-top:4px;"><?= ja_e($srv['subtitle'] ?? '') ?></div>
            </div>

            <div style="text-align:right;">
              <div style="font-size:12px;color:var(--muted)">Base Package Starting at:</div>
              <div style="font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:800;color:var(--forest);"><?= ja_currency((float)$srv['base_price_ngn']) ?></div>
            </div>
          </div>

          <p style="font-size:15px;color:var(--ink);line-height:1.7;margin-bottom:24px;">
            <?= ja_e($srv['short_desc']) ?>
          </p>

          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-bottom:28px;">
            <div style="background:var(--paper);border-radius:10px;padding:20px;border:1px solid var(--line);">
              <h4 style="font-size:14px;font-weight:700;color:var(--forest);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.04em;">Key Capabilities</h4>
              <ul class="solution-features">
                <?php foreach (($srv['features'] ?? []) as $feat): ?>
                  <li>
                    <span class="check-bullet">✓</span>
                    <span><?= ja_e($feat) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <div style="background:var(--paper);border-radius:10px;padding:20px;border:1px solid var(--line);">
              <h4 style="font-size:14px;font-weight:700;color:var(--forest);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.04em;">Engineering Specifications</h4>
              <ul style="list-style:none;padding:0;margin:0;font-size:13.5px;color:var(--ink);display:flex;flex-direction:column;gap:10px;">
                <li style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--line);padding-bottom:6px;">
                  <span style="color:var(--muted)">Protocol Standard:</span>
                  <b>Matter 1.3 / KNX / Zigbee 3.0</b>
                </li>
                <li style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--line);padding-bottom:6px;">
                  <span style="color:var(--muted)">Local Edge Processing:</span>
                  <b>100% On-Premise Gateway</b>
                </li>
                <li style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--line);padding-bottom:6px;">
                  <span style="color:var(--muted)">Installation Rigor:</span>
                  <b>Zero-Wall-Damage Retrofit</b>
                </li>
                <li style="display:flex;justify-content:space-between;">
                  <span style="color:var(--muted)">Warranty &amp; Concierge SLA:</span>
                  <b>3 Years Hardware + 24/7 SLA</b>
                </li>
              </ul>
            </div>
          </div>

          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;padding-top:16px;border-top:1px solid var(--line);">
            <div style="font-size:13px;color:var(--muted)">
              Designed for luxury residences in Lagos, Abuja, and Port Harcourt.
            </div>
            <div style="display:flex;gap:12px;">
              <a href="estimator.php" class="btn-corp btn-corp-outline" style="padding:10px 18px;font-size:13px">Estimate Cost</a>
              <a href="book-survey.php?module=<?= urlencode($srv['title']) ?>" class="btn-corp btn-corp-primary" style="padding:10px 18px;font-size:13px">Book Survey for This Solution →</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

<?php
require_once __DIR__ . '/includes/footer.php';
