<?php
/** Envia por e-mail o recibo (PDF em anexo) de cada folha aprovada ou paga. POST ids[] */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/rh_pdf.php';
require_once __DIR__ . '/../lib/rh_mail.php';

@set_time_limit(300);
$ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
if (!$ids) {
    rh_json(['success' => false, 'message' => 'Selecione pelo menos uma folha.'], 422);
}
if (!rh_smtp_configured()) {
    rh_json(['success' => false, 'message' => 'O envio de e-mail não está configurado no servidor (SMTP).'], 503);
}
$company = rh_company($pdo, $company_id);
$settings = rh_settings($pdo, $company_id);

$sent = 0;
$errors = [];
foreach ($ids as $id) {
    $p = rh_payslip_data($pdo, $company_id, $id);
    if (!$p) {
        continue;
    }
    $name = $p['employee_name'];
    if (!in_array($p['status'], ['Aprovado', 'Pago'], true)) {
        $errors[] = "$name: a folha ainda está pendente";
        continue;
    }
    if (!filter_var($p['employee_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "$name: sem e-mail válido na ficha";
        continue;
    }
    $vars = ['{nome}' => $name, '{mes}' => rh_month_label($p['reference_month']), '{empresa}' => $company['name'] ?? '', '{liquido}' => rh_kz($p['net_salary'])];
    $text = strtr((string)$settings['payslip_email_message'], $vars);
    $pdf = rh_pdf_render(rh_payslip_html($pdo, $company_id, $id), '', 'string');
    $error = rh_send_mail($p['employee_email'], 'Recibo de vencimento — ' . rh_month_label($p['reference_month']), $text, [[$pdf, rh_payslip_filename($p)]], $company['name'] ?? '');
    if ($error) {
        $errors[] = "$name: $error";
        continue;
    }
    $pdo->prepare('UPDATE payroll SET emailed_at = NOW() WHERE id = ? AND company_id = ?')->execute([$id, $company_id]);
    $sent++;
}
rh_json(['success' => $sent > 0, 'sent' => $sent, 'errors' => $errors, 'message' => "$sent recibo(s) enviado(s) por e-mail."]);
