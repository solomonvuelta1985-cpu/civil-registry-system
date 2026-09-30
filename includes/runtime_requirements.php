<?php
/** Runtime capability checks for the deployed web SAPI, including NAS PHP-FPM. */

function iscan_runtime_requirements(): array
{
    $checks = [
        ['key' => 'pdo_mysql', 'label' => 'PDO MySQL', 'severity' => 'critical', 'ok' => extension_loaded('pdo_mysql'), 'purpose' => 'Database connection'],
        ['key' => 'mbstring', 'label' => 'mbstring', 'severity' => 'critical', 'ok' => extension_loaded('mbstring'), 'purpose' => 'Text normalization and validation'],
        ['key' => 'fileinfo', 'label' => 'Fileinfo', 'severity' => 'critical', 'ok' => extension_loaded('fileinfo'), 'purpose' => 'PDF/file type validation'],
        ['key' => 'openssl', 'label' => 'OpenSSL', 'severity' => 'critical', 'ok' => extension_loaded('openssl'), 'purpose' => 'Secure sessions and application security'],
        ['key' => 'zlib', 'label' => 'zlib compression', 'severity' => 'feature', 'ok' => extension_loaded('zlib') && (function_exists('gzuncompress') || function_exists('zlib_decode')), 'purpose' => 'CRF PDF compressed stream inspection'],
        ['key' => 'zip', 'label' => 'ZipArchive', 'severity' => 'feature', 'ok' => class_exists('ZipArchive'), 'purpose' => 'DOCX generation and ZIP backup export'],
        ['key' => 'xml', 'label' => 'XML', 'severity' => 'feature', 'ok' => extension_loaded('xml'), 'purpose' => 'Document/XML processing'],
    ];
    $criticalFailures = [];
    $featureWarnings = [];
    foreach ($checks as $check) {
        if ($check['ok']) continue;
        if ($check['severity'] === 'critical') $criticalFailures[] = $check['key'];
        else $featureWarnings[] = $check['key'];
    }
    return [
        'ok' => $criticalFailures === [],
        'critical_failures' => $criticalFailures,
        'feature_warnings' => $featureWarnings,
        'sapi' => PHP_SAPI,
        'php_version' => PHP_VERSION,
        'php_ini' => php_ini_loaded_file() ?: null,
        'checks' => $checks,
    ];
}

function iscan_runtime_requirements_assert_critical(): array
{
    $report = iscan_runtime_requirements();
    if (!$report['ok']) throw new RuntimeException('Production PHP is missing required extensions: ' . implode(', ', $report['critical_failures']));
    return $report;
}
