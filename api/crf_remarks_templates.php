<?php
/** User-owned reusable remarks templates shared by CRF 1A, 2A, and 3A. */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/crf_1a.php';

requireAuth();
header('Cache-Control: private, no-store, max-age=0');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$userId = (int)getUserId();

function crf_remarks_template_payload(array $template): array
{
    $template['remarks_html'] = crf_1a_sanitize_remarks_html((string)($template['remarks_html'] ?? ''));
    return $template;
}

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare('SELECT id, template_name AS name, remarks_html, created_at, updated_at FROM crf_remarks_templates WHERE user_id = :user_id ORDER BY updated_at DESC, id DESC');
        $stmt->execute([':user_id' => $userId]);
        $templates = array_map('crf_remarks_template_payload', $stmt->fetchAll(PDO::FETCH_ASSOC));
        json_response(true, 'Remarks templates loaded.', ['templates' => $templates]);
    } catch (Throwable $error) {
        error_log('Unable to load CRF remarks templates: ' . $error->getMessage());
        json_response(false, 'Unable to load remarks templates.', null, 500);
    }
}

if ($method !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

requireCSRFToken();
$action = strtolower(trim((string)($_POST['action'] ?? '')));
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

try {
    if ($action === 'delete') {
        if ($id === false || $id === null) json_response(false, 'A valid template id is required.', null, 422);
        $stmt = $pdo->prepare('DELETE FROM crf_remarks_templates WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => (int)$id, ':user_id' => $userId]);
        if ($stmt->rowCount() !== 1) json_response(false, 'Remarks template not found.', null, 404);
        json_response(true, 'Template deleted.');
    }

    if (!in_array($action, ['create', 'update'], true)) {
        json_response(false, 'Invalid template action.', null, 400);
    }

    if ($action === 'update' && ($id === false || $id === null)) {
        json_response(false, 'A valid template id is required.', null, 422);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    $name = function_exists('mb_substr') ? mb_substr($name, 0, 120, 'UTF-8') : substr($name, 0, 120);
    if ($name === '') json_response(false, 'Enter a name for this template.', null, 422);

    $remarksHtml = crf_1a_sanitize_remarks_html($_POST['remarks_html'] ?? '');
    $plainText = html_entity_decode(strip_tags($remarksHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $contentCheck = preg_replace('/[\s\x{00a0}\x{200b}]+/u', '', $plainText);
    if ($remarksHtml === '' || ($contentCheck !== null && $contentCheck === '')) {
        json_response(false, 'Enter remarks before saving a template.', null, 422);
    }

    if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO crf_remarks_templates (user_id, template_name, remarks_html) VALUES (:user_id, :template_name, :remarks_html)');
        $stmt->execute([':user_id' => $userId, ':template_name' => $name, ':remarks_html' => $remarksHtml]);
        $templateId = (int)$pdo->lastInsertId();
        $message = 'Template saved.';
    } else {
        $exists = $pdo->prepare('SELECT id FROM crf_remarks_templates WHERE id = :id AND user_id = :user_id LIMIT 1');
        $exists->execute([':id' => (int)$id, ':user_id' => $userId]);
        if (!$exists->fetchColumn()) json_response(false, 'Remarks template not found.', null, 404);

        $stmt = $pdo->prepare('UPDATE crf_remarks_templates SET template_name = :template_name, remarks_html = :remarks_html WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':template_name' => $name, ':remarks_html' => $remarksHtml, ':id' => (int)$id, ':user_id' => $userId]);
        $templateId = (int)$id;
        $message = 'Template updated.';
    }

    $saved = $pdo->prepare('SELECT id, template_name AS name, remarks_html, created_at, updated_at FROM crf_remarks_templates WHERE id = :id AND user_id = :user_id LIMIT 1');
    $saved->execute([':id' => $templateId, ':user_id' => $userId]);
    $template = $saved->fetch(PDO::FETCH_ASSOC);
    json_response(true, $message, ['template' => is_array($template) ? crf_remarks_template_payload($template) : null]);
} catch (PDOException $error) {
    if (($error->errorInfo[0] ?? '') === '23000') {
        json_response(false, 'You already have a template with that name. Choose another name.', null, 409);
    }
    error_log('Unable to save CRF remarks template: ' . $error->getMessage());
    json_response(false, 'Unable to save remarks template.', null, 500);
} catch (Throwable $error) {
    error_log('Unable to save CRF remarks template: ' . $error->getMessage());
    json_response(false, 'Unable to save remarks template.', null, 500);
}
