<?php
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
requireAuth();
requireAdmin();
setSecurityHeaders();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <?= csrfTokenMeta() ?>
    <title>Recovery Review - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <?= google_fonts_tag('Inter:wght@400;500;600;700;800') ?>
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=20260929-groups">
    <style>
        :root{--ink:#172033;--muted:#64748b;--line:#e2e8f0;--bg:#f6f8fb;--primary:#6750a4;--warn:#b45309;--bad:#b91c1c}
        body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,"Segoe UI",sans-serif}.content{padding:30px 36px;max-width:1750px}.hero{background:linear-gradient(135deg,#1e293b,#0f172a);color:#fff;border-radius:16px;padding:28px 32px;margin-bottom:20px}.hero h1{margin:0 0 7px;font-size:27px;display:flex;align-items:center;gap:10px}.hero p{margin:0;opacity:.9;font-size:14px}.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px #0000000d}.notice{padding:12px 14px;border-radius:9px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;margin-bottom:16px}.filters{display:grid;grid-template-columns:minmax(240px,2fr) repeat(3,minmax(150px,1fr));gap:10px;align-items:end}.field label{display:block;font-size:12px;font-weight:800;color:#475569;margin-bottom:5px}.field input,.field select{width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:9px 10px;background:#fff;font:inherit;font-size:13px}.btn{border:0;border-radius:8px;padding:10px 14px;font:inherit;font-size:13px;font-weight:700;cursor:pointer}.btn-primary{background:var(--primary);color:#fff}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:1200px}th,td{padding:10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:12px}th{background:#f8fafc;color:#475569;font-size:11px;text-transform:uppercase;letter-spacing:.04em}.status{display:inline-flex;padding:4px 8px;border-radius:999px;background:#fffaeb;color:var(--warn);font-weight:800}.status.corrupt_source{background:#fef2f2;color:var(--bad)}.small{font-size:11px;color:var(--muted)}.source{max-width:360px;word-break:break-all}.mapping{display:grid;grid-template-columns:140px 100px auto;gap:6px;align-items:center}.mapping select,.mapping input{border:1px solid #cbd5e1;border-radius:7px;padding:7px;font:inherit;font-size:12px}.import-btn{background:#166534;color:#fff;border:0;border-radius:7px;padding:7px 9px;font:inherit;font-size:12px;font-weight:700;cursor:pointer}.pagination{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:14px}.pagination button{border:1px solid #cbd5e1;background:#fff;border-radius:7px;padding:8px 12px}.empty{padding:28px;text-align:center;color:var(--muted)}
        @media(max-width:950px){.content{padding:20px}.filters{grid-template-columns:1fr 1fr}}@media(max-width:600px){.filters{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php require_once '../includes/sidebar_nav.php'; ?>
<main class="content">
    <section class="hero"><h1><i data-lucide="clipboard-check"></i> Recovery Review Queue</h1><p>Review unmatched recovery PDFs and map them to an existing active record before importing.</p></section>
    <section class="card">
        <div class="notice"><strong>Controlled import:</strong> the target record must already exist and be Active. The source PDF must be inside the recovery job, pass integrity validation, and match a unique SHA-256 fingerprint. No database record is created automatically.</div>
        <div class="filters">
            <div class="field"><label for="q">Search</label><input id="q" placeholder="Item ID, job ID, registry no., or source path"></div>
            <div class="field"><label for="status">Status</label><select id="status"><option value="">All review items</option><option value="unmatched">Unmatched</option><option value="needs_review">Needs review</option><option value="corrupt_source">Corrupt source</option></select></div>
            <div class="field"><label for="per-page">Rows</label><select id="per-page"><option>10</option><option selected>25</option><option>50</option><option>100</option></select></div>
            <button class="btn btn-primary" id="refresh">Refresh</button>
        </div>
    </section>
    <section class="card">
        <div class="table-wrap"><table><thead><tr><th>Status</th><th>Job / Item</th><th>Source PDF</th><th>Source hash</th><th>Review mapping</th></tr></thead><tbody id="rows"><tr><td colspan="5" class="empty">Loading...</td></tr></tbody></table></div>
        <div class="pagination"><span class="small" id="summary">Loading...</span><span><button id="prev" disabled>Previous</button> <button id="next" disabled>Next</button></span></div>
    </section>
</main>
<script>
const api = '../api/pdf_recovery_review.php';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const rows = document.getElementById('rows');
const summary = document.getElementById('summary');
let page = 1;
const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));

async function load() {
    const params = new URLSearchParams({action:'list', page:String(page), per_page:document.getElementById('per-page').value, q:document.getElementById('q').value.trim(), status:document.getElementById('status').value});
    const response = await fetch(`${api}?${params.toString()}`, {credentials:'same-origin'});
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Could not load recovery review items.');
    const pagination = data.pagination; page = pagination.page;
    summary.textContent = pagination.total ? `Showing ${Number(pagination.from).toLocaleString()}-${Number(pagination.to).toLocaleString()} of ${Number(pagination.total).toLocaleString()} review item(s)` : 'No review items found';
    document.getElementById('prev').disabled = !pagination.has_previous;
    document.getElementById('next').disabled = !pagination.has_next;
    if (!data.items.length) { rows.innerHTML = '<tr><td colspan="5" class="empty">No recovery items require review.</td></tr>'; return; }
    rows.innerHTML = data.items.map(item => `<tr><td><span class="status ${esc(item.status)}">${esc(item.status)}</span><div class="small">${esc(item.job_status || '')}</div></td><td>#${Number(item.job_id)} / item #${Number(item.item_id)}<div class="small">${esc(item.cert_type || 'not mapped')} ${item.record_id ? ('#' + esc(item.record_id)) : ''}</div></td><td class="source">${esc(item.source_path)}<div class="small">${esc(item.last_error || '')}</div></td><td style="word-break:break-all">${esc(item.source_hash || '-')}</td><td><div class="mapping"><select data-type="${Number(item.item_id)}"><option value="birth">Birth</option><option value="death">Death</option><option value="marriage">Marriage</option><option value="marriage_license">Marriage license</option></select><input data-record="${Number(item.item_id)}" type="number" min="1" placeholder="Record ID"><button class="import-btn" data-import="${Number(item.item_id)}" data-job="${Number(item.job_id)}">Import</button></div><div class="small">Use the existing Active record ID.</div></td></tr>`).join('');
    rows.querySelectorAll('[data-import]').forEach(button => button.addEventListener('click', () => importItem(button).catch(error => alert(error.message))));
}

async function importItem(button) {
    const itemId = Number(button.dataset.import); const jobId = Number(button.dataset.job);
    const certType = document.querySelector(`[data-type="${itemId}"]`).value; const recordId = document.querySelector(`[data-record="${itemId}"]`).value;
    if (!recordId || Number(recordId) < 1) { alert('Enter the existing Active record ID first.'); return; }
    if (!confirm(`Import this PDF to ${certType} record #${recordId}?`)) return;
    button.disabled = true;
    const form = new FormData(); form.append('action','import'); form.append('csrf_token',csrf); form.append('job_id',String(jobId)); form.append('item_id',String(itemId)); form.append('cert_type',certType); form.append('record_id',String(recordId));
    const response = await fetch(api, {method:'POST', body:form, credentials:'same-origin'}); const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Manual import failed.');
    alert(data.message); await load();
}

document.getElementById('refresh').addEventListener('click', () => load().catch(error => alert(error.message)));
document.getElementById('prev').addEventListener('click', () => { if (page > 1) { page--; load().catch(error => alert(error.message)); } });
document.getElementById('next').addEventListener('click', () => { page++; load().catch(error => alert(error.message)); });
document.getElementById('status').addEventListener('change', () => { page = 1; load().catch(error => alert(error.message)); });
document.getElementById('per-page').addEventListener('change', () => { page = 1; load().catch(error => alert(error.message)); });
document.getElementById('q').addEventListener('keydown', event => { if (event.key === 'Enter') { page = 1; load().catch(error => alert(error.message)); } });
if (window.lucide) lucide.createIcons();
load().catch(error => { rows.innerHTML = `<tr><td colspan="5" class="empty">${esc(error.message)}</td></tr>`; summary.textContent = 'Unable to load review items.'; });
</script>
</body>
</html>
