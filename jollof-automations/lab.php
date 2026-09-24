<?php
/**
 * Jollof Automations — Interactive Experience Lab Page
 * Full-screen digital twin room simulator with live telemetry & scene controls.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Experience Lab · Interactive Smart Home Digital Twin · Jollof Automations';
$pageDesc  = 'Step inside a live digital twin of a Jollof Automations smart home — toggle scenes, lighting temperatures and climate in the interactive Experience Lab.';
$noindex   = false;
$activeNav = 'lab';

$scenes = AutoRepo::getSimulatorScenes();
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

  <section class="hero-section" style="padding:48px 0 30px;text-align:left;">
    <div class="wrap">
      <div class="hero-badge">
        <span class="pulse-indicator"></span>
        <span>Interactive Experience Lab · Digital Twin Simulation</span>
      </div>
      <h1 class="hero-title" style="max-width:850px;margin-bottom:14px;">
        Simulate <span class="highlight">Autonomous Living</span>
      </h1>
      <p class="hero-lead" style="max-width:760px;margin-left:0;">
        Experience how Jollof Automations orchestrates ambient lighting, zero-flicker inverter transitions, and biometric entry in luxury Nigerian properties. Select preset scenes below or toggle individual devices.
      </p>
    </div>
  </section>

  <!-- Simulator Section -->
  <section class="simulator-section" style="padding:20px 0 80px;">
    <div class="wrap">
      <div class="sim-grid">
        <!-- Interactive Controls Column -->
        <div class="sim-controls">
          <div class="sim-controls-header">
            <h3>Automated Scene Profiles</h3>
            <span class="corp-tag" style="background:#e4efe7;color:var(--green)">Live Edge Presets</span>
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

          <div style="margin-top:24px;border-top:1px solid var(--line);padding-top:18px;">
            <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:14px;">Individual Device Overrides</div>
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

      <div style="margin-top:40px;background:#ffffff;border:1px solid var(--line);border-radius:12px;padding:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
        <div>
          <h3 style="font-size:18px;color:var(--forest);margin:0 0 6px;">Want this level of control in your home?</h3>
          <p style="font-size:14px;color:var(--muted);margin:0;">We engineer custom scenes tailored specifically to your family's routines and estate staff access schedules.</p>
        </div>
        <div style="display:flex;gap:12px;">
          <a href="estimator.php" class="btn-corp btn-corp-outline">Configure Budget</a>
          <a href="book-survey.php" class="btn-corp btn-corp-primary">Schedule Site Audit →</a>
        </div>
      </div>
    </div>
  </section>

  <script>
    window.JA_SCENES = <?= json_encode($scenes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  </script>

<?php
require_once __DIR__ . '/includes/footer.php';
