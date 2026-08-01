<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($fullName === '' || $email === '' || $password === '') {
        $error = 'Full name, email, and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $result = register_user($fullName, $email, $password, $department, $phone);
        if ($result['ok']) {
            flash('success', 'Account created. You can now log in.');
            redirect('/login.php');
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body class="auth-page auth-page-centered">
    <div class="auth-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
    </div>

    <div class="auth-card-centered auth-card-wide">
        <div class="auth-card-header">
            <img src="<?= ASSET_URL ?>/images/logo.png" alt="SOS Reception Logo" class="auth-logo-centered">
            <h1>Create your account</h1>
            <p>Join the front desk team at SOS Reception</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="auth-form-centered">
            <?= csrf_field() ?>

            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="Ibrahim Usman">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@sos-security.com">

            <div class="form-row">
                <div>
                    <label for="department">Department</label>
                    <input type="text" id="department" name="department" value="<?= e($_POST['department'] ?? '') ?>" placeholder="Front Desk">
                </div>
                <div>
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+92 300 1234567">
                </div>
            </div>

            <label for="password">Password</label>
            <div class="password-field">
                <input type="password" id="password" name="password" required minlength="8" placeholder="At least 8 characters">
                <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">👁️</button>
            </div>

            <label for="confirm_password">Confirm Password</label>
            <div class="password-field">
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Re-enter password">
                <button type="button" class="password-toggle" id="toggleConfirmPassword" aria-label="Show password">👁️</button>
            </div>

            <button type="submit" class="btn-full">Register</button>
        </form>
        
        <p class="auth-switch-link">Already have an account? <a href="login.php">Log in</a></p>
    </div>

    <script>
        function setupToggle(buttonId, inputId) {
            const btn = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            btn.addEventListener('click', () => {
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                btn.textContent = isHidden ? '🙈' : '👁️';
                btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            });
        }
        setupToggle('togglePassword', 'password');
        setupToggle('toggleConfirmPassword', 'confirm_password');
    </script>
</body>
</html>