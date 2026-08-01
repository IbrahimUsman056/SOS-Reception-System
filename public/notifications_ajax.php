<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    echo json_encode([
        'unread_count' => get_unread_count(current_user_id()),
        'notifications' => get_recent_notifications(current_user_id(), 10),
    ]);
    exit;
}

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    mark_notification_read($id, current_user_id());
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    mark_all_read(current_user_id());
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Unknown action.']);