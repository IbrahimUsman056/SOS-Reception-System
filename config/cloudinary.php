<?php
/**
 * config/cloudinary.php
 * Cloudinary credentials. Get these from your Cloudinary dashboard.
 * Never commit real values — use environment variables in production.
 */

define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: 'cloud-name');
define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY') ?: 'api-key');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: 'api-secret');
define('CLOUDINARY_UPLOAD_FOLDER', 'folder-name');
