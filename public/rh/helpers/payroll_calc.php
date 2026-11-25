<?php
/**
 * Compatibilidade: o cálculo da folha vive agora em rh/lib/payroll_engine.php (motor único).
 */
require_once __DIR__ . '/../lib/payroll_engine.php';

function calcIrtGrupoA2026($base)
{
    return rh_irt((float)$base, rh_default_irt_table());
}

function calculate_employee_payroll(PDO $pdo, int $company_id, int $employee_id, string $reference_month, array $overrides = []): array
{
    return rh_payroll_compute($pdo, $company_id, $employee_id, $reference_month, $overrides);
}
