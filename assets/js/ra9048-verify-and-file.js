/**
 * RA 9048 — Verify & File post-save pipeline.
 *
 * After CertificateFormHandler's POST to petition_save.php / petition_update.php
 * returns success, we:
 *   1. Call api/ra9048/generate_document.php?doc_type=all to create the DOCX bundle
 *   2. Hold the post-save Notiflix redirect long enough to show download links
 *   3. Render a success panel with download buttons + a "Continue to records"
 *      button so the admin can choose when to leave the page
 *
 * Implemented by wrapping window.fetch — narrow surface, no edits to the
 * existing CertificateFormHandler code. We watch only for POSTs to the two
 * petition endpoints and only when those return JSON success.
 */
(function () {
    'use strict';

    const SAVE_ENDPOINT_RE   = /\/api\/ra9048\/petition_save\.php(\?|$)/;
    const UPDATE_ENDPOINT_RE = /\/api\/ra9048\/petition_update\.php(\?|$)/;

    const originalFetch = window.fetch.bind(window);

    window.fetch = function (input, init) {
        const url = (typeof input === 'string') ? input : (input && input.url) || '';
        const isSave   = SAVE_ENDPOINT_RE.test(url);
        const isUpdate = UPDATE_ENDPOINT_RE.test(url);

        if (!isSave && !isUpdate) {
            return originalFetch(input, init);
        }

        return originalFetch(input, init).then(function (response) {
            // Clone so we can inspect the body without consuming it.
            const cloned = response.clone();
            cloned.json().then(function (json) {
                if (json && json.success && json.data && json.data.id) {
                    // Suppress the existing Notiflix redirect/timer and run our flow instead.
                    suppressDefaultRedirect();
                    handlePostSave(json.data.id, isUpdate);
                }
            }).catch(function () { /* not JSON or no body — ignore */ });
            return response;
        });
    };

    /**
     * The form handler triggers Notiflix.Report.success + setTimeout(redirect, 3000).
     * We monkey-patch both to no-ops for one tick so our panel takes over.
     */
    function suppressDefaultRedirect() {
        try {
            if (window.Notiflix && Notiflix.Report) {
                const originalSuccess = Notiflix.Report.success;
                Notiflix.Report.success = function () { /* swallow this one call */
                    // Restore the original immediately for any subsequent calls.
                    Notiflix.Report.success = originalSuccess;
                };
            }
            // Patch setTimeout to drop the 3000ms redirect that the form-handler queues
            // right after the call we just suppressed.
            const originalSetTimeout = window.setTimeout;
            window.setTimeout = function (fn, ms) {
                if (ms === 3000 && typeof fn === 'function'
                        && /location\.href/.test(fn.toString())) {
                    // This is the auto-redirect — drop it.
                    window.setTimeout = originalSetTimeout;
                    return 0;
                }
                return originalSetTimeout.apply(window, arguments);
            };
            // Restore setTimeout after the next macrotask so we don't suppress unrelated timers.
            originalSetTimeout(function () {
                window.setTimeout = originalSetTimeout;
            }, 0);
        } catch (e) { /* best-effort suppression — fall through if anything errors */ }
    }

    function handlePostSave(petitionId, isUpdate) {
        const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        showGeneratingOverlay();

        const formData = new FormData();
        formData.append('petition_id', petitionId);
        formData.append('doc_type', 'all');
        formData.append('csrf_token', csrf);

        originalFetch('../../api/ra9048/generate_document.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                hideGeneratingOverlay();
                if (json && json.success) {
                    showSuccessPanel(petitionId, json.data, isUpdate);
                } else {
                    showSuccessPanel(petitionId, { generated: [], skipped: [] }, isUpdate, (json && json.message) || 'Documents could not be generated.');
                }
            })
            .catch(function () {
                hideGeneratingOverlay();
                showSuccessPanel(petitionId, { generated: [], skipped: [] }, isUpdate, 'Network error during document generation.');
            });
    }

    function showGeneratingOverlay() {
        if (document.getElementById('ra9048GenOverlay')) return;
        const overlay = document.createElement('div');
        overlay.id = 'ra9048GenOverlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:99998;'
            + 'display:flex;align-items:center;justify-content:center;';
        overlay.innerHTML =
            '<div style="background:#fff;padding:28px 36px;border-radius:12px;text-align:center;'
            + 'box-shadow:0 20px 50px rgba(0,0,0,0.25);min-width:280px;">'
            + '<div style="width:44px;height:44px;border:3px solid #cbd5e1;border-top-color:#3b82f6;'
            + 'border-radius:50%;margin:0 auto 14px;animation:ra9048Spin 0.8s linear infinite;"></div>'
            + '<div style="font-size:14px;color:#0f172a;font-weight:500;">Generating documents…</div>'
            + '</div>'
            + '<style>@keyframes ra9048Spin { to { transform: rotate(360deg); } }</style>';
        document.body.appendChild(overlay);
    }

    function hideGeneratingOverlay() {
        const o = document.getElementById('ra9048GenOverlay');
        if (o && o.parentNode) o.parentNode.removeChild(o);
    }

    function showSuccessPanel(petitionId, data, isUpdate, errorMsg) {
        const generated = (data && data.generated) || [];
        const skipped   = (data && data.skipped)   || [];
        const verb = isUpdate ? 'updated' : 'filed';

        const recordsUrl = '../../public/ra9048/records.php?type=petition';
        // Prefer absolute path inferred from current location to match how form-handler does it.
        const baseFromHere = location.pathname.replace(/\/public\/ra9048\/petition\.php.*/, '');
        const absRecordsUrl = baseFromHere + '/public/ra9048/records.php?type=petition';

        const overlay = document.createElement('div');
        overlay.id = 'ra9048SuccessPanel';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:99999;'
            + 'display:flex;align-items:center;justify-content:center;padding:20px;overflow:auto;';

        let docsHtml = '';
        if (generated.length === 0) {
            docsHtml = '<p style="color:#b45309;font-size:13px;margin:6px 0;">'
                + 'No documents were generated. '
                + (errorMsg || 'Templates may not yet have placeholders configured (Phase 4b).')
                + '</p>';
        } else {
            docsHtml = '<div style="display:flex;flex-direction:column;gap:8px;margin-top:14px;">';
            generated.forEach(function (g) {
                docsHtml += '<a href="' + g.url + '" target="_blank" rel="noopener" '
                    + 'style="display:flex;align-items:center;justify-content:space-between;'
                    + 'padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;'
                    + 'background:#f8fafc;color:#0f172a;text-decoration:none;font-size:13px;'
                    + 'transition:background 0.12s,border-color 0.12s;">'
                    + '<span><strong>' + escapeHtml(g.label) + '</strong> '
                    + '<span style="color:#64748b;">— ' + escapeHtml(g.filename) + '</span></span>'
                    + '<span style="color:#3b82f6;font-weight:500;">Download</span>'
                    + '</a>';
            });
            docsHtml += '</div>';
        }

        let skippedHtml = '';
        if (skipped.length > 0) {
            skippedHtml = '<details style="margin-top:12px;font-size:12px;color:#64748b;">'
                + '<summary style="cursor:pointer;">' + skipped.length + ' skipped</summary>'
                + '<ul style="margin:8px 0 0 18px;padding:0;">'
                + skipped.map(function (s) {
                    return '<li><strong>' + escapeHtml(s.doc_type) + ':</strong> ' + escapeHtml(s.reason) + '</li>';
                }).join('')
                + '</ul></details>';
        }

        overlay.innerHTML =
            '<div style="background:#fff;border-radius:14px;box-shadow:0 25px 60px rgba(0,0,0,0.3);'
            + 'max-width:540px;width:100%;padding:26px 30px;">'
            + '<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">'
            + '<div style="width:36px;height:36px;border-radius:50%;background:#dcfce7;color:#15803d;'
            + 'display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;">✓</div>'
            + '<h3 style="margin:0;font-size:17px;font-weight:600;color:#0f172a;">'
            + 'Petition ' + verb + ' successfully</h3></div>'
            + '<p style="margin:6px 0 0;font-size:13px;color:#475569;">'
            + 'Petition record #' + petitionId + ' saved.</p>'
            + '<h4 style="margin:18px 0 0;font-size:13px;font-weight:600;color:#1e293b;">'
            + 'Generated Documents</h4>'
            + docsHtml + skippedHtml
            + '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:22px;">'
            + '<button type="button" id="ra9048SuccessStay" '
            + 'style="padding:9px 16px;border:1px solid #cbd5e1;background:#fff;color:#334155;'
            + 'border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;">Stay on this page</button>'
            + '<a href="' + absRecordsUrl + '" '
            + 'style="padding:9px 18px;background:#3b82f6;color:#fff;border-radius:6px;'
            + 'font-size:13px;font-weight:600;text-decoration:none;">Continue to Records →</a>'
            + '</div>'
            + '</div>';

        document.body.appendChild(overlay);

        const stayBtn = document.getElementById('ra9048SuccessStay');
        if (stayBtn) {
            stayBtn.addEventListener('click', function () {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            });
        }
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = (s == null) ? '' : String(s);
        return d.innerHTML;
    }
})();
