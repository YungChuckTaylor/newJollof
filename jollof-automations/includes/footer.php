<?php
/**
 * Jollof Automations — Site Footer Template
 */
declare(strict_types=1);
?>
  <!-- Footer -->
  <footer class="site-footer">
    <div class="wrap">
      <div class="footer-grid">
        <div class="footer-col">
          <div class="brand-logo" style="margin-bottom:14px">
            <div class="brand-icon">⚡</div>
            <div class="brand-text">
              <span class="brand-title" style="color:#ffffff">JOLLOF AUTOMATIONS</span>
              <span class="brand-sub" style="color:var(--gold)">Turn Your Home Into an Intelligent Sanctuary</span>
            </div>
          </div>
          <p>
            An ultra-premium residential technology subsidiary of <b>Jollof Living Limited</b>. We engineer autonomous, energy-resilient, and beautifully integrated smart homes across West Africa.
          </p>
          <div style="margin-top:16px;font-size:13px;color:rgba(255,255,255,0.7)">
            Headquarters: Victoria Island, Lagos, Nigeria<br>
            Direct Technical Inquiries: <a href="mailto:automations@jollofliving.com" style="color:var(--gold);text-decoration:underline">automations@jollofliving.com</a>
          </div>
        </div>

        <div class="footer-col">
          <h4>Smart Solutions</h4>
          <ul>
            <li><a href="index.php#solutions">Biometric Access &amp; Keypads</a></li>
            <li><a href="index.php#solutions">Circadian Architectural Lighting</a></li>
            <li><a href="index.php#solutions">Smart Inverter Climate AI</a></li>
            <li><a href="index.php#solutions">Microgrid &amp; Generator Sync</a></li>
            <li><a href="index.php#solutions">Multi-Room Acoustic Distribution</a></li>
            <li><a href="index.php#solutions">Edge Computer Vision Defense</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Interactive Tools</h4>
          <ul>
            <li><a href="index.php#simulator">Experience Lab (Room Simulator)</a></li>
            <li><a href="index.php#configurator">Smart Home Budget Estimator</a></li>
            <li><a href="index.php#process">5-Phase Engineering Standards</a></li>
            <li><a href="index.php#survey">Book Executive Site Survey</a></li>
            <li><a href="leads.php">Internal Leads Management</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Jollof Living Ecosystem</h4>
          <ul>
            <li><a href="../">Jollof Living Home</a></li>
            <li><a href="../stays">Luxury Residences &amp; Penthouses</a></li>
            <li><a href="../host">Host With Jollof Living</a></li>
            <li><a href="../business">Corporate Partnerships</a></li>
            <li><a href="../help">Concierge &amp; Support</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div>© <?= date('Y') ?> Jollof Automations Limited. All rights reserved. A subsidiary of Jollof Living.</div>
        <div style="display:flex;gap:20px;">
          <span>256-Bit Encrypted IoT Telemetry</span>
          <span>Zero-Cloud Dependency Architecture</span>
        </div>
      </div>
    </div>
  </footer>

  <!-- Notification Toast -->
  <div class="toast-popup" id="toastBox">
    <span id="toastMsg">Notification message</span>
  </div>

  <script src="assets/js/app.js" defer></script>
</body>
</html>
