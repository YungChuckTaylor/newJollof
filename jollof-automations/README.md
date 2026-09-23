# ⚡ Jollof Automations — Turnkey Smart Home Solutions

> **Luxury Smart Home Conversion & Architectural Intelligence**  
> *A Subsidiary of Jollof Living Limited*

Jollof Automations is an enterprise-grade **PHP / MySQL** residential technology platform engineered to convert private luxury residences, diplomatic estates, and short-let hospitality fleets into intelligent smart homes.

---

## 🏗️ Architecture & Stack

Modeled directly after **Jollof Living**, Jollof Automations uses a clean, object-oriented PHP architecture backed by a high-performance MySQL schema:

```
jollof-automations/
├── index.php               # Dynamic PHP homepage (services, simulator, calculator, cases)
├── index.html              # High-performance pre-rendered static fallback
├── leads.php               # Internal leads & consultation administration portal
├── schema.sql              # MySQL database schema & seed datasets
├── config.php              # Database & environment configuration (auto-detects Jollof Living)
├── config.sample.php       # Template configuration for standalone deployments
├── server.js               # Zero-dependency local Node.js development preview server
├── package.json            # Development scripts ("npm start")
├── includes/
│   ├── bootstrap.php       # Session, configuration loader & helpers
│   ├── db.php              # AutoDB PDO database connection & query engine
│   ├── models.php          # AutoRepo data models (Services, Scenes, Typologies, Leads)
│   ├── header.php          # Corporate brand navigation & head metadata
│   └── footer.php          # Corporate footer & system telemetry
├── assets/
│   ├── css/
│   │   └── style.css       # Light corporate palette (#173d30, #1a6744, #f7f6f0, #c29c4b)
│   └── js/
│       └── app.js          # Interactive Digital Twin room simulator & budget calculator
├── api/
│   └── leads.php           # RESTful API endpoint inserting leads into MySQL & triggering email
└── storage/
    └── leads_backup.json   # Redundant fallback storage
```

---

## 🗄️ MySQL Database Schema

The database schema (`schema.sql`) introduces dedicated tables for smart home engineering:

| Table | Purpose |
| :--- | :--- |
| `automations_services` | 6 core engineering pillars (Biometric Access, Tunable Lighting, Smart Climate, Microgrids, Audio, Computer Vision) |
| `automations_simulator_scenes` | Interactive Digital Twin telemetry presets (Ikoyi Arrive Home, VI Cinema, Maitama Inverter Failover, Lekki Turnover) |
| `automations_typologies` | Residential categories (Apartment, Duplex/Villa, Short-Let Suite, Commercial Office) with cost multipliers |
| `automations_case_studies` | Verified Nigerian client transformations with fuel reduction & security metrics |
| `automations_leads` | Inbound site survey audit requests with reference codes (`JA-YYYY-XXXX`), budgets, dates, and status workflows |
| `automations_settings` | Brand configurations, contact numbers, and site survey policies |

---

## ⚙️ Installation & Setup

### 1. Database Setup
Import the schema into your MySQL database:
```bash
mysql -u your_user -p your_database < jollof-automations/schema.sql
```
*(When running inside the main Jollof Living repo, `install/schema/2026_09_23_automations_smart_homes.sql` is automatically included in database migrations and `wal7zkit_jollof.sql`).*

### 2. Configuration
Copy `config.sample.php` to `config.php` and configure your database credentials:
```php
return [
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'wal7zkit_jollof',
        'user'     => 'wal7zkit_jollof',
        'pass'     => 'your_password',
        'charset'  => 'utf8mb4',
    ],
    // ...
];
```
*Note: If deployed within the parent Jollof Living repository, `config.php` auto-detects and inherits parent credentials automatically.*

### 3. Local Development Server
You can run the PHP server with:
```bash
cd jollof-automations
php -S 0.0.0.0:8000
```
Or run the zero-dependency Node.js mock/preview server:
```bash
npm start
```

---

## ⚡ Core Dynamic Features

1. **Database-Driven Content**: All solution cards, hardware packages, simulator presets, and real-world case studies render dynamically from MySQL using `AutoRepo`.
2. **Interactive Digital Twin Lab**: Homeowners can test live ambient room lighting, power load shedding, and smart lock status changes in real time.
3. **Dynamic Budget Estimator**: Multiplies base hardware packages by property typology multipliers with instant Naira ($\text{₦}$) calculations.
4. **Site Survey Booking API**: Captures consultation appointments into MySQL `automations_leads` (and parent `business_leads`), dispatches confirmation emails, and displays live in `leads.php`.
5. **Administrative Leads Portal**: View all consultation requests, contact numbers, configured budgets, and update lead pipeline status (`new` → `survey_scheduled` → `quoted` → `completed`).

---

© 2026 Jollof Automations Limited. A Subsidiary of Jollof Living. All rights reserved.
