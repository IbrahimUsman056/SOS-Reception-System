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
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $result = login_user($email, $password);
        if ($result['ok']) {
            redirect('/dashboard.php');
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body class="auth-page auth-page-centered">
    <div class="auth-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
    </div>

    <div class="auth-card-centered">
        <div class="auth-card-header">
            <img src="<?= ASSET_URL ?>/images/logo.png" alt="SOS Reception Logo" class="auth-logo-centered">
            <h1>Welcome back</h1>
            <p>Log in to your SOS Reception account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="auth-form-centered">
            <?= csrf_field() ?>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus placeholder="you@sos-security.com">

            <label for="password">Password</label>
            <div class="password-field">
                <input type="password" id="password" name="password" required placeholder="••••••••">
                <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">👁️</button>
            </div>

            <button type="submit" class="btn-full">Log In</button>
        </form>

        <p class="auth-switch-link">Don't have an account? <a href="register.php">Register here</a></p>
    </div>

    <script>
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        toggleBtn.addEventListener('click', () => {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            toggleBtn.textContent = isHidden ? '🙈' : '👁️';
            toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    </script>
</body>
</html>