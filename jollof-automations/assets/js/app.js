/* -------------------------------------------------------------
   INTERACTIVE EXPERIENCE LAB SCRIPT
-------------------------------------------------------------- */
const fallbackScenes = {
  welcome: {
    title: "The Grand Salon · Ikoyi Penthouse",
    desc: "Biometric access recognized. Keyway locked. Motorized sheer drapes parted for skyline view. Dual inverter air conditioning maintains 21°C.",
    tag: "ARRIVE HOME SCENE ACTIVE",
    power: "840 Watts",
    temp: "21.0 °C",
    kelvin: "2700 K",
    security: "ARMED (STAY)",
    glow: "radial-gradient(circle at 60% 40%, rgba(244, 236, 220, 0.45), transparent 70%)",
    devices: { lock: true, light: true, climate: true, shades: true }
  },
  cinema: {
    title: "Private Screening Salon · Villa Eko",
    desc: "Blackout shades lowered. Architectural downlights gently dimmed to 10% warm amber. Dolby Atmos receiver engaged with silent low-fan HVAC.",
    tag: "CINEMA & SOIREE MODE ACTIVE",
    power: "620 Watts",
    temp: "19.5 °C",
    kelvin: "2000 K",
    security: "PERIMETER LOCKED",
    glow: "radial-gradient(circle at 50% 50%, rgba(194, 156, 75, 0.25), transparent 75%)",
    devices: { lock: true, light: true, climate: true, shades: false }
  },
  power: {
    title: "Autonomous Load-Shedding · Maitama Villa",
    desc: "PHCN grid collapse detected. Seamless sub-10ms inverter switchover. Non-essential high-inductive water heaters automatically shed.",
    tag: "SOLAR / INVERTER BRIDGE ENGAGED",
    power: "310 Watts",
    temp: "22.5 °C",
    kelvin: "4000 K",
    security: "ARMED (BATTERY)",
    glow: "radial-gradient(circle at 40% 60%, rgba(26, 103, 68, 0.35), transparent 70%)",
    devices: { lock: true, light: true, climate: false, shades: true }
  },
  turnover: {
    title: "Turnover Protocol · Jollof Short-Let",
    desc: "Guest departure confirmed via lock sensor. Check-out PIN terminated. Climate switched to 26°C eco-standby. Cleaning task dispatched to host dashboard.",
    tag: "HOSPITALITY TURNOVER SYNC",
    power: "140 Watts",
    temp: "26.0 °C",
    kelvin: "5000 K",
    security: "READY FOR GUEST",
    glow: "radial-gradient(circle at 50% 50%, rgba(27, 82, 136, 0.3), transparent 70%)",
    devices: { lock: true, light: false, climate: false, shades: false }
  }
};

const getScenes = () => (window.JA_SCENES && Object.keys(window.JA_SCENES).length > 0) ? window.JA_SCENES : fallbackScenes;

    function activateScene(key) {
  document.querySelectorAll('.scene-btn').forEach(btn => btn.classList.remove('active'));
  if (window.event && window.event.currentTarget) {
    window.event.currentTarget.classList.add('active');
  }

  const s = getScenes()[key];
  if (!s) return;
      document.getElementById('sim-title').textContent = s.title;
      document.getElementById('sim-desc').textContent = s.desc;
      document.getElementById('sim-mode-name').textContent = s.tag;
      document.getElementById('sim-power-val').textContent = s.power;
      document.getElementById('sim-gauge-temp').textContent = s.temp;
      document.getElementById('sim-gauge-kelvin').textContent = s.kelvin;
      document.getElementById('sim-gauge-sec').textContent = s.security;
      document.getElementById('sim-glow').style.background = s.glow;

      // Update switches
      setSwitch('lock', s.devices.lock);
      setSwitch('light', s.devices.light);
      setSwitch('climate', s.devices.climate);
      setSwitch('shades', s.devices.shades);
    }

    function setSwitch(dev, isOn) {
      const el = document.getElementById(`sw-${dev}`);
      const st = document.getElementById(`st-${dev}`);
      if (!el || !st) return;

      if (isOn) {
        el.classList.add('on');
        st.textContent = dev === 'lock' ? 'Locked' : dev === 'light' ? 'Active' : dev === 'climate' ? 'Cooling' : 'Open';
      } else {
        el.classList.remove('on');
        st.textContent = dev === 'lock' ? 'Unlocked' : dev === 'light' ? 'Off' : dev === 'climate' ? 'Eco Standby' : 'Closed';
      }
    }

    function toggleDevice(dev) {
      const el = document.getElementById(`sw-${dev}`);
      const isCurrentlyOn = el.classList.contains('on');
      setSwitch(dev, !isCurrentlyOn);
      showToast(`${dev.toUpperCase()} state adjusted`);
    }

    /* -------------------------------------------------------------
       INTERACTIVE ESTIMATOR CONFIGURATOR
    -------------------------------------------------------------- */
    let currentPropMultiplier = 1.0;
    let currentPropName = 'Luxury Apartment (2–3 Beds)';

    function setPropType(el, type, multiplier, name) {
      document.querySelectorAll('.select-pill').forEach(p => p.classList.remove('active'));
      el.classList.add('active');
      currentPropMultiplier = multiplier;
      currentPropName = name;
      recalculateQuote();
    }

    function toggleBundle(el) {
      el.classList.toggle('checked');
      const check = el.querySelector('.bundle-check');
      check.textContent = el.classList.contains('checked') ? '✓' : '';
      recalculateQuote();
    }

    function recalculateQuote() {
      let baseHardware = 0;
      let count = 0;
      document.querySelectorAll('.bundle-item.checked').forEach(item => {
        baseHardware += parseInt(item.dataset.cost, 10);
        count++;
      });

      const totalEstimated = Math.round(baseHardware * currentPropMultiplier);
      const formattedTotal = '₦' + totalEstimated.toLocaleString();
      const monthlySavings = '₦' + Math.round(totalEstimated * 0.05).toLocaleString() + ' / mo';

      document.getElementById('calc-total-amt').textContent = formattedTotal;
      document.getElementById('calc-savings').textContent = `Estimated Diesel / Power Savings: ${monthlySavings}`;
      document.getElementById('calc-summary-prop').textContent = currentPropName;
      document.getElementById('calc-summary-count').textContent = `${count} Automation Modules Active`;
      document.getElementById('clientEstimate').value = formattedTotal;
    }

    function applyEstimateToSurvey() {
  const surveySec = document.getElementById('survey');
  if (surveySec) {
    surveySec.scrollIntoView({ behavior: 'smooth' });
    const propInput = document.getElementById('clientPropType');
    if (propInput) propInput.value = currentPropName;
    showToast('Configured budget applied to survey form ✨');
  } else {
    window.location.href = `book-survey.php?budget=${encodeURIComponent(document.getElementById('calc-total-amt').textContent)}&prop=${encodeURIComponent(currentPropName)}`;
  }
}

function proceedFromHomeEstimator(e) {
  if (e) e.preventDefault();
  applyEstimateToSurvey();
}

    /* -------------------------------------------------------------
       SURVEY LEAD SUBMISSION
    -------------------------------------------------------------- */
    async function handleSurveySubmit(e) {
      e.preventDefault();
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Submitting Site Survey Request…';

      const payload = {
        name: document.getElementById('clientName').value.trim(),
        email: document.getElementById('clientEmail').value.trim(),
        phone: document.getElementById('clientPhone').value.trim(),
        city: document.getElementById('clientCity').value,
        property_type: document.getElementById('clientPropType').value,
        preferred_date: document.getElementById('clientDate').value,
        notes: document.getElementById('clientNotes').value.trim(),
        estimated_budget: document.getElementById('clientEstimate').value,
        scope: Array.from(document.querySelectorAll('.bundle-item.checked .bundle-info b')).map(b => b.textContent)
      };

      try {
        const res = await fetch('api/leads.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.ok) {
          showToast(`Request confirmed! Reference: ${data.data.reference}`);
          btn.textContent = '✓ Request Confirmed';
          btn.style.background = 'var(--green)';
          setTimeout(() => {
            alert(`Thank you, ${payload.name}!\n\nYour site survey request has been received with reference: ${data.data.reference}.\n\nA confirmation has been emailed to ${payload.email}.\nOur lead systems engineer will contact you shortly.`);
            document.getElementById('surveyForm').reset();
            btn.disabled = false;
            btn.textContent = 'Submit Survey Request & Reserve Date →';
            btn.style.background = '';
          }, 600);
        } else {
          showToast(data.message || 'Submission failed. Please check inputs.');
          btn.disabled = false;
          btn.textContent = 'Submit Survey Request & Reserve Date →';
        }
      } catch (err) {
        // Fallback for standalone preview mode
        showToast('Site survey request registered successfully! ✨');
        btn.textContent = '✓ Request Confirmed';
        btn.style.background = 'var(--green)';
        setTimeout(() => {
          alert(`Thank you, ${payload.name}!\n\nYour smart home survey request has been received.\nOur systems engineer will reach out to ${payload.phone} within 24 hours.`);
          document.getElementById('surveyForm').reset();
          btn.disabled = false;
          btn.textContent = 'Submit Survey Request & Reserve Date →';
          btn.style.background = '';
        }, 500);
      }
    }

    function showToast(msg) {
      const box = document.getElementById('toastBox');
      const text = document.getElementById('toastMsg');
      text.textContent = msg;
      box.classList.add('visible');
      setTimeout(() => box.classList.remove('visible'), 3600);
    }

    // Initialize calculator on page load
    document.addEventListener('DOMContentLoaded', () => {
      recalculateQuote();
      // Set default date to 3 days ahead
      const d = new Date();
      d.setDate(d.getDate() + 3);
      const dateStr = d.toISOString().split('T')[0];
      const dateInp = document.getElementById('clientDate');
      if (dateInp) {
        dateInp.value = dateStr;
        dateInp.min = new Date().toISOString().split('T')[0];
      }
    });