<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    echo json_encode(['success' => false, 'message' => 'Empresa não identificada']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, logo_url FROM companies WHERE id = ? LIMIT 1');
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$company) {
    echo json_encode(['success' => false, 'message' => 'Empresa não encontrada']);
    exit;
}

$logoUrl = $company['logo_url'] ?? '';
$logoUrl = is_string($logoUrl) ? trim($logoUrl) : '';

if ($logoUrl === '') {
    echo json_encode(['success' => true, 'name' => $company['name'], 'dataUrl' => null]);
    exit;
}

function guessMime($pathOrUrl) {
    $lower = strtolower(parse_url($pathOrUrl, PHP_URL_PATH) ?? $pathOrUrl);
    if (str_ends_with($lower, '.png')) return 'image/png';
    if (str_ends_with($lower, '.jpg') || str_ends_with($lower, '.jpeg')) return 'image/jpeg';
    if (str_ends_with($lower, '.webp')) return 'image/webp';
    return 'application/octet-stream';
}

$bytes = null;

// 1) Se for URL http(s), tenta baixar.
if (preg_match('~^https?://~i', $logoUrl)) {
    $context = stream_context_create([
        'http' => ['timeout' => 5],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $bytes = @file_get_contents($logoUrl, false, $context);
} else {
    // 2) Se for caminho relativo ao public, tenta ler do disco.
    $publicRoot = realpath(__DIR__ . '/../../'); // /public

    // Normaliza: se vier só o nome do arquivo (ex.: logo_x.png), ele fica em assets/img/companies/
    $candidate = $logoUrl;
    $candidate = ltrim($candidate, '/');
    if (strpos($candidate, '/') === false && strpos($candidate, '\\') === false) {
        $candidate = 'assets/img/companies/' . $candidate;
    }

    $full = realpath($publicRoot . '/' . $candidate);
    if ($full && str_starts_with($full, $publicRoot) && is_file($full)) {
        $bytes = @file_get_contents($full);
    } else {
        // 3) tenta como caminho absoluto
        if (is_file($logoUrl)) {
            $bytes = @file_get_contents($logoUrl);
        }
    }
}

if (!$bytes) {
    echo json_encode(['success' => true, 'name' => $company['name'], 'dataUrl' => null, 'message' => 'Logo não pôde ser carregada']);
    exit;
}

$mime = guessMime($logoUrl);
$dataUrl = 'data:' . $mime . ';base64,' . base64_encode($bytes);

echo json_encode([
    'success' => true,
    'name' => $company['name'],
    'dataUrl' => $dataUrl,
]);
