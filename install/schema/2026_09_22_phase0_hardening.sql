-- Jollof Living — Phase-0 hardening (2026-09-22)
-- The same schema install/migrate.php applies through
-- ensure_phase0_hardening(), for people who prefer to run SQL directly in
-- phpMyAdmin. The PHP route is preferred and idempotent; these statements
-- are not — check the INFORMATION_SCHEMA first if unsure.

ALTER TABLE users
  ADD COLUMN auth_version INT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS feature_flags (
  flag_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
  enabled    TINYINT(1)   NOT NULL DEFAULT 0,
  note       VARCHAR(255) NULL,
  updated_at DATETIME     NULL
);

INSERT IGNORE INTO feature_flags (flag_key, enabled, note, updated_at) VALUES
  ('account.2fa',          0, 'Two-factor sign-in for member accounts — needs a working OTP channel (WP08).',      NOW()),
  ('account.documents',    0, 'Document vault for host KYC uploads — needs storage + a review queue (WP08).',      NOW()),
  ('account.data_rights',  0, 'Self-service NDPR export/erase — needs the erasure worker (WP08).',                 NOW()),
  ('loyalty.redeem',       0, 'Spending points against bookings — needs the rewards ledger (WP07).',               NOW()),
  ('channels.google_sync', 0, 'Google Calendar availability sync — needs live OAuth credentials (WP15).',          NOW()),
  ('channels.airbnb_sync', 0, 'OTA availability sync — needs partner API keys (WP15).',                            NOW()),
  ('smartlock.sync',       0, 'Keyless entry codes issued to locks — needs the lock integration (WP18).',          NOW()),
  ('payments.live_mode',   0, 'Charging and payouts through a live gateway — needs webhook verification (WP04).',  NOW());
