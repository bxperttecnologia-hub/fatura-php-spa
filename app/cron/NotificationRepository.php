<?php

declare(strict_types=1);

namespace Cron;

require_once __DIR__ . '/../helpers/notification_service.php';
require_once __DIR__ . '/../helpers/env.php';

/**
 * Notificações dos crons, sobre o serviço central (bx_notify): sino + push, sem duplicados (dedupe_key).
 * O e-mail é opcional: só com NOTIFICATIONS_SEND_EMAIL=1 no .env e SMTP configurado.
 */
class NotificationRepository
{
    private bool $sendEmail;

    public function __construct(private \PDO $pdo)
    {
        $this->sendEmail = in_array(strtolower((string)bx_env('NOTIFICATIONS_SEND_EMAIL')), ['1', 'true', 'yes', 'on'], true);
        if ($this->sendEmail) {
            require_once __DIR__ . '/../../public/rh/lib/rh_mail.php';
        }
    }

    /**
     * Cria a notificação uma única vez por $dedupeKey.
     * $userId null = todos os owner/admin da empresa (a chave fica única por utilizador).
     * $email só é usado quando há $userId (e-mail do próprio utilizador notificado).
     * Devolve quantas notificações novas foram criadas.
     */
    public function notifyOnce(
        int $companyId,
        ?int $userId,
        string $type,
        string $entityType,
        int $entityId,
        string $dedupeKey,
        ?string $email,
        string $title,
        string $message,
        ?string $url = null
    ): int {
        $notification = [
            'company_id'  => $companyId > 0 ? $companyId : null,
            'type'        => $type,
            'title'       => $title,
            'message'     => $message,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'dedupe_key'  => $dedupeKey,
        ];
        if ($url !== null) {
            $notification['url'] = $url;
        }

        if ($userId === null) {
            return bx_notify_company_admins($this->pdo, $companyId, $notification);
        }

        $notification['user_id'] = $userId;
        $id = bx_notify($this->pdo, $notification);
        if ($id === null) {
            return 0;   // já tinha sido notificado (ou falhou: ver error_log)
        }

        if ($this->sendEmail && $email) {
            $error = rh_send_mail($email, $title, $message);
            if ($error !== null) {
                error_log("NotificationRepository: e-mail para utilizador {$userId} falhou: {$error}");
            }
        }
        return 1;
    }
}
