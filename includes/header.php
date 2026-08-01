<?php
/**
 * includes/header.php
 * Shared <head> + opening body markup. Include after require_login()
 * and after any page-specific $pageTitle is set.
 */
$pageTitle = $pageTitle ?? 'Reception Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
    <?php if (!empty($_SESSION['user_id'])) include __DIR__ . '/nav.php'; ?>
    <main class="container">