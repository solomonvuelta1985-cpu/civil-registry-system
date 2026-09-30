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
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Production PHP Requirements - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
<?= google_fonts_tag('Inter:wght@400;500;600;700;800') ?><script src="<?= asset_url('lucide') ?>"></script>
<link rel="stylesheet" href="../assets/css/sidebar.css?v=20260929-groups">
<style>:root{--ink:#172033;--muted:#64748b;--line:#e2e8f0;--bg:#f6f8fb;--primary:#6750a4;--good:#15803d;--bad:#b91c1c;--warn:#a16207}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,"Segoe UI",sans-serif}.content{padding:30px 36px;max-width:1200px}.hero{background:linear-gradient(135deg,#0f766e,#1e293b);color:#fff;border-radius:16px;padding:28px 32px;margin-bottom:20px}.hero h1{margin:0 0 7px;font-size:27px;display:flex;gap:10px;align-items:center}.hero p{margin:0;opacity:.9}.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 1px 3px #0000000d}.meta{color:var(--muted);font-size:12px;margin-bottom:16px}.notice{padding:12px 14px;border-radius:9px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;margin-bottom:16px}.check{display:grid;grid-template-columns:180px 110px 1fr;gap:12px;padding:12px 0;border-bottom:1px solid var(--line);align-items:center;font-size:13px}.check:last-child{border-bottom:0}.badge{display:inline-flex;width:max-content;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:800}.ok{background:#ecfdf3;color:var(--good)}.bad{background:#fef2f2;color:var(--bad)}.feature{color:var(--warn)}button{border:0;border-radius:8px;padding:10px 14px;background:var(--primary);color:#fff;font-weight:700;cursor:pointer}@media(max-width:700px){.content{padding:18px}.check{grid-template-columns:1fr}.hero h1{font-size:23px}}
</style>
</head>
<body>
<?php require_once '../includes/sidebar_nav.php'; ?>
<main class="content">
<section class="hero"><h1><i data-lucide="server-cog"></i> Production PHP Requirements</h1><p>This report runs under the web SAPI used by the NAS PHP-FPM deployment.</p></section>
<section class="card"><div id="summary" class="meta">Checking the active PHP-FPM/web runtime...</div><div id="notice" class="notice" hidden></div><div id="checks">Loading...</div><button type="button" onclick="loadReport()">Refresh check</button></section>
</main>
<script>
const api='../api/runtime_requirements.php',esc=value=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
async function loadReport(){const response=await fetch(api,{credentials:'same-origin'});const data=await response.json();if(!response.ok||!data.success)throw new Error(data.message||'Runtime check failed.');const report=data.report;document.getElementById('summary').textContent=`PHP ${report.php_version} · SAPI: ${report.sapi} · php.ini: ${report.php_ini||'not reported'}`;const notice=document.getElementById('notice');if(report.critical_failures.length||report.feature_warnings.length){notice.hidden=false;notice.textContent=report.critical_failures.length?`Critical extensions missing: ${report.critical_failures.join(', ')}. Fix PHP-FPM before production use.`:`Optional feature warning: ${report.feature_warnings.join(', ')}. Core records remain available, but the related feature is disabled.`;}else{notice.hidden=true;}document.getElementById('checks').innerHTML=report.checks.map(check=>`<div class="check"><strong>${esc(check.label)}</strong><span class="badge ${check.ok?'ok':'bad'}">${check.ok?'AVAILABLE':'MISSING'}</span><span class="${check.ok?'':'feature'}">${esc(check.purpose)}${check.ok?'':' — install/enable this capability in the PHP-FPM runtime.'}</span></div>`).join('');}
if(window.lucide)lucide.createIcons();loadReport().catch(error=>{document.getElementById('summary').textContent=error.message;});
</script>
</body>
</html>
