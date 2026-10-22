<?php

function gosmsLoadConfig(): void
{
    $envPath = dirname(__DIR__, 2) . '/.env';
    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (!in_array($key, ['GOSMS_TOKEN', 'GOSMS_TOKEN_API', 'GOSMS_FROM', 'GOSMS_CA_BUNDLE'], true) || getenv($key) !== false) {
                continue;
            }

            $value = trim($value, " \t\n\r\0\x0B\"'");
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    if ((string) getenv('GOSMS_TOKEN') === '' && (string) getenv('GOSMS_TOKEN_API') !== '') {
        $token = (string) getenv('GOSMS_TOKEN_API');
        putenv('GOSMS_TOKEN=' . $token);
        $_ENV['GOSMS_TOKEN'] = $token;
        $_SERVER['GOSMS_TOKEN'] = $token;
    }
}

function enviarMensagemGoSms(string $telefone, string $mensagem, ?string &$erro = null): bool
{
    $erro = null;
    gosmsLoadConfig();

    $token = (string) getenv('GOSMS_TOKEN');
    $from = (string) getenv('GOSMS_FROM');
    if ($token === '' || $from === '') {
        $erro = 'Configuração do serviço SMS incompleta.';
        error_log('[gosms] GOSMS_TOKEN ou GOSMS_FROM não configurado.');
        return false;
    }

    $to = preg_replace('/\D+/', '', $telefone);
    if (str_starts_with($to, '244') && strlen($to) === 12) {
        $to = substr($to, 3);
    }
    if ($to === '') {
        $erro = 'Número de destino inválido.';
        error_log('[gosms] Número de destino inválido.');
        return false;
    }

    $payload = json_encode([
        'message' => $mensagem,
        'from' => $from,
        'to' => $to,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        $erro = 'Não foi possível preparar a mensagem.';
        error_log('[gosms] Não foi possível preparar a mensagem.');
        return false;
    }

    $ch = curl_init('https://api.go-sms.co.ao/v1/messages');
    if ($ch === false) {
        $erro = 'Não foi possível iniciar a ligação ao serviço SMS.';
        error_log('[gosms] Não foi possível iniciar o pedido cURL.');
        return false;
    }

    $options = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    $caBundle = (string) getenv('GOSMS_CA_BUNDLE');
    if ($caBundle !== '') {
        if (!is_file($caBundle) || !is_readable($caBundle)) {
            curl_close($ch);
            $erro = 'O certificado de segurança do serviço SMS não está configurado corretamente.';
            error_log('[gosms] O ficheiro configurado em GOSMS_CA_BUNDLE não existe ou não pode ser lido.');
            return false;
        }
        $options[CURLOPT_CAINFO] = $caBundle;
    }
    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $erro = 'Falha na ligação ao serviço SMS. ' . $curlError;
        error_log('[gosms] Falha de comunicação: ' . $curlError);
        return false;
    }

    if ($http < 200 || $http >= 300) {
        $body = json_decode((string) $response, true);
        $messages = [];
        $apiErrors = $body['errors'] ?? null;
        if (is_array($apiErrors)) {
            if (isset($apiErrors['message']) && is_string($apiErrors['message'])) {
                $messages[] = $apiErrors['message'];
            } else {
                foreach ($apiErrors as $apiError) {
                    if (is_array($apiError) && isset($apiError['message']) && is_string($apiError['message'])) {
                        $messages[] = $apiError['message'];
                    }
                }
            }
        }
        if (!$messages && isset($body['message']) && is_string($body['message'])) {
            $messages[] = $body['message'];
        }

        $apiMessage = trim(strip_tags(implode('; ', $messages)));
        $erro = $apiMessage !== ''
            ? mb_substr($apiMessage, 0, 200)
            : "A GoSMS rejeitou o pedido (HTTP $http).";
        error_log("[gosms] HTTP $http: " . $erro);
        return false;
    }

    return true;
}
