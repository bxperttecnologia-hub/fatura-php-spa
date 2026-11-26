<?php
/**
 * Registo de dispositivos da app mobile (Expo) e preferências de push.
 * Chamado pelo JS da SPA dentro da WebView (public/assets/js/native-bridge.js), com a sessão e o CSRF normais:
 * a app nativa não tem autenticação própria.
 */
class PushController {
    private const TOKEN_RE = '/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_\-]{10,200}\]$/';

    /** POST /api/push/register  {token, platform: ios|android, device_name?, app_version?} */
    public function register(array $in): array {
        $user  = Auth::user();
        $token = trim((string)($in['token'] ?? ''));
        $platform = (string)($in['platform'] ?? '');

        if (!preg_match(self::TOKEN_RE, $token)) throw new HttpException(422, 'Token de push inválido.');
        if (!in_array($platform, ['ios', 'android'], true)) throw new HttpException(422, 'Plataforma inválida.');

        $pdo = Database::pdo();
        // Upsert pelo token: num telemóvel partilhado, o token passa a pertencer a quem entrou agora.
        $pdo->prepare('
            INSERT INTO push_devices (user_id, company_id, expo_token, platform, device_name, app_version, last_seen_at, revoked_at)
            VALUES (:user_id, :company_id, :token, :platform, :device_name, :app_version, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id), company_id = VALUES(company_id), platform = VALUES(platform),
                device_name = VALUES(device_name), app_version = VALUES(app_version),
                last_seen_at = NOW(), revoked_at = NULL
        ')->execute([
            ':user_id'     => (int)$user['id'],
            ':company_id'  => (int)($user['company_id'] ?? 0) ?: null,
            ':token'       => $token,
            ':platform'    => $platform,
            ':device_name' => mb_substr(trim((string)($in['device_name'] ?? '')), 0, 100) ?: null,
            ':app_version' => mb_substr(trim((string)($in['app_version'] ?? '')), 0, 20) ?: null,
        ]);

        // Guardado na sessão para o logout revogar este dispositivo (Auth::logout).
        $_SESSION['push_token'] = $token;

        return ['data' => ['registered' => true, 'unread' => $this->unread((int)$user['id'])]];
    }

    /** POST /api/push/unregister  {token} — deixa de enviar para este dispositivo. */
    public function unregister(array $in): array {
        $token = trim((string)($in['token'] ?? ($_SESSION['push_token'] ?? '')));
        if ($token !== '') {
            self::revoke($token, (int)Auth::user()['id']);
        }
        unset($_SESSION['push_token']);
        return ['data' => ['unregistered' => true]];
    }

    /** GET /api/push/badge — número de notificações por ler (badge do ícone da app). */
    public function badge(): array {
        return ['data' => ['unread' => $this->unread((int)Auth::user()['id'])]];
    }

    /** GET /api/push/preferences — tipos com push e se estão ligados para este utilizador. */
    public function preferences(): array {
        require_once ROOT . '/app/helpers/notification_service.php';
        $st = Database::pdo()->prepare('SELECT type, push FROM notification_preferences WHERE user_id = ?');
        $st->execute([(int)Auth::user()['id']]);
        $saved = $st->fetchAll(PDO::FETCH_KEY_PAIR);

        $out = [];
        foreach (BX_NOTIFICATION_TYPES as $type => $meta) {
            if (!$meta['push']) continue;
            $out[] = ['type' => $type, 'push' => isset($saved[$type]) ? (bool)$saved[$type] : true];
        }
        return ['data' => $out];
    }

    /** PUT /api/push/preferences  {type, push: bool} */
    public function updatePreference(array $in): array {
        require_once ROOT . '/app/helpers/notification_service.php';
        $type = (string)($in['type'] ?? '');
        if (!isset(BX_NOTIFICATION_TYPES[$type])) throw new HttpException(422, 'Tipo de notificação desconhecido.');

        Database::pdo()->prepare('
            INSERT INTO notification_preferences (user_id, type, push) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE push = VALUES(push)
        ')->execute([(int)Auth::user()['id'], $type, !empty($in['push']) ? 1 : 0]);

        return ['data' => ['type' => $type, 'push' => !empty($in['push'])]];
    }

    /** Revoga um token (só se pertencer ao utilizador indicado). Usado também pelo logout. */
    public static function revoke(string $token, int $userId): void {
        try {
            Database::pdo()->prepare('UPDATE push_devices SET revoked_at = NOW() WHERE expo_token = ? AND user_id = ? AND revoked_at IS NULL')
                ->execute([$token, $userId]);
        } catch (Throwable $e) {
            error_log('PushController::revoke: ' . $e->getMessage());
        }
    }

    private function unread(int $userId): int {
        $st = Database::pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $st->execute([$userId]);
        return (int)$st->fetchColumn();
    }
}
