<?php
/** Recovery review queue and approved manual import API. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/pdf_recovery_review.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Authentication required.']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Administrator access required.']); exit; }

$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list')));
try {
    if ($action === 'list') {
        $page = max(1, (int)($_GET['page'] ?? 1)); $perPage = (int)($_GET['per_page'] ?? 25); if (!in_array($perPage, [10,25,50,100], true)) $perPage = 25;
        $where = ["j.job_type = 'recovery'", "i.status IN ('unmatched','needs_review','corrupt_source')"]; $params = [];
        $status = strtolower(trim((string)($_GET['status'] ?? ''))); if (in_array($status, ['unmatched','needs_review','corrupt_source'], true)) { $where[] = 'i.status = :status'; $params[':status'] = $status; }
        $jobId = (int)($_GET['job_id'] ?? 0); if ($jobId > 0) { $where[] = 'i.job_id = :job_id'; $params[':job_id'] = $jobId; }
        $q = trim((string)($_GET['q'] ?? '')); if ($q !== '') { $where[] = '(CAST(i.id AS CHAR) LIKE :q OR CAST(i.job_id AS CHAR) LIKE :q OR i.source_path LIKE :q OR i.registry_no LIKE :q)'; $params[':q'] = '%' . $q . '%'; }
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $count = $pdo->prepare("SELECT COUNT(*) FROM pdf_protection_job_items i INNER JOIN pdf_protection_jobs j ON j.id=i.job_id{$whereSql}"); foreach($params as $key=>$value)$count->bindValue($key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR); $count->execute(); $total=(int)$count->fetchColumn(); $pages=max(1,(int)ceil($total/$perPage)); if($page>$pages)$page=$pages; $offset=($page-1)*$perPage;
        $stmt=$pdo->prepare("SELECT i.id AS item_id,i.job_id,i.source_path,i.source_filename,i.source_size,i.source_hash,i.expected_hash,i.cert_type,i.record_id,i.registry_no,i.match_method,i.status,i.attempts,i.last_error,i.created_at,i.updated_at,j.status AS job_status,j.source_root,j.destination_root FROM pdf_protection_job_items i INNER JOIN pdf_protection_jobs j ON j.id=i.job_id{$whereSql} ORDER BY i.updated_at DESC,i.id DESC LIMIT :limit OFFSET :offset"); foreach($params as $key=>$value)$stmt->bindValue($key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR); $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();
        echo json_encode(['success'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$pages,'from'=>$total?$offset+1:0,'to'=>min($offset+$perPage,$total),'has_previous'=>$page>1,'has_next'=>$page<$pages]]); exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'POST required.']); exit; }
    requireCSRFToken();
    if ($action !== 'import') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Unsupported recovery review action.']); exit; }
    $result = pdf_recovery_review_manual_import($pdo, (int)($_POST['job_id'] ?? 0), (int)($_POST['item_id'] ?? 0), strtolower(trim((string)($_POST['cert_type'] ?? ''))), (int)($_POST['record_id'] ?? 0), (int)($_SESSION['user_id'] ?? 0));
    echo json_encode(['success'=>true,'message'=>'PDF imported and mapped to the active record.','result'=>$result]);
} catch (Throwable $e) { error_log('pdf_recovery_review error: ' . $e->getMessage()); http_response_code(409); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
