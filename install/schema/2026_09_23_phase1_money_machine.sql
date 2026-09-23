-- ============================================================================
--  Jollof Living — Phase 1: The Money Machine (WP04-WP07)
--  Payment Intents, Double-Entry Ledger, Installment Plans, Gift Cards
-- ----------------------------------------------------------------------------
--  Safe to run more than once: every statement is idempotent.
--  Dialect: MySQL 5.7+ / MariaDB 10.3+ / SQLite (via install/migrate.php)
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
--  1. PAYMENT INTENTS (WP04)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_intents` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `kind` varchar(32) NOT NULL DEFAULT 'booking',
  `amount_fen` bigint(20) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'NGN',
  `provider` varchar(32) NOT NULL DEFAULT 'sandbox',
  `provider_ref` varchar(191) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'initiated',
  `idempotency_key` char(64) NOT NULL,
  `payload_hash` char(64) DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_intent_idem` (`idempotency_key`),
  KEY `ix_intent_booking` (`booking_id`),
  KEY `ix_intent_user` (`user_id`),
  KEY `ix_intent_provref` (`provider`, `provider_ref`),
  KEY `ix_intent_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  2. DOUBLE-ENTRY LEDGER (WP05)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ledger_accounts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'NGN',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ledger_acct_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ledger_entries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_key` varchar(64) NOT NULL,
  `booking_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `direction` varchar(10) NOT NULL,
  `amount_fen` bigint(20) NOT NULL,
  `ref_type` varchar(32) NOT NULL,
  `ref_id` varchar(64) NOT NULL,
  `idempotency_key` char(64) NOT NULL,
  `reverses_entry_id` bigint(20) UNSIGNED DEFAULT NULL,
  `narration` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ledger_idem` (`idempotency_key`),
  KEY `ix_ledger_acct` (`account_key`),
  KEY `ix_ledger_booking` (`booking_id`),
  KEY `ix_ledger_user` (`user_id`),
  KEY `ix_ledger_ref` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  3. INSTALLMENT PLANS (WP06)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `installment_plans` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `tranche_index` int(11) NOT NULL DEFAULT 1,
  `total_tranches` int(11) NOT NULL DEFAULT 2,
  `due_date` date NOT NULL,
  `amount_fen` bigint(20) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `payment_intent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inst_booking_tranche` (`booking_id`, `tranche_index`),
  KEY `ix_inst_booking` (`booking_id`),
  KEY `ix_inst_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  4. ASYNCHRONOUS OUTBOX & EVENT BUS (WP04/WP05/WP06/WP07)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `outbox_events` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` varchar(64) NOT NULL,
  `payload` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `run_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lease_until` datetime DEFAULT NULL,
  `dedupe_key` varchar(128) NOT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_outbox_dedupe` (`dedupe_key`),
  KEY `ix_outbox_status_run` (`status`, `run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  5. SEED STANDARD LEDGER ACCOUNTS (IDEMPOTENT)
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `ledger_accounts` (`key_name`, `name`, `currency`) VALUES
('guest_cash',       'Guest Cash Inflow',        'NGN'),
('escrow',           'Guest Escrow Liability',   'NGN'),
('host_earnings',    'Host Payable',             'NGN'),
('platform_fee',     'Platform Revenue',         'NGN'),
('gift_liability',   'Gift Card Liability',      'NGN'),
('loyalty_redeemed', 'Loyalty Liability',        'NGN'),
('refund_out',       'Refund Disbursed Clearing','NGN');
