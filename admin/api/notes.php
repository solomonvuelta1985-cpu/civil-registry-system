<?php
/**
 * System Notes API
 * Handles CRUD operations for system notes
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
            // Create new note
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (empty($data['note_title']) || empty($data['note_type']) || empty($data['note_content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }

            $is_pinned = isset($data['is_pinned']) && $data['is_pinned'] == '1' ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO system_notes
                (title, note_type, content, is_pinned, status, created_by, created_at)
                VALUES (?, ?, ?, ?, 'active', ?, NOW())
            ");

            $result = $stmt->execute([
                sanitize_input($data['note_title']),
                sanitize_input($data['note_type']),
                sanitize_input($data['note_content']),
                $is_pinned,
                $user_id
            ]);

            if ($result) {
                $note_id = $pdo->lastInsertId();

                // Log activity
                log_activity($pdo, $user_id, 'CREATE_NOTE', 'system_notes', $note_id,
                    "Created note: " . $data['note_title']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Note created successfully',
                    'note_id' => $note_id
                ]);
            } else {
                throw new Exception('Failed to create note');
            }
            break;

        case 'PUT':
            // Update existing note
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['note_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Note ID is required']);
                exit;
            }

            $is_pinned = isset($data['is_pinned']) && $data['is_pinned'] == '1' ? 1 : 0;

            $stmt = $pdo->prepare("
                UPDATE system_notes
                SET title = ?,
                    note_type = ?,
                    content = ?,
                    is_pinned = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ? AND deleted_at IS NULL
            ");

            $result = $stmt->execute([
                sanitize_input($data['note_title']),
                sanitize_input($data['note_type']),
                sanitize_input($data['note_content']),
                $is_pinned,
                !empty($data['status']) ? sanitize_input($data['status']) : 'active',
                $data['note_id']
            ]);

            if ($result) {
                // Log activity
                log_activity($pdo, $user_id, 'UPDATE_NOTE', 'system_notes', $data['note_id'],
                    "Updated note: " . $data['note_title']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Note updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update note');
            }
            break;

        case 'DELETE':
            // Soft delete note
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['note_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Note ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE system_notes
                SET deleted_at = NOW(), status = 'archived'
                WHERE id = ? AND deleted_at IS NULL
            ");

            $result = $stmt->execute([$data['note_id']]);

            if ($result) {
                // Log activity
                log_activity($pdo, $user_id, 'DELETE_NOTE', 'system_notes', $data['note_id'],
                    "Deleted note ID: " . $data['note_id']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Note deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete note');
            }
            break;

        case 'GET':
            // Get notes (all or by ID)
            if (isset($_GET['id'])) {
                // Get single note
                $stmt = $pdo->prepare("
                    SELECT n.*, u.full_name as created_by_name, u.role as created_by_role
                    FROM system_notes n
                    LEFT JOIN users u ON n.created_by = u.id
                    WHERE n.id = ? AND n.deleted_at IS NULL
                ");
                $stmt->execute([$_GET['id']]);
                $note = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($note) {
                    echo json_encode([
                        'success' => true,
                        'note' => $note
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Note not found']);
                }
            } else {
                // Get all notes with optional filters
                $where = "n.deleted_at IS NULL";
                $params = [];

                if (isset($_GET['status'])) {
                    $where .= " AND n.status = ?";
                    $params[] = $_GET['status'];
                } else {
                    $where .= " AND n.status = 'active'";
                }

                if (isset($_GET['is_pinned'])) {
                    $where .= " AND n.is_pinned = ?";
                    $params[] = $_GET['is_pinned'];
                }

                if (isset($_GET['note_type'])) {
                    $where .= " AND n.note_type = ?";
                    $params[] = $_GET['note_type'];
                }

                $stmt = $pdo->prepare("
                    SELECT n.*, u.full_name as created_by_name, u.role as created_by_role
                    FROM system_notes n
                    LEFT JOIN users u ON n.created_by = u.id
                    WHERE $where
                    ORDER BY n.is_pinned DESC, n.created_at DESC
                ");
                $stmt->execute($params);
                $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'notes' => $notes,
                    'count' => count($notes)
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    error_log("Notes API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
