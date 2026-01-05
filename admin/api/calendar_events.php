<?php
/**
 * Calendar Events API
 * Handles CRUD operations for calendar events
 */

require_once '../../includes/session_config.php';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // Create new event
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (empty($data['event_title']) || empty($data['event_type']) || empty($data['event_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO calendar_events
                (title, event_type, event_date, event_time, priority, description, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");

            $result = $stmt->execute([
                sanitize_input($data['event_title']),
                sanitize_input($data['event_type']),
                sanitize_input($data['event_date']),
                !empty($data['event_time']) ? sanitize_input($data['event_time']) : null,
                !empty($data['event_priority']) ? sanitize_input($data['event_priority']) : 'medium',
                !empty($data['event_description']) ? sanitize_input($data['event_description']) : null,
                $user_id
            ]);

            if ($result) {
                $event_id = $pdo->lastInsertId();

                // Log activity
                log_activity($pdo, $user_id, 'CREATE_EVENT', 'calendar_events', $event_id,
                    "Created event: " . $data['event_title']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Event created successfully',
                    'event_id' => $event_id
                ]);
            } else {
                throw new Exception('Failed to create event');
            }
            break;

        case 'PUT':
            // Update existing event
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['event_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Event ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE calendar_events
                SET title = ?,
                    event_type = ?,
                    event_date = ?,
                    event_time = ?,
                    priority = ?,
                    description = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ? AND deleted_at IS NULL
            ");

            $result = $stmt->execute([
                sanitize_input($data['event_title']),
                sanitize_input($data['event_type']),
                sanitize_input($data['event_date']),
                !empty($data['event_time']) ? sanitize_input($data['event_time']) : null,
                !empty($data['event_priority']) ? sanitize_input($data['event_priority']) : 'medium',
                !empty($data['event_description']) ? sanitize_input($data['event_description']) : null,
                !empty($data['status']) ? sanitize_input($data['status']) : 'scheduled',
                $data['event_id']
            ]);

            if ($result) {
                // Log activity
                log_activity($pdo, $user_id, 'UPDATE_EVENT', 'calendar_events', $data['event_id'],
                    "Updated event: " . $data['event_title']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Event updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update event');
            }
            break;

        case 'DELETE':
            // Soft delete event
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['event_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Event ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE calendar_events
                SET deleted_at = NOW(), status = 'cancelled'
                WHERE id = ? AND deleted_at IS NULL
            ");

            $result = $stmt->execute([$data['event_id']]);

            if ($result) {
                // Log activity
                log_activity($pdo, $user_id, 'DELETE_EVENT', 'calendar_events', $data['event_id'],
                    "Deleted event ID: " . $data['event_id']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Event deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete event');
            }
            break;

        case 'GET':
            // Get events (all or by ID)
            if (isset($_GET['id'])) {
                // Get single event
                $stmt = $pdo->prepare("
                    SELECT e.*, u.full_name as created_by_name, u.role as created_by_role
                    FROM calendar_events e
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE e.id = ? AND e.deleted_at IS NULL
                ");
                $stmt->execute([$_GET['id']]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($event) {
                    echo json_encode([
                        'success' => true,
                        'event' => $event
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Event not found']);
                }
            } else {
                // Get all events with optional date range
                $where = "e.deleted_at IS NULL";
                $params = [];

                if (isset($_GET['start_date'])) {
                    $where .= " AND e.event_date >= ?";
                    $params[] = $_GET['start_date'];
                }

                if (isset($_GET['end_date'])) {
                    $where .= " AND e.event_date <= ?";
                    $params[] = $_GET['end_date'];
                }

                if (isset($_GET['status'])) {
                    $where .= " AND e.status = ?";
                    $params[] = $_GET['status'];
                }

                $stmt = $pdo->prepare("
                    SELECT e.*, u.full_name as created_by_name, u.role as created_by_role
                    FROM calendar_events e
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE $where
                    ORDER BY e.event_date ASC, e.event_time ASC
                ");
                $stmt->execute($params);
                $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'events' => $events,
                    'count' => count($events)
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    error_log("Calendar Events API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
