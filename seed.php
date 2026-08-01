<?php
/**
 * seed.php
 * Wipes and repopulates all four tables with 50 rows each, using
 * Pakistan/India/Sri Lanka/Bangladesh/Afghanistan cricketer names
 * (men's and women's) as realistic SOS staff identities.
 *
 * Run once via CLI: php seed.php
 * Delete or move outside the webroot once done seeding.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getConnection();
echo "Seeding SOS Reception Management System (50 rows per table)...\n\n";

// ============================================================
// 1. WIPE EXISTING DATA
// ============================================================
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
$db->exec('TRUNCATE TABLE notifications');
$db->exec('TRUNCATE TABLE activity_logs');
$db->exec('TRUNCATE TABLE reception_logs');
$db->exec('TRUNCATE TABLE users');
$db->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "Cleared existing data.\n";

// ============================================================
// 2. USERS (50) — cricketer names, 3 admin / 10 manager / 37 receptionist
// ============================================================
// Names span Pakistan, India, Sri Lanka, Bangladesh, Afghanistan — men's and women's cricket.
$names = [
    // Admins (3)
    'Babar Azam', 'Virat Kohli', 'Sana Mir',
    // Managers (10)
    'Shaheen Afridi', 'Rohit Sharma', 'Angelo Mathews', 'Shakib Al Hasan', 'Rashid Khan',
    'Smriti Mandhana', 'Bismah Maroof', 'Harmanpreet Kaur', 'Chamari Athapaththu', 'Jhulan Goswami',
    // Receptionists (37)
    'Mohammad Rizwan', 'Fakhar Zaman', 'Shadab Khan', 'Imam-ul-Haq', 'Naseem Shah',
    'Rohit Sharma Jr', 'KL Rahul', 'Jasprit Bumrah', 'Ravindra Jadeja', 'Suryakumar Yadav',
    'Wanindu Hasaranga', 'Dimuth Karunaratne', 'Kusal Mendis', 'Pathum Nissanka', 'Dasun Shanaka',
    'Mustafizur Rahman', 'Litton Das', 'Mahmudullah Riyad', 'Taskin Ahmed', 'Najmul Hossain',
    'Mohammad Nabi', 'Hashmatullah Shahidi', 'Rahmanullah Gurbaz', 'Mujeeb Ur Rahman', 'Azmatullah Omarzai',
    'Muneeba Ali', 'Nida Dar', 'Aliya Riaz', 'Diana Baig', 'Fatima Sana',
    'Shafali Verma', 'Deepti Sharma', 'Renuka Singh', 'Yastika Bhatia', 'Richa Ghosh',
    'Nigar Sultana', 'Rumana Ahmed',
];

$roles = array_merge(
    array_fill(0, 3, 'admin'),
    array_fill(0, 10, 'manager'),
    array_fill(0, 37, 'receptionist')
);

$departments = [
    'Security Operations', 'Front Desk', 'Access Control', 'Surveillance & CCTV',
    'Executive Protection', 'IT Security', 'Compliance & Audit', 'Facilities',
];

$designationsByRole = [
    'admin' => ['Head of Security Operations', 'Director of Compliance', 'Chief Security Officer'],
    'manager' => ['Shift Supervisor', 'Control Room Manager', 'Facilities Manager', 'Compliance Manager'],
    'receptionist' => ['Security Officer', 'Front Desk Receptionist', 'Control Room Operator', 'Patrol Officer'],
];

function slugify_name(string $name): string
{
    $slug = strtolower(str_replace(' ', '', $name));
    return preg_replace('/[^a-z0-9]/', '', $slug);
}

$userIds = [];
$userMeta = []; // id => ['name'=>, 'role'=>, 'slug'=>]
$userStmt = $db->prepare(
    'INSERT INTO users (full_name, email, password, role, department, phone, is_active, last_login)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

for ($i = 0; $i < 50; $i++) {
    $name = $names[$i];
    $role = $roles[$i];
    $slug = slugify_name($name);
    $email = "{$slug}@sosreception.com";

    // Password scheme: [name][role][id] — e.g. babarazamadmin1
    $rawPassword = "{$slug}{$role}" . ($i + 1);
    $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);

    $dept = $departments[$i % count($departments)];
    // Fake but realistic-looking Pakistani mobile numbers (NOT real numbers of any real person)
    $phone = '+92 3' . random_int(0, 4) . random_int(0, 9) . '-' . str_pad((string)random_int(0, 9999999), 7, '0', STR_PAD_LEFT);

    $isActive = in_array($i, [40, 45], true) ? 0 : 1; // a couple deactivated for testing
    $lastLogin = date('Y-m-d H:i:s', strtotime('-' . random_int(0, 20) . ' days -' . random_int(0, 23) . ' hours'));

    $userStmt->execute([$name, $email, $passwordHash, $role, $dept, $phone, $isActive, $lastLogin]);
    $newId = (int)$db->lastInsertId();
    $userIds[] = $newId;
    $userMeta[$newId] = ['name' => $name, 'role' => $role, 'slug' => $slug, 'raw_password' => $rawPassword, 'email' => $email];
}
echo "Inserted 50 users (3 admin / 10 manager / 37 receptionist).\n";

// ============================================================
// 3. RECEPTION_LOGS (50) — one per user, guaranteed coverage of every ID
// ============================================================
$buildings = ['HQ Tower', 'Data Center Annex', 'Control Room Block', 'Executive Wing',
              'Perimeter Gatehouse', 'Training Facility', 'Fleet Depot'];

$securityItems = [
    'Replacement access control keycards (batch of 50)', 'CCTV dome camera housing units',
    'Two-way radio handsets (Motorola)', 'Body-worn camera units for patrol officers',
    'Biometric fingerprint scanner module', 'Security guard uniforms — seasonal batch',
    'Perimeter fence sensor replacement parts', 'Visitor badge printer ribbon cartridges',
    'K9 unit training equipment', 'Bulletproof vest — officer issue',
    'Server rack for surveillance NVR system', 'Barrier gate remote control units',
    'Metal detector wand (handheld)', 'X-ray baggage scanner spare parts',
    'Emergency response first-aid kits', 'Fire extinguisher inspection tags',
    'Patrol vehicle GPS tracker units', 'Duress alarm pendants for front desk staff',
    'Security console monitor — 27" replacement', 'Confidential incident report documents (sealed)',
    'Access control system firmware update drive', 'Night vision goggles — maintenance return',
    'Panic button wiring kit', 'Visitor management tablet devices',
    'Shift roster printed schedules (HR dispatch)', 'Guardhouse heater unit replacement',
    'Two-factor authentication hardware tokens', 'CCTV hard drive replacement (surveillance archive)',
    'Employee ID card blanks (batch of 200)', 'Radio charging dock station',
    'Perimeter floodlight replacement bulbs', 'Security dog food and supplies',
    'Vehicle barrier hydraulic pump unit', 'Evidence storage lockable containers',
    'Training manual printouts — annual refresher', 'Handheld metal detector calibration kit',
    'Emergency evacuation signage set', 'Visitor sign-in tablets (replacement batch)',
    'Control room chair — ergonomic replacement', 'Cable management trays for server room',
];

$trackingPrefixes = ['SOS-TRK', 'SEC-PKG', 'INT-DEL'];
$transitModes = ['Courier - TCS', 'Courier - Leopards', 'Hand Delivery', 'Internal Transfer', 'Postal Service'];
$statuses = ['pending', 'in_transit', 'delivered', 'returned'];
$priorities = ['low', 'medium', 'high', 'urgent'];

$recordIds = [];
$recordStmt = $db->prepare(
    'INSERT INTO reception_logs
     (type, status, priority, date_time, employee_code, employee_name, designation, building,
      package_detail, package_weight, package_dimensions, mode_of_transit, tracking_number,
      address, city, delivered_to, delivered_at, notes, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($userIds as $i => $uid) {
    $meta = $userMeta[$uid];
    $type = ($i % 3 === 0) ? 'dispatch' : 'receiving';
    $status = $statuses[$i % count($statuses)];
    $priority = $priorities[$i % count($priorities)];
    $building = $buildings[$i % count($buildings)];
    $item = $securityItems[$i % count($securityItems)];
    $weight = round(random_int(50, 8000) / 100, 2);
    $dims = random_int(15, 60) . 'x' . random_int(15, 60) . 'x' . random_int(10, 40) . ' cm';
    $mode = $transitModes[$i % count($transitModes)];
    $tracking = $trackingPrefixes[$i % count($trackingPrefixes)] . '-' . (10000 + $i);
    $designation = $designationsByRole[$meta['role']][$i % count($designationsByRole[$meta['role']])];

    $dateTime = date('Y-m-d H:i:s', strtotime('-' . random_int(0, 29) . ' days -' . random_int(0, 23) . ' hours'));
    $deliveredAt = ($status === 'delivered') ? date('Y-m-d H:i:s', strtotime($dateTime . ' +' . random_int(1, 48) . ' hours')) : null;
    $deliveredTo = ($status === 'delivered') ? $meta['name'] : null;

    // Record is created_by this same user — guarantees every user ID has an associated record.
    $notes = ($priority === 'urgent') ? 'Flagged urgent — expedite handling.' : '';

    $recordStmt->execute([
        $type, $status, $priority, $dateTime, $meta['slug'], $meta['name'],
        $designation, $building, $item, $weight, $dims, $mode, $tracking,
        "{$building}, SOS Corporate Campus", 'Lahore', $deliveredTo, $deliveredAt, $notes, $uid,
    ]);
    $recordIds[] = (int)$db->lastInsertId();
}
echo "Inserted 50 reception_logs records (one per user, every ID covered).\n";

// ============================================================
// 4. ACTIVITY_LOGS (50)
// ============================================================
$activityActions = [
    'login_success', 'login_failed', 'record_created', 'record_updated',
    'user_role_changed', 'user_status_toggled', 'report_exported', 'profile_updated',
    'access_denied', 'pending_pickup_reminder_sent',
];

$activityStmt = $db->prepare(
    'INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?)'
);

for ($i = 0; $i < 50; $i++) {
    $action = $activityActions[$i % count($activityActions)];
    $uid = $userIds[random_int(0, 49)];
    $meta = $userMeta[$uid];
    $ip = '192.168.1.' . random_int(2, 254);
    $createdAt = date('Y-m-d H:i:s', strtotime('-' . random_int(0, 29) . ' days -' . random_int(0, 23) . ' hours'));

    $details = match ($action) {
        'login_success' => 'User logged in.',
        'login_failed' => "Failed login attempt for email: {$meta['email']}",
        'record_created' => 'Created reception_logs record #' . $recordIds[$i % count($recordIds)] . ' (receiving)',
        'record_updated' => 'Updated reception_logs record #' . $recordIds[($i + 5) % count($recordIds)],
        'user_role_changed' => 'Changed user #' . $userIds[random_int(0, 49)] . ' role to manager',
        'user_status_toggled' => 'Toggled active status for user #' . $userIds[random_int(0, 49)],
        'report_exported' => 'Exported excel report (weekly), ' . random_int(10, 45) . ' rows',
        'profile_updated' => 'User updated their own profile.',
        'access_denied' => 'Denied permission: records.edit.any',
        'pending_pickup_reminder_sent' => 'Reminder sent for record #' . $recordIds[$i % count($recordIds)] . ' to user #' . $uid,
        default => '',
    };

    $activityStmt->execute([$uid, $action, $details, $ip, $createdAt]);
}
echo "Inserted 50 activity_logs entries.\n";

// ============================================================
// 5. NOTIFICATIONS (50)
// ============================================================
$notifTypes = ['in_app', 'email', 'sms'];
$notifTitles = [
    'Package Received' => 'A package has arrived for you. Check reception for pickup.',
    'Package Delivered' => 'Your package has been marked delivered.',
    'Pending Pickup Reminder' => 'A package has been pending pickup for over 24 hours.',
    'Urgent Package Alert' => 'An urgent priority package requires immediate attention.',
];
$titleKeys = array_keys($notifTitles);

$notifStmt = $db->prepare(
    'INSERT INTO notifications (user_id, type, title, message, is_read, related_log_id, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);

foreach ($userIds as $i => $uid) {
    $type = $notifTypes[$i % count($notifTypes)];
    $title = $titleKeys[$i % count($titleKeys)];
    $message = $notifTitles[$title];
    $isRead = ($i % 3 === 0) ? 0 : 1;
    $relatedLogId = $recordIds[$i];
    $createdAt = date('Y-m-d H:i:s', strtotime('-' . random_int(0, 15) . ' days -' . random_int(0, 23) . ' hours'));

    $notifStmt->execute([$uid, $type, $title, $message, $isRead, $relatedLogId, $createdAt]);
}
echo "Inserted 50 notifications (one per user).\n";

// ============================================================
// 6. CREDENTIALS REFERENCE FILE — so you can actually log in as anyone
// ============================================================
$credLines = ["email,password,role"];
foreach ($userMeta as $uid => $meta) {
    $credLines[] = "{$meta['email']},{$meta['raw_password']},{$meta['role']}";
}
file_put_contents(__DIR__ . '/seed_credentials.csv', implode("\n", $credLines));
echo "\nWrote seed_credentials.csv with all 50 login credentials.\n";
echo "IMPORTANT: delete seed_credentials.csv after use — it contains plaintext passwords.\n";

echo "\nSeeding complete.\n";
echo "Example admin login: " . slugify_name($names[0]) . "@sosreception.com / " . slugify_name($names[0]) . "admin1\n";