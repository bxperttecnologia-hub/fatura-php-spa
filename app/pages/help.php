<?php
require_once '../app/views/layout_creation.php';
require_once __DIR__ . '/../helpers/env.php';

// Contactos de suporte vêm do .env (nunca escritos no código). Vazios -> mensagem genérica.
$supportEmail = trim((string) (bx_env('SUPPORT_EMAIL') ?: ''));
$supportWhatsapp = preg_replace('/\D+/', '', (string) (bx_env('SUPPORT_WHATSAPP') ?: ''));

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/*
 * Perguntas frequentes. Cada item: [categoria, pergunta, resposta em HTML (texto fixo, sem dados do utilizador)].
 * Links internos usam data-spa para navegar dentro da aplicação.
 */
$categories = [
    'start'    => ['Primeiros passos', 'rocket'],
    'billing'  => ['Faturação', 'file-text'],
    'agt'      => ['AGT', 'shield-check'],
    'items'    => ['Clientes e artigos', 'package'],
    'payments' => ['Subscrição e pagamentos', 'credit-card'],
    'users'    => ['Utilizadores e empresas', 'users'],
    'account'  => ['Conta e segurança', 'lock'],
];

$faqs = [
    ['start', 'O que posso fazer no BXpert?',
        'O BXpert junta num só lugar a <strong>faturação certificada pela AGT</strong> (faturas, faturas-recibo, proformas, notas de crédito e débito, recibos e guias), a gestão de <strong>clientes, artigos e stock</strong>, os <strong>Recursos Humanos</strong> (funcionários, faltas, férias, cargos e folha salarial) e relatórios com indicadores do negócio.'],
    ['start', 'Por onde começo depois de criar a conta?',
        '<ol class="mb-0 ps-3"><li>Complete os dados da empresa em <a href="/settings" data-spa>Definições</a> (NIF, morada, logótipo e dados bancários).</li><li>Registe os seus <a href="/contacts" data-spa>clientes</a> e os <a href="/items" data-spa>artigos ou serviços</a> que vende.</li><li>Emita a primeira fatura em <a href="/invoices/create" data-spa>Nova fatura</a>.</li></ol>'],
    ['start', 'Como mudo de empresa ou filial?',
        'Se tiver mais de uma empresa, escolha a empresa activa no menu do topo. Todos os documentos, clientes e relatórios mostrados passam a ser dessa empresa.'],

    ['billing', 'Como emito uma fatura?',
        'Vá a <a href="/invoices/create" data-spa>Faturas → Nova fatura</a>, escolha o cliente, adicione os artigos (quantidade, preço, IVA e desconto) e confirme. A fatura é numerada, assinada e enviada à AGT automaticamente. Depois pode imprimi-la, descarregar o PDF ou enviá-la por email.'],
    ['billing', 'Qual é a diferença entre Fatura, Fatura-Recibo e Proforma?',
        '<ul class="mb-0 ps-3"><li><strong>Fatura (FT)</strong>: documento de venda a pagar mais tarde; o pagamento regista-se depois com um recibo.</li><li><strong>Fatura-Recibo (FR)</strong>: venda já paga no momento.</li><li><strong>Proforma</strong>: orçamento sem valor fiscal; pode convertê-la em fatura quando o cliente aceitar.</li></ul>'],
    ['billing', 'Enganei-me numa fatura já emitida. Posso apagá-la?',
        'Não. Uma fatura certificada não pode ser apagada nem editada. Para a corrigir, emita uma <strong>nota de crédito</strong> a partir da própria fatura (anula total ou parcialmente) e, se for preciso, emita uma fatura nova com os dados correctos.'],
    ['billing', 'Como registo o pagamento de uma fatura?',
        'Abra a fatura e use a opção de <strong>registar pagamento</strong>. É criado um recibo associado e o valor em dívida é actualizado.'],
    ['billing', 'Como aplico a retenção na fonte?',
        'Ao criar a fatura, active a retenção e escolha o tipo (por exemplo, 6,5% de Imposto Industrial). O documento mostra o total bruto, o valor retido e o valor líquido a pagar.'],

    ['agt', 'O que significa o estado AGT de um documento?',
        'Cada documento é enviado à AGT (faturação electrónica). <strong>Enviado</strong> quer dizer que a AGT recebeu o pedido; depois a AGT valida-o. Se a AGT rejeitar o documento, o motivo aparece no estado e o documento tem de ser corrigido e reenviado.'],
    ['agt', 'Para que serve o QR code impresso na fatura?',
        'O QR code abre a consulta do documento no Portal do Contribuinte da AGT. Quem recebe a fatura pode confirmar que ela foi comunicada à AGT.'],
    ['agt', 'Onde configuro as séries de faturação?',
        'Em <a href="/agt" data-spa>Faturação AGT</a>, no fundo do menu lateral, pode ver e criar as séries de cada tipo de documento e acompanhar o estado dos envios.'],

    ['items', 'Como adiciono um cliente?',
        'Em <a href="/contacts" data-spa>Clientes/Empresas</a>, clique em adicionar. Com o NIF, o BXpert procura automaticamente o nome registado na AGT. Também pode criar um cliente directamente ao emitir uma fatura.'],
    ['items', 'Como funcionam os códigos dos artigos?',
        'Ao criar um artigo, o código é gerado automaticamente conforme o tipo (por exemplo <code>SRV-0015</code> para serviços ou <code>PROD-0001</code> para produtos). Pode alterá-lo antes de gravar, desde que não se repita na mesma empresa.'],
    ['items', 'Como controlo o stock?',
        'Em <a href="/stock" data-spa>Stock</a> crie os armazéns e associe os produtos. As entradas e saídas ficam registadas e o stock é actualizado com as vendas.'],

    ['payments', 'Como pago ou renovo a subscrição?',
        'Em <a href="/subscription" data-spa>Subscrição</a> escolha o plano e o método: <strong>Referência</strong> (entidade + referência para pagar no Multicaixa, no banco ou na app) ou <strong>Multicaixa Express</strong> (recebe o pedido no telemóvel e aprova lá). A subscrição é activada assim que o pagamento é confirmado. Qualquer utilizador da empresa pode gerar o pagamento.'],
    ['payments', 'Gerei uma referência por engano. Como a cancelo?',
        'Em <a href="/subscription" data-spa>Subscrição</a>, na lista de transações, clique em <strong>Cancelar</strong> na referência pendente. Depois pode gerar uma nova. Se a referência cancelada acabar por ser paga, o pagamento é aceite na mesma e a subscrição é actualizada.'],
    ['payments', 'O Multicaixa Express recusou o pagamento. O que faço?',
        'A mensagem mostra o motivo (por exemplo, número não activo no Multicaixa Express). Confirme que o número indicado tem o Multicaixa Express activo e com saldo, ou pague por <strong>Referência</strong>.'],
    ['payments', 'O que inclui cada plano?',
        'Os planos diferem no número de faturas por mês, utilizadores, empresas, funcionários de RH e artigos em stock. Veja a comparação completa e o seu consumo actual em <a href="/subscription" data-spa>Subscrição</a>.'],

    ['users', 'Como adiciono um colaborador?',
        'Em <a href="/users" data-spa>Utilizadores</a> (fundo do menu lateral) crie o colaborador ou associe um utilizador que já exista, e escolha o papel. Só o proprietário e os administradores vêem esta página.'],
    ['users', 'Que papéis existem?',
        '<ul class="mb-0 ps-3"><li><strong>Proprietário</strong>: controlo total, incluindo subscrição, empresas e utilizadores.</li><li><strong>Administrador</strong>: gere utilizadores e permissões.</li><li><strong>Funcionário</strong>: trabalha com as permissões que lhe forem dadas.</li><li><strong>Visualizador</strong>: só consulta.</li></ul>'],
    ['users', 'Como adiciono outra empresa ou filial?',
        'Em <a href="/companies" data-spa>Empresas</a> use a opção de adicionar empresa. Só o proprietário pode adicionar, e o número de empresas depende do plano (o plano Enterprise não tem limite). Em cada empresa, a opção <strong>Utilizadores</strong> define quem tem acesso a ela.'],

    ['account', 'Esqueci-me da senha. Como a recupero?',
        'No ecrã de entrada, clique em <strong>Esqueci a senha</strong> e indique o seu email ou telemóvel. Recebe um código ou link para definir uma senha nova.'],
    ['account', 'Como altero os meus dados ou a foto?',
        'Em <a href="/profile" data-spa>Perfil</a> pode actualizar o nome, o contacto, a foto e a senha.'],
    ['account', 'Os meus dados estão seguros?',
        'Sim. O acesso é feito por ligação cifrada (HTTPS), as senhas são guardadas de forma irreversível e cada utilizador só vê as empresas a que tem acesso, com as permissões do seu papel.'],
];
?>

<style>
    .hx-hero {
        background: linear-gradient(135deg, #005a87, #007abd);
        color: #fff;
        border-radius: 16px;
        padding: 28px 28px 24px;
    }

    .hx-hero h1 {
        font-size: 1.6rem;
        font-weight: 700;
        margin: 0 0 6px;
    }

    .hx-hero p {
        opacity: .9;
        margin: 0 0 18px;
    }

    .hx-search {
        position: relative;
        max-width: 560px;
    }

    .hx-search input {
        border-radius: 999px;
        padding: 12px 18px 12px 44px;
        border: 0;
        width: 100%;
    }

    .hx-search i,
    .hx-search svg {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
        width: 18px;
        height: 18px;
    }

    .hx-cats {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .hx-cat {
        border: 1px solid #d6e4f0;
        background: #fff;
        color: #374151;
        border-radius: 999px;
        padding: 7px 14px;
        font-size: .9rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .hx-cat svg {
        width: 16px;
        height: 16px;
    }

    .hx-cat.active {
        background: #007abd;
        border-color: #007abd;
        color: #fff;
    }

    .hx-item {
        background: #fff;
        border: 1px solid #e5edf5;
        border-radius: 12px;
        margin-bottom: 10px;
    }

    .hx-q {
        width: 100%;
        text-align: left;
        background: none;
        border: 0;
        padding: 14px 16px;
        font-weight: 600;
        color: #1f2937;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .hx-q svg {
        flex: 0 0 18px;
        width: 18px;
        height: 18px;
        transition: transform .2s;
        color: #007abd;
    }

    .hx-item.open .hx-q svg {
        transform: rotate(180deg);
    }

    .hx-a {
        display: none;
        padding: 0 16px 14px;
        color: #4b5563;
        font-size: .95rem;
        line-height: 1.55;
    }

    .hx-item.open .hx-a {
        display: block;
    }

    .hx-tag {
        font-size: .72rem;
        color: #007abd;
        background: #eef6ff;
        border-radius: 999px;
        padding: 2px 8px;
        margin-bottom: 6px;
        display: inline-block;
    }

    .hx-contact {
        background: #fff;
        border: 1px solid #e5edf5;
        border-radius: 12px;
        padding: 20px;
    }
</style>

<div class="container-fluid px-0" id="helpPage">

    <div class="hx-hero mb-4">
        <h1>Como podemos ajudar?</h1>
        <p>Respostas rápidas sobre faturação, AGT, pagamentos, utilizadores e a sua conta.</p>
        <div class="hx-search">
            <i data-lucide="search"></i>
            <input type="search" id="hxSearch" placeholder="Pesquisar na ajuda (ex.: nota de crédito, referência, senha)" autocomplete="off">
        </div>
    </div>

    <div class="hx-cats mb-3" id="hxCats">
        <button type="button" class="hx-cat active" data-cat="all"><i data-lucide="layout-grid"></i> Tudo</button>
        <?php foreach ($categories as $key => [$label, $icon]): ?>
            <button type="button" class="hx-cat" data-cat="<?= $h($key) ?>"><i data-lucide="<?= $h($icon) ?>"></i> <?= $h($label) ?></button>
        <?php endforeach; ?>
    </div>

    <div id="hxList">
        <?php foreach ($faqs as $i => [$cat, $question, $answer]): ?>
            <div class="hx-item" data-cat="<?= $h($cat) ?>">
                <button type="button" class="hx-q" aria-expanded="false" aria-controls="hxA<?= $i ?>">
                    <span>
                        <span class="hx-tag"><?= $h($categories[$cat][0]) ?></span><br>
                        <?= $h($question) ?>
                    </span>
                    <i data-lucide="chevron-down"></i>
                </button>
                <div class="hx-a" id="hxA<?= $i ?>"><?= $answer ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="text-muted text-center my-4" id="hxEmpty" style="display:none">
        Não encontrámos nada com esse texto. Experimente outras palavras ou fale connosco.
    </p>

    <div class="hx-contact mt-4">
        <h5 class="mb-1">Ainda precisa de ajuda?</h5>
        <?php if ($supportEmail !== '' || $supportWhatsapp !== ''): ?>
            <p class="text-muted mb-3">A equipa BXpert responde-lhe o mais rápido possível.</p>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($supportWhatsapp !== ''): ?>
                    <a class="btn btn-success" href="https://wa.me/<?= $h($supportWhatsapp) ?>" target="_blank" rel="noopener">
                        <i data-lucide="message-circle"></i> WhatsApp
                    </a>
                <?php endif; ?>
                <?php if ($supportEmail !== ''): ?>
                    <a class="btn btn-outline-primary" href="mailto:<?= $h($supportEmail) ?>">
                        <i data-lucide="mail"></i> <?= $h($supportEmail) ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">Fale com o proprietário ou administrador da sua conta, ou contacte a equipa BXpert pelos canais habituais.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    (function() {
        const page = document.getElementById('helpPage');
        if (!page) return;
        const items = [...page.querySelectorAll('.hx-item')];
        const search = page.querySelector('#hxSearch');
        const empty = page.querySelector('#hxEmpty');
        let cat = 'all';

        const norm = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

        function apply() {
            const q = norm(search.value.trim());
            let shown = 0;
            items.forEach(it => {
                const ok = (cat === 'all' || it.dataset.cat === cat) && (!q || norm(it.textContent).includes(q));
                it.style.display = ok ? '' : 'none';
                if (ok) shown++;
                if (q && ok) it.classList.add('open');
            });
            empty.style.display = shown ? 'none' : '';
        }

        page.querySelectorAll('.hx-cat').forEach(btn => btn.addEventListener('click', () => {
            page.querySelectorAll('.hx-cat').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            cat = btn.dataset.cat;
            apply();
        }));

        search.addEventListener('input', apply);

        page.querySelectorAll('.hx-q').forEach(q => q.addEventListener('click', () => {
            const item = q.closest('.hx-item');
            const open = !item.classList.contains('open');
            item.classList.toggle('open', open);
            q.setAttribute('aria-expanded', open ? 'true' : 'false');
        }));

        if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
    })();
</script>
