CREATE TABLE IF NOT EXISTS subscription_license_sync_attempts (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  event_id char(36) NOT NULL,
  payload_hash char(64) NOT NULL,
  outcome enum('accepted','rejected','failed') NOT NULL,
  reason varchar(255) DEFAULT NULL,
  attempted_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY idx_subscription_license_attempt_event (event_id, attempted_at),
  KEY idx_subscription_license_attempt_result (outcome, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
