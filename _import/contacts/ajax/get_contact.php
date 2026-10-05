<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json'); 
session_start();
try {
    $id = $_POST['id'];

    $sql = "SELECT * FROM contact WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($contact);
} catch (PDOException $e) {
    echo json_encode(["error" => "Erro ao buscar contato: " . $e->getMessage()]);
}
?>
