<?php
require __DIR__ . '/../app/core/bootstrap.php';
$pageTitle = 'BXpert';
$user = Auth::user();
$boot = [
    'csrf'  => Csrf::token(),
    'user'  => $user,
    // rota => {file,title}; também para visitantes, para o router mandar para /login em vez de 404
    'pages' => array_map(fn($p) => ['file' => $p[0], 'title' => $p[1]], require ROOT . '/config/pages.php'),
];
?>
<?php include ROOT . '/app/partials/head.php'; ?>
<body class="<?= $user ? '' : 'guest' ?>">
  <?php if ($user): ?>
    <?php include ROOT . '/app/partials/nav.php'; ?>
  <?php endif; ?>
  <div class="layout">
    <?php if ($user) include ROOT . '/app/partials/side.php'; ?>
    <main id="app" class="p-4"><!-- páginas injetadas aqui --></main>
  </div>
  <?php include ROOT . '/app/partials/footer.php'; ?>
  <?php if ($user): ?>
    <?php include ROOT . '/app/models/modal_contacts.php'; ?>
    <?php include ROOT . '/app/models/modal_item.php'; ?>
    <?php include ROOT . '/app/models/modal_editItem.php'; ?>
  <?php endif; ?>

  <script>window.APP = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
