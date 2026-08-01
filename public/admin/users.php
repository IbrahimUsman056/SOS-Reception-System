<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../config/database.php';

require_login();
require_permission('users.manage');

$db = Database::getConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $targetId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($targetId === current_user_id() && $action !== 'noop') {
        $error = "You can't change your own role or active status from this panel.";
    } elseif ($action === 'update_role') {
        $newRole = $_POST['role'] ?? '';
        if (in_array($newRole, ['admin', 'manager', 'receptionist'], true)) {
            $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$newRole, $targetId]);
            log_activity(current_user_id(), 'user_role_changed', "Changed user #{$targetId} role to {$newRole}");
            flash('success', 'Role updated.');
        }
    } elseif ($action === 'toggle_active') {
        $stmt = $db->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$targetId]);
        log_activity(current_user_id(), 'user_status_toggled', "Toggled active status for user #{$targetId}");
        flash('success', 'User status updated.');
    }

    // Preserve current filters/page after a POST action redirect
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    redirect('/admin/users.php' . ($qs ? '?' . $qs : ''));
}

// ---- Filters ----
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role_filter'] ?? '';
$statusFilter = $_GET['status_filter'] ?? ''; // 'active' | 'inactive'
$departmentFilter = $_GET['department_filter'] ?? '';

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($roleFilter !== '' && in_array($roleFilter, ['admin', 'manager', 'receptionist'], true)) {
    $where[] = 'role = ?';
    $params[] = $roleFilter;
}
if ($statusFilter === 'active') {
    $where[] = 'is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'is_active = 0';
}
if ($departmentFilter !== '') {
    $where[] = 'department = ?';
    $params[] = $departmentFilter;
}

$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE {$whereSql}");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));

$sql = "SELECT id, full_name, email, role, department, phone, is_active, last_login, avatar
        FROM users
        WHERE {$whereSql}
        ORDER BY full_name
        LIMIT {$perPage} OFFSET {$offset}"; // both ints, hardcoded/cast above — safe to interpolate

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Org-wide stats (unfiltered, for the top summary strip)
$allUsers = $db->query('SELECT role, is_active FROM users')->fetchAll();
$totalUsers = count($allUsers);
$activeUsers = count(array_filter($allUsers, fn($u) => $u['is_active']));
$adminCount = count(array_filter($allUsers, fn($u) => $u['role'] === 'admin'));
$managerCount = count(array_filter($allUsers, fn($u) => $u['role'] === 'manager'));

// Distinct departments for the filter dropdown
$deptListStmt = $db->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$deptList = $deptListStmt->fetchAll(PDO::FETCH_COLUMN);

function build_users_query(array $overrides = []): string
{
    $current = [
        'search' => $_GET['search'] ?? '',
        'role_filter' => $_GET['role_filter'] ?? '',
        'status_filter' => $_GET['status_filter'] ?? '',
        'department_filter' => $_GET['department_filter'] ?? '',
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
    <title>User Management - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <main class="container">
        <section class="greeting-banner users-banner">
            <div>
                <h1>User Management</h1>
                <p>Manage roles, access, and account status across the organization.</p>
            </div>
            <!-- <div class="banner-illustration">🗂️</div> -->
        </section>

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="stats-grid">
            <div class="stat-card stat-teal">
                <div class="stat-icon">👤</div>
                <div><h3><?= $totalUsers ?></h3><p>Total Users</p></div>
            </div>
            <div class="stat-card stat-blue">
                <div class="stat-icon">✅</div>
                <div><h3><?= $activeUsers ?></h3><p>Active</p></div>
            </div>
            <div class="stat-card stat-purple">
                <div class="stat-icon">🛡️</div>
                <div><h3><?= $adminCount ?></h3><p>Admins</p></div>
            </div>
            <div class="stat-card stat-orange">
                <div class="stat-icon">📋</div>
                <div><h3><?= $managerCount ?></h3><p>Managers</p></div>
            </div>
        </section>

        <form method="GET" class="filters-bar filters-card">
            <input type="text" name="search" placeholder="Search name, email, phone..." value="<?= e($search) ?>" class="filter-search">
            <select name="role_filter">
                <option value="">All Roles</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="manager" <?= $roleFilter === 'manager' ? 'selected' : '' ?>>Manager</option>
                <option value="receptionist" <?= $roleFilter === 'receptionist' ? 'selected' : '' ?>>Receptionist</option>
            </select>
            <select name="status_filter">
                <option value="">All Statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <select name="department_filter">
                <option value="">All Departments</option>
                <?php foreach ($deptList as $d): ?>
                    <option value="<?= e($d) ?>" <?= $departmentFilter === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Apply</button>
            <?php if ($search || $roleFilter || $statusFilter || $departmentFilter): ?>
                <a href="users.php" class="btn">Clear</a>
            <?php endif; ?>
        </form>

        <div class="audit-table-card">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th></th><th>Name</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">No users found matching your filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= avatar_html($u['avatar'], $u['full_name'], '36px') ?></td>
                        <td><strong><?= e($u['full_name']) ?></strong><?= $u['id'] == current_user_id() ? ' <span class="you-tag">You</span>' : '' ?></td>
                        <td class="notif-time"><?= e($u['email']) ?></td>
                        <td><?= e($u['department'] ?? '—') ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="action" value="update_role">
                                <select name="role" class="role-select role-<?= e($u['role']) ?>" onchange="this.form.submit()" <?= $u['id'] == current_user_id() ? 'disabled' : '' ?>>
                                    <?php foreach (['receptionist', 'manager', 'admin'] as $r): ?>
                                        <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td><?= $u['is_active'] ? '<span class="badge-active">Active</span>' : '<span class="badge-inactive">Inactive</span>' ?></td>
                        <td class="notif-time"><?= $u['last_login'] ? e(date('M j, Y g:i A', strtotime($u['last_login']))) : 'Never' ?></td>
                        <td>
                            <?php if ($u['id'] != current_user_id()): ?>
                            <form method="POST" class="inline-form" onsubmit="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <button type="submit" class="btn-small <?= $u['is_active'] ? 'btn-deactivate' : 'btn-activate' ?>"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <?php else: ?>
                                <span class="notif-time">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= build_users_query(['page' => $page - 1]) ?>" class="btn-small">‹ Prev</a>
            <?php endif; ?>

            <?php
            $windowStart = max(1, $page - 2);
            $windowEnd = min($totalPages, $page + 2);
            if ($windowStart > 1) echo '<span class="pagination-ellipsis">…</span>';
            for ($p = $windowStart; $p <= $windowEnd; $p++):
            ?>
                <a href="?<?= build_users_query(['page' => $p]) ?>" class="btn-small <?= $p === $page ? 'btn-primary' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($windowEnd < $totalPages) echo '<span class="pagination-ellipsis">…</span>'; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?<?= build_users_query(['page' => $page + 1]) ?>" class="btn-small">Next ›</a>
            <?php endif; ?>

            <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalFiltered ?> users</span>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>