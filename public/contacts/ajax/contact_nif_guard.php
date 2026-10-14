<?php

function contact_normalize_nif_address(string $address): string
{
    $address = trim($address);
    return function_exists('mb_strtoupper') ? mb_strtoupper($address, 'UTF-8') : strtoupper($address);
}

function contact_nif_lock_name(int $companyId, string $contributor, string $address): string
{
    return 'contact_nif_address_' . sha1(
        $companyId . '|' . strtoupper(trim($contributor)) . '|' . contact_normalize_nif_address($address)
    );
}

function contact_acquire_nif_lock(PDO $pdo, string $lockName): bool
{
    $stmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 5)');
    $stmt->execute([':lock_name' => $lockName]);
    return (int) $stmt->fetchColumn() === 1;
}

function contact_nif_address_exists(PDO $pdo, int $companyId, string $contributor, string $address, ?int $excludeId = null): bool
{
    $sql = 'SELECT 1 FROM contact
            WHERE company_id = :company_id
              AND UPPER(TRIM(contributor)) = :contributor
              AND UPPER(TRIM(address)) = :address';
    $params = [
        ':company_id' => $companyId,
        ':contributor' => strtoupper(trim($contributor)),
        ':address' => contact_normalize_nif_address($address),
    ];
    if ($excludeId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() !== false;
}

function contact_release_nif_lock(PDO $pdo, string $lockName): void
{
    $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $stmt->execute([':lock_name' => $lockName]);
}
