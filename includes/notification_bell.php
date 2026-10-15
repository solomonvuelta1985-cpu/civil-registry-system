<?php
require_once __DIR__ . '/security.php';
$_notification_api_url = rtrim(defined('BASE_URL') ? BASE_URL : '../', '/') . '/api/notifications.php';
$_notification_csrf_token = generateCSRFToken();
?>
<button
    type="button"
    class="notification-bell"
    data-notification-bell
    data-user-id="<?= htmlspecialchars((string)($_SESSION['user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
    data-api-url="<?= htmlspecialchars($_notification_api_url, ENT_QUOTES, 'UTF-8') ?>"
    data-csrf-token="<?= htmlspecialchars($_notification_csrf_token, ENT_QUOTES, 'UTF-8') ?>"
    aria-label="Notifications"
    aria-expanded="false"
    aria-controls="notificationPanel"
    title="Notifications"
>
    <i data-lucide="bell" aria-hidden="true"></i>
    <span class="notification-count" data-notification-count hidden aria-hidden="true"></span>
</button>
