<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php'; // exige sessão iniciada (401/403 em JSON)
require_once '../../../app/config/db.php';

$id = $_POST['id'];
require_owned('attendance', $id);

$stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true]);
