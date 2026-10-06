<?php
declare(strict_types=1);

namespace Cron\Checks;

use Cron\NotificationRepository;

/**
 * Requer a coluna users.password_changed_at (ver sql/002_add_password_changed_at.sql)
 * e que o endpoint de troca de password grave essa data + is_active = 1.
 *
 * Este check é uma rede de segurança: garante is_active = 1 mesmo que o
 * endpoint da aplicação falhe em fazê-lo, e envia a confirmação por email.
 */
class PasswordChangeCheck
{
    public function __construct(
        private \PDO $pdo,
        private NotificationRepository $repo
    ) {
    }

    public function run(): void
    {
        $stmt = $this->pdo->query("
            SELECT u.id, u.name, u.email, u.password_changed_at, u.is_active,
                   chu.company_id
            FROM users u
            LEFT JOIN company_has_user chu ON chu.user_id = u.id
            WHERE u.password_changed_at IS NOT NULL
              AND u.password_changed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");

        foreach ($stmt as $user) {
            if ((int)$user['is_active'] !== 1) {
                $this->pdo->prepare('UPDATE users SET is_active = 1 WHERE id = :id')
                    ->execute([':id' => $user['id']]);
            }

            if (empty($user['email'])) {
                continue;
            }

            $this->repo->notifyOnce(
                (int)($user['company_id'] ?? 0),
                (int)$user['id'],
                'password_changed',
                'user',
                (int)$user['id'],
                "password_changed:{$user['id']}:{$user['password_changed_at']}",
                $user['email'],
                'A tua password foi alterada',
                "Olá {$user['name']}, confirmamos que a tua password foi alterada em "
                    . (new \DateTimeImmutable($user['password_changed_at']))->format('d/m/Y H:i')
                    . ". A tua conta continua ativa. Se não foste tu, contacta-nos imediatamente."
            );
        }
    }
}