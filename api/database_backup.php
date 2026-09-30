<?php
/** Database backup job setup, status, and log API. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/pdf_backup_locations.php';
require_once '../includes/database_backup.php';
require_once '../includes/database_backup_launcher.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Admin access required']); exit; }
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'locations')));
try {
    if ($action === 'locations' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $locations=[]; foreach(pdf_backup_allowed_roots() as $root){try{$locations[]=pdf_backup_storage_info($root,false);}catch(Throwable $e){}}
        echo json_encode(['success'=>true,'locations'=>$locations]); exit;
    }
    if ($action === 'list' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $page=max(1,(int)($_GET['page']??1));$perPage=(int)($_GET['per_page']??25);if(!in_array($perPage,[10,25,50,100],true))$perPage=25;$total=(int)$pdo->query('SELECT COUNT(*) FROM database_backup_jobs')->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));if($page>$pages)$page=$pages;$offset=($page-1)*$perPage;$stmt=$pdo->prepare('SELECT id,status,destination_root,backup_folder,dump_path,manifest_path,database_name,database_host,file_size,file_hash,manifest_hash,last_error,created_by,started_at,last_activity_at,completed_at,created_at,updated_at FROM database_backup_jobs ORDER BY id DESC LIMIT :limit OFFSET :offset');$stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();echo json_encode(['success'=>true,'jobs'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$pages,'from'=>$total?$offset+1:0,'to'=>min($offset+$perPage,$total),'has_previous'=>$page>1,'has_next'=>$page<$pages]]);exit;
    }
    $jobId=(int)($_GET['job_id']??$_POST['job_id']??0);
    if ($action === 'job' && $jobId>0) { $job=get_database_backup_job($pdo,$jobId);if(!$job){http_response_code(404);echo json_encode(['success'=>false,'message'=>'Backup job not found.']);exit;}echo json_encode(['success'=>true,'job'=>$job,'logs'=>database_backup_job_logs($pdo,$jobId)]);exit; }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'POST required.']); exit; }
    requireCSRFToken();
    if ($action !== 'create') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Unsupported database backup action.']); exit; }
    $root=pdf_backup_find_allowed_root((string)($_POST['storage_root']??''));if($root===null)throw new InvalidArgumentException('Choose an approved storage root.');$folderName=pdf_backup_validate_folder_name((string)($_POST['folder_name']??''));$folder=pdf_backup_resolve_folder($root,$folderName,true,true);$jobId=create_database_backup_job($pdo,$root,$folder['path'],(int)($_SESSION['user_id']??0));
    try{launch_database_backup_worker($jobId);}catch(Throwable $e){update_database_backup_job($pdo,$jobId,'failed',['last_error'=>$e->getMessage()]);database_backup_log($pdo,$jobId,'error',$e->getMessage());throw $e;}
    if(function_exists('log_activity'))log_activity($pdo,'DATABASE_BACKUP_JOB_CREATED',sprintf('Database backup job %d started for %s',$jobId,$folder['path']),$_SESSION['user_id']??null);
    echo json_encode(['success'=>true,'job_id'=>$jobId,'backup_folder'=>$folder['path'],'message'=>'Database backup started. Monitor its progress below.']);
}catch(Throwable $e){http_response_code(409);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
