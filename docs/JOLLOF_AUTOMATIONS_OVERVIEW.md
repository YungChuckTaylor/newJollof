# Jollof Automations — Subsidiary Technical & Brand Architecture

**Jollof Automations** is an ultra-premium engineering subsidiary of **Jollof Living**, dedicated to transforming private luxury residences, short-let hospitality portfolios, and executive penthouses into intelligent, autonomous, and energy-resilient living spaces across Nigeria and West Africa.

---

## 1. Brand Identity & Visual Language

### Corporate Light Theme (Jollof Living Audit Standard)
As established in the parent corporate design system (`docs/JOLLOF_LIVING_FEATURE_AUDIT.html`), Jollof Automations adopts the prestigious, nature-grounded forest green palette:
- **Deep Forest Green** (`#173d30`): Primary brand color, headers, executive statements, and hero badges.
- **Deepest Forest Accent** (`#102b23`): Sub-surfaces, contrast badges, and deep accent panels.
- **Emerald Green** (`#1a6744`): Active indicators, primary CTA buttons, verified state badges, and system telemetry pulses.
- **Warm Architectural Linen / Paper** (`#f7f6f0`): Primary body canvas and clean, glare-free light background.
- **Pure White** (`#ffffff`): Elevated cards, glassmorphic interactive modules, and input panels.
- **Gold Ochre Accent** (`#c29c4b`): Hardware highlights, premium indicators, and status accents.
- **Deep Obsidian Ink** (`#1c3028`): High-contrast typographic body text.
- **Muted Sage / Olive** (`#536257`): Secondary descriptions, technical annotations, and captions.

### Typography
- **Primary Brand Typography**: `Plus Jakarta Sans` (Weights: 400, 500, 600, 700, 800) — clean, modern, corporate grotesque offering crisp legibility and sophisticated luxury poise.
- **Technical & Metric Font**: `Space Grotesk` (Weights: 500, 600, 700) — used for telemetry, power consumption metrics, Kelvin light temperatures, protocol tags, and reference numbers.

---

## 2. Core Smart Home Solutions

Jollof Automations delivers enterprise-grade, certified smart residential transformations across six dedicated engineering domains:

1. **Autonomous Access & Hospitality Access Control**
   - Biometric touch sensors, anti-peep digital keypads, smart RFID badges, and Apple Home Key / NFC entry.
   - Dynamic hospitality check-in integration: automated guest PIN issuance synchronized with Jollof Living reservation checkout windows.
   - Auto-expiring visitor codes with tamper detection and instant video doorbell notifications.

2. **Smart Climate & Environmental AI**
   - Microclimate multi-zone AC modulation balancing comfort with inverter efficiency.
   - Smart ambient humidity, dust particulate ($PM_{2.5}$), and air quality monitoring with motorized extraction fans.
   - Sub-room occupancy sensors that set cooling units to energy-saving standby when bedrooms or living areas are vacant.

3. **Human-Centric & Architectural Tunable Lighting**
   - Circadian biorhythm scheduling shifting from 5,000K daylight white in the morning to 2,000K warm candle amber in the evening.
   - In-wall custom brass keypads, magnetic low-voltage track lighting, and under-cabinet architectural accents.
   - Synchronized one-touch ambiance scenes: *Arrive Home*, *Cinema & Soirée*, *Relaxation*, and *All Off*.

4. **Integrated Microgrid & Generator Management**
   - Real-time IoT split-core monitoring for NEPA / PHCN grid status, solar PV generation, lithium storage battery levels, and generator fuel reserve.
   - Sub-10 millisecond zero-flicker UPS switchover that protects sensitive smart appliances, audio gear, and servers.
   - Dynamic load shedding: non-critical loads (water heaters, outdoor pumps) automatically pause during generator or battery operation.

5. **Acoustic Sanctuary & Multi-Room Audio**
   - Invisible architectural in-ceiling and in-wall speakers flush with drywall.
   - High-resolution AirPlay 2, Spotify Connect, and Roon audio distribution across multiple acoustic zones.
   - Home theater integration with motorized projection drop-downs and synchronized blackout shades.

6. **Perimeter Defense & Proactive Threat Interception**
   - AI computer vision camera matrices distinguishing between family members, estate staff, pets, and unauthorized perimeter breaches.
   - Dual-spectrum thermal perimeter radar with automated perimeter floodlight illumination.
   - 24/7 localized encrypted Network Video Recording (NVR) with optional armed estate security relay.

---

## 3. Interactive Web Deliverables

### A. The Interactive Experience Lab (Digital Twin Simulator)
Located in `automations/index.html`, visitors can test real smart home scenes in real time:
- **Arrive Home Scene**: Unlocks entry, turns on warm 2,700K ambient illumination, engages 21°C dual inverter cooling, and adjusts motorized drapes.
- **Cinema & Soirée Scene**: Dims downlights to warm 2,000K amber, deploys blackout shades, and adjusts acoustics.
- **Power Outage / Solar Sync**: Demonstrates instant sub-10ms inverter switchover and smart load shedding.
- **Hospitality Turnover Protocol**: Shows how Jollof Living hosts switch apartments into eco-standby and revoke guest PINs automatically upon checkout.
- **Live Device Toggles**: Individual interactive switches for Biometric Locks, Architectural Lighting, Multi-Zone Climate, and Motorized Shades with responsive telemetry updates.

### B. Dynamic Smart Home Cost Estimator
Prospective clients select their property typology:
- Luxury Apartment (2–3 Beds)
- Detached Duplex / Villa (4–5 Beds)
- Short-Let Hospitality Suite
- Commercial Office / Showroom

Clients customize their automation modules (Biometric Access, Tunable Lighting, Smart Climate, Microgrid Sync, Multi-Zone Audio, AI Perimeter Defense). The configurator calculates instant hardware and commissioning budgets in Nigerian Naira ($\text{₦}$), displays estimated monthly diesel/energy savings, and pre-populates the consultation booking form with one click.

### C. Executive Site Survey Booking & Backend Lead API
- **Endpoint**: `automations/api/leads.php`
- Accepts structured JSON or form-encoded POST requests with validation for full name, email, phone number, property typology, city (Lagos, Abuja, Port Harcourt, Ibadan, or other), preferred survey date, scope items, and estimated budget.
- Generates a unique tracking reference (e.g. `JA-2026-A8F2`).
- Securely stores inquiries in the centralized `business_leads` database table with JSON metadata.
- Dispatches transactional confirmation emails to the client and engineering leads via `Mailer::send()`.

---

## 4. File Structure & Routing

```
newJollof/
├── automations.php               # Root entrypoint redirecting/rendering Jollof Automations
├── automations/
│   ├── index.html                # Interactive, responsive corporate website
│   ├── index.php                 # Direct folder index handler
│   └── api/
│       └── leads.php             # Lead ingestion, DB persistence & email notifications
├── docs/
│   ├── JOLLOF_LIVING_FEATURE_AUDIT.html   # Corporate design system reference
│   └── JOLLOF_AUTOMATIONS_OVERVIEW.md     # Architecture & implementation summary
└── includes/
    └── view.php                  # Global navigation & footer cross-links to Automations
```

---

## 5. Verification & Compliance
- **Color Accuracy**: Exactly matches `#173d30`, `#1a6744`, `#f7f6f0`, `#1c3028`, `#c29c4b`, and `#d9dfd4`.
- **Responsive Layout**: Fluid flex and CSS grid containers optimized from 320px mobile viewports up to 4K ultra-wide monitors.
- **Cross-Linking**: Direct navigation links added to header navigation and footer corporate sections in `includes/view.php`.
- **Zero-Dependency Core**: All animations, telemetry gauges, interactive toggles, and configurator math run natively in vanilla ES6 JavaScript with zero external runtime overhead.
