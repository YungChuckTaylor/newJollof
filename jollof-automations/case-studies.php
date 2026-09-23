<?php
/**
 * Jollof Automations — Case Studies & Client Transformations
 * Detailed project breakdowns from prominent Nigerian residences and portfolios.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Case Studies & Residential Transformations · Jollof Automations';
$activeNav = 'cases';

$caseStudies = AutoRepo::getCaseStudies();

require_once __DIR__ . '/includes/header.php';
?>

  <section class="hero-section" style="padding:48px 0 30px;text-align:left;">
    <div class="wrap">
      <div class="hero-badge">
        <span class="pulse-indicator"></span>
        <span>Proven Residential Engineering</span>
      </div>
      <h1 class="hero-title" style="max-width:850px;margin-bottom:14px;">
        Luxury Residential <span class="highlight">Transformations</span>
      </h1>
      <p class="hero-lead" style="max-width:760px;margin-left:0;">
        Explore how our certified systems engineers retrofitted luxury penthouses, diplomatic compounds, and high-yield hospitality portfolios across Nigeria.
      </p>
    </div>
  </section>

  <!-- Detailed Cases Section -->
  <section class="cases-section" style="padding:20px 0 80px;">
    <div class="wrap">
      <div style="display:flex;flex-direction:column;gap:36px;">
        <?php foreach ($caseStudies as $idx => $cs): ?>
          <div style="background:#ffffff;border:1px solid var(--line);border-radius:16px;padding:36px;box-shadow:0 6px 20px rgba(0,0,0,0.03);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;border-bottom:1px solid var(--line);padding-bottom:18px;margin-bottom:20px;">
              <div>
                <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);margin-bottom:6px;">
                  <span style="font-weight:700;color:var(--forest)"><?= ja_e($cs['location']) ?></span>
                  <span>·</span>
                  <span><?= ja_e($cs['property_type']) ?></span>
                  <span>·</span>
                  <span class="corp-tag" style="background:#e4efe7;color:var(--green);font-size:11px;padding:3px 8px;"><?= ja_e($cs['client_type']) ?></span>
                </div>
                <h2 style="font-size:24px;font-weight:800;color:var(--forest);margin:0;"><?= ja_e($cs['title']) ?></h2>
              </div>

              <div class="case-highlight" style="font-size:14px;padding:6px 14px;background:#fdf4db;color:#a87818;border-radius:20px;font-weight:700;">
                ★ <?= ja_e($cs['highlight_metric']) ?>
              </div>
            </div>

            <p style="font-size:15px;color:var(--ink);line-height:1.7;margin-bottom:24px;">
              <?= ja_e($cs['narrative']) ?>
            </p>

            <div style="margin-bottom:24px;">
              <h4 style="font-size:13px;color:var(--muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;">Integrated Sub-Systems:</h4>
              <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach (($cs['systems'] ?? []) as $sys): ?>
                  <span style="font-size:12px;background:var(--paper);border:1px solid var(--line);padding:6px 12px;border-radius:6px;color:var(--forest);font-weight:600;">
                    ✓ <?= ja_e($sys) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            </div>

            <?php if (!empty($cs['testimonial_quote'])): ?>
              <div class="quote-box" style="margin:0;">
                <div class="quote-text">“<?= ja_e($cs['testimonial_quote']) ?>”</div>
                <div class="quote-author"><?= ja_e($cs['client_name']) ?> — <span style="font-weight:400;color:var(--muted)"><?= ja_e($cs['client_role']) ?></span></div>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:50px;text-align:center;background:var(--forest);color:#ffffff;border-radius:16px;padding:48px 24px;">
        <h2 style="font-size:26px;font-weight:800;margin:0 0 12px;">Ready for Your Residence Transformation?</h2>
        <p style="font-size:15px;color:rgba(255,255,255,0.8);max-width:600px;margin:0 auto 24px;">
          Our engineers conduct physical site audits across Lagos, Abuja, and Port Harcourt. Receive an engineered bill of materials with zero obligation.
        </p>
        <a href="book-survey.php" class="btn-corp btn-corp-primary" style="background:#ffffff;color:var(--forest);font-weight:700">
          Book Complimentary On-Site Survey →
        </a>
      </div>
    </div>
  </section>

<?php
require_once __DIR__ . '/includes/footer.php';
