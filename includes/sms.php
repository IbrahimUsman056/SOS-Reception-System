<?php
/**
 * includes/sms.php
 * Sends SMS via TextBee (textbee.dev) — uses a registered Android device
 * as the SMS gateway. Works reliably in regions (like Pakistan) where
 * Twilio's local number/SMS support is limited or unavailable.
 */

require_once __DIR__ . '/../config/database.php';

define('TEXTBEE_API_KEY', getenv('TEXTBEE_API_KEY') ?: '');
define('TEXTBEE_DEVICE_ID', getenv('TEXTBEE_DEVICE_ID') ?: '');
define('TEXTBEE_API_URL', 'https://api.textbee.dev/api/v1/gateway/devices/');

function send_notification_sms(int $userId, string $message): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT phone FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['phone'])) {
        throw new RuntimeException("No phone number on file for user #{$userId}");
    }
    if (TEXTBEE_API_KEY === '' || TEXTBEE_DEVICE_ID === '') {
        throw new RuntimeException('TextBee credentials not configured (TEXTBEE_API_KEY/TEXTBEE_DEVICE_ID).');
    }

    $url = TEXTBEE_API_URL . TEXTBEE_DEVICE_ID . '/send-sms';

    $payload = json_encode([
        'recipients' => [$user['phone']],
        'message' => mb_substr($message, 0, 300), // keep SMS-length reasonable
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . TEXTBEE_API_KEY,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new RuntimeException('TextBee request failed: ' . $curlError);
    }
    if ($httpCode >= 400) {
        throw new RuntimeException('TextBee SMS failed (HTTP ' . $httpCode . '): ' . $response);
    }
}