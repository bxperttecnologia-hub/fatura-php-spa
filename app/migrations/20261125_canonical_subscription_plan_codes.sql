-- ERP/CRM canonical plan codes approved for BXPERT subscription integration.
-- Updates only integration identifiers; prices, durations and contract data are preserved.
UPDATE plans
SET legacy_code = CASE name
  WHEN 'BAZA' THEN 'BAZA'
  WHEN 'BASE' THEN 'BASE'
  WHEN 'XPERT' THEN 'XPERT'
  WHEN 'PRIME' THEN 'PRIME'
END
WHERE
  (name = 'BAZA' AND duration_months = 3 AND price = 19900.00)
  OR (name = 'BAZA' AND duration_months = 6 AND price = 35820.00)
  OR (name = 'BAZA' AND duration_months = 12 AND price = 67656.00)
  OR (name = 'BASE' AND duration_months = 3 AND price = 26900.00)
  OR (name = 'BASE' AND duration_months = 6 AND price = 48420.00)
  OR (name = 'BASE' AND duration_months = 12 AND price = 91464.00)
  OR (name = 'XPERT' AND duration_months = 3 AND price = 38900.00)
  OR (name = 'XPERT' AND duration_months = 6 AND price = 70020.00)
  OR (name = 'XPERT' AND duration_months = 12 AND price = 132264.00)
  OR (name = 'PRIME' AND duration_months = 3 AND price = 57900.00)
  OR (name = 'PRIME' AND duration_months = 6 AND price = 104220.00)
  OR (name = 'PRIME' AND duration_months = 12 AND price = 196860.00);

UPDATE subscription_orders
SET plan_code = CASE plan_code
  WHEN 'BXPERT_BAZA' THEN 'BAZA'
  WHEN 'BXPERT_BASE' THEN 'BASE'
  WHEN 'ENTERPRISE' THEN 'PRIME'
  ELSE plan_code
END
WHERE plan_code IN ('BXPERT_BAZA', 'BXPERT_BASE', 'ENTERPRISE');

UPDATE subscription_license_links
SET plan_code = CASE plan_code
  WHEN 'BXPERT_BAZA' THEN 'BAZA'
  WHEN 'BXPERT_BASE' THEN 'BASE'
  WHEN 'ENTERPRISE' THEN 'PRIME'
  ELSE plan_code
END
WHERE plan_code IN ('BXPERT_BAZA', 'BXPERT_BASE', 'ENTERPRISE');

UPDATE erp_subscription_orders
SET plan_code = CASE plan_code
  WHEN 'BXPERT_BAZA' THEN 'BAZA'
  WHEN 'BXPERT_BASE' THEN 'BASE'
  WHEN 'ENTERPRISE' THEN 'PRIME'
  ELSE plan_code
END
WHERE plan_code IN ('BXPERT_BAZA', 'BXPERT_BASE', 'ENTERPRISE');

UPDATE subscription_order_sync_outbox
SET payload = JSON_SET(
  payload,
  '$.plan_code',
  CASE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.plan_code'))
    WHEN 'BXPERT_BAZA' THEN 'BAZA'
    WHEN 'BXPERT_BASE' THEN 'BASE'
    WHEN 'ENTERPRISE' THEN 'PRIME'
    ELSE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.plan_code'))
  END
)
WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.plan_code'))
  IN ('BXPERT_BAZA', 'BXPERT_BASE', 'ENTERPRISE');
