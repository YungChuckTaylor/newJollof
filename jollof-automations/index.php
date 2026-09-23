<?php
/**
 * Jollof Automations — Dynamic PHP/MySQL Homepage
 * A Subsidiary of Jollof Living Limited.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Jollof Automations · Turn Your Home Into an Intelligent Smart Home';
$activeNav = 'home';

// Fetch dynamic data from MySQL database (with graceful schema defaults)
$services    = AutoRepo::getServices();
$scenes      = AutoRepo::getSimulatorScenes();
$typologies  = AutoRepo::getTypologies();
$caseStudies = AutoRepo::getCaseStudies();

// First scene as default
$defaultSceneKey = array_key_first($scenes) ?? 'welcome';
$defaultScene = $scenes[$defaultSceneKey] ?? [
    'title' => 'The Grand Salon · Ikoyi Penthouse',
    'desc' => 'Biometric access recognized. Keyway locked. Motorized sheer drapes parted for skyline view. Dual inverter air conditioning maintains 21°C.',
    'tag' => 'ARRIVE HOME SCENE ACTIVE',
    'power' => '840 Watts',
    'temp' => '21.0 °C',
    'kelvin' => '2700 K',
    'security' => 'ARMED (STAY)',
    'glow' => 'radial-gradient(circle at 60% 40%, rgba(244, 236, 220, 0.45), transparent 70%)',
    'devices' => ['lock' => true, 'light' => true, 'climate' => true, 'shades' => true]
];

require_once __DIR__ . '/includes/header.php';
?>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="wrap">
      <div class="hero-badge">
        <span class="pulse-indicator"></span>
        <span>A Subsidiary of Jollof Living · Certified Architectural Intelligence</span>
      </div>

      <h1 class="hero-title">
        Turn Your Residence Into an <span class="highlight">Intelligent Smart Home</span>
      </h1>

      <p class="hero-lead">
        We convert luxury private residences, penthouses, and short-let properties across Nigeria into autonomous, energy-resilient environments with biometric access, circadian lighting, and smart inverter failover.
      </p>

      <div class="hero-cta-group">
        <a href="book-survey.php" class="btn-corp btn-corp-primary">
          <span>Book an On-Site Survey</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
        <a href="estimator.php" class="btn-corp btn-corp-outline">
          <span>Explore Cost Estimator</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </a>
      </div>

      <div class="hero-stats">
        <div class="stat-card">
          <div class="stat-value">100%</div>
          <div class="stat-label">Local Edge Reliability (No Cloud Outages)</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">&lt; 10ms</div>
          <div class="stat-label">Inverter &amp; Microgrid Switchover Protection</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">38–45%</div>
          <div class="stat-label">Reduction in Diesel &amp; Generator Running Costs</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">KNX &amp; Matter</div>
          <div class="stat-label">Certified Open Standards (Zero Vendor Lock-In)</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Interactive Experience Lab (Digital Twin Room Simulator) -->
  <section class="simulator-section" id="lab">
    <div class="wrap">
      <div class="section-head">
        <div class="eyebrow">Interactive Digital Twin Simulator</div>
        <h2 class="section-title">Experience Autonomous Living in Real Time</h2>
        <p class="section-sub">
          Test real residential scenes engineered for high-end Nigerian homes. Switch between custom ambiance profiles to preview real-time power modulation and sensory automation.
        </p>
      </div>

      <div class="sim-grid">
        <!-- Interactive Controls Column -->
        <div class="sim-controls">
          <div class="sim-controls-header">
            <h3>Automated Scene Profiles</h3>
            <span class="corp-tag" style="background:#e4efe7;color:var(--green)">Database Presets</span>
          </div>

          <div class="scene-buttons-list">
            <?php foreach ($scenes as $key => $sc): ?>
              <button type="button" class="scene-btn <?= $key === $defaultSceneKey ? 'active' : '' ?>" onclick="activateScene('<?= ja_e($key) ?>')">
                <div class="scene-icon">
                  <?php if ($key === 'welcome'): ?>✨
                  <?php elseif ($key === 'cinema'): ?>🎬
                  <?php elseif ($key === 'power'): ?>⚡
                  <?php else: ?>🛎️<?php endif; ?>
                </div>
                <div class="scene-text">
                  <div class="scene-name"><?= ja_e($sc['name'] ?? $sc['title']) ?></div>
                  <div class="scene-desc"><?= ja_e($sc['tag'] ?? 'Interactive Scene') ?></div>
                </div>
              </button>
            <?php endforeach; ?>
          </div>

          <div style="margin-top:20px;border-top:1px solid var(--line);padding-top:16px;">
            <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:12px;">Individual Hardware Overrides</div>
            <div class="device-switches">
              <div class="device-item">
                <div>
                  <div style="font-size:13px;font-weight:700;color:var(--forest)">Biometric Deadbolt</div>
                  <div style="font-size:11px;color:var(--muted)" id="st-lock">Locked</div>
                </div>
                <div class="switch-toggle on" id="sw-lock" onclick="toggleDevice('lock')"></div>
              </div>

              <div class="device-item">
                <div>
                  <div style="font-size:13px;font-weight:700;color:var(--forest)">Circadian Downlights</div>
                  <div style="font-size:11px;color:var(--muted)" id="st-light">Active (2700K)</div>
                </div>
                <div class="switch-toggle on" id="sw-light" onclick="toggleDevice('light')"></div>
              </div>

              <div class="device-item">
                <div>
                  <div style="font-size:13px;font-weight:700;color:var(--forest)">Inverter Climate AC</div>
                  <div style="font-size:11px;color:var(--muted)" id="st-climate">Cooling 21°C</div>
                </div>
                <div class="switch-toggle on" id="sw-climate" onclick="toggleDevice('climate')"></div>
              </div>

              <div class="device-item">
                <div>
                  <div style="font-size:13px;font-weight:700;color:var(--forest)">Motorized Sheer Shades</div>
                  <div style="font-size:11px;color:var(--muted)" id="st-shades">Open (Daylight)</div>
                </div>
                <div class="switch-toggle on" id="sw-shades" onclick="toggleDevice('shades')"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Virtual Room Screen Column -->
        <div class="room-screen">
          <div class="room-glow" id="sim-glow" style="background:<?= ja_e($defaultScene['glow']) ?>;"></div>

          <div class="room-topbar">
            <span class="room-mode-tag" id="sim-mode-name"><?= ja_e($defaultScene['tag']) ?></span>
            <div class="power-meter">
              <span class="meter-dot"></span>
              <span id="sim-power-val"><?= ja_e($defaultScene['power']) ?></span>
            </div>
          </div>

          <div class="room-center">
            <h3 class="room-title" id="sim-title"><?= ja_e($defaultScene['title']) ?></h3>
            <p class="room-summary" id="sim-desc"><?= ja_e($defaultScene['desc']) ?></p>
          </div>

          <div class="room-telemetry">
            <div class="gauge-item">
              <div class="gauge-lbl">Indoor Climate</div>
              <div class="gauge-val" id="sim-gauge-temp"><?= ja_e($defaultScene['temp']) ?></div>
            </div>
            <div class="gauge-item">
              <div class="gauge-lbl">Color Kelvin</div>
              <div class="gauge-val" id="sim-gauge-kelvin"><?= ja_e($defaultScene['kelvin']) ?></div>
            </div>
            <div class="gauge-item">
              <div class="gauge-lbl">Perimeter Shield</div>
              <div class="gauge-val" id="sim-gauge-sec"><?= ja_e($defaultScene['security']) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Core Solutions Breakdown (Database Driven) -->
  <section class="solutions-section" id="solutions">
    <div class="wrap">
      <div class="section-head">
        <div class="eyebrow">End-to-End Residential Engineering</div>
        <h2 class="section-title">Six Pillars of Architectural Intelligence</h2>
        <p class="section-sub">
          Certified smart home conversion that blends with high-end African residential architecture without disruptive rewiring.
        </p>
        <div style="margin-top:16px;">
          <a href="solutions.php" class="corp-tag" style="background:var(--paper);border:1px solid var(--line);color:var(--forest);text-decoration:none;font-weight:700;padding:6px 14px;">
            Explore All 6 Detailed Solutions &amp; Schematics →
          </a>
        </div>
      </div>

      <div class="solutions-grid">
        <?php foreach ($services as $srv): ?>
          <div class="solution-card">
            <div class="card-icon">
              <?php if (str_contains($srv['slug'], 'access')): ?>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <?php elseif (str_contains($srv['slug'], 'lighting')): ?>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
              <?php elseif (str_contains($srv['slug'], 'climate')): ?>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>
              <?php elseif (str_contains($srv['slug'], 'microgrid')): ?>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
              <?php elseif (str_contains($srv['slug'], 'audio')): ?>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
              <?php else: ?>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              <?php endif; ?>
            </div>

            <?php if (!empty($srv['badge'])): ?>
              <span class="solution-badge"><?= ja_e($srv['badge']) ?></span>
            <?php endif; ?>

            <h3 class="solution-title"><?= ja_e($srv['title']) ?></h3>
            <p class="solution-desc"><?= ja_e($srv['short_desc']) ?></p>

            <ul class="solution-features">
              <?php foreach (($srv['features'] ?? []) as $feat): ?>
                <li>
                  <span class="check-bullet">✓</span>
                  <span><?= ja_e($feat) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>

            <div style="margin-top:20px;padding-top:14px;border-top:1px dashed var(--line);display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:12px;color:var(--muted)">Base Hardware Investment:</span>
              <span style="font-family:'Space Grotesk',sans-serif;font-weight:700;color:var(--forest)">from <?= ja_currency((float)$srv['base_price_ngn']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Dynamic Smart Home Cost Estimator -->
  <section class="estimator-section" id="estimator">
    <div class="wrap">
      <div class="section-head">
        <div class="eyebrow">Real-Time Budget Configurator</div>
        <h2 class="section-title">Configure Your Smart Home Conversion</h2>
        <p class="section-sub">
          Select your residence typology and customize hardware modules to calculate your estimated turnkey investment and monthly diesel power savings.
        </p>
      </div>

      <div class="estimator-box">
        <div class="estimator-step">
          <div class="step-num">STEP 1</div>
          <div class="step-title">Select Residence Typology</div>
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
          <div class="step-title">Choose Desired Automation Modules</div>
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
          </div>
          <a href="book-survey.php" class="btn-corp btn-corp-primary" onclick="proceedFromHomeEstimator(event)">
            <span>Apply to Site Survey Booking</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Certified Residential Case Studies (Database Driven) -->
  <section class="cases-section" id="cases">
    <div class="wrap">
      <div class="section-head">
        <div class="eyebrow">Proven Engineering Performance</div>
        <h2 class="section-title">Luxury Residential Transformations</h2>
        <p class="section-sub">
          How prominent Nigerian homeowners and portfolio operators elevated luxury, slashed diesel overhead, and attained total peace of mind.
        </p>
        <div style="margin-top:16px;">
          <a href="case-studies.php" class="corp-tag" style="background:var(--paper);border:1px solid var(--line);color:var(--forest);text-decoration:none;font-weight:700;padding:6px 14px;">
            View In-Depth Case Studies &amp; ROI Reports →
          </a>
        </div>
      </div>

      <div class="cases-grid">
        <?php foreach ($caseStudies as $cs): ?>
          <div class="case-card">
            <div class="case-meta">
              <span><?= ja_e($cs['location']) ?></span>
              <span>·</span>
              <span><?= ja_e($cs['property_type']) ?></span>
            </div>

            <h3 class="case-title"><?= ja_e($cs['title']) ?></h3>
            <div class="case-highlight"><?= ja_e($cs['highlight_metric']) ?></div>
            <p class="case-text"><?= ja_e($cs['narrative']) ?></p>

            <?php if (!empty($cs['testimonial_quote'])): ?>
              <div class="quote-box">
                <div class="quote-text">“<?= ja_e($cs['testimonial_quote']) ?>”</div>
                <div class="quote-author"><?= ja_e($cs['client_name']) ?> — <span style="font-weight:400;color:var(--muted)"><?= ja_e($cs['client_role']) ?></span></div>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- 5-Phase Engineering Methodology -->
  <section class="solutions-section" id="process" style="background:#ffffff;border-top:1px solid var(--line);border-bottom:1px solid var(--line);">
    <div class="wrap">
      <div class="section-head">
        <div class="eyebrow">Quality Engineering Standard</div>
        <h2 class="section-title">The 5-Phase Conversion Methodology</h2>
        <p class="section-sub">
          Enterprise rigor adapted for private luxury living. Clean, non-destructive retrofit executed by certified systems engineers.
        </p>
        <div style="margin-top:16px;">
          <a href="process.php" class="corp-tag" style="background:var(--paper);border:1px solid var(--line);color:var(--forest);text-decoration:none;font-weight:700;padding:6px 14px;">
            Read Full Engineering Specifications &amp; Standards →
          </a>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin-top:30px;">
        <div style="padding:24px;background:var(--paper);border-radius:12px;border:1px solid var(--line)">
          <div style="font-family:'Space Grotesk',sans-serif;font-size:24px;font-weight:800;color:var(--green);margin-bottom:8px;">01</div>
          <h4 style="font-size:16px;color:var(--forest);margin-bottom:8px">Site Audit &amp; Power Profiling</h4>
          <p style="font-size:13px;color:var(--muted);line-height:1.6">Comprehensive physical inspection of circuit boards, distribution panels, inverter battery banks, and Wi-Fi RF mesh.</p>
        </div>

        <div style="padding:24px;background:var(--paper);border-radius:12px;border:1px solid var(--line)">
          <div style="font-family:'Space Grotesk',sans-serif;font-size:24px;font-weight:800;color:var(--green);margin-bottom:8px;">02</div>
          <h4 style="font-size:16px;color:var(--forest);margin-bottom:8px">Schematic Topology Mapping</h4>
          <p style="font-size:13px;color:var(--muted);line-height:1.6">Architectural CAD schematics identifying wiring runs, KNX/Matter controller locations, and sensor focal cones.</p>
        </div>

        <div style="padding:24px;background:var(--paper);border-radius:12px;border:1px solid var(--line)">
          <div style="font-family:'Space Grotesk',sans-serif;font-size:24px;font-weight:800;color:var(--green);margin-bottom:8px;">03</div>
          <h4 style="font-size:16px;color:var(--forest);margin-bottom:8px">Hardware Commissioning</h4>
          <p style="font-size:13px;color:var(--muted);line-height:1.6">Zero-damage retrofit. Installation of solid brass keypads, smart relays, split-core CT power clamps, and motorized locks.</p>
        </div>

        <div style="padding:24px;background:var(--paper);border-radius:12px;border:1px solid var(--line)">
          <div style="font-family:'Space Grotesk',sans-serif;font-size:24px;font-weight:800;color:var(--green);margin-bottom:8px;">04</div>
          <h4 style="font-size:16px;color:var(--forest);margin-bottom:8px">Scene Calibration &amp; Failover</h4>
          <p style="font-size:13px;color:var(--muted);line-height:1.6">Real-world load-shedding and inverter switchover stress testing. Custom biorhythm circadian lighting curve fine-tuning.</p>
        </div>

        <div style="padding:24px;background:var(--paper);border-radius:12px;border:1px solid var(--line)">
          <div style="font-family:'Space Grotesk',sans-serif;font-size:24px;font-weight:800;color:var(--green);margin-bottom:8px;">05</div>
          <h4 style="font-size:16px;color:var(--forest);margin-bottom:8px">Handover &amp; Concierge SLA</h4>
          <p style="font-size:13px;color:var(--muted);line-height:1.6">Private concierge onboarding for family and staff. 24/7 dedicated engineering telephone line and warranty SLA.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Executive Site Survey Booking Form -->
  <section class="survey-section" id="survey">
    <div class="wrap">
      <div class="survey-card">
        <div class="survey-card-left">
          <div class="corp-tag" style="background:rgba(255,255,255,0.12);color:var(--gold);margin-bottom:16px;">Turnkey Consultation</div>
          <h2>Request an On-Site Engineering Audit</h2>
          <p>
            Our senior systems engineers conduct in-depth physical inspections of your property's electrical distribution, backup inverters, and network infrastructure.
          </p>

          <div style="margin-top:32px;display:flex;flex-direction:column;gap:18px;">
            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:700">✓</div>
              <div>
                <b style="color:#ffffff;font-size:14px">Complimentary Lagos Metro Audit</b>
                <div style="font-size:12.5px;color:rgba(255,255,255,0.7);line-height:1.5">Free on-site audit for residences in Ikoyi, Victoria Island, Lekki Phase 1, Banana Island, and Ikeja GRA.</div>
              </div>
            </div>

            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:700">✓</div>
              <div>
                <b style="color:#ffffff;font-size:14px">Formal CAD Schematics &amp; BOM</b>
                <div style="font-size:12.5px;color:rgba(255,255,255,0.7);line-height:1.5">Receive a detailed bill of materials, circuit topology diagram, and guaranteed turnkey conversion quote.</div>
              </div>
            </div>

            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:700">✓</div>
              <div>
                <b style="color:#ffffff;font-size:14px">Jollof Living Host Fleet Discounts</b>
                <div style="font-size:12.5px;color:rgba(255,255,255,0.7);line-height:1.5">Preferred hardware packages and direct Jollof Living PMS software synchronization for apartment hosts.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="survey-card-right">
          <form id="surveyForm" onsubmit="handleSurveySubmit(event)">
            <input type="hidden" id="clientEstimate" value="₦14,300,000">

            <div class="form-row">
              <div class="form-group">
                <label for="clientName">Full Name *</label>
                <input type="text" id="clientName" class="form-control" placeholder="e.g. Chief Adebayo Cole" required>
              </div>

              <div class="form-group">
                <label for="clientEmail">Email Address *</label>
                <input type="email" id="clientEmail" class="form-control" placeholder="adebayo@company.ng" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="clientPhone">Phone Number *</label>
                <input type="tel" id="clientPhone" class="form-control" placeholder="+234 803 000 0000" required>
              </div>

              <div class="form-group">
                <label for="clientCity">City &amp; Area *</label>
                <select id="clientCity" class="form-control" required>
                  <option value="Lagos (Ikoyi / VI / Lekki)">Lagos (Ikoyi / VI / Lekki / Banana Island)</option>
                  <option value="Lagos (Mainland / Ikeja GRA)">Lagos (Mainland / Ikeja GRA / Magodo)</option>
                  <option value="Abuja (Maitama / Asokoro / Guzape)">Abuja (Maitama / Asokoro / Guzape / Wuse)</option>
                  <option value="Port Harcourt (GRA / Old GRA)">Port Harcourt (GRA / Peter Odili / Trans-Amadi)</option>
                  <option value="Ibadan / Epe / Other">Other Urban Residence</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="clientPropType">Property Typology *</label>
                <select id="clientPropType" class="form-control">
                  <?php foreach ($typologies as $typ): ?>
                    <option value="<?= ja_e($typ['name']) ?>"><?= ja_e($typ['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="clientDate">Preferred Survey Date</label>
                <input type="date" id="clientDate" class="form-control" min="<?= date('Y-m-d') ?>">
              </div>
            </div>

            <div class="form-group">
              <label for="clientNotes">Project Specifics or Current Power Challenges</label>
              <textarea id="clientNotes" class="form-control" rows="3" placeholder="Describe your residence (e.g. 5-bedroom duplex in Ikoyi with a 30kVA generator and 15kVA solar inverter. Looking for full biometric access and smart climate load shedding)."></textarea>
            </div>

            <button type="submit" id="submitBtn" class="btn-corp btn-corp-primary" style="width:100%;padding:14px;font-size:15px;margin-top:10px">
              Submit Survey Request &amp; Reserve Date →
            </button>
            <div style="font-size:11.5px;color:var(--muted);text-align:center;margin-top:12px;">
              Data encrypted with 256-bit SSL. A certified systems engineer will contact you within 24 hours.
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

  <!-- Pass dynamic PHP scenes and presets into client-side JS -->
  <script>
    window.JA_SCENES = <?= json_encode($scenes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  </script>

<?php
require_once __DIR__ . '/includes/footer.php';
