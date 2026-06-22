# Cloudflare Security Checklist — cdrms.online

This checklist addresses the findings from the **Cloudflare Security Center** scan
(`SecurityInsights_20260622.csv`). Most findings are **dashboard or DNS settings** that
must be changed in the Cloudflare dashboard — they cannot be fixed in the iScan code.

> The one in-repo finding (**security.txt**) is already fixed — see [Section D](#d-securitytxt-done).

**Priority order:** do **A** first (affects every visitor), then **C** (account security),
then **B** (email), then optional toggles.

---

## A. HTTPS / TLS — Moderate (most findings)

Findings: *"Domains missing TLS Encryption"*, *"Domains without Always Use HTTPS"*,
*"Domains without HSTS"* — across `cdrms.online`, `www`, `iscan`, and the cPanel hosts.

**In Cloudflare dashboard → select the `cdrms.online` zone:**

1. **SSL/TLS → Overview** → set encryption mode to **Full (strict)**.
   - This clears all *"Domains missing TLS Encryption"* / *"Compliance violation"* rows.
   - Requires a valid cert on the origin. The iScan origin is behind a Cloudflare
     Tunnel (`http://localhost:8080`), so the tunnel already provides this — keep it on
     Full (strict) for the zone.

2. **SSL/TLS → Edge Certificates**:
   - Enable **Always Use HTTPS** → clears all *"Domains without Always Use HTTPS"* rows.
   - Enable **HSTS (HTTP Strict Transport Security)**:
     - Max Age: **12 months**
     - **Include subdomains:** On
     - **Preload:** optional (only enable once you're sure all subdomains are HTTPS-ready)
     - This clears all *"Domains without HSTS"* rows. (iScan also now sends this header
       at the origin via `.htaccess` — belt and suspenders.)

**Note on the cPanel/hosting subdomains** (`mail`, `whm`, `ftp`, `cpcalendars`,
`cpcontacts`, `webdisk`, `autoconfig`): these are flagged because they answer on their
own service ports rather than standard HTTPS. They are **lower priority** than the
public app hosts (`iscan`, `www`, `cdrms.online`). Enabling **Always Use HTTPS** + **HSTS**
at the zone level (step 2) covers them too; the "missing TLS" flag on service hostnames
is largely expected for cPanel infrastructure and can be left if those hosts are
admin-only.

---

## B. Email authentication — SPF + DMARC

Findings: *"SPF Record Errors"* (Moderate), *"DMARC Record Error detected"* (Low).
You have an MX record but no matching SPF/DMARC TXT records, so attackers could spoof
mail from `cdrms.online`.

**In Cloudflare dashboard → `cdrms.online` zone → DNS → Records → Add record (TXT):**

> ⚠️ **Confirm your real mail provider first.** Check the MX record under DNS. The
> `include:` value below depends on who sends your mail (cPanel/own server, Google
> Workspace, Microsoft 365, etc.). Publishing the wrong SPF will break legitimate mail.

1. **SPF** — one TXT record on the root (`@` / `cdrms.online`):
   ```
   Type: TXT   Name: @   Content: v=spf1 mx include:_spf.<your-mail-provider> ~all
   ```
   - If cPanel sends your mail, `mx` alone (or `+a +mx`) may suffice: `v=spf1 +mx ~all`.
   - Use `~all` (softfail) initially; tighten to `-all` once verified.

2. **DMARC** — one TXT record at `_dmarc`:
   ```
   Type: TXT   Name: _dmarc   Content: v=DMARC1; p=quarantine; rua=mailto:olmas.verona@outlook.fr; fo=1
   ```
   - Start with `p=none` to monitor (collect reports) if you want zero risk, then move
     to `p=quarantine` and eventually `p=reject`.
   - `rua=` is where aggregate reports are sent — change to whichever inbox you monitor.

Changes propagate within the DNS TTL (Cloudflare default ~5 min).

---

## C. Account hardening

1. **Users without MFA** (Moderate, `mcronasbaggao@gmail.com`):
   - Cloudflare dashboard → **My Profile → Authentication** → enable **Two-Factor
     Authentication** (TOTP app recommended).
   - Then **Account Home → Members** → consider **requiring 2FA** for all account
     members.

2. **Optional toggles** (Low — defense in depth, not required):
   - **Bot Fight Mode**: Security → Settings → toggle on. Watch results under
     Security → Events (labeled "Bot Fight Mode").
   - **AI Labyrinth**: Security → Settings → enable to deter unwanted AI crawlers.
   - **Turnstile**: create a widget under Turnstile if you want CAPTCHA-free bot
     protection on the iScan login form (would require a small code change to embed).

---

## D. security.txt — DONE (in repo)

Finding: *"Security.txt not configured"* (Low). **Fixed in code** — no dashboard action.

- Added [`.well-known/security.txt`](../.well-known/security.txt) (RFC 9116 format).
- `.htaccess` serves it as `text/plain`.
- After the next deploy, verify:
  ```
  curl -i https://iscan.cdrms.online/.well-known/security.txt
  ```
  Expect `200 OK` and `Content-Type: text/plain`.
- **Update the `Expires:` date** in that file before it lapses (currently 2027-06-22),
  and confirm the `Contact:` email is the right inbox for vulnerability reports.

---

## Verification after completing A–C

- **HTTPS/HSTS:** `curl -sI https://iscan.cdrms.online/` → should include
  `strict-transport-security: max-age=...`.
- **SPF:** `nslookup -type=txt cdrms.online` → should show the `v=spf1` record.
- **DMARC:** `nslookup -type=txt _dmarc.cdrms.online` → should show the `v=DMARC1` record.
- **Re-run** the Cloudflare Security Center scan; the addressed findings should move to
  resolved.
