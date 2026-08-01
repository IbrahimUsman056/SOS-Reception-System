<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Dompdf\Dompdf;
use Dompdf\Options;

require_login();
require_permission('reports.export');

$db = Database::getConnection();
$scope = record_scope_sql();

$period = $_GET['period'] ?? 'daily';
$format = $_GET['format'] ?? 'excel';

if (!in_array($format, ['excel', 'pdf'], true)) {
    http_response_code(400);
    die('Invalid export format.');
}

// ---- Apply optional export filters on top of the role scope ----
$where = [$scope['where']];
$params = $scope['params'];

if (!empty($_GET['type']) && in_array($_GET['type'], ['receiving', 'dispatch'], true)) {
    $where[] = 'reception_logs.type = ?';
    $params[] = $_GET['type'];
}
if (!empty($_GET['status'])) {
    $where[] = 'reception_logs.status = ?';
    $params[] = $_GET['status'];
}
if (!empty($_GET['building'])) {
    $where[] = 'reception_logs.building = ?';
    $params[] = $_GET['building'];
}
if (!empty($_GET['priority'])) {
    $where[] = 'reception_logs.priority = ?';
    $params[] = $_GET['priority'];
}
if (!empty($_GET['date_from'])) {
    $where[] = 'reception_logs.date_time >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $where[] = 'reception_logs.date_time <= ?';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}

$whereSql = implode(' AND ', $where);

$sql = "SELECT reception_logs.id, reception_logs.type, reception_logs.status, reception_logs.priority,
               reception_logs.date_time, reception_logs.employee_name, reception_logs.building,
               reception_logs.tracking_number, reception_logs.package_detail,
               reception_logs.package_weight, reception_logs.delivered_to, reception_logs.delivered_at
        FROM reception_logs
        JOIN users u ON reception_logs.created_by = u.id
        WHERE {$whereSql}
        ORDER BY reception_logs.date_time DESC
        LIMIT 5000";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filterSummary = array_filter([
    'type' => $_GET['type'] ?? null,
    'status' => $_GET['status'] ?? null,
    'building' => $_GET['building'] ?? null,
    'priority' => $_GET['priority'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null,
]);
log_activity(current_user_id(), 'report_exported', "Exported {$format} report ({$period}), " . count($rows) . " rows, filters: " . json_encode($filterSummary));

$filenameBase = 'sos-reception-report-' . $period . '-' . date('Y-m-d');
$scopeLabel = current_role() === 'admin'
    ? 'Organization-wide'
    : (current_role() === 'manager' ? 'Department: ' . $_SESSION['department'] : 'Own records');

// Brand colors (teal theme, matching the app)
const TEAL = '0D4D4A';
const TEAL_LIGHT = 'E8F3F1';
const ORANGE = 'F4A259';

// ============================================================
// EXCEL EXPORT
// ============================================================
if ($format === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reception Report');

    // ---- Title block ----
    $sheet->setCellValue('A1', 'SOS — Security Organizing System');
    $sheet->mergeCells('A1:L1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(TEAL);

    $sheet->setCellValue('A2', 'Reception Report — ' . ucfirst($period) . ' · ' . $scopeLabel);
    $sheet->mergeCells('A2:L2');
    $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB('64748B');

    $sheet->setCellValue('A3', 'Generated ' . date('Y-m-d H:i') . ' · ' . count($rows) . ' records');
    $sheet->mergeCells('A3:L3');
    $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB('94A3B8');

    // ---- Header row (row 5) ----
    $headers = ['ID', 'Type', 'Status', 'Priority', 'Date/Time', 'Employee', 'Building',
                'Tracking #', 'Package Detail', 'Weight (kg)', 'Delivered To', 'Delivered At'];
    $sheet->fromArray($headers, null, 'A5');

    $headerRange = 'A5:L5';
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(TEAL);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(5)->setRowHeight(22);

    // ---- Data rows with zebra striping ----
    $rowNum = 6;
    foreach ($rows as $r) {
        $sheet->fromArray([
            $r['id'], ucfirst($r['type']), ucfirst(str_replace('_', ' ', $r['status'])), ucfirst($r['priority']),
            $r['date_time'], $r['employee_name'], $r['building'], $r['tracking_number'],
            $r['package_detail'], $r['package_weight'], $r['delivered_to'], $r['delivered_at'],
        ], null, "A{$rowNum}");

        $rowRange = "A{$rowNum}:L{$rowNum}";
        if ($rowNum % 2 === 0) {
            $sheet->getStyle($rowRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(TEAL_LIGHT);
        }

        // Color-code the Priority column (D) for urgent/high visibility
        if (strtolower($r['priority']) === 'urgent') {
            $sheet->getStyle("D{$rowNum}")->getFont()->setBold(true)->getColor()->setRGB('DC2626');
        } elseif (strtolower($r['priority']) === 'high') {
            $sheet->getStyle("D{$rowNum}")->getFont()->setBold(true)->getColor()->setRGB(ORANGE);
        }

        $rowNum++;
    }

    $lastRow = $rowNum - 1;

    // ---- Borders around the whole table ----
    $sheet->getStyle("A5:L{$lastRow}")->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

    // ---- Column widths + freeze header ----
    foreach (range('A', 'L') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->freezePane('A6');
    $sheet->setAutoFilter("A5:L{$lastRow}");

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filenameBase . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ============================================================
// PDF EXPORT
// ============================================================
if ($format === 'pdf') {
    $html = '
    <html>
    <head>
        <style>
            @page { margin: 30px 36px; }
            body { font-family: DejaVu Sans, sans-serif; color: #1e293b; }

            .report-header {
                display: flex;
                border-bottom: 3px solid #0D4D4A;
                padding-bottom: 14px;
                margin-bottom: 18px;
            }
            .brand { font-size: 20px; font-weight: bold; color: #0D4D4A; }
            .brand-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
            .meta { text-align: right; font-size: 10px; color: #94a3b8; float: right; margin-top: -40px; }

            table { width: 100%; border-collapse: collapse; font-size: 9.5px; margin-top: 10px; }
            thead th {
                background: #0D4D4A;
                color: #fff;
                padding: 8px 6px;
                text-align: left;
                font-size: 9.5px;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            tbody td { padding: 7px 6px; border-bottom: 1px solid #e2e8f0; }
            tbody tr:nth-child(even) { background: #E8F3F1; }

            .pill { padding: 2px 8px; border-radius: 8px; font-size: 8.5px; font-weight: bold; }
            .pill-urgent { background: #fee2e2; color: #dc2626; }
            .pill-high { background: #fdecd8; color: #b5651d; }
            .pill-medium { background: #e8eefd; color: #5b8def; }
            .pill-low { background: #f1f5f9; color: #64748b; }

            .footer { margin-top: 20px; font-size: 8.5px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        </style>
    </head>
    <body>
        <div class="report-header">
            <div>
                <div class="brand">🛡️ SOS — Security Organizing System</div>
                <div class="brand-sub">Reception Report · ' . e(ucfirst($period)) . ' · ' . e($scopeLabel) . '</div>
            </div>
        </div>
        <div class="meta">
            Generated: ' . date('Y-m-d H:i') . '<br>
            Records: ' . count($rows) . '
        </div>

        <table>
            <thead>
                <tr>
                    <th>Type</th><th>Status</th><th>Priority</th><th>Date/Time</th>
                    <th>Employee</th><th>Building</th><th>Tracking #</th><th>Weight</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($rows as $r) {
        $priorityClass = 'pill-' . strtolower($r['priority']);
        $html .= '<tr>'
               . '<td>' . e(ucfirst($r['type'])) . '</td>'
               . '<td>' . e(ucfirst(str_replace('_', ' ', $r['status']))) . '</td>'
               . '<td><span class="pill ' . $priorityClass . '">' . e(ucfirst($r['priority'])) . '</span></td>'
               . '<td>' . e($r['date_time']) . '</td>'
               . '<td>' . e($r['employee_name']) . '</td>'
               . '<td>' . e($r['building']) . '</td>'
               . '<td>' . e($r['tracking_number'] ?? '—') . '</td>'
               . '<td>' . e((string)($r['package_weight'] ?? '—')) . '</td>'
               . '</tr>';
    }

    $html .= '</tbody></table>
        <div class="footer">SOS Reception Management System · Confidential internal report · Generated automatically</div>
    </body>
    </html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $dompdf->stream($filenameBase . '.pdf', ['Attachment' => true]);
    exit;
}