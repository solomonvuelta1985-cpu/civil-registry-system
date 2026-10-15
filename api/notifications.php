<?php
/** Authenticated in-app notification feed and per-user read state. */
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/settings.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
requireAuth();

$userId = (int)getUserId();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method === 'GET') {
        app_notification_sync_event_reminders($pdo);
        app_notification_sync_maintenance($pdo);
        notificationSendFeed($pdo, $userId);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    requireCSRFToken();
    $data = json_decode(requestBody(), true);
    if (!is_array($data)) $data = [];

    if (($data['action'] ?? '') === 'read_all') {
        $stmt = $pdo->prepare('
            INSERT IGNORE INTO app_notification_reads (notification_id, user_id, read_at)
            SELECT n.id, :user_id, NOW()
              FROM app_notifications n
              LEFT JOIN app_notification_reads r
                ON r.notification_id = n.id AND r.user_id = :read_user_id
             WHERE r.notification_id IS NULL
               AND (n.recipient_user_id IS NULL OR n.recipient_user_id = :recipient_user_id)
               AND (n.actor_user_id IS NULL OR n.actor_user_id <> :actor_user_id)
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':read_user_id' => $userId,
            ':recipient_user_id' => $userId,
            ':actor_user_id' => $userId,
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (($data['action'] ?? '') === 'read' && filter_var($data['notification_id'] ?? null, FILTER_VALIDATE_INT)) {
        $notificationId = (int)$data['notification_id'];
        $stmt = $pdo->prepare('
            INSERT IGNORE INTO app_notification_reads (notification_id, user_id, read_at)
            SELECT n.id, :user_id, NOW()
              FROM app_notifications n
             WHERE n.id = :notification_id
               AND (n.recipient_user_id IS NULL OR n.recipient_user_id = :recipient_user_id)
               AND (n.actor_user_id IS NULL OR n.actor_user_id <> :actor_user_id)
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':notification_id' => $notificationId,
            ':recipient_user_id' => $userId,
            ':actor_user_id' => $userId,
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Choose a valid notification action.']);
} catch (Throwable $e) {
    error_log('Notification API error: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Notifications are temporarily unavailable. Please apply the latest database migration and try again.',
    ]);
}

function notificationSendFeed(PDO $pdo, int $userId): void {
    $visible = '(n.recipient_user_id IS NULL OR n.recipient_user_id = :recipient_user_id) AND (n.actor_user_id IS NULL OR n.actor_user_id <> :actor_user_id)';

    $encodingStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN DATE(encoded_records.created_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS today_count,
            COUNT(*) AS total_count
        FROM (
            SELECT created_at FROM certificate_of_live_birth
             WHERE status <> 'Deleted' AND created_by = :birth_user_id
            UNION ALL
            SELECT created_at FROM certificate_of_death
             WHERE status <> 'Deleted' AND created_by = :death_user_id
            UNION ALL
            SELECT created_at FROM certificate_of_marriage
             WHERE status <> 'Deleted' AND created_by = :marriage_user_id
            UNION ALL
            SELECT created_at FROM application_for_marriage_license
             WHERE status <> 'Deleted' AND created_by = :license_user_id
        ) encoded_records
    ");
    $encodingStmt->execute([
        ':birth_user_id' => $userId,
        ':death_user_id' => $userId,
        ':marriage_user_id' => $userId,
        ':license_user_id' => $userId,
    ]);
    $encodingRow = $encodingStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $countStmt = $pdo->prepare('
        SELECT COUNT(*)
          FROM app_notifications n
          LEFT JOIN app_notification_reads r
            ON r.notification_id = n.id AND r.user_id = :read_user_id
         WHERE r.notification_id IS NULL AND ' . $visible
    );
    $countStmt->execute([
        ':read_user_id' => $userId,
        ':recipient_user_id' => $userId,
        ':actor_user_id' => $userId,
    ]);
    $unreadCount = (int)$countStmt->fetchColumn();

    $baseSelect = '
        SELECT n.id, n.category, n.event_type, n.title, n.message, n.target_url,
               n.created_at, (r.notification_id IS NOT NULL) AS is_read
          FROM app_notifications n
          LEFT JOIN app_notification_reads r
            ON r.notification_id = n.id AND r.user_id = :read_user_id
         WHERE ' . $visible;

    $unreadStmt = $pdo->prepare($baseSelect . ' AND r.notification_id IS NULL ORDER BY n.created_at DESC');
    $unreadStmt->execute([
        ':read_user_id' => $userId,
        ':recipient_user_id' => $userId,
        ':actor_user_id' => $userId,
    ]);
    $items = $unreadStmt->fetchAll(PDO::FETCH_ASSOC);

    $readStmt = $pdo->prepare($baseSelect . ' AND r.notification_id IS NOT NULL ORDER BY n.created_at DESC LIMIT 50');
    $readStmt->execute([
        ':read_user_id' => $userId,
        ':recipient_user_id' => $userId,
        ':actor_user_id' => $userId,
    ]);
    $items = array_merge($items, $readStmt->fetchAll(PDO::FETCH_ASSOC));
    usort($items, static function (array $left, array $right): int {
        return strcmp((string)$right['created_at'], (string)$left['created_at']);
    });

    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'encoding_stats' => [
            'today' => (int)($encodingRow['today_count'] ?? 0),
            'all_time' => (int)($encodingRow['total_count'] ?? 0),
        ],
        'notifications' => $items,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
