<?php
/**
 * Public, allow-listed logo delivery endpoint.
 *
 * Uploaded documents remain private under /uploads. Only the active branding
 * slots can be returned through this endpoint.
 */
require_once __DIR__ . '/../includes/branding.php';

$slot = trim((string) ($_GET['slot'] ?? ''));
$definitions = branding_logo_definitions();
if (!isset($definitions[$slot])) {
    http_response_code(404);
    exit;
}

$relativePath = branding_logo_path($slot);
$absolutePath = $relativePath !== '' ? branding_resolve_absolute_logo_path($relativePath) : null;
if ($absolutePath === null) {
    http_response_code(404);
    exit;
}

$imageInfo = @getimagesize($absolutePath);
$mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . (string) filesize($absolutePath));
readfile($absolutePath);
exit;