<?php
require_once __DIR__ . '/settings.php';
/** Two-person approval gate for verified production PDF recovery. */
require_once __DIR__ . '/database_backup.php';
require_once __DIR__ . '/database_backup_verify.php';
require_once __DIR__ . '/pdf_protection_jobs.php';
require_once __DIR__ . '/pdf_protection_launcher.php';

function production_restore_assert_recovery_ready(array $recovery): void
{
    if((string)($recovery['job_type']??'')!=='recovery')throw new RuntimeException('Only PDF recovery jobs can enter production restore.');
    if((string)($recovery['status']??'')!=='preview_ready')throw new RuntimeException('The recovery job must be preview_ready before production restore preparation.');
    if((int)($recovery['total_items']??0)<=0||(int)($recovery['scanned_items']??0)<(int)($recovery['total_items']??0))throw new RuntimeException('The recovery preview is incomplete. Scan all source files before approval.');
    if((int)($recovery['review_items']??0)>0||(int)($recovery['failed_items']??0)>0)throw new RuntimeException('The recovery preview still has unresolved review or failed items. Resolve them before production restore.');
}

function get_production_restore_job(PDO $pdo,int $jobId): ?array
{
    $stmt=$pdo->prepare('SELECT p.*,r.status AS recovery_status,r.total_items AS recovery_total_items,r.scanned_items AS recovery_scanned_items,r.copied_items AS recovery_copied_items,r.skipped_items AS recovery_skipped_items,r.failed_items AS recovery_failed_items,r.review_items AS recovery_review_items,r.source_root AS recovery_source_root,r.destination_root AS recovery_destination_root,b.status AS pre_backup_status,b.backup_folder AS pre_backup_folder,b.dump_path AS pre_backup_dump_path FROM production_restore_jobs p JOIN pdf_protection_jobs r ON r.id=p.recovery_job_id LEFT JOIN database_backup_jobs b ON b.id=p.pre_restore_backup_job_id WHERE p.id=:id LIMIT 1');$stmt->execute([':id'=>$jobId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
}

function production_restore_approvals(PDO $pdo,int $jobId): array
{
    $stmt=$pdo->prepare('SELECT a.stage,a.user_id,a.created_at,u.username,u.full_name FROM production_restore_approvals a LEFT JOIN users u ON u.id=a.user_id WHERE a.job_id=:job_id ORDER BY a.stage');$stmt->execute([':job_id'=>$jobId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function production_restore_log(PDO $pdo,int $jobId,string $level,string $message): void
{
    $stmt=$pdo->prepare('INSERT INTO production_restore_job_logs (job_id,level,message) VALUES (:job_id,:level,:message)');$stmt->execute([':job_id'=>$jobId,':level'=>$level,':message'=>mb_substr($message,0,1000)]);
}

function production_restore_logs(PDO $pdo,int $jobId,int $limit=100): array
{
    $stmt=$pdo->prepare('SELECT id,level,message,created_at FROM production_restore_job_logs WHERE job_id=:job_id ORDER BY id DESC LIMIT :limit');$stmt->bindValue(':job_id',$jobId,PDO::PARAM_INT);$stmt->bindValue(':limit',max(1,min(500,$limit)),PDO::PARAM_INT);$stmt->execute();return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function update_production_restore_job(PDO $pdo,int $jobId,string $status,array $fields=[]): void
{
    $allowed=['pre_restore_backup_job_id','first_approved_by','second_approved_by','first_approved_at','second_approved_at','last_error','started_at','last_activity_at','completed_at'];$sets=['status=:status','last_activity_at=NOW()'];$params=[':status'=>$status,':id'=>$jobId];foreach($allowed as $field)if(array_key_exists($field,$fields)){$sets[]="{$field}=:{$field}";$params[":{$field}"]=$fields[$field];}if($status==='running')$sets[]='started_at=COALESCE(started_at,NOW())';if(in_array($status,['completed','completed_with_errors','failed','cancelled'],true))$sets[]='completed_at=COALESCE(completed_at,NOW())';$stmt=$pdo->prepare('UPDATE production_restore_jobs SET '.implode(',',$sets).' WHERE id=:id');$stmt->execute($params);
}

function create_production_restore_job(PDO $pdo,int $recoveryJobId,string $backupRoot,string $backupFolder,?int $userId=null): int
{
    $recovery=get_pdf_protection_job($pdo,$recoveryJobId);if(!$recovery)throw new RuntimeException('Recovery job not found.');production_restore_assert_recovery_ready($recovery);$existing=$pdo->prepare("SELECT id,status FROM production_restore_jobs WHERE recovery_job_id=:recovery_job_id AND status NOT IN ('cancelled','failed','completed','completed_with_errors') LIMIT 1");$existing->execute([':recovery_job_id'=>$recoveryJobId]);if($existing->fetch())throw new RuntimeException('An active production restore gate already exists for this recovery job.');$pdo->beginTransaction();try{$stmt=$pdo->prepare('INSERT INTO production_restore_jobs (recovery_job_id,status,source_root,destination_root,created_by,last_activity_at) VALUES (:recovery_job_id,\'backup_running\',:source_root,:destination_root,:created_by,NOW())');$stmt->execute([':recovery_job_id'=>$recoveryJobId,':source_root'=>$recovery['source_root'],':destination_root'=>$recovery['destination_root'],':created_by'=>$userId]);$jobId=(int)$pdo->lastInsertId();$backupId=create_database_backup_job($pdo,$backupRoot,$backupFolder,$userId);$update=$pdo->prepare('UPDATE production_restore_jobs SET pre_restore_backup_job_id=:backup_id,last_activity_at=NOW() WHERE id=:id');$update->execute([':backup_id'=>$backupId,':id'=>$jobId]);production_restore_log($pdo,$jobId,'info','Production restore gate created; pre-restore database backup started.');$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}return $jobId;
}

function production_restore_sync_job(PDO $pdo,int $jobId): array
{
    $job=get_production_restore_job($pdo,$jobId);if(!$job)throw new RuntimeException('Production restore job not found.');$status=(string)$job['status'];if($status==='backup_running'){$backupId=(int)($job['pre_restore_backup_job_id']??0);$backup=$backupId>0?get_database_backup_job($pdo,$backupId):null;if($backup&&(string)$backup['status']==='completed'){$verification=verify_database_backup_job($pdo,$backupId);if(empty($verification['valid'])){update_production_restore_job($pdo,$jobId,'failed',['last_error'=>'The pre-restore database backup failed hash verification.']);production_restore_log($pdo,$jobId,'error','Pre-restore database backup verification failed.');}else{update_production_restore_job($pdo,$jobId,'awaiting_approval',['last_error'=>null]);production_restore_log($pdo,$jobId,'info','Pre-restore database backup verified; two administrator approvals are now required.');}}elseif($backup&&in_array((string)$backup['status'],['failed','cancelled'],true)){update_production_restore_job($pdo,$jobId,'failed',['last_error'=>'The pre-restore database backup did not complete: '.$backup['status']]);production_restore_log($pdo,$jobId,'error','Pre-restore database backup failed or was cancelled.');}}elseif(in_array($status,['approved','running'],true)){$recovery=get_pdf_protection_job($pdo,(int)$job['recovery_job_id']);if($recovery&&in_array((string)$recovery['status'],['completed','completed_with_errors','failed','cancelled'],true)){$next=(string)$recovery['status'];update_production_restore_job($pdo,$jobId,$next,['last_error'=>$recovery['last_error']]);production_restore_log($pdo,$jobId,$next==='completed'?'info':($next==='completed_with_errors'?'warning':'error'),'Linked PDF recovery worker finished with status '.$next.'.');}}return get_production_restore_job($pdo,$jobId)?:$job;
}

function production_restore_maintenance_is_enabled(): bool
{
    return function_exists('maintenance_is_active')?maintenance_is_active():(function_exists('get_setting')&&(bool)get_setting('maintenance_mode',false));
}

function production_restore_approve(PDO $pdo,int $jobId,int $stage,int $userId): array
{
    if(!in_array($stage,[1,2],true)||$userId<=0)throw new InvalidArgumentException('A valid approval stage and administrator are required.');$job=production_restore_sync_job($pdo,$jobId);if((string)$job['status']!=='awaiting_approval')throw new RuntimeException('This restore gate is not awaiting approval.');if(!production_restore_maintenance_is_enabled())throw new RuntimeException('Enable Maintenance Mode before approving production restore.');$backupId=(int)$job['pre_restore_backup_job_id'];$verification=verify_database_backup_job($pdo,$backupId);if(empty($verification['valid']))throw new RuntimeException('The pre-restore database backup is not verified.');$recovery=get_pdf_protection_job($pdo,(int)$job['recovery_job_id']);if(!$recovery)throw new RuntimeException('The linked recovery job no longer exists.');production_restore_assert_recovery_ready($recovery);$approvals=production_restore_approvals($pdo,$jobId);$byStage=[];foreach($approvals as $approval)$byStage[(int)$approval['stage']]=$approval;if(isset($byStage[$stage]))throw new RuntimeException('This approval stage is already recorded.');if($stage===1){if((int)$job['created_by']===$userId)throw new RuntimeException('The creator cannot provide the first independent approval.');}else{if(!isset($byStage[1]))throw new RuntimeException('The first approval is required before the second approval.');if((int)$byStage[1]['user_id']===$userId)throw new RuntimeException('The second approval must come from a different administrator.');}$pdo->beginTransaction();try{$insert=$pdo->prepare('INSERT INTO production_restore_approvals (job_id,stage,user_id) VALUES (:job_id,:stage,:user_id)');$insert->execute([':job_id'=>$jobId,':stage'=>$stage,':user_id'=>$userId]);if($stage===1){update_production_restore_job($pdo,$jobId,'awaiting_approval',['first_approved_by'=>$userId,'first_approved_at'=>date('Y-m-d H:i:s')]);production_restore_log($pdo,$jobId,'info','First administrator approval recorded.');$message='First approval recorded. A different administrator must approve the restore.';}else{update_production_restore_job($pdo,$jobId,'approved',['second_approved_by'=>$userId,'second_approved_at'=>date('Y-m-d H:i:s'),'last_error'=>null]);$stmt=$pdo->prepare("UPDATE pdf_protection_jobs SET status='approved',approved_by=:approved_by,last_error=NULL,last_activity_at=NOW(),updated_at=NOW() WHERE id=:id AND status='preview_ready'");$stmt->execute([':approved_by'=>$userId,':id'=>(int)$job['recovery_job_id']]);if($stmt->rowCount()!==1)throw new RuntimeException('The linked recovery job changed before launch.');production_restore_log($pdo,$jobId,'info','Second approval recorded; verified PDF recovery will now start.');$pdo->commit();try{launch_pdf_protection_worker((int)$job['recovery_job_id'],'recovery',(string)$job['source_root'],(string)$job['destination_root']);}catch(Throwable $e){update_production_restore_job($pdo,$jobId,'failed',['last_error'=>$e->getMessage()]);production_restore_log($pdo,$jobId,'error','Recovery approval was recorded but the worker could not start: '.$e->getMessage());throw $e;}update_production_restore_job($pdo,$jobId,'running');return ['job'=>get_production_restore_job($pdo,$jobId)?:$job,'message'=>'Second approval recorded. Verified PDF recovery worker started.'];}$pdo->commit();return ['job'=>get_production_restore_job($pdo,$jobId)?:$job,'message'=>$message];}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function production_restore_cancel(PDO $pdo,int $jobId,int $userId): array
{
    $job=production_restore_sync_job($pdo,$jobId);if(in_array((string)$job['status'],['completed','completed_with_errors','failed','cancelled'],true))throw new RuntimeException('This restore gate is already terminal.');if(in_array((string)$job['status'],['approved','running'],true)){$stmt=$pdo->prepare("UPDATE pdf_protection_jobs SET status='cancelled',last_activity_at=NOW(),updated_at=NOW() WHERE id=:id AND status IN ('approved','running')");$stmt->execute([':id'=>(int)$job['recovery_job_id']]);}update_production_restore_job($pdo,$jobId,'cancelled',['last_error'=>'Cancelled by administrator #'.$userId]);production_restore_log($pdo,$jobId,'warning','Production restore gate cancelled by administrator.');return get_production_restore_job($pdo,$jobId)?:$job;
}
