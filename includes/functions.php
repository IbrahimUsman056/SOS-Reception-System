<?php
/**
 * includes/functions.php
 * Small shared helpers used across the app.
 */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function validate_image_upload(array $file): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'skipped' => true];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed (error code ' . $file['error'] . ').'];
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['ok' => false, 'message' => 'File exceeds the 5MB size limit.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        return ['ok' => false, 'message' => 'Only JPEG, PNG, or WEBP images are allowed.'];
    }

    return ['ok' => true, 'mime' => $mime];
}

function store_upload(array $file, string $destDir): string
{
    $ext = match ($file['mime'] ?? '') {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default => 'bin',
    };
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return $filename;
}

/**
 * Renders an avatar image if the user has one, otherwise a circle with
 * their initials — used consistently in nav, dashboard, and admin table
 * so avatar display never has to be reimplemented per page.
 */
function avatar_html(?string $avatarUrl, string $fullName, string $size = '36px'): string
{
    if (!empty($avatarUrl)) {
        return '<img src="' . e($avatarUrl) . '" alt="' . e($fullName) . '" class="avatar-img" '
             . 'style="width:' . e($size) . '; height:' . e($size) . ';">';
    }
    $initials = strtoupper(substr(trim($fullName), 0, 1) ?: '?');
    return '<div class="avatar-circle" style="width:' . e($size) . '; height:' . e($size) . '; font-size:calc(' . e($size) . ' * 0.4);">'
         . e($initials) . '</div>';
}