-- ============================================================================
--  Jollof Living — Phase 5: Owner Workspace & Operations (WP23-WP26)
--  Listing drafts, host team invites, template automation, payouts & channel feeds
-- ----------------------------------------------------------------------------
--  Safe to run more than once: every statement is idempotent.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
--  1. LISTING DRAFTS (WP23)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `listing_drafts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `listing_id` int(10) UNSIGNED DEFAULT NULL,
  `payload` text NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_draft_user` (`user_id`),
  KEY `ix_draft_listing` (`listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  2. HOST TEAM & CO-HOST INVITATIONS (WP24)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `host_invitations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `host_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `capability` varchar(64) NOT NULL DEFAULT 'cohost_manage',
  `token` varchar(64) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_host_inv_token` (`token`),
  KEY `ix_host_inv_host` (`host_id`),
  KEY `ix_host_inv_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  3. AUTOMATED MESSAGE SENDS (WP24)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `template_sends` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` int(10) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_template_booking` (`template_id`, `booking_id`),
  KEY `ix_tmpl_send_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  4. SERVICE REQUESTS & VENDOR BOARD (WP26)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `host_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED DEFAULT NULL,
  `kind` varchar(32) NOT NULL, -- inspection, photo_shoot, tour
  `preferred_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'requested', -- requested, scheduled, done, declined
  `assigned_vendor` varchar(120) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_srv_host` (`host_id`),
  KEY `ix_srv_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
