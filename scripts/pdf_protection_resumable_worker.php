<?php
/** Resumable, pause-aware PDF protection worker. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdf_protection_jobs.php';
require_once __DIR__ . '/../includes/pdf_protection_resumable.php';

$o = getopt('', ['mode:', 'source:', 'destination:', 'job-id::', 'dry-run', 'limit::', 'retry-failed']);
$mode = strtolower(trim((string)($o['mode'] ?? ''))); $source = (string)($o['source'] ?? ''); $destination = (string)($o['destination'] ?? '');
$jobId = isset($o['job-id']) ? (int)$o['job-id'] : 0; $dryRun = array_key_exists('dry-run', $o); $limit = max(0, (int)($o['limit'] ?? 0)); $retryFailed = array_key_exists('retry-failed', $o);
if (!in_array($mode, ['backup','recovery'], true)) { fwrite(STDERR, "Usage: php scripts/pdf_protection_resumable_worker.php --mode=backup|recovery --source=<root> --destination=<root> [--job-id=N] [--dry-run] [--limit=N] [--retry-failed]\n"); exit(2); }
$job = $jobId > 0 ? get_pdf_protection_job($pdo, $jobId) : null;
if ($jobId > 0 && !$job) { fwrite(STDERR, "Job not found.\n"); exit(2); }
if ($job) { if ($job['job_type'] !== $mode) { fwrite(STDERR, "Job type does not match --mode.\n"); exit(2); } if ($source === '') $source = (string)$job['source_root']; if ($destination === '') $destination = (string)($job['destination_root'] ?? ''); }
if ($source === '' || $destination === '') { fwrite(STDERR, "Source and destination are required for a new job.\n"); exit(2); }
$sourceRoot = realpath($source); if ($sourceRoot === false || !is_dir($sourceRoot)) { fwrite(STDERR, "Source root does not exist or is not a directory.\n"); exit(2); }
$destinationRoot = realpath($destination); if ($destinationRoot === false) { if (!mkdir($destination, 0755, true)) { fwrite(STDERR, "Destination root could not be created.\n"); exit(2); } $destinationRoot = realpath($destination); }
if ($destinationRoot === false) { fwrite(STDERR, "Destination root is unavailable.\n"); exit(2); }
$sp = rtrim(str_replace('\\','/',$sourceRoot), '/') . '/'; $dp = rtrim(str_replace('\\','/',$destinationRoot), '/') . '/';
if (str_starts_with($dp, $sp) || str_starts_with($sp, $dp)) { fwrite(STDERR, "Source and destination must be separate roots.\n"); exit(2); }
if ($jobId <= 0) { $jobId = create_pdf_protection_job($pdo, $mode, $sourceRoot, $destinationRoot, null); }
$lockName = 'pdf_protection_job_' . $jobId; $lockStmt = $pdo->prepare('SELECT GET_LOCK(:name, 0)'); $lockStmt->execute([':name'=>$lockName]);
if ((int)$lockStmt->fetchColumn() !== 1) { fwrite(STDERR, "Another worker is already processing this job.\n"); exit(3); }
$release = static function() use ($pdo,$lockName): void { try { $s=$pdo->prepare('SELECT RELEASE_LOCK(:name)'); $s->execute([':name'=>$lockName]); } catch (Throwable $e) {} };
try {
    $state = pdf_resumable_job_state($pdo,$jobId);
    if (in_array($state,['cancelled','completed'],true)) { echo json_encode(['job_id'=>$jobId,'status'=>$state,'message'=>'Job is already terminal.'],JSON_PRETTY_PRINT).PHP_EOL; $release(); exit(0); }
    if ($state === 'paused') { echo json_encode(['job_id'=>$jobId,'status'=>'paused','message'=>'Job is paused; resume it from the admin control API first.'],JSON_PRETTY_PRINT).PHP_EOL; $release(); exit(0); }
    if ($state === 'preview_ready' && !$dryRun) { fwrite(STDERR,"Preview must be approved before a non-dry-run execution.\n"); $release(); exit(2); }
    update_pdf_protection_job($pdo,$jobId,'scanning',['source_root'=>$sourceRoot,'destination_root'=>$destinationRoot]);
    $manifest=pdf_resumable_filter_manifest(scan_pdf_manifest($sourceRoot)); $inventory=pdf_resumable_ensure_inventory($pdo,$jobId,$manifest);
    update_pdf_protection_job($pdo,$jobId,$dryRun?'preview_ready':'running',['total_items'=>count($inventory),'scanned_items'=>0]);
    if($retryFailed){$pdo->prepare("UPDATE pdf_protection_job_items SET status='queued',last_error=NULL,imported_at=NULL,updated_at=NOW() WHERE job_id=:job_id AND status IN ('failed','invalid_pdf','corrupt_source')")->execute([':job_id'=>$jobId]);foreach($inventory as &$iv)if(in_array($iv['status'],['failed','invalid_pdf','corrupt_source'],true))$iv['status']='queued';unset($iv);}
    $approvedRun=$state==='approved';$pending=[];foreach($inventory as $id=>$item){$st=(string)($item['status']??'queued');if($st==='queued'||($approvedRun&&$st==='matched'))$pending[(int)$id]=$item;}if($limit>0)$pending=array_slice($pending,0,$limit,true);
    $records=pdf_resumable_record_indexes($pdo);$processed=0;$summary=['job_id'=>$jobId,'mode'=>$mode,'dry_run'=>$dryRun,'total'=>count($inventory),'processed'=>0,'copied_or_imported'=>0,'already_present'=>0,'matched'=>0,'needs_review'=>0,'unmatched'=>0,'invalid'=>0,'corrupt_source'=>0,'failed'=>0,'paused'=>false,'cancelled'=>false];
    foreach($pending as $itemId=>$item){
        $state=pdf_resumable_job_state($pdo,$jobId);if($state==='paused'||$state==='cancelled'){$summary[$state]=true;break;}$processed++;$attempts=pdf_resumable_increment_attempt($pdo,(int)$itemId);$sourceItem=$item['source_item'];$relative=$item['relative_path'];$status='scanned';$fields=['source_hash'=>$sourceItem['hash'],'expected_hash'=>null,'destination_path'=>null,'destination_hash'=>null,'match_method'=>'none','attempts'=>$attempts];
        try{
            $bad=validate_pdf_integrity($sourceItem['absolute_path']);
            if(!empty($bad)){$status='invalid_pdf';$summary['invalid']++;}
            elseif($mode==='backup'){
                $expected=$records['by_path'][$relative][0]??null;if($expected!==null&&preg_match('/^[a-f0-9]{64}$/i',(string)$expected['pdf_hash'])){$fields['expected_hash']=strtolower((string)$expected['pdf_hash']);$fields['match_method']='exact_hash';if(!hash_equals($fields['expected_hash'],strtolower((string)$sourceItem['hash']))){$status='corrupt_source';$summary['corrupt_source']++;}}
                $dest=pdf_manifest_join_path($destinationRoot,$relative);$fields['destination_path']=$dest;if($status!=='corrupt_source'){$dh=is_file($dest)?hash_file('sha256',$dest):null;if(is_string($dh)&&hash_equals(strtolower($sourceItem['hash']),strtolower($dh))){$status='already_present';$fields['destination_hash']=strtolower($dh);$summary['already_present']++;}elseif($dryRun){$status='matched';$summary['matched']++;}else{pdf_resumable_install_verified($sourceItem['absolute_path'],$dest,$sourceItem['hash']);$status='imported';$fields['destination_hash']=strtolower((string)$sourceItem['hash']);$summary['copied_or_imported']++;}}
            }else{
                $hash=strtolower((string)$sourceItem['hash']);$matches=$records['by_hash'][$hash]??[];
                if(count($matches)===1){$record=$matches[0];$target=str_replace('\\','/',ltrim((string)$record['pdf_filename'],'/'));if($target==='')$target='recovered/'.$record['cert_type'].'/record_'.(int)$record['id'].'/recovered_'.$hash.'.pdf';$dest=pdf_manifest_join_path(UPLOAD_DIR,$target);$fields=['destination_path'=>$dest,'expected_hash'=>strtolower((string)$record['pdf_hash']),'destination_hash'=>null,'cert_type'=>$record['cert_type'],'record_id'=>(int)$record['id'],'registry_no'=>$record['registry_no'],'match_method'=>'exact_hash','source_hash'=>$hash,'attempts'=>$attempts];$eh=is_file($dest)?hash_file('sha256',$dest):null;if(is_string($eh)&&hash_equals($hash,strtolower($eh))){$status='already_present';$fields['destination_hash']=strtolower($eh);$summary['already_present']++;}elseif($dryRun){$status='matched';$summary['matched']++;}else{pdf_resumable_install_verified($sourceItem['absolute_path'],$dest,$hash);$pdo->beginTransaction();try{pdf_resumable_update_record($pdo,$record,$target,$hash);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}$status='imported';$fields['destination_hash']=$hash;$summary['copied_or_imported']++;}}
                else{$status=count($matches)>1?'needs_review':'unmatched';$summary[$status]++;}
            }
            update_pdf_protection_job_item($pdo,(int)$itemId,$status,$fields);
        }catch(Throwable $e){$status='failed';$summary['failed']++;update_pdf_protection_job_item($pdo,(int)$itemId,$status,array_merge($fields,['last_error'=>$e->getMessage()]));}
        $summary['processed']=$processed;$percent=count($pending)>0?round(($processed/count($pending))*100,1):100;echo sprintf('[%s%%] %d/%d %-18s %s%s',$percent,$processed,count($pending),$status,$relative,PHP_EOL);if(($processed%25)===0||$processed===count($pending)){$cs=pdf_resumable_job_state($pdo,$jobId);pdf_resumable_refresh_counters($pdo,$jobId,$cs==='paused'?'paused':($cs==='cancelled'?'cancelled':'running'));}
    }
    $state=pdf_resumable_job_state($pdo,$jobId);if($state!=='paused'&&$state!=='cancelled'){$ps=$pdo->prepare("SELECT COUNT(*) FROM pdf_protection_job_items WHERE job_id=:job_id AND status IN ('queued','matched')");$ps->execute([':job_id'=>$jobId]);$remaining=(int)$ps->fetchColumn();if($dryRun)$final='preview_ready';elseif($remaining>0)$final='running';else{$es=$pdo->prepare("SELECT COUNT(*) FROM pdf_protection_job_items WHERE job_id=:job_id AND status IN ('failed','invalid_pdf','corrupt_source','needs_review','unmatched')");$es->execute([':job_id'=>$jobId]);$final=(int)$es->fetchColumn()>0?'completed_with_errors':'completed';}pdf_resumable_refresh_counters($pdo,$jobId,$final);}else pdf_resumable_refresh_counters($pdo,$jobId,$state);
    $summary['status']=pdf_resumable_job_state($pdo,$jobId);echo json_encode($summary,JSON_PRETTY_PRINT).PHP_EOL;$release();exit(0);
}catch(Throwable $e){update_pdf_protection_job($pdo,$jobId,'failed',['last_error'=>$e->getMessage()]);$release();fwrite(STDERR,'ERROR: '.$e->getMessage().PHP_EOL);exit(1);}
