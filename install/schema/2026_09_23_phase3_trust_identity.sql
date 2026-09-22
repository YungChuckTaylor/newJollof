-- ============================================================================
--  Jollof Living — Phase 3: Trust & Identity (WP12-WP16)
--  Verification tokens, KYC documents, user preferences, safety reports & blocks
-- ----------------------------------------------------------------------------
--  Safe to run more than once: every statement is idempotent.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
--  1. VERIFICATION TOKENS (WP12)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `verify_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `purpose` varchar(32) NOT NULL, -- email, phone, password_reset
  `token_hash` char(64) NOT NULL,
  `code_hash` char(64) DEFAULT NULL,
  `attempts` tinyint(3) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_verify_token_hash` (`token_hash`),
  KEY `ix_verify_user_purpose` (`user_id`, `purpose`),
  KEY `ix_verify_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  2. KYC DOCUMENTS (WP14)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kyc_documents` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `kind` varchar(32) NOT NULL, -- gov_id, proof_of_address, selfie, business_reg
  `storage_key` varchar(255) NOT NULL,
  `mime` varchar(64) NOT NULL,
  `bytes` int(10) UNSIGNED NOT NULL,
  `sha256` char(64) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'uploaded', -- uploaded, in_review, verified, rejected
  `review_note` text DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_kyc_user` (`user_id`),
  KEY `ix_kyc_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  3. USER PREFERENCES (WP15)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_prefs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `pref_key` varchar(64) NOT NULL,
  `pref_val` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_pref_key` (`user_id`, `pref_key`),
  KEY `ix_user_prefs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  4. COMMUNITY SAFETY REPORTS & BLOCKS (WP16)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `safety_reports` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` int(10) UNSIGNED NOT NULL,
  `target_type` varchar(32) NOT NULL, -- user, listing, review, message
  `target_id` varchar(64) NOT NULL,
  `category` varchar(64) NOT NULL,
  `detail` text NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'new', -- new, reviewing, actioned, dismissed
  `assigned_admin` int(10) UNSIGNED DEFAULT NULL,
  `resolution_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_report_reporter` (`reporter_id`),
  KEY `ix_report_target` (`target_type`, `target_id`),
  KEY `ix_report_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_blocks` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `blocked_user_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_block_pair` (`user_id`, `blocked_user_id`),
  KEY `ix_block_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
