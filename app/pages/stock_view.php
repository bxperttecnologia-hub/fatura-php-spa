<?php
require_once '../app/config/db.php';
require_once '../app/helpers/authentication.php';
require_once '../app/helpers/subscription.php';

try {
  subscription_require_feature($pdo, (int)($_SESSION['user']['company_id'] ?? 0), 'stock');
} catch (Exception $e) {
  $cid = (int)($_SESSION['user']['company_id'] ?? 0);
  header('Location: subscription.php?company_id=' . $cid . '&upgrade=stock');
  exit;
}

require_once '../app/views/layout_creation.php';

$stockId = $_GET['id'] ?? null;
if (!$stockId) {
  header("Location: ../stock.php");
  exit;
}

?>

<link rel="stylesheet" href="stock/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
  /* ===== TABELA ESTILO ===== */
  #stockTable {
    border-collapse: separate;
    border-spacing: 0 12px;
    width: 100%;
  }

  /* HEADER */
  #stockTable thead th {
    border: none;
    font-size: 12px;
    color: #9ca3af;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 12px 16px;
    text-align: left;
    border-right: 1px solid #e5e7eb57;
  }

  #stockTable thead th:last-child {
    border-right: none;
  }

  #stockTable tbody tr td {
    border-right: 1px solid #e5e7eb57;
  }

  /* ROW */
  #stockTable tbody tr {
    background: #fff !important;
    border-radius: 14px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    text-align: left !important;
  }


  /* HOVER PRO */
  #stockTable tbody tr:hover {
    transform: translateY(-4px) scale(1.01);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
  }

  /* CELLS */
  #stockTable tbody td {
    border: none;
    padding: 18px 16px;
    vertical-align: middle;
    font-size: 0.95rem;
    background: #fff !important;
    text-align: left !important;
  }

  #stockTable thead td {
    background: #111 !important;
    display: none;
    max-width: 80px !important;
  }

  /* BORDAS ARREDONDADAS */
  #stockTable tbody td:first-child {
    border-top-left-radius: 14px;
    border-bottom-left-radius: 14px;
    background: #fff !important;
  }

  #stockTable tbody th {
    text-align: left !important;
  }

  #stockTable tbody td:last-child {
    border-top-right-radius: 14px;
    border-bottom-right-radius: 14px;
    text-align: right;
    padding-right: 24px;
  }

  /* ===== NOME (PRINCIPAL) ===== */
  #stockTable tbody td:first-child {
    font-weight: 600;
    color: #111;
  }

  /* SUBINFO */
  #stockTable tbody td small {
    display: block;
    color: #6b7280;
  }

  /* ===== ÍCONES ===== */
  .table-icon {
    font-size: 1.2rem;
    color: #9ca3af;
    transition: all 0.2s;
  }

  .table-icon:hover {
    color: #111;
    transform: scale(1.1);
  }

  /* ===== AÇÕES ===== */
  .edit-contact i,
  .delete-contact i {
    transition: all 0.2s ease;
  }

  .edit-contact:hover i {
    color: #2563eb;
    transform: scale(1.2);
  }

  .delete-contact:hover i {
    color: #dc2626;
    transform: scale(1.2);
  }

  .cell-edit {
    position: relative;
    display: flex;
    align-items: center;
  }

  .input-edit {
    border: none;
    background: transparent;
    padding-right: 25px;
  }

  .input-edit:focus {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
  }

  .edit-icon {
    position: absolute;
    right: 5px;
    cursor: pointer;
    font-size: 14px;
    color: #999;
    transition: 0.2s;
  }

  .edit-icon:hover {
    color: #0d6efd;
  }


  @media print {
    .celula-stack label {
      display: none !important;
    }

    .btn-qtd {
      display: none !important;
    }

    th:last-child,
    td:last-child {
      display: none !important;
    }

    .btn,
    .btn-group,
    #saveStatus,
    a[href="stock.php"] {
      display: none !important;
    }

    input {
      border: none !important;
      background: transparent !important;
      padding: 0 !important;
      width: 100% !important;
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
    }
  }
</style>

<div class="modal fade" id="modalNovoItem" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Novo Produto/Serviço</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form id="formNovoItem">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Código</label>
              <input type="text" class="form-control" name="code" placeholder="Código do produto">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nome</label>
              <input type="text" class="form-control" name="name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Categoria</label>
              <input type="text" class="form-control" name="category">
            </div>
            <div class="col-md-4">
              <label class="form-label">Quantidade</label>
              <input type="number" class="form-control" name="quantity" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Stock mínimo (alerta)</label>
              <input type="number" class="form-control" name="min_quantity" value="1" min="0">
              <small class="text-muted">Quando a quantidade ficar &le; este valor, aparece alerta no painel de stocks.</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Preço Unitário</label>
              <input type="number" class="form-control" name="unit_price" value="0" step="0.01">
            </div>
            <!-- <div class="col-md-4">
              <label class="form-label">Moeda</label>
              <select class="form-select" name="currency" id="selectMoeda"></select>
            </div> -->
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="btnSalvarItem">Salvar Produto/Serviço</button>
      </div>
    </div>
  </div>
</div>

<body>
  <main>

    <div class="container py-4 mt-5">
      <div>
        <a style="color:#cecece; font-weight:200;top:-1rem;position:relative; cursor:pointer" class="mb-2" href="stock.php">
          <span class="material-icons-round">
            reply
          </span> <span>Voltar</span></a>
      </div>
      <div class="mb-2 w-100 d-flex align-items-center justify-content-between ">
        <div class="d-flex flex-column">
          <h2 class="d-flex align-items-center gap-3 mb-0">
            <strong id="stockName"></strong>
          </h2>
          <small class="text-muted" style="font-size: 0.75rem;">
            Atualizado em: <span id="lastUpdate">--/--/----</span> por <span id="lastUser">---</span>
          </small>
        </div>

        <div class="d-flex align-items-center gap-2">
          <span id="saveStatus" class="text-muted small d-flex align-items-center gap-1 pe-2">
            <i class="bi bi-cloud-check"></i> <span class="status-text">Salvo</span>
          </span>

          <div class="btn-group me-2">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="bi bi-download"></i> Exportar
            </button>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="#" onclick="exportStockView('excel')"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
              <li><a class="dropdown-item" href="#" onclick="exportStockView('pdf')"><i class="bi bi-file-earmark-pdf me-2"></i>PDF</a></li>
              <li><a class="dropdown-item" href="#" onclick="window.print()"><i class="bi bi-printer me-2"></i>Imprimir</a></li>
            </ul>
          </div>

          <button class="btn btn-primary" id="addRow"> <i class="bi bi-plus"></i></button>
          <button class="btn btn-success btn-sm d-none" id="saveAllManual">
            <?= t('Salvar Tudo') ?>
          </button>
        </div>

      </div>



      <div class="table-responsive">
        <table class="table" id="stockTable">
          <thead>
            <tr>
              <th><?= t('Código') ?></th>
              <th><?= t('Nome') ?></th>
              <th><?= t('Categoria') ?></th>
              <th><?= t('Quantidade') ?></th>
              <th>Stock mín.</th>
              <th>IVA(%)</th>
              <th><?= t('Preço Unitário') ?></th>
              <th><?= t('Total') ?></th>
              <th><?= t('Ações') ?></th>
            </tr>
          </thead>
          <tbody>
            <!-- Carregado dinamicamente -->
          </tbody>
          <tfoot>
            <!-- Totals will be inserted here by JavaScript -->
          </tfoot>
        </table>
      </div>

    </div>
  </main>

  <script>
    const STOCK_ID = <?= (int)$stockId ?>;
  </script>
  <script>
    async function exportStockView(type) {
      const stockName = document.getElementById('stockName').innerText || 'Stock';
      const {
        headers,
        data
      } = getTableData();

      if (type === 'excel') {
        const ws = XLSX.utils.aoa_to_sheet([headers, ...data]);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Produtos_Serviços");
        XLSX.writeFile(wb, `${stockName}.xlsx`);
      } else if (type === 'pdf') {
        const {
          jsPDF
        } = window.jspdf;
        const doc = new jsPDF();

        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();

        // Logo no início do PDF
        let logoDataUrl = null;
        try {
          const resp = await fetch('stock/ajax/company_logo.php');
          const j = await resp.json();
          logoDataUrl = j?.dataUrl || null;
        } catch (e) {}

        let headerY = 15;
        if (logoDataUrl) {
          try {
            const fmt = (logoDataUrl.startsWith('data:image/jpeg') || logoDataUrl.startsWith('data:image/jpg')) ? 'JPEG' : 'PNG';
            doc.addImage(logoDataUrl, fmt, 14, 10, 28, 12);
            headerY = 28;
          } catch (e) {}
        }

        doc.setFontSize(12);
        doc.setTextColor(0);
        doc.text(`Relatório de Stock: ${stockName}`, 14, headerY);

        const lastUpdate = document.getElementById('lastUpdate').innerText;
        const lastUser = document.getElementById('lastUser').innerText;
        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text(`Atualizado em: ${lastUpdate} por ${lastUser}`, 14, headerY + 7);

        doc.autoTable({
          head: [headers],
          body: data,
          startY: headerY + 12,
          margin: {
            top: 10,
            bottom: 18
          }, // reserva rodapé para paginação
        });

        // Paginação
        const pageCount = doc.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
          doc.setPage(i);
          doc.setFontSize(9);
          doc.setTextColor(120);
          const text = `Página ${i} de ${pageCount}`;
          doc.text(text, pageWidth / 2, pageHeight - 10, {
            align: 'center'
          });
        }

        doc.save(`${stockName}.pdf`);
      }
    }

    function getTableData() {
      const data = [];
      const headers = ["Código", "Nome", "Categoria", "Quantidade", "Preço Unitário", "Total"];

      $('#stockTable tbody tr').each(function() {
        const row = [];
        row.push($(this).find('.item-code').val() || '');
        row.push($(this).find('.item-name').val() || '');
        row.push($(this).find('.item-category').val() || '');
        row.push($(this).find('.item-quantity').val() || '0');
        row.push($(this).find('.item-price').val() || '0');
        row.push($(this).find('.item-total').text().trim() || '0');
        data.push(row);
      });

      // Tenta pegar o rodapé (Total Geral)
      const $tfoot = $('#stockTable tfoot tr');
      if ($tfoot.length) {
        // Estrutura do footer no view.js: colspan=2 (Total Geral), qty, empty, total, empty
        // td indices: 0 (Total Geral), 1 (Qty), 2 (empty), 3 (Total), 4 (empty)
        const qty = $tfoot.find('td').eq(1).text().trim();
        const total = $tfoot.find('td').eq(3).text().trim();
        data.push(["Total Geral", "", qty, "", total]);
      }

      return {
        headers,
        data
      };
    }
  </script>
  <script src="stock/view.js"></script>
  <?php require_once '../app/views/footer.php'; ?>
</body>

</html>