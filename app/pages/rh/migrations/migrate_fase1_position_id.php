<?php
/**
 * FASE 1 — Migração de dados: preenche employees.position_id a partir
 * do texto livre em employees.position.
 *
 * Corre DEPOIS de sql/fase1_migration.sql (que cria as colunas/FKs) e
 * ANTES de colocar em produção o código que já assume position_id
 * (search_employees.php, delete_position.php, employees.php, etc.).
 *
 * Uso (linha de comandos, no servidor):
 *   php rh/migrations/migrate_fase1_position_id.php            -> aplica
 *   php rh/migrations/migrate_fase1_position_id.php --dry-run  -> só mostra o que faria, não grava nada
 *
 * Idempotente: pode ser corrido várias vezes em segurança — só toca em
 * funcionários com position_id ainda NULL. Não apaga nada.
 *
 * O QUE FAZ, por empresa (company_id):
 *   1. Lista os valores distintos de employees.position (texto, TRIM,
 *      não vazio) que ainda não têm position_id atribuído.
 *   2. Para cada valor, procura em positions (mesma company_id) um
 *      registo com TRIM(name) igual (case-sensitive, como o resto do
 *      sistema já compara). Se não encontrar, CRIA uma position nova
 *      com esse nome e os restantes campos a 0 (fica marcada para o
 *      utilizador rever suggested_salary/subsídios depois).
 *   3. Atualiza employees.position_id para todos os funcionários dessa
 *      empresa com aquele texto de cargo.
 *   4. employees.position (texto) NÃO é alterado nem apagado.
 */

require_once __DIR__ . '/../../../app/config/db.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser corrido via linha de comandos (CLI), não via browser.\n");
}

echo "== Migração Fase 1: employees.position -> employees.position_id ==\n";
echo $dryRun ? "(modo --dry-run: nada será gravado)\n\n" : "\n";

$stats = [
    'positions_created' => 0,
    'employees_updated' => 0,
    'employees_skipped_empty' => 0,
    'companies_processed' => 0,
];

try {
    $pdo->beginTransaction();

    // Todas as empresas com pelo menos um funcionário sem position_id
    // e com texto de cargo preenchido.
    $companies = $pdo->query("
        SELECT DISTINCT company_id
        FROM employees
        WHERE position_id IS NULL
          AND TRIM(COALESCE(position, '')) <> ''
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($companies as $companyId) {
        $stats['companies_processed']++;
        echo "-- Empresa #{$companyId}\n";

        // Valores distintos de cargo (texto) por preencher nesta empresa.
        $stmtDistinct = $pdo->prepare("
            SELECT DISTINCT TRIM(position) AS position_text
            FROM employees
            WHERE company_id = ?
              AND position_id IS NULL
              AND TRIM(COALESCE(position, '')) <> ''
        ");
        $stmtDistinct->execute([$companyId]);
        $positionTexts = $stmtDistinct->fetchAll(PDO::FETCH_COLUMN);

        foreach ($positionTexts as $positionText) {
            // Procura a position correspondente (mesmo padrão de
            // comparação usado em search_employees.php: TRIM nos dois
            // lados, mesma company_id).
            $stmtFind = $pdo->prepare("
                SELECT id FROM positions
                WHERE company_id = ? AND TRIM(name) = ?
                LIMIT 1
            ");
            $stmtFind->execute([$companyId, $positionText]);
            $positionId = $stmtFind->fetchColumn();

            if (!$positionId) {
                echo "   [criar] position \"{$positionText}\" não existe — a criar.\n";
                if (!$dryRun) {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO positions
                            (company_id, name, suggested_salary, food_allowance, transport_allowance, vacation_subsidy_pct, thirteenth_subsidy_pct)
                        VALUES (?, ?, 0, 0, 0, 0, 0)
                    ");
                    $stmtIns->execute([$companyId, $positionText]);
                    $positionId = $pdo->lastInsertId();
                }
                $stats['positions_created']++;
            }

            echo "   [ligar] \"{$positionText}\" -> position_id " . ($positionId ?: '(dry-run, não gerado)') . "\n";

            if (!$dryRun && $positionId) {
                $stmtUpd = $pdo->prepare("
                    UPDATE employees
                    SET position_id = ?
                    WHERE company_id = ?
                      AND position_id IS NULL
                      AND TRIM(position) = ?
                ");
                $stmtUpd->execute([$positionId, $companyId, $positionText]);
                $stats['employees_updated'] += $stmtUpd->rowCount();
            }
        }

        // Conta (só para o relatório) quantos ficam sem cargo — não é
        // erro, apenas informação: employees.position estava vazio.
        $stmtEmpty = $pdo->prepare("
            SELECT COUNT(*) FROM employees
            WHERE company_id = ? AND TRIM(COALESCE(position, '')) = ''
        ");
        $stmtEmpty->execute([$companyId]);
        $stats['employees_skipped_empty'] += (int)$stmtEmpty->fetchColumn();
    }

    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "ERRO: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n== Resumo ==\n";
echo "Empresas processadas: {$stats['companies_processed']}\n";
echo "Positions criadas: {$stats['positions_created']}\n";
echo "Funcionários com position_id atualizado: {$stats['employees_updated']}\n";
echo "Funcionários sem cargo (texto vazio, não tocados): {$stats['employees_skipped_empty']}\n";
echo $dryRun
    ? "\nNada foi gravado (--dry-run). Corre sem essa flag para aplicar.\n"
    : "\nConcluído.\n";
