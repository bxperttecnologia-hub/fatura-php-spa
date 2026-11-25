<?php

declare(strict_types=1);

namespace Cron\Checks;

use Cron\NotificationRepository;

class SubscriptionCheck
{
    public function __construct(
        private \PDO $pdo,
        private NotificationRepository $repo,
        private int $expiringDays,
        private int $staleOrderHours
    ) {}

    public function run(): void
    {
        $this->checkExpiringSubscriptions();
        $this->transitionExpiredSubscriptions();
        $this->checkStalePendingOrders();
    }

    /** Subscrição ativa cujo end_date está a X dias de terminar. */
    private function checkExpiringSubscriptions(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT s.id, s.company_id, s.end_date, p.name AS plan_name
            FROM subscriptions s
            LEFT JOIN plans p ON p.id = s.plan_id
            WHERE s.status = 'active'
              AND s.end_date <= DATE_ADD(NOW(), INTERVAL :days DAY)
              AND s.end_date > NOW()
        ");
        $stmt->execute([':days' => $this->expiringDays]);

        foreach ($stmt as $sub) {
            foreach ($this->ownerEmails((int)$sub['company_id']) as $owner) {
                $this->repo->notifyOnce(
                    (int)$sub['company_id'],
                    (int)$owner['id'],
                    'subscription_expiring',
                    'subscription',
                    (int)$sub['id'],
                    "subscription_expiring:{$sub['id']}",
                    $owner['email'],
                    'A tua subscrição está a terminar',
                    "O plano {$sub['plan_name']} termina em "
                        . (new \DateTimeImmutable($sub['end_date']))->format('d/m/Y')
                        . ". Renova para evitar interrupção do serviço."
                );
            }
        }
    }

    /** Marca como 'expired' subscrições e planos de empresa cujo prazo já passou. */
    private function transitionExpiredSubscriptions(): void
    {
        $expired = $this->pdo->query("
            SELECT id, company_id FROM subscriptions
            WHERE status = 'active' AND end_date <= NOW()
        ")->fetchAll();

        foreach ($expired as $sub) {
            $this->pdo->prepare("UPDATE subscriptions SET status = 'expired' WHERE id = :id")
                ->execute([':id' => $sub['id']]);

            foreach ($this->ownerEmails((int)$sub['company_id']) as $owner) {
                $this->repo->notifyOnce(
                    (int)$sub['company_id'],
                    (int)$owner['id'],
                    'subscription_expired',
                    'subscription',
                    (int)$sub['id'],
                    "subscription_expired:{$sub['id']}",
                    $owner['email'],
                    'A tua subscrição expirou',
                    'A tua subscrição expirou. Renova o plano para continuares a emitir documentos.'
                );
            }
        }

        // Espelha o mesmo estado em companies.plan_status (usado noutras partes do sistema)
        $this->pdo->exec("
            UPDATE companies
            SET plan_status = 'expired'
            WHERE plan_status = 'active' AND plan_expires_at IS NOT NULL AND plan_expires_at < CURDATE()
        ");
    }

    /** Pedidos de pagamento (subscription_orders) pendentes há demasiado tempo. */
    private function checkStalePendingOrders(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id, company_id, plan_code, amount, created_at
            FROM subscription_orders
            WHERE status = 'pending'
              AND created_at <= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        ");
        $stmt->execute([':hours' => $this->staleOrderHours]);

        foreach ($stmt as $order) {
            foreach ($this->ownerEmails((int)$order['company_id']) as $owner) {
                $this->repo->notifyOnce(
                    (int)$order['company_id'],
                    (int)$owner['id'],
                    'subscription_order_pending',
                    'subscription_order',
                    (int)$order['id'],
                    "subscription_order_pending:{$order['id']}",
                    $owner['email'],
                    'Pagamento da subscrição pendente',
                    "O pagamento do plano {$order['plan_code']} ({$order['amount']}) ainda "
                        . "está pendente desde " . (new \DateTimeImmutable($order['created_at']))->format('d/m/Y H:i')
                        . ". Conclui o pagamento para manter o plano ativo."
                );
            }
        }
    }

    /** @return array<int, array{id:int, email:string}> */
    private function ownerEmails(int $companyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.email
            FROM company_has_user chu
            INNER JOIN users u ON u.id = chu.user_id
            WHERE chu.company_id = :company_id
              AND chu.role IN ('owner', 'admin')
              AND u.email IS NOT NULL AND u.email <> ''
        ");
        $stmt->execute([':company_id' => $companyId]);

        return $stmt->fetchAll();
    }
}
