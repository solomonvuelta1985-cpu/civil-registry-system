# NAS PHP-FPM Production Preflight

The PHP-FPM/web runtime is separate from the PHP CLI runtime. A successful CLI check does not prove that the NAS web server has the same extensions loaded.

## Required check

Sign in as an administrator and open:

`/iscan/admin/runtime_requirements.php`

This page reports the actual web SAPI and `php.ini` used by the request. The critical checks are PDO MySQL, mbstring, Fileinfo, and OpenSSL. The feature checks are zlib compression, ZipArchive, and XML.

## zlib and ZIP behavior

The CRF PDF stream inspection checks `gzuncompress` and `zlib_decode` with `function_exists()` before calling them. The DOCX and ZIP export paths check `ZipArchive` before use and return an actionable error instead of a PHP fatal error.

If the page reports a missing capability, enable the matching extension in the PHP-FPM runtime used by the NAS web server, restart PHP-FPM/web server, and run the page again. Do not rely only on `php -m` from SSH because that may inspect a different PHP installation.

The CLI helper is useful for comparison:

`php scripts/check_production_php_requirements.php`

The web page remains authoritative for production because it runs inside PHP-FPM/Apache or the NAS web SAPI.
