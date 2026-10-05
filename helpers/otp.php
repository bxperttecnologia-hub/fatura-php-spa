<?php
// app/helpers/otp.php

/**
 * Função para carregar variáveis de ambiente sem dependências externas/Composer
 */
function carregarEnv(string $caminho): void
{
    if (!file_exists($caminho)) {
        return;
    }

    $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        // Ignora comentários
        if ($linha === '' || strpos($linha, '#') === 0) {
            continue;
        }

        if (strpos($linha, '=') !== false) {
            list($chave, $valor) = explode('=', $linha, 2);
            $chave = trim($chave);
            $valor = trim($valor, " \t\n\r\0\x0B\"'"); // Remove espaços e aspas extras

            putenv("$chave=$valor");
            $_ENV[$chave] = $valor;
            $_SERVER[$chave] = $valor;
        }
    }
}

// Carrega o arquivo .env localizado na raiz do projeto (app/helpers/../../.env)
carregarEnv(dirname(__DIR__, 2) . '/.env');

const OTP_TTL_MINUTES        = 10;
const OTP_MAX_ATTEMPTS       = 5;
const OTP_MAX_SENDS_PER_HOUR = 100;
const OTP_MAX_SENDS_PER_IP   = 100;
const OTP_RESEND_SECONDS     = 5;

/** 
 * Devolve o telefone em formato +DDI dígitos, ou null se inválido.
 * Caso o utilizador não digite o '+', assume por defeito o prefixo +244 (Angola).
 */
function normalizarTelefone(string $raw): ?string
{
    $limpo = preg_replace('/[^\d+]/', '', trim($raw));

    if (empty($limpo)) {
        return null;
    }

    $prefixos = ['+244', '+351', '+258', '+264', '+27', '+55', '+44', '+1'];

    // Se o número já começar com +, valida contra a lista de prefixos suportados
    if (str_starts_with($limpo, '+')) {
        foreach ($prefixos as $p) {
            if (str_starts_with($limpo, $p)) {
                $numero = substr($limpo, strlen($p));
                if (ctype_digit($numero) && strlen($numero) >= 7 && strlen($numero) <= 12) {
                    return $p . $numero;
                }
            }
        }
        return null; // O símbolo + foi fornecido, mas o DDI não está na lista
    }

    // BUG FIX: aceita "00244..." (prefixo internacional com zeros) convertendo para "+244..."
    if (str_starts_with($limpo, '00')) {
        return normalizarTelefone('+' . substr($limpo, 2));
    }

    // Se começou com 244 sem o '+', corrige adicionando o '+'
    if (str_starts_with($limpo, '244') && strlen($limpo) == 12) {
        return '+' . $limpo;
    }

    // BUG FIX: remove o zero de tronco local (ex: "0923123456" -> "923123456")
    // antes de o tratar como número nacional. Sem isto, um número digitado
    // com o 0 inicial ficava com um dígito a mais e nunca era reconhecido
    // como o número local que o utilizador de facto tinha.
    if (str_starts_with($limpo, '0')) {
        $limpo = ltrim($limpo, '0');
    }

    // Se digitou apenas o número local (ex: 923123456), assume +244 por defeito
    // BUG FIX: números móveis angolanos têm 9 dígitos; o intervalo 9-10 antigo
    // deixava passar números com um dígito a mais/menos por engano.
    if (ctype_digit($limpo) && strlen($limpo) === 9) {
        return '+244' . $limpo;
    }

    return null;
}

/**
 * Envia o código por SMS ou WhatsApp usando a API da Twilio
 */
function enviarOtp(string $telefone, string $canal, string $codigo): bool
{
    $sid   = getenv('TWILIO_SID');
    $token = getenv('TWILIO_TOKEN');
    $isWa  = ($canal === 'whatsapp');

    $from = $isWa ? getenv('TWILIO_WHATSAPP_FROM') : getenv('TWILIO_SMS_FROM');
    $to   = $isWa ? 'whatsapp:' . $telefone : $telefone;
    $body = "O seu código de verificação é $codigo. Válido por " . OTP_TTL_MINUTES . " minutos. Não o partilhe com ninguém.";

    if (!$sid || !$token || !$from) {
        error_log("Falha no OTP: Variáveis da Twilio não estão configuradas no .env");
        return false;
    }

    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json");

    // BUG FIX: CURLOPT_SSL_VERIFYPEER estava fixo a "false" em todos os ambientes,
    // o que desativa a verificação do certificado do servidor Twilio e expõe o
    // pedido (incluindo o token de autenticação) a um ataque man-in-the-middle.
    // Agora só se desativa quando explicitamente em ambiente de desenvolvimento.
    $ambienteDev = strtolower((string) getenv('APP_ENV')) === 'local'
        || strtolower((string) getenv('APP_ENV')) === 'development';

    $options = [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['From' => $from, 'To' => $to, 'Body' => $body]),
        CURLOPT_USERPWD        => "$sid:$token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => !$ambienteDev, // true em produção
    ];

    curl_setopt_array($ch, $options);
    $res  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch); // BUG FIX: falhas de rede/cURL (timeout, DNS, etc.)
                                  // nunca eram registadas; só erros HTTP eram logados.
    curl_close($ch);

    if ($res === false) {
        error_log("Falha ao enviar OTP ($canal): erro cURL - $erroCurl");
        return false;
    }

    if ($http < 200 || $http >= 300) {
        error_log("Falha ao enviar OTP ($canal): HTTP $http - Resposta: $res");
        return false;
    }

    return true;
}

function gerarUsername(PDO $pdo, string $nome): string
{
    $primeiro = explode(' ', trim($nome))[0];

    // Converte caracteres acentuados para ASCII
    $base = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $primeiro);
    if ($base === false) {
        $base = $primeiro;
    }

    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $base)) ?: 'user';

    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");

    // BUG FIX: o "do/while" original não tinha limite de tentativas; em teoria
    // (e em testes automatizados que esgotam o espaço de números) podia entrar
    // em ciclo infinito. Agora há um número máximo de tentativas, com um
    // sufixo aleatório maior como rede de segurança.
    $tentativas = 0;
    do {
        $tentativas++;
        $username = $tentativas < 50
            ? $base . random_int(100, 9999)
            : $base . random_int(10000, 999999);
        $stmt->execute([$username]);
    } while ($stmt->fetchColumn() && $tentativas < 100);

    return $username;
}

/**
 * Inicia a sessão do utilizador verificado.
 */
function iniciarSessao(int $userId, string $username, string $nome): void
{
    session_regenerate_id(true);

    // BUG FIX: dados temporários do fluxo de OTP (ex.: otp_code, otp_phone,
    // otp_attempts, otp_expires_at) ficavam na sessão mesmo depois do login
    // ser concluído. session_regenerate_id() migra os dados antigos para o
    // novo id de sessão em vez de os limpar, por isso era preciso esvaziar
    // a sessão explicitamente antes de gravar os novos dados do utilizador.
    $_SESSION = [];

    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['name']     = $nome;
}