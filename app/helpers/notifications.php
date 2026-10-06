<?php

function getNotifications($pdo, int $user_id, bool $only_unread, int $limit, int $offset): array
{
    $sql = '
        SELECT
            id,
            title,
            message,
            type,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = :user_id
    ';

    if ($only_unread) {
        $sql .= ' AND is_read = 0';
    }

    $sql .= ' ORDER BY created_at DESC
              LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normaliza tipos (is_read como bool, por exemplo)
    foreach ($rows as &$row) {
        $row['is_read'] = (bool) $row['is_read'];
    }
    unset($row);

    return $rows;
}


function countUnreadNotifications($pdo, int $user_id): int
{
    $sql = '
        SELECT COUNT(*) AS total
        FROM notifications
        WHERE user_id = :user_id
          AND is_read = 0
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($result['total'] ?? 0);
}


function markNotificationAsRead($pdo, int $notification_id, int $user_id): bool
{
    $sql = '
        UPDATE notifications
        SET is_read = 1
        WHERE id = :id
          AND user_id = :user_id
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $notification_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount() > 0;
}


function markAllNotificationsAsRead($pdo, int $user_id): int
{
    $sql = '
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = :user_id
          AND is_read = 0
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount();
}


function createNotification($pdo, int $user_id, string $title, string $message, string $type = 'info'): int
{
    $sql = '
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
        VALUES (:user_id, :title, :message, :type, 0, NOW())
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':title', $title, PDO::PARAM_STR);
    $stmt->bindValue(':message', $message, PDO::PARAM_STR);
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $pdo->lastInsertId();
}
