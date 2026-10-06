<?php

/**
 * Funções auxiliares partilhadas pelo módulo de RH.
 * Fase 2 (feriados / dias úteis) e Fase 2 (antiguidade, usada na rescisão).
 *
 * require_once este ficheiro depois de já teres $pdo disponível.
 */

if (!function_exists('rh_is_holiday')) {
    /**
     * Verifica se uma data é feriado nacional para a empresa.
     */
    function rh_is_holiday(PDO $pdo, int $companyId, string $date): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM holidays WHERE company_id = ? AND date = ?');
        $stmt->execute([$companyId, $date]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('rh_get_holidays_in_range')) {
    /**
     * Devolve o conjunto (YYYY-MM-DD => nome) de feriados da empresa entre duas datas, inclusive.
     */
    function rh_get_holidays_in_range(PDO $pdo, int $companyId, string $start, string $end): array
    {
        $stmt = $pdo->prepare('SELECT date, name FROM holidays WHERE company_id = ? AND date BETWEEN ? AND ?');
        $stmt->execute([$companyId, $start, $end]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['date']] = $row['name'];
        }
        return $out;
    }
}

if (!function_exists('rh_working_days')) {
    /**
     * Conta dias úteis entre $start e $end (inclusive), excluindo sábados,
     * domingos e feriados nacionais da empresa (tabela `holidays`).
     * Usado para o cálculo de férias (Lei Geral do Trabalho: 22 dias úteis/ano).
     */
    function rh_working_days(PDO $pdo, int $companyId, string $start, string $end): int
    {
        $startDt = new DateTime($start);
        $endDt = new DateTime($end);

        if ($endDt < $startDt) {
            return 0;
        }

        $holidays = rh_get_holidays_in_range($pdo, $companyId, $start, $end);

        $count = 0;
        $cursor = clone $startDt;
        while ($cursor <= $endDt) {
            $dow = (int)$cursor->format('N'); // 1 (segunda) .. 7 (domingo)
            $dateStr = $cursor->format('Y-m-d');
            if ($dow < 6 && !isset($holidays[$dateStr])) {
                $count++;
            }
            $cursor->modify('+1 day');
        }

        return $count;
    }
}

if (!function_exists('rh_anos_completos')) {
    /**
     * Anos completos de antiguidade entre $admissionDate e $refDate (hoje, por
     * omissão), com a regra: fração de ano >= 3 meses arredonda para cima
     * (conta como +1 ano). Usada em calcularRescisao() (Fase 2, item 5).
     */
    function rh_anos_completos(string $admissionDate, ?string $refDate = null): int
    {
        $admission = new DateTime($admissionDate);
        $ref = $refDate ? new DateTime($refDate) : new DateTime();

        if ($ref < $admission) {
            return 0;
        }

        $diff = $admission->diff($ref);
        $years = $diff->y;
        $months = $diff->m;

        if ($months >= 3) {
            $years++;
        }

        return $years;
    }
}
