<?php
/**
 * Jollof Automations — Data Models & Database Query Repository
 */
declare(strict_types=1);

final class AutoRepo
{
    /**
     * Fetch all active smart home services/solutions from MySQL.
     */
    public static function getServices(): array
    {
        if (AutoDB::isConnected() && AutoDB::tableExists('automations_services')) {
            $rows = AutoDB::all("SELECT * FROM `automations_services` WHERE `active` = 1 ORDER BY `display_order` ASC, `id` ASC");
            if (!empty($rows)) {
                return array_map(function($r) {
                    $r['features'] = !empty($r['features_json']) ? json_decode($r['features_json'], true) : [];
                    $r['hardware_specs'] = !empty($r['hardware_specs_json']) ? json_decode($r['hardware_specs_json'], true) : [];
                    return $r;
                }, $rows);
            }
        }

        // Default seed fallback
        return [
            [
                'id' => 1,
                'slug' => 'biometric-hospitality-access',
                'category' => 'Access & Security',
                'title' => 'Autonomous Biometric & Hospitality Access Control',
                'subtitle' => 'Touch-free biometric recognition with short-let reservation synchronization',
                'badge' => 'Flagship Module',
                'short_desc' => 'Replace traditional lock cylinders with enterprise-grade biometric locks, anti-peep keypads, and Apple Home Key NFC. For hospitality hosts, access codes sync directly with guest check-in/out schedules.',
                'features' => ['Sub-0.3s Optical Biometric Recognition', 'Automated Short-Let Checkout PIN Expiry', 'Apple Home Key & NFC Estate Badges', 'Real-Time Remote Tamper Telemetry'],
                'base_price_ngn' => 1850000,
            ],
            [
                'id' => 2,
                'slug' => 'circadian-architectural-lighting',
                'category' => 'Ambiance & Wellness',
                'title' => 'Human-Centric & Architectural Tunable Lighting',
                'subtitle' => '2,000K to 5,000K natural biorhythm tracking with solid brass wall keypads',
                'badge' => 'Circadian AI',
                'short_desc' => 'Lighting that aligns with the biological clock: crisp 5000K daylight white in the morning for peak cognitive focus, transitioning to 2000K candle amber at dusk to trigger natural melatonin production.',
                'features' => ['Natural Circadian Biorhythm Scheduling', 'Solid Brass In-Wall Tactile Keypads', 'Zero-Flicker Magnetic Track Dimming', 'Synchronized All-Off & Pathway Scenes'],
                'base_price_ngn' => 2400000,
            ],
            [
                'id' => 3,
                'slug' => 'smart-climate-environmental-ai',
                'category' => 'Climate & Comfort',
                'title' => 'Multi-Zone Smart Climate & Environmental AI',
                'subtitle' => 'Intelligent inverter regulation, indoor air quality monitoring, and humidity purging',
                'badge' => 'Energy Saver',
                'short_desc' => 'Centralized orchestration of multi-split inverter ACs. Detects room occupancy, ambient humidity, and harmful dust particulates, dynamically throttling fan speeds to eliminate overcooling and slash diesel burn.',
                'features' => ['Independent Multi-Zone Climate Mapping', 'Occupancy-Aware Automated Eco Standby', 'PM2.5 & Humidity Auto-Purge Ventilation', 'Jollof Living Guest Comfort Presets'],
                'base_price_ngn' => 1950000,
            ],
            [
                'id' => 4,
                'slug' => 'microgrid-generator-management',
                'category' => 'Power Resilience',
                'title' => 'Integrated Microgrid & Generator Management',
                'subtitle' => 'Zero-flicker UPS switchover, real-time telemetry, and automated load shedding',
                'badge' => 'Nigeria Critical',
                'short_desc' => 'Engineered specifically for Nigerian power realities. Seamlessly bridges PHCN grid power, solar arrays, lithium battery reserves, and diesel/petrol generators with sub-10ms switchover protection.',
                'features' => ['Sub-10ms Zero-Flicker Power Failover', 'Automated High-Inductive Load Shedding', 'Real-Time Diesel Reserve & Run-Hour IoT', 'Peak-Shaving Solar Microgrid Prioritization'],
                'base_price_ngn' => 3200000,
            ],
            [
                'id' => 5,
                'slug' => 'acoustic-sanctuary-audio',
                'category' => 'Entertainment',
                'title' => 'Acoustic Sanctuary & Multi-Room Audio',
                'subtitle' => 'Flush-mount architectural speakers with multi-zone AirPlay 2 & Spotify streaming',
                'badge' => 'High-Fidelity',
                'short_desc' => 'Studio-quality multi-room acoustics distributed invisibly through magnetic flush-mount ceiling and in-wall speakers. Cast high-fidelity playlists across terraces, living salons, and private master suites.',
                'features' => ['Magnetic Zero-Bezel Flush Architectural Speakers', 'Multi-Zone Synchronized High-Res Streaming', 'Apple AirPlay 2, Spotify Connect & Roon Ready', 'Automated Party & Cinematic Audio Scenes'],
                'base_price_ngn' => 2100000,
            ],
            [
                'id' => 6,
                'slug' => 'perimeter-defense-computer-vision',
                'category' => 'Security & Safety',
                'title' => 'Perimeter Defense & Proactive Computer Vision',
                'subtitle' => 'AI recognition distinguishing family, staff, and unauthorized boundary breaches',
                'badge' => 'Threat Shield',
                'short_desc' => 'Proactive security that does not sleep. Dual-spectrum thermal sensors and optical AI cameras detect fence line intrusions, instantly triggering focused perimeter floodlights and armed estate alerts.',
                'features' => ['AI Human & Vehicle Edge Classification', 'Dual-Spectrum Thermal Tripwire Radar', 'Automated Intrusion Floodlight Flashing', '24/7 Encrypted Local On-Premise NVR'],
                'base_price_ngn' => 2800000,
            ],
        ];
    }

    /**
     * Fetch all simulator scenes from MySQL.
     */
    public static function getSimulatorScenes(): array
    {
        if (AutoDB::isConnected() && AutoDB::tableExists('automations_simulator_scenes')) {
            $rows = AutoDB::all("SELECT * FROM `automations_simulator_scenes` ORDER BY `display_order` ASC, `id` ASC");
            if (!empty($rows)) {
                $scenes = [];
                foreach ($rows as $r) {
                    $key = $r['scene_key'];
                    $scenes[$key] = [
                        'title' => $r['title'],
                        'name' => $r['name'],
                        'desc' => $r['description'],
                        'tag' => $r['system_tag'],
                        'power' => $r['power_watts'],
                        'temp' => $r['temp_celsius'],
                        'kelvin' => $r['kelvin'],
                        'security' => $r['security_mode'],
                        'glow' => $r['glow_gradient'],
                        'devices' => !empty($r['devices_state_json']) ? json_decode($r['devices_state_json'], true) : []
                    ];
                }
                return $scenes;
            }
        }

        // Default simulator scenes
        return [
            'welcome' => [
                'title' => 'The Grand Salon · Ikoyi Penthouse',
                'name' => 'Arrive Home',
                'desc' => 'Biometric access recognized. Keyway locked. Motorized sheer drapes parted for skyline view. Dual inverter air conditioning maintains 21°C.',
                'tag' => 'ARRIVE HOME SCENE ACTIVE',
                'power' => '840 Watts',
                'temp' => '21.0 °C',
                'kelvin' => '2700 K',
                'security' => 'ARMED (STAY)',
                'glow' => 'radial-gradient(circle at 60% 40%, rgba(244, 236, 220, 0.45), transparent 70%)',
                'devices' => ['lock' => true, 'light' => true, 'climate' => true, 'shades' => true]
            ],
            'cinema' => [
                'title' => 'Private Screening Salon · Villa Eko',
                'name' => 'Cinema & Soirée',
                'desc' => 'Blackout shades lowered. Architectural downlights gently dimmed to 10% warm amber. Dolby Atmos receiver engaged with silent low-fan HVAC.',
                'tag' => 'CINEMA & SOIREE MODE ACTIVE',
                'power' => '620 Watts',
                'temp' => '19.5 °C',
                'kelvin' => '2000 K',
                'security' => 'PERIMETER LOCKED',
                'glow' => 'radial-gradient(circle at 50% 50%, rgba(194, 156, 75, 0.25), transparent 75%)',
                'devices' => ['lock' => true, 'light' => true, 'climate' => true, 'shades' => false]
            ],
            'power' => [
                'title' => 'Autonomous Load-Shedding · Maitama Villa',
                'name' => 'Solar / Inverter Failover',
                'desc' => 'PHCN grid collapse detected. Seamless sub-10ms inverter switchover. Non-essential high-inductive water heaters automatically shed.',
                'tag' => 'SOLAR / INVERTER BRIDGE ENGAGED',
                'power' => '310 Watts',
                'temp' => '22.5 °C',
                'kelvin' => '4000 K',
                'security' => 'ARMED (BATTERY)',
                'glow' => 'radial-gradient(circle at 40% 60%, rgba(26, 103, 68, 0.35), transparent 70%)',
                'devices' => ['lock' => true, 'light' => true, 'climate' => false, 'shades' => true]
            ],
            'turnover' => [
                'title' => 'Turnover Protocol · Jollof Short-Let',
                'name' => 'Hospitality Turnover',
                'desc' => 'Guest departure confirmed via lock sensor. Check-out PIN terminated. Climate switched to 26°C eco-standby. Cleaning task dispatched to host dashboard.',
                'tag' => 'HOSPITALITY TURNOVER SYNC',
                'power' => '140 Watts',
                'temp' => '26.0 °C',
                'kelvin' => '5000 K',
                'security' => 'READY FOR GUEST',
                'glow' => 'radial-gradient(circle at 50% 50%, rgba(27, 82, 136, 0.3), transparent 70%)',
                'devices' => ['lock' => true, 'light' => false, 'climate' => false, 'shades' => false]
            ],
        ];
    }

    /**
     * Fetch property typologies for the calculator.
     */
    public static function getTypologies(): array
    {
        if (AutoDB::isConnected() && AutoDB::tableExists('automations_typologies')) {
            $rows = AutoDB::all("SELECT * FROM `automations_typologies` ORDER BY `display_order` ASC, `id` ASC");
            if (!empty($rows)) {
                return $rows;
            }
        }

        return [
            ['slug' => 'apartment', 'name' => 'Luxury Apartment (2–3 Beds)', 'multiplier' => 1.00, 'typical_bedrooms' => '2–3 Beds', 'typical_sqm' => '200 sqm'],
            ['slug' => 'duplex', 'name' => 'Detached Duplex / Villa (4–5 Beds)', 'multiplier' => 1.85, 'typical_bedrooms' => '4–5 Beds', 'typical_sqm' => '550 sqm'],
            ['slug' => 'shortlet', 'name' => 'Short-Let Hospitality Suite', 'multiplier' => 1.25, 'typical_bedrooms' => '1–3 Beds', 'typical_sqm' => '220 sqm'],
            ['slug' => 'commercial', 'name' => 'Commercial Office / Showroom', 'multiplier' => 2.40, 'typical_bedrooms' => 'Floorplate', 'typical_sqm' => '800 sqm'],
        ];
    }

    /**
     * Fetch case studies.
     */
    public static function getCaseStudies(): array
    {
        if (AutoDB::isConnected() && AutoDB::tableExists('automations_case_studies')) {
            $rows = AutoDB::all("SELECT * FROM `automations_case_studies` ORDER BY `display_order` ASC, `id` ASC");
            if (!empty($rows)) {
                return array_map(function($r) {
                    $r['systems'] = !empty($r['systems_installed_json']) ? json_decode($r['systems_installed_json'], true) : [];
                    return $r;
                }, $rows);
            }
        }

        return [
            [
                'id' => 1,
                'slug' => 'banana-island-penthouse',
                'title' => 'The Glasshouse Penthouse · Banana Island',
                'location' => 'Banana Island, Lagos',
                'property_type' => '650 sqm Duplex Penthouse',
                'client_type' => 'Private Principal Residence',
                'highlight_metric' => '42% Reduction in Generator Fuel',
                'narrative' => 'Full retrofit of a double-height waterfront residence with 32-zone KNX circadian lighting, automated climate zoning, and dual inverter failover. Over 18 months, autonomous microgrid management reduced generator operational hours by 3.8 hours daily.',
                'systems' => ['KNX Circadian Lighting', 'Sub-10ms Solar Bridge', 'Invisible Architectural Audio', 'Motorized Drapery'],
                'testimonial_quote' => 'The transition between NEPA and our solar system is now so imperceptible that we literally never know when grid power fails. The ambient lighting at sunset is magnificent.',
                'client_name' => 'Dr. Kunle Adeleke',
                'client_role' => 'Managing Director, Horizon Energy',
            ],
            [
                'id' => 2,
                'slug' => 'maitama-ambassadorial-villa',
                'title' => 'The Ambassadorial Residence · Maitama',
                'location' => 'Maitama, Abuja',
                'property_type' => '1,100 sqm Private Estate',
                'client_type' => 'Diplomatic Compound',
                'highlight_metric' => 'Zero False Alarms in 14 Months',
                'narrative' => 'Turnkey installation of multi-spectrum thermal perimeter AI detection, access gates, and localized biometric locks for family and private security detail. Localized edge processing ensures zero camera feeds touch external public clouds.',
                'systems' => ['AI Edge Perimeter Defense', 'Biometric Access Control', 'Diesel Generator IoT Telemetry', 'Central Environmental Monitoring'],
                'testimonial_quote' => 'Security in Abuja requires zero compromise. Jollof Automations engineered an on-premise system that gives our security team total situational awareness without leaking video to external clouds.',
                'client_name' => 'Engr. Ibrahim Bello',
                'client_role' => 'Chief Technical Advisor',
            ],
            [
                'id' => 3,
                'slug' => 'victoria-island-fleet',
                'title' => 'The VI Prime Short-Let Collection',
                'location' => 'Victoria Island, Lagos',
                'property_type' => '14 Luxury Short-Let Units',
                'client_type' => 'Hospitality Fleet',
                'highlight_metric' => '18% Boost in Guest Review Scores',
                'narrative' => 'Complete fleet standardization across 14 serviced apartments managed on Jollof Living. Integrates automated guest PIN generation with reservation check-in and instantaneous HVAC eco-standby upon checkout.',
                'systems' => ['Jollof Living PMS Integration', 'Auto-Expiring Guest PINs', 'HVAC Eco-Standby Automation', 'Smart Leak & Flood Sensors'],
                'testimonial_quote' => 'Our energy bills dropped by nearly ₦1.8M every month simply by preventing guests from leaving air conditioners blasting in empty rooms. The automated check-in experience is world-class.',
                'client_name' => 'Titi Alabi',
                'client_role' => 'Founder, Prime Stay Residences',
            ],
        ];
    }

    /**
     * Store new site survey consultation lead in MySQL.
     */
    public static function createLead(array $data): array
    {
        $ref = 'JA-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $leadId = 0;

        $record = [
            'reference'        => $ref,
            'full_name'        => trim($data['name'] ?? ''),
            'email'            => strtolower(trim($data['email'] ?? '')),
            'phone'            => trim($data['phone'] ?? ''),
            'city'             => trim($data['city'] ?? 'Lagos'),
            'property_type'    => trim($data['property_type'] ?? 'Luxury Apartment'),
            'property_size'    => trim($data['property_size'] ?? '3 Bedrooms'),
            'estimated_budget' => trim($data['estimated_budget'] ?? '₦3,500,000'),
            'scope_json'       => json_encode($data['scope'] ?? []),
            'preferred_date'   => !empty($data['preferred_date']) ? date('Y-m-d', strtotime($data['preferred_date'])) : null,
            'client_notes'     => trim($data['notes'] ?? ''),
            'status'           => 'new',
            'ip_address'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ];

        if (AutoDB::isConnected()) {
            if (AutoDB::tableExists('automations_leads')) {
                $leadId = AutoDB::insert('automations_leads', $record);
            }
        }

        // Also append to local JSON storage for backup
        $jsonLog = JA_ROOT . '/storage/leads_backup.json';
        $existing = [];
        if (file_exists($jsonLog)) {
            $existing = json_decode((string) file_get_contents($jsonLog), true) ?: [];
        }
        $record['id'] = $leadId;
        $record['created_at'] = date('c');
        $existing[] = $record;
        @file_put_contents($jsonLog, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'id' => $leadId,
            'reference' => $ref,
            'data' => $record
        ];
    }

    /**
     * Fetch leads for the admin portal.
     */
    public static function getLeads(int $limit = 50): array
    {
        if (AutoDB::isConnected() && AutoDB::tableExists('automations_leads')) {
            return AutoDB::all("SELECT * FROM `automations_leads` ORDER BY `created_at` DESC LIMIT " . (int) $limit);
        }

        $jsonLog = JA_ROOT . '/storage/leads_backup.json';
        if (file_exists($jsonLog)) {
            return json_decode((string) file_get_contents($jsonLog), true) ?: [];
        }

        return [];
    }
}
