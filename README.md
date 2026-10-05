# SPA PHP + JS + AJAX (BXpert)

1. Importa `database/schema.sql` (cria a BD `db_fatura`) e ajusta `config/config.php`.
2. Cria um utilizador: `php bin/create_user.php "Admin" admin@exemplo.com minhaPass`
3. Arranca: `php -S localhost:8000 -t public public/router.php`
   (ou Apache com `public/` como DocumentRoot e mod_rewrite ativo)
4. Abre http://localhost:8000

## Notas
- Login/logout fazem recarga completa da página: o servidor só renderiza `nav` + `sidebar` com sessão ativa.
- Rotas SPA existentes: `/`, `/login`, `/clientes` (também `/contacts`, `/contacts/create`). As restantes mostram a página 404 até serem criadas.
- Endpoints usados pela nav (`/assets/ajax/*.php`, `/index/ajax/*.php`) não vêm neste zip; sem eles a nav mostra apenas valores por defeito.
- Ficheiros opcionais (`side.css`, `support-chat.*`, `script.js`) só são carregados se existirem em `public/assets/`.

## Páginas do ERP (pages.zip) dentro da SPA
- As páginas PHP ficam em `app/pages/` (fora da webroot). `config/pages.php` mapeia rota SPA -> ficheiro.
- `public/view.php` executa a página e devolve só o conteúdo (JSON); `assets/js/pages/legacy.js` injeta-o no `#app`,
  executa os `<script>`, converte URLs relativos de AJAX (`rh/ajax/x.php` -> `/rh/ajax/x.php`) e limpa
  DataTables/charts/intervals/handlers ao navegar. Links `x.php` passam a navegar pela SPA.
- `app/views/layout_creation.php`, `footer.php` e `head.php` são **stubs vazios** de propósito (o shell já tem nav/sidebar).
  Não os substituas pelos do ERP.
- Em falta no zip (copiar do ERP): `app/config/db.php`, `app/helpers/{authentication,subscription,functions,translation}.php`
  e os endpoints AJAX (`public/rh/ajax/`, `public/invoices/ajax/`, `public/index/ajax/`, …). Se faltar algum, o `view.php` diz qual.
- `login.php`, `logout.php` e `dashboard.php`/`download_pdf.php`/`stock-depot.php` (vazios) não foram incluídos: o login é o da SPA.
  `register.php`, `forgot_password.php`, `invoice_public.php` e `register_contact.php` ficam em `public/` como páginas independentes.
- O login da SPA só guarda `id/nome/email` na sessão; as páginas usam `$_SESSION['user']['company_id']`, por isso o `Auth::login` tem de passar a gravá-lo.
- Nova página: acrescentar uma linha em `config/pages.php` (e o ficheiro em `app/pages/`).
