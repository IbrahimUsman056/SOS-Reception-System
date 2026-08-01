<?php
/**
 * public/records/list_ajax.php
 * Server-side DataTables endpoint. Every query is scoped through
 * record_scope_sql() so a receptionist can never pull another user's
 * rows just by tampering with the DataTables request params.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../config/database.php';

require_login();
header('Content-Type: application/json');

$db = Database::getConnection();

$draw   = (int)($_GET['draw'] ?? 1);
$start  = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 25);
$searchValue = trim($_GET['search']['value'] ?? '');

$filterType     = $_GET['type'] ?? null;
$filterStatus   = $_GET['status'] ?? null;
$filterBuilding = $_GET['building'] ?? null;
$dateFrom       = $_GET['date_from'] ?? null;
$dateTo         = $_GET['date_to'] ?? null;

$scope = record_scope_sql();
$where = [$scope['where']];
$params = $scope['params'];

if ($filterType && in_array($filterType, ['receiving', 'dispatch'], true)) {
    $where[] = 'reception_logs.type = ?';
    $params[] = $filterType;
}
if ($filterStatus) {
    $where[] = 'reception_logs.status = ?';
    $params[] = $filterStatus;
}
if ($filterBuilding) {
    $where[] = 'reception_logs.building = ?';
    $params[] = $filterBuilding;
}
if ($dateFrom) {
    $where[] = 'reception_logs.date_time >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = 'reception_logs.date_time <= ?';
    $params[] = $dateTo . ' 23:59:59';
}
if ($searchValue !== '') {
    $where[] = '(reception_logs.employee_name LIKE ? OR reception_logs.tracking_number LIKE ?
                 OR reception_logs.building LIKE ? OR reception_logs.package_detail LIKE ?)';
    $like = '%' . $searchValue . '%';
    array_push($params, $like, $like, $like, $like);
}

$whereSql = implode(' AND ', $where);

$totalStmt = $db->prepare(
    "SELECT COUNT(*) FROM reception_logs JOIN users u ON reception_logs.created_by = u.id WHERE {$scope['where']}"
);
$totalStmt->execute($scope['params']);
$totalRecords = (int)$totalStmt->fetchColumn();

$filteredStmt = $db->prepare(
    "SELECT COUNT(*) FROM reception_logs JOIN users u ON reception_logs.created_by = u.id WHERE {$whereSql}"
);
$filteredStmt->execute($params);
$filteredRecords = (int)$filteredStmt->fetchColumn();

// $start/$length are cast to int above, so safe to interpolate directly.
$dataSql = "SELECT reception_logs.id, reception_logs.type, reception_logs.status, reception_logs.priority,
                   reception_logs.date_time, reception_logs.employee_name, reception_logs.building,
                   reception_logs.tracking_number, reception_logs.package_detail, u.department AS owner_department
            FROM reception_logs
            JOIN users u ON reception_logs.created_by = u.id
            WHERE {$whereSql}
            ORDER BY reception_logs.date_time DESC
            LIMIT {$length} OFFSET {$start}";

$dataStmt = $db->prepare($dataSql);
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $rows,
]);