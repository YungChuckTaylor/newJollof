-- ============================================================================
--  Jollof Living — Phase 7: Support Modules Finished (WP28-WP31)
--  Dispute case shareable tokens and per-party dispute ratings
-- ----------------------------------------------------------------------------
--  Safe to run more than once: every statement is idempotent.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
--  1. DISPUTE CASE SHAREABLE TOKENS (WP30.1)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dispute_case_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dispute_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `issued_to_email` varchar(190) DEFAULT NULL,
  `single_use` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_case_token` (`token`),
  KEY `ix_token_dispute` (`dispute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  2. DISPUTE MULTI-PARTY RATINGS (WP30.2)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dispute_ratings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dispute_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'guest', -- guest, host
  `stars` tinyint(4) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dispute_user_rating` (`dispute_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
