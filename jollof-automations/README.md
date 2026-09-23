# ⚡ Jollof Automations

> **Luxury Smart Home Conversion & Architectural Intelligence**  
> *A Subsidiary of Jollof Living Limited*

Jollof Automations is a dedicated high-end engineering brand delivering turnkey residential smart home conversions across Nigeria and West Africa. We engineer autonomous access, circadian lighting, multi-zone microclimate control, hybrid solar/inverter microgrids, and AI perimeter defense for private estates, luxury penthouses, and high-yield hospitality short-lets.

---

## 📁 Repository Structure

```
jollof-automations/
├── index.html              # Main corporate website
├── server.js               # Standalone zero-dependency Node.js HTTP & API server
├── package.json            # Development & run scripts
├── README.md               # Project documentation & deployment manual
├── assets/
│   ├── css/
│   │   └── style.css       # Corporate light palette (#173d30, #1a6744, #f7f6f0)
│   └── js/
│       └── app.js          # Interactive Digital Twin Simulator & Budget Calculator
└── api/
    ├── leads.php           # Ingestion API (DB + Mailer or fallback store)
    └── leads_store.json    # JSON storage for captured consultations
```

---

## 🎨 Visual Identity & Brand System

Built strictly following the official corporate design specifications from the Jollof Living Feature Audit:

- **Primary Color**: `#173d30` (Deep Forest Green)
- **Secondary Accent**: `#1a6744` (Emerald Green)
- **Surface Canvas**: `#f7f6f0` (Warm Architectural Linen Paper)
- **High Contrast Ink**: `#1c3028` (Obsidian Ink)
- **Luxury Trim**: `#c29c4b` (Gold Ochre)
- **Primary Typography**: `Plus Jakarta Sans` (Brand display & body)
- **Telemetry Typography**: `Space Grotesk` (Metrics, sensors, voltages & power data)

---

## 🚀 Running Standalone

### Option A: Using the included zero-dependency Node.js server
```bash
cd jollof-automations
npm start
```
Open your browser at `http://localhost:3030`.  
The site will be live with full static serving and a real working JSON lead ingestion endpoint.

### Option B: Using PHP built-in server
```bash
cd jollof-automations
php -S 0.0.0.0:8000
```
Open your browser at `http://localhost:8000`.

### Option C: Static hosting (Vercel, Netlify, Cloudflare Pages, S3)
The frontend in `index.html` and `assets/` is completely static and can be deployed directly to any CDN or static hosting platform.

---

## ⚡ Key Interactive Features

1. **Digital Twin Experience Lab**:
   - Interactive scene simulations (*Arrive Home*, *Cinema & Soirée*, *Power Outage / Solar Bridge*, and *Hospitality Turnover*).
   - Real-time device switch toggles updating room glow and telemetry metrics.

2. **Smart Home Cost Estimator & Savings Calculator**:
   - Dynamic typology selection (Apartments, Duplexes, Short-Let Suites, Offices).
   - Real-time hardware costing in $\text{₦}$ (NGN) and estimated monthly diesel/energy savings.
   - One-click transfer to site survey booking.

3. **Lead & Survey Booking Intake**:
   - Validated consultation form issuing tracking references (`JA-2026-XXXX`).
   - Automatically stores leads and dispatches notifications.

---

## 🏢 Parent Company Integration

When deployed alongside **Jollof Living**, Jollof Automations integrates seamlessly:
- Database records link directly into the `business_leads` table.
- Notifications dispatch via `Mailer::send()`.
- Navigation routes link smoothly between the main luxury hospitality platform and the automation subsidiary.

---

© 2026 Jollof Automations Limited. A Subsidiary of Jollof Living. All rights reserved.
