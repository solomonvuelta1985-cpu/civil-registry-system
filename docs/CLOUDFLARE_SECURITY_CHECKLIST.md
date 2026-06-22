# Cloudflare Security Checklist — cdrms.online

This checklist addresses the findings from the **Cloudflare Security Center** scan
(`SecurityInsights_20260622.csv`). Most findings are **dashboard or DNS settings** that
must be changed in the Cloudflare dashboard — they cannot be fixed in the iScan code.

> The one in-repo finding (**security.txt**) is already fixed — see [Section D](#d-securitytxt-done).

## Status (updated 2026-06-22)

| Finding | Status |
|---|---|
| Users without MFA (account 2FA) | ✅ Done — TOTP enabled |
| Domains without Always Use HTTPS | ✅ Done — toggle on |
| Domains without HSTS | ✅ Done — 6mo, includeSubDomains, preload off |
| SPF Record Errors | ✅ Done — `v=spf1 +mx ~all` published |
| DMARC Record Error | ✅ Done — `p=quarantine` + reporting published |
| Security.txt not configured | ✅ Done in repo — deploy to live origin pending |
| TLS encryption mode | ⚪ Left on **Full** (works; optional bump to Full strict) |
| Bot Fight Mode / AI Labyrinth / Turnstile | ⚪ Optional, not yet enabled |

**Priority order (original):** do **A** first (affects every visitor), then **C** (account security),
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

**Mail provider (confirmed 2026-06-22):** Jellyfish Hosting / cPanel
(MX = `mx1/mx2/mx3-hosting.jellyfish.systems`). Only cPanel/webmail sends mail for
this domain — no external sender (iScan does not send email).

**DONE — records published in Cloudflare DNS (verified live 2026-06-22):**

1. **SPF** — TXT on root (`@`):
   ```
   v=spf1 +mx ~all
   ```
   `+mx` authorizes the three Jellyfish MX servers; `~all` softfails everything else.
   (Tighten to `-all` later once you're confident no other source sends mail.)

2. **DMARC** — TXT at `_dmarc` (replaced the previous bare `v=DMARC1; p=none;`):
   ```
   v=DMARC1; p=quarantine; rua=mailto:mcronasbaggao@gmail.com; fo=1
   ```
   Suspicious mail → quarantine; daily aggregate reports go to the Gmail inbox.
   Can escalate to `p=reject` later after reviewing reports.

Verify any time with:
```
nslookup -type=txt cdrms.online
nslookup -type=txt _dmarc.cdrms.online
```

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
