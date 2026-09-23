-- =====================================================================
-- JOLLOF AUTOMATIONS — SUBSIDIARY SCHEMA & SEED DATA
-- Turned residential homes into smart homes with IoT, microgrid & AI
-- =====================================================================

CREATE TABLE IF NOT EXISTS `automations_services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(80) NOT NULL UNIQUE,
  `category` VARCHAR(60) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `subtitle` VARCHAR(255) NULL,
  `icon_svg` TEXT NULL,
  `badge` VARCHAR(50) NULL,
  `short_desc` TEXT NOT NULL,
  `long_desc` LONGTEXT NULL,
  `features_json` JSON NULL,
  `hardware_specs_json` JSON NULL,
  `base_price_ngn` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `is_bundle` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `automations_simulator_scenes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scene_key` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `location` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `system_tag` VARCHAR(100) NOT NULL,
  `power_watts` VARCHAR(50) NOT NULL DEFAULT '650 Watts',
  `temp_celsius` VARCHAR(20) NOT NULL DEFAULT '21.0 °C',
  `kelvin` VARCHAR(20) NOT NULL DEFAULT '2700 K',
  `security_mode` VARCHAR(50) NOT NULL DEFAULT 'ARMED',
  `glow_gradient` TEXT NOT NULL,
  `devices_state_json` JSON NOT NULL,
  `display_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `automations_typologies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `typical_bedrooms` VARCHAR(50) NOT NULL,
  `typical_sqm` VARCHAR(50) NOT NULL,
  `recommended_package` VARCHAR(100) NOT NULL,
  `display_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `automations_case_studies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(80) NOT NULL UNIQUE,
  `title` VARCHAR(150) NOT NULL,
  `location` VARCHAR(100) NOT NULL,
  `property_type` VARCHAR(100) NOT NULL,
  `client_type` VARCHAR(80) NOT NULL,
  `completion_year` INT NOT NULL DEFAULT 2025,
  `highlight_metric` VARCHAR(100) NOT NULL,
  `narrative` TEXT NOT NULL,
  `systems_installed_json` JSON NULL,
  `testimonial_quote` TEXT NULL,
  `client_name` VARCHAR(100) NULL,
  `client_role` VARCHAR(100) NULL,
  `display_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `automations_leads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference` VARCHAR(40) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `property_type` VARCHAR(100) NOT NULL,
  `property_size` VARCHAR(100) NULL,
  `estimated_budget` VARCHAR(100) NULL,
  `scope_json` JSON NULL,
  `preferred_date` DATE NULL,
  `client_notes` TEXT NULL,
  `status` ENUM('new', 'contacted', 'survey_scheduled', 'quoted', 'in_progress', 'completed', 'declined') NOT NULL DEFAULT 'new',
  `admin_notes` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_lead_status` (`status`),
  INDEX `idx_lead_email` (`email`),
  INDEX `idx_lead_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `automations_settings` (
  `setting_key` VARCHAR(60) PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `setting_group` VARCHAR(40) NOT NULL DEFAULT 'general',
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA
-- =====================================================================

INSERT INTO `automations_services` 
(`slug`, `category`, `title`, `subtitle`, `badge`, `short_desc`, `features_json`, `hardware_specs_json`, `base_price_ngn`, `display_order`)
VALUES
('biometric-hospitality-access', 'Access & Security', 'Autonomous Biometric & Hospitality Access Control', 'Touch-free biometric recognition with short-let reservation synchronization', 'Flagship Module', 'Replace traditional lock cylinders with enterprise-grade biometric locks, anti-peep keypads, and Apple Home Key NFC. For hospitality hosts, access codes sync directly with guest check-in/out schedules.', '["Sub-0.3s Optical Biometric Recognition", "Automated Short-Let Checkout PIN Expiry", "Apple Home Key & NFC Estate Badges", "Real-Time Remote Tamper Telemetry"]', '{"lock_standard": "Euro Profile / ANSI Grade 1", "battery_backup": "12-Month Alkaline + Type-C Jump", "protocols": "Zigbee 3.0 / Matter over Thread"}', 1850000.00, 1),

('circadian-architectural-lighting', 'Ambiance & Wellness', 'Human-Centric & Architectural Tunable Lighting', '2,000K to 5,000K natural biorhythm tracking with solid brass wall keypads', 'Circadian AI', 'Lighting that aligns with the biological clock: crisp 5000K daylight white in the morning for peak cognitive focus, transitioning to 2000K candle amber at dusk to trigger natural melatonin production.', '["Natural Circadian Biorhythm Scheduling", "Solid Brass In-Wall Tactile Keypads", "Zero-Flicker Magnetic Track Dimming", "Synchronized All-Off & Pathway Scenes"]', '{"dimming_standard": "PWM 0-10V / DALI-2", "color_temp_range": "2000K - 5000K CCT", "protocol": "KNX / Zigbee Pro"}', 2400000.00, 2),

('smart-climate-environmental-ai', 'Climate & Comfort', 'Multi-Zone Smart Climate & Environmental AI', 'Intelligent inverter regulation, indoor air quality monitoring, and humidity purging', 'Energy Saver', 'Centralized orchestration of multi-split inverter ACs. Detects room occupancy, ambient humidity, and harmful dust particulates, dynamically throttling fan speeds to eliminate overcooling and slash diesel burn.', '["Independent Multi-Zone Climate Mapping", "Occupancy-Aware Automated Eco Standby", "PM2.5 & Humidity Auto-Purge Ventilation", "Jollof Living Guest Comfort Presets"]', '{"sensor_accuracy": "±0.2°C Temperature / ±2% RH", "compatibility": "Daikin, Panasonic, LG, Gree", "protocol": "Matter / Modbus RTU"}', 1950000.00, 3),

('microgrid-generator-management', 'Power Resilience', 'Integrated Microgrid & Generator Management', 'Zero-flicker UPS switchover, real-time telemetry, and automated load shedding', 'Nigeria Critical', 'Engineered specifically for Nigerian power realities. Seamlessly bridges PHCN grid power, solar arrays, lithium battery reserves, and diesel/petrol generators with sub-10ms switchover protection.', '["Sub-10ms Zero-Flicker Power Failover", "Automated High-Inductive Load Shedding", "Real-Time Diesel Reserve & Run-Hour IoT", "Peak-Shaving Solar Microgrid Prioritization"]', '{"current_sensing": "Split-Core CT Clamps (Up to 250A)", "transfer_speed": "< 10 milliseconds", "protocol": "Modbus RS485 / Industrial IoT"}', 3200000.00, 4),

('acoustic-sanctuary-audio', 'Entertainment', 'Acoustic Sanctuary & Multi-Room Audio', 'Flush-mount architectural speakers with multi-zone AirPlay 2 & Spotify streaming', 'High-Fidelity', 'Studio-quality multi-room acoustics distributed invisibly through magnetic flush-mount ceiling and in-wall speakers. Cast high-fidelity playlists across terraces, living salons, and private master suites.', '["Magnetic Zero-Bezel Flush Architectural Speakers", "Multi-Zone Synchronized High-Res Streaming", "Apple AirPlay 2, Spotify Connect & Roon Ready", "Automated Party & Cinematic Audio Scenes"]', '{"frequency_response": "38Hz - 22kHz", "amplification": "Class-D Multi-Channel DSP", "protocol": "Wi-Fi 6 / Dante IP Audio"}', 2100000.00, 5),

('perimeter-defense-computer-vision', 'Security & Safety', 'Perimeter Defense & Proactive Computer Vision', 'AI recognition distinguishing family, staff, and unauthorized boundary breaches', 'Threat Shield', 'Proactive security that does not sleep. Dual-spectrum thermal sensors and optical AI cameras detect fence line intrusions, instantly triggering focused perimeter floodlights and armed estate alerts.', '["AI Human & Vehicle Edge Classification", "Dual-Spectrum Thermal Tripwire Radar", "Automated Intrusion Floodlight Flashing", "24/7 Encrypted Local On-Premise NVR"]', '{"resolution": "4K Ultra HD HDR + Thermal", "storage": "RAID-1 On-Premise 16TB Encrypted", "protocol": "ONVIF Profile T / PoE+"}', 2800000.00, 6)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `automations_simulator_scenes`
(`scene_key`, `name`, `title`, `location`, `description`, `system_tag`, `power_watts`, `temp_celsius`, `kelvin`, `security_mode`, `glow_gradient`, `devices_state_json`, `display_order`)
VALUES
('welcome', 'Arrive Home', 'The Grand Salon · Ikoyi Penthouse', 'Ikoyi, Lagos', 'Biometric access recognized. Keyway locked. Motorized sheer drapes parted for skyline view. Dual inverter air conditioning maintains 21°C.', 'ARRIVE HOME SCENE ACTIVE', '840 Watts', '21.0 °C', '2700 K', 'ARMED (STAY)', 'radial-gradient(circle at 60% 40%, rgba(244, 236, 220, 0.45), transparent 70%)', '{"lock": true, "light": true, "climate": true, "shades": true}', 1),

('cinema', 'Cinema & Soirée', 'Private Screening Salon · Villa Eko', 'Victoria Island, Lagos', 'Blackout shades lowered. Architectural downlights gently dimmed to 10% warm amber. Dolby Atmos receiver engaged with silent low-fan HVAC.', 'CINEMA & SOIREE MODE ACTIVE', '620 Watts', '19.5 °C', '2000 K', 'PERIMETER LOCKED', 'radial-gradient(circle at 50% 50%, rgba(194, 156, 75, 0.25), transparent 75%)', '{"lock": true, "light": true, "climate": true, "shades": false}', 2),

('power', 'Solar / Inverter Failover', 'Autonomous Load-Shedding · Maitama Villa', 'Maitama, Abuja', 'PHCN grid collapse detected. Seamless sub-10ms inverter switchover. Non-essential high-inductive water heaters automatically shed.', 'SOLAR / INVERTER BRIDGE ENGAGED', '310 Watts', '22.5 °C', '4000 K', 'ARMED (BATTERY)', 'radial-gradient(circle at 40% 60%, rgba(26, 103, 68, 0.35), transparent 70%)', '{"lock": true, "light": true, "climate": false, "shades": true}', 3),

('turnover', 'Hospitality Turnover', 'Turnover Protocol · Jollof Short-Let', 'Lekki Phase 1, Lagos', 'Guest departure confirmed via lock sensor. Check-out PIN terminated. Climate switched to 26°C eco-standby. Cleaning task dispatched to host dashboard.', 'HOSPITALITY TURNOVER SYNC', '140 Watts', '26.0 °C', '5000 K', 'READY FOR GUEST', 'radial-gradient(circle at 50% 50%, rgba(27, 82, 136, 0.3), transparent 70%)', '{"lock": true, "light": false, "climate": false, "shades": false}', 4)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `automations_typologies`
(`slug`, `name`, `multiplier`, `typical_bedrooms`, `typical_sqm`, `recommended_package`, `display_order`)
VALUES
('apartment', 'Luxury Apartment (2–3 Beds)', 1.00, '2 to 3 Bedrooms', '180 – 260 sqm', 'Signature Comfort & Access', 1),
('duplex', 'Detached Duplex / Villa (4–5 Beds)', 1.85, '4 to 5 Bedrooms', '450 – 750 sqm', 'Executive Estate Autonomous', 2),
('shortlet', 'Short-Let Hospitality Suite', 1.25, '1 to 3 Bedrooms', '120 – 300 sqm', 'Jollof Hospitality Fleet Suite', 3),
('commercial', 'Commercial Office / Showroom', 2.40, 'Executive Floor', '600 – 1,200 sqm', 'Commercial Energy & Facility Control', 4)
ON DUPLICATE KEY UPDATE `multiplier` = VALUES(`multiplier`);

INSERT INTO `automations_case_studies`
(`slug`, `title`, `location`, `property_type`, `client_type`, `completion_year`, `highlight_metric`, `narrative`, `systems_installed_json`, `testimonial_quote`, `client_name`, `client_role`, `display_order`)
VALUES
('banana-island-penthouse', 'The Glasshouse Penthouse · Banana Island', 'Banana Island, Lagos', '650 sqm Duplex Penthouse', 'Private Principal Residence', 2025, '42% Reduction in Generator Fuel', 'Full retrofit of a double-height waterfront residence with 32-zone KNX circadian lighting, automated climate zoning, and dual inverter failover. Over 18 months, autonomous microgrid management reduced generator operational hours by 3.8 hours daily.', '["KNX Circadian Lighting", "Sub-10ms Solar Bridge", "Invisible Architectural Audio", "Motorized Drapery"]', 'The transition between NEPA and our solar system is now so imperceptible that we literally never know when grid power fails. The ambient lighting at sunset is magnificent.', 'Dr. Kunle Adeleke', 'Managing Director, Horizon Energy', 1),

('maitama-ambassadorial-villa', 'The Ambassadorial Residence · Maitama', 'Maitama, Abuja', '1,100 sqm Private Estate', 'Diplomatic & Executive Compound', 2025, 'Zero False Alarms in 14 Months', 'Turnkey installation of multi-spectrum thermal perimeter AI detection, access gates, and localized biometric locks for family and private security detail. Localized edge processing ensures zero camera feeds touch external public clouds.', '["AI Edge Perimeter Defense", "Biometric Access Control", "Diesel Generator IoT Telemetry", "Central Environmental Monitoring"]', 'Security in Abuja requires zero compromise. Jollof Automations engineered an on-premise system that gives our security team total situational awareness without leaking video to external clouds.', 'Engr. Ibrahim Bello', 'Chief Technical Advisor', 2),

('victoria-island-fleet', 'The VI Prime Short-Let Collection', 'Victoria Island, Lagos', '14 Luxury Short-Let Units', 'Hospitality Investment Portfolio', 2026, '18% Boost in Guest Review Scores', 'Complete fleet standardization across 14 serviced apartments managed on Jollof Living. Integrates automated guest PIN generation with reservation check-in and instantaneous HVAC eco-standby upon checkout.', '["Jollof Living PMS Integration", "Auto-Expiring Guest PINs", "HVAC Eco-Standby Automation", "Smart Leak & Flood Sensors"]', 'Our energy bills dropped by nearly ₦1.8M every month simply by preventing guests from leaving air conditioners blasting in empty rooms. The automated check-in experience is world-class.', 'Titi Alabi', 'Founder, Prime Stay Residences', 3)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `automations_settings`
(`setting_key`, `setting_value`, `setting_group`, `description`)
VALUES
('brand_name', 'Jollof Automations', 'brand', 'Subsidiary brand display name'),
('brand_tagline', 'Turn Your Residence Into an Intelligent Smart Home', 'brand', 'Brand marketing tagline'),
('parent_company', 'Jollof Living Limited', 'brand', 'Parent corporate entity name'),
('contact_email', 'automations@jollofliving.com', 'contact', 'Primary engineering inbox'),
('contact_phone', '+234 1 888 5655', 'contact', 'Engineering direct telephone line'),
('survey_fee_lagos', '0', 'pricing', 'Site audit survey fee for Lagos (Free promo)'),
('survey_fee_regional', '50000', 'pricing', 'Regional survey travel fee (Abuja, PHC, Ibadan)')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
