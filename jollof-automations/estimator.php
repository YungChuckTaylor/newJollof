<?php
/**
 * Jollof Automations — Smart Home Budget & ROI Estimator
 * Dedicated interactive calculator pulling real package costs and property multipliers from MySQL.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Smart Home Cost Estimator & ROI Calculator · Jollof Automations';
$pageDesc  = 'Estimate the cost of automating your Lagos or Abuja residence — room-by-room budgets, inverter failover sizing and payback period in minutes.';
$noindex   = false;
$activeNav = 'estimator';

$services   = AutoRepo::getServices();
$typologies = AutoRepo::getTypologies();

require_once __DIR__ . '/includes/header.php';
?>

  <section class="hero-section" style="padding:48px 0 30px;text-align:left;">
    <div class="wrap">
      <div class="hero-badge">
        <span class="pulse-indicator"></span>
        <span>Transparent Turnkey Cost Estimation</span>
      </div>
      <h1 class="hero-title" style="max-width:850px;margin-bottom:14px;">
        Smart Home <span class="highlight">Budget &amp; Savings Calculator</span>
      </h1>
      <p class="hero-lead" style="max-width:760px;margin-left:0;">
        Estimate your turnkey hardware, installation, and commissioning investment. See how smart climate scheduling and solar/inverter load shedding slash monthly diesel generator burn.
      </p>
    </div>
  </section>

  <!-- Interactive Estimator Section -->
  <section class="estimator-section" style="padding:20px 0 80px;">
    <div class="wrap">
      <div class="estimator-box">
        <div class="estimator-step">
          <div class="step-num">STEP 1</div>
          <div class="step-title">Select Residence Typology</div>
          <p style="font-size:13px;color:var(--muted);margin:-8px 0 16px;">Property scale determines hardware density, wireless repeaters, and circuit load requirements.</p>
          <div class="property-select-grid">
            <?php foreach ($typologies as $idx => $typ): ?>
              <div class="select-pill <?= $idx === 0 ? 'active' : '' ?>" onclick="setPropType(this, '<?= ja_e($typ['slug']) ?>', <?= (float)$typ['multiplier'] ?>, '<?= ja_e($typ['name']) ?>')">
                <div class="pill-name"><?= ja_e($typ['name']) ?></div>
                <div class="pill-meta"><?= ja_e($typ['typical_bedrooms'] ?? '') ?> · <?= ja_e($typ['typical_sqm'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="estimator-step">
          <div class="step-num">STEP 2</div>
          <div class="step-title">Select Automation Disciplines</div>
          <p style="font-size:13px;color:var(--muted);margin:-8px 0 16px;">Toggle the systems you wish to integrate. All systems operate on a unified local hub.</p>
          <div class="bundles-grid">
            <?php foreach ($services as $srv): ?>
              <div class="bundle-item checked" data-cost="<?= (int)$srv['base_price_ngn'] ?>" onclick="toggleBundle(this)">
                <div class="bundle-check">✓</div>
                <div class="bundle-info">
                  <b><?= ja_e($srv['title']) ?></b>
                  <div class="bundle-sub"><?= ja_currency((float)$srv['base_price_ngn']) ?> base package · <?= ja_e($srv['category']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="calc-results-bar">
          <div>
            <div class="calc-summary-label">Estimated Turnkey Hardware &amp; Commissioning Budget</div>
            <div class="calc-total" id="calc-total-amt">₦14,300,000</div>
            <div class="calc-savings" id="calc-savings">Estimated Diesel &amp; Power Savings: ₦715,000 / month</div>
            <div style="font-size:12px;color:var(--muted);margin-top:6px;" id="calc-summary-details">
              Configured for <b id="calc-summary-prop">Luxury Apartment (2–3 Beds)</b> · <span id="calc-summary-count">6 Automation Modules Active</span>
            </div>
          </div>
          <button type="button" class="btn-corp btn-corp-primary" onclick="proceedToSurvey()">
            <span>Reserve Site Survey with This Quote →</span>
          </button>
        </div>
      </div>

      <!-- ROI & Financial FAQ -->
      <div style="margin-top:50px;display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;">
        <div style="background:#ffffff;border:1px solid var(--line);border-radius:12px;padding:28px;">
          <h3 style="font-size:16px;color:var(--forest);margin-bottom:8px;">How is ROI Calculated?</h3>
          <p style="font-size:13.5px;color:var(--muted);line-height:1.6;margin:0;">
            ROI is derived from real data across 40+ retrofitted Nigerian villas. Smart AC load-shedding and ambient occupancy cut unnecessary compressor cycles by 35–45%, directly reducing generator diesel consumption.
          </p>
        </div>

        <div style="background:#ffffff;border:1px solid var(--line);border-radius:12px;padding:28px;">
          <h3 style="font-size:16px;color:var(--forest);margin-bottom:8px;">Are There Hidden Maintenance Fees?</h3>
          <p style="font-size:13.5px;color:var(--muted);line-height:1.6;margin:0;">
            None. Because our edge controllers process automations locally on-premise without cloud servers, there are zero mandatory monthly subscriptions. You own the hardware completely.
          </p>
        </div>

        <div style="background:#ffffff;border:1px solid var(--line);border-radius:12px;padding:28px;">
          <h3 style="font-size:16px;color:var(--forest);margin-bottom:8px;">Phased Rollout Available?</h3>
          <p style="font-size:13.5px;color:var(--muted);line-height:1.6;margin:0;">
            Yes. Many clients start with Phase 1 (Biometric Access &amp; Inverter Microgrid Failover) and expand to circadian lighting and multi-room audio at a later date with zero rewiring.
          </p>
        </div>
      </div>
    </div>
  </section>

  <script>
    function proceedToSurvey() {
      const budget = document.getElementById('calc-total-amt').textContent;
      const prop = document.getElementById('calc-summary-prop').textContent;
      window.location.href = `book-survey.php?budget=${encodeURIComponent(budget)}&prop=${encodeURIComponent(prop)}`;
    }
  </script>

<?php
require_once __DIR__ . '/includes/footer.php';
