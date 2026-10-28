<?php
declare(strict_types=1);

function companyDeletionQuote(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function companyDeletionScope(
    string $table,
    string $alias,
    int $companyId,
    array $companyTables,
    array $relations,
    array $preferredScopeParents,
    array $stack,
    int &$aliasCounter
): ?array {
    if ($table === 'companies') {
        return [
            'sql' => companyDeletionQuote($alias) . '.' . companyDeletionQuote('id') . ' = ?',
            'params' => [$companyId],
        ];
    }
    if (isset($companyTables[$table])) {
        return [
            'sql' => companyDeletionQuote($alias) . '.' . companyDeletionQuote('company_id') . ' = ?',
            'params' => [$companyId],
        ];
    }
    if (isset($stack[$table])) {
        return null;
    }
    $stack[$table] = true;
    $predicates = [];
    $params = [];

    foreach ($relations as $relation) {
        if (
            $relation['child_table'] !== $table
            || (isset($preferredScopeParents[$table]) && $relation['parent_table'] !== $preferredScopeParents[$table])
        ) {
            continue;
        }

        $parentAlias = 'scope_' . $aliasCounter++;
        $parentScope = companyDeletionScope(
            $relation['parent_table'],
            $parentAlias,
            $companyId,
            $companyTables,
            $relations,
            $preferredScopeParents,
            $stack,
            $aliasCounter
        );
        if ($parentScope === null) {
            continue;
        }

        $joins = [];
        foreach ($relation['child_columns'] as $index => $childColumn) {
            $joins[] = companyDeletionQuote($alias) . '.' . companyDeletionQuote($childColumn)
                . ' = ' . companyDeletionQuote($parentAlias) . '.'
                . companyDeletionQuote($relation['parent_columns'][$index]);
        }
        $predicates[] = 'EXISTS (SELECT 1 FROM '
            . companyDeletionQuote($relation['parent_table']) . ' AS '
            . companyDeletionQuote($parentAlias) . ' WHERE '
            . implode(' AND ', $joins) . ' AND (' . $parentScope['sql'] . '))';
        array_push($params, ...$parentScope['params']);
    }

    if (!$predicates) {
        return null;
    }
    if (!isset($preferredScopeParents[$table]) && count($predicates) > 1) {
        throw new RuntimeException(
            'A tabela ' . $table . ' tem mais de uma relação possível com a empresa; a eliminação foi cancelada para evitar apagar dados de outra empresa.'
        );
    }
    return ['sql' => '(' . implode(' OR ', $predicates) . ')', 'params' => $params];
}

function companyDeletionPlan(PDO $pdo, int $companyId): array
{
    $tableRows = $pdo->query(
        "SELECT TABLE_NAME, ENGINE
           FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    )->fetchAll(PDO::FETCH_ASSOC);
    $engines = [];
    foreach ($tableRows as $row) {
        $engines[(string)$row['TABLE_NAME']] = strtoupper((string)($row['ENGINE'] ?? ''));
    }
    if (($engines['companies'] ?? '') !== 'INNODB') {
        throw new RuntimeException('A tabela de empresas não suporta uma eliminação transaccional segura.');
    }

    $columnRows = $pdo->query(
        "SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
          ORDER BY TABLE_NAME, ORDINAL_POSITION"
    )->fetchAll(PDO::FETCH_ASSOC);
    $columns = [];
    $companyTables = [];
    foreach ($columnRows as $row) {
        $table = (string)$row['TABLE_NAME'];
        $column = (string)$row['COLUMN_NAME'];
        $columns[$table][$column] = (string)$row['IS_NULLABLE'];
        if ($column === 'company_id' && !in_array($table, ['companies', 'users'], true)) {
            $companyTables[$table] = true;
        }
    }

    $relationRows = $pdo->query(
        "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
           FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
          ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION"
    )->fetchAll(PDO::FETCH_ASSOC);
    $groupedRelations = [];
    foreach ($relationRows as $row) {
        $key = (string)$row['TABLE_NAME'] . "\0" . (string)$row['CONSTRAINT_NAME'];
        $groupedRelations[$key]['child_table'] = (string)$row['TABLE_NAME'];
        $groupedRelations[$key]['parent_table'] = (string)$row['REFERENCED_TABLE_NAME'];
        $groupedRelations[$key]['child_columns'][] = (string)$row['COLUMN_NAME'];
        $groupedRelations[$key]['parent_columns'][] = (string)$row['REFERENCED_COLUMN_NAME'];
    }

    $relations = array_values($groupedRelations);
    $knownRelations = [];
    foreach ($relations as $relation) {
        $knownRelations[json_encode($relation)] = true;
    }

    $inferredRelations = [
        ['child_table' => 'guides', 'child_column' => 'contact_id', 'parent_table' => 'contact'],
        ['child_table' => 'guide_items', 'child_column' => 'guide_id', 'parent_table' => 'guides'],
        ['child_table' => 'guide_items', 'child_column' => 'item_id', 'parent_table' => 'items'],
        ['child_table' => 'invoice_items', 'child_column' => 'invoice_id', 'parent_table' => 'invoices'],
        ['child_table' => 'invoice_items', 'child_column' => 'item_id', 'parent_table' => 'items'],
        ['child_table' => 'receipts', 'child_column' => 'invoice_id', 'parent_table' => 'invoices'],
        ['child_table' => 'credit_note_items', 'child_column' => 'credit_note_id', 'parent_table' => 'credit_notes'],
        ['child_table' => 'credit_note_items', 'child_column' => 'item_id', 'parent_table' => 'items'],
        ['child_table' => 'debit_note_items', 'child_column' => 'debit_note_id', 'parent_table' => 'debit_notes'],
        ['child_table' => 'debit_note_items_bak_20261028', 'child_column' => 'debit_note_id', 'parent_table' => 'debit_notes_bak_20261028'],
        ['child_table' => 'delivery_note_items', 'child_column' => 'delivery_note_id', 'parent_table' => 'delivery_notes'],
        ['child_table' => 'delivery_note_items', 'child_column' => 'item_id', 'parent_table' => 'items'],
        ['child_table' => 'proforma_items', 'child_column' => 'proforma_id', 'parent_table' => 'proformas'],
        ['child_table' => 'proforma_items', 'child_column' => 'item_id', 'parent_table' => 'items'],
        ['child_table' => 'evaluations', 'child_column' => 'cycle_id', 'parent_table' => 'evaluation_cycles'],
        ['child_table' => 'evaluations', 'child_column' => 'employee_id', 'parent_table' => 'employees'],
        ['child_table' => 'evaluations', 'child_column' => 'template_id', 'parent_table' => 'evaluation_templates'],
        ['child_table' => 'evaluation_answers', 'child_column' => 'evaluation_id', 'parent_table' => 'evaluations'],
        ['child_table' => 'evaluation_answers', 'child_column' => 'criterion_id', 'parent_table' => 'evaluation_criteria'],
        ['child_table' => 'evaluation_criteria', 'child_column' => 'template_id', 'parent_table' => 'evaluation_templates'],
        ['child_table' => 'stock_items', 'child_column' => 'stock_id', 'parent_table' => 'stocks'],
        ['child_table' => 'stock_items', 'child_column' => 'item_id', 'parent_table' => 'items'],
        ['child_table' => 'copilot_messages', 'child_column' => 'conversation_id', 'parent_table' => 'copilot_conversations'],
    ];
    foreach ($inferredRelations as $inferred) {
        $childTable = $inferred['child_table'];
        $childColumn = $inferred['child_column'];
        $parentTable = $inferred['parent_table'];
        if (
            !isset($columns[$childTable][$childColumn])
            || !isset($columns[$parentTable]['id'])
        ) {
            continue;
        }
        $relation = [
            'child_table' => $childTable,
            'parent_table' => $parentTable,
            'child_columns' => [$childColumn],
            'parent_columns' => ['id'],
        ];
        $key = json_encode($relation);
        if (!isset($knownRelations[$key])) {
            $relations[] = $relation;
            $knownRelations[$key] = true;
        }
    }

    $preferredScopeParents = [
        'guide_items' => 'guides',
        'invoice_items' => 'invoices',
        'credit_note_items' => 'credit_notes',
        'debit_note_items' => 'debit_notes',
        'debit_note_items_bak_20261028' => 'debit_notes_bak_20261028',
        'delivery_note_items' => 'delivery_notes',
        'proforma_items' => 'proformas',
        'evaluation_answers' => 'evaluations',
        'stock_items' => 'stocks',
        'copilot_messages' => 'copilot_conversations',
    ];

    $scopes = [];
    $aliasCounter = 0;
    foreach (array_keys($engines) as $table) {
        if (in_array($table, ['companies', 'users'], true)) {
            continue;
        }
        $scope = companyDeletionScope(
            $table,
            'target_row',
            $companyId,
            $companyTables,
            $relations,
            $preferredScopeParents,
            [],
            $aliasCounter
        );
        if ($scope !== null) {
            $scopes[$table] = $scope;
        }
    }
    foreach ($preferredScopeParents as $table => $parentTable) {
        if (
            isset($engines[$table])
            && !isset($companyTables[$table])
            && !isset($scopes[$table])
        ) {
            throw new RuntimeException(
                'Não foi possível associar com segurança os registos de ' . $table
                . ' à empresa através de ' . $parentTable . '; a eliminação foi cancelada.'
            );
        }
    }

    foreach (array_keys($scopes) as $table) {
        if (($engines[$table] ?? '') !== 'INNODB') {
            throw new RuntimeException('A tabela ' . $table . ' não suporta uma eliminação transaccional segura.');
        }
    }

    $selfReferenceUpdates = [];
    foreach ($relations as $relation) {
        $table = $relation['child_table'];
        if (
            $table !== $relation['parent_table']
            || !isset($companyTables[$table])
            || count($relation['child_columns']) !== 1
            || ($columns[$table][$relation['child_columns'][0]] ?? 'NO') !== 'YES'
        ) {
            continue;
        }
        $selfReferenceUpdates[$table][$relation['child_columns'][0]] = true;
    }

    $clearUserCompanyReference = false;
    if (isset($columns['users']['company_id'])) {
        if ($columns['users']['company_id'] === 'YES') {
            $clearUserCompanyReference = true;
        } else {
            $userCompanyCount = $pdo->prepare('SELECT COUNT(*) FROM users WHERE company_id = ?');
            $userCompanyCount->execute([$companyId]);
            if ((int)$userCompanyCount->fetchColumn() > 0) {
                throw new RuntimeException(
                    'Existem contas de utilizadores associadas directamente à empresa por uma coluna obrigatória. Para preservar essas contas, a eliminação foi cancelada.'
                );
            }
        }
    }

    $dependencies = array_fill_keys(array_keys($scopes), []);
    foreach ($relations as $relation) {
        $child = $relation['child_table'];
        $parent = $relation['parent_table'];
        if ($child !== $parent && isset($scopes[$child], $scopes[$parent])) {
            $dependencies[$parent][$child] = true;
        }
    }

    $deleteOrder = [];
    while (count($deleteOrder) < count($scopes)) {
        $readyTable = null;
        foreach ($dependencies as $table => $children) {
            if (!in_array($table, $deleteOrder, true) && !$children) {
                $readyTable = $table;
                break;
            }
        }
        if ($readyTable === null) {
            throw new RuntimeException('Existem dependências circulares entre tabelas; a eliminação foi cancelada sem apagar dados.');
        }
        $deleteOrder[] = $readyTable;
        foreach ($dependencies as &$children) {
            unset($children[$readyTable]);
        }
        unset($children);
    }

    return [
        'scopes' => $scopes,
        'delete_order' => $deleteOrder,
        'self_reference_updates' => $selfReferenceUpdates,
        'clear_user_company_reference' => $clearUserCompanyReference,
    ];
}
