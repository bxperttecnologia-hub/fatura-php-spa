<?php
/**
 * Documentos do funcionário em PDF: declaração de serviço, declaração de rendimentos e minuta de contrato de trabalho.
 * ⚠️ Os textos são modelos: a empresa deve revê-los (e o contrato com um jurista) antes de usar.
 */
require_once __DIR__ . '/rh_pdf.php';

const RH_DOCUMENT_TYPES = [
    'declaracao'  => 'Declaração de serviço',
    'rendimentos' => 'Declaração de rendimentos',
    'contrato'    => 'Contrato de trabalho',
];

function rh_document_employee(PDO $pdo, int $companyId, int $employeeId): ?array
{
    $stmt = $pdo->prepare('SELECT e.*, COALESCE(p.name, e.position) AS position_name, d.name AS department_name
                           FROM employees e
                           LEFT JOIN positions p ON p.id = e.position_id AND p.company_id = e.company_id
                           LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id
                           WHERE e.id = ? AND e.company_id = ?');
    $stmt->execute([$employeeId, $companyId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function rh_date_long(?string $date): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '—';
    }
    $t = strtotime($date);
    return (int)date('j', $t) . ' de ' . rh_month_name((int)date('n', $t)) . ' de ' . date('Y', $t);
}

function rh_contract_label(?string $type): string
{
    return match ((string)$type) {
        'efetivo'              => 'por tempo indeterminado',
        'tempo determinado'    => 'por tempo determinado',
        'período experimental' => 'em período experimental',
        'Prestação de Serviço' => 'em regime de prestação de serviço',
        default                => 'por conta de outrem',
    };
}

/** Devolve ['html' => ..., 'filename' => ...] ou null. */
function rh_employee_document(PDO $pdo, int $companyId, int $employeeId, string $type, array $opts = []): ?array
{
    if (!isset(RH_DOCUMENT_TYPES[$type])) {
        return null;
    }
    $e = rh_document_employee($pdo, $companyId, $employeeId);
    if (!$e) {
        return null;
    }
    $c = rh_company($pdo, $companyId);
    $company = rh_e($c['name'] ?? 'a empresa');
    $nifCompany = rh_e($c['registration_number'] ?? '—');
    $name = '<strong>' . rh_e($e['name']) . '</strong>';
    $doc = rh_e(($e['document_type'] ?: 'BI') . ' n.º ' . ($e['bi'] ?: '—'));
    $position = rh_e($e['position_name'] ?: '—');
    $admission = rh_date_long($e['admission_date']);
    $active = ($e['status'] ?? '') === 'ativo';
    $city = rh_e($c['city'] ?? 'Luanda');
    $today = rh_date_long(date('Y-m-d'));
    $purpose = trim((string)($opts['purpose'] ?? ''));
    $purposeTxt = $purpose !== '' ? ', para efeitos de ' . rh_e($purpose) : ', para os fins que julgar convenientes';
    $title = RH_DOCUMENT_TYPES[$type];
    $body = '';

    if ($type === 'declaracao') {
        $period = $active
            ? "desde $admission, exercendo actualmente a função de <strong>$position</strong>"
            : "de $admission a " . rh_date_long($e['end_date']) . ", tendo exercido a função de <strong>$position</strong>";
        $body = "<p>Para os devidos efeitos, declara-se que $name, portador(a) do $doc, "
            . ($active ? 'é' : 'foi') . " trabalhador(a) de <strong>$company</strong>, NIF $nifCompany, "
            . rh_contract_label($e['contract_type']) . ", $period"
            . ($e['department_name'] ? ', no departamento de ' . rh_e($e['department_name']) : '') . '.</p>'
            . "<p>Por ser verdade e nos ter sido solicitada, passa-se a presente declaração$purposeTxt, que vai assinada e carimbada.</p>";
    } elseif ($type === 'rendimentos') {
        $stmt = $pdo->prepare("SELECT reference_month, gross_salary, inss_value, irt_value, net_salary FROM payroll
                               WHERE company_id = ? AND employee_id = ? AND status IN ('Aprovado','Pago')
                               ORDER BY reference_month DESC LIMIT 3");
        $stmt->execute([$companyId, $employeeId]);
        $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        $table = '';
        if ($rows) {
            $table = "<table class='lines'><tr><th>Mês</th><th class='num'>Ilíquido</th><th class='num'>INSS</th><th class='num'>IRT</th><th class='num'>Líquido</th></tr>";
            foreach ($rows as $i => $r) {
                $table .= '<tr' . ($i % 2 ? " class='alt'" : '') . '><td>' . rh_e(rh_month_label($r['reference_month'])) . "</td><td class='num'>" . rh_kz($r['gross_salary'])
                    . "</td><td class='num'>" . rh_kz($r['inss_value']) . "</td><td class='num'>" . rh_kz($r['irt_value']) . "</td><td class='num'>" . rh_kz($r['net_salary']) . '</td></tr>';
            }
            $table .= '</table>';
        }
        $body = "<p>Para os devidos efeitos, declara-se que $name, portador(a) do $doc, é trabalhador(a) de <strong>$company</strong>, NIF $nifCompany, "
            . "desde $admission, com a função de <strong>$position</strong>, auferindo o salário base mensal de <strong>" . rh_kz($e['salary_base']) . '</strong>.</p>'
            . ($rows ? '<p>Remunerações processadas nos últimos meses:</p>' . $table : '')
            . "<p>Por ser verdade e nos ter sido solicitada, passa-se a presente declaração$purposeTxt, que vai assinada e carimbada.</p>";
    } else { // contrato
        $end = $e['contract_type'] === 'tempo determinado' && $e['contract_end_date'] ? ', terminando em ' . rh_date_long($e['contract_end_date']) : '';
        $address = $e['address'] ? ', residente em ' . rh_e($e['address']) : '';
        $nif = $e['nif'] ? ', NIF ' . rh_e($e['nif']) : '';
        $clauses = [
            'Objecto e função' => "O Trabalhador é admitido para exercer a função de <strong>$position</strong>"
                . ($e['department_name'] ? ' no departamento de ' . rh_e($e['department_name']) : '') . ', obrigando-se a desempenhá-la com zelo e diligência, sob a autoridade e direcção do Empregador.',
            'Duração' => 'O presente contrato é celebrado ' . rh_contract_label($e['contract_type']) . ", com início em $admission$end.",
            'Local e horário de trabalho' => "O trabalho será prestado nas instalações do Empregador em $city ou noutro local que este indique, cumprindo o horário em vigor na empresa, com o período normal de trabalho definido na lei.",
            'Remuneração' => 'O Trabalhador aufere o salário base mensal ilíquido de <strong>' . rh_kz($e['salary_base']) . '</strong>, pago por transferência bancária'
                . ($e['iban'] ? ' para o IBAN ' . rh_e($e['iban']) : '') . ', acrescido dos subsídios previstos na lei e nas normas internas, e sujeito aos descontos legais (Segurança Social e IRT).',
            'Férias' => 'O Trabalhador tem direito a férias remuneradas e ao respectivo subsídio, nos termos da Lei Geral do Trabalho.',
            'Deveres' => 'As partes obrigam-se ao cumprimento dos deveres previstos na Lei Geral do Trabalho, incluindo o dever de sigilo sobre informação do Empregador a que o Trabalhador tenha acesso.',
            'Legislação aplicável' => 'Em tudo o que não estiver previsto neste contrato aplica-se a Lei Geral do Trabalho e demais legislação em vigor na República de Angola.',
        ];
        $body = "<p>Entre <strong>$company</strong>, NIF $nifCompany, adiante designada por <em>Empregador</em>, e $name, portador(a) do $doc$nif$address, "
            . 'adiante designado(a) por <em>Trabalhador</em>, é celebrado o presente contrato de trabalho, que se rege pelas cláusulas seguintes:</p>';
        $n = 1;
        foreach ($clauses as $h => $txt) {
            $body .= '<h2>Cláusula ' . $n++ . '.ª — ' . rh_e($h) . "</h2><p>$txt</p>";
        }
        $body .= '<p>Feito em duplicado, ficando cada parte com um exemplar.</p>';
    }

    $signatures = $type === 'contrato'
        ? "<table class='sign'><tr><td><div class='line'>O Empregador</div></td><td><div class='line'>O Trabalhador</div></td></tr></table>"
        : "<table class='sign'><tr><td></td><td><div class='line'>A Direcção / Recursos Humanos</div></td></tr></table>";
    $html = rh_pdf_header($c, $title, $e['name'])
        . "<div class='doc' style='margin-top:18px'>$body<p>$city, $today.</p></div>" . $signatures;
    $slug = preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $e['name']) ?: 'funcionario');
    return [
        'html'     => rh_pdf_document($html, ($c['name'] ?? '') . ' · ' . $title . ' · emitido em ' . date('d/m/Y')),
        'filename' => "{$type}_" . trim($slug, '_') . '.pdf',
    ];
}
