# BXpert — SPA PHP + JS + AJAX

## Arrancar
1. Ajusta `app/config/config.php` (BD, `APP_ENV`). **Não** há `config/config.php` na raiz: a SPA lê as constantes do ERP.
2. `php -S localhost:8000 -t public public/router.php`  (ou Apache com `public/` como DocumentRoot, `mod_rewrite` e `AllowOverride All`).
3. Abre http://localhost:8000 — sem sessão válida és levado a `/login` (e, depois de entrar, de volta à página pedida).
4. Palavra-passe de teste: `php bin/reset_password.php <username|email> <nova-palavra-passe>`.

## Sessão e login
- Login = o do ERP: `users` + `company_has_user` + `companies`, token na tabela `sessions` (UTC), senha cifrada no browser com `app/keys/public.key`
  (JSEncrypt) e decifrada em `AuthController` com `private.key`. Sem JSEncrypt segue em claro (fallback).
- `Auth::check()` valida em cada pedido: sessão PHP → token na BD → dono do token → expiração. Expiração **deslizante com TTL fixo**
  (30 min, ou 24 h com "Lembrar de mim"); antes crescia a cada renovação.
- `public/index.php`: sem sessão válida → `302 /login?next=…`; com sessão em `/login` → `302 /`.
- `view.php` e a API devolvem `401` quando a sessão acaba; o JS (`app:unauthorized`) leva ao login com a mensagem "sessão expirada".
  Endpoints AJAX legados (`*/ajax/*.php`) também respondem `401` JSON via `app/helpers/authentication.php`.
- O browser verifica a sessão ao voltar ao separador e de 60 em 60 s com `GET /api/auth/me`, que **não** prolonga a sessão.
- 5 senhas erradas seguidas bloqueiam o login durante 5 minutos (por sessão — não substitui rate-limit no servidor web).

## Envio de faturas e proformas
- Configure `SMTP_USERNAME` e `SMTP_PASSWORD` no ambiente do PHP para habilitar o envio de documentos.
- `SMTP_FROM_NAME` é opcional; por omissão, o remetente usa o nome `BXpert`. O endereço de origem é o utilizador SMTP configurado.
- Não coloque credenciais SMTP no código ou em ficheiros servidos pela aplicação. Credenciais anteriormente expostas devem ser rodadas no fornecedor.

## Geração de documentos pela API
- Recibos, notas de crédito/débito, notas de entrega e folhas salariais são gerados pelo servidor genérico do `bxintelligence-api-rest`.
- O browser obtém um token de documentos de cinco minutos através da sessão PHP autenticada e envia-o à API; o token restringe a consulta à empresa associada à sessão.
- Configure `DOCUMENT_API_TOKEN_SECRET` com o mesmo segredo aleatório (mínimo 32 bytes) no ambiente do PHP e da API; configure `DOCUMENT_API_BASE_URL` com a URL pública da API.
- Os uploads de logótipos são assinados no servidor. A chave usa `COMPANY_LOGO_UPLOAD_TOKEN`, `DOCUMENT_API_TOKEN_SECRET` ou, como fallback, a palavra-passe de base de dados já partilhada entre PHP e API; não é necessária configuração adicional quando usam as mesmas credenciais da BD.
- No ambiente da API, `DB_PASS` ou `DB_PASSWORD` deve apontar para a mesma base de dados MySQL da aplicação. `DOCUMENT_ASSETS_DIR` é opcional e aponta para a pasta local dos logótipos.
- Não exponha o segredo no frontend, em respostas JSON ou em repositórios. Consulte o README do `bxintelligence-api-rest` para as rotas disponíveis.

## Estrutura
- `app/pages/*.php` — páginas do ERP, injetadas no `#app` por `public/view.php` + `assets/js/pages/legacy.js`.
- `app/config/pages.php` — mapa rota → ficheiro. Nova página = uma linha aqui.
- `public/<pasta>/ajax/*.php` (rh, invoices, index, …) — endpoints do ERP. **Têm de ficar em `public/`**: usam `require '../../../app/…'`
  e as páginas chamam-nos com URLs relativos (`rh/ajax/x.php`), que o `head.php` converte para `/rh/ajax/x.php`.
- `app/views/{layout_creation,footer,head}.php` — stubs vazios de propósito (nav/sidebar vêm do shell). Não os substituas pelos do ERP.
- `t()`, `formatName()`, `currencySelects()` vêm só de `app/helpers/translation.php` e `functions.php` (carregados no bootstrap).
  Defini-las também em `core/helpers.php` dava "Cannot redeclare" nas páginas.
- `database/sql/` — migrações SQL (antes em `app/pages/sql`, que ficava acessível por engano).
- `public/rh/migrations/` — scripts CLI; bloqueados por HTTP (`.htaccess` / `router.php`).

## Em falta no zip (copiar do ERP)
`public/assets/ajax/*` (a nav chama `assets/ajax/get_companies.php`), `public/assets/img/*`, `public/assets/translations/{angola,brasil}.php`
(opcional: sem ele os textos ficam como estão), `vendor/` (PHPMailer etc.), `public/purchases.php` (link "Compras").

## Notas
- `app/keys/private.key` e `.env` vêm no zip: confirma que não vão para o repositório nem para o servidor público (`.gitignore` já cobre `.env` e `*.key`).
- `login/ajax/process_login.php` e `loginGoogle/*` (login antigo) continuam acessíveis; o da SPA é `POST /api/auth/login`. Remove o antigo quando já não precisares dele.
- `/clientes` + `ClienteController` são o exemplo do esqueleto (tabela `clientes`/`contacts` inconsistentes); a lista real é `/contacts`.
