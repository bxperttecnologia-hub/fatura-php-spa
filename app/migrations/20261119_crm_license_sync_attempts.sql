ALTER TABLE subscription_license_sync_events
  ADD COLUMN attempts int unsigned NOT NULL DEFAULT 1 AFTER outcome;
