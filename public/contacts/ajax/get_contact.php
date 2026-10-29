<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php'; // exige sessão iniciada (401/403 em JSON)
require_once '../../../app/config/db.php';
header('Content-Type: application/json'); 
if (session_status() === PHP_SESSION_NONE) session_start();
try {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

    $sql = "SELECT * FROM contact WHERE id = :id AND company_id = :cid";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $cid = session_company_id();
    $stmt->bindParam(":cid", $cid, PDO::PARAM_INT);
    $stmt->execute();

    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($contact);
} catch (PDOException $e) {
    echo json_encode(["error" => "Erro ao buscar contato: " . $e->getMessage()]);
}
?>
