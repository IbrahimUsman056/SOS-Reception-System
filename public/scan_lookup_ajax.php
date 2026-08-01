<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/database.php';

require_login();
header('Content-Type: application/json');

$tracking = trim($_GET['tracking'] ?? '');

if ($tracking === '') {
    echo json_encode(['found' => false]);
    exit;
}

$db = Database::getConnection();
$scope = record_scope_sql();

// Scoped exactly like list_ajax.php — a receptionist scanning a barcode
// still can't retrieve a record outside their own scope.
$stmt = $db->prepare(
    "SELECT reception_logs.id, reception_logs.employee_name, reception_logs.building
     FROM reception_logs
     JOIN users u ON reception_logs.created_by = u.id
     WHERE reception_logs.tracking_number = ? AND {$scope['where']}
     LIMIT 1"
);
$stmt->execute(array_merge([$tracking], $scope['params']));
$record = $stmt->fetch();

echo json_encode($record ? array_merge(['found' => true], $record) : ['found' => false]);