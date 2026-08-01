<?php
/**
 * public/barcode.php
 * Streams a Code128 barcode PNG for a given tracking number.
 * Usage: <img src="barcode.php?text=TRK123456">
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

require_login(); // barcodes contain internal tracking data, keep this behind auth

$text = trim($_GET['text'] ?? '');

if ($text === '') {
    http_response_code(400);
    die('Missing tracking number.');
}

// Basic sanity limit — barcode encoders choke on absurdly long strings.
if (strlen($text) > 64) {
    http_response_code(400);
    die('Tracking number too long to encode.');
}

$generator = new BarcodeGeneratorPNG();
$imageData = $generator->getBarcode($text, $generator::TYPE_CODE_128, 2, 60);

header('Content-Type: image/png');
header('Cache-Control: private, max-age=3600'); // barcodes for a given tracking # never change
echo $imageData;