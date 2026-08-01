<?php
/**
 * includes/activity_logger.php
 * Writes audit trail entries to activity_logs. Never breaks the calling request on failure.
 */

require_once __DIR__ . '/../config/database.php';

function log_activity(?int $userId, string $action, string $details = ''): void
{
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO activity_logs (user_id, action, details, ip_address)
             VALUES (?, ?, ?, ?)'
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}