<?php
/**
 * Adiantamentos e empréstimos (descontados automaticamente na folha, em prestações).
 * GET  action=list [&employee_id] [&status]
 * POST action=save   (id?, employee_id, type, description, total_amount, installments, start_month, granted_date)
 * POST action=cancel (id) — deixa de descontar; o que já foi descontado mantém-se
 * POST action=delete (id) — só sem prestações descontadas
 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$action = (string)($_REQUEST['action'] ?? 'list');

function rh_advance_paid(PDO $pdo, int $id): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM rh_advance_payments WHERE advance_id = ?');
    $stmt->execute([$id]);
    return (float)$stmt->fetchColumn();
}

function rh_advance_owned(PDO $pdo, int $companyId, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM rh_advances WHERE id = ? AND company_id = ?');
    $stmt->execute([$id, $companyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        rh_json(['success' => false, 'message' => 'Registo não encontrado.'], 404);
    }
    return $row;
}

if ($action === 'list') {
    $where = ['a.company_id = ?'];
    $params = [$company_id];
    if (!empty($_GET['employee_id'])) {
        $where[] = 'a.employee_id = ?';
        $params[] = (int)$_GET['employee_id'];
    }
    if (in_array($_GET['status'] ?? '', ['ativo', 'liquidado', 'cancelado'], true)) {
        $where[] = 'a.status = ?';
        $params[] = $_GET['status'];
    }
    $stmt = $pdo->prepare("
        SELECT a.*, e.name AS employee_name,
               COALESCE((SELECT SUM(ap.amount) FROM rh_advance_payments ap WHERE ap.advance_id = a.id), 0) AS paid_amount,
               (SELECT COUNT(*) FROM rh_advance_payments ap WHERE ap.advance_id = a.id) AS paid_installments
        FROM rh_advances a JOIN employees e ON e.id = a.employee_id
        WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(a.status, 'ativo', 'liquidado', 'cancelado'), a.start_month DESC, a.id DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['remaining'] = rh_money((float)$r['total_amount'] - (float)$r['paid_amount']);
    }
    rh_json(['data' => $rows, 'totals' => [
        'active' => count(array_filter($rows, fn($r) => $r['status'] === 'ativo')),
        'outstanding' => rh_money(array_sum(array_map(fn($r) => $r['status'] === 'ativo' ? $r['remaining'] : 0, $rows))),
    ]]);
}

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $type = ($_POST['type'] ?? '') === 'emprestimo' ? 'emprestimo' : 'adiantamento';
    $total = (float)str_replace(',', '.', (string)($_POST['total_amount'] ?? 0));
    $installments = max(1, min(120, (int)($_POST['installments'] ?? 1)));
    $startMonth = (string)($_POST['start_month'] ?? '');
    $granted = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['granted_date'] ?? '')) ? $_POST['granted_date'] : null;
    $description = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 255);

    if (!$employeeId || $total <= 0 || !rh_valid_month($startMonth)) {
        rh_json(['success' => false, 'message' => 'Indique o funcionário, o valor e o mês do primeiro desconto.'], 422);
    }
    $stmt = $pdo->prepare('SELECT 1 FROM employees WHERE id = ? AND company_id = ?');
    $stmt->execute([$employeeId, $company_id]);
    if (!$stmt->fetchColumn()) {
        rh_json(['success' => false, 'message' => 'Funcionário não encontrado.'], 404);
    }
    $installment = rh_money(ceil($total / $installments * 100) / 100);

    if ($id) {
        $current = rh_advance_owned($pdo, $company_id, $id);
        $paid = rh_advance_paid($pdo, $id);
        if ($paid > 0 && ((int)$current['employee_id'] !== $employeeId || $total < $paid)) {
            rh_json(['success' => false, 'message' => 'Já foram descontadas prestações: não pode mudar o funcionário nem baixar o valor abaixo do já descontado.'], 422);
        }
        $pdo->prepare('UPDATE rh_advances SET employee_id = ?, type = ?, description = ?, total_amount = ?, installments = ?, installment_amount = ?, start_month = ?, granted_date = ? WHERE id = ? AND company_id = ?')
            ->execute([$employeeId, $type, $description, $total, $installments, $installment, $startMonth, $granted, $id, $company_id]);
    } else {
        $pdo->prepare('INSERT INTO rh_advances (company_id, employee_id, type, description, total_amount, installments, installment_amount, start_month, granted_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$company_id, $employeeId, $type, $description, $total, $installments, $installment, $startMonth, $granted]);
    }
    rh_refresh_advance_status($pdo, $company_id, $employeeId);
    rh_json(['success' => true, 'message' => 'Registo gravado. A prestação de ' . number_format($installment, 2, ',', '.') . ' Kz será descontada a partir de ' . rh_month_label($startMonth) . '.']);
}

if ($action === 'cancel') {
    $row = rh_advance_owned($pdo, $company_id, (int)($_POST['id'] ?? 0));
    $pdo->prepare("UPDATE rh_advances SET status = 'cancelado' WHERE id = ? AND company_id = ?")->execute([$row['id'], $company_id]);
    rh_json(['success' => true, 'message' => 'Deixou de ser descontado nas próximas folhas.']);
}

if ($action === 'delete') {
    $row = rh_advance_owned($pdo, $company_id, (int)($_POST['id'] ?? 0));
    if (rh_advance_paid($pdo, (int)$row['id']) > 0) {
        rh_json(['success' => false, 'message' => 'Já tem prestações descontadas — use "Cancelar" em vez de eliminar.'], 422);
    }
    $pdo->prepare('DELETE FROM rh_advances WHERE id = ? AND company_id = ?')->execute([$row['id'], $company_id]);
    rh_json(['success' => true, 'message' => 'Eliminado.']);
}

rh_json(['success' => false, 'message' => 'Acção inválida.'], 400);
