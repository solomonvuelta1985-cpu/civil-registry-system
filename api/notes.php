<?php
/**
 * System Notes API
 * Handles CRUD operations for system notes
 */

require_once '../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = (int)$_SESSION['user_id'];

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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function handleGet($pdo) {
    // Single note by ID
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT n.*, u.full_name as created_by_name
            FROM system_notes n
            LEFT JOIN users u ON n.created_by = u.id
            WHERE n.id = ? AND n.deleted_at IS NULL
        ");
        $stmt->execute([(int)$_GET['id']]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($note) {
            echo json_encode(['success' => true, 'note' => $note]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note not found']);
        }
        return;
    }

    // All active notes (for "View All" modal)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $stmt = $pdo->prepare("
        SELECT n.*, u.full_name as created_by_name
        FROM system_notes n
        LEFT JOIN users u ON n.created_by = u.id
        WHERE n.deleted_at IS NULL AND n.status = 'active'
        ORDER BY n.is_pinned DESC, n.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'notes' => $notes]);
}

function handlePost($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['note_title']) || empty($data['note_type']) || empty($data['note_content'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title, type, and content are required']);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO system_notes (title, note_type, content, is_pinned, created_by, created_at, status)
        VALUES (?, ?, ?, ?, ?, NOW(), 'active')
    ");
    $stmt->execute([
        $data['note_title'],
        $data['note_type'],
        $data['note_content'],
        !empty($data['is_pinned']) ? 1 : 0,
        $user_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Note created successfully',
        'note_id' => $pdo->lastInsertId()
    ]);
}

function handlePut($pdo, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);

    // Toggle pin action
    if (isset($_GET['action']) && $_GET['action'] === 'toggle_pin') {
        if (empty($data['note_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Note ID is required']);
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE system_notes
            SET is_pinned = NOT is_pinned, updated_by = ?, updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$user_id, (int)$data['note_id']]);

        echo json_encode(['success' => true, 'message' => 'Pin status toggled']);
        return;
    }

    // Full update
    if (empty($data['note_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Note ID is required']);
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE system_notes
        SET title = ?, note_type = ?, content = ?, is_pinned = ?,
            updated_by = ?, updated_at = NOW()
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([
        $data['note_title'],
        $data['note_type'],
        $data['note_content'],
        !empty($data['is_pinned']) ? 1 : 0,
        $user_id,
        (int)$data['note_id']
    ]);

    echo json_encode(['success' => true, 'message' => 'Note updated successfully']);
}

function handleDelete($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['note_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Note ID is required']);
        return;
    }

    // Soft delete
    $stmt = $pdo->prepare("
        UPDATE system_notes SET deleted_at = NOW() WHERE id = ?
    ");
    $stmt->execute([(int)$data['note_id']]);

    echo json_encode(['success' => true, 'message' => 'Note deleted successfully']);
}
