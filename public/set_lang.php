<?php
// O bootstrap carrega app/helpers/translation.php, que trata de POST {lang} e responde em JSON.
require __DIR__ . '/../app/core/bootstrap.php';
http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'error']);
