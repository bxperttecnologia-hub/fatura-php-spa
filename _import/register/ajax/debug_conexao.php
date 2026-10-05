<?php
// register/ajax/debug_conexao.php
// FICHEIRO TEMPORÁRIO SÓ PARA DIAGNÓSTICO — apagar depois de usar.
// Abre isto diretamente no browser:
// http://localhost/projects/bxpert/workspace/fatura/public/register/ajax/debug_conexao.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1) A tentar carregar db.php...<br>";
try {
    require_once '../../../app/config/db.php';
    echo "✅ db.php carregado sem erros.<br>";

    if (isset($pdo) && $pdo instanceof PDO) {
        echo "✅ Variável \$pdo existe e é uma instância de PDO.<br>";
        $pdo->query("SELECT 1");
        echo "✅ Consulta de teste à base de dados funcionou.<br>";
    } else {
        echo "❌ db.php correu, mas não deixou uma variável \$pdo válida.<br>";
    }
} catch (Throwable $e) {
    echo "❌ ERRO ao carregar/usar db.php: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<br>2) A tentar carregar otp.php...<br>";
try {
    require_once '../../../app/helpers/otp.php';
    echo "✅ otp.php carregado sem erros.<br>";
} catch (Throwable $e) {
    echo "❌ ERRO ao carregar otp.php: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<br>3) A verificar se a tabela phone_verifications existe...<br>";
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->query("SELECT 1 FROM phone_verifications LIMIT 1");
        echo "✅ Tabela phone_verifications existe e é acessível.<br>";
    }
} catch (Throwable $e) {
    echo "❌ ERRO na tabela phone_verifications: " . htmlspecialchars($e->getMessage()) . "<br>";
}