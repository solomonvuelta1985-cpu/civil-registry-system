# A Narrative Report on the Development of the iScan-CRDMS

**iScan — Civil Registry Records Management System (CRDMS)**
For the Municipal Civil Registrar's Office, Municipality of Baggao
Period of Development: December 2025 – May 2026
Production URL: https://iscan.cdrms.online

---

Submitted by:           ______________________________
Institution / School:   ______________________________
Adviser:                ______________________________
Date:                   May 2026

---

## Table of Contents

1. Abstract
2. Introduction
3. Statement of the Problem
4. Purpose and Description
5. Significance of the Study
6. Objective of the Study
7. Scope of the Study
8. Definition of Terms
9. Highlighted Features of the System
10. System Architecture
11. Methodology of Development
12. Development Timeline
13. Conclusion

---

## 1. Abstract

The Civil Registry Records Management System (iScan-CRDMS) is a locally hosted, web-based information system developed for the Municipal Civil Registrar's Office (MCRO) of the Municipality of Baggao to address the long-standing operational, legal, and audit challenges associated with the manual handling of civil registry documents. Built on PHP 8.2, MariaDB 10, and Apache 2.4, with **zero Composer dependencies**, the system digitizes and securely manages four primary civil registry document types — Certificate of Live Birth, Certificate of Marriage, Certificate of Death, and the Application for Marriage License — together with petitions filed under Republic Act No. 9048 for the administrative correction of clerical or typographical errors.

The system's distinguishing feature is its **breadth of integrated automation within a single, dependency-light codebase**. It performs Optical Character Recognition (OCR) over scanned certificates using Tesseract.js across sixteen pre-mapped extraction fields per document type, complete with date normalization and confidence scoring; it ingests pages directly from an Epson DS-530 II flatbed scanner through a dedicated Python-Flask microservice; it computes SHA-256 integrity hashes over every uploaded PDF to prevent duplicate attachments and to provide forensic verification; it detects probable double registrations of the same vital event and presents them to the registrar in a side-by-side comparison modal that scores the match, highlights field-level discrepancies, and tracks correction status; it enforces a six-state approval workflow with a complete transition audit trail; it maintains an append-only activity log capturing user actions and originating IP addresses; and it secures access through CSRF protection, login rate limiting, hardened session cookies, HSTS, a Content-Security-Policy, and a device-lock allowlist whose entries are SHA-256 fingerprints derived from eleven browser characteristics.

The application is deployed on an on-premises Synology DS925+ NAS at the municipal hall and exposed to authorized users through a Cloudflare Tunnel — an outbound-only connection that requires no inbound port forwarding — preserving the local government unit's full custody of its citizens' data. Developed iteratively over approximately five months and **104 documented commits** from 30 December 2025 to 05 May 2026, iScan-CRDMS demonstrates that a small, framework-free PHP codebase can deliver an auditable, offline-capable, feature-rich records management platform suitable for production use in a Philippine LGU context.

---

## 2. Introduction

Civil registration is one of the foundational functions of local government in the Philippines. Under the supervision of the Philippine Statistics Authority (PSA), every Municipal Civil Registrar's Office (MCRO) is mandated to record, preserve, and issue certified copies of the country's vital events — births, marriages, and deaths — and to administer petitions filed under Republic Act No. 9048 for the correction of clerical or typographical errors in those records. These documents are not merely administrative artifacts; they are legal instruments that determine citizenship, inheritance, marital status, parental authority, and access to social services for every Filipino. Their accuracy, integrity, and continuous availability are therefore matters of significant public interest.

For decades, however, the day-to-day handling of civil registry documents in many Philippine municipalities has remained almost entirely paper-based. Records are manually encoded into bound logbooks, stored in steel cabinets that line the walls of the registrar's office, and retrieved by physically leafing through aging volumes whenever a citizen requests a copy. This traditional approach is not only slow and labor-intensive but also exposes priceless documents to very real risks: ink fading, paper acidification, water damage, fire, theft, pest infestation, and accidental misfiling. The same paper-bound workflow makes it disproportionately difficult to detect duplicate or "double" registrations, to track the history of edits and corrections, to enforce a uniform review-and-approval process, and to produce timely management information for executive decision-making. When citizens transact for certified true copies, they often experience long waiting times because clerks must manually locate decades-old documents — and when those documents have been damaged or misfiled, the consequences can range from delayed travel documents and pension claims to disputed inheritance and contested parentage.

The challenge is compounded by the limited connectivity and resource constraints faced by many local government units. Cloud-based commercial records-management products are often priced for organizations far larger than a typical Philippine municipality, and their reliance on continuous internet connectivity makes them ill-suited to areas where the network may go down for hours at a time. There is also a legitimate concern about data sovereignty: civil registry data is among the most sensitive personal information held by any government office, and many LGUs are understandably reluctant to entrust it to off-shore commercial providers.

The **iScan Civil Registry Records Management System (iScan-CRDMS)** was conceived as a direct response to these realities. Designed specifically for the Municipality of Baggao, the system replaces the manual logbook with a structured digital archive while preserving the legal and procedural conventions of the Civil Registrar's Office. It introduces an OCR-assisted data entry pipeline so that legacy paper documents can be captured efficiently; it enforces a six-state approval workflow so that no record reaches the archive without verification; it surfaces problems — such as duplicate PDFs and double registrations — that the manual process tends to hide; it produces management information through an analytics dashboard and an encoder leaderboard; and it does all of this on the **LGU's own Synology NAS**, with secure remote access through a Cloudflare Tunnel, ensuring that the municipality retains full custody of its citizens' data while still benefiting from modern web-application convenience. This narrative describes the rationale, scope, design decisions, and feature set of that system as it was built incrementally between December 2025 and May 2026.

---

## 3. Statement of the Problem

The development of iScan-CRDMS was driven by a number of concrete problems observed in the prevailing paper-based civil registry workflow of the Municipality of Baggao. These problems, which the system was specifically designed to address, may be stated as follows:

**3.1 Physical Deterioration and Risk of Catastrophic Loss.**
Civil registry documents are commonly stored as bound paper logbooks and loose certificates housed in steel cabinets within the municipal hall. Over time, these documents fade, yellow, become brittle, and accumulate stains from handling. They are also vulnerable to fire, flooding, typhoons, pest infestation, and accidental misfiling. The complete absence of a digital backup means that a single incident — a burst water pipe, an electrical fire, a major typhoon — could permanently destroy decades of irreplaceable records. The civic and legal consequences of such a loss are difficult to overstate: thousands of citizens could be left without proof of birth, marriage, or parentage.

**3.2 Slow and Error-Prone Retrieval.**
Manually searching through paper logbooks for a single record — especially when the requestor cannot supply an exact registry number — can consume substantial staff time. Searches by partial name, by parent name, by year of registration, or by barangay of birth are particularly slow because they require linear scanning of every potentially relevant volume. The lack of indexed, multi-token search makes it impossible to combine partial information (for example, a child's first name, a year, and a barangay) into a single efficient query. Citizens often wait days for documents that, in a digital system, could be retrieved in seconds.

**3.3 Undetected Double Registrations of the Same Vital Event.**
It is not unusual for the same vital event to be recorded more than once, whether through clerical error, a citizen re-applying without disclosing a prior registration, migration of the family between offices, or the late filing of a delayed registration that duplicates an earlier one. In a paper system, these duplicates are extraordinarily difficult to detect, yet they create serious legal complications when later discovered — including conflicting registry numbers, contradictory parental information, and ambiguity over which record is authoritative. iScan addresses this directly through an automated double-registration detector backed by a `record_links` table, with a side-by-side comparison modal that scores match probability, classifies discrepancies as critical or minor, and tracks correction status until resolution.

**3.4 Duplicate and Orphaned PDF Attachments.**
Once digitization begins, a parallel problem emerges: the same scanned PDF may inadvertently be uploaded against multiple records, wasting storage and corrupting the audit trail. iScan addresses this through SHA-256 content hashing — every uploaded PDF is hashed at the moment of save, and the hash is checked against all four certificate tables before the record is committed. A duplicate triggers an HTTP 409 Conflict response identifying the existing record, and the rejected file is automatically deleted to prevent orphans on disk.

**3.5 Lack of Accountability for Changes.**
In a manual system there is no reliable way to determine who edited what, when, or from where. Erasures, overwrites, and inserted lines all leave the same physical evidence — and even when staff initial their entries, that signature offers no defense against deliberate tampering. iScan introduces a comprehensive `activity_logs` table capturing every user-initiated action (create, update, archive, delete, restore, login, logout) with the actor's user ID, the action type, contextual details, the originating IP address, and a timestamp, backed by a strict policy that every database write is gated on authentication.

**3.6 Risk of Unauthorized Device Access.**
A web application accessible from any browser is also accessible to any *unauthorized* browser — including those of staff who have left the office, contractors who once had temporary access, or attackers who have obtained credentials through phishing. iScan responds with a device-lock allowlist secured by SHA-256 browser fingerprinting derived from eleven device characteristics (user agent, language, platform, CPU cores, screen resolution, color depth, pixel depth, timezone, touch points, canvas render, WebGL renderer). Only pre-registered workstations can reach the login page; unregistered devices are redirected to a hard-block page *before* credentials are even checked.

**3.7 Manual Handling of RA 9048 Petitions.**
Petitions for the administrative correction of clerical or typographical errors under Republic Act No. 9048 are themselves paper documents requiring the careful manual drafting of the petition itself, the order for publication or posting, the certificate of posting, and the certification of proof of filing. This drafting work is repetitive and error-prone, and it consumes a disproportionate share of the registrar's time. iScan introduces a dedicated RA 9048 module that auto-generates these documents from DOCX templates, supports both CCE (Clerical or Typographical Error) and CFN (Change of First Name) petitions, and provides in-browser PDF preview so the petitioner can verify the wording before printing.

**3.8 Limited Management Visibility.**
Without digital records, the MCRO cannot easily produce trend reports, demographic summaries, or productivity metrics. The municipal mayor and the registrar themselves operate without the routine analytics that any modern office expects: how many births this month? how many late registrations? which encoder has the highest output? iScan addresses this through an analytics dashboard with Chart.js trend lines, an encoder leaderboard ranking users by CREATE actions, late-registration badges flagging timeliness violations, and one-click export of filtered records to XLS or CSV.

---

## 4. Purpose and Description

### 4.1 Purpose

The **purpose** of the study is to design, develop, and deploy a Civil Registry Records Management System for the Municipality of Baggao that digitizes and secures the office's paper-based records workflow without surrendering the local government unit's data sovereignty. The system is intended to operate fully on the municipality's own infrastructure, to remain functional during periods of poor internet connectivity, to integrate cleanly with the office's existing scanning hardware, and to introduce a level of audit, security, and management visibility that the manual workflow cannot provide.

### 4.2 Description

iScan-CRDMS is a **server-side rendered web application** implemented in PHP 8.2 (with backwards compatibility down to PHP 7.4), running on Apache 2.4 and backed by a MariaDB 10 database. It uses **vanilla JavaScript and a custom CSS design system** with CSS variables — no Bootstrap, no Tailwind, no React, no Vue, no jQuery, no JavaScript framework of any kind. Visual rendering of attached PDFs is handled by PDF.js 3.11; analytics charts use Chart.js 4.4; user notifications use Notiflix 3.2.6; and OCR is performed in the browser by Tesseract.js v4 (with a server-side Tesseract binary path also available where installed). The repository contains zero Composer dependencies, and all third-party JavaScript libraries can be served from a local `assets/vendor/` directory rather than a CDN, supporting full offline operation.

The system manages four certificate types — Certificate of Live Birth (`certificate_of_live_birth`), Certificate of Marriage (`certificate_of_marriage`), Certificate of Death (`certificate_of_death`), and Application for Marriage License (`application_for_marriage_license`) — each backed by its own dedicated database table with full audit columns (`created_at`, `updated_at`, `created_by`, `updated_by`, `is_active`, `is_deleted`, `pdf_hash`, etc.). A separate module handles petitions under RA 9048, comprising the `ra9048_petitions`, `ra9048_corrections`, and `ra9048_supporting_documents` tables, and including the automated generation of the petition, posting, and certification documents from DOCX templates stored in `documents/templates/`.

A dedicated **Python-Flask scanner microservice**, running on port 18622, provides direct browser-to-scanner ingestion from an Epson DS-530 II flatbed scanner. It exposes endpoints such as `/scanner/status`, `/scanner/scan`, and `/scanner/test`, returning multi-page PDFs ready for upload — eliminating the manual round-trip of scanning to disk and then attaching from disk.

Access is controlled by a **role-based authorization model** with three roles — Administrator, Encoder, and Viewer — augmented by certificate-type granularity (e.g. an encoder may be authorized for births and deaths but not for marriages) and a dynamic permissions cache that reflects role changes immediately without forcing a logout. The system enforces CSRF tokens on all writes via `generateCSRFToken()` / `verifyCSRFToken()`; rate-limits login attempts to a configurable five tries per five minutes per `(username, IP)` pair; hardens session cookies for reverse-proxy operation with `HttpOnly`, `Secure`, and `SameSite=Lax`; and writes a security-event log with severity levels (LOW / MEDIUM / HIGH / CRITICAL).

The application is deployed on a **Synology DS925+ NAS** at the municipal hall and exposed to authorized users at `https://iscan.cdrms.online` through a **Cloudflare Tunnel** — an outbound-only secure tunnel that requires no inbound port forwarding, never exposes the NAS directly to the public internet, and adds Cloudflare's TLS termination, DDoS protection, and access controls on top of the application's own hardening.

---

## 5. Significance of the Study

The development of iScan-CRDMS is significant to several distinct stakeholder groups:

**5.1 To the Civil Registrar Staff of Baggao.**
The system replaces hours of manual logbook lookup with sub-second multi-token search across decades of records. OCR-assisted extraction reduces the keystrokes required to encode a paper certificate from hundreds to a handful of confirmations. Duplicate detection happens automatically at the moment of save, sparing staff the embarrassment and legal exposure of issuing conflicting certificates. The six-state workflow standardizes the verify-then-approve process across the entire team, and the device lock means staff can confidently access the system from approved workstations without worrying that a stolen password will compromise the office.

**5.2 To the Citizens of Baggao.**
Faster retrieval translates directly into shorter waiting times for the issuance of certified copies. The OCR-assisted late-registration workflow means that delayed registrations — common in rural barangays where families could not always reach the office in time after a birth or death — can be encoded and processed faster. The RA 9048 module provides a more reliable petition-tracking experience, with auto-generated supporting documents and a petition status visible to staff at every stage.

**5.3 To the LGU Executive Leadership.**
The system surfaces previously invisible information through an institutional admin dashboard. Monthly registration trends, demographic summaries, the distribution of records across workflow states, and an **encoder leaderboard** ranking users by CREATE actions all support evidence-based management decisions — from staffing allocations to performance reviews to municipal budgeting.

**5.4 To Auditors, the Philippine Statistics Authority (PSA), and Other Oversight Bodies.**
The append-only `activity_logs` and `security_logs` tables, the per-record version history, the SHA-256 PDF integrity hashes, and the `workflow_logs` table together provide a level of forensic accountability that is simply not achievable in a paper workflow. Every change to a record can be traced to an authenticated user, an IP address, and a precise timestamp; every PDF can be verified to be byte-identical to the version originally archived; and every approval decision is permanently recorded.

**5.5 To the LGU's Information-Technology and Data-Governance Posture.**
The choice to deploy on an on-premises Synology NAS — rather than on a third-party cloud — preserves data sovereignty and avoids the vendor lock-in that comes with proprietary SaaS records-management products. The Cloudflare Tunnel layer provides authenticated remote access without exposing the NAS to the open internet, and the offline-capable design ensures the office can continue serving citizens even if the internet uplink fails.

**5.6 To the Academic and Developer Community.**
iScan stands as a reference implementation of a **zero-Composer-dependency, offline-capable, LGU-grade records management system**. Its source organization, security patterns, and deployment recipes can be studied and adapted by other Philippine municipalities — and by computing students researching public-sector digitization — facing the same challenges. The codebase deliberately avoids large frameworks in favor of explicit, readable PHP, making it suitable as a teaching example.

---

## 6. Objective of the Study

### 6.1 General Objective

To **design, develop, and deploy a Civil Registry Records Management System (iScan-CRDMS) for the Municipality of Baggao** that digitizes and secures civil registry documents from the moment of capture through long-term archival, while preserving the municipality's full ownership of its data.

### 6.2 Specific Objectives

In support of the general objective, the study sought to:

1. **Implement complete CRUD operations** (create, read, update, archive, soft-delete, hard-delete) for the four civil registry certificate types and for RA 9048 petitions, with full audit columns, version history, and a soft-delete (Trash) layer on every record.
2. **Integrate Optical Character Recognition** at two layers — server-side via the Tesseract binary as the primary path, and browser-side via Tesseract.js v4 as a fallback — extracting up to sixteen pre-mapped fields per certificate type with automatic date normalization (e.g. `OCTOBER 17, 1999` → `1999-10-17`) and per-field confidence scoring.
3. **Provide direct-from-scanner ingestion** from the office's Epson DS-530 II flatbed scanner via a Python-Flask microservice on port 18622, eliminating the need to save files to disk before uploading and supporting multi-page PDF output.
4. **Enforce role-based access control** (Administrator, Encoder, Viewer) with certificate-type granularity, augmented by a device-lock allowlist secured through SHA-256 browser fingerprinting of eleven browser characteristics.
5. **Detect and surface data-quality problems** that the paper workflow tends to hide, specifically (a) duplicate PDF attachments, via SHA-256 content hashing checked across all four certificate tables, and (b) double registrations of the same vital event, via an automated detector backed by a `record_links` table with match scoring and field-level discrepancy classification.
6. **Implement a six-state workflow engine** — Draft → Pending Review → In Review → Approved → Rejected / Archived — with a complete transition audit trail in the `workflow_logs` table, so that no record is finalized without verification.
7. **Ensure offline operation and on-premises data sovereignty** by deploying on a Synology DS925+ NAS, exposing the application through an outbound-only Cloudflare Tunnel, and providing a fully local `assets/vendor/` fallback so the system does not require the internet to function.
8. **Deliver management information** through an institutional admin dashboard with Chart.js visualizations, an encoder leaderboard tracking CREATE actions per user, late-registration badges using configurable thresholds, and certificate-records export to XLS and CSV formats with multi-criteria filtering.
9. **Harden the system against common web threats** with CSRF tokens, login rate limiting, hardened session cookies, HSTS, a Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, and a Permissions-Policy blocking the camera, microphone, and geolocation APIs.
10. **Automate the production of RA 9048 petition paperwork** including the petition itself, the order for publication, the certificate of posting, and the certification of proof of filing, generated from DOCX templates and rendered to in-browser PDF preview before printing.

---

## 7. Scope of the Study

### 7.1 In Scope

The study and the resulting system specifically cover:

- The four primary civil registry document types: **Certificate of Live Birth, Certificate of Marriage, Certificate of Death, and Application for Marriage License** — each with its own dedicated table, dedicated entry form, and dedicated viewer.
- **RA 9048 petitions** for the administrative correction of clerical or typographical errors, including both the CCE (Clerical or Typographical Error) and CFN (Change of First Name) petition types, and the automated generation of the petition, posting, and certification documents from DOCX templates.
- **OCR-assisted data extraction** with mandatory manual encoder verification — the system does not auto-save OCR output without human review, in keeping with the legal sensitivity of registry data.
- **Direct-from-scanner ingestion** from an Epson DS-530 II flatbed scanner via the Python-Flask scanner microservice.
- **PDF management**, including upload, attachment, integrity hashing (SHA-256), de-duplication checked across all four certificate tables, automatic backup versioning before replacement, integrity scanning, and bulk operations such as restore and cleanup of orphaned files.
- **A six-state approval workflow** (Draft → Pending Review → In Review → Approved → Rejected → Archived) with a full transition audit trail.
- **Activity logging, security event logging, and registered-device management**, all with append-only semantics suitable for audit.
- **Multi-token search** with two-pass strict-then-fuzzy matching across name fields, registry numbers, dates, and place fields.
- **Double-registration detection and management**, including a side-by-side comparison modal with match scoring and discrepancy classification.
- **A Family Relations module** that aggregates siblings, parents' marriage, and parent deaths for any given birth record.
- **An analytics dashboard** with Chart.js trend lines, demographic summaries, an encoder leaderboard, and late-registration badges.
- **Export to XLS and CSV** with multi-criteria filtering, suitable for reporting to PSA and to the municipal mayor's office.
- **Calendar and notes** functionality for attaching reminders and free-text notes to records.
- **Single-municipality deployment** for the Municipality of Baggao MCRO, on a Synology DS925+ NAS, with remote access via Cloudflare Tunnel at `https://iscan.cdrms.online`.
- **Both XAMPP / localhost (Windows) and Synology / Linux runtime targets**, so that the same codebase can be used for local development on a Windows workstation and for production on the NAS, with no code changes required.

### 7.2 Out of Scope

The following items are explicitly outside the scope of the present study:

- **Inter-municipality data sharing or real-time synchronization with PSA's national civil registration system.** iScan is a standalone municipal system; PSA reporting remains a manual export-and-submit workflow.
- **A native mobile application.** The system is mobile-friendly through responsive web design, but no Android or iOS native app is delivered.
- **A public-facing certificate ordering or payment portal.** Citizens still transact in person at the MCRO; iScan is a back-office system for staff.
- **Biometric authentication (fingerprint, facial recognition).** User identity is established by username/password, hardened by rate limiting, CSRF protection, session hardening, and device-lock allowlisting.
- **Automated data migration from external legacy systems** other than the existing paper records of the Baggao MCRO. Bulk import is supported through a batch-upload module, but bespoke connectors to other municipalities' databases are not provided.
- **Automatic translation between languages.** The user interface is English-only, although free-text fields (notes, names, places) accept Filipino characters.

---

## 8. Definition of Terms

For the purposes of this report, the following technical and domain terms are defined as follows:

- **Activity Log** — An append-only record stored in the `activity_logs` table that captures every user-initiated action (login, create, update, archive, delete, restore) together with the originating IP address. Used for audit and forensic review.
- **Apache 2.4** — The web server software used to serve iScan, deployed via XAMPP on Windows during development and via Synology Web Station in production.
- **Bcrypt** — The adaptive password-hashing function used to store user passwords in the `users.password` column.
- **CCE (Clerical or Typographical Error)** — The class of clerical errors correctable by administrative petition under RA 9048, e.g. misspellings of names, incorrect day or month of birth.
- **CFN (Change of First Name)** — The petition type under RA 9048 that allows the administrative change of an entry's first name (subject to specific legal grounds).
- **Cloudflare Tunnel** — An outbound-only secure tunnel that connects the on-premises Synology NAS to the public hostname `iscan.cdrms.online` without requiring inbound port forwarding or exposing the NAS directly to the internet.
- **CRDMS** — Civil Registry Records Management System; the class of information systems to which iScan belongs.
- **CSP (Content-Security-Policy)** — An HTTP response header that restricts which sources of script, style, font, and image the browser will load, mitigating cross-site scripting attacks.
- **CSRF (Cross-Site Request Forgery)** — A class of web attack that iScan blocks via per-session anti-CSRF tokens checked on every state-changing request.
- **Device Lock** — A security mechanism that restricts system access to a list of pre-registered browsers, identified by a SHA-256 fingerprint of selected browser characteristics. Unregistered devices are denied at the authentication layer, before credentials are even checked.
- **Double Registration** — The recording of the same vital event (typically a birth) more than once in the civil registry. iScan automatically detects probable duplicates and presents them in a side-by-side comparison modal for adjudication.
- **Encoder Leaderboard** — A productivity dashboard that ranks users by the number of CREATE actions recorded against their account in the activity log.
- **HSTS (HTTP Strict-Transport-Security)** — An HTTP response header that instructs the browser to use HTTPS for all future connections to the site, preventing protocol-downgrade attacks.
- **iScan / iScan-CRDMS** — The Civil Registry Records Management System developed for the Municipality of Baggao and described in this report.
- **Late Registration** — A vital event registered after the legal deadline (typically 30 days for births and deaths, 15 days for marriages). iScan flags such records with a visible badge.
- **MariaDB** — The open-source MySQL-compatible relational database used by iScan, version 10.
- **MCRO** — Municipal Civil Registrar's Office; the local-government office responsible for civil registration in a municipality.
- **OCR** — Optical Character Recognition; the conversion of scanned document images into machine-readable text. iScan uses both server-side Tesseract and browser-side Tesseract.js v4.
- **PDF Integrity Hash** — A SHA-256 fingerprint computed over the bytes of an uploaded PDF, used both to detect duplicate uploads (HTTP 409) and to verify that an archived PDF has not been altered.
- **PDF.js** — The Mozilla open-source library that iScan uses to render PDF pages directly inside the browser without requiring a plug-in.
- **PSA** — Philippine Statistics Authority; the national agency responsible for civil registration policy, statistics, and the issuance of authenticated copies of vital records.
- **RA 9048** — Republic Act No. 9048 of the Philippines (as amended by RA 10172), permitting the City or Municipal Civil Registrar to administratively correct clerical or typographical errors in civil registry documents without a judicial order.
- **Rate Limiting** — A control that restricts the number of login attempts per username and IP within a configurable window, blocking brute-force attacks.
- **Soft Delete / Trash** — A status flag (e.g. `is_active = 0`, `is_deleted = 1`) that marks a record as removed from normal views without physically deleting the row, preserving it for possible restoration and for audit.
- **Synology NAS** — A network-attached storage server (model DS925+ in this deployment) hosting the iScan application, MariaDB database, and PDF archive on the LGU's own premises.
- **Tesseract** — The open-source optical character recognition engine, originally developed at HP Labs and now maintained by Google. iScan uses two variants: the native binary on the server and the JavaScript port (Tesseract.js) in the browser.
- **Workflow State** — One of six lifecycle states applied to each certificate record: Draft, Pending Review, In Review, Approved, Rejected, Archived.
- **XAMPP** — The Windows distribution of Apache, MariaDB, and PHP used during local development.

---

## 9. Highlighted Features of the System

This section presents an organized inventory of the features delivered by iScan-CRDMS. Each feature is described in terms of what it does, why it matters, and where in the codebase it lives.

### 9.1 Authentication and Authorization

iScan implements a layered authentication and authorization model centered on three roles — **Administrator, Encoder, and Viewer** — stored in the `users.role` column. Permissions are not hard-coded against roles; instead a separate `permissions` table holds granular permission names (e.g. `birth.create`, `marriage.archive`, `admin.view`) and a `role_permissions` junction table maps roles to the permissions they hold. The helper function `hasPermission($name)` in `includes/auth.php` checks the active session's cached permission list first and falls back to a database query, which makes permission checks both fast and authoritative.

When an administrator changes a role's permissions, **the change takes effect on the next request** without requiring affected users to log out — the dynamic permission cache is validated on every request and refreshed when its underlying tables change. Passwords are stored as **bcrypt hashes** in `users.password`. Session cookies are configured with `HttpOnly`, `Secure` (when HTTPS is detected), and `SameSite=Lax` to remain compatible with reverse-proxy operation behind Cloudflare. Every state-changing request must carry a CSRF token: forms include the `csrfTokenField()` helper, AJAX requests send the token via the `X-CSRF-TOKEN` header, and the server verifies the token through `verifyCSRFToken()` in `includes/security.php`.

### 9.2 Login Rate Limiting and Brute-Force Protection

Login attempts are rate-limited per `(username, IP)` pair through the `rate_limits` table. The default policy is **five failed attempts per five-minute window** (configurable via `MAX_LOGIN_ATTEMPTS` and `RATE_LIMIT_WINDOW` in the `.env` file). When the limit is exceeded, the user is shown a countdown timer rather than a generic "wrong password" message, and the event is logged to `security_logs` with severity `HIGH` and event type `RATE_LIMIT_EXCEEDED`. This control alone defeats casual credential-stuffing attacks even before the device-lock layer engages.

### 9.3 Device-Lock Security

The most distinctive security feature of iScan is its **device-lock allowlist**, implemented through the `registered_devices` table. The lock works as follows:

- When the login page loads, a JavaScript module (`assets/js/device-fingerprint.js`) collects eleven browser characteristics — User-Agent, language, platform, CPU cores (`navigator.hardwareConcurrency`), screen resolution, color depth, pixel depth, timezone, touch points, a canvas render hash, and the WebGL renderer string. These are concatenated into a stable signature and hashed with SHA-256 into a 64-character hex fingerprint.
- The fingerprint is sent with the login request. Before credentials are even examined, `includes/device_auth.php` queries `registered_devices` for an active row with that hash. If none is found, the browser is redirected to `public/device_blocked.php` and never reaches the password-validation step.
- Administrators register a new device through `admin/devices.php`, which calls `api/device_save.php` (Admin role + CSRF required). They can revoke a device via `api/device_delete.php` with `action=revoke`, which sets `status=Revoked` without deleting the row, preserving the audit trail.
- On every successful login, `updateDeviceLastSeen()` updates the `last_seen_at` and `last_seen_ip` columns so that administrators can review which workstations are actively in use.

This is a defense-in-depth feature: even if a password is stolen, the attacker cannot log in from an unregistered laptop because the request never reaches the credential check.

### 9.4 Optical Character Recognition (OCR)

iScan provides a sophisticated OCR pipeline that turns a scanned certificate into pre-populated form fields. **Sixteen extraction fields** are mapped per certificate type — for the Certificate of Live Birth, these include the registry number, the child's first / middle / last name, sex, date of birth, place of birth, type of birth (Single / Twin / Triplets), birth order, and the parents' first / middle / last names. The browser-side path uses **Tesseract.js v4**, with the OCR worker initialized once and reused across pages to avoid expensive model reloads. Multi-page PDFs are first converted to images via PDF.js, then handed page-by-page to the OCR worker.

A **page-range selector** (`docs/OCR_PAGE_SELECTOR.md`) lets the encoder choose which pages to process, which is essential when a PDF contains multiple certificates or includes blank back pages. Results are accompanied by a **per-field confidence score** displayed as a percentage (0–100), so the encoder can quickly identify low-confidence extractions for closer review. The OCR processor performs **smart label filtering** to ignore form labels like `(First)`, `(Middle)`, and `(Last)`, and **automatic date normalization** so that an OCR'd `OCTOBER 17, 1999` becomes the canonical `1999-10-17`. A server-side path using the Tesseract binary is also supported via `includes/TesseractOCR.php` for installations where a binary is available; the cross-platform path detection covers Linux `/usr/bin`, Synology Entware `/opt/entware/bin`, and Windows `C:\Program Files\Tesseract-OCR`.

### 9.5 Direct-from-Scanner Ingestion (Python-Flask Microservice)

A dedicated Python-Flask microservice — the `scanner_service` — runs on port 18622 and bridges the office's **Epson DS-530 II flatbed scanner** to the browser. It exposes three endpoints: `/scanner/status` for health checks, `/scanner/test` for a one-page diagnostic scan, and `/scanner/scan` for a full multi-page document scan. The service depends on `flask-cors`, `Pillow`, `img2pdf`, `reportlab`, and `python-sane`, and outputs scanned documents directly as **multi-page PDFs** ready for upload — eliminating the manual round-trip through a "Scan to Folder" workflow. On Windows it runs via `start_scanner.bat`; on the Synology NAS it runs as a systemd service via `start_scanner.sh`.

### 9.6 PDF Integrity and Duplicate Prevention

Every uploaded PDF is hashed with SHA-256 at the moment of save through `compute_file_hash()` in `includes/functions.php`, and the resulting 64-character hex digest is stored in the `pdf_hash` column of the relevant certificate table. The function `check_pdf_duplicate()` then queries all four certificate tables for any active record carrying the same hash. If a match is found, the new request is rejected with **HTTP 409 Conflict** and a message identifying the existing record (e.g., "This PDF is already attached to Certificate of Live Birth Registry No. 2025-00123"); the rejected file is deleted from disk by `delete_file()` to prevent orphans. On legitimate updates, the prior PDF is moved to a backup folder via `backup_pdf_file()`, with a timestamp suffix, so it can be restored if the new attachment is later found to be wrong. An admin tool performs **bulk integrity scanning**, **bulk restore** from backups, and **cleanup of orphaned files** that are present on disk but no longer referenced by any record.

### 9.7 Six-State Workflow Engine

Every record passes through a six-state lifecycle: **Draft → Pending Review → In Review → Approved → Rejected → Archived**. Encoders create records in Draft and submit them for Pending Review; administrators verify and either Approve or Reject the record (with mandatory rejection notes stored in `workflow_logs.rejection_reason`); approved records can be Archived for long-term storage. Every transition is recorded in the `workflow_logs` table together with the actor's user ID, the from-state, the to-state, an optional rejection reason, and a timestamp. The dashboard at `public/workflow_dashboard.php` shows the count of records in each state and supports drag-and-drop card transitions where the user has the necessary permission.

### 9.8 Append-Only Activity and Security Logs

Two separate audit tables provide forensic accountability. The `activity_logs` table (columns `id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) records every user-initiated operation. The `security_logs` table (columns `id`, `event_type`, `severity`, `user_id`, `ip_address`, `user_agent`, `details`, `created_at`) records authentication and security events at four severity levels — `LOW`, `MEDIUM`, `HIGH`, `CRITICAL` — covering events such as `LOGIN_SUCCESS`, `LOGIN_FAILED`, `CSRF_VALIDATION_FAILED`, `RATE_LIMIT_EXCEEDED`, `SUSPICIOUS_ACTIVITY`, and `DEVICE_BLOCKED`. The application's policy is that **every database write is gated on authentication** and emits exactly one activity-log entry, so the log is a complete record of all user-initiated change.

### 9.9 Multi-Token Search (Strict-then-Fuzzy)

The records search at `public/advanced_search.php` implements a two-pass algorithm. The **strict pass** filters by exact matches on indexed fields — registry number, certificate type, date ranges, place fields — and only returns records that match every supplied criterion (AND-across-tokens). If the strict pass returns fewer than five results, a **fuzzy pass** runs `LIKE` queries against the name fields (first / middle / last names of the subject, the spouse, or the parents). This two-pass design gives users sharp results when their input is precise but still finds candidates when input is partial or misspelled.

### 9.10 Double Registration Detection and Comparison Modal

When two records appear to describe the same vital event, iScan creates a row in the `record_links` table linking them as `primary_certificate_type/id` (the original) and `duplicate_certificate_type/id` (the suspected duplicate). The link carries a `match_score` (0–100), a `discrepancies` JSON column listing every field that differs (with both values), flags for `has_discrepancies` and `needs_correction`, a `correction_status` enum, and the `linked_by` user. The **comparison modal** — refined through six iterations between April and May 2026 — renders the two records side by side with critical discrepancies highlighted in red, minor discrepancies in amber, missing-data fields explicitly labeled, and a verdict line at the top that summarizes the match score and action required. Users with the `unlink` permission can dissolve a false positive (with a reason prompt), and CSRF protection is enforced on every link/unlink action.

### 9.11 Family Relations Module

The Family Relations module aggregates **siblings, parents' marriage record, and parent death records** for any given birth certificate. Given a birth-record ID, the API endpoint `api/family_relations.php` returns a JSON object containing: an array of sibling birth records (other births with matching mother and father names, excluding records already linked as duplicates); the parents' marriage record (searched against `certificate_of_marriage` by husband/wife match); and the parents' death records, if any (searched against `certificate_of_death`). The module is read-only; it writes nothing. Helper functions such as `fr_get_linked_birth_ids()`, `fr_find_siblings()`, `fr_find_marriage()`, and `fr_find_deaths()` live in `includes/functions.php`. A strict-and-fuzzy search variant is available so that minor spelling differences in parent names do not hide siblings from the user.

### 9.12 RA 9048 Petition Module

The RA 9048 module handles administrative correction of clerical and typographical errors. It supports the **CCE-minor**, **CCE-10172**, and **CFN** petition types, each backed by its own subset of DOCX templates stored in `documents/templates/`. On "Verify & File", the system uses `DocxTemplateProcessor` (introduced in commit `838fb97`) to substitute placeholders into the appropriate templates and write the generated documents to `uploads/ra9048/{petition_id}/`. The processor includes **placeholder normalization that handles tokens split across runs** (commit `9d8d5e7`) — a non-trivial DOCX issue where Word may break a `{{registry_no}}` placeholder across multiple text runs in the underlying XML. An **in-browser PDF preview** (commit `895b3e7`) renders the generated documents directly without requiring the user to download Word; on the Synology NAS, a Docker LibreOffice container performs the DOCX → PDF conversion. The petition data is stored in `ra9048_petitions`, with child rows in `ra9048_corrections` (one per field being corrected, capturing old and new values) and `ra9048_supporting_documents` (one per supporting attachment).

### 9.13 PDF Folder Organization (Year + Last Name)

To keep the on-disk PDF archive navigable as it grows into the tens of thousands of files, iScan organizes uploads into a **`uploads/{type}/{YEAR}/{LAST_NAME}/{filename}.pdf`** layout. The year is derived in priority order from (1) the event date on the record (e.g. child's date of birth), (2) the registry-number prefix expanded with a configurable pivot (`YY > pivot → 19YY`, else `20YY`), or (3) defaulted to a year-less fallback when neither is available. The last name is normalized through `folder_safe_last_name()` (uppercase, spaces → underscores, punctuation removed, defaulting to `UNKNOWN`). A reorganization helper at `includes/reorganize_uploads.php` can rebuild the folder structure for a legacy upload directory, and a folder-browser API at `api/folder_browser.php` returns a directory listing with file counts per folder for the admin's `folder browser` UI.

### 9.14 PDF Backup, Restore, and Bulk Operations

Whenever a record's PDF attachment is replaced, the existing file is moved to `uploads/{type}/backup/` with a timestamp suffix (e.g. `cert_xxx_backup_20260405_103022.pdf`). This means **no PDF is ever destroyed by an update** — it is only superseded — so an erroneous upload can always be reversed. Migration `025_pdf_backup_extensions.sql` adds backup tracking columns; the most recent commit on the main branch (`7e13c41`, May 5, 2026) introduces **bulk operations and deduplication** to the PDF Backup Management screen, allowing administrators to restore many backups at once, scan for duplicate backups, and clean up orphaned backup files in a single action.

### 9.15 Late-Registration Detection

Civil registry law sets deadlines for the registration of vital events: typically **30 days** for births and deaths and **15 days** for marriages. iScan computes the difference between the event date and the registration date, and where it exceeds the relevant threshold, it returns an `is_late = true` flag together with the exact number of days delayed and a human-readable label such as "Registered 47 days late." The badge is rendered prominently on the records viewer (commit `ceeec3d`, "feat: add late registration detection and display badges for timely/late registrations"), and the same flag is available to filtered exports and to the analytics dashboard for late-registration trend reporting.

### 9.16 Analytics Dashboard and Encoder Leaderboard

`public/analytics_dashboard.php` provides at-a-glance management information. Total counts by status (Active / Archived / Deleted), single-vs-multiple birth statistics, registrations this month, the workflow-state distribution (rendered as a Chart.js pie chart), and a monthly-registration trend (rendered as a Chart.js line chart) all appear above the fold. Below them, the **encoder leaderboard** — added in commit `57f1109` — displays a ranked table of users by CREATE actions, with absolute counts and percentage-of-total, computed by a `GROUP BY` against `activity_logs`. Access is restricted to users with the `admin_view` permission.

### 9.17 Reports and Export to XLS / CSV

`public/export.php` supports certificate-specific exports in **XLS** (HTML-table format readable by Excel) and **CSV** (with a UTF-8 BOM header so that Filipino characters render correctly). Filters include search text, date ranges (from / to on both event date and registration date), place filters, and status filters; the resulting file is named with the certificate type and the current date (e.g. `Birth_Records_2026-05-05.xls`). The encoder who created each row is included in the export so that audit and productivity questions can be answered directly from the file.

### 9.18 Calendar and Notes

The `calendar_notes` table (columns `id`, `user_id`, `record_type`, `record_id`, `note_text`, `note_date`, `is_reminder`, `reminder_sent_at`, `created_at`) lets staff attach free-text notes and reminder dates to any record. Reminders surface on the relevant record's viewer page; a setup wizard ensures the table is created on first run.

### 9.19 HTTP Security Headers and Apache-Level Hardening

Beyond application-level controls, iScan deploys **defense in depth at the HTTP and Apache layers**. The root `.htaccess` blocks direct access to sensitive files (`.env`, `.log`, `.sql`, `.sh`, `.bat`, `.md`, `.gitignore`) and directories (`includes/`, `database/`, `logs/`, `scanner_service/`); it disables directory indexing with `Options -Indexes`; and it sets PHP runtime values such as `upload_max_filesize 20M`, `post_max_size 25M`, `max_execution_time 120s`, `session.gc_maxlifetime 3600s`, and `session.cookie_httponly 1`.

The Apache headers set at the `.htaccess` and PHP layers include `X-Frame-Options: SAMEORIGIN` (clickjacking defense), `X-XSS-Protection: 1; mode=block` (legacy XSS defense), `X-Content-Type-Options: nosniff` (MIME-sniffing defense), `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (disables camera, microphone, and geolocation), `Strict-Transport-Security` (HSTS with a one-year max-age, auto-detected on HTTPS), and a configurable `Content-Security-Policy` that restricts script, style, and font sources. The `X-Powered-By` header is removed for obscurity.

### 9.20 Offline Mode and Asset Vendoring

iScan supports a fully **offline mode** controlled by `OFFLINE_MODE=true` in `.env`. When enabled, the `asset_url()` helper in `includes/asset_urls.php` returns a path under `assets/vendor/` instead of a CDN URL — for libraries such as PDF.js, Chart.js, Tesseract.js, Notiflix, and Font Awesome. A `download_assets.sh` script populates the vendor directory in one pass; the directory itself is gitignored to keep the repository size manageable. This means the system is functional even when the municipal hall's internet connection is down — including login, record viewing, OCR, and document generation.

### 9.21 Cross-Platform Compatibility

The same codebase runs unchanged on **Windows + XAMPP** (PHP 8.2, Apache 2.4, MariaDB 10) for development and on **Synology DSM 7 + Web Station** (PHP 8.2, Apache 2.4, MariaDB 10) for production. Path-sensitive features detect their environment automatically: the Tesseract OCR wrapper (`includes/TesseractOCR.php`) probes for the binary in `/opt/entware/bin/tesseract` (Synology), `/usr/bin/tesseract` (generic Linux), and `C:\Program Files\Tesseract-OCR\tesseract.exe` (Windows). The scanner service has both `start_scanner.bat` (Windows) and `start_scanner.sh` (Linux). Configuration is centralized in `includes/config.php` and `.env`, with `.env.example` (development), `.env.synology` (production NAS), and `.env` (active) providing template-and-override semantics.

---

## 10. System Architecture

The architecture of iScan-CRDMS is deliberately layered and explicit, in keeping with the project's preference for readable PHP over heavy framework abstraction.

```
                  +----------------------------+
                  |   Cloudflare Tunnel        |
                  |   (iscan.cdrms.online)     |
                  +-------------+--------------+
                                |  outbound only
                                v
                  +----------------------------+
                  |   Apache 2.4 + .htaccess   |
                  |   (security headers,       |
                  |    file/dir blocks)        |
                  +-------------+--------------+
                                |
        +-----------+-----------+-----------+-----------+
        |           |                       |           |
        v           v                       v           v
   +---------+ +---------+             +---------+ +-------------+
   | public/ | | admin/  |             |  api/   | | scanner svc |
   | (UI)    | | (UI)    |             | (REST)  | | (Flask:18622)|
   +----+----+ +----+----+             +----+----+ +------+------+
        |           |                       |             |
        +-----+-----+                       |             |
              v                             v             v
     +-----------------+         +-------------------+ +-----------+
     |   includes/     |         |    includes/      | | Epson     |
     |   config, auth, |         |    auth, security,| | DS-530 II |
     |   security,     |         |    functions      | +-----------+
     |   functions     |         +-------------------+
     +--------+--------+                  |
              |                           |
              v                           v
     +----------------------------------------------+
     |             MariaDB 10                        |
     |  certificates / users / audit / workflow      |
     +----------------------------------------------+
              |
              v
     +----------------------------------------------+
     |   Synology DSM 7 file system                  |
     |   /volume1/web/iscan/   /uploads/             |
     +----------------------------------------------+
```

The key layers are: **Cloudflare Tunnel** (outbound-only ingress), **Apache 2.4** with hardened `.htaccess` (security headers, file blocks, runtime limits), the **PHP application layer** (split into `public/` for end-user pages, `admin/` for administrator pages, and `api/` for REST endpoints, all sharing the same `includes/` libraries), the **Python-Flask scanner microservice** running independently on port 18622, the **MariaDB 10** database holding certificates / users / audit / workflow tables, and the **Synology DSM 7 file system** holding the on-disk PDF archive under `uploads/`.

---

## 11. Methodology of Development

The development of iScan-CRDMS followed an **incremental, commit-driven methodology** that fits cleanly between Agile/XP and the Modified Waterfall typically seen in Philippine IT capstone projects. Rather than producing one large monolithic deliverable at the end, the developer worked in short cycles — typically a single feature or bug fix per cycle — committing each completed unit of work to version control with a descriptive message in conventional-commits style (`feat:`, `fix:`, `docs:`, `refactor:`).

The five phases of the cycle, repeated for every feature, were:

1. **Identify** the next user pain point or requirement, often surfaced through live use of the system in the MCRO during a deployment cycle.
2. **Design** the change — choosing the smallest feasible code path, preferring extension of existing helpers in `includes/functions.php` over introducing new abstractions.
3. **Implement** the change in PHP, vanilla JavaScript, and SQL, including a dedicated database migration file in `database/migrations/` where the schema changed.
4. **Verify** the change manually against the running system, both on the developer's Windows + XAMPP setup and (for deployment-sensitive changes) on the production Synology NAS.
5. **Commit** the change with a descriptive conventional-commits message and, for non-trivial features, a corresponding documentation file in `docs/`.

This methodology is visible in the project's git log: 104 commits across approximately five months, each scoped to a single concern. It also explains why the system has accumulated such an extensive feature set without ever undergoing a "big bang" rewrite — every feature was introduced as a small, reversible increment on a working base.

The source materials for the system's design include the PSA's Vital Statistics Manuals; Republic Act No. 9048 (and its amending RA 10172) governing administrative correction; the Data Privacy Act of 2012 (RA 10173) governing personal-information handling; OWASP's Top 10 web-security guidance for the security model; and Mozilla's Web Security Guidelines for the HTTP-header policy.

---

## 12. Development Timeline

The system was built incrementally over approximately five months and 104 documented commits. The work can usefully be grouped into four phases.

### 12.1 Phase 1 — Foundation (December 2025 – January 2026)

Phase 1 established the project skeleton. The initial commit (`37fac31`, 30 December 2025) introduced the core Civil Registry System with OCR capabilities. The admin sidebar with authentication checks (`87a8cee`) and the user-management page (`87a8cee`) followed within days. The marriage-license module — navigation, form UI, soft-delete and hard-delete semantics — was added across `9fc7609` and `4c602e5`. Form usability improvements followed: skeleton loading (`10163ab`), pagination cleanup (`1239123`), reports filtering and sorting (`ce693cf`), and the introduction of the place-type field for births (`98330fe`). By the end of Phase 1, the core data model, the admin shell, the four certificate forms, and the authentication boundary were all in place.

### 12.2 Phase 2 — Core Hardening (March 2026)

Phase 2 was a concentrated effort to bring the system from a functional prototype to a production-credible platform. The documentation was reorganized into a dedicated `/docs/` directory (`5c80b2d`); the PDF-hashing migration and migration runner were introduced (`0e0cb62`); the security model was substantially upgraded with device management, security logs, and a hardened login (`cd89916`); all certificate forms, APIs, and shared styles were updated together for consistency (`043dac6`); the sidebar, dashboard, records viewer, and page layouts were redesigned (`88bbf7b`); the PDF serving, backup management, and integrity system were introduced (`393f854`); the calendar events, notes, and setup wizard arrived (`fc1668b`); and a final pass hardened API endpoints, optimized queries, and improved input validation across the codebase (`2eb48ee`). A cPanel deployment guide (`c85e3d1`) was also produced to support an alternative hosting target.

### 12.3 Phase 3 — Feature Expansion (April 2026)

Phase 3 was the longest and most feature-dense period. A "Not Stated" option was added for father's information together with illegitimate-status form behavior (`4290c7d`); marriage information gained an "Others" option (`eb9ac61`); bulk archive and toggle-archive APIs landed (`01d7808`); date-of-birth fields became nullable across registration tables (`e382bd4`) and a manual age-entry option was added for unknown DOB (`7872637`). A multi-token search with strict and fuzzy matching transformed the records experience (`550c841`). Duplicate PDF prevention was implemented and documented (`2a0815d`, `7f76327`, `c187172`). Comprehensive documentation and database migrations for the CRDMS were committed (`2707874`); permissions were restricted to Admin role only in the migration (`8aff719`); and dynamic permission cache validation began reflecting changes without forcing a logout (`b7577cc`).

The latter half of April brought a wave of high-impact features: activity logs with IP tracking (`bf021d5`); the encoder leaderboard (`57f1109`); the reorganization of PDF uploads into year-and-last-name folders (`1092d95`) with full documentation (`16ca30e`); a folder-browser API and front-end (`55fce0a`); PDF folder reconciliation for updates without re-uploading (`2ac9445`); enforcement of authentication and improved user-ID handling across all API endpoints (`4428fce`); HSTS and a stricter password policy (`bd6402d`); the replacement of citizenship fields with residence fields for marriage records (`87e98ed`); the late-registration detector with timeliness badges (`ceeec3d`); the **double-registration detection and management** functionality (`5caac82`); and the **RA 9048 petition module** with form and listing (`1d1887a`), later redesigned to match the institutional design pattern (`45a5033`). The export of civil-registry records to XLS and CSV (`782190c`) closed the major-feature work for this phase.

### 12.4 Phase 4 — Polish and Deployment (Late April – May 2026)

Phase 4 concentrated on UX polish, document generation, and deployment readiness. The `DocxTemplateProcessor` was added for handling DOCX templates (`838fb97`), with placeholder normalization that handles tokens split across runs (`9d8d5e7`). The RA 9048 module gained in-browser PDF preview and Docker LibreOffice support (`895b3e7`). A Family Relations module with rendering and search (`b68f454`) was introduced, followed by strict-and-fuzzy search for birth records in family relations (`272971a`). CSRF protection and unlink/relink functionality for record management (`5bfa26a`) were added, with an enhanced unlink confirmation process and reason prompt (`84fb8a0`).

The double-registration comparison modal was iterated through six versions (`8d63013`, `2846f25`, `07ea25d`, `2539692`, `82a69ad`, `ae9c9f3`), each adding refinements: critical and minor discrepancy highlighting, improved row highlighting, verdict display logic, visual clarity, and missing-data indication. The folder browse API was refactored to use the reorganized upload definitions (`1b5a230`). The most recent commit on the main branch — `7e13c41`, dated 5 May 2026 — added bulk operations and deduplication to PDF Backup Management.

Together, these four phases trace the system's evolution from an empty repository on 30 December 2025 to a feature-complete, production-deployed Civil Registry Records Management System by 5 May 2026.

---

## 13. Conclusion

The iScan Civil Registry Records Management System set out to solve a very old problem with a very modern approach: replace the steel cabinet, the bound logbook, and the manual lookup with a digital archive that is **fast, secure, auditable, and locally owned**. Over 104 commits and approximately five months, that goal has been substantially achieved.

The system delivers complete CRUD, OCR, scanner-driven ingestion, duplicate prevention, double-registration detection, RA 9048 petition automation, six-state workflow, append-only auditing, multi-token search, family-relations aggregation, late-registration detection, analytics dashboards, encoder leaderboards, XLS/CSV exports, calendar notes, and PDF backup-and-restore — all in a zero-Composer-dependency PHP codebase that runs unchanged on both Windows and Linux, and that defaults to fully offline operation when configured. It is hardened with CSRF tokens, login rate-limiting, hardened sessions, HSTS, a Content-Security-Policy, and a device-lock allowlist whose entries are SHA-256 fingerprints of eleven browser characteristics. It is deployed on the LGU's own Synology DS925+ NAS and exposed through a Cloudflare Tunnel that never allows inbound connections to the NAS itself.

Beyond its immediate utility to the Municipality of Baggao, iScan-CRDMS demonstrates a broader thesis: that a small, framework-free PHP codebase, written deliberately and committed in small reversible increments, can deliver an LGU-grade records management platform with a feature surface comparable to commercial offerings — at a fraction of the cost and without surrendering data sovereignty. The project stands ready both as a production system serving the citizens of Baggao and as a reference implementation for any Philippine municipality facing the same digitization challenge.

---

*End of report.*
