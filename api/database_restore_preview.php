<?php
/** Isolated database restore-preview job API. No production restore is exposed here. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/database_restore_preview.php';
require_once '../includes/database_restore_preview_launcher.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Admin access required']); exit; }
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list')));
try {
    if ($action === 'list' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 25);
        if (!in_array($perPage, [10,25,50,100], true)) $perPage = 25;
        $status = trim((string)($_GET['status'] ?? ''));
        $allowedStatuses = ['queued','running','completed','completed_with_errors','failed','cancelled','cleaned'];
        $where = '';
        $params = [];
        if ($status !== '' && in_array($status, $allowedStatuses, true)) { $where = ' WHERE r.status=:status'; $params[':status'] = $status; }
        $count = $pdo->prepare('SELECT COUNT(*) FROM database_restore_preview_jobs r' . $where); $count->execute($params); $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage)); if ($page > $pages) $page = $pages; $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare('SELECT r.id,r.backup_job_id,r.status,r.staging_database_name,r.dump_size,r.table_statements,r.insert_statements,r.staging_table_count,r.pdf_reference_count,r.pdf_present_count,r.pdf_missing_count,r.pdf_invalid_count,r.report_path,r.last_error,r.created_by,r.started_at,r.last_activity_at,r.completed_at,r.created_at,r.updated_at,b.database_name,b.backup_folder FROM database_restore_preview_jobs r JOIN database_backup_jobs b ON b.id=r.backup_job_id' . $where . ' ORDER BY r.id DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); $stmt->execute();
        echo json_encode(['success'=>true,'jobs'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$pages,'from'=>$total?$offset+1:0,'to'=>min($offset+$perPage,$total),'has_previous'=>$page>1,'has_next'=>$page<$pages]]); exit;
    }
    $jobId = (int)($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
    if ($action === 'job' && $jobId > 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $job = get_database_restore_preview_job($pdo, $jobId); if (!$job) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Restore preview job not found.']); exit; }
        echo json_encode(['success'=>true,'job'=>$job,'logs'=>database_restore_preview_job_logs($pdo,$jobId)]); exit;
    }
    if ($action === 'report' && $jobId > 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $job = get_database_restore_preview_job($pdo, $jobId); if (!$job) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Restore preview job not found.']); exit; }
        echo json_encode(['success'=>true,'report'=>get_database_restore_preview_report($job)]); exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'POST required.']); exit; }
    requireCSRFToken();
    if ($action === 'create') {
        $backupJobId = (int)($_POST['backup_job_id'] ?? 0); if ($backupJobId <= 0) throw new InvalidArgumentException('Choose a completed database backup.');
        $previewJobId = create_database_restore_preview_job($pdo, $backupJobId, (int)($_SESSION['user_id'] ?? 0));
        try { launch_database_restore_preview_worker($previewJobId); } catch (Throwable $e) { update_database_restore_preview_job($pdo,$previewJobId,'failed',['last_error'=>$e->getMessage()]); database_restore_preview_log($pdo,$previewJobId,'error',$e->getMessage()); throw $e; }
        if (function_exists('log_activity')) log_activity($pdo,'DATABASE_RESTORE_PREVIEW_CREATED',sprintf('Restore preview job %d created from database backup job %d',$previewJobId,$backupJobId),$_SESSION['user_id']??null);
        echo json_encode(['success'=>true,'job_id'=>$previewJobId,'message'=>'Restore preview started. No production database was changed.']); exit;
    }
    if ($action === 'cleanup') {
        if ($jobId <= 0) throw new InvalidArgumentException('Invalid restore preview job ID.');
        $job = cleanup_database_restore_preview($pdo, $jobId);
        if (function_exists('log_activity')) log_activity($pdo,'DATABASE_RESTORE_PREVIEW_CLEANED',sprintf('Staging database for restore preview job %d was removed',$jobId),$_SESSION['user_id']??null);
        echo json_encode(['success'=>true,'job'=>$job,'message'=>'The isolated staging database was removed.']); exit;
    }
    http_response_code(400); echo json_encode(['success'=>false,'message'=>'Unsupported restore preview action.']);
} catch (Throwable $e) { http_response_code(409); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
