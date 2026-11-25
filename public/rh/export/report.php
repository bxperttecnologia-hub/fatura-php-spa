<?php
/** Mapas mensais do RH. GET type=folha|inss|irt|banco, mes=YYYY-MM, format=xlsx|pdf|csv */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/rh_reports.php';

ini_set('display_errors', '0');
$type = (string)($_GET['type'] ?? 'folha');
$month = (string)($_GET['mes'] ?? '');
$format = in_array($_GET['format'] ?? 'xlsx', ['xlsx', 'pdf', 'csv'], true) ? $_GET['format'] : 'xlsx';
if (!isset(rh_report_types()[$type]) || !rh_valid_month($month)) {
    rh_json(['success' => false, 'message' => 'Indique o mapa e o mês de referência.'], 422);
}
rh_report_output($pdo, $company_id, rh_report_data($pdo, $company_id, $type, $month), $format);
