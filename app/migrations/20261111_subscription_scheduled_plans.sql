-- Planos inferiores pagos ficam "por activar" até o plano actual terminar.
-- activated_at: quando o plano do pedido passou a valer (NULL = ainda por activar).
ALTER TABLE subscription_orders ADD COLUMN activated_at DATETIME NULL DEFAULT NULL AFTER paid_at;
UPDATE subscription_orders SET activated_at = paid_at WHERE status = 'paid' AND activated_at IS NULL;
