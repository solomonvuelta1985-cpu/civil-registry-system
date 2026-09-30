<?php
/** Web-SAPI runtime compatibility report for the deployed server. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/runtime_requirements.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Admin access required']); exit; }
echo json_encode(['success' => true, 'report' => iscan_runtime_requirements()], JSON_PRETTY_PRINT);
