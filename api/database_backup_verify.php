<?php
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/database_backup_verify.php';
header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Authentication required.']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Administrator access required.']); exit; }
try { $result=verify_database_backup_job($pdo,(int)($_GET['job_id']??0)); echo json_encode(['success'=>true,'verification'=>$result]); }
catch(Throwable $e){http_response_code(409);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
