<?php
/**
 * Shared configurable logo paths for web pages and CRF output.
 */

require_once __DIR__ . '/settings.php';

const BRANDING_LOGO_HIDDEN_VALUE = '__hidden__';

function branding_logo_definitions(): array
{
    return [
        'app' => [
            'setting_key' => 'branding_app_logo',
            'label' => 'Application Logo',
            'description' => 'Shown on the login page, page headers, sidebar, mobile header, and preloader.',
            'default_path' => 'assets/img/LOGO1.png',
        ],
        'crf_seal' => [
            'setting_key' => 'branding_crf_logo_seal',
            'label' => 'CRF Seal',
            'description' => 'Leftmost seal in Civil Registry Form headers.',
            'default_path' => env('CRF1A_LOGO_SEAL', 'assets/img/LOGO1.png'),
        ],
        'crf_baggao' => [
            'setting_key' => 'branding_crf_logo_baggao',
            'label' => 'CRF Baggao Logo',
            'description' => 'Second logo in Civil Registry Form headers.',
            'default_path' => env('CRF1A_LOGO_BAGGAO', 'assets/img/CRF1A_BAGGAO_REFERENCE.png'),
        ],
        'crf_pilipinas' => [
            'setting_key' => 'branding_crf_logo_pilipinas',
            'label' => 'CRF Bagong Pilipinas Logo',
            'description' => 'Rightmost logo in Civil Registry Form headers.',
            'default_path' => env('CRF1A_LOGO_PILIPINAS', 'assets/img/CRF1A_BAGONG_PILIPINAS.png'),
        ],
    ];
}

function branding_normalize_logo_path(string $path): string
{
    return ltrim(str_replace('\\', '/', trim($path)), '/');
}

function branding_is_uploaded_logo_path(string $path): bool
{
    return preg_match('~^uploads/branding/[a-f0-9]{32}\\.(?:png|jpg)$~iD', branding_normalize_logo_path($path)) === 1;
}

function branding_resolve_absolute_logo_path(string $path): ?string
{
    $path = branding_normalize_logo_path($path);
    if ($path === '' || strpos($path, "\0") !== false || preg_match('~^[a-z]:~i', $path)) {
        return null;
    }

    $basePath = realpath(BASE_PATH);
    if ($basePath === false) {
        return null;
    }
    $absolutePath = realpath($basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    if ($absolutePath === false || !is_file($absolutePath)) {
        return null;
    }

    $prefix = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $isInsideBase = DIRECTORY_SEPARATOR === '\\'
        ? strncasecmp($absolutePath, $prefix, strlen($prefix)) === 0
        : strncmp($absolutePath, $prefix, strlen($prefix)) === 0;
    return $isInsideBase ? $absolutePath : null;
}

function branding_resolve_relative_logo_path(string $path): ?string
{
    if (branding_resolve_absolute_logo_path($path) === null) {
        return null;
    }

    return branding_normalize_logo_path($path);
}

function branding_is_logo_hidden(string $value): bool
{
    return trim($value) === BRANDING_LOGO_HIDDEN_VALUE;
}

function branding_logo_path(string $slot): string
{
    $definitions = branding_logo_definitions();
    if (!isset($definitions[$slot])) {
        return '';
    }

    $definition = $definitions[$slot];
    $storedPath = (string) get_setting($definition['setting_key'], '');
    if (branding_is_logo_hidden($storedPath)) {
        return '';
    }
    if (branding_is_uploaded_logo_path($storedPath)) {
        $resolvedStoredPath = branding_resolve_relative_logo_path($storedPath);
        if ($resolvedStoredPath !== null) {
            return $resolvedStoredPath;
        }
    }

    return branding_resolve_relative_logo_path((string) $definition['default_path']) ?? '';
}

function branding_logo_url(string $slot): string
{
    $definitions = branding_logo_definitions();
    if (!isset($definitions[$slot])) {
        return '';
    }

    $path = branding_logo_path($slot);
    $absolutePath = $path !== '' ? branding_resolve_absolute_logo_path($path) : null;
    if ($absolutePath === null) {
        return '';
    }
    $version = substr(hash_file('sha256', $absolutePath), 0, 16);

    return rtrim(BASE_URL, '/') . '/public/branding_logo.php?slot=' . rawurlencode($slot) . '&v=' . $version;
}

function branding_apply_crf_logo_overrides(array $config): array
{
    $fieldSlots = [
        'logo_seal' => 'crf_seal',
        'logo_baggao' => 'crf_baggao',
        'logo_pilipinas' => 'crf_pilipinas',
    ];
    $definitions = branding_logo_definitions();

    foreach ($fieldSlots as $field => $slot) {
        $definition = $definitions[$slot];
        $storedPath = (string) get_setting($definition['setting_key'], '');
        if (branding_is_logo_hidden($storedPath)) {
            $config[$field] = BRANDING_LOGO_HIDDEN_VALUE;
        } elseif (branding_is_uploaded_logo_path($storedPath) && branding_resolve_relative_logo_path($storedPath) !== null) {
            $config[$field] = branding_normalize_logo_path($storedPath);
        }
    }

    return $config;
}

function branding_crf_preview_config(array $config): array
{
    $fieldSlots = [
        'logo_seal' => 'crf_seal',
        'logo_baggao' => 'crf_baggao',
        'logo_pilipinas' => 'crf_pilipinas',
    ];
    $definitions = branding_logo_definitions();

    foreach ($fieldSlots as $field => $slot) {
        $storedPath = (string) get_setting($definitions[$slot]['setting_key'], '');
        if (branding_is_logo_hidden($storedPath)) {
            $config[$field] = BRANDING_LOGO_HIDDEN_VALUE;
            continue;
        }
        if (branding_is_uploaded_logo_path($storedPath) && branding_resolve_absolute_logo_path($storedPath) !== null) {
            $logoUrl = branding_logo_url($slot);
            if ($logoUrl !== '') {
                $parts = parse_url($logoUrl);
                $basePath = rtrim((string)parse_url(BASE_URL, PHP_URL_PATH), '/');
                $logoPath = (string)($parts['path'] ?? '');
                if ($basePath !== '' && strpos($logoPath, $basePath . '/') === 0) {
                    $logoPath = substr($logoPath, strlen($basePath) + 1);
                } else {
                    $logoPath = ltrim($logoPath, '/');
                }
                $config[$field] = $logoPath . (isset($parts['query']) ? '?' . $parts['query'] : '');
            }
        }
    }

    return $config;
}

function branding_delete_uploaded_logo_if_unused(string $path, string $excludedSettingKey = '')
{
    $path = branding_normalize_logo_path($path);
    if (!branding_is_uploaded_logo_path($path)) {
        return;
    }

    foreach (branding_logo_definitions() as $definition) {
        if ($definition['setting_key'] === $excludedSettingKey) {
            continue;
        }
        if ((string) get_setting($definition['setting_key'], '') === $path) {
            return;
        }
    }

    $absolutePath = branding_resolve_absolute_logo_path($path);
    if ($absolutePath !== null) {
        @unlink($absolutePath);
    }
}

function branding_store_logo_upload(PDO $pdo, string $slot, array $file, ?int $userId, string &$error): bool
{
    $error = '';
    $definitions = branding_logo_definitions();
    if (!isset($definitions[$slot])) {
        $error = 'Choose a valid logo slot.';
        return false;
    }

    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        $error = $uploadError === UPLOAD_ERR_NO_FILE
            ? 'Choose a PNG or JPEG image to upload.'
            : 'The image upload did not complete. Please try again.';
        return false;
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $fileSize = (int) (@filesize($temporaryPath) ?: 0);
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath) || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        $error = 'Choose an image up to 5 MB.';
        return false;
    }

    $imageInfo = @getimagesize($temporaryPath);
    if (!is_array($imageInfo) || empty($imageInfo['mime'])) {
        $error = 'The uploaded file is not a valid PNG or JPEG image.';
        return false;
    }

    $mime = (string) $imageInfo['mime'];
    if (class_exists('finfo')) {
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $fileInfo->file($temporaryPath);
        if (!is_string($detectedMime) || $detectedMime !== $mime) {
            $error = 'The uploaded file content does not match its image type.';
            return false;
        }
    }

    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    if (!isset($extensions[$mime])) {
        $error = 'Only PNG and JPEG logos are supported.';
        return false;
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width > 8000 || $height > 8000 || $width * $height > 25000000) {
        $error = 'The image dimensions are too large. Use an image no larger than 8,000 by 8,000 pixels.';
        return false;
    }

    $brandingDirectory = BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'branding';
    if (!is_dir($brandingDirectory) && !@mkdir($brandingDirectory, 0755, true) && !is_dir($brandingDirectory)) {
        $error = 'The branding uploads directory is not writable.';
        return false;
    }

    try {
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    } catch (Throwable $exception) {
        $error = 'A secure filename could not be generated.';
        return false;
    }

    $absolutePath = $brandingDirectory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($temporaryPath, $absolutePath)) {
        $error = 'The logo could not be saved. Check the branding uploads directory permissions.';
        return false;
    }

    $definition = $definitions[$slot];
    $previousPath = (string) get_setting($definition['setting_key'], '');
    $relativePath = 'uploads/branding/' . $filename;
    if (!set_setting($pdo, $definition['setting_key'], $relativePath, $userId)) {
        @unlink($absolutePath);
        $error = 'The logo was uploaded but its setting could not be saved.';
        return false;
    }

    branding_delete_uploaded_logo_if_unused($previousPath, $definition['setting_key']);
    return true;
}