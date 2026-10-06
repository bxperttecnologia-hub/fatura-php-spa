<?php

require_once '../../../app/config/db.php';

session_start();

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$company_id) {
    http_response_code(401);
    exit('Sessão inválida.');
}

/*
|--------------------------------------------------------------------------
| INPUTS
|--------------------------------------------------------------------------
*/

$id = $_POST['id'] ?? $_POST['editid'] ?? null;
$id = !empty($id) ? (int)$id : null;

$name            = trim($_POST['employee_name'] ?? '');
$bi              = trim($_POST['bi'] ?? '');
$email           = trim($_POST['email'] ?? '');
$phone_ddi       = trim($_POST['phone_ddi'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$position        = trim($_POST['position'] ?? '');
$position_id     = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
$department_id   = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$manager_id      = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
$salary          = (float)($_POST['salary'] ?? 0);
$status          = trim($_POST['status'] ?? 'ativo');

$document_type   = trim($_POST['document_type'] ?? '');
$birth_date      = $_POST['birth_date'] ?? null;
$marital_status  = trim($_POST['marital_status'] ?? '');
$academic_level  = trim($_POST['academic_level'] ?? '');
$contract_type   = trim($_POST['contract_type'] ?? '');
$admission_date  = $_POST['admission_date'] ?? null;

// IBAN de Angola: "AO" + 23 dígitos = 25 caracteres no total. Normaliza
// removendo espaços/maiúsculas antes de validar e gravar (o frontend já
// formata com espaços agrupados de 4 em 4 para leitura, mas a base de
// dados guarda sempre a versão compacta, sem espaços).
$iban = strtoupper(trim($_POST['iban'] ?? ''));
$iban = preg_replace('/\s+/', '', $iban);

/*
|--------------------------------------------------------------------------
| VALIDATIONS
|--------------------------------------------------------------------------
*/

if (empty($name)) {
    http_response_code(400);
    exit('Nome obrigatório.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('E-mail inválido.');
}

// IBAN é opcional, mas se preenchido tem de respeitar o formato angolano:
// "AO" + 23 dígitos = 25 caracteres no total (ex: AO06000600001234567890154).
if ($iban !== '' && !preg_match('/^AO\d{23}$/', $iban)) {
    http_response_code(400);
    exit('IBAN inválido. O formato de Angola é "AO" seguido de 23 dígitos (25 caracteres no total).');
}

if (empty($position) && !$position_id) {
    http_response_code(400);
    exit('Cargo obrigatório.');
}

/*
|--------------------------------------------------------------------------
| FASE 1: RESOLUÇÃO DE CARGO / DEPARTAMENTO / CHEFIA
|--------------------------------------------------------------------------
| - Se o formulário já envia position_id (select de cargo), usa-o como
|   fonte de verdade e sincroniza employees.position (texto) a partir do
|   nome do cargo, só como cache de leitura para telas antigas.
| - Se só vier texto livre (formulário ainda não migrado), tenta encontrar
|   o position_id correspondente por TRIM()+case-insensitive; se não achar,
|   fica null (não cria cargo automaticamente aqui — isso é feito só na
|   migração em lote, migrations/fase1_migrate_positions.php).
*/

if ($position_id) {
    $stmtPos = $pdo->prepare('SELECT name FROM positions WHERE id = ? AND company_id = ?');
    $stmtPos->execute([$position_id, $company_id]);
    $posRow = $stmtPos->fetch(PDO::FETCH_ASSOC);
    if (!$posRow) {
        http_response_code(400);
        exit('Cargo inválido.');
    }
    $position = $posRow['name'];
} elseif ($position !== '') {
    $stmtPos = $pdo->prepare('SELECT id FROM positions WHERE company_id = ? AND TRIM(name) = TRIM(?)');
    $stmtPos->execute([$company_id, $position]);
    $foundId = $stmtPos->fetchColumn();
    if ($foundId) {
        $position_id = (int)$foundId;
    }
}

if ($department_id) {
    $stmtDep = $pdo->prepare('SELECT COUNT(*) FROM departments WHERE id = ? AND company_id = ?');
    $stmtDep->execute([$department_id, $company_id]);
    if (!$stmtDep->fetchColumn()) {
        http_response_code(400);
        exit('Departamento inválido.');
    }
}

if ($manager_id) {
    if ($id && $manager_id === $id) {
        http_response_code(400);
        exit('Um funcionário não pode ser a sua própria chefia direta.');
    }
    $stmtMgr = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE id = ? AND company_id = ?');
    $stmtMgr->execute([$manager_id, $company_id]);
    if (!$stmtMgr->fetchColumn()) {
        http_response_code(400);
        exit('Chefia direta inválida.');
    }
}

/*
|--------------------------------------------------------------------------
| UPLOAD PATHS
|--------------------------------------------------------------------------
*/

$uploadImgDir = __DIR__ . '/../../assets/img/employees/';
$uploadDocDir = __DIR__ . '/../../assets/docs/employees/';

if (!is_dir($uploadImgDir)) {
    mkdir($uploadImgDir, 0775, true);
}

if (!is_dir($uploadDocDir)) {
    mkdir($uploadDocDir, 0775, true);
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

// Extensão -> lista de MIME types reais aceites para essa extensão.
// A extensão sozinha é só o que está escrito no nome do ficheiro; quem faz
// upload pode renomear qualquer ficheiro para .jpg. finfo_file() lê a
// assinatura real do ficheiro no disco, que é o que importa para segurança.
const RH_ALLOWED_MIME_BY_EXT = [
    'png'  => ['image/png'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'webp' => ['image/webp'],
    'gif'  => ['image/gif'],
    'pdf'  => ['application/pdf'],
];

function saveUpload($fileKey, $destDir, array $allowedExts)
{
    if (
        !isset($_FILES[$fileKey]) ||
        $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK
    ) {
        return null;
    }

    $tmp  = $_FILES[$fileKey]['tmp_name'];
    $orig = $_FILES[$fileKey]['name'];

    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts, true)) {
        throw new Exception("Formato inválido para {$fileKey}");
    }

    // Valida o MIME real do ficheiro (não apenas a extensão do nome).
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = $finfo ? finfo_file($finfo, $tmp) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    $expectedMimes = RH_ALLOWED_MIME_BY_EXT[$ext] ?? [];
    if (!$realMime || !in_array($realMime, $expectedMimes, true)) {
        throw new Exception("O ficheiro enviado para {$fileKey} não corresponde a um {$ext} válido.");
    }

    $fileName = uniqid($fileKey . '_', true) . '.' . $ext;

    $dest = rtrim($destDir, '/') . '/' . $fileName;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new Exception("Erro ao salvar {$fileKey}");
    }

    return $fileName;
}

function removeFileIfExists($dir, $file)
{
    if (!$file) return;

    $path = rtrim($dir, '/') . '/' . basename($file);

    if (is_file($path)) {
        @unlink($path);
    }
}

/*
|--------------------------------------------------------------------------
| TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | UPLOADS
    |--------------------------------------------------------------------------
    */

    $newPhoto = saveUpload(
        'photo',
        $uploadImgDir,
        ['png', 'jpg', 'jpeg', 'webp', 'gif']
    );

    $newDoc1 = saveUpload(
        'doc1',
        $uploadDocDir,
        ['pdf', 'png', 'jpg', 'jpeg', 'webp']
    );

    $newDoc2 = saveUpload(
        'doc2',
        $uploadDocDir,
        ['pdf', 'png', 'jpg', 'jpeg', 'webp']
    );

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    if ($id) {

        $stmtOld = $pdo->prepare("
            SELECT 
                photo_url,
                doc1_url,
                doc2_url
            FROM employees
            WHERE id = ?
            AND company_id = ?
        ");

        $stmtOld->execute([$id, $company_id]);

        $old = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$old) {
            throw new Exception('Funcionário não encontrado.');
        }

        $photoUrl = $newPhoto ?: $old['photo_url'];
        $doc1Url  = $newDoc1 ?: $old['doc1_url'];
        $doc2Url  = $newDoc2 ?: $old['doc2_url'];

        /*
        |--------------------------------------------------------------------------
        | REMOVE OLD FILES
        |--------------------------------------------------------------------------
        */

        if ($newPhoto && $old['photo_url']) {
            removeFileIfExists($uploadImgDir, $old['photo_url']);
        }

        if ($newDoc1 && $old['doc1_url']) {
            removeFileIfExists($uploadDocDir, $old['doc1_url']);
        }

        if ($newDoc2 && $old['doc2_url']) {
            removeFileIfExists($uploadDocDir, $old['doc2_url']);
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE QUERY
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE employees SET

                name = :name,
                bi = :bi,
                email = :email,
                phone_ddi = :phone_ddi,
                phone = :phone,
                position = :position,
                position_id = :position_id,
                department_id = :department_id,
                manager_id = :manager_id,
                salary_base = :salary,
                status = :status,

                document_type = :document_type,
                birth_date = :birth_date,
                marital_status = :marital_status,
                academic_level = :academic_level,
                contract_type = :contract_type,
                admission_date = :admission_date,
                iban = :iban,

                photo_url = :photo_url,
                doc1_url = :doc1_url,
                doc2_url = :doc2_url

            WHERE id = :id
            AND company_id = :company_id
        ");

        $stmt->execute([

            ':name'            => $name,
            ':bi'              => $bi,
            ':email'           => $email,
            ':phone_ddi'       => $phone_ddi,
            ':phone'           => $phone,
            ':position'        => $position,
            ':position_id'     => $position_id,
            ':department_id'   => $department_id,
            ':manager_id'      => $manager_id,
            ':salary'          => $salary,
            ':status'          => $status,

            ':document_type'   => $document_type,
            ':birth_date'      => $birth_date,
            ':marital_status'  => $marital_status,
            ':academic_level'  => $academic_level,
            ':contract_type'   => $contract_type,
            ':admission_date'  => $admission_date,
            ':iban'            => $iban,

            ':photo_url'       => $photoUrl,
            ':doc1_url'        => $doc1Url,
            ':doc2_url'        => $doc2Url,

            ':id'              => $id,
            ':company_id'      => $company_id

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */ else {

        $stmt = $pdo->prepare("
            INSERT INTO employees (

                company_id,
                name,
                bi,
                email,
                phone_ddi,
                phone,
                position,
                position_id,
                department_id,
                manager_id,
                salary_base,
                status,

                document_type,
                birth_date,
                marital_status,
                academic_level,
                contract_type,
                admission_date,
                iban,

                photo_url,
                doc1_url,
                doc2_url

            ) VALUES (

                :company_id,
                :name,
                :bi,
                :email,
                :phone_ddi,
                :phone,
                :position,
                :position_id,
                :department_id,
                :manager_id,
                :salary,
                :status,

                :document_type,
                :birth_date,
                :marital_status,
                :academic_level,
                :contract_type,
                :admission_date,
                :iban,

                :photo_url,
                :doc1_url,
                :doc2_url

            )
        ");

        $stmt->execute([

            ':company_id'      => $company_id,
            ':name'            => $name,
            ':bi'              => $bi,
            ':email'           => $email,
            ':phone_ddi'       => $phone_ddi,
            ':phone'           => $phone,
            ':position'        => $position,
            ':position_id'     => $position_id,
            ':department_id'   => $department_id,
            ':manager_id'      => $manager_id,
            ':salary'          => $salary,
            ':status'          => $status,

            ':document_type'   => $document_type,
            ':birth_date'      => $birth_date,
            ':marital_status'  => $marital_status,
            ':academic_level'  => $academic_level,
            ':contract_type'   => $contract_type,
            ':admission_date'  => $admission_date,
            ':iban'            => $iban,

            ':photo_url'       => $newPhoto,
            ':doc1_url'        => $newDoc1,
            ':doc2_url'        => $newDoc2

        ]);

        $id = $pdo->lastInsertId();
    }

    $pdo->commit();

    // Fase 2, item 6: alerta (não bloqueia) se o salário base ficar abaixo
    // do salário mínimo nacional vigente, configurado por empresa em
    // rh_settings (nunca fixo no código).
    $salaryWarning = null;
    $stmtMin = $pdo->prepare('SELECT salario_minimo_nacional FROM rh_settings WHERE company_id = ?');
    $stmtMin->execute([$company_id]);
    $salarioMinimo = $stmtMin->fetchColumn();
    if ($salarioMinimo !== false && $salary < (float)$salarioMinimo) {
        $salaryWarning = 'Atenção: o salário base (Kz ' . number_format($salary, 2, ',', '.') . ') está abaixo do salário mínimo nacional configurado (Kz ' . number_format((float)$salarioMinimo, 2, ',', '.') . ').';
    }

    echo json_encode([
        'success' => true,
        'id'      => $id,
        'message' => $id ? 'Funcionário salvo com sucesso.' : 'Erro.',
        'warning' => $salaryWarning
    ]);
} catch (Exception $e) {

    $pdo->rollBack();

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
