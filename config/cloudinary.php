<?php
/**
 * config/cloudinary.php
 * Cloudinary credentials. Get these from your Cloudinary dashboard.
 * Never commit real values — use environment variables in production.
 */

define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: 'mmx9fung');
define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY') ?: '478484111137792');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: '53xfbQITE2E1GBIRc-HRwdkY54g');
define('CLOUDINARY_UPLOAD_FOLDER', 'reception-system');