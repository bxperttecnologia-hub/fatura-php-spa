<?php
require __DIR__ . '/../app/core/bootstrap.php';

$uri   = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path  = (string)parse_url($uri, PHP_URL_PATH);
$norm  = rtrim($path, '/') ?: '/';
$isLoginRoute = in_array($norm, ['/login', '/login.php'], true);

// Ficheiros estáticos / endpoints inexistentes não devem devolver o shell HTML (200) nem redirecionar para o login
if (str_contains($path, '/ajax/')) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['success' => false, 'message' => 'Endpoint não encontrado: ' . $path]));
}
if (preg_match('~\.(?!php$)[a-z0-9]{1,5}$~i', $path)) {
    http_response_code(404);
    exit('Não encontrado');
}

// ---- Verificação de sessão (token na BD + expiração) ----
$user = Auth::user();

if (!$user && !$isLoginRoute) {
    // Sem sessão válida a primeira página é o login; guarda o destino para depois de entrar
    $dest = $uri;
    header('Location: /login' . ($norm !== '/' ? '?next=' . rawurlencode($dest) : ''));
    exit;
}
if ($user && $isLoginRoute) {
    header('Location: ' . (safe_next($_GET['next'] ?? null) ?? '/'));
    exit;
}

$pageTitle = 'BXpert';
if ($user) require_once ROOT . '/app/config/db.php';   // $pdo global para nav/modais/helpers legados

$pubKey = is_file(ROOT . '/app/keys/public.key') ? (string)file_get_contents(ROOT . '/app/keys/public.key') : null;
$boot = [
    'csrf'      => Csrf::token(),
    'user'      => Auth::publicUser(),
    'publicKey' => $user ? null : $pubKey,                       // só o login precisa dela
    'next'      => $user ? null : safe_next($_GET['next'] ?? null),
    // rota => {file,title}; também para visitantes, para o router mandar para /login em vez de 404
    'pages'     => array_map(fn($p) => ['file' => $p[0], 'title' => $p[1]], require ROOT . '/app/config/pages.php'),
];
?>
<?php include ROOT . '/app/partials/head.php'; ?>

<body class="<?= $user ? '' : 'guest' ?>">
  <?php if ($user): ?>
    <?php include ROOT . '/app/partials/nav.php'; ?>
  <?php endif; ?>
  <div class="layout">
    <?php if ($user) include ROOT . '/app/partials/side.php'; ?>
    <main id="app" class="p-4 w-full"><!-- páginas injetadas aqui --></main>
  </div>
  <?php include ROOT . '/app/partials/footer.php'; ?>
  <?php if ($user): ?>
    <?php include ROOT . '/app/models/modal_contacts.php'; ?>
    <?php include ROOT . '/app/models/modal_item.php'; ?>
    <?php include ROOT . '/app/models/modal_editItem.php'; ?>
  <?php endif; ?>

  <script>
    window.APP = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script type="module" src="/assets/js/app.js"></script>
</body>

</html>
