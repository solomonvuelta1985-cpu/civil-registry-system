<?php
/** Admin monitor and controls for PDF recovery/backup jobs. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

if (!isLoggedIn()) { header('Location: ../public/login.php'); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); exit('Administrator access required.'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= csrfTokenMeta() ?>
    <title>PDF Protection Jobs - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <style>
        :root { --ink:#172033; --muted:#667085; --line:#e5e7eb; --bg:#f6f8fb; --primary:#2251b5; --ok:#067647; --warn:#b54708; --bad:#b42318; }
        * { box-sizing:border-box; } body { margin:0; background:var(--bg); color:var(--ink); font-family:Inter,Segoe UI,Arial,sans-serif; }
        .wrap { max-width:1500px; margin:0 auto; padding:28px; } .head { display:flex; justify-content:space-between; gap:20px; margin-bottom:22px; }
        h1 { margin:0 0 6px; font-size:26px; } .muted { color:var(--muted); } button, select, input { border:1px solid var(--line); border-radius:8px; padding:8px 11px; background:#fff; cursor:pointer; font:inherit; } input[type=text], input[type=date] { width:100%; } button.primary { background:var(--primary); color:#fff; border-color:var(--primary); } button.danger { color:var(--bad); } button:disabled { opacity:.5; cursor:not-allowed; }
        .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:18px; margin-bottom:18px; box-shadow:0 2px 8px rgba(16,24,40,.04); } .table-wrap { overflow:auto; }
        .filters { display:grid; grid-template-columns:minmax(220px,2fr) repeat(4,minmax(130px,1fr)); gap:12px; align-items:end; } .filter label, .sort-row label { display:block; font-size:12px; font-weight:700; color:var(--muted); margin-bottom:5px; } .filter input, .filter select, .sort-row select { width:100%; }
        .sort-row { display:flex; flex-wrap:wrap; gap:10px; align-items:end; margin-top:14px; padding-top:14px; border-top:1px solid var(--line); } .sort-row .control { min-width:145px; } .filter-actions { display:flex; gap:8px; }
        .pagination-bar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin:14px 0 0; } .pagination-controls { display:flex; align-items:center; gap:8px; } .pagination-controls button { min-width:78px; }
        .selection-bar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin:14px 0; } .selection-bar strong { color:var(--ink); }
        table { width:100%; border-collapse:collapse; min-width:1120px; } th,td { text-align:left; border-bottom:1px solid var(--line); padding:10px 8px; vertical-align:top; font-size:13px; } th { color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; } th:first-child, td:first-child { width:38px; }
        tr.selected { background:#eef4ff; } .status { display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; font-weight:600; background:#eef2f6; } .status.completed,.status.imported,.status.already_present { color:var(--ok); background:#ecfdf3; } .status.failed,.status.corrupt_source,.status.invalid_pdf { color:var(--bad); background:#fef3f2; } .status.completed_with_errors,.status.needs_review,.status.unmatched,.status.paused { color:var(--warn); background:#fffaeb; }
        .progress { height:8px; background:#edf0f4; border-radius:99px; overflow:hidden; min-width:130px; } .progress > span { display:block; height:100%; background:var(--primary); } .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; } .metric { border:1px solid var(--line); border-radius:10px; padding:12px; } .metric strong { display:block; font-size:21px; margin-top:3px; } .items { max-height:520px; overflow:auto; } .small { font-size:12px; } .actions { display:flex; flex-wrap:wrap; gap:6px; } a.button { display:inline-block; border:1px solid var(--line); border-radius:8px; padding:7px 9px; color:var(--primary); text-decoration:none; background:#fff; font-size:12px; }
        @media (max-width:900px) { .filters { grid-template-columns:repeat(2,minmax(0,1fr)); } } @media (max-width:720px) { .wrap { padding:16px; } .head { display:block; } .head button { margin-top:14px; } .filters { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<main class="wrap">
    <div class="head"><div><h1>PDF Protection Jobs</h1><div class="muted">Monitor, pause, resume, retry, approve, cancel, delete, and download batch reports.</div></div><button class="primary" id="refresh">Refresh</button></div>

    <section class="card">
        <div class="filters">
            <div class="filter"><label for="search">Search</label><input id="search" type="text" placeholder="Job ID, source, or destination"></div>
            <div class="filter"><label for="job-type">Type</label><select id="job-type"><option value="">All types</option><option value="backup">Backup</option><option value="recovery">Recovery</option></select></div>
            <div class="filter"><label for="status-filter-list">Status</label><select id="status-filter-list"><option value="">All statuses</option><option value="queued">Queued</option><option value="scanning">Scanning</option><option value="preview_ready">Preview ready</option><option value="approved">Approved</option><option value="running">Running</option><option value="paused">Paused</option><option value="completed">Completed</option><option value="completed_with_errors">Completed with errors</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option></select></div>
            <div class="filter"><label for="outcome">Outcome</label><select id="outcome"><option value="">All outcomes</option><option value="active">Active / queued</option><option value="success">Successful</option><option value="attention">Needs attention</option></select></div>
            <div class="filter"><label for="has-errors">Failed items</label><select id="has-errors"><option value="">Any</option><option value="yes">Has failed items</option><option value="no">No failed items</option></select></div>
        </div>
        <div class="sort-row">
            <div class="control"><label for="date-from">Created from</label><input id="date-from" type="date"></div>
            <div class="control"><label for="date-to">Created to</label><input id="date-to" type="date"></div>
            <div class="control"><label for="sort">Sort by</label><select id="sort"><option value="updated_at">Last updated</option><option value="created_at">Created</option><option value="id">Job ID</option><option value="job_type">Type</option><option value="status">Status</option><option value="total_items">Total items</option><option value="scanned_items">Scanned</option><option value="copied_items">Copied</option><option value="failed_items">Failed items</option><option value="review_items">Review items</option></select></div>
            <div class="control"><label for="direction">Order</label><select id="direction"><option value="desc">Descending</option><option value="asc">Ascending</option></select></div>
            <div class="control"><label for="per-page">Rows per page</label><select id="per-page"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select></div>
            <div class="filter-actions"><button class="primary" id="apply-filters">Apply</button><button id="reset-filters">Reset</button></div>
        </div>
    </section>

    <section class="card">
        <div class="selection-bar"><span id="selection-summary" class="small muted">No jobs selected</span><button class="danger" id="delete-selected" disabled>Delete selected</button></div>
        <div class="table-wrap"><table><thead><tr><th><input type="checkbox" id="select-all" title="Select deletable jobs on this page"></th><th>ID</th><th>Type</th><th>Status</th><th>Progress</th><th>Copied</th><th>Errors</th><th>Review</th><th>Actions</th><th>Updated</th></tr></thead><tbody id="jobs"><tr><td colspan="10" class="muted">Loading...</td></tr></tbody></table></div>
        <div class="pagination-bar"><span id="page-summary" class="small muted">Loading...</span><div class="pagination-controls"><button id="previous-page" disabled>Previous</button><span id="page-label" class="small muted">Page 1</span><button id="next-page" disabled>Next</button></div></div>
    </section>

    <section class="card" id="detail" hidden>
        <h2 id="detail-title" style="margin-top:0">Selected job</h2><div class="grid" id="metrics"></div><p class="small muted" id="paths"></p>
        <div style="display:flex;gap:10px;align-items:center;margin:16px 0 10px"><label for="item-status-filter">Show:</label><select id="item-status-filter"><option value="">All items</option><option value="failed">Failed</option><option value="needs_review">Needs review</option><option value="unmatched">Unmatched</option><option value="corrupt_source">Corrupt source</option><option value="invalid_pdf">Invalid PDF</option><option value="imported">Imported</option><option value="already_present">Already present</option></select><span class="small muted" id="item-count"></span></div>
        <div class="table-wrap items"><table><thead><tr><th>Source</th><th>Target</th><th>Record</th><th>Match</th><th>Status</th><th>Error</th></tr></thead><tbody id="items"><tr><td colspan="6" class="muted">Select a job.</td></tr></tbody></table></div>
    </section>
</main>
<script>
const api = '../api/pdf_protection_job_status.php';
const controlApi = '../api/pdf_protection_job_control.php';
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let selectedJob = null;
let selectedIds = new Set();
let listState = { page:1, per_page:25, q:'', job_type:'', status:'', outcome:'', has_errors:'', date_from:'', date_to:'', sort:'updated_at', direction:'desc' };
const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
const fmt = value => Number(value || 0).toLocaleString();
const badge = value => `<span class="status ${esc(value)}">${esc(value)}</span>`;
const reportLinks = id => `<a class="button" href="../api/pdf_protection_job_report.php?job_id=${Number(id)}&format=csv">CSV</a><a class="button" href="../api/pdf_protection_job_report.php?job_id=${Number(id)}&format=json">JSON</a>`;
const deletable = job => ['preview_ready','completed','completed_with_errors','failed','cancelled'].includes(String(job.status));
const actionButtons = job => {
    const id = Number(job.id), state = String(job.status), out = [];
    if (state === 'preview_ready') out.push(`<button data-action="approve" data-id="${id}">Approve</button>`);
    if (['queued','scanning','approved','running'].includes(state)) out.push(`<button data-action="pause" data-id="${id}">Pause</button>`);
    if (state === 'paused') out.push(`<button data-action="resume" data-id="${id}">Resume</button>`);
    if (!['completed','completed_with_errors','failed','cancelled'].includes(state)) out.push(`<button class="danger" data-action="cancel" data-id="${id}">Cancel</button>`);
    if ((state === 'completed_with_errors' || state === 'failed') && Number(job.failed_items || 0) > 0) out.push(`<button data-action="retry" data-id="${id}">Retry failed</button>`);
    if (deletable(job)) out.push(`<button class="danger" data-action="delete" data-id="${id}">Delete</button>`);
    out.push(reportLinks(id));
    return `<div class="actions">${out.join('')}</div>`;
};
function readControls() { listState = {...listState, q:document.getElementById('search').value.trim(), job_type:document.getElementById('job-type').value, status:document.getElementById('status-filter-list').value, outcome:document.getElementById('outcome').value, has_errors:document.getElementById('has-errors').value, date_from:document.getElementById('date-from').value, date_to:document.getElementById('date-to').value, sort:document.getElementById('sort').value, direction:document.getElementById('direction').value, per_page:Number(document.getElementById('per-page').value), page:1}; }
function clearSelection() { selectedIds.clear(); updateSelectionUi(); }
function updateSelectionUi() { const count=selectedIds.size; document.getElementById('selection-summary').textContent=count ? `${count} job(s) selected` : 'No jobs selected'; document.getElementById('delete-selected').disabled=count===0; }
function queryUrl() { const params = new URLSearchParams({action:'list',page:String(listState.page),per_page:String(listState.per_page),sort:listState.sort,direction:listState.direction}); Object.entries(listState).forEach(([key,value])=>{ if (!['page','per_page','sort','direction'].includes(key) && value) params.set(key,String(value)); }); return `${api}?${params.toString()}`; }
async function deleteJobs(ids) { if (!ids.length) return; if (!window.confirm(`Delete ${ids.length} job record(s)? Their batch items and reports will also be deleted. This cannot be undone.`)) return; const form=new FormData(); form.append('action','delete'); form.append('csrf_token',csrf); ids.forEach(id=>form.append('job_ids[]',String(id))); const res=await fetch(controlApi,{method:'POST',body:form,credentials:'same-origin'}); const data=await res.json(); if(!data.success) throw new Error(data.message||'Job deletion failed.'); clearSelection(); if(ids.includes(selectedJob)){ selectedJob=null; document.getElementById('detail').hidden=true; } await loadJobs(); }
async function control(action,id) { if(action==='delete'){ await deleteJobs([id]); return; } const form=new FormData(); form.append('action',action); form.append('job_id',id); form.append('csrf_token',csrf); const res=await fetch(controlApi,{method:'POST',body:form,credentials:'same-origin'}); const data=await res.json(); if(!data.success) throw new Error(data.message||'Job control failed.'); await loadJobs(); }
async function loadJobs() {
    const res=await fetch(queryUrl(),{credentials:'same-origin'}); const data=await res.json(); if(!data.success) throw new Error(data.message||'Could not load jobs.');
    const body=document.getElementById('jobs'), page=data.pagination||{page:1,total:0,total_pages:1,from:0,to:0,has_previous:false,has_next:false};
    document.getElementById('page-summary').textContent=page.total?`Showing ${fmt(page.from)}-${fmt(page.to)} of ${fmt(page.total)} job(s)`:'No jobs match the current filters'; document.getElementById('page-label').textContent=`Page ${page.page} of ${page.total_pages}`; document.getElementById('previous-page').disabled=!page.has_previous; document.getElementById('next-page').disabled=!page.has_next; listState.page=page.page;
    if(!data.jobs.length){ body.innerHTML='<tr><td colspan="10" class="muted">No PDF protection jobs match the current filters.</td></tr>'; document.getElementById('select-all').checked=false; document.getElementById('select-all').disabled=true; return; }
    body.innerHTML=data.jobs.map(job=>{ const total=Number(job.total_items||0), scanned=Number(job.scanned_items||0), pct=total?Math.min(100,Math.round(scanned/total*100)):0, canDelete=deletable(job), checked=selectedIds.has(Number(job.id)); return `<tr class="${selectedJob===Number(job.id)?'selected':''}" data-id="${Number(job.id)}"><td><input type="checkbox" data-select="${Number(job.id)}" ${canDelete?'':'disabled'} ${checked?'checked':''} title="${canDelete?'Select job':'Active job cannot be deleted'}"></td><td>#${Number(job.id)}</td><td>${esc(job.job_type)}</td><td>${badge(job.status)}</td><td><div class="progress"><span style="width:${pct}%"></span></div><div class="small muted">${fmt(scanned)} / ${fmt(total)} (${pct}%)</div></td><td>${fmt(job.copied_items)} <span class="small muted">(${fmt(job.skipped_items)} skipped)</span></td><td>${fmt(job.failed_items)}</td><td>${fmt(job.review_items)}</td><td>${actionButtons(job)}</td><td>${esc(job.last_activity_at||job.updated_at||'')}</td></tr>`; }).join('');
    body.querySelectorAll('tr[data-id]').forEach(row=>row.addEventListener('click',event=>{if(event.target.closest('button,a,input'))return;selectJob(Number(row.dataset.id));}));
    body.querySelectorAll('input[data-select]').forEach(box=>box.addEventListener('change',()=>{const id=Number(box.dataset.select); if(box.checked)selectedIds.add(id); else selectedIds.delete(id); updateSelectionUi();}));
    body.querySelectorAll('button[data-action]').forEach(button=>button.addEventListener('click',()=>control(button.dataset.action,Number(button.dataset.id)).catch(e=>alert(e.message))));
    const boxes=[...body.querySelectorAll('input[data-select]:not(:disabled)')]; const selectedVisible=boxes.filter(box=>box.checked).length; const selectAll=document.getElementById('select-all'); selectAll.disabled=boxes.length===0; selectAll.checked=boxes.length>0&&selectedVisible===boxes.length; selectAll.indeterminate=selectedVisible>0&&selectedVisible<boxes.length; updateSelectionUi(); if(selectedJob) await selectJob(selectedJob,false);
}
async function selectJob(id,reloadJobs=true) { selectedJob=id; const filter=document.getElementById('item-status-filter').value; const res=await fetch(`${api}?action=report&job_id=${id}&item_status=${encodeURIComponent(filter)}&limit=500`,{credentials:'same-origin'}); const data=await res.json(); if(!data.success)throw new Error(data.message||'Could not load job.'); document.getElementById('detail').hidden=false; document.getElementById('detail-title').textContent=`Job #${id} — ${data.job.job_type}`; document.getElementById('paths').textContent=`Source: ${data.job.source_root||'n/a'} | Destination: ${data.job.destination_root||'n/a'}`; document.getElementById('metrics').innerHTML=[['Status',badge(data.job.status)],['Scanned',fmt(data.job.scanned_items)],['Copied/imported',fmt(data.job.copied_items)],['Skipped',fmt(data.job.skipped_items)],['Errors',fmt(data.job.failed_items)],['Needs review',fmt(data.job.review_items)]].map(m=>`<div class="metric"><span class="small muted">${m[0]}</span><strong>${m[1]}</strong></div>`).join(''); document.getElementById('item-count').textContent=`${data.items.length.toLocaleString()} displayed`; document.getElementById('items').innerHTML=data.items.length?data.items.map(item=>`<tr><td>${esc(item.source_path)}</td><td>${esc(item.destination_path)}</td><td>${esc(item.cert_type||'')} #${esc(item.record_id||'')} ${esc(item.registry_no||'')}</td><td>${esc(item.match_method)}</td><td>${badge(item.status)}</td><td>${esc(item.last_error||'')}</td></tr>`).join(''):'<tr><td colspan="6" class="muted">No items match the selected filter.</td></tr>'; if(reloadJobs)await loadJobs(); }
document.getElementById('apply-filters').addEventListener('click',()=>{readControls();clearSelection();loadJobs().catch(e=>alert(e.message));}); document.getElementById('reset-filters').addEventListener('click',()=>{['search','date-from','date-to'].forEach(id=>document.getElementById(id).value=''); ['job-type','status-filter-list','outcome','has-errors'].forEach(id=>document.getElementById(id).value=''); document.getElementById('sort').value='updated_at'; document.getElementById('direction').value='desc'; document.getElementById('per-page').value='25'; readControls();clearSelection();loadJobs().catch(e=>alert(e.message));}); document.getElementById('previous-page').addEventListener('click',()=>{if(listState.page>1){listState.page--;clearSelection();loadJobs().catch(e=>alert(e.message));}}); document.getElementById('next-page').addEventListener('click',()=>{listState.page++;clearSelection();loadJobs().catch(e=>alert(e.message));}); document.getElementById('select-all').addEventListener('change',event=>{document.querySelectorAll('#jobs input[data-select]:not(:disabled)').forEach(box=>{box.checked=event.target.checked;const id=Number(box.dataset.select);if(event.target.checked)selectedIds.add(id);else selectedIds.delete(id);});updateSelectionUi();}); document.getElementById('delete-selected').addEventListener('click',()=>deleteJobs([...selectedIds]).catch(e=>alert(e.message))); document.getElementById('refresh').addEventListener('click',()=>loadJobs().catch(e=>alert(e.message))); document.getElementById('item-status-filter').addEventListener('change',()=>selectedJob&&selectJob(selectedJob,false).catch(e=>alert(e.message))); loadJobs().catch(e=>{document.getElementById('jobs').innerHTML=`<tr><td colspan="10">${esc(e.message)}</td></tr>`;}); setInterval(()=>loadJobs().catch(()=>{}),5000);
</script>
</body>
</html>
