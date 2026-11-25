<?php
/**
 * Portal do funcionário (sem login): acesso por link pessoal /portal.php?t=<token>, gerado pelo RH.
 *  - recibos de vencimento aprovados/pagos (PDF);
 *  - saldo de férias e pedidos de férias (ficam Pendentes para o RH aprovar);
 *  - declaração de serviço (PDF).
 * O token só existe no link; na base de dados guarda-se o SHA-256 (employees.portal_token).
 */
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/rh/lib/vacation_lib.php';
require_once __DIR__ . '/rh/lib/rh_pdf.php';
require_once __DIR__ . '/rh/lib/rh_documents.php';

ini_set('display_errors', '0');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$emp = null;
if (preg_match('/^[a-f0-9]{48}$/', $token)) {
    $stmt = $pdo->prepare("SELECT e.id, e.company_id, e.name, e.email, e.admission_date, e.status, COALESCE(p.name, e.position) AS position_name
                           FROM employees e LEFT JOIN positions p ON p.id = e.position_id AND p.company_id = e.company_id
                           WHERE e.portal_token = ? AND e.status = 'ativo' LIMIT 1");
    $stmt->execute([hash('sha256', $token)]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$emp) {
    usleep(300000);
    http_response_code(410); // 404 seria trocado pela página genérica do nginx (fastcgi_intercept_errors)
    portal_page('Link inválido', '<div class="text-center py-5"><i class="bi bi-shield-lock fs-1 text-muted"></i><h1 class="h4 mt-3">Link inválido ou expirado</h1><p class="text-muted">Peça um novo link de acesso aos Recursos Humanos da sua empresa.</p></div>');
    exit;
}
$companyId = (int)$emp['company_id'];
$employeeId = (int)$emp['id'];
$self = 'portal.php?t=' . $token;

// ---------- downloads ----------
if (isset($_GET['recibo'])) {
    $stmt = $pdo->prepare("SELECT id FROM payroll WHERE id = ? AND company_id = ? AND employee_id = ? AND status IN ('Aprovado','Pago')");
    $stmt->execute([(int)$_GET['recibo'], $companyId, $employeeId]);
    $pid = (int)$stmt->fetchColumn();
    $html = $pid ? rh_payslip_html($pdo, $companyId, $pid) : null;
    if (!$html) {
        http_response_code(404);
        exit('Recibo não encontrado.');
    }
    rh_pdf_render($html, rh_payslip_filename(rh_payslip_data($pdo, $companyId, $pid)), 'inline');
    exit;
}
if (($_GET['doc'] ?? '') === 'declaracao') {
    $doc = rh_employee_document($pdo, $companyId, $employeeId, 'declaracao', ['purpose' => mb_substr((string)($_GET['purpose'] ?? ''), 0, 120)]);
    rh_pdf_render($doc['html'], $doc['filename'], 'inline');
    exit;
}

// ---------- pedido de férias ----------
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'vacation') {
    $start = (string)($_POST['start_date'] ?? '');
    $end = (string)($_POST['end_date'] ?? '');
    $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 500);
    $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && strtotime($start) && strtotime($end);
    if (!$valid || $end < $start) {
        $flash = ['danger', 'Indique datas válidas (o fim não pode ser antes do início).'];
    } elseif ($start < date('Y-m-d')) {
        $flash = ['danger', 'O pedido tem de começar hoje ou numa data futura.'];
    } elseif (rh_vacation_overlaps($pdo, $companyId, $employeeId, $start, $end)) {
        $flash = ['danger', 'Já tem um período registado ou pedido nessas datas.'];
    } else {
        $days = rh_working_days($pdo, $companyId, $start, $end);
        if ($days < 1) {
            $flash = ['danger', 'O período escolhido não tem dias úteis.'];
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM vacations WHERE company_id = ? AND employee_id = ? AND requested_via = ? AND created_at > (NOW() - INTERVAL 1 DAY)');
            $stmt->execute([$companyId, $employeeId, 'portal']);
            if ((int)$stmt->fetchColumn() >= 5) {
                $flash = ['danger', 'Atingiu o limite de pedidos por dia. Tente novamente amanhã.'];
            } else {
                $pdo->prepare("INSERT INTO vacations (employee_id, company_id, type, start_date, end_date, reason, status, working_days, requested_via)
                               VALUES (?, ?, 'Férias', ?, ?, ?, 'Pendente', ?, 'portal')")
                    ->execute([$employeeId, $companyId, $start, $end, $reason ?: null, $days]);
                header('Location: ' . $self . '&ok=1#ferias', true, 303);
                exit;
            }
        }
    }
}
if (isset($_GET['ok'])) {
    $flash = ['success', 'Pedido de férias enviado. Os Recursos Humanos vão analisá-lo.'];
}

// ---------- dados ----------
$company = rh_company($pdo, $companyId);
$year = (int)date('Y');
$bal = rh_vacation_balances($pdo, $companyId, $year, $employeeId)[$employeeId] ?? null;
$stmt = $pdo->prepare("SELECT id, reference_month, net_salary, gross_salary, status, payment_date FROM payroll
                       WHERE company_id = ? AND employee_id = ? AND status IN ('Aprovado','Pago') ORDER BY reference_month DESC LIMIT 24");
$stmt->execute([$companyId, $employeeId]);
$slips = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT type, start_date, end_date, status, working_days FROM vacations WHERE company_id = ? AND employee_id = ? AND start_date >= ? ORDER BY start_date DESC LIMIT 20");
$stmt->execute([$companyId, $employeeId, ($year - 1) . '-01-01']);
$vacs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$e = 'rh_e';
$fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 1, ',', ''), '0'), ',');
ob_start();
?>
<header class="portal-head">
    <div>
        <div class="small text-uppercase opacity-75"><?= $e($company['name'] ?? '') ?></div>
        <h1 class="h4 mb-0">Olá, <?= $e(explode(' ', trim($emp['name']))[0]) ?></h1>
        <div class="small opacity-75"><?= $e($emp['name']) ?><?= $emp['position_name'] ? ' · ' . $e($emp['position_name']) : '' ?></div>
    </div>
    <i class="bi bi-person-badge fs-1 opacity-50"></i>
</header>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] ?> mt-3"><?= $e($flash[1]) ?></div><?php endif; ?>

<div class="row g-3 mt-1">
    <div class="col-sm-4"><div class="pcard text-center"><div class="lbl">Saldo de férias <?= $year ?></div><div class="big"><?= $bal ? $fmt($bal['balance']) : '—' ?></div><div class="small text-muted">dias úteis</div></div></div>
    <div class="col-sm-4"><div class="pcard text-center"><div class="lbl">Direito + transitados</div><div class="big"><?= $bal ? $fmt($bal['entitlement'] + $bal['carry']) : '—' ?></div><div class="small text-muted">gozados: <?= $bal ? $fmt($bal['used']) : '—' ?></div></div></div>
    <div class="col-sm-4"><div class="pcard text-center"><div class="lbl">Último recibo</div><div class="big fs-5 pt-2"><?= $slips ? $e(rh_kz($slips[0]['net_salary'])) : '—' ?></div><div class="small text-muted"><?= $slips ? $e(rh_month_label($slips[0]['reference_month'])) : 'sem recibos' ?></div></div></div>
</div>

<section class="pcard mt-3">
    <h2><i class="bi bi-receipt"></i> Recibos de vencimento</h2>
    <?php if (!$slips): ?><p class="text-muted mb-0">Ainda não há recibos disponíveis.</p><?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($slips as $s): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0" target="_blank" rel="noopener" href="<?= $e($self . '&recibo=' . (int)$s['id']) ?>">
            <span><strong><?= $e(rh_month_label($s['reference_month'])) ?></strong>
                <span class="badge <?= $s['status'] === 'Pago' ? 'text-bg-success' : 'text-bg-primary' ?> ms-1"><?= $e($s['status']) ?></span></span>
            <span class="text-nowrap"><?= $e(rh_kz($s['net_salary'])) ?> <i class="bi bi-file-earmark-pdf text-danger ms-2"></i></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="pcard mt-3" id="ferias">
    <h2><i class="bi bi-airplane"></i> Pedir férias</h2>
    <form method="post" action="<?= $e($self) ?>#ferias" class="row g-2">
        <input type="hidden" name="t" value="<?= $e($token) ?>"><input type="hidden" name="action" value="vacation">
        <div class="col-6"><label class="form-label small" for="ps">Início</label><input type="date" class="form-control" id="ps" name="start_date" min="<?= date('Y-m-d') ?>" required value="<?= $e($_POST['start_date'] ?? '') ?>"></div>
        <div class="col-6"><label class="form-label small" for="pe">Fim</label><input type="date" class="form-control" id="pe" name="end_date" min="<?= date('Y-m-d') ?>" required value="<?= $e($_POST['end_date'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label small" for="pr">Observação (opcional)</label><input type="text" class="form-control" id="pr" name="reason" maxlength="500" value="<?= $e($_POST['reason'] ?? '') ?>"></div>
        <div class="col-12"><button class="btn btn-primary w-100"><i class="bi bi-send"></i> Enviar pedido</button></div>
    </form>
    <?php if ($vacs): ?>
    <h3 class="h6 mt-4">Os meus períodos</h3>
    <div class="list-group list-group-flush">
        <?php foreach ($vacs as $v): ?>
        <div class="list-group-item d-flex justify-content-between px-0">
            <span><?= $e($v['type']) ?> · <?= $e(rh_date_pt($v['start_date'])) ?> → <?= $e(rh_date_pt($v['end_date'])) ?><?= $v['working_days'] !== null ? ' <span class="text-muted small">(' . $fmt($v['working_days']) . ' d. úteis)</span>' : '' ?></span>
            <span class="badge <?= $v['status'] === 'Aprovado' ? 'text-bg-success' : ($v['status'] === 'Pendente' ? 'text-bg-warning' : 'text-bg-danger') ?> align-self-center"><?= $e($v['status']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="pcard mt-3">
    <h2><i class="bi bi-file-earmark-text"></i> Documentos</h2>
    <form method="get" action="portal.php" target="_blank" class="d-flex gap-2 flex-wrap">
        <input type="hidden" name="t" value="<?= $e($token) ?>"><input type="hidden" name="doc" value="declaracao">
        <input type="text" name="purpose" class="form-control flex-grow-1" style="min-width:200px" maxlength="120" placeholder="Finalidade (ex.: abertura de conta bancária)">
        <button class="btn btn-outline-primary"><i class="bi bi-download"></i> Declaração de serviço</button>
    </form>
</section>
<p class="text-center small text-muted mt-4 mb-0">Este link é pessoal. Não o partilhe. Em caso de perda, peça um novo aos Recursos Humanos.</p>
<?php
portal_page('Portal do funcionário', ob_get_clean());

function portal_page(string $title, string $body): void
{
    ?><!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
    <title><?= rh_e($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 16px 16px 32px; }
        .portal-head { background: linear-gradient(135deg, #0f766e, #0d9488); color: #fff; border-radius: 14px; padding: 20px 22px; display: flex; justify-content: space-between; align-items: center; }
        .pcard { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; height: 100%; }
        .pcard h2 { font-size: 1rem; font-weight: 700; margin-bottom: 12px; }
        .pcard .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; color: #64748b; font-weight: 600; }
        .pcard .big { font-size: 1.8rem; font-weight: 700; color: #0f172a; }
    </style>
</head>
<body><div class="wrap"><?= $body ?></div></body>
</html><?php
}
