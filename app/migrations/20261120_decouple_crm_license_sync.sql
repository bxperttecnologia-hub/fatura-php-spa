-- ERP owns these statuses and the local snapshot; license_id is an external CRM identifier.
SET @subscription_schema = DATABASE();

SELECT COUNT(*) INTO @has_license_link_fk
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = @subscription_schema
  AND TABLE_NAME = 'subscription_license_links'
  AND CONSTRAINT_NAME = 'fk_subscription_license_link_license';
SET @migration_sql = IF(
  @has_license_link_fk > 0,
  'ALTER TABLE subscription_license_links DROP FOREIGN KEY fk_subscription_license_link_license',
  'SELECT 1'
);
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_plan_code
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_license_links' AND COLUMN_NAME = 'plan_code';
SET @migration_sql = IF(@has_plan_code = 0,
  'ALTER TABLE subscription_license_links ADD COLUMN plan_code varchar(40) NOT NULL DEFAULT '''' AFTER company_id',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_crm_subscription_id
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_license_links' AND COLUMN_NAME = 'crm_subscription_id';
SET @migration_sql = IF(@has_crm_subscription_id = 0,
  'ALTER TABLE subscription_license_links ADD COLUMN crm_subscription_id bigint unsigned DEFAULT NULL AFTER subscription_id',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_license_status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_license_links' AND COLUMN_NAME = 'license_status';
SET @migration_sql = IF(@has_license_status = 0,
  'ALTER TABLE subscription_license_links ADD COLUMN license_status varchar(20) NOT NULL DEFAULT ''pending'' AFTER plan_code',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_start_date
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_license_links' AND COLUMN_NAME = 'start_date';
SET @migration_sql = IF(@has_start_date = 0,
  'ALTER TABLE subscription_license_links ADD COLUMN start_date datetime DEFAULT NULL AFTER license_status',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_expires_at
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_license_links' AND COLUMN_NAME = 'expires_at';
SET @migration_sql = IF(@has_expires_at = 0,
  'ALTER TABLE subscription_license_links ADD COLUMN expires_at datetime DEFAULT NULL AFTER start_date',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_license_key_hash
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_license_links' AND COLUMN_NAME = 'license_key_hash';
SET @migration_sql = IF(@has_license_key_hash = 0,
  'ALTER TABLE subscription_license_links ADD COLUMN license_key_hash char(64) DEFAULT NULL AFTER expires_at',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

ALTER TABLE subscriptions
  MODIFY status enum('pending','awaiting_license','awaiting_sync','trial','active','suspended','expired','cancelled')
  NOT NULL DEFAULT 'pending';

SELECT COUNT(*) INTO @has_license_sync_version
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'license_sync_version';
SET @migration_sql = IF(@has_license_sync_version = 0,
  'ALTER TABLE subscriptions ADD COLUMN license_sync_version bigint unsigned NOT NULL DEFAULT 0 AFTER status',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_plan_id
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_orders' AND COLUMN_NAME = 'plan_id';
SET @migration_sql = IF(@has_plan_id = 0,
  'ALTER TABLE subscription_orders ADD COLUMN plan_id int(11) DEFAULT NULL AFTER plan_code',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_subscription_id
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_orders' AND COLUMN_NAME = 'subscription_id';
SET @migration_sql = IF(@has_subscription_id = 0,
  'ALTER TABLE subscription_orders ADD COLUMN subscription_id int(11) DEFAULT NULL AFTER plan_code',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_subscription_id_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_orders'
  AND INDEX_NAME = 'idx_subscription_orders_subscription';
SET @migration_sql = IF(@has_subscription_id_index = 0,
  'ALTER TABLE subscription_orders ADD KEY idx_subscription_orders_subscription (subscription_id)',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_subscription_fk
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_orders'
  AND CONSTRAINT_NAME = 'fk_subscription_orders_subscription';
SET @migration_sql = IF(@has_subscription_fk = 0,
  'ALTER TABLE subscription_orders ADD CONSTRAINT fk_subscription_orders_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions (id)',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_subscription_status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema AND TABLE_NAME = 'subscription_orders' AND COLUMN_NAME = 'subscription_status';
SET @migration_sql = IF(@has_subscription_status = 0,
  'ALTER TABLE subscription_orders ADD COLUMN subscription_status enum(''pending'',''awaiting_license'',''awaiting_sync'',''license_issued'',''sync_failed'',''license_rejected'',''active'',''suspended'',''expired'',''cancelled'') NOT NULL DEFAULT ''pending'' AFTER status',
  'SELECT 1');
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

ALTER TABLE subscription_orders
  MODIFY subscription_status enum('pending','awaiting_license','awaiting_sync','license_issued','sync_failed','license_rejected','active','suspended','expired','cancelled')
  NOT NULL DEFAULT 'pending';

ALTER TABLE subscription_license_sync_events
  MODIFY outcome enum('accepted','stale','rejected','failed') NOT NULL;

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
