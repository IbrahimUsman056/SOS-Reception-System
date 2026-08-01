<?php
/**
 * includes/nav.php
 * Self-contained requires so this include never breaks depending on
 * what the calling page already loaded.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/functions.php'; // for avatar_html()

$unreadCount = get_unread_count(current_user_id());
?>
<style>
    .nav-brand a,
    .nav-brand a:visited {
        color: inherit;
        text-decoration: none;
    }
    .nav-links a.active {
        font-weight: bold;
    }
</style>
<nav class="navbar">
    <div class="nav-brand">
        <a href="<?= BASE_URL ?>/dashboard.php">
            <img src="<?= ASSET_URL ?>/images/logo.png" alt="SOS Reception Logo" style="height: 27px; vertical-align: middle; margin-right: 8px;">
            SOS Reception
        </a>
    </div>
    <div class="nav-links">
        <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/records/view.php?type=receiving">Receiving</a>
        <a href="<?= BASE_URL ?>/records/view.php?type=dispatch">Dispatch</a>
        <a href="<?= BASE_URL ?>/scan.php">Scan</a>

        <?php if (can('reports.view.own') || can('reports.view.department') || can('reports.view.org')): ?>
            <a href="<?= BASE_URL ?>/reports.php">Reports</a>
        <?php endif; ?>

        <?php if (can('users.manage')): ?>
            <a href="<?= BASE_URL ?>/admin/users.php">Users</a>
        <?php endif; ?>

        <?php if (can('activity_logs.view.department') || can('activity_logs.view.all')): ?>
            <a href="<?= BASE_URL ?>/admin/activity_logs.php">Audit Log</a>
        <?php endif; ?>

        <div class="notif-bell" id="notifBell">
            🔔<span id="notifBadge" class="notif-badge" style="<?= $unreadCount ? '' : 'display:none;' ?>"><?= $unreadCount ?></span>
            <div id="notifDropdown" class="notif-dropdown" style="display:none;">
                <div class="notif-dropdown-header">
                    <span>Notifications</span>
                    <button id="markAllReadBtn" class="notif-mark-all">Mark all read</button>
                </div>
                <div id="notifList" class="notif-list"><p class="notif-empty">Loading...</p></div>
            </div>
        </div>

        <a href="<?= BASE_URL ?>/profile.php" class="nav-profile-link">
            <?= avatar_html($_SESSION['avatar'] ?? null, $_SESSION['full_name'] ?? '', '28px') ?>
            <span><?= e($_SESSION['full_name'] ?? '') ?></span>
        </a>

        <a href="#" class="nav-logout" id="logoutTrigger">Logout</a>
    </div>
</nav>

<div id="logoutModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Log out?</h3>
        <p>You'll need to sign in again to access the reception system.</p>
        <div class="modal-actions">
            <button id="logoutCancelBtn" class="btn">Cancel</button>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger-solid">Log Out</a>
        </div>
    </div>
</div>

<script>
(function() {
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    const logoutTrigger = document.getElementById('logoutTrigger');
    const logoutModal = document.getElementById('logoutModal');
    const logoutCancelBtn = document.getElementById('logoutCancelBtn');

    logoutTrigger.addEventListener('click', (e) => {
        e.preventDefault();
        logoutModal.style.display = 'flex';
    });
    logoutCancelBtn.addEventListener('click', () => {
        logoutModal.style.display = 'none';
    });
    logoutModal.addEventListener('click', (e) => {
        if (e.target === logoutModal) logoutModal.style.display = 'none'; // click outside box to dismiss
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') logoutModal.style.display = 'none';
    });
    
    const bell = document.getElementById('notifBell');
    const dropdown = document.getElementById('notifDropdown');
    const badge = document.getElementById('notifBadge');
    const list = document.getElementById('notifList');

    const currentUrl = window.location.href;
    document.querySelectorAll('.nav-links a').forEach(link => {
        if (link.href === currentUrl) {
            link.classList.add('active');
        }
    });

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr.replace(' ', 'T'))) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function loadNotifications() {
        fetch('<?= BASE_URL ?>/notifications_ajax.php?action=list')
            .then(r => r.json())
            .then(data => {
                badge.textContent = data.unread_count;
                badge.style.display = data.unread_count > 0 ? 'inline-block' : 'none';

                if (!data.notifications.length) {
                    list.innerHTML = '<p class="notif-empty">No notifications yet.</p>';
                    return;
                }
                list.innerHTML = data.notifications.map(n => `
                    <div class="notif-item ${n.is_read == 0 ? 'notif-unread' : ''}" data-id="${n.id}">
                        <strong>${n.title}</strong>
                        <p>${n.message}</p>
                        <span class="notif-time">${timeAgo(n.created_at)}</span>
                    </div>
                `).join('');
            });
    }

    bell.addEventListener('click', (e) => {
        const isOpen = dropdown.style.display === 'block';
        dropdown.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) loadNotifications();
        e.stopPropagation();
    });

    document.addEventListener('click', (e) => {
        if (!bell.contains(e.target)) dropdown.style.display = 'none';
    });

    list.addEventListener('click', (e) => {
        const item = e.target.closest('.notif-item');
        if (!item) return;
        fetch('<?= BASE_URL ?>/notifications_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=mark_read&id=${item.dataset.id}&csrf_token=${encodeURIComponent(csrfToken)}`
        }).then(() => { item.classList.remove('notif-unread'); loadNotifications(); });
    });

    document.getElementById('markAllReadBtn').addEventListener('click', () => {
        fetch('<?= BASE_URL ?>/notifications_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=mark_all_read&csrf_token=${encodeURIComponent(csrfToken)}`
        }).then(loadNotifications);
    });

    setInterval(() => { fetch('<?= BASE_URL ?>/notifications_ajax.php?action=list').then(r => r.json()).then(data => {
        badge.textContent = data.unread_count;
        badge.style.display = data.unread_count > 0 ? 'inline-block' : 'none';
    }); }, 30000);
})();
</script>