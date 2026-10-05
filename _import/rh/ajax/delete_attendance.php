<?php
require_once '../../../app/config/db.php';

$id = $_POST['id'];

$stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true]);
