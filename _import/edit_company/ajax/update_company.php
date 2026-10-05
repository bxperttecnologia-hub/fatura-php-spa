<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido.');
    }

    $companyId = (int)($_POST['id'] ?? 0);

    if ($companyId <= 0) {
        throw new Exception('ID da empresa inválido.');
    }

    // =============================
    // INPUTS BASE
    // =============================
    $name = trim($_POST['name'] ?? '');
    $registration_number = trim($_POST['registration_number'] ?? '');
    $currency = trim($_POST['currency'] ?? 'AOA');

    if ($name === '' || $registration_number === '') {
        throw new Exception('Nome e registo da empresa são obrigatórios.');
    }

    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');

    // =============================
    //  FISCAL (CORRIGIDO)
    // =============================
    $vat_regime = $_POST['vat_regime'] ?? 'geral';

    $default_vat_rate = isset($_POST['default_vat_rate'])
        ? (float) str_replace(',', '.', $_POST['default_vat_rate'])
        : 14.00;

    $vat_included_prices = isset($_POST['vat_included_prices']) ? 1 : 0;

    $withholding_enabled = isset($_POST['withholding_enabled'])
        ? (int) $_POST['withholding_enabled']
        : 0;

    $withholding_rate = isset($_POST['withholding_rate'])
        ? (float) str_replace(',', '.', $_POST['withholding_rate'])
        : 0.00;

    // segurança extra
    if (!in_array($vat_regime, ['geral', 'simplificado', 'isento'])) {
        $vat_regime = 'geral';
    }

    if ($withholding_enabled === 0) {
        $withholding_rate = 0.00;
    }

    $goods_services = $_POST['goods_services'] ?? null;

    // =============================
    // BANK
    // =============================
    $bank_name = $_POST['bank_name'] ?? null;
    $bank_name_optional = $_POST['bank_name_optional'] ?? null;

    $normalizeAngolaIban = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = preg_replace('/[^A-Z0-9]/i', '', strtoupper(trim((string) $value)));
        if ($clean === '') {
            return null;
        }

        $clean = preg_replace('/^AO/i', '', $clean);
        $clean = preg_replace('/^\d{2}/', '', $clean);
        $clean = preg_replace('/\D/', '', $clean);
        $clean = substr($clean, 0, 21);
        $clean = str_pad($clean, 21, '0');

        $first = substr($clean, 0, 20);
        $rest = substr($clean, 20);
        $groups = [];

        for ($i = 0; $i < strlen($first); $i += 4) {
            $groups[] = substr($first, $i, 4);
        }

        if ($rest !== '') {
            $groups[] = $rest;
        }

        $formatted = 'AO06 ' . implode(' ', $groups);
        return strlen(str_replace(' ', '', $formatted)) > 25
            ? substr($formatted, 0, 25)
            : $formatted;
    };

    $iban = $normalizeAngolaIban($_POST['iban'] ?? null);
    $iban_optional = $normalizeAngolaIban($_POST['iban_optional'] ?? null);
    $bank_details = $_POST['bank_details'] ?? null;

    // =============================
    // INVOICE
    // =============================
    $allowedInvoices = ['A4', 'A5', 'Recibo'];
    $invoice_impression = $_POST['invoice_impression'] ?? 'A4';

    if (!in_array($invoice_impression, $allowedInvoices, true)) {
        $invoice_impression = 'A4';
    }

    // =============================
    // LOGO UPLOAD
    // =============================
    $logoPath = null;

    if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {

        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $originalName = $_FILES['logo']['name'] ?? '';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

        if (!in_array($ext, $allowed, true)) {
            throw new Exception('Formato de logo inválido.');
        }

        if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            throw new Exception('Logo muito grande (máx 2MB).');
        }

        $fileName = uniqid('logo_', true) . '.' . $ext;

        $uploadDir = __DIR__ . '/../../assets/img/companies';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $destPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            throw new Exception('Erro ao salvar logo.');
        }

        $logoPath = $fileName;
    }

    // =============================
    // SQL
    // =============================
    $query = "UPDATE companies SET
        name = :name,
        registration_number = :registration_number,
        phone = :phone,
        email = :email,
        website = :website,
        address = :address,
        country = :country,
        city = :city,
        zip_code = :zip_code,
        currency = :currency,

        vat_regime = :vat_regime,
        default_vat_rate = :default_vat_rate,
        vat_included_prices = :vat_included_prices,
        withholding_enabled = :withholding_enabled,
        withholding_rate = :withholding_rate,

        goods_services = :goods_services,
        bank_name = :bank_name,
        bank_name_optional = :bank_name_optional,
        iban = :iban,
        iban_optional = :iban_optional,
        bank_details = :bank_details,
        invoice_impression = :invoice_impression";

    if ($logoPath) {
        $query .= ", logo_url = :logo_url";
    }

    $query .= " WHERE id = :company_id";

    $stmt = $pdo->prepare($query);

    // =============================
    // BIND
    // =============================
    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':registration_number', $registration_number);
    $stmt->bindValue(':phone', $phone);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':website', $website);
    $stmt->bindValue(':address', $address);
    $stmt->bindValue(':country', $country);
    $stmt->bindValue(':city', $city);
    $stmt->bindValue(':zip_code', $zip_code);
    $stmt->bindValue(':currency', $currency);

    $stmt->bindValue(':vat_regime', $vat_regime);
    $stmt->bindValue(':default_vat_rate', $default_vat_rate);
    $stmt->bindValue(':vat_included_prices', $vat_included_prices);
    $stmt->bindValue(':withholding_enabled', $withholding_enabled);
    $stmt->bindValue(':withholding_rate', $withholding_rate);

    $stmt->bindValue(':goods_services', $goods_services);

    $stmt->bindValue(':bank_name', $bank_name);
    $stmt->bindValue(':bank_name_optional', $bank_name_optional);
    $stmt->bindValue(':iban', $iban);
    $stmt->bindValue(':iban_optional', $iban_optional);
    $stmt->bindValue(':bank_details', $bank_details);

    $stmt->bindValue(':invoice_impression', $invoice_impression);

    if ($logoPath) {
        $stmt->bindValue(':logo_url', $logoPath);
    }

    $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'logo_url' => $logoPath
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}