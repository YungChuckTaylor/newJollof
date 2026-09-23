-- ============================================================================
--  Jollof Living — Phase 2: Booking Integrity (WP08-WP11)
--  Night holds, booking modifications, and cancellation/change models
-- ----------------------------------------------------------------------------
--  Safe to run more than once: every statement is idempotent.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
--  1. NIGHT HOLDS (WP09) - Hard Concurrency Guard
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `night_holds` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `property_id` int(10) UNSIGNED NOT NULL,
  `stay_date` date NOT NULL,
  `booking_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_night_prop_date` (`property_id`, `stay_date`, `status`),
  KEY `ix_night_booking` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  2. BOOKING CHANGES (WP10) - Modification Workflow
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `booking_changes` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `proposed_checkin` date NOT NULL,
  `proposed_checkout` date NOT NULL,
  `proposed_guests` int(11) NOT NULL,
  `delta_quote` text NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending_host',
  `note` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_change_booking` (`booking_id`),
  KEY `ix_change_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
