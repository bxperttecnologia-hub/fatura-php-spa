<?php

declare(strict_types=1);

// =====================================================================
// DEBUG TEMPORÁRIO — ligado. Quando resolver, mude para false e apague
// o ficheiro perfil/ajax/debug_perfil.log (contém dados do formulário).
// =====================================================================
const PERFIL_DEBUG = false;

ob_start();

$dbg = ['steps' => [], 'php_errors' => []];
$dbgStep = function (string $s) use (&$dbg) { $dbg['steps'][] = $s; };

function dbg_log(string $msg): void
{
    if (!PERFIL_DEBUG) {
        return;
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    if (@file_put_contents(__DIR__ . '/debug_perfil.log', $line, FILE_APPEND) === false) {
        error_log('PERFIL_DEBUG ' . $msg);
    }
}

function respond(int $code, array $data): void
{
    global $dbg;
    if (PERFIL_DEBUG) {
        $data['debug'] = $dbg;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    dbg_log("RESPOSTA $code: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    exit;
}

if (PERFIL_DEBUG) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    // avisos/notices ficam registados em vez de estragar o JSON
    set_error_handler(function ($no, $str, $file, $line) use (&$dbg) {
        $dbg['php_errors'][] = "[$no] $str em " . basename($file) . ":$line";
        return true;
    });

    // exceções não tratadas
    set_exception_handler(function (Throwable $e) {
        respond(500, [
            "status" => "error",
            "message" => "Exceção: " . $e->getMessage(),
            "where" => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
    });

    // erros fatais (ex.: db.php não encontrado, função inexistente)
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            $out = [
                "status" => "error",
                "message" => "ERRO FATAL PHP: " . $e['message'],
                "where" => basename($e['file']) . ':' . $e['line'],
            ];
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
            dbg_log('FATAL: ' . json_encode($out, JSON_UNESCAPED_UNICODE));
        }
    });
}

$dbgStep('inicio');

$dbPath = __DIR__ . '/../../../app/config/db.php';
$dbg['db_path'] = realpath($dbPath) ?: ($dbPath . ' (NÃO ENCONTRADO)');
require_once $dbPath;
$dbgStep('db.php carregado');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$dbgStep('sessao ok');

$dbg['pdo'] = isset($pdo) && $pdo instanceof PDO ? 'PDO ok' : 'PDO NÃO DEFINIDO (verifique o nome da variável em db.php)';
$dbg['session_user_keys'] = isset($_SESSION['user']) && is_array($_SESSION['user']) ? array_keys($_SESSION['user']) : 'SEM $_SESSION[user]';
$dbg['method'] = $_SERVER['REQUEST_METHOD'] ?? '';
$dbg['post'] = $_POST;
$dbg['content_type'] = $_SERVER['CONTENT_TYPE'] ?? '';

// Diagnóstico por GET: abra .../update_profile.php?check=1 no navegador (já logado)
if (PERFIL_DEBUG && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['check'])) {
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $dbg['colunas_users'] = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
            $dbg['db_atual'] = $pdo->query('SELECT DATABASE()')->fetchColumn();
            $dbg['grants'] = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
            if (isset($_SESSION['user']['id'])) {
                $st = $pdo->prepare('SELECT id, username, name, email, phone, address, address_district, country, lang, image FROM users WHERE id = ?');
                $st->execute([(int)$_SESSION['user']['id']]);
                $dbg['linha_no_banco'] = $st->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            $dbg['check_erro'] = $e->getMessage();
        }
    }
    respond(200, ["status" => "check"]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ["status" => "error", "message" => "Método não permitido."]);
}

if (!isset($_SESSION['user']['id'])) {
    respond(401, ["status" => "error", "message" => "Sessão expirada ou \$_SESSION['user']['id'] inexistente. Faça login novamente."]);
}
$dbgStep('sessao com id');

$userId = (int)$_SESSION['user']['id'];
$dbg['userId'] = $userId;

$allowedKeys = ['username', 'name', 'phone', 'email', 'address', 'address_district', 'country', 'lang'];
$canBeEmpty = ['address']; // país/cidade vazios não apagam o valor salvo

$updates = [];
$params = [];
$sessionUpdates = [];

foreach ($_POST as $key => $value) {
    if (!in_array($key, $allowedKeys, true) || !is_scalar($value)) {
        continue;
    }

    $value = trim((string)$value);

    if ($value === '' && !in_array($key, $canBeEmpty, true)) {
        continue;
    }
    if ($key === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        respond(422, ["status" => "error", "message" => "E-mail inválido."]);
    }
    if ($key === 'lang' && !in_array($value, ['angola', 'brasil'], true)) {
        continue;
    }

    $updates[] = "`$key` = :$key";
    $params[":$key"] = $value;
    $sessionUpdates[$key] = $value;
}

if (!empty($_SESSION['user']['image'])) {
    $img = basename((string)strtok((string)$_SESSION['user']['image'], '?'));
    if ($img !== '') {
        $updates[] = "`image` = :image";
        $params[":image"] = $img;
    }
}
$dbgStep('campos montados');

if (empty($updates)) {
    respond(200, ["status" => "error", "message" => "Nenhum dado válido para atualizar (o POST chegou sem campos permitidos)."]);
}

$sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
$params[":id"] = $userId;
$dbg['sql'] = $sql;
$dbg['params'] = $params;

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // garante que erros do MySQL virem exceção
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dbgStep('UPDATE executado');

    foreach ($sessionUpdates as $key => $value) {
        $_SESSION['user'][$key] = $value;
        if ($key === 'name') {
            $_SESSION['user']['nome'] = $value;
        }
    }

    $dbg['rowCount'] = $stmt->rowCount();
    respond(200, ["status" => "success", "rowsAffected" => $stmt->rowCount()]);
} catch (PDOException $e) {
    error_log('Erro ao atualizar perfil: ' . $e->getMessage());
    $dbg['pdo_code'] = $e->getCode();

    if (PERFIL_DEBUG) {
        respond(500, ["status" => "error", "message" => "MySQL: " . $e->getMessage()]);
    }

    if ($e->getCode() === '23000') {
        respond(409, ["status" => "error", "message" => "Este e-mail já está em uso por outra conta."]);
    }
    respond(500, ["status" => "error", "message" => "Erro ao atualizar perfil."]);
}
