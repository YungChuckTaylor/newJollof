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
          <div style="margin-bottom:16px">
            <a href="index.php">
              <img src="assets/img/automations-logo-white.png" alt="Jollof Automations" height="42" style="display:block;max-width:100%;object-fit:contain;">
            </a>
          </div>
          <p>
            An ultra-premium residential technology subsidiary of <b>Jollof Living Limited</b>. We engineer autonomous, energy-resilient, and beautifully integrated smart homes across West Africa.
          </p>
          <div style="margin-top:16px;font-size:13px;color:rgba(255,255,255,0.7);line-height:1.6">
            Headquarters: Victoria Island, Lagos, Nigeria<br>
            Direct Technical Inquiries: <a href="mailto:automations@jollofliving.com" style="color:var(--gold);text-decoration:underline">automations@jollofliving.com</a><br>
            Direct Helpline: <a href="tel:+23418885655" style="color:#ffffff;text-decoration:underline">+234 1 888 5655</a>
          </div>
        </div>

        <div class="footer-col">
          <h4>Smart Solutions</h4>
          <ul>
            <li><a href="solutions.php#access">Biometric Access &amp; Keypads</a></li>
            <li><a href="solutions.php#lighting">Circadian Architectural Lighting</a></li>
            <li><a href="solutions.php#climate">Smart Inverter Climate AI</a></li>
            <li><a href="solutions.php#power">Microgrid &amp; Generator Sync</a></li>
            <li><a href="solutions.php#audio">Multi-Room Acoustic Distribution</a></li>
            <li><a href="solutions.php#security">Edge Computer Vision Defense</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Pages &amp; Tools</h4>
          <ul>
            <li><a href="solutions.php">All Solutions Catalog</a></li>
            <li><a href="lab.php">Experience Lab (Room Simulator)</a></li>
            <li><a href="estimator.php">Smart Home Budget Estimator</a></li>
            <li><a href="case-studies.php">Client Case Studies &amp; ROI</a></li>
            <li><a href="process.php">5-Phase Engineering Standards</a></li>
            <li><a href="book-survey.php">Book Executive Site Survey</a></li>
            <li><a href="leads.php">Internal Leads Dashboard</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Jollof Living Ecosystem</h4>
          <ul>
            <li><a href="../">Jollof Living Home</a></li>
            <li><a href="../stays.php">Luxury Residences &amp; Penthouses</a></li>
            <li><a href="../host.php">Host With Jollof Living</a></li>
            <li><a href="../business.php">Corporate Partnerships</a></li>
            <li><a href="../help.php">Concierge &amp; Support</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div>© <?= date('Y') ?> Jollof Automations Limited. All rights reserved. A subsidiary of Jollof Living.</div>
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
          <span>256-Bit Encrypted IoT Telemetry</span>
          <span>Zero-Cloud Dependency Architecture</span>
          <span>Matter 1.3 &amp; KNX Certified</span>
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
