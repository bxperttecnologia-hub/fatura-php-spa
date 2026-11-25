<?php
/** Grava um registo de ponto (insere ou actualiza; um registo por funcionário e dia). */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/attendance_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rh_json(['success' => false, 'message' => 'Método não permitido.'], 405);
}
try {
    $id = (int)($_POST['id'] ?? 0) ?: null;
    $newId = rh_attendance_save($pdo, $company_id, (int)($_POST['employee_id'] ?? 0), (string)($_POST['date'] ?? ''), $_POST, $id);
    rh_json(['success' => true, 'id' => $newId, 'message' => 'Registo de ponto gravado.']);
} catch (Throwable $e) {
    rh_json(['success' => false, 'message' => rh_error_message($e, 'save_attendance')], 422);
}
