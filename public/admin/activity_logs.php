<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../config/database.php';

require_login();

if (!can('activity_logs.view.all') && !can('activity_logs.view.department')) {
    http_response_code(403);
    die('You do not have permission to view activity logs.');
}

$db = Database::getConnection();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$actionFilter = $_GET['action_filter'] ?? '';
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = ['1=1'];
$params = [];

if (!can('activity_logs.view.all')) {
    $where[] = 'u.department = ?';
    $params[] = $_SESSION['department'];
}

if ($actionFilter !== '') {
    $where[] = 'activity_logs.action = ?';
    $params[] = $actionFilter;
}

if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR activity_logs.details LIKE ? OR activity_logs.ip_address LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

if ($dateFrom !== '') {
    $where[] = 'activity_logs.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'activity_logs.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare(
    "SELECT COUNT(*) FROM activity_logs
     LEFT JOIN users u ON activity_logs.user_id = u.id
     WHERE {$whereSql}"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT activity_logs.id, activity_logs.action, activity_logs.details,
               activity_logs.ip_address, activity_logs.created_at,
               u.full_name, u.email, u.avatar
        FROM activity_logs
        LEFT JOIN users u ON activity_logs.user_id = u.id
        WHERE {$whereSql}
        ORDER BY activity_logs.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}"; // both ints, hardcoded/cast above — safe to interpolate

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actionListStmt = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actionList = $actionListStmt->fetchAll(PDO::FETCH_COLUMN);

function action_badge_class(string $action): string
{
    if (str_contains($action, 'failed') || str_contains($action, 'denied') || str_contains($action, 'rate_limited')) {
        return 'action-danger';
    }
    if (str_contains($action, 'created') || str_contains($action, 'success')) {
        return 'action-success';
    }
    if (str_contains($action, 'updated') || str_contains($action, 'changed') || str_contains($action, 'toggled')) {
        return 'action-warning';
    }
    return 'action-neutral';
}

// Helper to preserve current filters across page links / clear link
function build_query(array $overrides = []): string
{
    $current = [
        'action_filter' => $_GET['action_filter'] ?? '',
        'search' => $_GET['search'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'page' => $_GET['page'] ?? 1,
    ];
    $merged = array_merge($current, $overrides);
    return http_build_query(array_filter($merged, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Log - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <main class="container">
        <section class="greeting-banner audit-banner">
            <div>
                <h1>Audit Log</h1>
                <p>
                    Scope: <?= can('activity_logs.view.all') ? 'Organization-wide' : 'Department: ' . e($_SESSION['department']) ?>
                    · <?= $total ?> total entries
                </p>
            </div>
        </section>

        <form method="GET" class="filters-bar filters-card">
            <input type="text" name="search" placeholder="Search name, email, details, IP..." value="<?= e($search) ?>" class="filter-search">
            <select name="action_filter">
                <option value="">All Actions</option>
                <?php foreach ($actionList as $a): ?>
                    <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
            <input type="date" name="date_to" value="<?= e($dateTo) ?>">
            <button type="submit" class="btn btn-primary">Apply</button>
            <?php if ($search || $actionFilter || $dateFrom || $dateTo): ?>
                <a href="activity_logs.php" class="btn">Clear</a>
            <?php endif; ?>
        </form>

        <div class="audit-table-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:30px;">No activity found matching your filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="audit-timestamp"><?= e(date('M j, Y', strtotime($log['created_at']))) ?><br><span class="notif-time"><?= e(date('g:i A', strtotime($log['created_at']))) ?></span></td>
                        <td>
                            <?php if ($log['full_name']): ?>
                                <div class="audit-user-cell">
                                    <?= avatar_html($log['avatar'] ?? null, $log['full_name'], '28px') ?>
                                    <div>
                                        <strong><?= e($log['full_name']) ?></strong>
                                        <p class="notif-time"><?= e($log['email']) ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <em class="notif-time">System / Unauthenticated</em>
                            <?php endif; ?>
                        </td>
                        <td><span class="action-badge <?= action_badge_class($log['action']) ?>"><?= e($log['action']) ?></span></td>
                        <td class="audit-details"><?= e($log['details']) ?></td>
                        <td class="notif-time"><?= e($log['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= build_query(['page' => $page - 1]) ?>" class="btn-small">‹ Prev</a>
            <?php endif; ?>

            <?php
            // Show a windowed page range instead of every page number for large result sets
            $windowStart = max(1, $page - 2);
            $windowEnd = min($totalPages, $page + 2);
            if ($windowStart > 1) echo '<span class="pagination-ellipsis">…</span>';
            for ($p = $windowStart; $p <= $windowEnd; $p++):
            ?>
                <a href="?<?= build_query(['page' => $p]) ?>" class="btn-small <?= $p === $page ? 'btn-primary' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($windowEnd < $totalPages) echo '<span class="pagination-ellipsis">…</span>'; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?<?= build_query(['page' => $page + 1]) ?>" class="btn-small">Next ›</a>
            <?php endif; ?>

            <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?> · <?= $total ?> entries</span>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>