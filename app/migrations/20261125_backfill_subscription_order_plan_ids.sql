-- Resolve existing ERP order plan_id only when canonical code, duration and amount agree.
-- The unique legacy_code/duration index guarantees at most one plan row.
UPDATE subscription_orders so
INNER JOIN plans p
  ON p.legacy_code = so.plan_code
 AND p.duration_months = so.period_months
 AND p.price = so.amount
SET so.plan_id = p.id
WHERE so.plan_id IS NULL;
