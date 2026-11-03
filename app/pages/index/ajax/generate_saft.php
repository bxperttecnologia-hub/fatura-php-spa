<?php
require_once __DIR__ . '/../../../../app/helpers/require_login.php';

http_response_code(501);
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => false,
    'message' => 'A exportação SAF-T(AO) está indisponível: o gerador anterior usava dados de empresa, NIF, certificado e IVA fictícios e não validava o XML contra o esquema oficial.',
], JSON_UNESCAPED_UNICODE);
exit;
