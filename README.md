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

## Notificações (sino + push da app mobile)
- **Regra:** nunca fazer `INSERT INTO notifications` direto. Usar `bx_notify($pdo, [...])` ou `bx_notify_company_admins()`
  de `app/helpers/notification_service.php` (o antigo `createNotification()` continua a funcionar e delega nele).
- `dedupe_key` evita duplicados (ex.: `'invoice_overdue:150:w2'`); uma chave repetida devolve `null` sem erro.
- `url` só aceita caminhos internos (`/invoices/view?id=1`): é para onde a app/sino leva ao tocar.
- O push por omissão é decidido por tipo em `BX_NOTIFICATION_TYPES` (lembretes como "perfil incompleto" não vão por push);
  `'push' => true|false` força, e `notification_preferences` permite ao utilizador desligar um tipo.
- O pedido só enfileira em `push_queue`; o envio à Expo é feito por um worker (cron), nunca dentro do pedido HTTP.
- Antes de publicar: `app/migrations/20261126_notifications_push.sql` (idempotente). Sem ela, o sino continua a funcionar sem push.
- Cron de avisos (faturas a vencer/vencidas, subscrições, pagamentos pendentes): `php app/cron/notifications_cron.php`,
  de hora a hora. É idempotente. Opcional no `.env`: `NOTIFY_INVOICE_DUE_SOON_DAYS` (3), `NOTIFY_INVOICE_OVERDUE_MAX_DAYS` (90),
  `NOTIFY_SUBSCRIPTION_EXPIRING_DAYS` (7), `NOTIFY_SUBSCRIPTION_ORDER_STALE_HOURS` (24), `NOTIFICATIONS_SEND_EMAIL` (0).
- Requer PHP ≥ 8.3 por causa do `vendor/` (o `php.exe` do XAMPP é 8.0; usar p.ex. `C:\tools\php84\php.exe` na linha de comandos).

## App mobile (../bxpert-mobile)
- A app é uma WebView desta SPA; `public/assets/js/native-bridge.js` (incluído no `index.php`) activa o modo app só quando
  o user-agent tem `BXpertApp/` — no browser não faz nada. Ver o README do `bxpert-mobile`.
- API (sessão + CSRF normais): `POST /api/push/register`, `POST /api/push/unregister`, `GET /api/push/badge`,
  `GET|PUT /api/push/preferences` (`PushController`). O logout revoga o push do dispositivo.
- Envio: `php app/cron/push_sender.php` **de minuto a minuto** (lotes de 100 à Expo, receipts após 15 min, revoga tokens
  `DeviceNotRegistered`, retry com espera exponencial). `.env` opcional: `EXPO_ACCESS_TOKEN`, `PUSH_QUIET_HOURS=22-7`, `PUSH_CA_BUNDLE`.
- Sessão: na app o login dura 30 dias (`Auth::isApp()`); em qualquer cliente, se a sessão PHP se perder mas o cookie
  `session_token` ainda for válido, a sessão é reposta (`Auth::restoreFromCookie`). O "Lembrar de mim" do browser passa
  assim a sobreviver ao fecho do browser, dentro das 24 h.

## Verificação por SMS
- O registo e a recuperação de senha enviam códigos pela API GoSMS (`POST https://api.go-sms.co.ao/v1/messages`).
- Configure `GOSMS_TOKEN` e `GOSMS_FROM` no `.env` local ou no ambiente do PHP. O remetente deve estar autorizado na GoSMS.
- Se o cURL do PHP não tiver um bundle CA configurado, defina `GOSMS_CA_BUNDLE` para um bundle CA confiável e legível.
- A autenticação é enviada como Bearer token. O token não deve ser colocado no código nem no repositório.
- A recuperação por e-mail continua disponível; a recuperação por telefone usa SMS.

## Pagamento de subscrições AppyPay
- Este fluxo paga exclusivamente a subscrição BXPERT; não processa facturas comerciais emitidas pelas empresas.
- Antes de publicar a integração, execute `app/migrations/20261021_harden_subscription_payments.sql` na base MySQL. A migração acrescenta metadados de operação/auditoria e unicidade do identificador da AppyPay; não remove colunas nem histórico.
- O período actual é de três meses. O preço é obtido de `subscription_plans()` no servidor, e coincide com os montantes dos pedidos históricos existentes.
- Sandbox: `APPYPAY_ENV=sandbox`, `APPYPAY_CLIENT_ID_SANDBOX`, `APPYPAY_CLIENT_SECRET_SANDBOX`, `APPYPAY_RESOURCE_SANDBOX` (ou `APPYPAY_RESOURCE_TST`) e `APPYPAY_PAYMENT_METHOD_REF_SANDBOX` / `APPYPAY_PAYMENT_METHOD_GPO_SANDBOX`. Os nomes antigos sem sufixo são aceites apenas no Sandbox.
- Produção exige credenciais e métodos separados (`APPYPAY_CLIENT_ID_PRODUCTION`, `APPYPAY_CLIENT_SECRET_PRODUCTION`, `APPYPAY_RESOURCE_PRODUCTION`, `APPYPAY_PAYMENT_METHOD_REF_PRODUCTION`, `APPYPAY_PAYMENT_METHOD_GPO_PRODUCTION`) e `APPYPAY_API_BASE_PRODUCTION` definido com a URL de produção fornecida pela AppyPay. O endpoint OAuth de produção segue a documentação oficial; nenhuma URL de gateway de produção é inferida pelo código.
- Para ambos os ambientes, o token usa OAuth 2.0 Client Credentials documentado pela AppyPay e fica numa cache temporária separada por ambiente/credenciais, com permissões restritas. Não exponha credenciais, tokens nem respostas técnicas no browser.
- Se o PHP/cURL não tiver um bundle CA configurado, o cliente tenta detectar o bundle do XAMPP junto do executável PHP. Noutros ambientes, defina `APPYPAY_CA_BUNDLE` no `.env` local ou no ambiente PHP para o caminho de um bundle CA confiável e legível. A validação TLS permanece activa.
- Configure o webhook AppyPay para `POST /subscription/webhook/appypay.php`. O webhook só inicia uma consulta: o servidor confirma novamente a cobrança através de `GET /charges/{id}` antes de actualizar a subscrição. `APPYPAY_WEBHOOK_TOKEN` é opcional como validação adicional se o portal suportar o header `X-Appypay-Webhook-Token`.
- Faça os testes exclusivamente no Sandbox: GPO com os números de simulação da AppyPay (por exemplo `900000000`) e REF com o endpoint Sandbox `/mocks/referenceProcessing`, seguido de “Atualizar status”. Não use números/dados Sandbox em produção.
- A confirmação manual foi removida; uma subscrição só é activada após a confirmação do estado junto da AppyPay. Uma cobrança com resultado de rede desconhecido permanece pendente para impedir duplicação; um proprietário/administrador pode associar o ID obtido no portal, que só é aceite após validar a identidade, o valor, a moeda e o estado pela API.

## Activação por licença do BXPERT CRM
- Um pagamento confirmado fica em `awaiting_license`; não altera o plano da empresa nem activa funcionalidades. O ERP só actualiza a subscrição depois de receber e validar a licença emitida pelo CRM.
- O ERP mantém os pedidos AppyPay e publica os pedidos pagos numa outbox independente; o CRM mantém um espelho próprio. O ERP usa `subscription_plans()` para preços, sem consultar tabelas CRM. O CRM confirma o código, preço e período contra o seu próprio catálogo antes de mostrar o pedido para emissão.
- O catálogo BAZA/BASE/XPERT/PRIME oferece períodos de 3, 6 e 12 meses. Os preços continuam definidos por período em `subscription_plans()`; ambos os sistemas associam os seus próprios IDs de `plans` por `legacy_code` e `duration_months`. Os códigos ERP canónicos são `BAZA`, `BASE`, `XPERT` e `PRIME`; os códigos internos antigos das empresas são normalizados sem alterar limites, preços ou acesso. O ERP só cria um pedido quando existe exactamente um plano correspondente e grava o seu próprio `plan_id`; não infere IDs entre bases.
- Aplique migrações apenas no sistema correspondente: no CRM, 009 e 010 depois das anteriores; no ERP, `20261118`, `20261119`, `20261120` e `20261121`, pela ordem. A migração incremental `20261122_create_subscription_license_sync_attempts.sql` pode ser executada isoladamente pelo comando `php bin/migrate_subscription_license_sync_attempts.php --dry-run`; confirme a base ERP apresentada e só então execute com `--apply`. Aplique também `app/migrations/20261123_explicit_erp_plan_mapping.sql` e, após aprovação explícita do catálogo, `app/migrations/20261125_canonical_subscription_plan_codes.sql` e `app/migrations/20261125_backfill_subscription_order_plan_ids.sql`. Estas migrações associam os códigos aprovados e resolvem `plan_id` apenas quando código, duração e valor coincidem; não alteram preços nem durações. Valide os resultados em `docs/diagnostico_integracao_erp.sql` antes de aceitar novos pedidos.
- Configure `CRM_LICENSE_SYNC_SECRET` no `.env` do ERP e `ERP_LICENSE_SYNC_URL=https://<host-erp>/subscription/webhook/crm.php` e `ERP_LICENSE_SYNC_SECRET` no CRM. O mesmo segredo aleatório (mínimo 32 caracteres) tem de ser usado em ambos. O endpoint aceita apenas `POST`, exige HMAC-SHA256 com timestamp e ID único do evento e rejeita chamadas não HTTPS em produção; HTTP só é aceite em desenvolvimento para loopback.
- Configure `CRM_ORDER_SYNC_URL=https://<host-crm>/integrations/erp/subscription-orders` e `CRM_ORDER_SYNC_SECRET` no ERP; `ERP_ORDER_SYNC_SECRET` no CRM tem de ser o mesmo segredo. O mesmo segredo autentica a consulta CRM→ERP em `POST /subscription/webhook/crm-orders.php`, usando HMAC de corpo e timestamp; o endpoint consulta `subscription_orders` e exige HTTPS em produção. Agende `php app/cron/sync_subscription_orders.php` no ERP a cada minuto. Cada empresa ERP precisa de associação explícita a uma empresa CRM, configurada por um `support_admin` em **Clientes → Editar cliente**. Não se assume igualdade dos IDs e não há associação automática por nome/NIF.
- O CRM confirma chave, licença, pedido, IDs de ambas as empresas, código e duração do plano, subscrição, datas, validade, versão e duplicação antes de o ERP aplicar estado. O ERP resolve e grava o seu próprio `plans.id` por código+duração ao criar a subscrição e ao activar uma renovação; nunca usa o ID do plano CRM. Pagamento confirmado apenas enfileira o pedido; não activa a subscrição. O reprocessamento exige `status = 'paid'` e `paid_at` preenchido; um pedido sem confirmação financeira registada é recusado sem criar evento. Falhas e datas da última tentativa ficam visíveis no histórico de pedidos, e proprietários/administradores podem reprocessar a entrega sem usar “Atualizar status”, que continua exclusivo da AppyPay.
- Estados de pagamento, pedido, subscrição e licença são independentes. O acesso exige uma subscrição activa e uma licença CRM activa, válida e correspondente ao plano; licenças pendentes, futuras, expiradas, suspensas ou canceladas não autorizam acesso.

## Perfis e permissões
- Os perfis hierárquicos existentes (`owner`, `admin`, `employee`, `viewer`) mantêm-se; proprietários têm acesso total.
- Proprietários e administradores podem configurar o acesso de cada utilizador aos módulos em **Utilizadores → Gerir permissões**.
- A ausência de configuração explícita mantém o acesso atual. As permissões gravadas são verificadas no servidor para páginas e endpoints.
- Antes de usar a funcionalidade, execute uma vez `app/migrations/20261020_create_company_user_permissions.sql` na base MySQL.

## Geração de documentos pela API
- Recibos, notas de crédito/débito, notas de entrega e folhas salariais são gerados pelo servidor genérico do `bxintelligence-api-rest`.
- O histórico documental de facturas/proformas usa as relações `converted_invoice_id` e `invoice_id`, sem migração adicional; a consulta e os PDFs validam a empresa e a permissão do módulo.
- Para instalações existentes, execute `app/migrations/20261020_add_debit_note_discounts.sql` na base MySQL para ativar descontos nas notas de débito.
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

## Segurança (auditoria 2026-11-26)

- **Migração**: `app/migrations/20261126_login_rate_limit.sql` (tabela `rate_limits`, limite de tentativas de login
  por utilizador e por IP). Sem ela o login funciona, mas sem limite no servidor.
- **Endpoints legados** (`public/<modulo>/ajax/*.php`): começar sempre por
  `require_once __DIR__ . '/../../../app/helpers/require_login.php';` e, para registos pedidos por id,
  `require_owned('<tabela>', $id)` ou `WHERE ... AND company_id = session_company_id()`.
  O guard central (`Permission::guardCurrentRequest`) não substitui isto.
- **Uploads**: nome gerado no servidor e extensão a partir do tipo real; `assets/img` e `assets/docs` não executam
  scripts (`.htaccess` + `router.php`). Documentos de funcionários só via `/rh/ajax/employee_file.php`.
- **Login Google**: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` no `.env` (ver `.env.example`).
  O secret antigo esteve no git e tem de ser rodado na Google Cloud Console.
