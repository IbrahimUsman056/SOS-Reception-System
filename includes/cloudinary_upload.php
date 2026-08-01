<?php
/**
 * includes/cloudinary_upload.php
 * Signed upload to Cloudinary via direct API call (no SDK dependency).
 * Signing is done server-side so the API secret never touches the client.
 */

require_once __DIR__ . '/../config/cloudinary.php';

/**
 * Upload a validated local file to Cloudinary and return its secure URL
 * plus public_id (needed later if you want to delete/replace the asset).
 *
 * @param string $tmpFilePath Path to the temp uploaded file ($_FILES[...]['tmp_name'])
 * @param string $subfolder   e.g. 'attachments' or 'signatures'
 * @return array ['ok' => bool, 'url' => string, 'public_id' => string, 'message' => string]
 */
function cloudinary_upload(string $tmpFilePath, string $subfolder = 'attachments'): array
{
    $timestamp = time();
    $folder = CLOUDINARY_UPLOAD_FOLDER . '/' . $subfolder;

    // Cloudinary requires a signature = sha1 of all params (except file/api_key)
    // sorted alphabetically, concatenated as key=value&key=value, + api_secret appended.
    $paramsToSign = [
        'folder' => $folder,
        'timestamp' => $timestamp,
    ];
    ksort($paramsToSign);
    $signatureString = '';
    foreach ($paramsToSign as $key => $value) {
        $signatureString .= ($signatureString ? '&' : '') . "{$key}={$value}";
    }
    $signature = sha1($signatureString . CLOUDINARY_API_SECRET);

    $postFields = [
        'file' => new CURLFile($tmpFilePath),
        'api_key' => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'folder' => $folder,
        'signature' => $signature,
    ];

    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/upload';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('Cloudinary upload cURL error: ' . $curlError);
        return ['ok' => false, 'message' => 'Upload service unreachable. Please try again.'];
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200 || empty($data['secure_url'])) {
        error_log('Cloudinary upload failed: ' . $response);
        return ['ok' => false, 'message' => $data['error']['message'] ?? 'Upload failed.'];
    }

    return [
        'ok' => true,
        'url' => $data['secure_url'],
        'public_id' => $data['public_id'],
    ];
}

/**
 * Delete an asset from Cloudinary by public_id (used when a record's
 * attachment is replaced or the record is deleted).
 */
function cloudinary_delete(string $publicId): bool
{
    $timestamp = time();
    $paramsToSign = ['public_id' => $publicId, 'timestamp' => $timestamp];
    ksort($paramsToSign);
    $signatureString = '';
    foreach ($paramsToSign as $key => $value) {
        $signatureString .= ($signatureString ? '&' : '') . "{$key}={$value}";
    }
    $signature = sha1($signatureString . CLOUDINARY_API_SECRET);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/destroy',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'public_id' => $publicId,
            'api_key' => CLOUDINARY_API_KEY,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return ($data['result'] ?? '') === 'ok';
}