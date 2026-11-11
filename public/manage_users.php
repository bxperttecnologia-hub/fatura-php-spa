<?php
// Endereço antigo da gestão de utilizadores: redireciona para a rota da SPA.
$companyId = filter_var($_GET['company_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
header('Location: /users' . ($companyId ? '?company_id=' . $companyId : ''), true, 301);
exit;
