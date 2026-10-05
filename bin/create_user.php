<?php
// Uso: php bin/create_user.php "Nome" email@exemplo.com palavra-passe
require __DIR__ . '/../app/core/bootstrap.php';
[$_, $nome, $email, $pass] = $argv + [null, null, null, null];
if (!$nome || !$email || !$pass) exit("Uso: php bin/create_user.php Nome email password\n");
Database::pdo()->prepare('INSERT INTO users (nome,email,password_hash) VALUES (?,?,?)')
    ->execute([$nome, $email, password_hash($pass, PASSWORD_DEFAULT)]);
echo "Utilizador criado.\n";
