<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/database.php';

require_login();

if (!can('reports.view.own') && !can('reports.view.department') && !can('reports.view.org')) {
    http_response_code(403);
    die('You do not have permission to view reports.');
}

$db = Database::getConnection();
$scope = record_scope_sql();

$period = $_GET['period'] ?? 'daily';
$dateFormatSql = match ($period) {
    'weekly'  => '%x-W%v',
    'monthly' => '%Y-%m',
    default   => '%Y-%m-%d',
};

$volumeSql = "SELECT DATE_FORMAT(date_time, '{$dateFormatSql}') AS period_label,
                     SUM(CASE WHEN type = 'receiving' THEN 1 ELSE 0 END) AS receiving_count,
                     SUM(CASE WHEN type = 'dispatch' THEN 1 ELSE 0 END) AS dispatch_count
              FROM reception_logs
              JOIN users u ON reception_logs.created_by = u.id
              WHERE {$scope['where']}
              GROUP BY period_label
              ORDER BY period_label ASC
              LIMIT 30";
$stmt = $db->prepare($volumeSql);
$stmt->execute($scope['params']);
$volumeData = $stmt->fetchAll();

$buildingSql = "SELECT building, COUNT(*) AS total
                FROM reception_logs
                JOIN users u ON reception_logs.created_by = u.id
                WHERE {$scope['where']}
                GROUP BY building
                ORDER BY total DESC
                LIMIT 10";
$stmt = $db->prepare($buildingSql);
$stmt->execute($scope['params']);
$buildingData = $stmt->fetchAll();

$avgTimeSql = "SELECT AVG(TIMESTAMPDIFF(HOUR, date_time, delivered_at)) AS avg_hours,
                      building
               FROM reception_logs
               JOIN users u ON reception_logs.created_by = u.id
               WHERE {$scope['where']} AND status = 'delivered' AND delivered_at IS NOT NULL
               GROUP BY building
               ORDER BY avg_hours DESC
               LIMIT 10";
$stmt = $db->prepare($avgTimeSql);
$stmt->execute($scope['params']);
$avgTimeData = $stmt->fetchAll();

$statusSql = "SELECT status, COUNT(*) AS total
              FROM reception_logs
              JOIN users u ON reception_logs.created_by = u.id
              WHERE {$scope['where']}
              GROUP BY status";
$stmt = $db->prepare($statusSql);
$stmt->execute($scope['params']);
$statusData = $stmt->fetchAll();

$totalRecords = array_sum(array_column($volumeData, 'receiving_count')) + array_sum(array_column($volumeData, 'dispatch_count'));
$avgDeliveryOverall = count($avgTimeData) ? round(array_sum(array_column($avgTimeData, 'avg_hours')) / count($avgTimeData), 1) : 0;

// Distinct buildings/statuses for the export filter modal dropdowns
$allBuildingsStmt = $db->prepare("SELECT DISTINCT building FROM reception_logs JOIN users u ON reception_logs.created_by = u.id WHERE {$scope['where']} ORDER BY building");
$allBuildingsStmt->execute($scope['params']);
$allBuildings = $allBuildingsStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="container">

        <section class="greeting-banner reports-banner">
            <div>
                <h1>Reports & Analytics</h1>
                <p>Scope: <?= current_role() === 'admin' ? 'Organization-wide' : (current_role() === 'manager' ? 'Department: ' . e($_SESSION['department']) : 'Your records only') ?></p>
            </div>
            <!-- <div class="banner-illustration">📈</div> -->
        </section>

        <section class="report-toolbar">
            <div class="period-tabs">
                <a href="?period=daily" class="period-tab <?= $period === 'daily' ? 'active' : '' ?>">Daily</a>
                <a href="?period=weekly" class="period-tab <?= $period === 'weekly' ? 'active' : '' ?>">Weekly</a>
                <a href="?period=monthly" class="period-tab <?= $period === 'monthly' ? 'active' : '' ?>">Monthly</a>
            </div>

            <?php if (can('reports.export')): ?>
            <div class="export-buttons">
                <button type="button" class="btn-export btn-export-excel" onclick="openExportModal('excel')">⬇ Export Excel</button>
                <button type="button" class="btn-export btn-export-pdf" onclick="openExportModal('pdf')">⬇ Export PDF</button>
            </div>
            <?php endif; ?>
        </section>

        <section class="stats-grid">
            <div class="stat-card stat-teal">
                <div class="stat-icon">📦</div>
                <div><h3><?= $totalRecords ?></h3><p>Records in Period</p></div>
            </div>
            <div class="stat-card stat-orange">
                <div class="stat-icon">⏱️</div>
                <div><h3><?= $avgDeliveryOverall ?>h</h3><p>Avg. Delivery Time</p></div>
            </div>
            <div class="stat-card stat-blue">
                <div class="stat-icon">🏢</div>
                <div><h3><?= count($buildingData) ?></h3><p>Active Buildings</p></div>
            </div>
            <div class="stat-card stat-purple">
                <div class="stat-icon">📋</div>
                <div><h3><?= count($statusData) ?></h3><p>Status Types</p></div>
            </div>
        </section>

        <div class="chart-grid">
            <div class="chart-card">
                <h3>Volume Over Time</h3>
                <p class="chart-desc">Receiving vs dispatch records logged per period. Click a point to see that day's records.</p>
                <div class="chart-canvas-wrap"><canvas id="volumeChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3>Status Breakdown</h3>
                <p class="chart-desc">Share of records by current status. Click a slice to filter records by that status.</p>
                <div class="chart-canvas-wrap"><canvas id="statusChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3>Top Buildings by Volume</h3>
                <p class="chart-desc">Which buildings receive the most packages. Click a bar to view that building's records.</p>
                <div class="chart-canvas-wrap"><canvas id="buildingChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3>Avg. Delivery Time by Building</h3>
                <p class="chart-desc">Average hours from log-in to delivery, per building. Click a bar to view that building's delivered records.</p>
                <div class="chart-canvas-wrap"><canvas id="avgTimeChart"></canvas></div>
            </div>
        </div>
    </main>

    <!-- Export filter modal -->
    <div id="exportModal" class="modal-overlay" style="display:none;">
        <div class="modal-box modal-box-wide">
            <!-- <div class="modal-icon">⬇</div> -->
            <h3 id="exportModalTitle">Export Records</h3>

            <form id="exportFilterForm" class="export-filter-form">
                <div class="form-row">
                    <div>
                        <label for="exportType">Type</label>
                        <select id="exportType" name="type">
                            <option value="">All Types</option>
                            <option value="receiving">Receiving</option>
                            <option value="dispatch">Dispatch</option>
                        </select>
                    </div>
                    <div>
                        <label for="exportStatus">Status</label>
                        <select id="exportStatus" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="returned">Returned</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="exportBuilding">Building</label>
                        <select id="exportBuilding" name="building">
                            <option value="">All Buildings</option>
                            <?php foreach ($allBuildings as $b): ?>
                                <option value="<?= e($b) ?>"><?= e($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="exportPriority">Priority</label>
                        <select id="exportPriority" name="priority">
                            <option value="">All Priorities</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="exportDateFrom">From Date</label>
                        <input type="date" id="exportDateFrom" name="date_from">
                    </div>
                    <div>
                        <label for="exportDateTo">To Date</label>
                        <input type="date" id="exportDateTo" name="date_to">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn" onclick="closeExportModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="exportSubmitBtn">Download</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const volumeData = <?= json_encode($volumeData) ?>;
        const buildingData = <?= json_encode($buildingData) ?>;
        const avgTimeData = <?= json_encode($avgTimeData) ?>;
        const statusData = <?= json_encode($statusData) ?>;
        const currentPeriod = <?= json_encode($period) ?>;
        const baseUrl = <?= json_encode(BASE_URL) ?>;

        const palette = {
            teal: '#0D4D4A', orange: '#F4A259', blue: '#5B8DEF',
            purple: '#8B7BD8', red: '#DC2626', green: '#16A34A',
        };

        Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
        Chart.defaults.color = '#64748B';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.onHover = (event, elements) => {
            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
        };

        // Navigate to records/view.php pre-filtered based on what was clicked
        function goToRecords(params) {
            const qs = new URLSearchParams(params).toString();
            window.location.href = `${baseUrl}/records/view.php?${qs}`;
        }

        new Chart(document.getElementById('volumeChart'), {
            type: 'line',
            data: {
                labels: volumeData.map(r => r.period_label),
                datasets: [
                    { label: 'Receiving', data: volumeData.map(r => r.receiving_count), borderColor: palette.teal, backgroundColor: palette.teal + '22', fill: true, tension: 0.35, pointBackgroundColor: palette.teal, pointRadius: 4, pointHoverRadius: 7 },
                    { label: 'Dispatch', data: volumeData.map(r => r.dispatch_count), borderColor: palette.orange, backgroundColor: palette.orange + '22', fill: true, tension: 0.35, pointBackgroundColor: palette.orange, pointRadius: 4, pointHoverRadius: 7 }
                ]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                scales: { y: { beginAtZero: true, grid: { color: '#E2E8F0' } }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } },
                onClick: (evt, elements) => {
                    if (!elements.length) return;
                    const idx = elements[0].index;
                    const label = volumeData[idx].period_label;
                    // Only daily period labels map cleanly to a single date filter
                    if (currentPeriod === 'daily') {
                        goToRecords({ date_from: label, date_to: label });
                    } else {
                        goToRecords({}); // weekly/monthly labels aren't a single date — send to all records
                    }
                }
            }
        });

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.map(r => r.status.replace('_', ' ')),
                datasets: [{
                    data: statusData.map(r => r.total),
                    backgroundColor: [palette.orange, palette.blue, palette.green, palette.red],
                    borderWidth: 2, borderColor: '#fff',
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                cutout: '65%',
                onClick: (evt, elements) => {
                    if (!elements.length) return;
                    const status = statusData[elements[0].index].status;
                    goToRecords({ status });
                }
            }
        });

        new Chart(document.getElementById('buildingChart'), {
            type: 'bar',
            data: {
                labels: buildingData.map(r => r.building),
                datasets: [{ label: 'Records', data: buildingData.map(r => r.total), backgroundColor: palette.teal, borderRadius: 6 }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: '#E2E8F0' } }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } },
                onClick: (evt, elements) => {
                    if (!elements.length) return;
                    const building = buildingData[elements[0].index].building;
                    goToRecords({ building });
                }
            }
        });

        new Chart(document.getElementById('avgTimeChart'), {
            type: 'bar',
            data: {
                labels: avgTimeData.map(r => r.building),
                datasets: [{ label: 'Avg Hours', data: avgTimeData.map(r => Math.round(r.avg_hours * 10) / 10), backgroundColor: palette.orange, borderRadius: 6 }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: '#E2E8F0' } }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } },
                onClick: (evt, elements) => {
                    if (!elements.length) return;
                    const building = avgTimeData[elements[0].index].building;
                    goToRecords({ building, status: 'delivered' });
                }
            }
        });

        // ---- Export modal ----
        let exportFormat = 'excel';
        function openExportModal(format) {
            exportFormat = format;
            // document.getElementById('exportModalIcon').textContent = format === 'excel' ? '📊' : '📄';
            document.getElementById('exportModalTitle').textContent = format === 'excel' ? 'Export to Excel' : 'Export to PDF';
            document.getElementById('exportModal').style.display = 'flex';
        }
        function closeExportModal() {
            document.getElementById('exportModal').style.display = 'none';
        }
        document.getElementById('exportModal').addEventListener('click', (e) => {
            if (e.target.id === 'exportModal') closeExportModal();
        });

        document.getElementById('exportFilterForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const params = new URLSearchParams({ period: currentPeriod, format: exportFormat });
            for (const [key, value] of formData.entries()) {
                if (value) params.append(key, value);
            }
            window.location.href = `reports_export.php?${params.toString()}`;
            closeExportModal();
        });
    </script>
</body>
</html>