<?php
/** System-based PDF backup and recovery setup. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

requireAuth();
requireAdmin();
setSecurityHeaders();
$csrfMeta = csrfTokenMeta();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $csrfMeta ?>
    <title>PDF Backup &amp; Recovery - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <?= google_fonts_tag('Inter:wght@400;500;600;700;800') ?>
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=20260929-groups">
    <style>
        :root { --primary:#6750A4; --ink:#1c1b1f; --muted:#64748b; --line:#e2e8f0; --bg:#f8fafc; --success:#15803d; --success-bg:#dcfce7; --warning:#b45309; --warning-bg:#fef3c7; --info:#1d4ed8; --info-bg:#dbeafe; --e1:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06); --e2:0 4px 6px rgba(0,0,0,.07),0 2px 4px rgba(0,0,0,.06); }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter','Segoe UI',sans-serif; background:var(--bg); color:var(--ink); line-height:1.6; }
        .content { padding:32px 36px; max-width:1600px; }
        .hero { background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%); color:#fff; padding:32px 36px; border-radius:16px; margin-bottom:24px; box-shadow:var(--e2); position:relative; overflow:hidden; }
        .hero::before { content:''; position:absolute; top:0; right:0; width:300px; height:300px; background:radial-gradient(circle,rgba(59,130,246,.16) 0%,transparent 70%); border-radius:50%; transform:translate(30%,-30%); }
        .hero h1 { font-size:28px; font-weight:800; margin-bottom:8px; display:flex; align-items:center; gap:12px; position:relative; }
        .hero p { font-size:15px; opacity:.9; max-width:760px; position:relative; }
        .purpose { display:flex; align-items:flex-start; gap:12px; padding:14px 18px; margin-bottom:22px; background:var(--info-bg); border:1px solid #bfdbfe; border-radius:12px; color:#1e3a8a; box-shadow:var(--e1); }
        .purpose strong { display:block; margin-bottom:2px; font-size:13px; } .purpose span { display:block; font-size:12px; line-height:1.5; }
        .layout { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr); gap:20px; align-items:start; }
        .card { background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:var(--e1); padding:22px; }
        .card h2 { font-size:18px; margin-bottom:5px; } .card-subtitle { color:var(--muted); font-size:13px; margin-bottom:18px; }
        .tabs { display:flex; gap:4px; margin-bottom:20px; border-bottom:2px solid var(--line); }
        .tab { padding:11px 18px; cursor:pointer; font-size:14px; font-weight:700; color:var(--muted); border:0; border-bottom:3px solid transparent; margin-bottom:-2px; background:transparent; display:inline-flex; align-items:center; gap:8px; }
        .tab.active { color:var(--primary); border-bottom-color:var(--primary); }
        .field { margin-bottom:17px; } label { display:block; font-size:13px; font-weight:700; color:#334155; margin-bottom:6px; } .hint { display:block; font-size:12px; color:#64748b; margin-top:5px; }
        select, input[type=text] { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:11px 12px; background:#fff; color:#1e293b; font:inherit; font-size:14px; } select:focus, input:focus { outline:3px solid rgba(103,80,164,.15); border-color:var(--primary); }
        .path-preview { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:9px; background:#f8fafc; border:1px dashed #cbd5e1; margin:3px 0 18px; } .path-preview code { word-break:break-all; font-size:12px; color:#334155; }
        .mode-copy { padding:12px 14px; border-radius:9px; background:#f8fafc; margin-bottom:18px; color:#475569; font-size:13px; } .mode-copy strong { color:#1e293b; }
        .actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:22px; } .btn { border:0; border-radius:8px; padding:10px 15px; font:inherit; font-size:13px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; } .btn-primary { background:var(--primary); color:#fff; } .btn-primary:hover { background:#5a3d99; } .btn-outline { background:#fff; color:#475569; border:1px solid #cbd5e1; } .btn:disabled { opacity:.55; cursor:not-allowed; }
        .check { display:flex; gap:9px; align-items:flex-start; font-size:13px; color:#475569; } .check input { margin-top:4px; accent-color:var(--primary); }
        .flash { padding:12px 15px; border-radius:9px; font-size:13px; margin-bottom:18px; display:none; } .flash.show { display:flex; gap:9px; align-items:flex-start; } .flash.success { background:var(--success-bg); color:var(--success); border:1px solid #86efac; } .flash.error { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; } .flash.info { background:var(--info-bg); color:#1e3a8a; border:1px solid #93c5fd; }
        .stat-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin:16px 0 20px; } .stat { border:1px solid var(--line); border-radius:10px; padding:13px; background:#fff; } .stat-label { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.04em; font-weight:700; } .stat-value { display:block; margin-top:4px; font-size:20px; font-weight:800; color:#1e293b; }
        .checklist { list-style:none; display:grid; gap:12px; } .checklist li { display:flex; gap:10px; color:#475569; font-size:13px; } .checklist i { color:var(--success); flex:0 0 auto; margin-top:3px; } .monitor-link { display:inline-flex; align-items:center; gap:7px; color:var(--primary); font-size:13px; font-weight:700; text-decoration:none; margin-top:18px; }
        @media (max-width:900px) { .content { padding:22px 18px; } .layout { grid-template-columns:1fr; } } @media (max-width:560px) { .hero { padding:24px 20px; } .hero h1 { font-size:23px; } .tabs { overflow:auto; } .tab { white-space:nowrap; } }
    </style>
</head>
<body>
<?php require_once '../includes/sidebar_nav.php'; ?>
<div class="content">
    <section class="hero"><h1><i data-lucide="hard-drive-download"></i> PDF Backup &amp; Recovery</h1><p>Choose a safe storage location, set a custom folder name, and start a verified batch job without using a command line.</p></section>
    <div class="purpose"><i data-lucide="shield-check" style="width:20px;height:20px;flex:0 0 auto;"></i><div><strong>Administrator-only protected workflow</strong><span>The system validates the selected drive, prevents unsafe folder traversal, creates the backup folder when needed, and records a job ID for progress and audit reporting.</span></div></div>
    <div id="flash" class="flash"></div>
    <div class="layout">
        <section class="card">
            <h2>Start a PDF protection job</h2><p class="card-subtitle">Use a dry run first when recovering from an external source or testing a new backup destination.</p>
            <div class="tabs"><button class="tab active" type="button" data-mode="backup"><i data-lucide="hard-drive-upload"></i> Incremental Backup</button><button class="tab" type="button" data-mode="recovery"><i data-lucide="hard-drive-download"></i> Recovery</button></div>
            <div id="mode-copy" class="mode-copy"><strong>Backup mode:</strong> source is the current iSCAN uploads/NAS folder; the selected external folder becomes the destination.</div>
            <form id="setup-form">
                <input type="hidden" name="action" value="create"><input type="hidden" name="mode" id="mode" value="backup"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field"><label for="storage-root">Storage drive / approved root</label><select id="storage-root" name="storage_root" required><option value="">Loading available storage roots...</option></select><span class="hint">Only administrator-approved or locally visible storage roots are shown. Read-only roots cannot be used as backup destinations.</span></div>
                <div class="field"><label for="folder-name">Folder name</label><input id="folder-name" name="folder_name" type="text" maxlength="80" value="iSCAN-PDF-Backup_<?= date('Y-m-d') ?>" required><span class="hint">Example: iSCAN-PDF-Backup_2026-09-23_Batch-001</span></div>
                <div class="path-preview"><i data-lucide="folder-open" style="width:18px;height:18px;color:var(--primary);flex:0 0 auto;"></i><code id="path-preview">Select a storage root and enter a folder name.</code></div>
                <label class="check"><input type="checkbox" name="dry_run" value="1" checked><span><strong>Dry run / preview only</strong><br><span class="hint" style="margin-top:2px;">Scan and match files, but do not copy or change certificate records.</span></span></label>
                <div class="actions"><button class="btn btn-primary" type="submit" id="start-btn"><i data-lucide="play"></i><span>Validate &amp; Start Job</span></button><button class="btn btn-outline" type="button" id="validate-btn"><i data-lucide="scan-search"></i> Validate Location</button></div>
            </form>
        </section>
        <aside class="card">
            <h2>Selected location</h2><p class="card-subtitle">Storage availability and safety checks.</p>
            <div class="stat-grid"><div class="stat"><span class="stat-label">Free space</span><span class="stat-value" id="free-space">-</span></div><div class="stat"><span class="stat-label">Folder state</span><span class="stat-value" id="folder-state">-</span></div></div>
            <ul class="checklist"><li><i data-lucide="check-circle-2"></i><span>Only the selected folder is used as the job destination/source.</span></li><li><i data-lucide="check-circle-2"></i><span>Files are copied through a temporary path and verified by SHA-256.</span></li><li><i data-lucide="check-circle-2"></i><span>Existing healthy backup files are protected from corrupt sources.</span></li><li><i data-lucide="check-circle-2"></i><span>Every file and result is recorded under a persistent Batch Job ID.</span></li></ul>
            <a class="monitor-link" href="<?= BASE_URL ?>admin/pdf_protection_jobs.php"><i data-lucide="activity"></i> Open job monitor</a>
        </aside>
    </div>
</div>
<script>
const api = '../api/pdf_protection_setup.php';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const rootSelect = document.getElementById('storage-root'); const folderInput = document.getElementById('folder-name'); const modeInput = document.getElementById('mode'); const pathPreview = document.getElementById('path-preview'); const freeSpace = document.getElementById('free-space'); const folderState = document.getElementById('folder-state'); const flash = document.getElementById('flash');
let locations = []; let currentMode = 'backup';
const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
function showFlash(type, message) { flash.className = `flash show ${type}`; flash.textContent = message; }
function selectedLocation() { return locations.find(item => item.root === rootSelect.value) || null; }
function updatePreview() { const root = rootSelect.value; const folder = folderInput.value.trim(); pathPreview.textContent = root && folder ? `${root.replace(/[\\\/]$/, '')}\\${folder}` : 'Select a storage root and enter a folder name.'; const location = selectedLocation(); freeSpace.textContent = location ? `${Number(location.free_gb || 0).toLocaleString()} GB · ${location.writable ? 'writable' : 'read-only'}` : '-'; }
async function loadLocations() { const response = await fetch(`${api}?action=locations`, {credentials:'same-origin'}); const data = await response.json(); if (!data.success) throw new Error(data.message || 'Could not load storage roots.'); locations = data.locations || []; rootSelect.innerHTML = locations.length ? locations.map(item => { const state = item.writable ? 'writable' : 'read-only'; return `<option value="${esc(item.root)}">${esc(item.root)} — ${Number(item.free_gb || 0).toLocaleString()} GB free — ${state}</option>`; }).join('') : '<option value="">No approved storage roots available</option>'; updatePreview(); }
async function validateLocation() { const form = new FormData(document.getElementById('setup-form')); form.set('action','validate'); form.set('csrf_token',csrf); const response=await fetch(api,{method:'POST',body:form,credentials:'same-origin'}); const data=await response.json(); if (!data.success) throw new Error(data.message || 'Location validation failed.'); folderState.textContent = data.exists ? 'Existing folder' : 'Will be created'; showFlash('success', `${data.message} Path: ${data.path}`); }
document.querySelectorAll('.tab').forEach(tab => tab.addEventListener('click', () => { document.querySelectorAll('.tab').forEach(item=>item.classList.remove('active')); tab.classList.add('active'); currentMode=tab.dataset.mode; modeInput.value=currentMode; document.getElementById('mode-copy').innerHTML=currentMode==='backup' ? '<strong>Backup mode:</strong> source is the current iSCAN uploads/NAS folder; the selected external folder becomes the destination.' : '<strong>Recovery mode:</strong> the selected existing external folder is the source; iSCAN uploads/NAS becomes the verified destination.'; updatePreview(); }));
rootSelect.addEventListener('change',updatePreview); folderInput.addEventListener('input',updatePreview); document.getElementById('validate-btn').addEventListener('click',()=>validateLocation().catch(error=>showFlash('error',error.message)));
document.getElementById('setup-form').addEventListener('submit',async event=>{ event.preventDefault(); const button=document.getElementById('start-btn'); button.disabled=true; showFlash('info','Validating location and starting the protected worker...'); const form=new FormData(event.currentTarget); form.set('action','create'); form.set('csrf_token',csrf); try { const response=await fetch(api,{method:'POST',body:form,credentials:'same-origin'}); const data=await response.json(); if(!data.success) throw new Error(data.message||'Could not start job.'); folderState.textContent='Job started'; showFlash('success',`${data.message} Job ID: ${data.job_id}`); setTimeout(()=>{ window.location.href=`<?= BASE_URL ?>admin/pdf_protection_jobs.php`; },1200); } catch(error) { showFlash('error',error.message); button.disabled=false; } });
loadLocations().catch(error=>showFlash('error',error.message)); if(window.lucide) lucide.createIcons();
</script>
</body>
</html>
