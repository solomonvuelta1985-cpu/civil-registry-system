<?php
/**
 * Shared in-app notification helpers.
 * Notification rows are immutable snapshots; per-user read state is stored
 * separately so a read on one device is reflected on every device.
 */

function app_notification_store(PDO $pdo, array $notification): void {
    static $schemaWarningLogged = false;

    try {
        $stmt = $pdo->prepare('
            INSERT IGNORE INTO app_notifications
                (notification_key, category, event_type, source_id, actor_user_id,
                 recipient_user_id, title, message, target_url, created_at)
            VALUES
                (:notification_key, :category, :event_type, :source_id, :actor_user_id,
                 :recipient_user_id, :title, :message, :target_url, NOW())
        ');
        $stmt->execute([
            ':notification_key' => substr((string)$notification['notification_key'], 0, 191),
            ':category' => (string)$notification['category'],
            ':event_type' => substr((string)$notification['event_type'], 0, 48),
            ':source_id' => isset($notification['source_id']) ? (string)$notification['source_id'] : null,
            ':actor_user_id' => !empty($notification['actor_user_id']) ? (int)$notification['actor_user_id'] : null,
            ':recipient_user_id' => !empty($notification['recipient_user_id']) ? (int)$notification['recipient_user_id'] : null,
            ':title' => mb_substr(trim((string)$notification['title']), 0, 200),
            ':message' => trim((string)($notification['message'] ?? '')),
            ':target_url' => !empty($notification['target_url']) ? (string)$notification['target_url'] : null,
        ]);
    } catch (Throwable $e) {
        if ($e instanceof PDOException && (string)$e->getCode() === '42S02') return;
        if (!$schemaWarningLogged) {
            error_log('In-app notification could not be stored. Apply migration 054_in_app_notifications.sql. ' . $e->getMessage());
            $schemaWarningLogged = true;
        }
    }
}

function app_notification_record_calendar_event(PDO $pdo, int $eventId, int $actorId, bool $updated = false): void {
    try {
        $stmt = $pdo->prepare('
            SELECT id, title, description, event_type, event_date, event_time, priority, status, deleted_at,
                   created_at, updated_at
              FROM calendar_events
             WHERE id = :id
             LIMIT 1
        ');
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event || !empty($event['deleted_at']) || ($event['status'] ?? '') === 'cancelled') return;

        $version = $updated ? (string)($event['updated_at'] ?? '') : (string)($event['created_at'] ?? '');
        $contentFingerprint = hash('sha256', implode('|', [
            (string)$event['title'], (string)$event['description'], (string)$event['event_type'],
            (string)$event['event_date'], (string)$event['event_time'], (string)$event['priority'], (string)$event['status'],
        ]));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$event['event_date']);
        $dateText = $date ? $date->format('F j, Y') : (string)$event['event_date'];
        $timeText = !empty($event['event_time']) ? ' at ' . date('g:i A', strtotime((string)$event['event_time'])) : '';
        $action = $updated ? 'updated' : 'created';
        $summary = mb_substr(trim(strip_tags((string)($event['description'] ?? ''))), 0, 160);
        $message = 'Scheduled for ' . $dateText . $timeText;
        if (!empty($event['priority'])) $message .= ' | Priority: ' . ucfirst((string)$event['priority']);
        if ($summary !== '') $message .= ' - ' . $summary;

        app_notification_store($pdo, [
            'notification_key' => 'calendar:' . $eventId . ':' . $action . ':' . $version . ':' . substr($contentFingerprint, 0, 24),
            'category' => 'calendar',
            'event_type' => 'event_' . $action,
            'source_id' => (string)$eventId,
            'actor_user_id' => $actorId,
            'title' => ($updated ? 'Calendar event updated: ' : 'New calendar event: ') . $event['title'],
            'message' => $message,
            'target_url' => rtrim(BASE_URL, '/') . '/admin/dashboard.php#calendarWidget',
        ]);
    } catch (Throwable $e) {
        error_log('Calendar notification creation failed: ' . $e->getMessage());
    }
}

function app_notification_record_note(PDO $pdo, int $noteId, int $actorId, bool $updated = false): void {
    try {
        $stmt = $pdo->prepare('
            SELECT id, title, content, note_type, is_pinned, visibility, status, created_by, created_at, updated_at, deleted_at
              FROM system_notes
             WHERE id = :id
             LIMIT 1
        ');
        $stmt->execute([':id' => $noteId]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$note || !empty($note['deleted_at']) || ($note['status'] ?? '') !== 'active') return;

        $visibility = in_array(($note['visibility'] ?? 'team'), ['private', 'team', 'public'], true)
            ? $note['visibility']
            : 'team';
        $version = $updated ? (string)($note['updated_at'] ?? '') : (string)($note['created_at'] ?? '');
        $contentFingerprint = hash('sha256', implode('|', [
            (string)$note['title'], (string)$note['content'], (string)$note['note_type'],
            (string)$note['is_pinned'], $visibility, (string)$note['status'],
        ]));
        $action = $updated ? 'updated' : 'created';

        app_notification_store($pdo, [
            'notification_key' => 'note:' . $noteId . ':' . $action . ':' . $version . ':' . substr($contentFingerprint, 0, 24),
            'category' => 'note',
            'event_type' => 'note_' . $action,
            'source_id' => (string)$noteId,
            'actor_user_id' => $actorId,
            'recipient_user_id' => $visibility === 'private' ? (int)$note['created_by'] : null,
            'title' => ($updated ? 'Note updated: ' : 'New note: ') . $note['title'],
            'message' => mb_substr(trim(strip_tags((string)$note['content'])), 0, 220),
            'target_url' => rtrim(BASE_URL, '/') . '/admin/dashboard.php#notesWidget',
        ]);
    } catch (Throwable $e) {
        error_log('Note notification creation failed: ' . $e->getMessage());
    }
}

function app_notification_record_maintenance(PDO $pdo, string $eventId, string $eventType, string $title, string $message = ''): void {
    app_notification_store($pdo, [
        'notification_key' => 'maintenance:' . $eventId . ':' . $eventType,
        'category' => 'maintenance',
        'event_type' => $eventType,
        'source_id' => $eventId,
        'actor_user_id' => null,
        'title' => $title,
        'message' => $message,
    ]);
}

/** Persist due (or missed while the event remains upcoming) reminders once. */
function app_notification_sync_event_reminders(PDO $pdo): void {
    try {
        $stmt = $pdo->query('
            SELECT id, title, event_date, event_time, reminder_days_before
              FROM calendar_events
             WHERE deleted_at IS NULL
               AND status NOT IN (\'cancelled\', \'completed\')
               AND event_date >= CURDATE()
               AND reminder_days_before >= 0
               AND DATEDIFF(event_date, CURDATE()) <= reminder_days_before
        ');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$event['event_date']);
            $dateText = $date ? $date->format('F j, Y') : (string)$event['event_date'];
            $timeText = !empty($event['event_time']) ? ' at ' . date('g:i A', strtotime((string)$event['event_time'])) : '';
            app_notification_store($pdo, [
                'notification_key' => 'calendar-reminder:' . (int)$event['id'] . ':' . $event['event_date'],
                'category' => 'calendar',
                'event_type' => 'event_reminder',
                'source_id' => (string)$event['id'],
                'title' => 'Upcoming event: ' . $event['title'],
                'message' => 'Scheduled for ' . $dateText . $timeText,
                'target_url' => rtrim(BASE_URL, '/') . '/admin/dashboard.php#calendarWidget',
            ]);
        }
    } catch (Throwable $e) {
        error_log('Calendar reminder synchronization failed: ' . $e->getMessage());
    }
}

/** Persist the stable maintenance events already exposed by the status helper. */
function app_notification_sync_maintenance(PDO $pdo): void {
    if (!function_exists('maintenance_get_notification_events')) return;
    try {
        foreach (maintenance_get_notification_events() as $event) {
            if (empty($event['id']) || empty($event['title'])) continue;
            $eventType = (string)($event['type'] ?? 'update');
            $eventId = (string)$event['id'];
            $suffix = ':' . $eventType;
            if (substr($eventId, -strlen($suffix)) === $suffix) {
                $eventId = substr($eventId, 0, -strlen($suffix));
            }
            $message = trim((string)($event['message'] ?? ''));
            $window = implode(' to ', array_filter([
                trim((string)($event['starts_at'] ?? '')),
                trim((string)($event['ends_at'] ?? '')),
            ]));
            if ($window !== '') $message = trim($message . ' ' . $window);
            app_notification_record_maintenance(
                $pdo,
                $eventId,
                $eventType,
                (string)$event['title'],
                $message
            );
        }
    } catch (Throwable $e) {
        error_log('Maintenance notification synchronization failed: ' . $e->getMessage());
    }
}
