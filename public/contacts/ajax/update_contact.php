<?php
require_once '../../../app/config/db.php';
session_start();

// Nota: esta resposta continua SEM header "application/json" de propósito —
// o JS que a consome faz JSON.parse() ao texto recebido.

if (!empty($_SESSION["timezone"])) {
    date_default_timezone_set($_SESSION["timezone"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Só edita contactos da empresa da sessão.
    $companyId = $_SESSION['user']['company_id'] ?? null;
    if (!$companyId) {
        echo json_encode(["status" => "error", "message" => "Sessão expirada. Faça login novamente."]);
        exit;
    }

    $contactId = isset($_POST["id"]) ? intval($_POST["id"]) : 0;

    // Campos que nunca devem ser alterados por aqui
    // (a empresa e a data de criação não mudam; o ID vai no WHERE).
    unset($_POST["id"], $_POST["undefined"], $_POST["company_id"], $_POST["created_at"], $_POST["updated_at"]);

    if ($contactId === 0) {
        echo json_encode(["status" => "error", "message" => "ID inválido"]);
        exit;
    }

    // Os nomes dos campos entram na query (entre crases). Só aceitamos
    // nomes de coluna "normais" para impedir SQL injection pelo nome do campo.
    foreach (array_keys($_POST) as $field) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $field)) {
            echo json_encode(["status" => "error", "message" => "Campo inválido no formulário."]);
            exit;
        }
    }

    // Validações dos campos que vierem no pedido
    foreach (["telephone", "pref_telephone", "pref_cellphone"] as $phoneField) {
        if (isset($_POST[$phoneField]) && trim($_POST[$phoneField]) !== "" && !preg_match('/^[29]\d{8}$/', trim($_POST[$phoneField]))) {
            echo json_encode(["status" => "error", "message" => "Telefone inválido. Deve ter 9 dígitos e começar por 2 ou 9."]);
            exit;
        }
    }
    if (isset($_POST["telephone"]) && trim($_POST["telephone"]) === "") {
        echo json_encode(["status" => "error", "message" => "O campo 'telephone' é obrigatório."]);
        exit;
    }
    if (!empty($_POST["email"]) && !filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Email inválido."]);
        exit;
    }

    $fieldsToUpdate = [];
    $params = [];

    foreach ($_POST as $field => $value) {
        $fieldsToUpdate[] = "`$field` = :$field";
        $params[":$field"] = $value;
    }

    // Tem de ser verificado ANTES de acrescentarmos o updated_at abaixo,
    // senão "nenhuma alteração" nunca seria detetado.
    if (empty($fieldsToUpdate)) {
        echo json_encode(["status" => "error", "message" => "Nenhuma alteração detectada"]);
        exit;
    }

    try {
        // updated_at passa a ser gravado no servidor. Antes vinha de um campo
        // escondido que o JS só enviava se o valor mudasse — ou seja, nunca.
        // Só o usamos se a coluna existir, para não partir a edição.
        $hasUpdatedAt = $pdo->query("SHOW COLUMNS FROM contact LIKE 'updated_at'")->fetch() !== false;
        if ($hasUpdatedAt) {
            $fieldsToUpdate[] = "`updated_at` = :updated_at";
            $params[":updated_at"] = date('Y-m-d H:i:s');
        }

        $query = "UPDATE contact SET " . implode(", ", $fieldsToUpdate) . " WHERE id = :id AND company_id = :company_id";

        $params[":id"] = $contactId;
        $params[":company_id"] = $companyId;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode(["status" => "success", "message" => "Contato atualizado com sucesso"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Erro ao atualizar contato: " . $e->getMessage()]);
    }
}
?>
