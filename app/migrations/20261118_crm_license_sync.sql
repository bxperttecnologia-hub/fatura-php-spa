-- Aplicar depois de existirem as tabelas ERP companies, subscriptions e subscription_orders.
ALTER TABLE companies
  MODIFY plan_status enum('pending','awaiting_license','awaiting_sync','active','suspended','expired','cancelled')
  DEFAULT 'active';

CREATE TABLE subscription_license_links (
  license_id bigint unsigned NOT NULL,
  subscription_id int(11) NOT NULL,
  crm_subscription_id bigint unsigned DEFAULT NULL,
  source_order_id int(11) DEFAULT NULL,
  company_id int(11) NOT NULL,
  plan_code varchar(40) NOT NULL,
  license_status varchar(20) NOT NULL,
  start_date datetime DEFAULT NULL,
  expires_at datetime NOT NULL,
  license_key_hash char(64) NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (license_id),
  UNIQUE KEY uq_subscription_license_order (source_order_id),
  KEY idx_subscription_license_subscription (subscription_id),
  KEY idx_subscription_license_company (company_id),
  CONSTRAINT fk_subscription_license_link_subscription
    FOREIGN KEY (subscription_id) REFERENCES subscriptions (id),
  CONSTRAINT fk_subscription_license_link_order
    FOREIGN KEY (source_order_id) REFERENCES subscription_orders (id),
  CONSTRAINT fk_subscription_license_link_company
    FOREIGN KEY (company_id) REFERENCES companies (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_license_sync_events (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  event_id char(36) NOT NULL,
  payload_hash char(64) NOT NULL,
  license_id bigint unsigned DEFAULT NULL,
  source_order_id int(11) DEFAULT NULL,
  sync_version bigint unsigned DEFAULT NULL,
  outcome enum('accepted','stale','rejected') NOT NULL,
  reason varchar(255) DEFAULT NULL,
  received_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscription_license_event (event_id),
  KEY idx_subscription_license_event_license (license_id, received_at),
  KEY idx_subscription_license_event_order (source_order_id, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
