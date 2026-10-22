<?php
// app/helpers/otp.php

require_once __DIR__ . '/gosms.php';

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
 * Envia o código de verificação por SMS através da GoSMS.
 */
function enviarOtp(string $telefone, string $canal, string $codigo, ?string &$erro = null): bool
{
    $body = "O seu código de verificação é $codigo. Válido por " . OTP_TTL_MINUTES . " minutos. Não o partilhe com ninguém.";
    if ($canal !== 'sms') {
        $erro = 'Canal de envio não suportado.';
        error_log('[otp] Canal de envio não suportado.');
        return false;
    }

    return enviarMensagemGoSms($telefone, $body, $erro);
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