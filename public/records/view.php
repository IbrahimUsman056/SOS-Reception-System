<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$buildingFilter = $_GET['building'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$pageTitle = match ($typeFilter) {
    'receiving' => 'Receiving Records',
    'dispatch'  => 'Dispatch Records',
    default     => 'All Records',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($pageTitle) ?> - SOS Reception Management System</title>
    <link rel="stylesheet" href="<?= ASSET_URL ?>/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/nav.php'; ?>

    <main class="container">
        <section class="greeting-banner records-banner">
            <div>
                <h1> <?= e($pageTitle) ?></h1>
                <p>Search, filter, and manage every logged package in this view.</p>
                <a href="add.php" class="btn btn-banner">+ New Record</a>
            </div>
            <!-- <div class="banner-illustration"><?= $pageIcon ?></div> -->
        </section>

        <section class="filters-bar filters-card">
            <input type="text" id="filterSearch" placeholder="Search employee, building, tracking #..." class="filter-search">
            <input type="text" id="filterBuilding" placeholder="Building" value="<?= e($buildingFilter) ?>">
            <select id="filterStatus">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_transit" <?= $statusFilter === 'in_transit' ? 'selected' : '' ?>>In Transit</option>
                <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                <option value="returned" <?= $statusFilter === 'returned' ? 'selected' : '' ?>>Returned</option>
            </select>
            <input type="date" id="filterDateFrom" value="<?= e($dateFrom) ?>">
            <input type="date" id="filterDateTo" value="<?= e($dateTo) ?>">
            <button id="applyFilters" class="btn btn-primary">Apply</button>
            <button id="clearFilters" class="btn">Clear</button>
        </section>

        <div class="records-table-card">
            <table id="recordsTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Type</th>
                        <th>Employee</th>
                        <th>Building</th>
                        <th>Tracking #</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        const fixedType = <?= json_encode($typeFilter) ?>;

        function buildFilterParams() {
            return {
                type: fixedType,
                building: $('#filterBuilding').val(),
                status: $('#filterStatus').val(),
                date_from: $('#filterDateFrom').val(),
                date_to: $('#filterDateTo').val(),
            };
        }

        function renderTypePill(type) {
            return `<span class="type-pill type-${type}">${type.charAt(0).toUpperCase() + type.slice(1)}</span>`;
        }
        function renderStatusPill(status) {
            return `<span class="status-pill status-${status}">${status.replace('_',' ').replace(/\b\w/g, c => c.toUpperCase())}</span>`;
        }
        function renderPriorityPill(priority) {
            return `<span class="priority-pill priority-${priority}">${priority.charAt(0).toUpperCase() + priority.slice(1)}</span>`;
        }

        const table = $('#recordsTable').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 15,
            lengthMenu: [10, 15, 25, 50, 100],
            dom: '<"top">rt<"bottom"lip><"clear">', // removed 'f' — drops the built-in search box entirely
            language: {
                emptyTable: 'No records found matching your filters.',
                zeroRecords: 'No matching records found.',
                loadingRecords: 'Loading records...',
                paginate: { previous: '‹', next: '›' }
            },
            ajax: {
                url: 'list_ajax.php',
                data: function (d) {
                    d.search.value = $('#filterSearch').val();
                    return Object.assign(d, buildFilterParams());
                }
            },
            columns: [
                { data: 'date_time' },
                { data: 'type', render: renderTypePill },
                { data: 'employee_name' },
                { data: 'building' },
                { data: 'tracking_number', render: (d) => d || '<span style="color:#94a3b8;">—</span>' },
                { data: 'status', render: renderStatusPill },
                { data: 'priority', render: renderPriorityPill },
                {
                    data: 'id',
                    orderable: false,
                    render: (id) => `<a href="edit.php?id=${id}" class="btn-small">Open</a>`
                }
            ]
        });

        $('#applyFilters').on('click', () => table.ajax.reload());
        $('#filterSearch').on('keyup', () => table.ajax.reload());
        $('#clearFilters').on('click', () => {
            $('#filterBuilding, #filterSearch, #filterDateFrom, #filterDateTo').val('');
            $('#filterStatus').val('');
            table.ajax.reload();
        });
    </script>
</body>
</html>