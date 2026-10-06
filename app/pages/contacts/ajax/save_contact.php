<?php
require_once '../../../app/config/db.php';
session_start();

// Nota: esta resposta continua SEM header "application/json" de propósito —
// o JS que a consome faz JSON.parse() ao texto recebido.

if (!empty($_SESSION["timezone"])) {
    date_default_timezone_set($_SESSION["timezone"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // A empresa vem SEMPRE da sessão, nunca do que o navegador envia.
    $sessionCompanyId = $_SESSION['user']['company_id'] ?? null;
    if (!$sessionCompanyId) {
        echo json_encode(["status" => "error", "message" => "Sessão expirada. Faça login novamente."]);
        exit;
    }

    $_POST['company_id'] = $sessionCompanyId;

    // Data de criação definida no servidor (com o fuso horário da conta).
    // Antes vinha de um campo escondido preenchido quando a página carregava,
    // o que ficava desatualizado se a página ficasse aberta muito tempo.
    $_POST['created_at'] = date('Y-m-d H:i:s');

    // Campos que nunca devem vir do formulário
    unset($_POST['id'], $_POST['updated_at'], $_POST['undefined']);

    // Os nomes dos campos entram na query (entre crases). Só aceitamos
    // nomes de coluna "normais" para impedir SQL injection pelo nome do campo.
    foreach (array_keys($_POST) as $field) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $field)) {
            echo json_encode(["status" => "error", "message" => "Campo inválido no formulário."]);
            exit;
        }
    }

    // Lista de campos obrigatórios
    $requiredFields = ["company_id", "name", "contributor", "address"];

    // Verifica se algum campo obrigatório está vazio
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === "") {
            echo json_encode(["status" => "error", "message" => "O campo '$field' é obrigatório."]);
            exit;
        }
    }

    $fields = [];
    $placeholders = [];
    $values = [];

    foreach ($_POST as $field => $value) {
        $fields[] = "`$field`";
        $placeholders[] = ":$field";
        $values[":$field"] = $value;
    }

    if (empty($fields)) {
        echo json_encode(["status" => "error", "message" => "Nenhum dado enviado"]);
        exit;
    }

    $query = "INSERT INTO contact (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($values);
        echo json_encode([
            "status"  => "success",
            "message" => "Contato cadastrado com sucesso",
            "id"      => (int) $pdo->lastInsertId(),
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Erro ao salvar contato: " . $e->getMessage()]);
    }
}
?>
