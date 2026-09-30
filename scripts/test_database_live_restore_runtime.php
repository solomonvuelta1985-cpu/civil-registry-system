<?php
/** Read-only runtime smoke test for Phase 6 database restore queries. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/pdf_backup_locations.php';
require_once __DIR__ . '/../includes/database_live_restore_launcher.php';
require_once __DIR__ . '/../includes/database_live_restore.php';
$eligible=$pdo->query("SELECT p.id,p.backup_job_id,p.status,b.database_name,p.staging_table_count,p.table_statements,p.pdf_reference_count,p.pdf_present_count,p.pdf_missing_count,p.pdf_invalid_count,p.created_at,b.status AS backup_status,b.dump_path,b.manifest_path FROM database_restore_preview_jobs p JOIN database_backup_jobs b ON b.id=p.backup_job_id WHERE p.status IN ('completed','completed_with_errors','cleaned') AND p.staging_table_count>0 AND p.table_statements>0 AND NOT EXISTS (SELECT 1 FROM database_live_restore_jobs l WHERE l.preview_job_id=p.id AND l.status NOT IN ('cancelled','failed','completed','completed_with_errors','rolled_back')) ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$total=(int)$pdo->query('SELECT COUNT(*) FROM database_live_restore_jobs')->fetchColumn();$locations=[];foreach(pdf_backup_allowed_roots() as $root){try{$locations[]=pdf_backup_storage_info($root,false);}catch(Throwable $e){}}
echo json_encode(['maintenance_mode'=>database_live_restore_maintenance_enabled(),'eligible_preview_count'=>count($eligible),'live_restore_job_count'=>$total,'location_count'=>count($locations)],JSON_PRETTY_PRINT).PHP_EOL;
