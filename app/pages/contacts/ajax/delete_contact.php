<?php

require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Só apaga/arquiva contactos da empresa da sessão (antes não havia
    // este filtro: bastava saber o ID para apagar o contacto de outra empresa).
    $companyId = $_SESSION['user']['company_id'] ?? null;
    if (!$companyId) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Sessão expirada. Faça login novamente."]);
        exit;
    }

    $contactId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if (!$contactId) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID do contato não fornecido."]);
        exit;
    }

    try {
        // Confirma que o contacto existe e pertence a esta empresa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact WHERE id = :id AND company_id = :company_id");
        $stmt->execute([':id' => $contactId, ':company_id' => $companyId]);
        if ((int) $stmt->fetchColumn() === 0) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Contato não encontrado."]);
            exit;
        }

        // Se existir fatura vinculada, não exclui de verdade: apenas arquiva (is_active=0)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE contact_id = :id");
        $stmt->bindParam(":id", $contactId, PDO::PARAM_INT);
        $stmt->execute();
        $hasInvoices = ((int) $stmt->fetchColumn() > 0);

        if ($hasInvoices) {
            $stmt = $pdo->prepare("UPDATE contact SET is_active = 0 WHERE id = :id AND company_id = :company_id");
            $stmt->execute([':id' => $contactId, ':company_id' => $companyId]);

            echo json_encode([
                "success" => true,
                "archived" => true,
                "message" => "Não foi possível excluir, pois existem facturas vinculadas. O registo foi arquivado."
            ]);
            exit;
        }

        // Sem faturas vinculadas: exclui de verdade
        $stmt = $pdo->prepare("DELETE FROM contact WHERE id = :id AND company_id = :company_id");
        $stmt->execute([':id' => $contactId, ':company_id' => $companyId]);

        echo json_encode(["success" => true, "archived" => false]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erro ao excluir contato: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método inválido."]);
}
