<?php
/**
 * includes/permissions.php
 * Central RBAC authority.
 */

require_once __DIR__ . '/auth.php';

const ROLE_PERMISSIONS = [
    'admin' => [
        'records.view.all', 'records.create', 'records.edit.any', 'records.delete',
        'reports.view.org', 'reports.export',
        'users.manage', 'roles.manage',
        'activity_logs.view.all',
        'notifications.manage.global',
    ],
    'manager' => [
        'records.view.department', 'records.create', 'records.edit.department',
        'reports.view.department', 'reports.export',
        'activity_logs.view.department',
    ],
    'receptionist' => [
        'records.view.own', 'records.create', 'records.edit.own',
        'reports.view.own',
    ],
];

function can(string $permission): bool
{
    $role = current_role();
    if (!$role || !isset(ROLE_PERMISSIONS[$role])) {
        return false;
    }
    return in_array($permission, ROLE_PERMISSIONS[$role], true);
}

function require_permission(string $permission): void
{
    if (!can($permission)) {
        http_response_code(403);
        log_activity(current_user_id(), 'access_denied', "Denied permission: {$permission}");
        die('You do not have permission to perform this action.');
    }
}

function can_edit_record(array $record): bool
{
    $role = current_role();

    if ($role === 'admin') {
        return true;
    }
    if ($role === 'manager') {
        return ($record['owner_department'] ?? null) === $_SESSION['department'];
    }
    if ($role === 'receptionist') {
        return (int)($record['created_by'] ?? -1) === (int)current_user_id();
    }
    return false;
}

function can_view_record(array $record): bool
{
    $role = current_role();

    if ($role === 'admin') {
        return true;
    }
    if ($role === 'manager') {
        return ($record['owner_department'] ?? null) === $_SESSION['department'];
    }
    if ($role === 'receptionist') {
        return (int)($record['created_by'] ?? -1) === (int)current_user_id();
    }
    return false;
}

function record_scope_sql(): array
{
    $role = current_role();

    if ($role === 'admin') {
        return ['where' => '1=1', 'params' => []];
    }
    if ($role === 'manager') {
        return ['where' => 'u.department = ?', 'params' => [$_SESSION['department']]];
    }
    return ['where' => 'reception_logs.created_by = ?', 'params' => [current_user_id()]];
}