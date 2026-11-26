<?php

declare(strict_types=1);

/**
 * Ponto único de criação de notificações: sino da SPA (tabela `notifications`)
 * e, quando o tipo o justifica, push para a app mobile (fila `push_queue`).
 *
 * Regra: NENHUM código deve fazer INSERT INTO notifications diretamente — usar bx_notify()
 * (ou bx_notify_company_admins()). Assim o push chega a todas as notificações relevantes.
 *
 * Requer app/migrations/20261126_notifications_push.sql. Sem ela, cai num INSERT legado
 * (sem link, sem push) para o sino continuar a funcionar.
 */

/**
 * Tipos conhecidos: se geram push por omissão e o link para onde a notificação leva.
 * Tipos de "lembrete" gerados ao abrir páginas (perfil/empresa incompletos) NÃO vão por push.
 */
const BX_NOTIFICATION_TYPES = [
    'info'                       => ['push' => false, 'url' => null],
    'missing_profile_data'       => ['push' => false, 'url' => '/profile'],
    'missing_company_data'       => ['push' => false, 'url' => '/companies'],
    'invoice_due_soon'           => ['push' => true,  'url' => '/invoices'],
    'invoice_overdue'            => ['push' => true,  'url' => '/invoices'],
    'subscription_expiring'      => ['push' => true,  'url' => '/subscription'],
    'subscription_expired'       => ['push' => true,  'url' => '/subscription'],
    'subscription_order_pending' => ['push' => true,  'url' => '/subscription'],
    'password_changed'           => ['push' => true,  'url' => '/profile'],
];

/**
 * Cria uma notificação. Devolve o id, ou null se já existia (mesma dedupe_key) ou se falhou.
 * Nunca lança exceção: uma notificação nunca pode partir a operação que a originou.
 *
 * @param array{
 *   user_id:int, title:string, message:string, type?:string, company_id?:?int,
 *   url?:?string, entity_type?:?string, entity_id?:?int, dedupe_key?:?string, push?:?bool
 * } $n
 */
function bx_notify(PDO $pdo, array $n): ?int
{
    static $extendedSchema = true;

    $n = bx_notification_normalize($n);
    if ($n === null) {
        return null;
    }

    try {
        if ($extendedSchema) {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO notifications
                        (user_id, company_id, type, title, message, url, entity_type, entity_id, dedupe_key, is_read, created_at)
                    VALUES
                        (:user_id, :company_id, :type, :title, :message, :url, :entity_type, :entity_id, :dedupe_key, 0, NOW())
                ');
                $stmt->execute([
                    ':user_id'     => $n['user_id'],
                    ':company_id'  => $n['company_id'],
                    ':type'        => $n['type'],
                    ':title'       => $n['title'],
                    ':message'     => $n['message'],
                    ':url'         => $n['url'],
                    ':entity_type' => $n['entity_type'],
                    ':entity_id'   => $n['entity_id'],
                    ':dedupe_key'  => $n['dedupe_key'],
                ]);
            } catch (PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);
                if ($code === 1062) {          // dedupe_key repetida: já notificado
                    return null;
                }
                if ($code !== 1054) {          // 1054 = coluna desconhecida (migração por correr)
                    throw $e;
                }
                $extendedSchema = false;
                error_log('bx_notify: falta correr app/migrations/20261126_notifications_push.sql; a usar o INSERT legado.');
            }
        }

        if (!$extendedSchema) {
            return bx_notify_legacy($pdo, $n);
        }

        $id = (int)$pdo->lastInsertId();
        if ($n['push']) {
            bx_push_enqueue($pdo, $id, $n);
        }
        return $id;
    } catch (Throwable $e) {
        error_log('bx_notify: ' . $e->getMessage());
        return null;
    }
}

/**
 * Notifica os responsáveis de uma empresa (por omissão owner/admin).
 * A dedupe_key, se existir, é tornada única por utilizador. Devolve quantas foram criadas.
 */
function bx_notify_company_admins(PDO $pdo, int $companyId, array $n, array $roles = ['owner', 'admin']): int
{
    if ($companyId <= 0 || !$roles) {
        return 0;
    }
    try {
        $in = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM company_has_user WHERE company_id = ? AND role IN ($in)");
        $stmt->execute(array_merge([$companyId], array_values($roles)));
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        error_log('bx_notify_company_admins: ' . $e->getMessage());
        return 0;
    }

    $created = 0;
    foreach ($userIds as $userId) {
        $one = $n;
        $one['user_id'] = (int)$userId;
        $one['company_id'] = $companyId;
        if (!empty($n['dedupe_key'])) {
            $one['dedupe_key'] = $n['dedupe_key'] . ':u' . (int)$userId;
        }
        if (bx_notify($pdo, $one) !== null) {
            $created++;
        }
    }
    return $created;
}

/** Valida e completa os campos. Devolve null se faltar o essencial. */
function bx_notification_normalize(array $n): ?array
{
    $userId  = (int)($n['user_id'] ?? 0);
    $title   = trim((string)($n['title'] ?? ''));
    $message = trim((string)($n['message'] ?? ''));
    if ($userId <= 0 || $title === '' || $message === '') {
        error_log('bx_notify: user_id, title e message são obrigatórios.');
        return null;
    }

    $type = substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string)($n['type'] ?? 'info'))) ?: 'info', 0, 50);
    $meta = BX_NOTIFICATION_TYPES[$type] ?? ['push' => false, 'url' => null];

    // Só caminhos internos ("/x"), nunca URLs externos nem "//host": o link é aberto dentro da app.
    $url = $n['url'] ?? $meta['url'];
    if (!is_string($url) || !preg_match('#^/(?!/)[^\s]*$#', $url) || strlen($url) > 255) {
        $url = null;
    }

    $dedupe = isset($n['dedupe_key']) && $n['dedupe_key'] !== '' ? (string)$n['dedupe_key'] : null;
    if ($dedupe !== null && strlen($dedupe) > 191) {
        $dedupe = substr($dedupe, 0, 150) . ':' . sha1($dedupe);   // mantém-se única e cabe no índice
    }

    return [
        'user_id'     => $userId,
        'company_id'  => isset($n['company_id']) && (int)$n['company_id'] > 0 ? (int)$n['company_id'] : null,
        'type'        => $type,
        'title'       => mb_substr($title, 0, 150),
        'message'     => $message,
        'url'         => $url,
        'entity_type' => isset($n['entity_type']) ? mb_substr((string)$n['entity_type'], 0, 40) : null,
        'entity_id'   => isset($n['entity_id']) && (int)$n['entity_id'] > 0 ? (int)$n['entity_id'] : null,
        'dedupe_key'  => $dedupe,
        'push'        => isset($n['push']) ? (bool)$n['push'] : (bool)$meta['push'],
    ];
}

/** INSERT do esquema antigo (antes da migração 20261126). Sem push; dedupe aproximada por dia. */
function bx_notify_legacy(PDO $pdo, array $n): ?int
{
    if ($n['dedupe_key'] !== null) {
        $stmt = $pdo->prepare('
            SELECT 1 FROM notifications
            WHERE user_id = ? AND type = ? AND title = ? AND DATE(created_at) = CURDATE()
            LIMIT 1
        ');
        $stmt->execute([$n['user_id'], $n['type'], $n['title']]);
        if ($stmt->fetchColumn()) {
            return null;
        }
    }
    $pdo->prepare('
        INSERT INTO notifications (user_id, company_id, type, title, message, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ')->execute([$n['user_id'], $n['company_id'], $n['type'], $n['title'], $n['message']]);
    return (int)$pdo->lastInsertId();
}

/**
 * Põe na fila um push por dispositivo ativo do utilizador (o envio é feito pelo worker).
 * Respeita notification_preferences. Devolve quantos pushes foram enfileirados.
 */
function bx_push_enqueue(PDO $pdo, int $notificationId, array $n): int
{
    try {
        $pref = $pdo->prepare('SELECT push FROM notification_preferences WHERE user_id = ? AND type = ?');
        $pref->execute([$n['user_id'], $n['type']]);
        $allowed = $pref->fetchColumn();
        if ($allowed !== false && (int)$allowed === 0) {
            return 0;
        }

        $devices = $pdo->prepare('SELECT id FROM push_devices WHERE user_id = ? AND revoked_at IS NULL');
        $devices->execute([$n['user_id']]);
        $deviceIds = $devices->fetchAll(PDO::FETCH_COLUMN);
        if (!$deviceIds) {
            return 0;
        }

        $body = $n['message'];
        if (mb_strlen($body) > 178) {
            $body = mb_substr($body, 0, 175) . '...';
        }
        $payload = json_encode([
            'title'     => $n['title'],
            'body'      => $body,
            'sound'     => 'default',
            'channelId' => 'default',
            'data'      => [
                'notification_id' => $notificationId,
                'type'            => $n['type'],
                'url'             => $n['url'],
                'company_id'      => $n['company_id'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $insert = $pdo->prepare('INSERT INTO push_queue (notification_id, device_id, payload) VALUES (?, ?, ?)');
        foreach ($deviceIds as $deviceId) {
            $insert->execute([$notificationId, (int)$deviceId, $payload]);
        }
        return count($deviceIds);
    } catch (Throwable $e) {
        error_log('bx_push_enqueue: ' . $e->getMessage());
        return 0;
    }
}
