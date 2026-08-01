<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/cloudinary_upload.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../config/database.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('/records/view.php');
}

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT reception_logs.*, u.department AS owner_department
     FROM reception_logs
     JOIN users u ON reception_logs.created_by = u.id
     WHERE reception_logs.id = ?'
);
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(404);
    die('Record not found.');
}

if (!can_view_record($record)) {
    http_response_code(403);
    log_activity(current_user_id(), 'access_denied', "Attempted to view record #{$id} outside of scope.");
    die('You do not have permission to view this record.');
}

$canEdit = can_edit_record($record);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$canEdit) {
        http_response_code(403);
        log_activity(current_user_id(), 'access_denied', "Attempted to edit record #{$id} outside of scope.");
        die('You do not have permission to edit this record.');
    }

    $status = $_POST['status'] ?? $record['status'];
    $deliveredTo = trim($_POST['delivered_to'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $signatureData = $_POST['signature_data'] ?? '';

    $deliveredAt = null;
    if ($status === 'delivered' && $record['status'] !== 'delivered') {
        $deliveredAt = date('Y-m-d H:i:s');
    }

    $signaturePath = $record['signature_path'];

    if ($signatureData !== '' && str_starts_with($signatureData, 'data:image/png;base64,')) {
        $base64 = substr($signatureData, strlen('data:image/png;base64,'));
        $binary = base64_decode($base64, true);

        if ($binary !== false) {
            $tmpPath = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
            file_put_contents($tmpPath, $binary);

            $upload = cloudinary_upload($tmpPath, 'signatures');
            @unlink($tmpPath);

            if ($upload['ok']) {
                $signaturePath = $upload['url'];
            } else {
                $error = 'Signature upload failed: ' . $upload['message'];
            }
        }
    }

    if (empty($error)) {
        $sql = 'UPDATE reception_logs SET status = ?, delivered_to = ?, notes = ?, signature_path = ?';
        $params = [$status, $deliveredTo, $notes, $signaturePath];

        if ($deliveredAt) {
            $sql .= ', delivered_at = ?';
            $params[] = $deliveredAt;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;

        $update = $db->prepare($sql);
        $update->execute($params);

        if ($status === 'delivered' && $record['status'] !== 'delivered') {
            notify_user(
                (int)$record['created_by'],
                'Package Delivered',
                "Record #{$id} ({$record['employee_name']}, {$record['building']}) has been marked delivered.",
                $id,
                ['in_app']
            );
        }

        log_activity(current_user_id(), 'record_updated', "Updated reception_logs record #{$id}");
        flash('success', 'Record updated.');
        redirect('/records/edit.php?id=' . $id);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record #<?= (int)$record['id'] ?> - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <main class="container">
        <div class="record-page-header">
            <div>
                <h1>Record #<?= (int)$record['id'] ?> <span class="type-pill type-<?= e($record['type']) ?>"><?= e(ucfirst($record['type'])) ?></span></h1>
                <p><?= e($record['employee_name']) ?> · <?= e($record['building']) ?></p>
            </div>
            <span class="status-pill status-<?= e($record['status']) ?> status-pill-lg"><?= e(ucfirst(str_replace('_',' ',$record['status']))) ?></span>
        </div>

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$canEdit): ?>
            <p class="notice">🔒 You have read-only access to this record.</p>
        <?php endif; ?>

        <div class="record-layout-2col">

            <!-- LEFT: read-only details -->
            <div class="record-read-col">

                <div class="profile-card">
                    <h3>Package Details</h3>
                    <dl class="record-details">
                        <dt>Employee</dt><dd><?= e($record['employee_name']) ?> (<?= e($record['designation'] ?? '—') ?>)</dd>
                        <dt>Building</dt><dd><?= e($record['building']) ?></dd>
                        <dt>Package Detail</dt><dd><?= e($record['package_detail'] ?? '—') ?></dd>
                        <dt>Weight / Dimensions</dt><dd><?= e($record['package_weight'] ?? '—') ?> kg / <?= e($record['package_dimensions'] ?? '—') ?></dd>
                        <dt>Mode of Transit</dt><dd><?= e($record['mode_of_transit'] ?? '—') ?></dd>
                        <dt>Address</dt><dd><?= e($record['address'] ?? '—') ?><?= $record['city'] ? ', ' . e($record['city']) : '' ?></dd>
                        <dt>Date/Time</dt><dd><?= e(date('M j, Y g:i A', strtotime($record['date_time']))) ?></dd>
                    </dl>
                </div>

                <?php if (!empty($record['tracking_number'])): ?>
                    <div class="profile-card barcode-preview">
                        <h3>Barcode</h3>
                        <div class="barcode-scroll">
                            <img src="<?= BASE_URL ?>/barcode.php?text=<?= urlencode($record['tracking_number']) ?>" alt="Barcode">
                        </div>
                        <p class="barcode-label"><?= e($record['tracking_number']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($record['attachment'])): ?>
                    <div class="profile-card attachment-preview">
                        <h3>Attachment</h3>
                        <img src="<?= e($record['attachment']) ?>" alt="Package attachment">
                    </div>
                <?php endif; ?>

            </div>

            <!-- RIGHT: update form -->
            <div class="record-update-col">
                <div class="profile-card record-update-card">
                    <h3>Update Status</h3>
                    <form method="POST" action="edit.php?id=<?= (int)$record['id'] ?>">
                        <?= csrf_field() ?>

                        <label for="status">Status</label>
                        <select id="status" name="status" <?= $canEdit ? '' : 'disabled' ?>>
                            <?php foreach (['pending', 'in_transit', 'delivered', 'returned'] as $s): ?>
                                <option value="<?= $s ?>" <?= $record['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="delivered_to">Delivered To</label>
                        <input type="text" id="delivered_to" name="delivered_to" value="<?= e($record['delivered_to'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>

                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3" <?= $canEdit ? '' : 'disabled' ?>><?= e($record['notes'] ?? '') ?></textarea>

                        <?php if ($canEdit): ?>
                        <div class="signature-section">
                            <label>Recipient Signature</label>
                            <?php if (!empty($record['signature_path'])): ?>
                                <div class="signature-preview">
                                    <img src="<?= e($record['signature_path']) ?>" alt="Captured signature">
                                    <p class="notif-time">Signature already captured. Signing again will replace it.</p>
                                </div>
                            <?php endif; ?>
                            <canvas id="signaturePad" width="400" height="150"></canvas>
                            <div class="signature-actions">
                                <button type="button" id="clearSignatureBtn" class="btn-small">Clear</button>
                                <span id="signatureStatus" class="notif-time"></span>
                            </div>
                            <input type="hidden" name="signature_data" id="signatureData">
                        </div>

                        <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script>
        const canvas = document.getElementById('signaturePad');
        if (canvas) {
            const signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)' });
            const statusEl = document.getElementById('signatureStatus');

            document.getElementById('clearSignatureBtn').addEventListener('click', () => {
                signaturePad.clear();
                statusEl.textContent = '';
            });

            canvas.closest('form').addEventListener('submit', () => {
                if (!signaturePad.isEmpty()) {
                    document.getElementById('signatureData').value = signaturePad.toDataURL('image/png');
                }
            });

            signaturePad.addEventListener('endStroke', () => {
                statusEl.textContent = '✏️ Signature captured (not yet saved — click Save Changes).';
            });
        }
    </script>
</body>
</html>