<?php
/**
 * includes/security_headers.php
 * Sets defensive HTTP headers on every response. Include this at the
 * very top of config.php (before session_start) so it applies globally.
 */

header('X-Content-Type-Options: nosniff');       // stop MIME-sniffing attacks
header('X-Frame-Options: DENY');                  // prevent clickjacking via iframe embedding
header('Referrer-Policy: strict-origin-when-cross-origin');
header("X-XSS-Protection: 0"); // deprecated header, explicitly disabled rather than left ambiguous

// Content-Security-Policy: allow the CDN scripts/styles this app actually
// uses (DataTables, jQuery, Chart.js, html5-qrcode, signature_pad), plus
// Cloudinary for uploaded images. Tighten further if you remove any of these.
header("Content-Security-Policy: default-src 'self'; "
     . "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.datatables.net "
     . "https://cdn.jsdelivr.net https://unpkg.com; "
     . "style-src 'self' 'unsafe-inline' https://cdn.datatables.net; "
     . "img-src 'self' data: https://res.cloudinary.com; "
     . "connect-src 'self' https://api.cloudinary.com;");