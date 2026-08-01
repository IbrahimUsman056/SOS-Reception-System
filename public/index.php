<?php
/**
 * public/index.php
 * Simple entry point: bounce to dashboard if logged in, else login.
 */
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;