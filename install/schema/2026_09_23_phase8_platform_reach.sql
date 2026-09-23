-- ============================================================================
--  Jollof Living — Phase 8: Platform Reach & Polish (WP32-WP37)
--  Leads management, push subscriptions, and business services
-- ----------------------------------------------------------------------------
--  Safe to run more than once: every statement is idempotent.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
--  1. BUSINESS LEADS (WP36)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `business_leads` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `company_name` varchar(160) NOT NULL,
  `contact_name` varchar(120) NOT NULL,
  `contact_email` varchar(190) NOT NULL,
  `contact_phone` varchar(40) DEFAULT NULL,
  `team_size` varchar(32) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'new', -- new, contacted, scheduled, won, lost
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_lead_email` (`contact_email`),
  KEY `ix_lead_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  2. WEB PUSH SUBSCRIPTIONS (WP34/WP35)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_push_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
