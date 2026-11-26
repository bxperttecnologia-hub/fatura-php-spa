<?php
// Desativado (auditoria de segurança 2026-11-26): endpoint sem uso na aplicação e sem controlo de
// sessão/empresa. A funcionalidade equivalente existe em invoices/ajax/* com as devidas verificações.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'message' => 'Este endpoint foi desativado.']);
