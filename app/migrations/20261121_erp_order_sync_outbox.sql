CREATE TABLE IF NOT EXISTS subscription_order_sync_outbox (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  event_id char(36) NOT NULL,
  event_type varchar(40) NOT NULL,
  order_id int(11) NOT NULL,
  payload json NOT NULL,
  attempts int unsigned NOT NULL DEFAULT 0,
  next_attempt_at datetime NOT NULL,
  last_attempt_at datetime DEFAULT NULL,
  last_error varchar(1000) DEFAULT NULL,
  created_at datetime NOT NULL,
  delivered_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscription_order_sync_event (event_id),
  UNIQUE KEY uq_subscription_order_sync_order (order_id, event_type),
  KEY idx_subscription_order_sync_delivery (delivered_at, next_attempt_at, id),
  CONSTRAINT fk_subscription_order_sync_order FOREIGN KEY (order_id) REFERENCES subscription_orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO subscription_order_sync_outbox
  (event_id, event_type, order_id, payload, next_attempt_at, created_at)
SELECT UUID(), 'subscription_order.paid', so.id,
       JSON_OBJECT(
         'event_type', 'subscription_order.paid',
         'order_id', so.id,
         'order_version', 1,
         'company_id', so.company_id,
         'plan_code', so.plan_code,
         'operation', so.operation,
         'crm_subscription_id', (
           SELECT l.crm_subscription_id FROM subscription_license_links l
           WHERE l.subscription_id = so.subscription_id AND l.company_id = so.company_id
           ORDER BY l.updated_at DESC LIMIT 1
         ),
         'period_months', so.period_months,
         'amount', so.amount,
         'customer_email', u.email,
         'paid_at', DATE_FORMAT(so.paid_at, '%Y-%m-%d %H:%i:%s'),
         'created_at', DATE_FORMAT(so.created_at, '%Y-%m-%d %H:%i:%s')
       ),
       UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM subscription_orders so
LEFT JOIN users u ON u.id = so.created_by_user_id
WHERE so.status = 'paid'
  AND so.operation IN ('new','renewal','upgrade','downgrade')
  AND so.subscription_status IN ('awaiting_license','awaiting_sync','license_issued','sync_failed','license_rejected');
