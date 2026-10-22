<?php
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'message' => 'A alteração directa do plano está desactivada.']);
