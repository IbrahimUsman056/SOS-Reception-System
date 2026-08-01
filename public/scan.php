<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Package - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="container">
        <section class="greeting-banner scan-banner">
            <div>
                <h1>Scan Package</h1>
                <p>Point your camera at a tracking number barcode or QR code to instantly look up the record.</p>
            </div>
            <!-- <div class="banner-illustration">🔍</div> -->
        </section>

        <div class="scan-layout">
            <div class="scan-card">
                <h3>Camera Scanner</h3>
                <div id="reader"></div>
                <p class="scan-hint">Hold the barcode steady, well-lit, and centered in the frame.</p>
            </div>

            <div class="scan-side">
                <div id="scanResult"></div>

                <div class="manual-lookup-card">
                    <h3>Manual Lookup</h3>
                    <p class="field-hint">No camera? Enter the tracking number directly.</p>
                    <label for="manualTracking">Tracking Number</label>
                    <input type="text" id="manualTracking" placeholder="e.g. SOS-TRK-10023">
                    <button id="manualLookupBtn" class="btn btn-primary">Look Up</button>
                </div>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const resultBox = document.getElementById('scanResult');

        function lookupTracking(code) {
            resultBox.innerHTML = '<div class="notice">🔄 Looking up "' + code + '"...</div>';
            fetch('scan_lookup_ajax.php?tracking=' + encodeURIComponent(code))
                .then(r => r.json())
                .then(data => {
                    if (data.found) {
                        resultBox.innerHTML = `
                            <div class="scan-result-card scan-result-found">
                                <div class="scan-result-icon">✅</div>
                                <div class="scan-result-info">
                                    <strong>Record #${data.id} found</strong>
                                    <p>${data.employee_name} · ${data.building}</p>
                                </div>
                                <a href="records/edit.php?id=${data.id}" class="btn-small">Open Record</a>
                            </div>`;
                    } else {
                        resultBox.innerHTML = `
                            <div class="scan-result-card scan-result-notfound">
                                <div class="scan-result-icon">⚠️</div>
                                <div class="scan-result-info">
                                    <strong>No match found</strong>
                                    <p>No record for that tracking number, or you don't have access to it.</p>
                                </div>
                            </div>`;
                    }
                })
                .catch(() => {
                    resultBox.innerHTML = `
                        <div class="scan-result-card scan-result-notfound">
                            <div class="scan-result-icon">❌</div>
                            <div class="scan-result-info"><strong>Lookup failed</strong><p>Please try again.</p></div>
                        </div>`;
                });
        }

        const scanner = new Html5Qrcode('reader');
        let scanning = false;

        Html5Qrcode.getCameras().then(cameras => {
            if (cameras && cameras.length) {
                scanning = true;
                scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 150 } },
                    (decodedText) => {
                        scanner.pause();
                        lookupTracking(decodedText);
                        setTimeout(() => { if (scanning) scanner.resume(); }, 3000);
                    },
                    () => {}
                );
            } else {
                document.getElementById('reader').innerHTML = '<p class="notice">No camera found. Use manual lookup instead.</p>';
            }
        }).catch(() => {
            document.getElementById('reader').innerHTML = '<p class="notice">Camera access unavailable. Use manual lookup instead.</p>';
        });

        document.getElementById('manualLookupBtn').addEventListener('click', () => {
            const val = document.getElementById('manualTracking').value.trim();
            if (val) lookupTracking(val);
        });
        document.getElementById('manualTracking').addEventListener('keyup', (e) => {
            if (e.key === 'Enter') document.getElementById('manualLookupBtn').click();
        });

        window.addEventListener('beforeunload', () => {
            scanning = false;
            if (scanner) scanner.stop().catch(() => {});
        });
    </script>
</body>
</html>