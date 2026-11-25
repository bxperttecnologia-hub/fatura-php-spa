<?php
require_once __DIR__ . '/../lib/rh_guard.php'; // sessão + permissão do módulo RH
/** Compatibilidade: o mapa salarial é gerado por export/report.php?type=folha. */
$_GET['type'] = 'folha';
$_GET['mes'] = $_GET['mes'] ?? date('Y-m');
$_GET['format'] = $_GET['format'] ?? 'xlsx';
require __DIR__ . '/report.php';
