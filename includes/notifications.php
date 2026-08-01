<?php
/**
 * includes/notifications.php
 * Central notification creation + retrieval. All notification types
 * (in_app, email, sms) get logged as a row in `notifications` regardless
 * of delivery channel, so there's always an in-app record even if the
 * email/SMS send fails downstream.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Create a notification row. Does NOT send email/SMS itself — that's
 * handled by notify_user() below, which calls this plus the relevant
 * channel sender.
 */
function create_notification(int $userId, string $type, string $title, string $message, ?int $relatedLogId = null): int
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'INSERT INTO notifications (user_id, type, title, message, related_log_id)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $type, $title, $message, $relatedLogId]);
    return (int)$db->lastInsertId();
}

/**
 * High-level entry point: creates the in-app record always, and
 * additionally sends email/SMS if requested and the user has the
 * relevant contact info. Failures in email/SMS never block the in-app
 * notification from existing.
 */
function notify_user(int $userId, string $title, string $message, ?int $relatedLogId = null, array $channels = ['in_app']): void
{
    // Always create the in-app row first — this is the source of truth.
    create_notification($userId, 'in_app', $title, $message, $relatedLogId);

    if (in_array('email', $channels, true)) {
        try {
            require_once __DIR__ . '/mailer.php';
            send_notification_email($userId, $title, $message);
            create_notification($userId, 'email', $title, $message, $relatedLogId);
        } catch (Throwable $e) {
            error_log('Email notification failed: ' . $e->getMessage());
        }
    }

    if (in_array('sms', $channels, true)) {
        try {
            require_once __DIR__ . '/sms.php';
            send_notification_sms($userId, $message);
            create_notification($userId, 'sms', $title, $message, $relatedLogId);
        } catch (Throwable $e) {
            error_log('SMS notification failed: ' . $e->getMessage());
        }
    }
}

function get_unread_count(int $userId): int
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'in_app' AND is_read = 0"
    );
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function get_recent_notifications(int $userId, int $limit = 10): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare(
        "SELECT id, title, message, is_read, related_log_id, created_at
         FROM notifications
         WHERE user_id = ? AND type = 'in_app'
         ORDER BY created_at DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mark_notification_read(int $notificationId, int $userId): void
{
    $db = Database::getConnection();
    // Scope to user_id too, so one user can't mark another user's notification read via ID guessing.
    $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$notificationId, $userId]);
}

function mark_all_read(int $userId): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'in_app'");
    $stmt->execute([$userId]);
}