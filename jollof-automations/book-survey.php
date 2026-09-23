<?php
/**
 * Jollof Automations — Book Site Survey & Consultation Page
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Book an On-Site Engineering Audit · Jollof Automations';
$activeNav = 'survey';

$prefillBudget = trim((string) ($_GET['budget'] ?? '₦14,300,000'));
$prefillProp   = trim((string) ($_GET['prop'] ?? 'Luxury Apartment (2–3 Beds)'));
$prefillModule = trim((string) ($_GET['module'] ?? ''));

$typologies = AutoRepo::getTypologies();
$services   = AutoRepo::getServices();

require_once __DIR__ . '/includes/header.php';
?>

  <section class="hero-section" style="padding:48px 0 30px;text-align:left;">
    <div class="wrap">
      <div class="hero-badge">
        <span class="pulse-indicator"></span>
        <span>Executive Site Survey Reservation</span>
      </div>
      <h1 class="hero-title" style="max-width:850px;margin-bottom:14px;">
        Book an On-Site <span class="highlight">Engineering Audit</span>
      </h1>
      <p class="hero-lead" style="max-width:760px;margin-left:0;">
        Our senior systems engineers conduct in-depth physical inspections of your property's electrical distribution, backup inverters, and network infrastructure.
      </p>
    </div>
  </section>

  <!-- Survey Booking Section -->
  <section class="survey-section" style="padding:10px 0 80px;">
    <div class="wrap">
      <div class="survey-card" style="margin-top:0;">
        <div class="survey-card-left">
          <div class="corp-tag" style="background:rgba(255,255,255,0.12);color:var(--gold);margin-bottom:16px;">Turnkey Consultation</div>
          <h2>What Happens During Your Site Audit?</h2>
          <p>
            An experienced smart systems engineer personally visits your residence to profile electrical circuits, evaluate Wi-Fi RF mesh, and draft your custom automation schematics.
          </p>

          <div style="margin-top:32px;display:flex;flex-direction:column;gap:20px;">
            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:700">✓</div>
              <div>
                <b style="color:#ffffff;font-size:14.5px">Distribution Board (MDB) Inspection</b>
                <div style="font-size:12.5px;color:rgba(255,255,255,0.7);line-height:1.5">Assessment of phase balance, breaker capacities, and DIN-rail smart energy metering space.</div>
              </div>
            </div>

            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:700">✓</div>
              <div>
                <b style="color:#ffffff;font-size:14.5px">Inverter &amp; Generator Profiling</b>
                <div style="font-size:12.5px;color:rgba(255,255,255,0.7);line-height:1.5">Verification of automatic transfer switches (ATS) to guarantee sub-10ms zero-flicker failover.</div>
              </div>
            </div>

            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:700">✓</div>
              <div>
                <b style="color:#ffffff;font-size:14.5px">CAD Schematics &amp; Guaranteed BOM</b>
                <div style="font-size:12.5px;color:rgba(255,255,255,0.7);line-height:1.5">Receive an itemized bill of materials with fixed pricing and turnkey completion timelines.</div>
              </div>
            </div>

            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:700">✓</div>
              <div>
                <b style="color:#ffffff;font-size:14.5px">Complimentary Lagos Metro Audit</b>
                <div style="font-size:12.5px;color:rgba(255,255,255,0.7);line-height:1.5">Free audit for residences in Ikoyi, VI, Lekki Phase 1, Banana Island, and Ikeja GRA.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="survey-card-right">
          <form id="surveyForm" onsubmit="handleSurveySubmit(event)">
            <input type="hidden" id="clientEstimate" value="<?= ja_e($prefillBudget) ?>">

            <div class="form-row">
              <div class="form-group">
                <label for="clientName">Full Name *</label>
                <input type="text" id="clientName" class="form-control" placeholder="e.g. Dr. Kalu Nnamdi" required>
              </div>

              <div class="form-group">
                <label for="clientEmail">Email Address *</label>
                <input type="email" id="clientEmail" class="form-control" placeholder="kalu@enterprise.ng" required>
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
                    <option value="<?= ja_e($typ['name']) ?>" <?= str_contains($prefillProp, $typ['name']) ? 'selected' : '' ?>>
                      <?= ja_e($typ['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="clientDate">Preferred Survey Date</label>
                <input type="date" id="clientDate" class="form-control" min="<?= date('Y-m-d') ?>">
              </div>
            </div>

            <div class="form-group">
              <label>Select Automation Scope (Multi-Select)</label>
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;margin-top:6px;">
                <?php foreach ($services as $srv): ?>
                  <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--ink);cursor:pointer;background:var(--paper);border:1px solid var(--line);padding:8px 10px;border-radius:6px;">
                    <input type="checkbox" name="scope[]" value="<?= ja_e($srv['title']) ?>" <?= (empty($prefillModule) || str_contains($srv['title'], $prefillModule)) ? 'checked' : '' ?>>
                    <span><?= ja_e($srv['category']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="form-group" style="margin-top:14px;">
              <label for="clientNotes">Project Specifics &amp; Backup Infrastructure</label>
              <textarea id="clientNotes" class="form-control" rows="3" placeholder="Describe your residence (e.g. 5-bedroom duplex in Ikoyi with a 30kVA generator and 15kVA solar inverter. Looking for full biometric access and smart climate load shedding)."></textarea>
            </div>

            <button type="submit" id="submitBtn" class="btn-corp btn-corp-primary" style="width:100%;padding:14px;font-size:15px;margin-top:10px">
              Submit Survey Request &amp; Reserve Date →
            </button>
            <div style="font-size:11.5px;color:var(--muted);text-align:center;margin-top:12px;">
              Your information is protected with 256-bit encryption. A senior engineer will confirm your appointment within 24 hours.
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

<?php
require_once __DIR__ . '/includes/footer.php';
