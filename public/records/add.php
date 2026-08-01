<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/cloudinary_upload.php';
require_once __DIR__ . '/../../config/database.php';

require_login();
require_permission('records.create');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $type            = $_POST['type'] ?? '';
    $priority        = $_POST['priority'] ?? 'medium';
    $employeeCode    = trim($_POST['employee_code'] ?? '');
    $employeeName    = trim($_POST['employee_name'] ?? '');
    $designation     = trim($_POST['designation'] ?? '');
    $building        = trim($_POST['building'] ?? '');
    $packageDetail   = trim($_POST['package_detail'] ?? '');
    $packageWeight   = $_POST['package_weight'] !== '' ? (float)$_POST['package_weight'] : null;
    $dimensions      = trim($_POST['package_dimensions'] ?? '');
    $modeOfTransit   = trim($_POST['mode_of_transit'] ?? '');
    $trackingNumber  = trim($_POST['tracking_number'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');

    if (!in_array($type, ['receiving', 'dispatch'], true)) {
        $error = 'Please select a valid record type.';
    } elseif ($employeeName === '' || $building === '') {
        $error = 'Employee name and building are required.';
    } else {
        $attachmentUrl = null;
        $attachmentPublicId = null;

        if (!empty($_FILES['attachment']['name'])) {
            $check = validate_image_upload($_FILES['attachment']);
            if (!$check['ok']) {
                $error = $check['message'];
            } else {
                $upload = cloudinary_upload($_FILES['attachment']['tmp_name'], 'attachments');
                if (!$upload['ok']) {
                    $error = $upload['message'];
                } else {
                    $attachmentUrl = $upload['url'];
                    $attachmentPublicId = $upload['public_id'];
                }
            }
        }

        if ($error === '') {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                'INSERT INTO reception_logs
                 (type, priority, date_time, employee_code, employee_name, designation,
                  building, package_detail, package_weight, package_dimensions,
                  mode_of_transit, tracking_number, address, city, attachment, attachment_public_id,
                  notes, created_by)
                 VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $type, $priority, $employeeCode, $employeeName, $designation,
                $building, $packageDetail, $packageWeight, $dimensions,
                $modeOfTransit, $trackingNumber, $address, $city,
                $attachmentUrl, $attachmentPublicId, $notes,
                current_user_id(),
            ]);

            $newId = $db->lastInsertId();
            log_activity(current_user_id(), 'record_created', "Created reception_logs record #{$newId} ({$type})");

            if ($type === 'receiving') {
                require_once __DIR__ . '/../../includes/notifications.php';
                $userLookup = $db->prepare('SELECT id FROM users WHERE email LIKE CONCAT(?, "@%") LIMIT 1');
                $userLookup->execute([$employeeCode]);
                $targetUser = $userLookup->fetch();

                if ($targetUser) {
                    $msg = "A package has arrived for you at {$building}. Tracking #: " . ($trackingNumber ?: 'N/A');
                    $channels = ($priority === 'urgent') ? ['in_app', 'email', 'sms'] : ['in_app', 'email'];
                    notify_user((int)$targetUser['id'], 'Package Received', $msg, $newId, $channels);
                }
            }

            flash('success', 'Record saved successfully.');
            redirect('/records/view.php?type=' . $type);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Record - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <main class="container">
        <section class="greeting-banner add-record-banner">
            <div>
                <h1>New Reception Record</h1>
                <p>Log a package receiving or dispatch entry for the front desk.</p>
            </div>
            <!-- <div class="banner-illustration">📦</div> -->
        </section>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="form-layout">

            <form method="POST" action="add.php" enctype="multipart/form-data" class="record-form profile-card">

                <?= csrf_field() ?>

                <h3 class="form-section-title">Basic Information</h3>
                <div class="form-row">
                    <div>
                        <label for="type">Type *</label>
                        <select id="type" name="type" required>
                            <option value="">-- Select --</option>
                            <option value="receiving">📥 Receiving (Incoming)</option>
                            <option value="dispatch">📤 Dispatch (Outgoing)</option>
                        </select>
                    </div>
                    <div>
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label for="employee_code">Employee Code</label>
                        <input type="text" id="employee_code" name="employee_code" placeholder="e.g. ibrahim">
                    </div>
                    <div>
                        <label for="employee_name">Employee Name *</label>
                        <input type="text" id="employee_name" name="employee_name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label for="designation">Designation</label>
                        <input type="text" id="designation" name="designation" placeholder="Security Officer">
                    </div>
                    <div>
                        <label for="building">Building *</label>
                        <input type="text" id="building" name="building" required placeholder="HQ Tower">
                    </div>
                </div>

                <h3 class="form-section-title">Package Details</h3>
                <label for="package_detail">Package Detail</label>
                <textarea id="package_detail" name="package_detail" rows="2" placeholder="e.g. CCTV camera housing units — dome type"></textarea>

                <div class="form-row">
                    <div>
                        <label for="package_weight">Weight (kg)</label>
                        <input type="number" step="0.01" id="package_weight" name="package_weight">
                    </div>
                    <div>
                        <label for="package_dimensions">Dimensions (LxWxH)</label>
                        <input type="text" id="package_dimensions" name="package_dimensions" placeholder="e.g. 30x20x15 cm">
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label for="mode_of_transit">Mode of Transit</label>
                        <input type="text" id="mode_of_transit" name="mode_of_transit" placeholder="Courier, Post, Hand-delivery...">
                    </div>
                    <div>
                        <label for="tracking_number">Tracking Number</label>
                        <input type="text" id="tracking_number" name="tracking_number" placeholder="SOS-TRK-10023">
                    </div>
                </div>

                <h3 class="form-section-title">Delivery Address</h3>
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="2"></textarea>

                <label for="city">City</label>
                <input type="text" id="city" name="city">

                <h3 class="form-section-title">Attachment & Notes</h3>
                <label for="attachment">Attachment (photo of package/label)</label>
                <input type="file" id="attachment" name="attachment" accept="image/jpeg,image/png,image/webp">

                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2"></textarea>

                <button type="submit" class="btn btn-primary btn-full">Save Record</button>
            </form>

            <aside class="form-side">
                <div id="barcodePreviewBox" class="profile-card barcode-preview" style="display:none;">
                    <h3>Barcode Preview</h3>
                    <img id="barcodePreviewImg" alt="Barcode preview">
                    <p class="barcode-label">Auto-generated from tracking number</p>
                </div>

                <div class="profile-card form-tip-card">
                    <h3>💡 Tips</h3>
                    <ul class="tip-list">
                        <li>Employee code should match their login email prefix so they get notified automatically.</li>
                        <li>Urgent priority triggers SMS + email in addition to the in-app alert.</li>
                        <li>Tracking number generates a scannable barcode instantly.</li>
                    </ul>
                </div>
            </aside>

        </div>
    </main>

    <script>
        const trackingInput = document.getElementById('tracking_number');
        const previewBox = document.getElementById('barcodePreviewBox');
        const previewImg = document.getElementById('barcodePreviewImg');

        trackingInput.addEventListener('input', () => {
            const val = trackingInput.value.trim();
            if (val.length > 0) {
                previewImg.src = '<?= BASE_URL ?>/barcode.php?text=' + encodeURIComponent(val) + '&t=' + Date.now();
                previewBox.style.display = 'block';
            } else {
                previewBox.style.display = 'none';
            }
        });
    </script>
</body>
</html>