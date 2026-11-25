-- Migração incremental. Depois de aplicar, preencher plans.legacy_code com o mapeamento
-- aprovado por código e duração; não deduzir códigos a partir de nomes ou IDs.
SET @subscription_schema = DATABASE();

SELECT COUNT(*) INTO @has_plan_legacy_code
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_schema
  AND TABLE_NAME = 'plans'
  AND COLUMN_NAME = 'legacy_code';
SET @migration_sql = IF(
  @has_plan_legacy_code = 0,
  'ALTER TABLE plans ADD COLUMN legacy_code varchar(40) DEFAULT NULL AFTER name',
  'SELECT 1'
);
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_plan_code_duration_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @subscription_schema
  AND TABLE_NAME = 'plans'
  AND INDEX_NAME = 'uq_erp_plans_legacy_code_duration';
SET @migration_sql = IF(
  @has_plan_code_duration_index = 0,
  'ALTER TABLE plans ADD UNIQUE KEY uq_erp_plans_legacy_code_duration (legacy_code, duration_months)',
  'SELECT 1'
);
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

-- Só preenche pedidos cujo código e duração resolvem exactamente um plano aprovado.
UPDATE subscription_orders so
INNER JOIN plans p ON p.legacy_code = so.plan_code AND p.duration_months = so.period_months
SET so.plan_id = p.id
WHERE so.plan_id IS NULL;

SELECT COUNT(*) INTO @has_order_plan_fk
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = @subscription_schema
  AND TABLE_NAME = 'subscription_orders'
  AND CONSTRAINT_NAME = 'fk_subscription_orders_plan';
SET @migration_sql = IF(
  @has_order_plan_fk = 0,
  'ALTER TABLE subscription_orders ADD CONSTRAINT fk_subscription_orders_plan FOREIGN KEY (plan_id) REFERENCES plans (id)',
  'SELECT 1'
);
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;

SELECT COUNT(*) INTO @has_subscription_plan_fk
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = @subscription_schema
  AND TABLE_NAME = 'subscriptions'
  AND CONSTRAINT_NAME = 'fk_subscriptions_plan';
SET @migration_sql = IF(
  @has_subscription_plan_fk = 0,
  'ALTER TABLE subscriptions ADD CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES plans (id)',
  'SELECT 1'
);
PREPARE subscription_sync_stmt FROM @migration_sql;
EXECUTE subscription_sync_stmt;
DEALLOCATE PREPARE subscription_sync_stmt;
