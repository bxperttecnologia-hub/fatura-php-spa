<?php

declare(strict_types=1);

namespace Cron\Checks;

use Cron\NotificationRepository;

class InvoiceCheck
{
    // status finais que não interessam para aviso: Cancelado(2), Pago(4)
    private const IGNORED_STATUS = [2, 4];

    public function __construct(
        private \PDO $pdo,
        private NotificationRepository $repo,
        private int $dueSoonDays
    ) {}

    public function run(): void
    {
        $sql = "
            SELECT
                i.id, i.company_id, i.series, i.reference, i.final_total, i.currency,
                i.issue_date, i.due_date, i.document_type,
                DATE_ADD(i.issue_date, INTERVAL COALESCE(i.due_date, 0) DAY) AS due_at,
                c.name AS contact_name, c.email AS contact_email,
                comp.name AS company_name
            FROM invoices i
            INNER JOIN contact c ON c.id = i.contact_id
            INNER JOIN companies comp ON comp.id = i.company_id
            WHERE i.status NOT IN (" . implode(',', self::IGNORED_STATUS) . ")
              AND i.issue_date IS NOT NULL
        ";

        foreach ($this->pdo->query($sql) as $inv) {
            if (empty($inv['contact_email'])) {
                continue;
            }

            $dueAt = new \DateTimeImmutable($inv['due_at']);
            $today = new \DateTimeImmutable('today');
            $daysDiff = (int)$today->diff($dueAt)->format('%r%a');

            $docRef = $inv['series'] ?: $inv['reference'] ?: ('#' . $inv['id']);

            if ($daysDiff >= 0 && $daysDiff <= $this->dueSoonDays) {
                // vence em breve — um aviso único por fatura (por data de vencimento)
                $this->repo->notifyOnce(
                    (int)$inv['company_id'],
                    null,
                    'invoice_due_soon',
                    'invoice',
                    (int)$inv['id'],
                    "invoice_due_soon:{$inv['id']}:{$inv['due_at']}",
                    $inv['contact_email'],
                    "Fatura {$docRef} vence em breve",
                    "Olá {$inv['contact_name']}, a fatura {$docRef} da {$inv['company_name']} "
                        . "no valor de {$inv['final_total']} {$inv['currency']} vence em "
                        . $dueAt->format('d/m/Y') . "."
                );
            } elseif ($daysDiff < 0) {
                // vencida — repete o aviso a cada 7 dias em atraso, não todos os dias
                $weekBucket = intdiv(abs($daysDiff), 7);
                $this->repo->notifyOnce(
                    (int)$inv['company_id'],
                    null,
                    'invoice_overdue',
                    'invoice',
                    (int)$inv['id'],
                    "invoice_overdue:{$inv['id']}:w{$weekBucket}",
                    $inv['contact_email'],
                    "Fatura {$docRef} está vencida",
                    "Olá {$inv['contact_name']}, a fatura {$docRef} da {$inv['company_name']} "
                        . "no valor de {$inv['final_total']} {$inv['currency']} venceu em "
                        . $dueAt->format('d/m/Y') . " e continua por regularizar."
                );
            }
        }
    }
}
