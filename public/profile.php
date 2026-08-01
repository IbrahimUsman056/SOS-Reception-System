<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/cloudinary_upload.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$db = Database::getConnection();
$userId = current_user_id();

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die('User not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    // Email and role are intentionally NOT editable here — email changes
    // need re-verification, and role changes are admin-only (admin/users.php).

    if ($fullName === '') {
        $error = 'Full name cannot be empty.';
    } else {
        $avatarUrl = $user['avatar'];

        if (!empty($_FILES['avatar']['name'])) {
            $check = validate_image_upload($_FILES['avatar']);
            if (!$check['ok']) {
                $error = $check['message'];
            } else {
                $upload = cloudinary_upload($_FILES['avatar']['tmp_name'], 'avatars');
                if (!$upload['ok']) {
                    $error = 'Avatar upload failed: ' . $upload['message'];
                } else {
                    $avatarUrl = $upload['url'];
                }
            }
        }

        if ($error === '') {
            $update = $db->prepare('UPDATE users SET full_name = ?, phone = ?, avatar = ? WHERE id = ?');
            $update->execute([$fullName, $phone, $avatarUrl, $userId]);

            $_SESSION['full_name'] = $fullName;
            $_SESSION['avatar'] = $avatarUrl;

            log_activity($userId, 'profile_updated', 'User updated their own profile.');
            flash('success', 'Profile updated.');
            redirect('/profile.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="container">
        <section class="greeting-banner profile-banner">
            <div>
                <h1>My Profile</h1>
                <p>Manage your personal details and profile picture.</p>
            </div>
            <!-- <div class="banner-illustration">🪪</div> -->
        </section>

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="profile-layout">

            <div class="profile-card profile-summary-card">
                <?= avatar_html($user['avatar'], $user['full_name'], '96px') ?>
                <h3><?= e($user['full_name']) ?></h3>
                <span class="role-badge role-badge-<?= e($user['role']) ?>"><?= e(ucfirst($user['role'])) ?></span>
                <p class="profile-department"><?= $user['department'] ? '🏢 ' . e($user['department']) : '' ?></p>
                <p class="profile-email">✉️ <?= e($user['email']) ?></p>
                <?php if ($user['phone']): ?>
                    <p class="profile-phone">📞 <?= e($user['phone']) ?></p>
                <?php endif; ?>
                <p class="profile-since">Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
            </div>

            <div class="profile-card profile-form-card">
                <h3>Edit Details</h3>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <label for="avatar">Profile Picture</label>
                    <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
                    <p class="field-hint">JPEG, PNG, or WEBP — max 5MB.</p>

                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required value="<?= e($user['full_name']) ?>">

                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+92 300 1234567">

                    <label>Email <span class="field-locked">🔒</span></label>
                    <input type="email" value="<?= e($user['email']) ?>" disabled>

                    <label>Role <span class="field-locked">🔒</span></label>
                    <input type="text" value="<?= e(ucfirst($user['role'])) ?>" disabled>

                    <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
                </form>
            </div>

        </div>
    </main>
</body>
</html>