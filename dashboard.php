<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$db = Database::getConnection();
$scope = record_scope_sql();

$statsSql = "SELECT COUNT(*) AS total,
               SUM(CASE WHEN type = 'receiving' THEN 1 ELSE 0 END) AS receiving_count,
               SUM(CASE WHEN type = 'dispatch' THEN 1 ELSE 0 END) AS dispatch_count,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count
        FROM reception_logs
        JOIN users u ON reception_logs.created_by = u.id
        WHERE {$scope['where']}";
$stmt = $db->prepare($statsSql);
$stmt->execute($scope['params']);
$stats = $stmt->fetch();

$recentSql = "SELECT reception_logs.id, reception_logs.type, reception_logs.employee_name,
                     reception_logs.building, reception_logs.status, reception_logs.date_time
              FROM reception_logs
              JOIN users u ON reception_logs.created_by = u.id
              WHERE {$scope['where']}
              ORDER BY reception_logs.date_time DESC
              LIMIT 6";
$stmt = $db->prepare($recentSql);
$stmt->execute($scope['params']);
$recent = $stmt->fetchAll();

$pendingSql = "SELECT reception_logs.id, reception_logs.employee_name, reception_logs.building,
                      reception_logs.date_time, reception_logs.priority
               FROM reception_logs
               JOIN users u ON reception_logs.created_by = u.id
               WHERE {$scope['where']} AND reception_logs.status = 'pending'
               ORDER BY reception_logs.date_time ASC
               LIMIT 3";
$stmt = $db->prepare($pendingSql);
$stmt->execute($scope['params']);
$pendingPickups = $stmt->fetchAll();

// Counts per day-of-week, current week, for the mini calendar dots
$weekCountsSql = "SELECT DATE(date_time) AS d, COUNT(*) AS c
                  FROM reception_logs
                  JOIN users u ON reception_logs.created_by = u.id
                  WHERE {$scope['where']} AND YEARWEEK(date_time, 1) = YEARWEEK(CURDATE(), 1)
                  GROUP BY DATE(date_time)";
$stmt = $db->prepare($weekCountsSql);
$stmt->execute($scope['params']);
$weekCounts = [];
foreach ($stmt->fetchAll() as $row) {
    $weekCounts[$row['d']] = (int)$row['c'];
}

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

// Build current week (Mon–Sun) for the calendar strip
$monday = date('Y-m-d', strtotime('monday this week'));
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime($monday . " +{$i} days"));
    $weekDays[] = [
        'label' => date('D', strtotime($date)),
        'num' => (int)date('j', strtotime($date)),
        'date' => $date,
        'is_today' => $date === date('Y-m-d'),
        'count' => $weekCounts[$date] ?? 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="container">

        <!-- Greeting banner with illustration -->
        <section class="greeting-banner">
            <div>
                <h1><?= e($greeting) ?>, <?= e(explode(' ', $_SESSION['full_name'])[0]) ?> </h1>
                <p>Here's what's moving through reception today.</p>
                <a href="records/add.php" class="btn btn-banner">+ New Record</a>
            </div>
            <div class="banner-illustration">
                <img src="<?= ASSET_URL ?>/images/image.png"
                    alt="Reception illustration"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="illustration-fallback">🛡️📦</div>
            </div>
        </section>

        <div class="dashboard-columns">

            <div class="dashboard-main">
                <!-- Stat cards — now clickable -->
                <section class="stats-grid">
                    <a href="records/view.php" class="stat-card stat-teal">
                        <div class="stat-icon">📦</div>
                        <div>
                            <h3><?= (int)($stats['total'] ?? 0) ?></h3>
                            <p>Total Records<?= current_role() !== 'admin' ? ' (your scope)' : '' ?></p>
                        </div>
                    </a>
                    <a href="records/view.php?type=receiving" class="stat-card stat-blue">
                        <div class="stat-icon">📥</div>
                        <div>
                            <h3><?= (int)($stats['receiving_count'] ?? 0) ?></h3>
                            <p>Receiving</p>
                        </div>
                    </a>
                    <a href="records/view.php?type=dispatch" class="stat-card stat-purple">
                        <div class="stat-icon">📤</div>
                        <div>
                            <h3><?= (int)($stats['dispatch_count'] ?? 0) ?></h3>
                            <p>Dispatch</p>
                        </div>
                    </a>
                    <a href="records/view.php?status=pending" class="stat-card stat-orange">
                        <div class="stat-icon">⏳</div>
                        <div>
                            <h3><?= (int)($stats['pending_count'] ?? 0) ?></h3>
                            <p>Pending Pickup</p>
                        </div>
                    </a>
                </section>

                <section class="panel panel-main">
                    <div class="panel-header">
                        <h3>Recent Records</h3>
                        <a href="records/view.php" class="panel-link">View all »</a>
                    </div>

                    <?php if (empty($recent)): ?>
                        <p class="empty-state">No records yet. <a href="records/add.php">Add the first one</a>.</p>
                    <?php else: ?>
                    <table class="mini-table">
                        <thead>
                            <tr><th>Type</th><th>Employee</th><th>Building</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><span class="type-pill type-<?= e($r['type']) ?>"><?= e(ucfirst($r['type'])) ?></span></td>
                                <td><?= e($r['employee_name']) ?></td>
                                <td><?= e($r['building']) ?></td>
                                <td><span class="status-pill status-<?= e($r['status']) ?>"><?= e(ucfirst(str_replace('_',' ',$r['status']))) ?></span></td>
                                <td><a href="records/edit.php?id=<?= (int)$r['id'] ?>" class="btn-small">Open</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="panel panel-side">
                <div class="user-mini-card">
                    <?= avatar_html($_SESSION['avatar'] ?? null, $_SESSION['full_name'], '42px') ?>
                    <div>
                        <strong><?= e($_SESSION['full_name']) ?></strong>
                        <p><?= e(ucfirst($_SESSION['role'])) ?><?= $_SESSION['department'] ? ' · ' . e($_SESSION['department']) : '' ?></p>
                    </div>
                </div>

                <div class="panel-header">
                    <h3>Schedule</h3>
                </div>
                <div class="mini-calendar">
                    <?php foreach ($weekDays as $d): ?>
                        <a href="records/view.php?date_from=<?= $d['date'] ?>&date_to=<?= $d['date'] ?>"
                           class="cal-day <?= $d['is_today'] ? 'cal-today' : '' ?>">
                            <span class="cal-label"><?= e($d['label']) ?></span>
                            <span class="cal-num"><?= $d['num'] ?></span>
                            <?php if ($d['count'] > 0): ?><span class="cal-dot"><?= $d['count'] ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="panel-header" style="margin-top:20px;">
                    <h3>Pending Pickups</h3>
                </div>

                <?php if (empty($pendingPickups)): ?>
                    <p class="empty-state">Nothing pending — all clear ✅</p>
                <?php else: ?>
                    <?php foreach ($pendingPickups as $p): ?>
                    <a href="records/edit.php?id=<?= (int)$p['id'] ?>" class="pickup-card pickup-<?= e($p['priority']) ?>">
                        <div class="pickup-avatar"><?= e(strtoupper(substr($p['employee_name'],0,1))) ?></div>
                        <div class="pickup-info">
                            <strong><?= e($p['employee_name']) ?></strong>
                            <span><?= e($p['building']) ?></span>
                        </div>
                        <span class="pickup-time"><?= date('g:i A', strtotime($p['date_time'])) ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="quick-links">
                    <a href="scan.php" class="btn-small">Scan Package</a>
                    <?php if (can('users.manage')): ?>
                        <a href="admin/users.php" class="btn-small btn-admin-small">Manage Users</a>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </main>
</body>
</html>