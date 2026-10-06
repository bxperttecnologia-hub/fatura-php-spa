<?php
// Uso: php bin/reset_password.php <username|email> <nova-palavra-passe>
// (Substitui o antigo create_user.php, que inseria na tabela demo `users(nome,password_hash)`.
//  Os utilizadores reais vivem em users + company_has_user e criam-se pelo registo da app.)
require __DIR__ . '/../app/core/bootstrap.php';
[$_, $who, $pass] = $argv + [null, null, null];
if (!$who || !$pass) exit("Uso: php bin/reset_password.php <username|email> <nova-palavra-passe>\n");
$st = Database::pdo()->prepare('UPDATE users SET password = ? WHERE username = ? OR email = ?');
$st->execute([password_hash($pass, PASSWORD_DEFAULT), $who, $who]);
echo $st->rowCount() ? "Palavra-passe actualizada.\n" : "Utilizador não encontrado.\n";
