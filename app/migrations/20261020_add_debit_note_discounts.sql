-- Idempotent update for existing debit note installations.
SET @has_nd_total_discount = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'debit_notes'
    AND column_name = 'total_discount'
);
SET @nd_total_discount_sql = IF(
  @has_nd_total_discount = 0,
  'ALTER TABLE debit_notes ADD COLUMN total_discount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER subtotal_without_tax',
  'SELECT 1'
);
PREPARE nd_total_discount_stmt FROM @nd_total_discount_sql;
EXECUTE nd_total_discount_stmt;
DEALLOCATE PREPARE nd_total_discount_stmt;

SET @has_ndi_discount = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'debit_note_items'
    AND column_name = 'discount'
);
SET @ndi_discount_sql = IF(
  @has_ndi_discount = 0,
  'ALTER TABLE debit_note_items ADD COLUMN discount DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER tax',
  'SELECT 1'
);
PREPARE ndi_discount_stmt FROM @ndi_discount_sql;
EXECUTE ndi_discount_stmt;
DEALLOCATE PREPARE ndi_discount_stmt;
