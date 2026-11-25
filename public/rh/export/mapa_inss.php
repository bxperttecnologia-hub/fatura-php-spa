<?php
require_once __DIR__ . '/../lib/rh_guard.php'; // sessão + permissão do módulo RH
/** Compatibilidade: o mapa INSS é gerado por export/report.php?type=inss. */
$_GET['type'] = 'inss';
$_GET['format'] = ($_GET['format'] ?? 'pdf') === 'xlsx' ? 'xlsx' : 'pdf';
require __DIR__ . '/report.php';
