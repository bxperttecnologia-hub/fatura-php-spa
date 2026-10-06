<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
require_once '../../../vendor/autoload.php';

session_start();
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;

function sendMailDefault(string $toEmail, string $toName, string $subject, string $html): void {
    $mail = new PHPMailer(true);

    // SMTP Hostinger
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'suporte@guislab.com';
    $mail->Password = 'Gu!gu!145145';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $fromEmail = 'suporte@guislab.com';
    $fromName = 'Guislab | Sistema Fatura';

    $mail->CharSet = 'UTF-8';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($toEmail, $toName ?: $toEmail);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = strip_tags($html);

    // Se falhar, não quebrar o cadastro/vínculo
    try {
        $mail->send();
    } catch (Exception $e) {
        // silencioso (ideal: logar em arquivo)
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido.');
    }

    if (!isset($_SESSION['user']['id'])) {
        throw new Exception('Usuário não autenticado.');
    }

    $company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    if (!$company_id) {
        throw new Exception('company_id é obrigatório.');
    }

    $requester_id = (int)$_SESSION['user']['id'];
    $stmt = $pdo->prepare("SELECT role FROM company_has_user WHERE user_id = :user_id AND company_id = :company_id");
    $stmt->execute(['user_id' => $requester_id, 'company_id' => $company_id]);
    $requester_role = $stmt->fetchColumn();
    if ($requester_role !== 'owner' && $requester_role !== 'admin') {
        throw new Exception('Acesso negado.');
    }

    // Assinatura/Plano: bloquear criação de usuários se expirado e respeitar limite
    subscription_assert_active($pdo, $company_id);
    subscription_check_limit($pdo, $company_id, 'user');

    $stmt = $pdo->prepare('SELECT name FROM companies WHERE id = :id');
    $stmt->execute(['id' => $company_id]);
    $company_name = (string)($stmt->fetchColumn() ?: '');

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = trim((string)($_POST['role'] ?? 'employee'));
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if ($name === '' || $email === '') {
        throw new Exception('Nome e email são obrigatórios.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido.');
    }

    $allowedRoles = ['owner','admin','employee','viewer'];
    if (!in_array($role, $allowedRoles, true)) {
        throw new Exception('Role inválida.');
    }

    // Não permitir criação de owner via UI
    if ($role === 'owner') {
        throw new Exception('Não é permitido cadastrar colaborador como owner.');
    }

    // Se não vier username, gera um baseado no email
    if ($username === '') {
        $base = preg_replace('/[^a-z0-9_\.]/i', '', strtolower(explode('@', $email)[0] ?? 'user'));
        if ($base === '') $base = 'user';
        $username = $base;
    }

    $created_at = date('Y-m-d H:i:s');

    $pdo->beginTransaction();

    // 1) Procura usuário por email (regra: email é único no sistema)
    $stmt = $pdo->prepare('SELECT id, username, name FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

    $isNewUser = false;
    $finalUsername = null;
    $generatedPassword = null;

    if ($existing_user) {
        $user_id = (int)$existing_user['id'];

        // Se já está vinculado à empresa → não faz nada (evita duplicar)
        $stmt = $pdo->prepare('SELECT id FROM company_has_user WHERE company_id = :company_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['company_id' => $company_id, 'user_id' => $user_id]);
        $link_id = (int)($stmt->fetchColumn() ?: 0);
        if ($link_id) {
            throw new Exception('Este email já está cadastrado nesta empresa.');
        }

        // vincula apenas
        $stmt = $pdo->prepare('INSERT INTO company_has_user (company_id, user_id, role, created_at) VALUES (:company_id, :user_id, :role, :created_at)');
        $stmt->execute([
            'company_id' => $company_id,
            'user_id' => $user_id,
            'role' => $role,
            'created_at' => $created_at,
        ]);

    } else {
        // 2) Criar usuário do zero
        $isNewUser = true;

        // Email não pode existir (já checamos), mas reforço: evita corrida
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new Exception('Já existe um usuário com este email.');
        }

        // Se não vier senha, gera uma temporária
        if ($password === '') {
            $password = bin2hex(random_bytes(4)); // 8 chars
            $generatedPassword = $password;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Username deve ser único no sistema
        $try = 0;
        $finalUsername = $username;
        while (true) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
            $stmt->execute(['u' => $finalUsername]);
            if ((int)$stmt->fetchColumn() === 0) break;
            $try++;
            $finalUsername = $username . $try;
            if ($try > 50) throw new Exception('Já existe um usuário com este username.');
        }

        $stmt = $pdo->prepare('INSERT INTO users (username, name, email, password, is_active, created_at) VALUES (:username, :name, :email, :password, :is_active, :created_at)');
        $stmt->execute([
            'username' => $finalUsername,
            'name' => $name,
            'email' => $email,
            'password' => $password_hash,
            'is_active' => $is_active,
            'created_at' => $created_at,
        ]);
        $user_id = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO company_has_user (company_id, user_id, role, created_at) VALUES (:company_id, :user_id, :role, :created_at)');
        $stmt->execute([
            'company_id' => $company_id,
            'user_id' => $user_id,
            'role' => $role,
            'created_at' => $created_at,
        ]);
    }

    $pdo->commit();

    // 3) Disparo de email (mail() padrão via PHPMailer)
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $loginUrl = $host ? ('https://' . $host . '/login.php') : '';

    $brand = 'Guislab';
    $appName = 'Sistema Fatura';

    $baseCss = "
        <style>
          body{margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#111827;}
          .wrap{width:100%;padding:24px 12px;}
          .card{max-width:620px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 8px 24px rgba(17,24,39,.06)}
          .header{background:linear-gradient(135deg,#0ea5e9,#2563eb);padding:18px 22px;color:#fff}
          .header h1{margin:0;font-size:18px;letter-spacing:.2px}
          .content{padding:22px}
          .h{font-size:16px;margin:0 0 8px 0}
          .p{font-size:14px;line-height:1.6;margin:0 0 14px 0;color:#374151}
          .kv{background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin:14px 0}
          .kv p{margin:0 0 8px 0;font-size:13px;color:#374151}
          .kv code{display:inline-block;background:#111827;color:#fff;padding:2px 8px;border-radius:8px;font-size:12px}
          .btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:700;font-size:14px}
          .note{font-size:12px;color:#6b7280;line-height:1.6}
          .footer{padding:16px 22px;border-top:1px solid #e5e7eb;background:#fafafa;font-size:12px;color:#6b7280}
        </style>
    ";

    if ($isNewUser) {
        $subject = 'Bem-vindo! Sua conta foi criada' . ($company_name ? " - $company_name" : '');

        $html = "<!doctype html><html><head><meta charset=\"utf-8\">$baseCss</head><body>"
            . "<div class=\"wrap\"><div class=\"card\">"
            . "<div class=\"header\"><h1>$brand • $appName</h1></div>"
            . "<div class=\"content\">"
            . "<p class=\"h\">Olá, " . htmlspecialchars($name) . "</p>"
            . ($company_name ? "<p class=\"p\">Você foi adicionado(a) à empresa <b>" . htmlspecialchars($company_name) . "</b>.</p>" : '')
            . "<p class=\"p\">Aqui estão seus dados de acesso:</p>"
            . "<div class=\"kv\">"
            . "<p><b>Username:</b> <code>" . htmlspecialchars($finalUsername ?: '') . "</code></p>"
            . "<p><b>Email:</b> <code>" . htmlspecialchars($email) . "</code></p>";

        if ($generatedPassword) {
            $html .= "<p><b>Senha temporária:</b> <code>" . htmlspecialchars($generatedPassword) . "</code></p>";
        } else {
            $html .= "<p><b>Senha:</b> (a que foi definida no cadastro)</p>";
        }

        $html .= "</div>";

        if ($loginUrl) {
            $html .= "<p class=\"p\"><a class=\"btn\" href=\"" . htmlspecialchars($loginUrl) . "\">Acessar o sistema</a></p>";
        }

        $html .= "<p class=\"note\">Recomendação: altere sua senha após o primeiro acesso. Se você não reconhece este cadastro, ignore este email.</p>"
            . "</div><div class=\"footer\">Este email foi enviado automaticamente por $brand.</div>"
            . "</div></div></body></html>";

        sendMailDefault($email, $name, $subject, $html);

    } else {
        $subject = 'Acesso liberado em uma nova empresa' . ($company_name ? " - $company_name" : '');

        $html = "<!doctype html><html><head><meta charset=\"utf-8\">$baseCss</head><body>"
            . "<div class=\"wrap\"><div class=\"card\">"
            . "<div class=\"header\"><h1>$brand • $appName</h1></div>"
            . "<div class=\"content\">"
            . "<p class=\"h\">Olá, " . htmlspecialchars($name) . "</p>"
            . ($company_name ? "<p class=\"p\">Você agora tem acesso à empresa <b>" . htmlspecialchars($company_name) . "</b>.</p>" : "<p class=\"p\">Você foi vinculado(a) a uma nova empresa no $appName.</p>")
            . ($loginUrl ? "<p class=\"p\"><a class=\"btn\" href=\"" . htmlspecialchars($loginUrl) . "\">Acessar o sistema</a></p>" : '')
            . "<p class=\"note\">Se você não reconhece este acesso, responda este email ou entre em contato com o suporte.</p>"
            . "</div><div class=\"footer\">Este email foi enviado automaticamente por $brand.</div>"
            . "</div></div></body></html>";

        sendMailDefault($email, $name, $subject, $html);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $user_id,
            'email' => $email,
            'generated_password' => $generatedPassword,
        ]
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
