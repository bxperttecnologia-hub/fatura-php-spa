<?php

declare(strict_types=1);

namespace Cron\Checks;

use Cron\NotificationRepository;

/**
 * Avisa os responsáveis da empresa (owner/admin) de faturas a vencer e vencidas.
 * É uma notificação interna (sino + push); não envia nada ao cliente da fatura.
 */
class InvoiceCheck
{
    // Só faturas emitidas e por pagar: Finalizado(3), Pendente(5). Ficam de fora Rascunho(1), Cancelado(2), Pago(4).
    private const OPEN_STATUS = [3, 5];

    // FR (fatura-recibo) é paga na emissão: só a FT tem prazo de pagamento.
    private const DOCUMENT_TYPES = ['FT'];

    public function __construct(
        private \PDO $pdo,
        private NotificationRepository $repo,
        private int $dueSoonDays,
        private int $overdueMaxDays = 90       // depois disto deixa de lembrar (evita lembretes eternos)
    ) {}

    public function run(): void
    {
        $types = implode(',', array_map(fn($t) => $this->pdo->quote($t), self::DOCUMENT_TYPES));
        $stmt = $this->pdo->prepare("
            SELECT * FROM (
                SELECT
                    i.id, i.company_id, i.series, i.reference, i.final_total, i.currency,
                    DATE_ADD(i.issue_date, INTERVAL COALESCE(i.due_date, 0) DAY) AS due_at,
                    c.name AS contact_name
                FROM invoices i
                LEFT JOIN contact c ON c.id = i.contact_id
                WHERE i.status IN (" . implode(',', self::OPEN_STATUS) . ")
                  AND i.document_type IN ($types)
                  AND i.issue_date IS NOT NULL
            ) x
            WHERE x.due_at BETWEEN DATE_SUB(CURDATE(), INTERVAL :overdue DAY)
                              AND DATE_ADD(CURDATE(), INTERVAL :soon DAY)
        ");
        $stmt->execute([':overdue' => $this->overdueMaxDays, ':soon' => $this->dueSoonDays]);

        $today = new \DateTimeImmutable('today');
        foreach ($stmt as $inv) {
            $dueAt = new \DateTimeImmutable(substr((string)$inv['due_at'], 0, 10));
            $daysDiff = (int)$today->diff($dueAt)->format('%r%a');

            // reference = número completo (ex.: "FT FT1226S13348N/4"); series é só a série
            $docRef = $inv['reference'] ?: $inv['series'] ?: ('#' . $inv['id']);
            $client = trim((string)($inv['contact_name'] ?? '')) ?: 'cliente';
            $amount = number_format((float)$inv['final_total'], 2, ',', '.') . ' ' . $inv['currency'];
            $url = '/invoices/view?id=' . (int)$inv['id'];

            if ($daysDiff >= 0) {
                // vence em breve — um aviso por fatura e data de vencimento
                $this->repo->notifyOnce(
                    (int)$inv['company_id'],
                    null,
                    'invoice_due_soon',
                    'invoice',
                    (int)$inv['id'],
                    "invoice_due_soon:{$inv['id']}:{$dueAt->format('Y-m-d')}",
                    null,
                    "Fatura {$docRef} vence " . ($daysDiff === 0 ? 'hoje' : "em {$daysDiff} dia(s)"),
                    "A fatura {$docRef} de {$client}, no valor de {$amount}, vence em {$dueAt->format('d/m/Y')}.",
                    $url
                );
            } else {
                // vencida — repete o aviso a cada 7 dias em atraso, não todos os dias
                $weekBucket = intdiv(abs($daysDiff), 7);
                $this->repo->notifyOnce(
                    (int)$inv['company_id'],
                    null,
                    'invoice_overdue',
                    'invoice',
                    (int)$inv['id'],
                    "invoice_overdue:{$inv['id']}:w{$weekBucket}",
                    null,
                    "Fatura {$docRef} está vencida",
                    "A fatura {$docRef} de {$client}, no valor de {$amount}, venceu em {$dueAt->format('d/m/Y')} "
                        . "e continua por regularizar.",
                    $url
                );
            }
        }
    }
}
