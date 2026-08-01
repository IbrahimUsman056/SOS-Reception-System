<?php
/**
 * cron/pending_reminders.php
 * Run via cron every hour, e.g.:
 *   0 * * * * php /path/to/reception-system/cron/pending_reminders.php
 *
 * Finds 'receiving' records still 'pending' after N hours and notifies
 * the matched employee via notify_user() (in_app + email; SMS reserved
 * for urgent-priority records to control cost).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/notifications.php';

const REMINDER_THRESHOLD_HOURS = 24;

$db = Database::getConnection();

// Find pending receiving records older than the threshold that don't
// already have a reminder logged for them (checked via activity_logs
// instead of notifications, since notify_user() writes multiple rows
// per event now — in_app + email — and we just need "have we reminded
// about THIS record before" as a single guard).
$stmt = $db->prepare(
    "SELECT reception_logs.id, reception_logs.employee_name, reception_logs.employee_code,
            reception_logs.building, reception_logs.date_time, reception_logs.tracking_number,
            reception_logs.priority
     FROM reception_logs
     WHERE reception_logs.type = 'receiving'
       AND reception_logs.status = 'pending'
       AND reception_logs.date_time <= (NOW() - INTERVAL :hours HOUR)
       AND NOT EXISTS (
           SELECT 1 FROM activity_logs
           WHERE activity_logs.action = 'pending_pickup_reminder_sent'
             AND activity_logs.details LIKE CONCAT('%record #', reception_logs.id, ' %')
       )"
);
$stmt->bindValue(':hours', REMINDER_THRESHOLD_HOURS, PDO::PARAM_INT);
$stmt->execute();
$overdue = $stmt->fetchAll();

$findUser = $db->prepare('SELECT id FROM users WHERE email LIKE CONCAT(?, "@%") LIMIT 1');

$notifiedCount = 0;
foreach ($overdue as $record) {
    $findUser->execute([$record['employee_code']]);
    $user = $findUser->fetch();

    $message = "Package for {$record['employee_name']} at {$record['building']} has been "
             . "pending pickup since {$record['date_time']}"
             . ($record['tracking_number'] ? " (Tracking #: {$record['tracking_number']})" : '') . '.';

    if ($user) {
        $channels = ($record['priority'] === 'urgent') ? ['in_app', 'email', 'sms'] : ['in_app', 'email'];
        notify_user((int)$user['id'], 'Pending Pickup Reminder', $message, (int)$record['id'], $channels);

        // Guard marker so this record isn't re-notified every hour.
        log_activity(null, 'pending_pickup_reminder_sent', "Reminder sent for record #{$record['id']} to user #{$user['id']}");
        $notifiedCount++;
    } else {
        log_activity(null, 'reminder_unmatched_employee', $message);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Processed " . count($overdue) . " overdue record(s), sent {$notifiedCount} reminder(s).\n";