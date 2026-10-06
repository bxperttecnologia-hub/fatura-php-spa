<?php
require_once '../../../app/config/db.php';
require '../../../vendor/autoload.php'; // Autoload do PhpSpreadsheet
session_start();

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$type = $_GET['type'] ?? 'csv';

try {
    $stmt = $pdo->prepare("SELECT 
    cp.name AS 'Nome da empresa', 
    c.type AS 'Tipo', 
    c.name AS 'Nome do Contacto', 
    c.contributor AS 'Contribuinte', 
    c.address AS 'Endereço', 
    c.website AS 'Website', 
    c.country AS 'País', 
    c.city AS 'Cidade', 
    c.email AS 'E-mail', 
    c.telephone AS 'Telefone', 
    c.cellphone AS 'Telemóvel', 
    c.po_box AS 'Caixa Postal', 
    c.fax AS 'Fax', 
    c.pref_name AS 'Nome - Contato Preferencial', 
    c.pref_email AS 'E-mail - Contato Preferencial',  
    c.pref_telephone AS 'Telefone - Contato Preferencial', 
    c.pref_cellphone AS 'Telemóvel - Contato Preferencial', 
    CASE 
        WHEN c.numberCopys = 1 THEN 'Original' 
        WHEN c.numberCopys = 2 THEN 'Duplicado'  
        WHEN c.numberCopys = 3 THEN 'Triplicado' 
        ELSE 'Desconhecido' 
    END AS 'Nº de Cópias', 
    c.observations, 
    CASE WHEN c.due_date = 0 THEN 'Pronto pagamento' ELSE CONCAT(c.due_date, ' Dias') END AS 'Vencimento', 
    ct.name AS 'Idioma', 
    p.name AS 'Metodo de Pagamento', 
    CONCAT(cr.currency, ' (', cr.iso_code, ')') AS 'Moeda', 
    c.created_at AS 'Criado em', 
    c.updated_at AS 'Última Atualização' 
FROM contact c 
JOIN companies cp ON cp.id = c.company_id 
LEFT JOIN countries ct ON ct.iso = c.language 
LEFT JOIN payment_methods_contacts p ON p.code = c.payment_method 
LEFT JOIN currencies cr ON cr.iso_code = c.currency 
WHERE cp.id = :company_id");

    $stmt->bindParam(':company_id', $_SESSION['user']['company_id'], PDO::PARAM_INT);
    $stmt->execute();
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$contacts) {
        // Para PDF, gerar um PDF com uma mensagem de "nenhum contato"
        if ($type === "pdf") {
            $dompdf = new Dompdf();
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Relatório de Contatos</title>
                <style>
                    body { font-family: "DejaVu Sans", sans-serif; text-align: center; margin-top: 50px; }
                    h1 { color: #333; }
                </style>
            </head>
            <body>
                <h1>Nenhum contato encontrado para exportação.</h1>
                <p>Por favor, verifique os critérios de filtro ou se existem contatos cadastrados.</p>
            </body>
            </html>';
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream("contatos_vazio.pdf", ["Attachment" => true]);
            exit;
        } else {
            // Para CSV/Excel, manter o comportamento original de "die"
            die("Nenhum contato encontrado.");
        }
    }

    if ($type === "csv") {
        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=contatos.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        $output = fopen("php://output", "w");
        fputcsv($output, array_keys($contacts[0])); // Cabeçalhos

        foreach ($contacts as $contact) {
            fputcsv($output, $contact);
        }

        fclose($output);
        exit;
    } elseif ($type === "excel") {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Definir cabeçalhos na primeira linha (A1, B1, C1...)
        $columns = array_keys($contacts[0]);
        $columnLetter = 'A';

        foreach ($columns as $header) {
            $sheet->setCellValue($columnLetter . '1', $header);
            $columnLetter++;
        }

        // Inserir dados na planilha
        $row = 2;
        foreach ($contacts as $contact) {
            $columnLetter = 'A';
            foreach ($contact as $value) {
                $sheet->setCellValue($columnLetter . $row, $value);
                $columnLetter++;
            }
            $row++;
        }

        // Forçar download do arquivo
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=contatos.xlsx");
        header("Cache-Control: max-age=0");

        $writer = new Xlsx($spreadsheet);
        $writer->save("php://output");
        exit;
    } elseif ($type === "pdf") {

        $dompdf = new Dompdf();

        $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Relatório de Contatos</title>

        <style>
            @page {
                margin: 30px 40px 60px 40px;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 10pt;
                color: #333;
                line-height: 1.3;
                margin: 0;
            }

            .header {
                text-align: center;
                border-bottom: 2px solid #007abd;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }

            .header h1 {
                margin: 0;
                font-size: 16pt;
                color: #007abd;
                letter-spacing: 1px;
                text-transform: uppercase;
            }

            .header p {
                margin: 4px 0 0;
                font-size: 8pt;
                color: #666;
            }

            .contact-card {
                padding: 10px 0;
                border-bottom: 1px solid #e0e0e0;
                page-break-inside: avoid;
            }

            .card-top {
                width: 100%;
                margin-bottom: 6px;
            }

            .contact-name {
                font-size: 11pt;
                font-weight: bold;
                color: #007abd;
            }

            .company-name {
                font-size: 10pt;
                font-weight: 600;
                color: #555;
            }

            .contact-type {
                font-size: 8pt;
                color: #fff;
                background: #007abd;
                padding: 2px 6px;
                border-radius: 3px;
                text-transform: uppercase;
            }

            table.details {
                width: 100%;
                border-collapse: collapse;
            }

            table.details td {
                vertical-align: top;
            }

            .info-row {
                display: flex;
                align-items: flex-start;
                gap: 6px;
                margin-bottom: 3px;
            }

            .icon {
                width: 14px;
                text-align: center;
                font-size: 10pt;
                color: #007abd;
                line-height: 1.3;
                flex-shrink: 0;
                marin-right: 5px;
                pading-right: 5px;
            }

            .info-text {
                line-height: 1.3;
                word-break: break-word;
            }

            .label {
                font-weight: bold;
                font-size: 9pt;
                color: #555;
                margin-right: 4px;
            }

            .obs {
                margin-top: 6px;
                font-size: 9pt;
                color: #777;
                background: #f9f9f9;
                padding: 4px;
                border-radius: 4px;
                font-style: italic;
            }
        </style>
    </head>
    <body>

    <div class="header">
        <h1>Relatório de Contatos</h1>
        <p>Gerado em: ' . date('d/m/Y H:i:s') . ' | Bxpert</p>
    </div>
    ';

        foreach ($contacts as $contact) {

            $phones = array_unique(array_filter([
                $contact['Telefone'] ?? null,
                $contact['Telemóvel'] ?? null,
                !empty($contact['Telefone - Contato Preferencial']) ? $contact['Telefone - Contato Preferencial'] . ' (Pref)' : null,
                !empty($contact['Telemóvel - Contato Preferencial']) ? $contact['Telemóvel - Contato Preferencial'] . ' (Pref)' : null,
            ]));

            $emails = array_unique(array_filter([
                $contact['E-mail'] ?? null,
                $contact['E-mail - Contato Preferencial'] ?? null,
            ]));

            $address = implode(', ', array_filter([
                $contact['Endereço'] ?? null,
                $contact['Cidade'] ?? null,
                $contact['País'] ?? null,
            ]));

            $html .= '<div class="contact-card">';

            $html .= '
        <table class="card-top">
            <tr>
                <td>
                    <span class="contact-name">' . htmlspecialchars($contact['Nome do Contacto'] ?? 'Sem Nome') . '</span>
        ';

            if (!empty($contact['Nome da empresa'])) {
                $html .= ' <span style="color:#ccc;">|</span> <span class="company-name">' . htmlspecialchars($contact['Nome da empresa']) . '</span>';
            }

            $html .= '
                </td>
                <td align="right" width="120">
                    <span class="contact-type">' . htmlspecialchars($contact['Tipo'] ?? 'Geral') . '</span>
                </td>
            </tr>
        </table>
        ';

            $html .= '<table class="details"><tr>';

            $html .= '<td width="60%">';

            if ($emails) {
                $html .= '<div class="info-row"><span class="icon">&#9993;</span><span class="info-text">' . htmlspecialchars(implode(' • ', $emails)) . '</span></div>';
            }

            if ($phones) {
                $html .= '<div class="info-row"><span class="icon">&#9742;</span><span class="info-text">' . htmlspecialchars(implode(' • ', $phones)) . '</span></div>';
            }

            if ($address) {
                $html .= '<div class="info-row"><span class="icon">&#8962;</span><span class="info-text">' . htmlspecialchars($address) . '</span></div>';
            }

            if (!empty($contact['Website'])) {
                $html .= '<div class="info-row"><span class="icon">&#127760;</span><span class="info-text">' . htmlspecialchars($contact['Website']) . '</span></div>';
            }

            $html .= '</td><td width="40%">';

            if (!empty($contact['Contribuinte'])) {
                $html .= '<div class="info-row"><span class="label">NIF:</span>' . htmlspecialchars($contact['Contribuinte']) . '</div>';
            }

            if (!empty($contact['Metodo de Pagamento'])) {
                $html .= '<div class="info-row"><span class="label">Pagamento:</span>' . htmlspecialchars($contact['Metodo de Pagamento']) . '</div>';
            }

            if (!empty($contact['Moeda'])) {
                $html .= '<div class="info-row"><span class="label">Moeda:</span>' . htmlspecialchars($contact['Moeda']) . '</div>';
            }

            if (!empty($contact['Vencimento'])) {
                $html .= '<div class="info-row"><span class="label">Vencimento:</span>' . htmlspecialchars($contact['Vencimento']) . '</div>';
            }

            $html .= '</td></tr></table>';

            if (!empty($contact['observations'])) {
                $html .= '<div class="obs">Obs: ' . nl2br(htmlspecialchars($contact['observations'])) . '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Rodapé DEFINITIVO via canvas
        $canvas = $dompdf->getCanvas();
        $font   = $dompdf->getFontMetrics()->getFont("DejaVu Sans");

        $width  = $canvas->get_width();
        $height = $canvas->get_height();
        $y      = $height - 30;

        $canvas->page_text(40, $y, "Relatório de Contatos - Bxpert", $font, 8, [120, 120, 120]);
        $canvas->page_text($width - 140, $y, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, 8, [120, 120, 120]);

        $dompdf->stream("Relatório de Contatos - Bxpert.pdf", ["Attachment" => true]);
        exit;
    }
} catch (Exception $e) {
    die("Erro ao gerar arquivo: " . $e->getMessage());
}
