const get = new URLSearchParams(window.location.search).get("id");
const guideId = get ? get.substring(get.lastIndexOf("/") + 1) : null;

if (!guideId) {
  alert("Guia não encontrada!");
}

// Carrega o HTML da guia
$('#guide-container').load(`guides/ajax/guide_public.php?id=${guideId}`);

// Carrega dados para autofill do e-mail
let currentGuide = null;
function loadCurrentGuide(){
  return $.getJSON(`guides/ajax/get_guide.php?id=${guideId}`)
    .then(res => {
      if(res && res.success){
        currentGuide = res.data;
      }
      return currentGuide;
    })
    .catch(() => null);
}

// Quill (modal de envio)
let quill = null;
function ensureQuill(){
  if(quill) return quill;
  if(!window.Quill) return null;

  quill = new Quill('#editor-container', {
    theme : 'snow',
    modules:{ toolbar:'#editor-toolbar' }
  });

  return quill;
}

function formatMoneyPt(value){
  const v = Number(value || 0);
  return v.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDateBr(iso){
  if(!iso) return '';
  try{
    const [y,m,d] = String(iso).split('-');
    if(y && m && d) return `${d}/${m}/${y}`;
  }catch(e){}
  return iso;
}

$('#modalSendGuide').on('show.bs.modal', async function(){
  await loadCurrentGuide();
  ensureQuill();

  if(!currentGuide){
    return;
  }

  // hidden id
  $('#email_guide_id').val(currentGuide.id);

  const codigo = `${currentGuide.series}/${currentGuide.id}`;
  const company = currentGuide.company_name || '';
  const client = currentGuide.client_name || '';
  const symbol = currentGuide.money_symbol || (currentGuide.currency || '');
  const total = formatMoneyPt(currentGuide.final_total);
  const docDate = fmtDateBr(currentGuide.document_date);

  // Subject
  $('input[name="subject"]').val(`Guia #${codigo} – ${company}`);

  // Body template (similar ao da fatura)
  const template = `
<p>Prezado(a) <strong>${client}</strong>,</p>

<p>Segue em anexo a <strong>guia nº ${codigo}</strong>,
no valor de <strong>${symbol} ${total}</strong>${docDate ? `, emitida em ${docDate}` : ''}.</p>

<p>Qualquer dúvida estou à disposição.</p>

<p>Atenciosamente,<br>
&nbsp;</p>`;

  if(quill){
    quill.setContents(quill.clipboard.convert(template));
    $('#body-hidden').val(quill.root.innerHTML);
  } else {
    // fallback sem Quill (se CDN falhar)
    const editor = document.getElementById('editor-container');
    if(editor) editor.innerHTML = template;
    $('#body-hidden').val(template);
  }

  // Sugere o email do cliente se existir e campo estiver vazio
  const $to = $('input[name="to"]');
  if(!$to.val() && currentGuide.client_email){
    $to.val(currentGuide.client_email);
  }
});

// Submit do envio
$('#formSendGuide').on('submit', function(e){
  e.preventDefault();

  if(this.checkValidity() === false){
    this.classList.add('was-validated');
    return;
  }

  const q = ensureQuill();
  if(q){
    $('#body-hidden').val(q.root.innerHTML);
  }

  $.post('guides/ajax/send_guide.php', {
    to: $(this).find('[name="to"]').val(),
    cc: $(this).find('[name="cc"]').val(),
    subject: $(this).find('[name="subject"]').val(),
    body: $('#body-hidden').val(),
    attach: $('#chkAnexarGuide').is(':checked') ? 1 : 0,
    guide_id: $('#email_guide_id').val()
  })
  .done(() => {
    bootstrap.Modal.getInstance(document.getElementById('modalSendGuide')).hide();
    alert('E-mail enviado com sucesso!');
  })
  .fail(xhr => {
    alert('Erro: ' + (xhr.responseText || 'Tente novamente.'));
  });
});
