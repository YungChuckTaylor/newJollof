-- ============================================================================
--  Jollof Living — Phase 6: Back Office Completeness (WP27)
--  CMS blocks, fraud signals, audit integrity & review eligibility
-- ----------------------------------------------------------------------------
--  Safe to run more than once: every statement is idempotent.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
--  1. CMS BLOCKS (WP27.5)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cms_blocks` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `block_key` varchar(64) NOT NULL,
  `title` varchar(140) NOT NULL,
  `content` text NOT NULL,
  `draft` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'published', -- draft, published
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cms_block_key` (`block_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  2. FRAUD SIGNALS & RULES (WP27.6)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fraud_signals` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_key` varchar(64) NOT NULL,
  `subject_type` varchar(32) NOT NULL, -- booking, user, gift_card, dispute
  `subject_id` varchar(64) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `evidence` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_signal_rule` (`rule_key`),
  KEY `ix_signal_subject` (`subject_type`, `subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  3. SEED CORE CMS BLOCKS (IDEMPOTENT)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `cms_blocks` (`block_key`, `title`, `content`) VALUES
('home_hero', 'Hero Banner Copy', 'Luxury residences with hotel hospitality across West Africa.'),
('trust_promise', 'Trust & Escrow Guarantee', '100% escrow protection on every reservation until verified check-out.'),
('faq_cancellation', 'Cancellation Policy FAQ', 'Flexible, moderate, and strict tiers clearly shown before payment.');
