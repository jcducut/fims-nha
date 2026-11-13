<?php
/**
 * UAC - Security Management (User Level Management)
 * Consolidated overview dashboard for the UAC module: Users, Departments,
 * Positions, User Levels (Roles), and quick links into each management page.
 */
namespace PHPMaker2024\FIMS;

// ── Key metrics ──
$totalUsers = ExecuteScalar("SELECT COUNT(*) FROM users", "DB");
$activeUsers = ExecuteScalar("SELECT COUNT(*) FROM users WHERE is_active = TRUE", "DB");
$unassignedUsers = ExecuteScalar("
    SELECT COUNT(*) FROM users u
    WHERE NOT EXISTS (SELECT 1 FROM user_level_assignments ula WHERE ula.user_id = u.user_id)
", "DB");

$totalDepartments = ExecuteScalar("SELECT COUNT(*) FROM departments", "DB");
$budgetUnitDepartments = ExecuteScalar("SELECT COUNT(*) FROM departments WHERE is_budget_unit = TRUE", "DB");

$totalPositions = ExecuteScalar("SELECT COUNT(*) FROM positions", "DB");
$totalPositionCategories = ExecuteScalar("SELECT COUNT(*) FROM position_categories", "DB");

$totalUserLevels = ExecuteScalar("SELECT COUNT(*) FROM user_levels", "DB");
$totalSystems = ExecuteScalar("SELECT COUNT(*) FROM systems WHERE is_active = TRUE", "DB");

// ── Breakdowns ──
$usersByDepartment = ExecuteRowsAssociative("
    SELECT COALESCE(d.department_name, 'Unassigned') AS department_name, COUNT(*) AS user_count
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    GROUP BY d.department_name
    ORDER BY user_count DESC
    LIMIT 8
", "DB");

$departmentsByType = ExecuteRowsAssociative("
    SELECT COALESCE(department_type, 'Unspecified') AS department_type, COUNT(*) AS dept_count
    FROM departments
    GROUP BY department_type
    ORDER BY dept_count DESC
", "DB");

$positionsByCategory = ExecuteRowsAssociative("
    SELECT COALESCE(pc.category_name, 'Uncategorized') AS category_name, COUNT(*) AS position_count
    FROM positions p
    LEFT JOIN position_categories pc ON p.position_category_id = pc.position_category_id
    GROUP BY pc.category_name
    ORDER BY position_count DESC
", "DB");

$recentUsers = ExecuteRowsAssociative("
    SELECT username, first_name, last_name, date_created, is_active
    FROM users
    ORDER BY date_created DESC
    LIMIT 5
", "DB");

// ── Quick link URLs ──
$userAccessUrl = GetUrl("UserAccess");
$departmentManagementUrl = GetUrl("DepartmentManagement");
$positionManagementUrl = GetUrl("PositionManagement");
$userLevelsUrl = GetUrl("UserLevelsList");
$systemsUrl = GetUrl("SystemsList");

// ── Recent Activity (audit_logs) — filterable, read-only ──
// audit_logs is an immutable system table (STRICT - DO NOT MODIFY), so this is
// view/filter only. "user" and "table" are quoted - reserved/ambiguous in Postgres.
$auditUserFilter = trim(Get("audit_user") ?? '');
$auditTableFilter = trim(Get("audit_table") ?? '');
$auditActionFilter = trim(Get("audit_action") ?? '');
$auditDateFrom = trim(Get("audit_date_from") ?? '');
$auditDateTo = trim(Get("audit_date_to") ?? '');

$auditConditions = [];
if ($auditUserFilter !== '') {
    $auditConditions[] = '"user" = ' . QuotedValue($auditUserFilter, DataType::STRING);
}
if ($auditTableFilter !== '') {
    $auditConditions[] = '"table" = ' . QuotedValue($auditTableFilter, DataType::STRING);
}
if ($auditActionFilter !== '') {
    $auditConditions[] = 'action = ' . QuotedValue($auditActionFilter, DataType::STRING);
}
if ($auditDateFrom !== '') {
    $auditConditions[] = 'date_time >= ' . QuotedValue($auditDateFrom . ' 00:00:00', DataType::STRING);
}
if ($auditDateTo !== '') {
    $auditConditions[] = 'date_time <= ' . QuotedValue($auditDateTo . ' 23:59:59', DataType::STRING);
}
$auditWhereClause = empty($auditConditions) ? '' : ' WHERE ' . implode(' AND ', $auditConditions);

$auditLogUsers = ExecuteRowsAssociative('SELECT DISTINCT "user" FROM audit_logs WHERE "user" IS NOT NULL ORDER BY "user"', "DB");
$auditLogTables = ExecuteRowsAssociative('SELECT DISTINCT "table" FROM audit_logs WHERE "table" IS NOT NULL ORDER BY "table"', "DB");
$auditLogActions = ExecuteRowsAssociative('SELECT DISTINCT action FROM audit_logs WHERE action IS NOT NULL ORDER BY action', "DB");

$auditFilteredCount = ExecuteScalar("SELECT COUNT(*) FROM audit_logs" . $auditWhereClause, "DB");
$auditLogRows = ExecuteRowsAssociative("
    SELECT id, date_time, script, \"user\", action, \"table\", field, key_value, old_value, new_value
    FROM audit_logs" . $auditWhereClause . "
    ORDER BY date_time DESC
    LIMIT 50
", "DB");

$auditTodayCount = ExecuteScalar("SELECT COUNT(*) FROM audit_logs WHERE date_time::date = CURRENT_DATE", "DB");
$auditDistinctUsers = ExecuteScalar('SELECT COUNT(DISTINCT "user") FROM audit_logs', "DB");

function getAuditActionBadgeClass($action)
{
    switch ($action) {
        case 'Add':
            return 'success';
        case 'Edit':
        case 'Update':
            return 'info';
        case 'Delete':
            return 'danger';
        case 'Login':
            return 'primary';
        case 'Logout':
            return 'secondary';
        default:
            return 'warning';
    }
}
?>

<!-- Main content -->
<div class="container-fluid">
    <!-- Info boxes -->
    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Users</span>
                    <span class="info-box-number"><?php echo $totalUsers; ?></span>
                    <small class="text-muted"><?php echo $activeUsers; ?> active</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-building"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Departments</span>
                    <span class="info-box-number"><?php echo $totalDepartments; ?></span>
                    <small class="text-muted"><?php echo $budgetUnitDepartments; ?> budget unit<?php echo $budgetUnitDepartments == 1 ? '' : 's'; ?></small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-id-badge"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Positions</span>
                    <span class="info-box-number"><?php echo $totalPositions; ?></span>
                    <small class="text-muted"><?php echo $totalPositionCategories; ?> categor<?php echo $totalPositionCategories == 1 ? 'y' : 'ies'; ?></small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-user-shield"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">User Levels (Roles)</span>
                    <span class="info-box-number"><?php echo $totalUserLevels; ?></span>
                    <small class="text-muted"><?php echo $unassignedUsers; ?> user<?php echo $unassignedUsers == 1 ? '' : 's'; ?> unassigned</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Links -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Links</h3>
                </div>
                <div class="card-body d-grid">
                    <a href="<?php echo $userAccessUrl; ?>" class="btn btn-primary btn-block mb-2 text-left">
                        <i class="fas fa-user-lock mr-2"></i> User Access Management
                    </a>
                    <a href="<?php echo $departmentManagementUrl; ?>" class="btn btn-warning btn-block mb-2 text-left">
                        <i class="fas fa-building mr-2"></i> Department Management
                    </a>
                    <a href="<?php echo $positionManagementUrl; ?>" class="btn btn-success btn-block mb-2 text-left">
                        <i class="fas fa-id-badge mr-2"></i> Position Management
                    </a>
                    <a href="<?php echo $userLevelsUrl; ?>" class="btn btn-danger btn-block mb-2 text-left">
                        <i class="fas fa-user-shield mr-2"></i> Manage User Levels
                    </a>
                    <a href="<?php echo $systemsUrl; ?>" class="btn btn-secondary btn-block text-left">
                        <i class="fas fa-cogs mr-2"></i> Manage Systems (<?php echo $totalSystems; ?>)
                    </a>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent User Registrations</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="nav flex-column">
                        <?php foreach ($recentUsers as $user): ?>
                        <li class="nav-item">
                            <div class="nav-link d-flex justify-content-between align-items-center">
                                <span>
                                    <?php echo HtmlEncode(trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username']); ?>
                                    <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?> ml-1">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </span>
                                <span class="text-muted text-sm"><?php echo HtmlEncode($user['date_created']); ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($recentUsers)): ?>
                        <li class="nav-item"><div class="nav-link text-muted">No users yet.</div></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Breakdowns -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Users by Department</h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr><th>Department</th><th class="text-right">Users</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersByDepartment as $row): ?>
                            <tr>
                                <td><?php echo HtmlEncode($row['department_name']); ?></td>
                                <td class="text-right"><span class="badge badge-primary"><?php echo $row['user_count']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Departments by Type</h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="nav flex-column">
                                <?php foreach ($departmentsByType as $row): ?>
                                <li class="nav-item">
                                    <div class="nav-link d-flex justify-content-between">
                                        <span><?php echo HtmlEncode($row['department_type']); ?></span>
                                        <span class="badge badge-warning"><?php echo $row['dept_count']; ?></span>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Positions by Category</h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="nav flex-column">
                                <?php foreach ($positionsByCategory as $row): ?>
                                <li class="nav-item">
                                    <div class="nav-link d-flex justify-content-between">
                                        <span><?php echo HtmlEncode($row['category_name']); ?></span>
                                        <span class="badge badge-success"><?php echo $row['position_count']; ?></span>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity (Audit Log) -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history mr-1"></i> Recent Activity</h3>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Showing <?php echo count($auditLogRows); ?> of <?php echo $auditFilteredCount; ?> matching log entries (most recent first, limit 50).
                <?php echo $auditTodayCount; ?> action<?php echo $auditTodayCount == 1 ? '' : 's'; ?> today across
                <?php echo $auditDistinctUsers; ?> user<?php echo $auditDistinctUsers == 1 ? '' : 's'; ?> total.
            </p>
            <form method="get" class="form-row align-items-end mb-3">
                <div class="form-group col-md-2">
                    <label class="small mb-1">User</label>
                    <select name="audit_user" class="form-control form-control-sm">
                        <option value="">All</option>
                        <?php foreach ($auditLogUsers as $row): ?>
                        <option value="<?php echo HtmlEncode($row['user']); ?>" <?php echo $auditUserFilter === $row['user'] ? 'selected' : ''; ?>>
                            <?php echo HtmlEncode($row['user']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label class="small mb-1">Table</label>
                    <select name="audit_table" class="form-control form-control-sm">
                        <option value="">All</option>
                        <?php foreach ($auditLogTables as $row): ?>
                        <option value="<?php echo HtmlEncode($row['table']); ?>" <?php echo $auditTableFilter === $row['table'] ? 'selected' : ''; ?>>
                            <?php echo HtmlEncode($row['table']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label class="small mb-1">Action</label>
                    <select name="audit_action" class="form-control form-control-sm">
                        <option value="">All</option>
                        <?php foreach ($auditLogActions as $row): ?>
                        <option value="<?php echo HtmlEncode($row['action']); ?>" <?php echo $auditActionFilter === $row['action'] ? 'selected' : ''; ?>>
                            <?php echo HtmlEncode($row['action']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label class="small mb-1">From</label>
                    <input type="date" name="audit_date_from" class="form-control form-control-sm" value="<?php echo HtmlEncode($auditDateFrom); ?>">
                </div>
                <div class="form-group col-md-2">
                    <label class="small mb-1">To</label>
                    <input type="date" name="audit_date_to" class="form-control form-control-sm" value="<?php echo HtmlEncode($auditDateTo); ?>">
                </div>
                <div class="form-group col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                    <?php if ($auditUserFilter || $auditTableFilter || $auditActionFilter || $auditDateFrom || $auditDateTo): ?>
                    <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Field</th>
                            <th>Key</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogRows as $log): ?>
                        <tr>
                            <td><?php echo HtmlEncode($log['date_time']); ?></td>
                            <td><?php echo HtmlEncode($log['user'] ?: '—'); ?></td>
                            <td><span class="badge badge-<?php echo getAuditActionBadgeClass($log['action']); ?>"><?php echo HtmlEncode($log['action'] ?: '—'); ?></span></td>
                            <td><?php echo HtmlEncode($log['table'] ?: '—'); ?></td>
                            <td><?php echo HtmlEncode($log['field'] ?: '—'); ?></td>
                            <td class="text-truncate" style="max-width:120px;" title="<?php echo HtmlEncode($log['key_value'] ?? ''); ?>"><?php echo HtmlEncode($log['key_value'] ?: '—'); ?></td>
                            <td class="text-truncate" style="max-width:160px;" title="<?php echo HtmlEncode($log['old_value'] ?? ''); ?>"><?php echo HtmlEncode($log['old_value'] ?: '—'); ?></td>
                            <td class="text-truncate" style="max-width:160px;" title="<?php echo HtmlEncode($log['new_value'] ?? ''); ?>"><?php echo HtmlEncode($log['new_value'] ?: '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($auditLogRows)): ?>
                        <tr><td colspan="8" class="text-muted text-center">No matching log entries.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
