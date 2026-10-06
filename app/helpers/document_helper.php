<?php

function document_types(): array
{
    return ['PF', 'FT', 'FR', 'NC', 'ND'];
}

function generate_document_number(PDO $pdo, int $companyId, string $type): string
{
    $year = date('Y');
    $type = strtoupper(trim($type));

    if (!in_array($type, document_types(), true)) {
        throw new InvalidArgumentException('Tipo de documento inválido.');
    }

    try {

        // 🔥 1. GARANTIR QUE EXISTE REGISTO (UPSERT SEGURO)
        $stmt = $pdo->prepare("
            INSERT INTO document_sequences (company_id, document_type, year, last_number)
            VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE id = id
        ");

        $stmt->execute([$companyId, $type, $year]);

        // 🔒 2. LOCK DA LINHA (AGORA SEMPRE EXISTE)
        $stmt = $pdo->prepare("
            SELECT last_number
            FROM document_sequences
            WHERE company_id = ?
              AND document_type = ?
              AND year = ?
            FOR UPDATE
        ");

        $stmt->execute([$companyId, $type, $year]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $number = ((int)$row['last_number']) + 1;

        // 🔥 3. UPDATE SEGURO
        $stmt = $pdo->prepare("
            UPDATE document_sequences
            SET last_number = ?
            WHERE company_id = ?
              AND document_type = ?
              AND year = ?
        ");

        $stmt->execute([
            $number,
            $companyId,
            $type,
            $year
        ]);

        return sprintf('%s%s/%06d', $type, $year, $number);
    } catch (Throwable $e) {
        throw $e;
    }
}

function is_proforma(string $type): bool
{
    return strtoupper($type) === 'PF';
}

function allow_stock_movement(string $type): bool
{
    return strtoupper($type) !== 'PF';
}
