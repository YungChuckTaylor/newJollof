-- ============================================================================
--  Jollof Living — migration 2026_09_12
--  Dispute Resolution Centre  +  Live Chat module
-- ----------------------------------------------------------------------------
--  Safe to run more than once: every statement is idempotent.
--  Run it from cPanel → phpMyAdmin (Import), or in the browser at
--  /install/migrate.php  (recommended — it also reports what changed).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
--  DISPUTE RESOLUTION CENTRE
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dispute_categories` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sla_hours` int(11) NOT NULL DEFAULT '48',
  `icon` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dispute_cat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `disputes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `booking_ref` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_id` int(10) UNSIGNED DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `counterparty_id` int(10) UNSIGNED DEFAULT NULL,
  `against` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'host',
  `subject` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `desired_outcome` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'refund',
  `amount_claimed` int(11) NOT NULL DEFAULT '0',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `resolution_outcome` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution_note` text COLLATE utf8mb4_unicode_ci,
  `refund_amount` int(11) NOT NULL DEFAULT '0',
  `evidence_deadline` datetime DEFAULT NULL,
  `sla_due_at` datetime DEFAULT NULL,
  `first_response_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `satisfaction` tinyint(4) DEFAULT NULL,
  `satisfaction_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dispute_ref` (`ref`),
  KEY `idx_dispute_user` (`user_id`),
  KEY `idx_dispute_booking` (`booking_id`),
  KEY `idx_dispute_status` (`status`),
  KEY `idx_dispute_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dispute_events` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dispute_id` int(10) UNSIGNED NOT NULL,
  `actor_id` int(10) UNSIGNED DEFAULT NULL,
  `actor_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `actor_name` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kind` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'message',
  `body` text COLLATE utf8mb4_unicode_ci,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visibility` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_devent_dispute` (`dispute_id`),
  KEY `idx_devent_kind` (`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `dispute_categories` (`slug`, `name`, `description`, `sla_hours`, `icon`, `sort_order`) VALUES
('booking-issue',    'Booking or date problem',      'Wrong dates, cancelled stay, host could not accommodate you.', 24, 'calendar', 0),
('refund-request',   'Refund request',               'You cancelled, or the stay did not match the listing.',        48, 'wallet',   1),
('property-quality', 'Property not as described',    'Condition, cleanliness, amenities or photos differ.',          48, 'home',     2),
('payment-billing',  'Payment, invoice or billing',  'Charged twice, unexpected fees, tax or invoice queries.',      24, 'wallet',   3),
('host-conduct',     'Host conduct',                 'Communication, access, privacy or policy breaches.',           24, 'building', 4),
('guest-conduct',    'Guest conduct (hosts)',        'Property damage, rule breaches or unpaid balances.',           48, 'users',    5),
('safety-security',  'Safety or security',           'A safety incident, lock or security concern.',                 4,  'shield',   6),
('other',            'Something else',               'Anything that does not fit the categories above.',             72, 'scale',    7);

-- ----------------------------------------------------------------------------
--  LIVE CHAT
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `chat_departments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `routing` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fewest',
  `welcome_message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_hours` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_dept_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `chat_departments` (`slug`, `name`, `description`, `routing`, `welcome_message`, `business_hours`, `sort_order`) VALUES
('guest-support', 'Guest support',      'Day-to-day questions from guests and travellers.',        'fewest',       'Welcome to Jollof Living — a guest support specialist will be with you shortly.', '24/7', 0),
('reservations',  'Reservations desk',  'New bookings, modifications and availability.',           'round_robin',  'Hello! This is the reservations desk — tell us your dates and we will find the perfect residence.', '08:00 – 22:00 WAT', 1),
('host-support',  'Host support',       'Help for property owners on listings, calendar and payouts.', 'fewest',    'Welcome back! A host support specialist will pick up your chat shortly.', '08:00 – 20:00 WAT', 2),
('payments',      'Payments & billing', 'Invoices, escrow, refunds and payment issues.',           'fewest',       'Thanks for reaching out about payments — an agent will be with you in a moment.', '08:00 – 20:00 WAT', 3),
('trust-safety',  'Trust & safety',     'Disputes, safety incidents and account security.',        'manual',       'This channel is monitored by our trust & safety team. If this is an emergency, please call our 24/7 line.', '24/7', 4);

CREATE TABLE IF NOT EXISTS `chat_agents` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agent_role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'agent',
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'offline',
  `max_chats` int(11) NOT NULL DEFAULT '3',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `last_assigned_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_agent_email` (`email`),
  KEY `idx_chat_agent_user` (`user_id`),
  KEY `idx_chat_agent_dept` (`department_id`),
  KEY `idx_chat_agent_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_agent_departments` (
  `agent_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`agent_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_sessions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `visitor_id` int(10) UNSIGNED DEFAULT NULL,
  `visitor_name` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visitor_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visitor_phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visitor_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `agent_id` int(10) UNSIGNED DEFAULT NULL,
  `transferred_from` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `subject` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visitor_typing_at` datetime DEFAULT NULL,
  `agent_typing_at` datetime DEFAULT NULL,
  `first_response_at` datetime DEFAULT NULL,
  `agent_joined_at` datetime DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `close_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `rating_comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unread_agent` int(11) NOT NULL DEFAULT '0',
  `unread_visitor` int(11) NOT NULL DEFAULT '0',
  `wait_seconds` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_ref` (`ref`),
  KEY `idx_chat_status` (`status`),
  KEY `idx_chat_agent` (`agent_id`),
  KEY `idx_chat_visitor` (`visitor_id`),
  KEY `idx_chat_dept` (`department_id`),
  KEY `idx_chat_last` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` int(10) UNSIGNED NOT NULL,
  `sender` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'visitor',
  `agent_id` int(10) UNSIGNED DEFAULT NULL,
  `author_name` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_flag` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chatmsg_session` (`session_id`),
  KEY `idx_chatmsg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_events` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` int(10) UNSIGNED NOT NULL,
  `kind` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `actor_id` int(10) UNSIGNED DEFAULT NULL,
  `actor_name` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chatevent_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_canned` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `shortcut` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'global',
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `agent_id` int(10) UNSIGNED DEFAULT NULL,
  `uses` int(11) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_canned_shortcut` (`shortcut`),
  KEY `idx_canned_scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `chat_canned` (`shortcut`, `title`, `body`, `scope`, `sort_order`) VALUES
('/greet',    'Warm greeting',        'Hello and welcome to Jollof Living ✨ My name is {agent} — how can I help you today?', 'global', 0),
('/hold',     'Please hold on',       'Give me one moment while I check that for you — I will not leave you hanging.', 'global', 1),
('/escrow',   'How escrow works',     'Your payment is held securely in escrow and released to the host only after you confirm check-in. If anything is not as described, our dispute team steps in.', 'global', 2),
('/cancel',   'Cancellation tiers',   'Flexible: full refund up to 48h before check-in. Moderate: full refund up to 5 days, then 50%. Strict: 50% up to 14 days. Your reservation page shows which applies.', 'global', 3),
('/transfer', 'Arrange a transfer',   'I can arrange a chauffeured airport transfer — our driver meets you inside arrivals with a name board. May I have your flight number and arrival time?', 'global', 4),
('/dispute',  'Open a dispute',       'I am sorry this has happened. I can open a formal dispute for you: it goes straight to our resolution centre with a named mediator and a target of 48 hours. Shall I raise it now?', 'global', 5),
('/bye',      'Polite close',         'Thank you for chatting with Jollof Living. I will email you a transcript of this conversation — enjoy the rest of your day!', 'global', 6);

-- The agent console gets its own page metadata so the header/title resolve.
-- `settings` and `page_meta` carry no UNIQUE key in the base schema, so
-- INSERT IGNORE cannot deduplicate them: both seeds check first instead, which
-- makes them safe to run any number of times.
INSERT INTO `page_meta` (`page_key`, `title`, `description`)
SELECT 'agent', 'Live chat desk | Jollof Living',
       'Work the live chat queues — reply to guests, claim and transfer chats, and keep the desk running.'
  FROM (SELECT 1) AS `one`
  LEFT JOIN `page_meta` AS `existing` ON `existing`.`page_key` = 'agent'
 WHERE `existing`.`page_key` IS NULL;

-- Settings used by the live chat widget and console.
INSERT INTO `settings` (`skey`, `svalue`)
SELECT `seed`.`k`, `seed`.`v`
  FROM (
    SELECT 'chat_enabled'        AS k, '1' AS v UNION ALL
    SELECT 'chat_welcome', 'Welcome to Jollof Living 👋 Chat with our team about stays, bookings, payments or hosting. A specialist replies in about a minute.' UNION ALL
    SELECT 'chat_offline', 'Our team is offline right now. Leave your question here and we will reply by email — or ask Jollof, our AI concierge, for an instant answer.' UNION ALL
    SELECT 'chat_routing', 'fewest' UNION ALL
    SELECT 'chat_max_queue', '25' UNION ALL
    SELECT 'chat_auto_close', '30' UNION ALL
    SELECT 'chat_rating_enabled', '1' UNION ALL
    SELECT 'chat_hours', '24/7 support · median first reply under 3 minutes' UNION ALL
    SELECT 'chat_transcript', '1'
  ) AS `seed`
  LEFT JOIN `settings` AS `existing` ON `existing`.`skey` = `seed`.`k`
 WHERE `existing`.`skey` IS NULL;

-- The platform administrator doubles as the first chat agent so the console
-- is never empty on a fresh install. Safe to run repeatedly.
INSERT IGNORE INTO `chat_agents` (`user_id`, `name`, `email`, `agent_role`, `status`, `max_chats`, `is_active`, `signature`)
SELECT u.`id`, u.`name`, u.`email`, 'supervisor', 'offline', 5, 1, 'Jollof Living support'
  FROM `users` u
  LEFT JOIN `chat_agents` a ON a.`user_id` = u.`id` OR a.`email` = u.`email`
 WHERE u.`role` = 'admin'
   AND a.`id` IS NULL
 ORDER BY u.`id`
 LIMIT 1;
