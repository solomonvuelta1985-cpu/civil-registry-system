<?php
/**
 * Calendar Events API
 * Handles CRUD operations for calendar events
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Start session if not already started
header('Content-Type: application/json');

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$user_id = (int)$_SESSION['user_id'];
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) requireCSRFToken();

try {
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo, $user_id);
            break;
        case 'PUT':
            handlePut($pdo, $user_id);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log('Calendar events API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}

function handleGet($pdo) {
    // Single event by ID
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM calendar_events
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([(int)$_GET['id']]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($event) {
            echo json_encode(['success' => true, 'event' => $event]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Event not found']);
        }
        return;
    }

    // Events by date range
    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM calendar_events
            WHERE event_date >= ? AND event_date <= ?
              AND deleted_at IS NULL
              AND status != 'cancelled'
            ORDER BY event_date ASC, event_time ASC
        ");
        $stmt->execute([$_GET['start_date'], $_GET['end_date']]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'events' => $events]);
        return;
    }

    // All events (for "View All" modal)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as created_by_name
        FROM calendar_events e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.deleted_at IS NULL
          AND e.status != 'cancelled'
        ORDER BY e.event_date DESC, e.event_time ASC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'events' => $events]);
}

function handlePost($pdo, $user_id) {
    $data = json_decode(requestBody(), true);

    if (empty($data['event_title']) || empty($data['event_type']) || empty($data['event_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title, type, and date are required']);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO calendar_events (title, event_type, event_date, event_time, priority, description, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $data['event_title'],
        $data['event_type'],
        $data['event_date'],
        !empty($data['event_time']) ? $data['event_time'] : null,
        $data['event_priority'] ?? 'medium',
        $data['event_description'] ?? null,
        $user_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Event created successfully',
        'event_id' => $pdo->lastInsertId()
    ]);
}

function handlePut($pdo, $user_id) {
    $data = json_decode(requestBody(), true);

    if (empty($data['event_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE calendar_events
        SET title = ?, event_type = ?, event_date = ?, event_time = ?,
            priority = ?, description = ?, updated_by = ?, updated_at = NOW()
        WHERE id = ? AND deleted_at IS NULL AND (created_by = ? OR ? = 1)
    ");
    $stmt->execute([
        $data['event_title'],
        $data['event_type'],
        $data['event_date'],
        !empty($data['event_time']) ? $data['event_time'] : null,
        $data['event_priority'] ?? 'medium',
        $data['event_description'] ?? null,
        $user_id,
        (int)$data['event_id'], $user_id, isAdmin() ? 1 : 0
    ]);

    echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
}

function handleDelete($pdo) {
    $data = json_decode(requestBody(), true);

    if (empty($data['event_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        return;
    }

    // Soft delete
    global $user_id;
    $stmt = $pdo->prepare("UPDATE calendar_events SET deleted_at = NOW()
        WHERE id = ? AND (created_by = ? OR ? = 1) AND deleted_at IS NULL");
    $stmt->execute([(int)$data['event_id'], $user_id, isAdmin() ? 1 : 0]);

    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
}
