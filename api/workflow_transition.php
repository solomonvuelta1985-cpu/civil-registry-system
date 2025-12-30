<?php
/**
 * Workflow Transition API
 * Handles state transitions for certificate workflow management
 *
 * Valid transitions:
 * - draft -> pending_review (submit)
 * - pending_review -> verified (verify)
 * - pending_review -> rejected (reject)
 * - verified -> approved (approve)
 * - verified -> rejected (reject)
 * - rejected -> draft (reopen)
 * - * -> archived (archive)
 */

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display in output
ini_set('log_errors', 1);

try {
    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. POST required.');
    }

    // Get and validate input
    $certificate_type = isset($_POST['certificate_type']) ? sanitize_input($_POST['certificate_type']) : null;
    $certificate_id = isset($_POST['certificate_id']) ? (int)$_POST['certificate_id'] : null;
    $transition_type = isset($_POST['transition_type']) ? sanitize_input($_POST['transition_type']) : null;
    $notes = isset($_POST['notes']) ? sanitize_input($_POST['notes']) : null;
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1; // Default to admin for testing

    // Validate required fields
    if (!$certificate_type || !$certificate_id || !$transition_type) {
        throw new Exception('Missing required fields: certificate_type, certificate_id, transition_type');
    }

    // Validate certificate type
    $valid_types = ['birth', 'marriage', 'death'];
    if (!in_array($certificate_type, $valid_types)) {
        throw new Exception('Invalid certificate type. Must be: birth, marriage, or death');
    }

    // Validate transition type
    $valid_transitions = ['submit', 'verify', 'approve', 'reject', 'archive', 'reopen'];
    if (!in_array($transition_type, $valid_transitions)) {
        throw new Exception('Invalid transition type');
    }

    // Get current workflow state
    $current_state = getCurrentWorkflowState($pdo, $certificate_type, $certificate_id);

    // Determine target state based on transition
    $to_state = determineTargetState($transition_type, $current_state);

    // Validate transition is allowed
    validateTransition($current_state, $to_state, $transition_type);

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update or create workflow_states record
        if ($current_state === null) {
            // First time - insert
            $stmt = $pdo->prepare("
                INSERT INTO workflow_states
                (certificate_type, certificate_id, current_state,
                 " . getWorkflowColumnName($transition_type) . ",
                 " . getWorkflowColumnName($transition_type, 'at') . ")
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$certificate_type, $certificate_id, $to_state, $user_id]);
        } else {
            // Update existing
            $updateFields = [];
            $updateParams = [];

            $updateFields[] = "current_state = ?";
            $updateParams[] = $to_state;

            // Set appropriate user and timestamp based on transition
            $columnName = getWorkflowColumnName($transition_type);
            $columnTimestamp = getWorkflowColumnName($transition_type, 'at');

            if ($columnName) {
                $updateFields[] = "$columnName = ?";
                $updateParams[] = $user_id;

                $updateFields[] = "$columnTimestamp = NOW()";
            }

            // Add rejection reason if rejecting
            if ($transition_type === 'reject' && $notes) {
                $updateFields[] = "rejection_reason = ?";
                $updateParams[] = $notes;
            }

            $updateParams[] = $certificate_type;
            $updateParams[] = $certificate_id;

            $sql = "UPDATE workflow_states SET " . implode(', ', $updateFields) . "
                    WHERE certificate_type = ? AND certificate_id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateParams);
        }

        // Log transition in workflow_transitions table
        $stmt = $pdo->prepare("
            INSERT INTO workflow_transitions
            (certificate_type, certificate_id, from_state, to_state, transition_type, notes, performed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $certificate_type,
            $certificate_id,
            $current_state,
            $to_state,
            $transition_type,
            $notes,
            $user_id
        ]);

        // Log in activity_logs
        $action = strtoupper($transition_type);
        $details = "Workflow transition: $current_state -> $to_state";
        if ($notes) {
            $details .= " | Notes: $notes";
        }

        log_activity($pdo, $user_id, $action, $details, $certificate_type, $certificate_id);

        // Commit transaction
        $pdo->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Workflow transition completed successfully',
            'data' => [
                'certificate_type' => $certificate_type,
                'certificate_id' => $certificate_id,
                'from_state' => $current_state,
                'to_state' => $to_state,
                'transition_type' => $transition_type,
                'performed_by' => $user_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper functions

function getCurrentWorkflowState($pdo, $certificate_type, $certificate_id) {
    $stmt = $pdo->prepare("
        SELECT current_state
        FROM workflow_states
        WHERE certificate_type = ? AND certificate_id = ?
    ");
    $stmt->execute([$certificate_type, $certificate_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ? $result['current_state'] : null; // null means no workflow yet (new record)
}

function determineTargetState($transition_type, $current_state) {
    $transitions = [
        'submit' => 'pending_review',
        'verify' => 'verified',
        'approve' => 'approved',
        'reject' => 'rejected',
        'archive' => 'archived',
        'reopen' => 'draft'
    ];

    return $transitions[$transition_type] ?? null;
}

function validateTransition($from_state, $to_state, $transition_type) {
    // If no current state (new record), only allow submit or stays as draft
    if ($from_state === null) {
        if (!in_array($transition_type, ['submit', 'reopen'])) {
            throw new Exception('New records can only be submitted for review');
        }
        return;
    }

    // Define allowed transitions
    $allowedTransitions = [
        'draft' => ['pending_review'],
        'pending_review' => ['verified', 'rejected'],
        'verified' => ['approved', 'rejected'],
        'approved' => ['archived'],
        'rejected' => ['draft', 'pending_review'],
        'archived' => [] // Cannot transition from archived
    ];

    // Archive can be done from any non-archived state
    if ($transition_type === 'archive' && $from_state !== 'archived') {
        return;
    }

    if (!isset($allowedTransitions[$from_state])) {
        throw new Exception("Invalid current state: $from_state");
    }

    if (!in_array($to_state, $allowedTransitions[$from_state])) {
        throw new Exception("Invalid transition from '$from_state' to '$to_state'");
    }
}

function getWorkflowColumnName($transition_type, $suffix = '') {
    $columns = [
        'verify' => 'verified_by',
        'approve' => 'approved_by',
        'reject' => 'rejected_by'
    ];

    if ($suffix === 'at') {
        $columns = [
            'verify' => 'verified_at',
            'approve' => 'approved_at',
            'reject' => 'rejected_at'
        ];
    }

    return $columns[$transition_type] ?? null;
}

// Enhanced log_activity function that accepts certificate info
function log_activity($pdo, $user_id, $action, $details, $certificate_type = null, $certificate_id = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs
            (user_id, action, details, certificate_type, certificate_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt->execute([
            $user_id,
            $action,
            $details,
            $certificate_type,
            $certificate_id,
            $ip_address,
            $user_agent
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
