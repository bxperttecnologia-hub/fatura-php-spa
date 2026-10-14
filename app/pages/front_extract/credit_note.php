<?php require_once '../app/views/layout_creation.php'; ?>

<style>
  body { background-color:#f8f9fa; }
  .pagea4{ width:190mm; max-width:100%; margin:0 auto; }
  iframe{ width:100%; min-height:85vh; border:none; background:#fff; }
</style>

<main class="flex-grow-1 p-3" style="margin-left: 250px;">
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="m-0">Nota de Crédito</h4>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="list_invoices.php">Voltar</a>
      </div>
    </div>

    <div class="pagea4">
      <?php
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if(!$id){
          echo '<div class="alert alert-danger">Nota de crédito inválida.</div>';
        } else {
          echo '<iframe id="credit-note-frame" src="credit_notes/ajax/credit_note_public.php?id='.$id.'"></iframe>';
        }
      ?>
    </div>

  </div>
</main>

<?php // footer vazio nesse projeto ?>
</body>
</html>
