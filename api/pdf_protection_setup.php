<?php
/** System-based PDF backup/recovery setup API. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/pdf_backup_locations.php';
require_once '../includes/pdf_protection_jobs.php';
require_once '../includes/pdf_protection_launcher.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Admin access required']); exit; }
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'locations')));
try {
    if ($action === 'locations' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $locations=[]; foreach(pdf_backup_allowed_roots() as $root){try{$locations[]=pdf_backup_storage_info($root,false);}catch(Throwable $e){}}
        echo json_encode(['success'=>true,'locations'=>$locations,'upload_root'=>realpath(UPLOAD_DIR)]); exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'POST required']); exit; }
    requireCSRFToken();
    $mode=strtolower(trim((string)($_POST['mode']??'')));$storageRoot=(string)($_POST['storage_root']??'');$folderName=(string)($_POST['folder_name']??'');$dryRun=isset($_POST['dry_run'])&&(string)$_POST['dry_run']==='1';
    if(!in_array($mode,['backup','recovery'],true))throw new InvalidArgumentException('Choose backup or recovery.');$root=pdf_backup_find_allowed_root($storageRoot);if($root===null)throw new InvalidArgumentException('Choose an approved storage root.');$folderName=pdf_backup_validate_folder_name($folderName);$storageInfo=pdf_backup_storage_info($root,false);
    if($action==='validate'){$candidate=rtrim($root,"\\/").DIRECTORY_SEPARATOR.$folderName;$exists=is_dir($candidate);if($mode==='backup'&&!$exists&&!is_writable($root))throw new RuntimeException('The selected storage root cannot create a new folder. Choose a writable external drive or configure its permissions.');if($mode==='recovery'&&!$exists)throw new RuntimeException('The recovery folder does not exist.');if(file_exists($candidate)&&!$exists)throw new RuntimeException('The selected name is already used by a file.');if($exists&&!is_readable($candidate))throw new RuntimeException('The selected folder is not readable.');if($exists&&$mode==='backup'&&!is_writable($candidate))throw new RuntimeException('The selected backup folder is not writable.');echo json_encode(['success'=>true,'mode'=>$mode,'path'=>$candidate,'exists'=>$exists,'free_bytes'=>$storageInfo['free_bytes'],'free_gb'=>$storageInfo['free_gb'],'message'=>$mode==='backup'&&!$exists?'Folder will be created when the job starts.':'Location is ready.']);exit;}
    if($action!=='create')throw new InvalidArgumentException('Unsupported setup action.');
    $folder=pdf_backup_resolve_folder($root,$folderName,$mode==='backup',$mode==='backup');$sourceRoot=$mode==='backup'?realpath(UPLOAD_DIR):$folder['path'];$destinationRoot=$mode==='backup'?$folder['path']:realpath(UPLOAD_DIR);if($sourceRoot===false||$destinationRoot===false)throw new RuntimeException('The source or destination is unavailable.');$sourcePrefix=rtrim(str_replace('\\','/',$sourceRoot),'/').'/';$destinationPrefix=rtrim(str_replace('\\','/',$destinationRoot),'/').'/';if(str_starts_with($sourcePrefix,$destinationPrefix)||str_starts_with($destinationPrefix,$sourcePrefix))throw new RuntimeException('Source and destination must be separate locations.');
    $jobId=create_pdf_protection_job($pdo,$mode,$sourceRoot,$destinationRoot,(int)($_SESSION['user_id']??0),$dryRun);
    try{launch_pdf_protection_worker($jobId,$mode,$sourceRoot,$destinationRoot,$dryRun);}catch(Throwable $e){update_pdf_protection_job($pdo,$jobId,'failed',['last_error'=>$e->getMessage()]);throw $e;}
    if(function_exists('log_activity'))log_activity($pdo,'PDF_PROTECTION_JOB_CREATED',sprintf('Job %d (%s%s) started for %s',$jobId,$mode,$dryRun?' dry-run':'',$destinationRoot),$_SESSION['user_id']??null);
    echo json_encode(['success'=>true,'job_id'=>$jobId,'mode'=>$mode,'source_root'=>$sourceRoot,'destination_root'=>$destinationRoot,'dry_run'=>$dryRun,'message'=>'Job created and started. Open the job monitor for live progress.']);
}catch(Throwable $e){http_response_code(409);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
