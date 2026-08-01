<?php
/**
 * includes/mailer.php
 * Sends notification emails via PHPMailer/SMTP (Gmail or any SMTP host).
 * Configure via environment variables — never hardcode credentials.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: ''); // Gmail: use an App Password, not your real password
define('SMTP_FROM_NAME', 'Reception Management System');

function send_notification_email(int $userId, string $subject, string $message): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT email, full_name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email'])) {
        throw new RuntimeException("No email on file for user #{$userId}");
    }

    if (SMTP_USER === '' || SMTP_PASS === '') {
        // Not configured yet — fail loudly in logs but don't break the request.
        throw new RuntimeException('SMTP credentials not configured (SMTP_USER/SMTP_PASS env vars empty).');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;

    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress($user['email'], $user['full_name']);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = '<p>' . nl2br(htmlspecialchars($message)) . '</p>'
                . '<p style="color:#94a3b8; font-size:12px;">— Reception Management System</p>';

    $mail->send();
}