<?php
/** Persistent job helpers for PDF recovery and incremental backup. */

function create_pdf_protection_job(PDO $pdo,string $jobType,string $sourceRoot,?string $destinationRoot=null,?int $userId=null,bool $dryRun=false): int
{
    if(!in_array($jobType,['recovery','backup'],true))throw new InvalidArgumentException('Invalid PDF protection job type.');
    $stmt=$pdo->prepare('INSERT INTO pdf_protection_jobs (job_type,dry_run,source_root,destination_root,created_by,last_activity_at) VALUES (:job_type,:dry_run,:source_root,:destination_root,:created_by,NOW())');
    $stmt->execute([':job_type'=>$jobType,':dry_run'=>$dryRun?1:0,':source_root'=>$sourceRoot,':destination_root'=>$destinationRoot,':created_by'=>$userId]);
    return (int)$pdo->lastInsertId();
}

function add_pdf_protection_job_item(PDO $pdo,int $jobId,array $item): int
{
    $stmt=$pdo->prepare('INSERT INTO pdf_protection_job_items (job_id,source_path,destination_path,source_filename,source_size,source_mtime,source_hash,expected_hash,cert_type,record_id,registry_no,match_method,status) VALUES (:job_id,:source_path,:destination_path,:source_filename,:source_size,:source_mtime,:source_hash,:expected_hash,:cert_type,:record_id,:registry_no,:match_method,:status)');
    $stmt->execute([':job_id'=>$jobId,':source_path'=>(string)($item['source_path']??''),':destination_path'=>$item['destination_path']??null,':source_filename'=>$item['source_filename']??null,':source_size'=>isset($item['source_size'])?(int)$item['source_size']:null,':source_mtime'=>$item['source_mtime']??null,':source_hash'=>$item['source_hash']??null,':expected_hash'=>$item['expected_hash']??null,':cert_type'=>$item['cert_type']??null,':record_id'=>isset($item['record_id'])?(int)$item['record_id']:null,':registry_no'=>$item['registry_no']??null,':match_method'=>$item['match_method']??'none',':status'=>$item['status']??'queued']);
    return (int)$pdo->lastInsertId();
}

function update_pdf_protection_job(PDO $pdo,int $jobId,string $status,array $fields=[]): void
{
    $allowed=['total_items','scanned_items','copied_items','skipped_items','failed_items','review_items','bytes_scanned','bytes_copied','last_error','manifest_hash','approved_by'];$sets=['status=:status','last_activity_at=NOW()'];$params=[':status'=>$status,':id'=>$jobId];
    foreach($allowed as $field)if(array_key_exists($field,$fields)){$sets[]="{$field}=:{$field}";$params[":{$field}"]=$fields[$field];}
    if(in_array($status,['running','scanning'],true))$sets[]='started_at=COALESCE(started_at,NOW())';
    if(in_array($status,['completed','completed_with_errors','failed','cancelled'],true))$sets[]='completed_at=COALESCE(completed_at,NOW())';
    $stmt=$pdo->prepare('UPDATE pdf_protection_jobs SET '.implode(',',$sets).' WHERE id=:id');$stmt->execute($params);
}

function update_pdf_protection_job_item(PDO $pdo,int $itemId,string $status,array $fields=[]): void
{
    $allowed=['destination_path','destination_hash','source_hash','expected_hash','cert_type','record_id','registry_no','match_method','attempts','last_error','reviewed_by','reviewed_at','imported_at'];$sets=['status=:status','updated_at=NOW()'];$params=[':status'=>$status,':id'=>$itemId];
    foreach($allowed as $field)if(array_key_exists($field,$fields)){$sets[]="{$field}=:{$field}";$params[":{$field}"]=$fields[$field];}
    $stmt=$pdo->prepare('UPDATE pdf_protection_job_items SET '.implode(',',$sets).' WHERE id=:id');$stmt->execute($params);
}

function refresh_pdf_protection_job_counters(PDO $pdo,int $jobId): void
{
    $stmt=$pdo->prepare("SELECT COUNT(*) AS total_items,SUM(status<>'queued') AS scanned_items,SUM(status IN ('imported','matched')) AS copied_items,SUM(status IN ('already_present','skipped')) AS skipped_items,SUM(status IN ('failed','invalid_pdf','corrupt_source')) AS failed_items,SUM(status IN ('needs_review','unmatched','duplicate_source')) AS review_items,COALESCE(SUM(source_size),0) AS bytes_scanned FROM pdf_protection_job_items WHERE job_id=:job_id");$stmt->execute([':job_id'=>$jobId]);$counters=$stmt->fetch(PDO::FETCH_ASSOC)?:[];update_pdf_protection_job($pdo,$jobId,'running',$counters);
}

function get_pdf_protection_job(PDO $pdo,int $jobId): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM pdf_protection_jobs WHERE id=:id LIMIT 1');$stmt->execute([':id'=>$jobId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
}
