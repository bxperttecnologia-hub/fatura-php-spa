<?php
/**
 * Notas de crédito parciais: o que ainda se pode creditar em cada linha de uma fatura.
 * Cada linha creditada fica em credit_note_items.invoice_item_id; NC antigas (sem essa
 * ligação) eram sempre de anulação integral, por isso contam como tudo creditado.
 */

/** Linhas da fatura com a quantidade já creditada e a que ainda se pode creditar. */
function bx_credit_note_invoice_lines(PDO $pdo, int $invoiceId): array
{
    $stmt = $pdo->prepare("
        SELECT ii.id, ii.item_id, ii.quantity, ii.unit_price, ii.tax, ii.discount, ii.retention_rate,
               i.name, i.code, i.unit_measure, i.item_type
        FROM invoice_items ii
        LEFT JOIN items i ON i.id = ii.item_id
        WHERE ii.invoice_id = ?
        ORDER BY ii.id
    ");
    $stmt->execute([$invoiceId]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT cni.invoice_item_id, SUM(cni.quantity) AS credited
        FROM credit_note_items cni
        JOIN credit_notes cn ON cn.id = cni.credit_note_id
        WHERE cn.invoice_id = ? AND cni.invoice_item_id IS NOT NULL
        GROUP BY cni.invoice_item_id
    ");
    $stmt->execute([$invoiceId]);
    $credited = array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM credit_note_items cni
        JOIN credit_notes cn ON cn.id = cni.credit_note_id
        WHERE cn.invoice_id = ? AND cni.invoice_item_id IS NULL
    ");
    $stmt->execute([$invoiceId]);
    $legacyFull = (int) $stmt->fetchColumn() > 0;

    foreach ($lines as $n => &$line) {
        $qty = (float) $line['quantity'];
        $done = $legacyFull ? $qty : min($qty, $credited[(int) $line['id']] ?? 0.0);
        $line['line_no'] = $n + 1;
        $line['credited_quantity'] = round($done, 4);
        $line['remaining_quantity'] = round(max(0.0, $qty - $done), 4);
    }
    unset($line);
    return $lines;
}
