-- Notificações centralizadas + infraestrutura de push (app mobile Expo).
-- Migração aditiva e idempotente (MariaDB): não altera nem apaga dados existentes.

-- 1) notifications: link de destino, chave anti-duplicado e entidade relacionada.
ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS url varchar(255) DEFAULT NULL AFTER message,
  ADD COLUMN IF NOT EXISTS entity_type varchar(40) DEFAULT NULL AFTER url,
  ADD COLUMN IF NOT EXISTS entity_id int unsigned DEFAULT NULL AFTER entity_type,
  ADD COLUMN IF NOT EXISTS dedupe_key varchar(191) DEFAULT NULL AFTER entity_id,
  ADD UNIQUE KEY IF NOT EXISTS uq_notifications_dedupe (dedupe_key),
  ADD KEY IF NOT EXISTS idx_notifications_user_unread (user_id, is_read, created_at);

-- 2) Dispositivos móveis registados (um token Expo pertence a um só utilizador de cada vez).
CREATE TABLE IF NOT EXISTS push_devices (
  id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  company_id int DEFAULT NULL,
  expo_token varchar(255) NOT NULL,
  platform enum('ios','android') NOT NULL,
  device_name varchar(100) DEFAULT NULL,
  app_version varchar(20) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  last_seen_at datetime NOT NULL DEFAULT current_timestamp(),
  revoked_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_push_devices_token (expo_token),
  KEY idx_push_devices_user (user_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Fila de envio: o pedido HTTP só enfileira; o worker (cron) envia à Expo e lê os receipts.
CREATE TABLE IF NOT EXISTS push_queue (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  notification_id int unsigned DEFAULT NULL,
  device_id int unsigned NOT NULL,
  payload json NOT NULL,
  status enum('pending','sent','delivered','error') NOT NULL DEFAULT 'pending',
  ticket_id varchar(64) DEFAULT NULL,
  attempts tinyint unsigned NOT NULL DEFAULT 0,
  next_attempt_at datetime NOT NULL DEFAULT current_timestamp(),
  error varchar(255) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  sent_at datetime DEFAULT NULL,
  receipt_checked_at datetime DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_push_queue_pending (status, next_attempt_at),
  KEY idx_push_queue_receipts (status, sent_at),
  KEY idx_push_queue_notification (notification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Preferências por tipo (ausência de linha = usa o padrão do tipo).
CREATE TABLE IF NOT EXISTS notification_preferences (
  user_id int unsigned NOT NULL,
  type varchar(50) NOT NULL,
  push tinyint(1) NOT NULL DEFAULT 1,
  updated_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
