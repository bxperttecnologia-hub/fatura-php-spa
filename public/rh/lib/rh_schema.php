<?php
/**
 * Esquema do módulo de RH (v2).
 *
 * rh_schema_migrate() só ACRESCENTA o que falta (colunas, tabelas, índices) — nunca apaga
 * nem altera dados. Pode ser corrida várias vezes. Usada pelo script CLI
 * rh/migrations/migrate_rh_v2.php.
 *
 * Porquê: o código das "Fases 1/2" do RH assumia colunas (payroll.total_discounts,
 * employees.position_id, …) criadas por um sql/fase1_migration.sql que nunca chegou ao
 * repositório — em bases importadas sem elas, gravar folhas/funcionários falhava.
 */

function rh_schema_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function rh_schema_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function rh_schema_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

/** Lista das colunas que o módulo precisa: tabela => [coluna => definição]. */
function rh_schema_columns(): array
{
    return [
        'employees' => [
            'position_id'        => 'INT NULL',
            'department_id'      => 'INT NULL',
            'manager_id'         => 'INT NULL',
            'termination_type'   => 'VARCHAR(60) NULL',
            'termination_amount' => 'DECIMAL(12,2) NULL',
            'termination_notes'  => 'TEXT NULL',
            'nif'                => 'VARCHAR(30) NULL',
            'inss_number'        => 'VARCHAR(30) NULL',
            'bank_name'          => 'VARCHAR(100) NULL',
            'gender'             => 'VARCHAR(20) NULL',
            'address'            => 'VARCHAR(255) NULL',
            'portal_token'       => 'VARCHAR(64) NULL',
            'vacation_carry_days'=> 'DECIMAL(5,1) NOT NULL DEFAULT 0',
        ],
        'positions' => [
            'department_id' => 'INT NULL',
        ],
        'evaluations' => [
            'company_id' => 'INT NULL',
        ],
        'payroll' => [
            'total_discounts'     => 'DECIMAL(12,2) NULL',
            'inss_employer_value' => 'DECIMAL(12,2) NULL',
            'gross_salary'        => 'DECIMAL(12,2) NULL',
            'absence_days'        => 'DECIMAL(5,1) NOT NULL DEFAULT 0',
            'absence_value'       => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'vacation_subsidy'    => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'thirteenth_subsidy'  => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'overtime_hours'      => 'DECIMAL(6,2) NOT NULL DEFAULT 0',
            'overtime_value'      => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'other_earnings'      => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'advance_deduction'   => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'inss_base'           => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'irt_base'            => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'exempt_allowances'   => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'notes'               => 'TEXT NULL',
            'calc_version'        => 'TINYINT NOT NULL DEFAULT 1',
            'approved_at'         => 'DATETIME NULL',
            'approved_by'         => 'INT NULL',
            'emailed_at'          => 'DATETIME NULL',
        ],
        'rh_settings' => [
            'inss_employee_rate'        => 'DECIMAL(5,2) NOT NULL DEFAULT 3.00',
            'inss_employer_rate'        => 'DECIMAL(5,2) NOT NULL DEFAULT 8.00',
            'food_exempt_limit'         => 'DECIMAL(12,2) NOT NULL DEFAULT 30000.00',
            'transport_exempt_limit'    => 'DECIMAL(12,2) NOT NULL DEFAULT 30000.00',
            'inss_on_exempt_allowances' => 'TINYINT NOT NULL DEFAULT 0',
            'absence_divisor'           => 'TINYINT NOT NULL DEFAULT 30',
            'monthly_hours'             => 'DECIMAL(6,2) NOT NULL DEFAULT 176.00',
            'overtime_rate_1'           => 'DECIMAL(5,2) NOT NULL DEFAULT 50.00',
            'overtime_rate_2'           => 'DECIMAL(5,2) NOT NULL DEFAULT 75.00',
            'overtime_threshold_hours'  => 'DECIMAL(6,2) NOT NULL DEFAULT 30.00',
            'vacation_days_per_year'    => 'TINYINT NOT NULL DEFAULT 22',
            'vacation_days_first_year_per_month' => 'DECIMAL(4,1) NOT NULL DEFAULT 2.0',
            'thirteenth_month'          => 'TINYINT NOT NULL DEFAULT 12',
            'work_start_time'           => "TIME NOT NULL DEFAULT '08:00:00'",
            'daily_hours'               => 'DECIMAL(4,2) NOT NULL DEFAULT 8.00',
            'late_tolerance_minutes'    => 'SMALLINT NOT NULL DEFAULT 10',
            'break_minutes'             => 'SMALLINT NOT NULL DEFAULT 60',
            'irt_table'                 => 'TEXT NULL',
            'payslip_email_message'     => 'TEXT NULL',
        ],
        'attendance' => [
            'check_in'         => 'TIME NULL',
            'check_out'        => 'TIME NULL',
            'worked_minutes'   => 'INT NOT NULL DEFAULT 0',
            'late_minutes'     => 'INT NOT NULL DEFAULT 0',
            'overtime_minutes' => 'INT NOT NULL DEFAULT 0',
        ],
        'vacations' => [
            'working_days' => 'DECIMAL(5,1) NULL',
            'requested_via'=> "VARCHAR(10) NOT NULL DEFAULT 'rh'",
            'decided_at'   => 'DATETIME NULL',
            'decided_by'   => 'INT NULL',
        ],
    ];
}

/** Tabelas novas do módulo (CREATE TABLE IF NOT EXISTS). */
function rh_schema_tables(): array
{
    return [
        'rh_settings' => "CREATE TABLE IF NOT EXISTS `rh_settings` (
            `company_id` INT NOT NULL,
            `salario_minimo_nacional` DECIMAL(10,2) NULL,
            `aviso_previo_demissao_dias` INT NULL DEFAULT 30,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`company_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'holidays' => "CREATE TABLE IF NOT EXISTS `holidays` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `company_id` INT NOT NULL,
            `date` DATE NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_holidays_company_date` (`company_id`, `date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'rh_payroll_periods' => "CREATE TABLE IF NOT EXISTS `rh_payroll_periods` (
            `company_id` INT NOT NULL,
            `reference_month` CHAR(7) NOT NULL,
            `status` VARCHAR(10) NOT NULL DEFAULT 'aberto',
            `closed_at` DATETIME NULL,
            `closed_by` INT NULL,
            PRIMARY KEY (`company_id`, `reference_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'rh_advances' => "CREATE TABLE IF NOT EXISTS `rh_advances` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `company_id` INT NOT NULL,
            `employee_id` INT NOT NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT 'adiantamento',
            `description` VARCHAR(255) NULL,
            `total_amount` DECIMAL(12,2) NOT NULL,
            `installments` SMALLINT NOT NULL DEFAULT 1,
            `installment_amount` DECIMAL(12,2) NOT NULL,
            `start_month` CHAR(7) NOT NULL,
            `granted_date` DATE NULL,
            `status` VARCHAR(12) NOT NULL DEFAULT 'ativo',
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_adv_company_employee` (`company_id`, `employee_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'rh_advance_payments' => "CREATE TABLE IF NOT EXISTS `rh_advance_payments` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `advance_id` INT NOT NULL,
            `payroll_id` INT NOT NULL,
            `reference_month` CHAR(7) NOT NULL,
            `amount` DECIMAL(12,2) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_adv_payroll` (`advance_id`, `payroll_id`),
            KEY `idx_advpay_payroll` (`payroll_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}

/**
 * Aplica o esquema. Devolve a lista de alterações feitas (vazia se já estava tudo).
 * $dryRun: só lista o que faria.
 */
function rh_schema_migrate(PDO $pdo, bool $dryRun = false): array
{
    $log = [];
    $run = function (string $sql, string $label) use ($pdo, $dryRun, &$log) {
        $log[] = $label;
        if (!$dryRun) {
            $pdo->exec($sql);
        }
    };

    foreach (rh_schema_tables() as $table => $sql) {
        if (!rh_schema_table_exists($pdo, $table)) {
            $run($sql, "criar tabela $table");
        }
    }

    foreach (rh_schema_columns() as $table => $columns) {
        if (!rh_schema_table_exists($pdo, $table)) {
            if (!$dryRun) {
                $log[] = "AVISO: tabela $table não existe — colunas ignoradas";
            }
            continue;
        }
        foreach ($columns as $column => $definition) {
            if (!rh_schema_column_exists($pdo, $table, $column)) {
                $run("ALTER TABLE `$table` ADD COLUMN `$column` $definition", "$table.$column");
            }
        }
    }

    // Estado da folha: Pendente (em revisão) → Aprovado → Pago
    $stmt = $pdo->query("SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'payroll' AND column_name = 'status'");
    $statusType = (string)$stmt->fetchColumn();
    if ($statusType !== '' && stripos($statusType, 'Aprovado') === false) {
        $run("ALTER TABLE `payroll` MODIFY `status` ENUM('Pendente','Aprovado','Pago') NOT NULL DEFAULT 'Pendente'", 'payroll.status passa a aceitar Aprovado');
    }

    // Percentagens de férias/13.º com casas decimais (13.º proporcional aos meses trabalhados)
    foreach (['payroll', 'positions'] as $pctTable) foreach (['vacation_subsidy_pct', 'thirteenth_subsidy_pct'] as $pctColumn) {
        $stmt = $pdo->prepare("SELECT data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$pctTable, $pctColumn]);
        if (in_array(strtolower((string)$stmt->fetchColumn()), ['tinyint', 'smallint', 'int'], true)) {
            $run("ALTER TABLE `$pctTable` MODIFY `$pctColumn` DECIMAL(5,2) NOT NULL DEFAULT 0", "$pctTable.$pctColumn com decimais");
        }
    }

    if (!rh_schema_index_exists($pdo, 'payroll', 'idx_payroll_company_month')) {
        $run('ALTER TABLE `payroll` ADD INDEX `idx_payroll_company_month` (`company_id`, `reference_month`)', 'índice payroll(company_id, reference_month)');
    }
    if (!rh_schema_index_exists($pdo, 'attendance', 'idx_attendance_company_date')) {
        $run('ALTER TABLE `attendance` ADD INDEX `idx_attendance_company_date` (`company_id`, `date`)', 'índice attendance(company_id, date)');
    }
    if (rh_schema_column_exists($pdo, 'employees', 'portal_token') && !rh_schema_index_exists($pdo, 'employees', 'uq_employees_portal_token')) {
        $run('ALTER TABLE `employees` ADD UNIQUE INDEX `uq_employees_portal_token` (`portal_token`)', 'índice único employees.portal_token');
    }

    // Folhas antigas: total_discounts / gross_salary em falta são reconstruídos a partir das colunas antigas
    if (!$dryRun && rh_schema_column_exists($pdo, 'payroll', 'gross_salary')) {
        $n = $pdo->exec("UPDATE payroll SET
                gross_salary = base_salary + COALESCE(bonuses,0) + COALESCE(food_allowance,0) + COALESCE(transport_allowance,0)
                    + base_salary * COALESCE(vacation_subsidy_pct,0) / 100 + base_salary * COALESCE(thirteenth_subsidy_pct,0) / 100
                    + COALESCE(commissions,0),
                vacation_subsidy = base_salary * COALESCE(vacation_subsidy_pct,0) / 100,
                thirteenth_subsidy = base_salary * COALESCE(thirteenth_subsidy_pct,0) / 100
            WHERE gross_salary IS NULL");
        if ($n) {
            $log[] = "$n folha(s) antiga(s): bruto reconstruído";
        }
        $n = $pdo->exec('UPDATE payroll SET total_discounts = GREATEST(0, gross_salary - net_salary) WHERE total_discounts IS NULL');
        if ($n) {
            $log[] = "$n folha(s) antiga(s): total de descontos reconstruído";
        }
    }

    // evaluations.company_id a partir do ciclo
    if (!$dryRun && rh_schema_column_exists($pdo, 'evaluations', 'company_id') && rh_schema_table_exists($pdo, 'evaluation_cycles')) {
        $n = $pdo->exec('UPDATE evaluations ev JOIN evaluation_cycles c ON c.id = ev.cycle_id SET ev.company_id = c.company_id WHERE ev.company_id IS NULL');
        if ($n) {
            $log[] = "$n avaliação(ões): company_id preenchido";
        }
    }

    // employees.position_id a partir do texto do cargo (mesma empresa, nome igual)
    if (!$dryRun && rh_schema_column_exists($pdo, 'employees', 'position_id')) {
        $n = $pdo->exec('UPDATE employees e JOIN positions p ON p.company_id = e.company_id AND TRIM(p.name) = TRIM(e.position)
                         SET e.position_id = p.id WHERE e.position_id IS NULL AND e.position IS NOT NULL AND TRIM(e.position) <> ""');
        if ($n) {
            $log[] = "$n funcionário(s): position_id ligado pelo nome do cargo";
        }
    }

    return $log;
}
