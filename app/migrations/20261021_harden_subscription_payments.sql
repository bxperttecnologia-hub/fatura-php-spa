-- Additive, idempotent migration for AppyPay subscription charges
-- Empty transaction IDs are unset values and are normalized before adding uniqueness
UPDATE subscription_orders
SET merchant_transaction_id = NULL
WHERE merchant_transaction_id = '';

SET @subscription_orders_schema = DATABASE();

SELECT COUNT(*) INTO @subscription_orders_operation_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_orders_schema
  AND TABLE_NAME = 'subscription_orders'
  AND COLUMN_NAME = 'operation';

SET @subscription_orders_sql = IF(
    @subscription_orders_operation_exists = 0,
    'ALTER TABLE subscription_orders ADD COLUMN operation VARCHAR(20) NOT NULL DEFAULT ''legacy'' AFTER plan_code',
    'SET @subscription_orders_noop = 1'
);
PREPARE subscription_orders_stmt FROM @subscription_orders_sql;
EXECUTE subscription_orders_stmt;
DEALLOCATE PREPARE subscription_orders_stmt;

SELECT COUNT(*) INTO @subscription_orders_creator_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @subscription_orders_schema
  AND TABLE_NAME = 'subscription_orders'
  AND COLUMN_NAME = 'created_by_user_id';

SET @subscription_orders_sql = IF(
    @subscription_orders_creator_exists = 0,
    'ALTER TABLE subscription_orders ADD COLUMN created_by_user_id INT NULL AFTER company_id',
    'SET @subscription_orders_noop = 1'
);
PREPARE subscription_orders_stmt FROM @subscription_orders_sql;
EXECUTE subscription_orders_stmt;
DEALLOCATE PREPARE subscription_orders_stmt;

SELECT COUNT(*) INTO @subscription_orders_index_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @subscription_orders_schema
  AND TABLE_NAME = 'subscription_orders'
  AND INDEX_NAME = 'uq_subscription_orders_merchant_transaction';

SET @subscription_orders_sql = IF(
    @subscription_orders_index_exists = 0,
    'ALTER TABLE subscription_orders ADD UNIQUE INDEX uq_subscription_orders_merchant_transaction (merchant_transaction_id)',
    'SET @subscription_orders_noop = 1'
);
PREPARE subscription_orders_stmt FROM @subscription_orders_sql;
EXECUTE subscription_orders_stmt;
DEALLOCATE PREPARE subscription_orders_stmt;
