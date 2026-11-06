# Deploy no aaPanel (SPA + API AGT)

Arquitectura recomendada: SPA (PHP) e API AGT (Node) no **mesmo servidor**. O PHP chama a API
por `http://127.0.0.1:4000`. A API não precisa de domínio público nem de porta aberta.

```
browser ──HTTPS──> Nginx (app.dominio) ──> PHP-FPM  fatura-php-spa/public
                                              │
                                              └─ http://127.0.0.1:4000 ──> PM2: agt-facturacao-api ──> AGT
```

## 1. Requisitos no servidor

- PHP **8.2+** com as extensões `pdo_mysql`, `mbstring`, `gd`, `intl`, `zip`, `curl`, `openssl`, `fileinfo`, `dom`
  e `composer`. No aaPanel: *App Store → PHP 8.2 → Setting → Install extensions*.
- Node **20 LTS** (*App Store → Node.js version manager*) e PM2.
- MySQL 8 / MariaDB 10.6+.
- Em *PHP 8.2 → Setting → Performance*: `pm.max_children` ≥ 10. Os PDFs (Dompdf) fazem pedidos ao
  próprio site; com poucos processos, o pedido fica à espera de si próprio e bloqueia.
- Em *PHP → Disabled functions*: o `putenv` pode ficar desactivado, porque o código não depende dele.

## 2. Base de dados

1. No aaPanel: *Databases → Add*. Criar a BD e um utilizador próprio com palavra-passe forte (não usar `root`).
2. Importar um **dump da BD local já migrada** (`mysqldump db_fatura_test`). Não correr as migrações no
   servidor: a BD não tem `SequelizeMeta`, porque as migrações foram aplicadas à mão.
3. A API AGT usa a sua própria BD (`DB_NAME` no `.env` da API). Importar também essa.

## 3. Código

```bash
cd /www/wwwroot
git clone <repo-spa> fatura-php-spa      # branch de produção
git clone <repo-api> agt-facturacao-api
cd fatura-php-spa && composer install --no-dev --optimize-autoloader
cd ../agt-facturacao-api && npm ci --omit=dev
```

### Ficheiros que NÃO estão no Git (copiar à mão por SFTP)

| Ficheiro | Para quê |
|---|---|
| `fatura-php-spa/.env` | a partir de `.env.example` |
| `fatura-php-spa/app/keys/private.key` e `public.key` | cifra RSA do login; sem eles o login falha |
| `agt-facturacao-api/.env` | a partir de `.env.example` |
| `agt-facturacao-api/secrets/*.pem` | chave privada do produtor de software (FE/384) |
| `agt-facturacao-api/src/config/keys/` | se existir |
| `public/assets/img/companies/` e `profiles/` | logótipos e fotos já carregados |

Permissões: `chown -R www:www fatura-php-spa` e `chmod 600` em `.env`, `app/keys/*` e `secrets/*`.

## 4. Site do SPA (Nginx)

*Website → Add site* com o domínio do SPA, e PHP 8.2.

- **Site directory → Running directory: `/public`**. É obrigatório: na raiz do projecto estão o `.env`,
  o `app/`, o `vendor/` e as chaves.
- **SSL → Let's Encrypt** e activar *Force HTTPS*.
- **URL rewrite** (separador *URL rewrite* do site); colar:

```nginx
# Ficheiros que nunca devem ser servidos
location ~ /\.            { deny all; }
location ~* \.(bak|sql|log|md|ini|sh|lock|pem|key|txt|env|dist|orig|ya?ml)$ { deny all; }
location ~* \.bak[.\-]    { deny all; }
location ^~ /rh/migrations { deny all; }

# PDFs de documentos: /api/documents/receipts/5/pdf -> document_pdf.php?path=receipts/5/pdf
location ^~ /api/documents/ {
    rewrite ^/api/documents/(.*)$ /document_pdf.php?path=$1 last;
}
# API do SPA: /api/clientes/5 -> api.php?r=clientes/5
location ^~ /api/ {
    rewrite ^/api/(.*)$ /api.php?r=$1 last;
}
# Rotas da SPA (/contacts, /invoices/create...) -> shell. Sem "$uri/", de propósito:
# há pastas com o mesmo nome das rotas (/login, /contacts...).
location / {
    try_files $uri /index.php?$query_string;
}
```

Se o site usar Apache em vez de Nginx, os `.htaccess` já tratam disto (`AllowOverride All` e `mod_rewrite`).

### `.env` do SPA em produção (diferenças principais)

```
APP_ENV=production
DB_HOST=127.0.0.1
AGT_API_URL=http://127.0.0.1:4000
AGT_API_KEY=<igual ao API_KEY da API>
AGT_ENVIRONMENT=test            # production só com certificação e credenciais PROD da AGT
AI_API_BASE_URL=https://...     # API de IA BXpert
DOCUMENT_API_BASE_URL=https://...
```

## 5. API AGT (PM2)

`.env` da API em produção:

```
NODE_ENV=production
HOST=127.0.0.1
PORT=4000
CORS_ORIGIN=                    # vazio: nenhum browser chama a API directamente
API_KEY=<openssl rand -base64 32>
API_DOCS=false
```

Arrancar com o *Node project manager* do aaPanel (start command `node src/server.js`) ou com:

```bash
cd /www/wwwroot/agt-facturacao-api
pm2 start src/server.js --name agt-api
pm2 save && pm2 startup
curl http://127.0.0.1:4000/health      # {"success":true,"status":"ok"}
```

A API só lê o `.env` no arranque, por isso **depois de mexer no `.env` é preciso `pm2 restart agt-api`**.

Se mais tarde a API precisar de ficar pública (num subdomínio): proxy reverso no aaPanel para
`127.0.0.1:4000`, com SSL, `CORS_ORIGIN=https://app.dominio` e o `API_KEY` sempre obrigatório.

## 6. Cron (*Cron → Add task → Shell script*)

```bash
# Cobranças automáticas, todos os dias às 09:00
php /www/wwwroot/fatura-php-spa/bin/cobrancas_automaticas.php
```

Acrescentar também um backup diário das duas BDs (*Cron → Backup database*).

## 7. Firewall (*Security*)

Abrir só as portas 80, 443, SSH e a porta do painel. As portas **4000 e 3306 ficam fechadas**.
Mudar a porta SSH por omissão e usar uma chave SSH.

## 8. Verificação depois do deploy

- `https://app.dominio/.env`, `/app/config/config.php` e `/vendor/autoload.php` → 403/404
- Login, criar FT em rascunho, finalizar (envio à AGT), PDF, enviar por email
- `pm2 logs agt-api` sem erros; *PHP → Logs* sem erros fatais
