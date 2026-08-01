<?php
/**
 * includes/auth.php
 * Registration, login, logout, and session-guard logic.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/activity_logger.php';

/**
 * Rate limiting config. Tuned to stop brute-force without punishing a
 * legitimate user who mistypes their password a couple of times.
 */
const MAX_LOGIN_ATTEMPTS_PER_EMAIL = 5;   // tight — targets one account being guessed
const MAX_LOGIN_ATTEMPTS_PER_IP = 20;     // loose — targets one IP spraying many accounts
const LOGIN_LOCKOUT_MINUTES = 15;

function register_user(string $fullName, string $email, string $password, string $department = '', string $phone = ''): array
{
    $db = Database::getConnection();

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'message' => 'An account with that email already exists.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare(
        'INSERT INTO users (full_name, email, password, role, department, phone)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    // Self-registration always defaults to receptionist.
    $stmt->execute([$fullName, $email, $hash, 'receptionist', $department, $phone]);

    return ['ok' => true, 'user_id' => $db->lastInsertId()];
}

function login_user(string $email, string $password): array
{
    $db = Database::getConnection();

    $rateCheck = is_rate_limited($email);
    if ($rateCheck['limited']) {
        log_activity(null, 'login_rate_limited', "Blocked login attempt for {$email} — too many recent failures.");
        return [
            'ok' => false,
            'message' => "Too many failed login attempts. Please try again in " . LOGIN_LOCKOUT_MINUTES . " minutes.",
        ];
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        log_activity(null, 'login_failed', "Failed login attempt for email: {$email}");
        return ['ok' => false, 'message' => 'Invalid email or password.'];
    }

    if (!$user['is_active']) {
        log_activity($user['id'], 'login_blocked', 'Login attempt on deactivated account.');
        return ['ok' => false, 'message' => 'This account has been deactivated. Contact an administrator.'];
    }

    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['department']    = $user['department'];
    $_SESSION['last_activity'] = time();
    $_SESSION['avatar'] = $user['avatar'];

    $update = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $update->execute([$user['id']]);

    log_activity($user['id'], 'login_success', 'User logged in.');

    return ['ok' => true];
}

function logout_user(): void
{
    if (isset($_SESSION['user_id'])) {
        log_activity($_SESSION['user_id'], 'logout', 'User logged out.');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('/login.php');
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        logout_user();
        flash('error', 'Your session expired due to inactivity. Please log in again.');
        redirect('/login.php');
    }

    $_SESSION['last_activity'] = time();
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Checks email-based and IP-based failure counts SEPARATELY, with
 * different thresholds. This prevents one user's failed attempts from
 * locking out other users sharing the same IP (office network, NAT, etc).
 */
function is_rate_limited(string $email): array
{
    $db = Database::getConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Per-email check
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM activity_logs
         WHERE action = 'login_failed'
           AND created_at >= (NOW() - INTERVAL :minutes MINUTE)
           AND details = :details"
    );
    $stmt->bindValue(':minutes', LOGIN_LOCKOUT_MINUTES, PDO::PARAM_INT);
    $stmt->bindValue(':details', "Failed login attempt for email: {$email}", PDO::PARAM_STR);
    $stmt->execute();
    $emailAttempts = (int)$stmt->fetchColumn();

    if ($emailAttempts >= MAX_LOGIN_ATTEMPTS_PER_EMAIL) {
        return ['limited' => true, 'reason' => 'email'];
    }

    // Per-IP check (separate query, much higher threshold)
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM activity_logs
         WHERE action = 'login_failed'
           AND created_at >= (NOW() - INTERVAL :minutes MINUTE)
           AND ip_address = :ip"
    );
    $stmt->bindValue(':minutes', LOGIN_LOCKOUT_MINUTES, PDO::PARAM_INT);
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();
    $ipAttempts = (int)$stmt->fetchColumn();

    if ($ipAttempts >= MAX_LOGIN_ATTEMPTS_PER_IP) {
        return ['limited' => true, 'reason' => 'ip'];
    }

    return ['limited' => false, 'reason' => null];
}