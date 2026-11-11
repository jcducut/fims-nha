<?php
/**
 * UAC - User Access Management
 * Lists system users and lets an administrator review / assign
 * system + role (user level) access and inspect the resulting permissions.
 */

namespace PHPMaker2024\FIMS;

// ── Key metrics ──────────────────────────────────────────────
$totalUsers = ExecuteScalar("SELECT COUNT(*) FROM users", "DB");
$activeUsers = ExecuteScalar("SELECT COUNT(*) FROM users WHERE is_active = TRUE", "DB");
$totalUserLevels = ExecuteScalar("SELECT COUNT(*) FROM user_levels", "DB");
$unassignedUsers = ExecuteScalar("
    SELECT COUNT(*) FROM users u
    WHERE NOT EXISTS (SELECT 1 FROM user_level_assignments ula WHERE ula.user_id = u.user_id)
", "DB");

// ── User list (with department / primary office / access summary) ──
$users = ExecuteRowsAssociative("
    SELECT
        u.user_id,
        u.username,
        u.email,
        u.employee_id,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.suffix,
        u.mobile_number,
        u.is_active,
        u.last_login,
        u.department_id,
        u.rc_id,
        u.position_id,
        d.department_name,
        d.department_acronym,
        o.office_name,
        STRING_AGG(DISTINCT COALESCE(s.system_name || ': ', '') || ul.name, ', ' ORDER BY COALESCE(s.system_name || ': ', '') || ul.name) AS access_summary
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN user_offices uo ON uo.user_id = u.user_id AND uo.is_primary = TRUE
    LEFT JOIN offices o ON uo.office_id = o.office_id
    -- users.user_level_id is the field the actual login/permission engine
    -- reads (see AdvancedSecurity/PermissionMiddleware) - user_level_assignments
    -- is only this page's own supplementary per-system assignment log. Union
    -- both so a user's real, working role always shows even if it was never
    -- recorded in user_level_assignments (e.g. seeded directly on the row).
    LEFT JOIN (
        SELECT user_id, user_level_id, system_id FROM user_level_assignments
        UNION
        SELECT user_id, user_level_id::integer, NULL::int4 AS system_id
        FROM users WHERE user_level_id ~ '^-?[0-9]+$'
    ) ula ON ula.user_id = u.user_id
    LEFT JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    LEFT JOIN systems s ON s.system_id = COALESCE(ula.system_id, ul.system_id)
    GROUP BY u.user_id, d.department_id, o.office_id
    ORDER BY u.username
", "DB");

$usersById = [];
foreach ($users as $user) {
    $usersById[$user['user_id']] = $user;
}

// ── Per-user assignment detail (for the access panel) ──
$assignmentRows = ExecuteRowsAssociative("
    SELECT
        ula.assignment_id,
        ula.user_id,
        ula.system_id,
        COALESCE(s.system_name, 'N/A') AS system_name,
        ula.user_level_id,
        ul.name AS level_name
    FROM user_level_assignments ula
    JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    LEFT JOIN systems s ON ula.system_id = s.system_id
    ORDER BY ula.user_id
", "DB");

$assignmentsByUser = [];
foreach ($assignmentRows as $row) {
    $assignmentsByUser[$row['user_id']][] = $row;
}

// ── User levels (roles/modules) for the assignment dropdown ──
$userLevels = ExecuteRowsAssociative("
    SELECT ul.user_level_id, ul.name, ul.description, ul.system_id, COALESCE(s.system_name, 'N/A') AS system_name
    FROM user_levels ul
    LEFT JOIN systems s ON ul.system_id = s.system_id
    ORDER BY s.system_name, ul.name
", "DB");

$userLevelsById = [];
foreach ($userLevels as $level) {
    $userLevelsById[$level['user_level_id']] = $level;
}
$userLevelsByIdJson = json_encode($userLevelsById);

// ── Permission matrix per user level ──
// Bitmask per PHPMaker's Allow enum (src/Allow.php):
// ADD=1, DELETE=2, EDIT=4, LIST=8, ADMIN=16, VIEW=32, SEARCH=64, IMPORT=128, LOOKUP=256, PUSH=512, EXPORT=1024
const PERM_BITS = [
    'list' => 8,
    'view' => 32,
    'add' => 1,
    'edit' => 4,
    'delete' => 2,
    'search' => 64,
    'export' => 1024,
    'lookup' => 256,
];

$permissionRows = ExecuteRowsAssociative("
    SELECT user_level_id, table_name, permission
    FROM user_level_permissions
    ORDER BY user_level_id, table_name
", "DB");

// PHPMaker prefixes the stored table_name with a project ID (e.g. "{FIMS}users") when it
// seeds rows itself (see models/UserLevelsAdd.php), but some tooling may also write the
// plain name. Normalize so lookups work regardless of which form is on a given row.
function normalizeTableKey($tableName)
{
    return preg_replace('/^\{[^}]*\}/', '', $tableName);
}

$permissionsByLevel = [];
foreach ($permissionRows as $row) {
    $permission = (int)$row['permission'];
    $decoded = ['table_name' => normalizeTableKey($row['table_name'])];
    foreach (PERM_BITS as $key => $bit) {
        $decoded[$key] = ($permission & $bit) !== 0;
    }
    $permissionsByLevel[$row['user_level_id']][] = $decoded;
}

// ── Caption / visibility overrides; see _assets/sql/00 - uac/02 - alter_table/04 - permission_module_captions.sql ──
// Hiding a module only removes it from this list - the underlying
// uac.user_level_permissions rows are left untouched, and it can be restored.
$captionOverrides = [];
$hiddenModules = [];
try {
    $overrideRows = ExecuteRowsAssociative("SELECT table_name, caption, is_hidden FROM permission_module_captions", "DB");
    foreach ($overrideRows as $row) {
        $captionOverrides[$row['table_name']] = $row['caption'];
        if (ConvertToBool($row['is_hidden'])) {
            $hiddenModules[$row['table_name']] = true;
        }
    }
} catch (\Exception $e) {
    // Table not created yet (migration not run) - fall back to registry captions only.
    $captionOverrides = [];
    $hiddenModules = [];
}

// ── Full module registry, sourced from PHPMaker's own table/page list ──
// ($USER_LEVEL_TABLES is loaded globally by src/config.php via src/userlevelsettings.php)
$registryTables = $GLOBALS["USER_LEVEL_TABLES"] ?? [];

// Modules hidden by default on every branch/environment (this list ships with the code, so no DB
// script or PHPMaker change is needed). They remain visible via "Show hidden". Display-only:
// the underlying permission rows are untouched. Use the plain table_name from $USER_LEVEL_TABLES.
$defaultHiddenModules = [
    'audit_logs',
    'file_share_activity',
    'file_share_permissions',
    'file_shares',
    'shared_folder_access',
    'chat_history',
    'chat_sessions',
    'id_format_configs',
    'id_reset_strategies',
    'id_sequences',
    'jwt_sessions',
    'permission_module_captions',
    'aggregated_audit_logs',
    'BullMQManager.php',
    'department_systems',
    'document_asset_types',
    'document_header_assets',
    'document_header_slots',
    'DocumentTypeEditor.php',
    'DocumentTypeManager.php',
    'AiAssistant.php',
    'PageEditor.php',
    'EventPlayground.php',
    'FileManager.php',
    'psgc',
    'plantilla_items',
    'position_categories',
    'positions',
    'PSGCMap.php',
];

$allModules = [];
foreach ($registryTables as $table) {
    $isDefaultHidden = in_array($table[0], $defaultHiddenModules, true);
    $allModules[] = [
        'table_name' => $table[0],          // plain name, e.g. "users", "UserAccess.php"
        'full_key' => $table[4] . $table[0], // PHPMaker's native stored key, e.g. "{FIMS}users"
        'caption' => $captionOverrides[$table[0]] ?? $table[2],
        'is_hidden' => $isDefaultHidden || isset($hiddenModules[$table[0]]),
        'is_default_hidden' => $isDefaultHidden,
    ];
}
usort($allModules, function ($a, $b) {
    return strcasecmp($a['caption'], $b['caption']);
});

// ── Departments / Positions (for the Add User form) ──
$positions = ExecuteRowsAssociative("SELECT position_id, position_title FROM positions WHERE is_active = TRUE ORDER BY position_title", "DB");
$rcCodes = ExecuteRowsAssociative("SELECT rc_id, rc_code, rc_name FROM fims.responsibility_code WHERE status = 'Active' ORDER BY rc_code", "DB");

// ── Systems (for the Create User Level form) ──
$systems = ExecuteRowsAssociative("SELECT system_id, system_name FROM systems WHERE is_active = TRUE ORDER BY system_name", "DB");

$assignmentsByUserJson = json_encode($assignmentsByUser);
$permissionsByLevelJson = json_encode($permissionsByLevel);
$allModulesJson = json_encode($allModules);
$usersByIdJson = json_encode($usersById);

// ── Permission checks ──
$canAddUser = AllowAdd("users");
$canEditUser = AllowEdit("users");
$canAddUserLevel = AllowAdd("user_levels");
$canEditUserLevel = AllowEdit("user_levels");
$canManageAssignments = AllowAdd("user_level_assignments") && AllowDelete("user_level_assignments");
$canManagePermissions = AllowAdd("user_level_permissions") || AllowEdit("user_level_permissions");

// ── URLs ──
$manageLevelsUrl = GetUrl("UserLevelsList");
$manageSystemsUrl = GetUrl("SystemsList");
$deleteAssignmentBaseUrl = GetUrl("UserLevelAssignmentsDelete");
?>

<link rel="stylesheet"
	href="/app/fims-css/fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css') ?>" />

<!-- Main content -->
<div class="container-fluid app-uac-page">
	<!-- Page header -->
	<div class="app-page-header">
		<div class="app-page-header-icon"><i class="fas fa-user-lock"></i></div>
		<div>
			<h1 class="app-page-title">User Access</h1>
			<p class="app-page-subtitle">Review registered users, then assign a role and fine-tune what that role
				can see and do.</p>
		</div>
	</div>

	<!-- Info boxes -->
	<div class="row app-stat-row">
		<div class="col-12 col-sm-6 col-md-3">
			<div class="app-stat-card is-info">
				<span class="app-stat-icon is-info"><i class="fas fa-users"></i></span>
				<div class="app-stat-content">
					<span
						class="app-stat-number"><?php echo $totalUsers; ?></span>
					<span class="app-stat-label">Total Users</span>
				</div>
				<i class="fas fa-users app-stat-ghost-icon"></i>
			</div>
		</div>
		<div class="col-12 col-sm-6 col-md-3">
			<div class="app-stat-card is-success">
				<span class="app-stat-icon is-success"><i class="fas fa-user-check"></i></span>
				<div class="app-stat-content">
					<span
						class="app-stat-number"><?php echo $activeUsers; ?></span>
					<span class="app-stat-label">Active Users</span>
				</div>
				<i class="fas fa-user-check app-stat-ghost-icon"></i>
			</div>
		</div>
		<div class="col-12 col-sm-6 col-md-3">
			<div class="app-stat-card is-warning">
				<span class="app-stat-icon is-warning"><i class="fas fa-user-shield"></i></span>
				<div class="app-stat-content">
					<span
						class="app-stat-number"><?php echo $totalUserLevels; ?></span>
					<span class="app-stat-label">User Levels (Roles)</span>
				</div>
				<i class="fas fa-user-shield app-stat-ghost-icon"></i>
			</div>
		</div>
		<div class="col-12 col-sm-6 col-md-3">
			<div class="app-stat-card is-danger">
				<span class="app-stat-icon is-danger"><i class="fas fa-user-slash"></i></span>
				<div class="app-stat-content">
					<span
						class="app-stat-number"><?php echo $unassignedUsers; ?></span>
					<span class="app-stat-label">Unassigned Access</span>
				</div>
				<i class="fas fa-user-slash app-stat-ghost-icon"></i>
			</div>
		</div>
	</div>

	<!-- Users -->
	<div class="row mt-4">
		<div class="col-12">
			<div class="card card-flat h-100">
				<div class="card-header">
					<h3 class="card-title"><i class="fas fa-users mr-1"></i> Users
						<span class="app-card-title-count"><?php echo count($users); ?></span>
					</h3>
					<div class="card-tools">
						<?php if ($canAddUser): ?>
						<button type="button" class="btn btn-primary btn-sm" id="openAddUserModalBtn">
							<i class="fas fa-user-plus mr-1"></i> Add User
						</button>
						<?php endif; ?>
					</div>
				</div>
				<div class="card-body">
					<div class="app-table-search app-table-search--wide mb-3">
						<i class="fas fa-search app-table-search-icon"></i>
						<input type="text" id="userSearchInput" class="form-control"
							placeholder="Search by name, username, department, office...">
					</div>
					<div id="usersList" class="app-user-list">
						<?php foreach ($users as $user): ?>
						<?php $fullName = trim($user['first_name'] . ' ' . $user['last_name']); ?>
						<div class="app-user-row">
							<div class="app-user-col-user">
								<span
									class="app-table-avatar"><?php echo HtmlEncode(mb_strtoupper(mb_substr($user['username'], 0, 1))); ?></span>
								<div class="app-table-user-cell-text">
									<span class="app-table-user-cell-name"
										title="<?php echo HtmlEncode($fullName ?: $user['username']); ?>"><?php echo HtmlEncode($fullName ?: $user['username']); ?></span>
									<span
										class="app-table-user-cell-sub">@<?php echo HtmlEncode($user['username']); ?></span>
								</div>
							</div>
							<div class="app-user-col-status">
								<span
									class="app-status-dot badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
									<?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
								</span>
							</div>
							<div class="app-user-col-actions">
								<?php if ($canEditUser): ?>
								<button type="button" class="btn app-table-action-btn edit-user-btn"
									data-user-id="<?php echo $user['user_id']; ?>"
									title="Edit User">
									<i class="fas fa-edit"></i> <span class="app-table-action-btn-label">Edit</span>
								</button>
								<?php endif; ?>
								<?php if ($canManageAssignments): ?>
								<button type="button" class="btn app-table-action-btn manage-access-btn"
									data-user-id="<?php echo $user['user_id']; ?>"
									data-username="<?php echo HtmlEncode($user['username']); ?>"
									title="Manage Access">
									<i class="fas fa-user-lock"></i> <span class="app-table-action-btn-label">Manage Access</span>
								</button>
								<?php endif; ?>
							</div>
							<div class="app-user-col-meta">
								<span class="app-user-meta-field">
									<i class="fas fa-building app-user-meta-icon"></i>
									<span class="app-user-meta-label">Dept</span>
									<?php echo HtmlEncode($user['department_acronym'] ?: $user['department_name'] ?: '—'); ?>
								</span>
								<span class="app-user-meta-field">
									<i class="fas fa-map-marker-alt app-user-meta-icon"></i>
									<span class="app-user-meta-label">Office</span>
									<?php echo HtmlEncode($user['office_name'] ?: '—'); ?>
								</span>
								<span class="app-user-meta-field app-user-meta-field--access">
									<i class="fas fa-shield-alt app-user-meta-icon"></i>
									<span class="app-user-meta-label">Access</span>
									<?php if ($user['access_summary']): ?>
									<?php foreach (explode(', ', $user['access_summary']) as $access): ?>
									<span
										class="badge badge-info"><?php echo HtmlEncode($access); ?></span>
									<?php endforeach; ?>
									<?php else: ?>
									<span class="badge badge-secondary">No Access</span>
									<?php endif; ?>
								</span>
								<span class="app-user-meta-field app-user-meta-field--login">
									<i class="fas fa-clock app-user-meta-icon"></i>
									<span class="app-user-meta-label">Last login</span>
									<?php echo $user['last_login'] ? HtmlEncode(date('M j, Y g:i A', strtotime($user['last_login']))) : 'Never'; ?>
								</span>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
					<div class="app-table-pagination">
						<small class="text-muted" id="userPaginationInfo"></small>
						<div class="d-flex align-items-center app-table-pagination-controls">
							<button type="button" class="btn app-table-pager-btn" id="userPrevPageBtn">
								<i class="fas fa-chevron-left"></i>
							</button>
							<div id="userPageNumbers" class="d-flex app-table-pagination-controls"></div>
							<button type="button" class="btn app-table-pager-btn" id="userNextPageBtn">
								<i class="fas fa-chevron-right"></i>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

	</div>

	<?php if ($canManageAssignments): ?>
	<div id="accessPanel">
		<!-- Role Access + Permissions Management — combined into one card since
		     they're directly connected: picking a role on the left (Role
		     Access) is what drives which role's permissions the matrix on the
		     right (Permissions Management) shows and edits. -->
		<div class="row mt-4">
			<div class="col-12">
				<div class="card card-flat">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-user-lock mr-1"></i> Role Access &amp; Permissions</h3>
						<div class="card-tools">
							<?php if ($canAddUserLevel): ?>
							<button type="button" class="btn app-table-toolbar-btn" id="openCreateUserLevelModalBtn">
								<i class="fas fa-plus mr-1"></i> Create User Level
							</button>
							<?php endif; ?>
							<?php if ($canManagePermissions): ?>
							<button type="button" class="btn app-table-toolbar-btn" id="syncPermissionModulesBtn"
								title="Pull in any PHPMaker tables/pages not yet listed here">
								<i class="fas fa-sync-alt mr-1"></i> Sync Permission Modules
							</button>
							<?php endif; ?>
						</div>
					</div>
					<div class="card-body">
						<!-- Full-width context strip: stays visible across both columns
						     (and while scrolling a long permission matrix) so it's always
						     clear whose access is being edited right now. -->
						<div class="app-role-access-selected">
							<i class="fas fa-user"></i>
							<span>Selected user: <strong id="selectedUserLabel">none</strong></span>
						</div>
						<div class="row app-role-permissions-row">
							<!-- Role Access: pick the user + role whose permissions the
							     matrix on the right edits. -->
							<div class="col-md-4 app-role-access-col">
								<div class="app-uac-step-heading">
									<span class="app-uac-step-num">1</span>
									<span>Pick a user &amp; role</span>
								</div>
								<div class="app-flat-form app-role-access-body">
									<div class="step-heading">Current Assignments</div>
									<div class="app-role-access-list" id="currentAssignmentsList">
										<p class="text-muted small mb-0">Select a user to view assignments.</p>
									</div>

									<div class="app-role-list-group">
										<div class="step-heading mt-4">Assign New Access</div>
										<div class="app-table-search app-role-list-search mb-2">
											<i class="fas fa-search app-table-search-icon"></i>
											<input type="text" id="roleSearchInput" class="form-control"
												placeholder="Search roles...">
										</div>
										<input type="hidden" id="assignUserId">
										<input type="hidden" id="assignSystemId">
										<select class="d-none" id="assignLevelSelect">
											<option value="">Select role...</option>
											<?php foreach ($userLevels as $level): ?>
											<option
												value="<?php echo $level['user_level_id']; ?>"
												data-system-id="<?php echo $level['system_id']; ?>">
												<?php echo HtmlEncode($level['name'] . ' (' . $level['system_name'] . ')'); ?>
											</option>
											<?php endforeach; ?>
										</select>
										<p class="text-muted small mb-2 app-role-list-empty" id="roleListEmptyMsg"
											style="display:none;">No roles match your search.</p>
										<div class="app-role-list" id="assignRoleList">
											<?php foreach ($userLevels as $level): ?>
											<button type="button" class="app-role-list-item"
												data-level-id="<?php echo $level['user_level_id']; ?>">
												<span class="app-role-list-item-icon"><i class="fas fa-user-shield"></i></span>
												<span class="app-role-list-item-text">
													<span
														class="app-role-list-item-name"><?php echo HtmlEncode($level['name']); ?></span>
													<span
														class="app-role-list-item-system"><?php echo HtmlEncode($level['system_name']); ?></span>
												</span>
												<?php if ($canEditUserLevel): ?>
												<span class="app-role-list-item-edit"
													data-level-id="<?php echo $level['user_level_id']; ?>"
													title="Edit this user level">
													<i class="fas fa-edit"></i>
												</span>
												<?php endif; ?>
												<span class="app-role-list-item-check"><i class="fas fa-check"></i></span>
											</button>
											<?php endforeach; ?>
										</div>
									</div>
									<button type="button" class="btn btn-primary btn-block" id="assignAccessBtn" disabled>
										<i class="fas fa-plus mr-1"></i> Assign Access
									</button>

									<!-- Fills the space left over once the role list stopped
									     stretching to match the (taller) permissions column —
									     surfaces context about the selected role that was
									     previously only implicit in the matrix on the right. -->
									<div class="app-role-details" id="roleDetailsPanel">
										<div class="app-role-details-empty" id="roleDetailsEmpty">
											<i class="fas fa-circle-info"></i>
											Select a role above to see its details here.
										</div>
										<div class="app-role-details-content" id="roleDetailsContent" style="display:none;">
											<div class="step-heading">Role Details</div>
											<dl class="app-role-details-list">
												<dt>System</dt>
												<dd id="roleDetailsSystem">—</dd>
												<dt>Description</dt>
												<dd id="roleDetailsDescription">—</dd>
												<dt>Users assigned</dt>
												<dd id="roleDetailsUserCount">—</dd>
											</dl>
										</div>
									</div>
								</div>
							</div>

							<!-- Permissions Management: the matrix for whichever role is
							     selected on the left. -->
							<div class="col-md-8 app-permissions-col">
								<div class="app-uac-step-heading">
									<span class="app-uac-step-num">2</span>
									<span>Edit what that role can do</span>
								</div>
								<div id="permissionNoRoleNotice" class="alert alert-warning small mb-3">
									<i class="fas fa-circle-info mr-1"></i>
									<strong>Select a User Level (Role)</strong> on the left first — the checkboxes
									below are disabled until you do, since they edit that role's permissions.
								</div>

								<div class="app-table-permissions-toolbar">
									<div class="app-table-permissions-toolbar-row">
										<div class="app-table-search">
											<i class="fas fa-search app-table-search-icon"></i>
											<input type="text" id="permissionSearchInput" class="form-control"
												placeholder="Search tables...">
										</div>
										<?php if ($canManagePermissions): ?>
										<div class="app-table-permissions-toolbar-actions">
											<label class="app-checkbox-label">
												<input type="checkbox" class="app-checkbox" id="showHiddenModulesCheck">
												Show hidden
											</label>
											<button type="button" class="btn app-table-toolbar-btn is-success"
												id="checkAllPermissionsBtn" disabled>
												Check All
											</button>
											<button type="button" class="btn app-table-toolbar-btn"
												id="uncheckAllPermissionsBtn" disabled>
												Uncheck All
											</button>
										</div>
										<?php endif; ?>
									</div>
								</div>

								<div class="app-table-permissions-wrap">
									<table class="table app-table app-table-permissions" id="permissionMatrixTable">
								<thead>
									<tr>
										<th class="text-center"><span class="app-table-th-check"><input type="checkbox"
													id="permSelectAll"
													title="Toggle every permission, for every page"><span
													class="app-table-th-sublabel">All</span></span></th>
										<th class="text-left app-table-permissions-divider">Page</th>
										<th class="text-center"><span class="app-table-th-check"><input type="checkbox"
													class="perm-col-toggle" data-perm="list"><span>List</span></span>
										</th>
										<th class="text-center"><span class="app-table-th-check"><input type="checkbox"
													class="perm-col-toggle" data-perm="view"><span>View</span></span>
										</th>
										<th class="text-center"><span class="app-table-th-check"><input type="checkbox"
													class="perm-col-toggle" data-perm="add"><span>Add</span></span></th>
										<th class="text-center"><span class="app-table-th-check"><input type="checkbox"
													class="perm-col-toggle" data-perm="edit"><span>Edit</span></span>
										</th>
										<th class="text-center"><span class="app-table-th-check"><input type="checkbox"
													class="perm-col-toggle"
													data-perm="delete"><span>Delete</span></span></th>
										<th class="text-center"><span class="app-table-th-check"><input type="checkbox"
													class="perm-col-toggle"
													data-perm="search"><span>Search</span></span></th>
										<th class="text-center"><span class="app-table-th-check"><input type="checkbox"
													class="perm-col-toggle"
													data-perm="export"><span>Export</span></span></th>
										<th class="text-center"><span class="app-table-th-check"><input type="checkbox"
													class="perm-col-toggle"
													data-perm="lookup"><span>Lookup</span></span></th>
										<?php if ($canManagePermissions): ?>
										<th class="text-center app-table-permissions-divider-left">&nbsp;</th>
										<?php endif; ?>
									</tr>
								</thead>
								<tbody id="permissionMatrixBody">
									<tr>
										<td colspan="11" class="text-muted">Loading modules...</td>
									</tr>
								</tbody>
							</table>
						</div>
						<div class="app-table-pagination">
							<small class="text-muted" id="permissionPaginationInfo"></small>
							<div class="d-flex align-items-center app-table-pagination-controls">
								<button type="button" class="btn app-table-pager-btn" id="permissionPrevPageBtn">
									<i class="fas fa-chevron-left"></i>
								</button>
								<div id="permissionPageNumbers" class="d-flex app-table-pagination-controls"></div>
								<button type="button" class="btn app-table-pager-btn" id="permissionNextPageBtn">
									<i class="fas fa-chevron-right"></i>
								</button>
							</div>
						</div>
							</div><!-- /.app-permissions-col -->
						</div><!-- /.row -->
					</div>
					<?php if ($canManagePermissions): ?>
					<div class="card-footer text-right">
						<button type="button" class="btn btn-primary" id="savePermissionsBtn" disabled>
							<i class="fas fa-save mr-1"></i> Save Permissions
						</button>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>


</div>

<?php if ($canAddUser): ?>
<!-- Add User modal (self-contained, no Bootstrap JS dependency) -->
<div id="addUserOverlay" class="user-modal-overlay">
	<div class="add-user-modal-shell">
		<div class="card mb-0 add-user-modal-card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h3 class="card-title mb-0">Add New User</h3>
				<button type="button" class="btn btn-tool" id="closeAddUserModalBtn"><i
						class="fas fa-times"></i></button>
			</div>
			<form id="addUserForm" class="add-user-modal-form">
				<div class="card-body">
					<div class="add-user-stepper">
						<div class="add-user-stepper-track">
							<div class="add-user-stepper-seg is-active" data-step-index="0"></div>
							<div class="add-user-stepper-seg" data-step-index="1"></div>
							<div class="add-user-stepper-seg" data-step-index="2"></div>
						</div>
						<div class="add-user-stepper-labels">
							<span class="is-active" data-step-index="0">Account</span>
							<span data-step-index="1">Personal Info</span>
							<span data-step-index="2">Assignment</span>
						</div>
					</div>

					<div id="addUserError" class="alert alert-danger" style="display:none;"></div>

					<div class="add-user-step" data-step="0">
						<div class="step-heading">Account</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label>Username *</label>
								<input type="text" class="form-control" name="username" id="addUserUsername" required
									maxlength="50" placeholder="e.g. jdelacruz">
							</div>
							<div class="form-group col-md-6">
								<label>Email *</label>
								<input type="email" class="form-control" name="email" id="addUserEmail" required
									maxlength="100" placeholder="e.g. jdelacruz@nha.gov.ph">
							</div>
						</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label>Employee ID</label>
								<input type="text" class="form-control" name="employee_id" id="addUserEmployeeId"
									maxlength="50" placeholder="e.g. 2021-00123">
							</div>
						</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label>Password *</label>
								<div class="icon-input">
									<input type="password" class="form-control has-suffix" name="password"
										id="addUserPassword" required minlength="6" placeholder="Minimum 6 characters">
									<button type="button" class="icon-input-suffix-btn" id="toggleAddUserPassword"
										tabindex="-1">
										<i class="fas fa-eye" id="toggleAddUserPasswordIcon"></i>
									</button>
								</div>
							</div>
						</div>
					</div>

					<div class="add-user-step" data-step="1" style="display:none;">
						<div class="step-heading">Personal Information</div>
						<div class="form-row">
							<div class="form-group col-md-4">
								<label>First Name</label>
								<input type="text" class="form-control" name="first_name" id="addUserFirstName"
									maxlength="50">
							</div>
							<div class="form-group col-md-4">
								<label>Middle Name</label>
								<input type="text" class="form-control" name="middle_name" id="addUserMiddleName"
									maxlength="50">
							</div>
							<div class="form-group col-md-4">
								<label>Last Name</label>
								<input type="text" class="form-control" name="last_name" id="addUserLastName"
									maxlength="50">
							</div>
						</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label>Suffix</label>
								<select class="form-control" name="suffix" id="addUserSuffix">
									<option value="">(None)</option>
									<option value="Jr.">Jr.</option>
									<option value="Sr.">Sr.</option>
									<option value="II">II</option>
									<option value="III">III</option>
									<option value="IV">IV</option>
									<option value="V">V</option>
								</select>
							</div>
							<div class="form-group col-md-6">
								<label>Mobile Number</label>
								<input type="text" class="form-control" name="mobile_number" id="addUserMobileNumber"
									maxlength="20" placeholder="e.g. 09XX-XXX-XXXX">
							</div>
						</div>
					</div>

					<div class="add-user-step" data-step="2" style="display:none;">
						<div class="step-heading">RC ID &amp; Position</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label>RC ID</label>
								<div class="searchable-select" data-searchable-select data-placeholder="(No RC ID)">
									<input type="text" class="form-control" id="addUserRcIdInput"
										placeholder="(No RC ID)" autocomplete="off">
									<i class="fas fa-chevron-down searchable-select-caret"></i>
									<input type="hidden" name="rc_id" id="addUserRcId">
									<div class="searchable-select-menu" style="display:none;">
										<div class="searchable-select-option is-empty" data-value="" data-label="">No
											results found</div>
										<div class="searchable-select-option" data-value="" data-label="(No RC ID)">(No
											RC ID)</div>
										<?php foreach ($rcCodes as $rc): ?>
										<div class="searchable-select-option"
											data-value="<?php echo HtmlEncode($rc['rc_id']); ?>"
											data-label="<?php echo HtmlEncode($rc['rc_id']); ?> — <?php echo HtmlEncode($rc['rc_name']); ?>">
											<?php echo HtmlEncode($rc['rc_id']); ?>
											—
											<?php echo HtmlEncode($rc['rc_name']); ?>
										</div>
										<?php endforeach; ?>
									</div>
								</div>
								<small class="form-text text-muted">The user's responsibility center</small>
							</div>
							<div class="form-group col-md-6">
								<label>Position</label>
								<div class="searchable-select" data-searchable-select data-placeholder="(No Position)">
									<input type="text" class="form-control" id="addUserPositionInput"
										placeholder="(No Position)" autocomplete="off">
									<i class="fas fa-chevron-down searchable-select-caret"></i>
									<input type="hidden" name="position_id" id="addUserPosition">
									<div class="searchable-select-menu" style="display:none;">
										<div class="searchable-select-option is-empty" data-value="" data-label="">No
											results found</div>
										<div class="searchable-select-option" data-value="" data-label="(No Position)">
											(No Position)</div>
										<?php foreach ($positions as $position): ?>
										<div class="searchable-select-option"
											data-value="<?php echo $position['position_id']; ?>"
											data-label="<?php echo HtmlEncode($position['position_title']); ?>">
											<?php echo HtmlEncode($position['position_title']); ?>
										</div>
										<?php endforeach; ?>
									</div>
								</div>
								<small class="form-text text-muted">The user's position / job title</small>
							</div>
						</div>

						<div class="custom-control custom-switch mt-4">
							<input type="checkbox" class="custom-control-input" name="is_active" id="addUserIsActive"
								checked>
							<label class="custom-control-label" for="addUserIsActive">Active (can log in)</label>
						</div>
					</div>
				</div>
				<div class="card-footer d-flex justify-content-end align-items-center">
					<button type="button" class="btn btn-link text-muted" id="cancelAddUserBtn">Cancel</button>
					<button type="button" class="btn btn-light" id="addUserBackBtn" style="display:none;">Back</button>
					<button type="button" class="btn btn-primary" id="addUserNextBtn">Next</button>
					<button type="submit" class="btn btn-primary" id="submitAddUserBtn" style="display:none;">Save
						User</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ($canEditUser): ?>
<!-- Edit User modal (self-contained, no Bootstrap JS dependency) -->
<div id="editUserOverlay" class="user-modal-overlay">
	<div class="add-user-modal-shell">
		<div class="card mb-0 add-user-modal-card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h3 class="card-title mb-0">Edit User</h3>
				<button type="button" class="btn btn-tool" id="closeEditUserModalBtn"><i
						class="fas fa-times"></i></button>
			</div>
			<form id="editUserForm" class="add-user-modal-form">
				<input type="hidden" id="editUserId">
				<div class="card-body">
					<div class="add-user-stepper">
						<div class="add-user-stepper-track">
							<div class="add-user-stepper-seg is-active" data-step-index="0"></div>
							<div class="add-user-stepper-seg" data-step-index="1"></div>
							<div class="add-user-stepper-seg" data-step-index="2"></div>
						</div>
						<div class="add-user-stepper-labels">
							<span class="is-active" data-step-index="0">Account</span>
							<span data-step-index="1">Personal Info</span>
							<span data-step-index="2">Assignment</span>
						</div>
					</div>

					<div id="editUserError" class="alert alert-danger" style="display:none;"></div>

					<div class="edit-user-step add-user-step" data-step="0">
						<div class="step-heading">Account</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label>Username *</label>
								<input type="text" class="form-control" id="editUserUsername" required maxlength="50">
							</div>
							<div class="form-group col-md-6">
								<label>Email *</label>
								<input type="email" class="form-control" id="editUserEmail" required maxlength="100">
							</div>
						</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label>Employee ID</label>
								<input type="text" class="form-control" id="editUserEmployeeId" maxlength="50"
									placeholder="e.g. 2021-00123">
							</div>
							<div class="form-group col-md-6">
								<label>New Password</label>
								<div class="icon-input">
									<input type="password" class="form-control has-suffix" id="editUserPassword"
										minlength="6" placeholder="Leave blank to keep current">
									<button type="button" class="icon-input-suffix-btn" id="toggleEditUserPassword"
										tabindex="-1">
										<i class="fas fa-eye" id="toggleEditUserPasswordIcon"></i>
									</button>
								</div>
							</div>
						</div>
					</div>

					<div class="edit-user-step add-user-step" data-step="1" style="display:none;">
						<div class="step-heading">Personal Information</div>
						<div class="form-row">
							<div class="form-group col-md-4">
								<label>First Name</label>
								<input type="text" class="form-control" id="editUserFirstName" maxlength="50">
							</div>
							<div class="form-group col-md-4">
								<label>Middle Name</label>
								<input type="text" class="form-control" id="editUserMiddleName" maxlength="50">
							</div>
							<div class="form-group col-md-4">
								<label>Last Name</label>
								<input type="text" class="form-control" id="editUserLastName" maxlength="50">
							</div>
						</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label>Suffix</label>
								<select class="form-control" id="editUserSuffix">
									<option value="">(None)</option>
									<option value="Jr.">Jr.</option>
									<option value="Sr.">Sr.</option>
									<option value="II">II</option>
									<option value="III">III</option>
									<option value="IV">IV</option>
									<option value="V">V</option>
								</select>
							</div>
							<div class="form-group col-md-6">
								<label>Mobile Number</label>
								<input type="text" class="form-control" id="editUserMobileNumber" maxlength="20"
									placeholder="e.g. 09XX-XXX-XXXX">
							</div>
						</div>
					</div>

					<div class="edit-user-step add-user-step" data-step="2" style="display:none;">
						<div class="step-heading">RC ID &amp; Position</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label>RC ID</label>
								<div class="searchable-select" data-searchable-select data-placeholder="(No RC ID)">
									<input type="text" class="form-control" id="editUserRcIdInput"
										placeholder="(No RC ID)" autocomplete="off">
									<i class="fas fa-chevron-down searchable-select-caret"></i>
									<input type="hidden" name="rc_id" id="editUserRcId">
									<div class="searchable-select-menu" style="display:none;">
										<div class="searchable-select-option is-empty" data-value="" data-label="">No
											results found</div>
										<div class="searchable-select-option" data-value="" data-label="(No RC ID)">(No
											RC ID)</div>
										<?php foreach ($rcCodes as $rc): ?>
										<div class="searchable-select-option"
											data-value="<?php echo HtmlEncode($rc['rc_id']); ?>"
											data-label="<?php echo HtmlEncode($rc['rc_id']); ?> — <?php echo HtmlEncode($rc['rc_name']); ?>">
											<?php echo HtmlEncode($rc['rc_id']); ?>
											—
											<?php echo HtmlEncode($rc['rc_name']); ?>
										</div>
										<?php endforeach; ?>
									</div>
								</div>
								<small class="form-text text-muted">The user's responsibility center</small>
							</div>
							<div class="form-group col-md-6">
								<label>Position</label>
								<select class="form-control" id="editUserPosition">
									<option value="">(No Position)</option>
									<?php foreach ($positions as $position): ?>
									<option
										value="<?php echo $position['position_id']; ?>">
										<?php echo HtmlEncode($position['position_title']); ?>
									</option>
									<?php endforeach; ?>
								</select>
								<small class="form-text text-muted">The user's position / job title</small>
							</div>
						</div>

						<div class="custom-control custom-switch mt-4">
							<input type="checkbox" class="custom-control-input" id="editUserIsActive">
							<label class="custom-control-label" for="editUserIsActive">Active (can log in)</label>
						</div>
					</div>
				</div>
				<div class="card-footer d-flex justify-content-end align-items-center">
					<button type="button" class="btn btn-link text-muted" id="cancelEditUserBtn">Cancel</button>
					<button type="button" class="btn btn-light" id="editUserBackBtn" style="display:none;">Back</button>
					<button type="button" class="btn btn-primary" id="editUserNextBtn">Next</button>
					<button type="submit" class="btn btn-primary" id="submitEditUserBtn" style="display:none;">Save
						User</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ($canAddUserLevel): ?>
<!-- Create User Level modal (self-contained, no Bootstrap JS dependency) -->
<div id="createUserLevelOverlay"
	style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
	<div style="max-width:500px; margin:8% auto; background:#fff; border-radius:4px; overflow:hidden;">
		<div class="card mb-0">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h3 class="card-title mb-0">Create User Level</h3>
				<button type="button" class="btn btn-tool" id="closeCreateUserLevelModalBtn"><i
						class="fas fa-times"></i></button>
			</div>
			<form id="createUserLevelForm">
				<div class="card-body">
					<div id="createUserLevelError" class="alert alert-danger" style="display:none;"></div>
					<div class="form-group">
						<label>Name *</label>
						<input type="text" class="form-control" id="createUserLevelName" required maxlength="50"
							placeholder="e.g. Regional Encoder">
					</div>
					<div class="form-group">
						<label>Description</label>
						<textarea class="form-control" id="createUserLevelDescription" rows="2"></textarea>
					</div>
					<div class="form-group">
						<label>System</label>
						<select class="form-control" id="createUserLevelSystem">
							<option value="">(No System)</option>
							<?php foreach ($systems as $system): ?>
							<option
								value="<?php echo $system['system_id']; ?>">
								<?php echo HtmlEncode($system['system_name']); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<small class="form-text text-muted">The system/module this role belongs to</small>
					</div>
				</div>
				<div class="card-footer text-right">
					<button type="button" class="btn btn-link text-muted" id="cancelCreateUserLevelBtn">Cancel</button>
					<button type="submit" class="btn btn-primary" id="submitCreateUserLevelBtn">
						<i class="fas fa-save mr-1"></i> Save User Level
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ($canEditUserLevel): ?>
<!-- Edit User Level modal (self-contained, no Bootstrap JS dependency) -->
<div id="editUserLevelOverlay"
	style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
	<div style="max-width:500px; margin:8% auto; background:#fff; border-radius:4px; overflow:hidden;">
		<div class="card mb-0">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h3 class="card-title mb-0">Edit User Level</h3>
				<button type="button" class="btn btn-tool" id="closeEditUserLevelModalBtn"><i
						class="fas fa-times"></i></button>
			</div>
			<form id="editUserLevelForm">
				<input type="hidden" id="editUserLevelId">
				<div class="card-body">
					<div id="editUserLevelError" class="alert alert-danger" style="display:none;"></div>
					<div class="form-group">
						<label>Name *</label>
						<input type="text" class="form-control" id="editUserLevelName" required maxlength="50">
					</div>
					<div class="form-group">
						<label>Description</label>
						<textarea class="form-control" id="editUserLevelDescription" rows="2"></textarea>
					</div>
					<div class="form-group">
						<label>System</label>
						<select class="form-control" id="editUserLevelSystem">
							<option value="">(No System)</option>
							<?php foreach ($systems as $system): ?>
							<option
								value="<?php echo $system['system_id']; ?>">
								<?php echo HtmlEncode($system['system_name']); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<small class="form-text text-muted">The system/module this role belongs to</small>
					</div>
				</div>
				<div class="card-footer text-right">
					<button type="button" class="btn btn-link text-muted" id="cancelEditUserLevelBtn">Cancel</button>
					<button type="submit" class="btn btn-primary" id="submitEditUserLevelBtn">
						<i class="fas fa-save mr-1"></i> Save User Level
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ($canManagePermissions): ?>
<!-- Rename Module modal (self-contained, no Bootstrap JS dependency) -->
<div id="renameModuleOverlay"
	style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
	<div style="max-width:450px; margin:10% auto; background:#fff; border-radius:4px; overflow:hidden;">
		<div class="card mb-0">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h3 class="card-title mb-0">Rename Module</h3>
				<button type="button" class="btn btn-tool" id="closeRenameModuleModalBtn"><i
						class="fas fa-times"></i></button>
			</div>
			<form id="renameModuleForm">
				<input type="hidden" id="renameModuleTableName">
				<div class="card-body">
					<div id="renameModuleError" class="alert alert-danger" style="display:none;"></div>
					<div class="form-group">
						<label>Display Caption *</label>
						<input type="text" class="form-control" id="renameModuleCaption" required maxlength="150">
						<small class="form-text text-muted">Only changes how this row is labeled here — not the
							underlying page/table.</small>
					</div>
				</div>
				<div class="card-footer text-right">
					<button type="button" class="btn btn-link text-muted" id="cancelRenameModuleBtn">Cancel</button>
					<button type="submit" class="btn btn-primary" id="submitRenameModuleBtn">
						<i class="fas fa-save mr-1"></i> Save Caption
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php endif; ?>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		const
			assignmentsByUser = <?php echo $assignmentsByUserJson ?: '{}'; ?> ;
		const
			permissionsByLevel = <?php echo $permissionsByLevelJson ?: '{}'; ?> ;
		const
			allModules = <?php echo $allModulesJson ?: '[]'; ?> ;
		const
			usersById = <?php echo $usersByIdJson ?: '{}'; ?> ;
		const
			userLevelsById = <?php echo $userLevelsByIdJson ?: '{}'; ?> ;
		const
			deleteAssignmentBaseUrl = <?php echo json_encode($deleteAssignmentBaseUrl); ?> ;
		const
			canManagePermissions = <?php echo $canManagePermissions ? 'true' : 'false'; ?> ;

		// ── Searchable select (combobox) ──
		function initSearchableSelect(wrap) {
			const input = wrap.querySelector('input[type="text"]');
			const hidden = wrap.querySelector('input[type="hidden"]');
			const menu = wrap.querySelector('.searchable-select-menu');
			const noResults = menu.querySelector('.searchable-select-option.is-empty');
			const options = Array.from(menu.querySelectorAll('.searchable-select-option:not(.is-empty)'));
			const placeholder = wrap.getAttribute('data-placeholder') || '';

			function labelFor(value) {
				const opt = options.find(function(o) {
					return o.dataset.value === value;
				});
				return opt ? opt.dataset.label : placeholder;
			}

			function filterOptions(query) {
				const q = query.trim().toLowerCase();
				let anyVisible = false;
				options.forEach(function(o) {
					const match = q === '' || o.dataset.label.toLowerCase().indexOf(q) !== -1;
					o.style.display = match ? '' : 'none';
					o.classList.toggle('is-selected', o.dataset.value === hidden.value);
					if (match) anyVisible = true;
				});
				noResults.style.display = anyVisible ? 'none' : '';
			}

			function openMenu() {
				menu.style.display = 'block';
				filterOptions(input.value === labelFor(hidden.value) ? '' : input.value);
				input.select();
				wrap.scrollIntoView({
					block: 'nearest'
				});
			}

			function closeMenu() {
				menu.style.display = 'none';
				input.value = labelFor(hidden.value);
			}

			input.addEventListener('focus', openMenu);
			input.addEventListener('input', function() {
				filterOptions(input.value);
				menu.style.display = 'block';
			});
			input.addEventListener('keydown', function(e) {
				if (e.key === 'Escape') input.blur();
			});
			input.addEventListener('blur', function() {
				setTimeout(closeMenu, 150);
			});

			options.forEach(function(o) {
				o.addEventListener('mousedown', function(e) {
					e.preventDefault();
					hidden.value = o.dataset.value;
					hidden.dispatchEvent(new Event('change', {
						bubbles: true
					}));
					input.value = o.dataset.label || placeholder;
					menu.style.display = 'none';
				});
			});

			wrap.resetSearchableSelect = function() {
				hidden.value = '';
				input.value = placeholder;
			};
			wrap.setSearchableSelectValue = function(value) {
				hidden.value = value || '';
				input.value = labelFor(hidden.value);
			};

			input.value = labelFor(hidden.value);
		}

		document.querySelectorAll('[data-searchable-select]').forEach(initSearchableSelect);

		function escapeHtml(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		}

		// ── User search / filter + pagination ──
		// Rows are hidden (not removed) so each row's data-user-id-bearing buttons
		// stay intact regardless of which page is currently shown.
		const searchInput = document.getElementById('userSearchInput');
		const USER_PAGE_SIZE = 7;
		let userCurrentPage = 1;
		const userPaginationInfo = document.getElementById('userPaginationInfo');
		const userPrevPageBtn = document.getElementById('userPrevPageBtn');
		const userNextPageBtn = document.getElementById('userNextPageBtn');
		const userPageNumbers = document.getElementById('userPageNumbers');

		function applyUserTableView() {
			const allRows = Array.from(document.querySelectorAll('#usersList .app-user-row'));
			const term = searchInput ? searchInput.value.toLowerCase() : '';
			const matched = allRows.filter(function(row) {
				return !term || row.textContent.toLowerCase().includes(term);
			});

			const totalPages = Math.max(1, Math.ceil(matched.length / USER_PAGE_SIZE));
			if (userCurrentPage > totalPages) userCurrentPage = totalPages;
			if (userCurrentPage < 1) userCurrentPage = 1;

			const start = (userCurrentPage - 1) * USER_PAGE_SIZE;
			const end = start + USER_PAGE_SIZE;

			allRows.forEach(function(row) {
				row.style.display = 'none';
			});
			matched.forEach(function(row, idx) {
				row.style.display = (idx >= start && idx < end) ? '' : 'none';
			});

			if (userPaginationInfo) {
				userPaginationInfo.textContent = matched.length === 0 ?
					'No users to show.' :
					'Showing ' + (start + 1) + '–' + Math.min(end, matched.length) + ' of ' + matched.length;
			}
			if (userPrevPageBtn) userPrevPageBtn.disabled = userCurrentPage <= 1;
			if (userNextPageBtn) userNextPageBtn.disabled = userCurrentPage >= totalPages;
			if (userPageNumbers) {
				userPageNumbers.innerHTML = Array.from({
						length: totalPages
					}, function(_, i) {
						return i + 1;
					})
					.map(function(p) {
						return '<button type="button" class="btn app-table-page-btn' +
							(p === userCurrentPage ? ' is-active' : '') +
							' user-page-btn" data-page="' + p + '">' + p + '</button>';
					}).join('');
			}
		}

		if (searchInput) {
			searchInput.addEventListener('input', function() {
				userCurrentPage = 1;
				applyUserTableView();
			});
		}
		if (userPrevPageBtn) {
			userPrevPageBtn.addEventListener('click', function() {
				userCurrentPage--;
				applyUserTableView();
			});
		}
		if (userNextPageBtn) {
			userNextPageBtn.addEventListener('click', function() {
				userCurrentPage++;
				applyUserTableView();
			});
		}
		if (userPageNumbers) {
			userPageNumbers.addEventListener('click', function(e) {
				const btn = e.target.closest('.user-page-btn');
				if (!btn) return;
				userCurrentPage = parseInt(btn.dataset.page, 10) || 1;
				applyUserTableView();
			});
		}

		applyUserTableView();

		// ── Role & access assignment panel ──
		const selectedUserLabel = document.getElementById('selectedUserLabel');
		const currentAssignmentsList = document.getElementById('currentAssignmentsList');
		const assignUserId = document.getElementById('assignUserId');
		const assignSystemId = document.getElementById('assignSystemId');
		const assignLevelSelect = document.getElementById('assignLevelSelect');
		const assignAccessBtn = document.getElementById('assignAccessBtn');
		const assignRoleList = document.getElementById('assignRoleList');

		function syncRoleListActive(levelId) {
			if (!assignRoleList) return;
			assignRoleList.querySelectorAll('.app-role-list-item').forEach(function(item) {
				item.classList.toggle('is-active', item.dataset.levelId === String(levelId || ''));
			});
		}
		if (assignRoleList) {
			assignRoleList.querySelectorAll('.app-role-list-item').forEach(function(item) {
				item.addEventListener('click', function() {
					if (!assignLevelSelect) return;
					assignLevelSelect.value = this.dataset.levelId;
					assignLevelSelect.dispatchEvent(new Event('change'));
				});
			});
			// Edit icon lives inside each role row now (not a single header button
			// tied to the current selection), so it needs its own click handling
			// and must not also trigger the row's own "select this role" click.
			assignRoleList.querySelectorAll('.app-role-list-item-edit').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					openEditUserLevelModal(this.dataset.levelId);
				});
			});
		}

		const roleSearchInput = document.getElementById('roleSearchInput');
		const roleListEmptyMsg = document.getElementById('roleListEmptyMsg');

		function applyRoleListFilter() {
			if (!assignRoleList) return;
			const term = roleSearchInput ? roleSearchInput.value.toLowerCase().trim() : '';
			let visibleCount = 0;
			assignRoleList.querySelectorAll('.app-role-list-item').forEach(function(item) {
				const matches = !term || item.textContent.toLowerCase().includes(term);
				item.style.display = matches ? '' : 'none';
				if (matches) visibleCount++;
			});
			if (roleListEmptyMsg) roleListEmptyMsg.style.display = visibleCount === 0 ? '' : 'none';
		}
		if (roleSearchInput) {
			roleSearchInput.addEventListener('input', applyRoleListFilter);
		}
		const permissionMatrixBody = document.getElementById('permissionMatrixBody');
		const savePermissionsBtn = document.getElementById('savePermissionsBtn');

		const PERM_KEYS = ['list', 'view', 'add', 'edit', 'delete', 'search', 'export', 'lookup'];
		const permSelectAll = document.getElementById('permSelectAll');
		const permissionSearchInput = document.getElementById('permissionSearchInput');
		const checkAllPermissionsBtn = document.getElementById('checkAllPermissionsBtn');
		const uncheckAllPermissionsBtn = document.getElementById('uncheckAllPermissionsBtn');
		const syncPermissionModulesBtn = document.getElementById('syncPermissionModulesBtn');
		const showHiddenModulesCheck = document.getElementById('showHiddenModulesCheck');

		function rowCheckboxes(tr) {
			return PERM_KEYS.map(function(key) {
				return tr.querySelector('.perm-' + key);
			});
		}

		function syncRowCheckbox(tr) {
			const boxes = rowCheckboxes(tr);
			const rowToggle = tr.querySelector('.perm-row-toggle');
			if (rowToggle) rowToggle.checked = boxes.every(function(b) {
				return b.checked;
			});
		}

		function syncColumnCheckboxes() {
			PERM_KEYS.forEach(function(key) {
				const colToggle = document.querySelector('.perm-col-toggle[data-perm="' + key + '"]');
				if (!colToggle) return;
				const boxes = Array.from(permissionMatrixBody.querySelectorAll('.perm-' + key));
				colToggle.checked = boxes.length > 0 && boxes.every(function(b) {
					return b.checked;
				});
			});
		}

		function syncMasterCheckbox() {
			if (!permSelectAll) return;
			const boxes = Array.from(permissionMatrixBody.querySelectorAll('input[type="checkbox"].perm-cell'));
			permSelectAll.checked = boxes.length > 0 && boxes.every(function(b) {
				return b.checked;
			});
		}

		function syncAllToggles() {
			document.querySelectorAll('#permissionMatrixBody tr[data-table]').forEach(syncRowCheckbox);
			syncColumnCheckboxes();
			syncMasterCheckbox();
		}

		// ── Permission matrix: pagination ──
		// Rows are never removed from the DOM (only hidden) so Save Permissions, which
		// reads every tr[data-table] regardless of visibility, keeps working across pages.
		const PERMISSION_PAGE_SIZE = 10;
		let permissionCurrentPage = 1;
		const permissionPaginationInfo = document.getElementById('permissionPaginationInfo');
		const permissionPrevPageBtn = document.getElementById('permissionPrevPageBtn');
		const permissionNextPageBtn = document.getElementById('permissionNextPageBtn');
		const permissionPageNumbers = document.getElementById('permissionPageNumbers');

		function applyPermissionTableView() {
			if (!permissionMatrixBody) return;
			const term = permissionSearchInput ? permissionSearchInput.value.toLowerCase() : '';
			const allRows = Array.from(permissionMatrixBody.querySelectorAll('tr[data-table]'));
			const matched = allRows.filter(function(row) {
				return !term || row.textContent.toLowerCase().includes(term);
			});

			const totalPages = Math.max(1, Math.ceil(matched.length / PERMISSION_PAGE_SIZE));
			if (permissionCurrentPage > totalPages) permissionCurrentPage = totalPages;
			if (permissionCurrentPage < 1) permissionCurrentPage = 1;

			const start = (permissionCurrentPage - 1) * PERMISSION_PAGE_SIZE;
			const end = start + PERMISSION_PAGE_SIZE;

			allRows.forEach(function(row) {
				row.style.display = 'none';
			});
			matched.forEach(function(row, idx) {
				row.style.display = (idx >= start && idx < end) ? '' : 'none';
			});

			if (permissionPaginationInfo) {
				permissionPaginationInfo.textContent = matched.length === 0 ?
					'No modules to show.' :
					'Showing ' + (start + 1) + '–' + Math.min(end, matched.length) + ' of ' + matched.length;
			}
			if (permissionPrevPageBtn) permissionPrevPageBtn.disabled = permissionCurrentPage <= 1;
			if (permissionNextPageBtn) permissionNextPageBtn.disabled = permissionCurrentPage >= totalPages;
			if (permissionPageNumbers) {
				permissionPageNumbers.innerHTML = Array.from({
						length: totalPages
					}, function(_, i) {
						return i + 1;
					})
					.map(function(p) {
						return '<button type="button" class="btn app-table-page-btn' +
							(p === permissionCurrentPage ? ' is-active' : '') +
							' permission-page-btn" data-page="' + p + '">' + p + '</button>';
					}).join('');
			}
		}

		if (permissionPrevPageBtn) {
			permissionPrevPageBtn.addEventListener('click', function() {
				permissionCurrentPage--;
				applyPermissionTableView();
			});
		}
		if (permissionNextPageBtn) {
			permissionNextPageBtn.addEventListener('click', function() {
				permissionCurrentPage++;
				applyPermissionTableView();
			});
		}
		if (permissionPageNumbers) {
			permissionPageNumbers.addEventListener('click', function(e) {
				const btn = e.target.closest('.permission-page-btn');
				if (!btn) return;
				permissionCurrentPage = parseInt(btn.dataset.page, 10) || 1;
				applyPermissionTableView();
			});
		}

		function renderPermissionMatrix(levelId) {
			if (!permissionMatrixBody) return;
			if (savePermissionsBtn) savePermissionsBtn.disabled = !levelId;
			if (checkAllPermissionsBtn) checkAllPermissionsBtn.disabled = !levelId;
			if (uncheckAllPermissionsBtn) uncheckAllPermissionsBtn.disabled = !levelId;

			const permissionNoRoleNotice = document.getElementById('permissionNoRoleNotice');
			if (permissionNoRoleNotice) permissionNoRoleNotice.style.display = levelId ? 'none' : '';

			if (allModules.length === 0) {
				permissionMatrixBody.innerHTML =
					'<tr><td colspan="11" class="text-muted">No modules to show.</td></tr>';
				if (permissionPaginationInfo) permissionPaginationInfo.textContent = '';
				if (permissionPageNumbers) permissionPageNumbers.innerHTML = '';
				return;
			}

			// The module list itself (rename / remove / restore) is always manageable -
			// only the per-level permission checkboxes need an actual level selected.
			const existing = {};
			if (levelId) {
				(permissionsByLevel[levelId] || []).forEach(function(r) {
					existing[r.table_name] = r;
				});
			}
			const canEditCells = canManagePermissions && !!levelId;

			const checkbox = function(perm, checked) {
				return '<input type="checkbox" class="perm-cell perm-' + perm + '" ' + (checked ? 'checked' :
						'') +
					(canEditCells ? '' : ' disabled') + '>';
			};

			const showHidden = showHiddenModulesCheck && showHiddenModulesCheck.checked;
			const visibleModules = allModules.filter(function(m) {
				return showHidden || !m.is_hidden;
			});

			if (visibleModules.length === 0) {
				permissionMatrixBody.innerHTML =
					'<tr><td colspan="11" class="text-muted">No modules to show.</td></tr>';
				if (permissionPaginationInfo) permissionPaginationInfo.textContent = '';
				if (permissionPageNumbers) permissionPageNumbers.innerHTML = '';
				return;
			}

			permissionMatrixBody.innerHTML = visibleModules.map(function(module) {
				const r = existing[module.table_name] || {};
				const cells = PERM_KEYS.map(function(key) {
					return '<td class="text-center">' + checkbox(key, !!r[key]) + '</td>';
				}).join('');
				let actionsCell = '';
				if (canManagePermissions) {
					let actions =
						'<button type="button" class="btn btn-link btn-sm p-0 edit-module-caption-btn" data-table="' +
						escapeHtml(module.table_name) + '" data-caption="' + escapeHtml(module.caption) +
						'" title="Rename">' +
						'<i class="fas fa-pencil-alt"></i></button>';
					if (module.is_hidden && !module.is_default_hidden) {
						actions +=
							'<button type="button" class="btn btn-link btn-sm p-0 ml-1 text-success restore-module-btn" data-table="' +
							escapeHtml(module.table_name) + '" title="Restore to the list">' +
							'<i class="fas fa-undo"></i></button>';
					} else if (!module.is_hidden) {
						actions +=
							'<button type="button" class="btn btn-link btn-sm p-0 ml-1 text-danger hide-module-btn" data-table="' +
							escapeHtml(module.table_name) + '" title="Hide from the list">' +
							'<i class="fas fa-eye-slash"></i></button>';
					}
					actionsCell = '<td class="text-center app-table-permissions-divider-left">' + actions +
						'</td>';
				}
				const rowClass = module.is_hidden ? ' class="text-muted"' : '';
				return '<tr' + rowClass + ' data-table="' + escapeHtml(module.table_name) +
					'" data-full-key="' + escapeHtml(module.full_key) + '">' +
					'<td><input type="checkbox" class="perm-row-toggle" title="Toggle every permission for this page"' +
					(canEditCells ? '' : ' disabled') + '></td>' +
					'<td class="app-table-permissions-divider">' + escapeHtml(module.caption) + (module
						.is_hidden ? ' <span class="badge badge-secondary">Hidden</span>' : '') + '</td>' +
					cells + actionsCell + '</tr>';
			}).join('');

			syncAllToggles();

			permissionCurrentPage = 1;
			applyPermissionTableView();
		}

		if (permissionMatrixBody) {
			permissionMatrixBody.addEventListener('change', function(e) {
				if (e.target.classList.contains('perm-cell')) {
					syncRowCheckbox(e.target.closest('tr'));
					syncColumnCheckboxes();
					syncMasterCheckbox();
				} else if (e.target.classList.contains('perm-row-toggle')) {
					const tr = e.target.closest('tr');
					rowCheckboxes(tr).forEach(function(b) {
						b.checked = e.target.checked;
					});
					syncColumnCheckboxes();
					syncMasterCheckbox();
				}
			});

			permissionMatrixBody.addEventListener('click', function(e) {
				const editBtn = e.target.closest('.edit-module-caption-btn');
				if (editBtn) {
					openRenameModuleModal(editBtn.dataset.table, editBtn.dataset.caption);
					return;
				}
				const hideBtn = e.target.closest('.hide-module-btn');
				if (hideBtn) {
					const module = allModules.find(function(m) {
						return m.table_name === hideBtn.dataset.table;
					});
					setModuleVisibility(hideBtn.dataset.table, module ? module.caption : hideBtn.dataset
						.table, true);
					return;
				}
				const restoreBtn = e.target.closest('.restore-module-btn');
				if (restoreBtn) {
					const module = allModules.find(function(m) {
						return m.table_name === restoreBtn.dataset.table;
					});
					setModuleVisibility(restoreBtn.dataset.table, module ? module.caption : restoreBtn
						.dataset.table, false);
				}
			});
		}

		if (showHiddenModulesCheck) {
			showHiddenModulesCheck.addEventListener('change', function() {
				renderPermissionMatrix(assignLevelSelect ? assignLevelSelect.value : '');
			});
		}

		function setModuleVisibility(tableName, caption, isHidden) {
			return fetch('/api/user-access/module-visibility', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
					},
					body: JSON.stringify({
						table_name: tableName,
						caption: caption,
						is_hidden: isHidden
					})
				})
				.then(function(r) {
					return r.json();
				})
				.then(function(result) {
					if (result.success) {
						ew.showToast(isHidden ? 'Module removed from the list' : 'Module restored', 'success');
						location.reload();
					} else {
						ew.alert(result.message || 'Error updating module visibility');
					}
				})
				.catch(function() {
					ew.alert('Error connecting to the server. Please try again.');
				});
		}

		document.querySelectorAll('.perm-col-toggle').forEach(function(colToggle) {
			colToggle.addEventListener('change', function() {
				const key = this.dataset.perm;
				document.querySelectorAll('#permissionMatrixBody .perm-' + key).forEach(function(
				b) {
					b.checked = colToggle.checked;
				});
				document.querySelectorAll('#permissionMatrixBody tr[data-table]').forEach(
					syncRowCheckbox);
				syncMasterCheckbox();
			});
		});

		if (permSelectAll) {
			permSelectAll.addEventListener('change', function() {
				document.querySelectorAll('#permissionMatrixBody input[type="checkbox"]').forEach(function(
					b) {
					b.checked = permSelectAll.checked;
				});
				syncColumnCheckboxes();
			});
		}

		if (permissionSearchInput) {
			permissionSearchInput.addEventListener('input', function() {
				permissionCurrentPage = 1;
				applyPermissionTableView();
			});
		}

		function setAllPermissionCheckboxes(checked) {
			document.querySelectorAll('#permissionMatrixBody input[type="checkbox"]').forEach(function(b) {
				b.checked = checked;
			});
			syncAllToggles();
		}

		if (checkAllPermissionsBtn) {
			checkAllPermissionsBtn.addEventListener('click', function() {
				setAllPermissionCheckboxes(true);
			});
		}
		if (uncheckAllPermissionsBtn) {
			uncheckAllPermissionsBtn.addEventListener('click', function() {
				setAllPermissionCheckboxes(false);
			});
		}

		if (syncPermissionModulesBtn) {
			syncPermissionModulesBtn.addEventListener('click', function() {
				syncPermissionModulesBtn.disabled = true;
				fetch('/api/user-access/sync-modules', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						}
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast(result.message || 'Sync complete', 'success');
							if (result.inserted > 0) location.reload();
						} else {
							ew.alert(result.message || 'Error syncing permission modules');
						}
					})
					.catch(function() {
						ew.alert('Error connecting to the server. Please try again.');
					})
					.finally(function() {
						syncPermissionModulesBtn.disabled = false;
					});
			});
		}

		if (savePermissionsBtn) {
			savePermissionsBtn.addEventListener('click', function() {
				const levelId = assignLevelSelect ? assignLevelSelect.value : '';
				if (!levelId) {
					ew.alert('Select a user level first.');
					return;
				}
				const permissions = Array.from(permissionMatrixBody.querySelectorAll('tr[data-table]'))
					.map(function(tr) {
						const entry = {
							table_name: tr.dataset.table,
							full_key: tr.dataset.fullKey
						};
						PERM_KEYS.forEach(function(key) {
							entry[key] = tr.querySelector('.perm-' + key).checked;
						});
						return entry;
					});

				savePermissionsBtn.disabled = true;
				fetch('/api/user-access/permissions', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify({
							user_level_id: levelId,
							permissions: permissions
						})
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							// Merge instead of replace: hidden rows aren't in this payload, and dropping
							// them from the cache would show them unticked under "Show hidden".
							const savedTables = {};
							permissions.forEach(function(p) { savedTables[p.table_name] = true; });
							const untouched = (permissionsByLevel[levelId] || []).filter(function(r) {
								return !savedTables[r.table_name];
							});
							permissionsByLevel[levelId] = untouched.concat(permissions);
							ew.showToast('Permissions saved successfully', 'success');
						} else {
							ew.alert(result.message || 'Error saving permissions');
						}
					})
					.catch(function() {
						ew.alert('Error connecting to the server. Please try again.');
					})
					.finally(function() {
						savePermissionsBtn.disabled = false;
					});
			});
		}

		function selectUserForAccess(userId, username) {
			if (selectedUserLabel) selectedUserLabel.textContent = username + ' (ID: ' + userId + ')';
			if (assignUserId) assignUserId.value = userId;

			const assignments = assignmentsByUser[userId] || [];
			if (currentAssignmentsList) {
				currentAssignmentsList.innerHTML = assignments.length ?
					assignments.map(function(a) {
						return '<div class="app-role-access-list-item">' +
							'<span><strong>' + a.system_name + '</strong>: ' + a.level_name + '</span>' +
							'<a href="' + deleteAssignmentBaseUrl + '/' + a.assignment_id +
							'" class="app-table-action-btn" title="Remove">' +
							'<i class="fas fa-trash"></i> <span class="app-table-action-btn-label">Remove</span></a></div>';
					}).join('') :
					'<p class="text-muted small mb-0">No access assigned yet.</p>';
			}

			// If this user already has exactly one assigned role, select it so its
			// permissions are immediately visible/editable instead of resetting blank.
			// (assignLevelSelect.value silently falls back to '' if the id has no
			// matching <option> - e.g. a stale/deleted role - so re-read it after
			// assigning rather than trusting the original value.)
			if (assignLevelSelect) {
				assignLevelSelect.value = assignments.length === 1 ? String(assignments[0].user_level_id) : '';
			}
			const levelId = assignLevelSelect ? assignLevelSelect.value : '';
			const selectedOption = levelId ? assignLevelSelect.options[assignLevelSelect.selectedIndex] : null;
			if (assignSystemId) assignSystemId.value = selectedOption ? selectedOption.dataset.systemId : '';
			if (assignAccessBtn) assignAccessBtn.disabled = !levelId;
			syncRoleListActive(levelId);
			renderPermissionMatrix(levelId);

			const panel = document.getElementById('accessPanel');
			if (panel) panel.scrollIntoView({
				behavior: 'smooth',
				block: 'start'
			});
		}

		document.querySelectorAll('.manage-access-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				selectUserForAccess(this.dataset.userId, this.dataset.username);
			});
		});

		// ── Role Details panel (fills the space under "Assign Access" that
		// opened up once the role list stopped stretching to match the taller
		// permissions column) ──
		const roleDetailsEmpty = document.getElementById('roleDetailsEmpty');
		const roleDetailsContent = document.getElementById('roleDetailsContent');
		const roleDetailsSystem = document.getElementById('roleDetailsSystem');
		const roleDetailsDescription = document.getElementById('roleDetailsDescription');
		const roleDetailsUserCount = document.getElementById('roleDetailsUserCount');

		function countUsersForLevel(levelId) {
			let count = 0;
			Object.keys(assignmentsByUser).forEach(function(userId) {
				const hasLevel = (assignmentsByUser[userId] || []).some(function(a) {
					return String(a.user_level_id) === String(levelId);
				});
				if (hasLevel) count++;
			});
			return count;
		}

		function renderRoleDetails(levelId) {
			if (!roleDetailsEmpty || !roleDetailsContent) return;
			const level = levelId ? userLevelsById[levelId] : null;
			if (!level) {
				roleDetailsEmpty.style.display = '';
				roleDetailsContent.style.display = 'none';
				return;
			}
			roleDetailsEmpty.style.display = 'none';
			roleDetailsContent.style.display = '';
			if (roleDetailsSystem) roleDetailsSystem.textContent = level.system_name || 'N/A';
			if (roleDetailsDescription) roleDetailsDescription.textContent = level.description || '—';
			if (roleDetailsUserCount) {
				const n = countUsersForLevel(levelId);
				roleDetailsUserCount.textContent = n === 1 ? '1 user' : (n + ' users');
			}
		}

		if (assignLevelSelect) {
			assignLevelSelect.addEventListener('change', function() {
				const selectedOption = this.options[this.selectedIndex];
				assignAccessBtn.disabled = !this.value;
				if (assignSystemId) assignSystemId.value = this.value ? selectedOption.dataset.systemId :
					'';
				syncRoleListActive(this.value);
				renderPermissionMatrix(this.value);
				renderRoleDetails(this.value);
			});
		}

		if (assignAccessBtn) {
			assignAccessBtn.addEventListener('click', function() {
				const userId = assignUserId ? assignUserId.value : '';
				const userLevelId = assignLevelSelect ? assignLevelSelect.value : '';
				if (!userId) {
					ew.alert('Select a user first.');
					return;
				}
				if (!userLevelId) {
					ew.alert('Select a user level first.');
					return;
				}

				assignAccessBtn.disabled = true;
				fetch('/api/user-access/assignments', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify({
							user_id: userId,
							user_level_id: userLevelId,
							system_id: assignSystemId ? assignSystemId.value : null
						})
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('Access assigned successfully', 'success');
							location.reload();
						} else {
							ew.alert(result.message || 'Error assigning access');
							assignAccessBtn.disabled = false;
						}
					})
					.catch(function() {
						ew.alert('Error connecting to the server. Please try again.');
						assignAccessBtn.disabled = false;
					});
			});
		}

		// ── Add User modal ──
		const addUserOverlay = document.getElementById('addUserOverlay');
		const addUserForm = document.getElementById('addUserForm');
		const addUserError = document.getElementById('addUserError');
		const openAddUserModalBtn = document.getElementById('openAddUserModalBtn');

		// ── Add User modal: step navigation ──
		const addUserSteps = Array.from(addUserForm.querySelectorAll('.add-user-step'));
		const addUserStepperSegs = Array.from(addUserForm.querySelectorAll('.add-user-stepper-seg'));
		const addUserStepperLabels = Array.from(addUserForm.querySelectorAll('.add-user-stepper-labels span'));
		const addUserBackBtn = document.getElementById('addUserBackBtn');
		const addUserNextBtn = document.getElementById('addUserNextBtn');
		const submitAddUserBtn = document.getElementById('submitAddUserBtn');
		let addUserStepIndex = 0;

		function validateAddUserStep(stepEl) {
			const fields = Array.from(stepEl.querySelectorAll('input, select, textarea'));
			for (const field of fields) {
				if (!field.checkValidity()) {
					field.reportValidity();
					return false;
				}
			}
			return true;
		}

		function showAddUserStep(index) {
			addUserStepIndex = index;
			addUserSteps.forEach(function(stepEl, i) {
				stepEl.style.display = i === index ? '' : 'none';
				stepEl.classList.toggle('is-visible', i === index);
			});
			addUserStepperSegs.forEach(function(seg, i) {
				seg.classList.toggle('is-active', i === index);
				seg.classList.toggle('is-done', i < index);
			});
			addUserStepperLabels.forEach(function(label, i) {
				label.classList.toggle('is-active', i === index);
				label.classList.toggle('is-done', i < index);
			});
			if (addUserBackBtn) addUserBackBtn.style.display = index === 0 ? 'none' : '';
			const isLastStep = index === addUserSteps.length - 1;
			if (addUserNextBtn) addUserNextBtn.style.display = isLastStep ? 'none' : '';
			if (submitAddUserBtn) submitAddUserBtn.style.display = isLastStep ? '' : 'none';
		}

		if (addUserNextBtn) {
			addUserNextBtn.addEventListener('click', function() {
				if (!validateAddUserStep(addUserSteps[addUserStepIndex])) return;
				showAddUserStep(addUserStepIndex + 1);
			});
		}
		if (addUserBackBtn) {
			addUserBackBtn.addEventListener('click', function() {
				showAddUserStep(addUserStepIndex - 1);
			});
		}
		addUserForm.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' && e.target.tagName === 'INPUT' && addUserStepIndex < addUserSteps
				.length - 1) {
				e.preventDefault();
				if (addUserNextBtn) addUserNextBtn.click();
			}
		});

		function openAddUserModal() {
			if (!addUserOverlay) return;
			addUserForm.reset();
			addUserForm.querySelectorAll('[data-searchable-select]').forEach(function(wrap) {
				if (wrap.resetSearchableSelect) wrap.resetSearchableSelect();
			});
			addUserError.style.display = 'none';
			showAddUserStep(0);
			addUserOverlay.style.display = 'flex';
		}

		function closeAddUserModal() {
			if (addUserOverlay) addUserOverlay.style.display = 'none';
		}

		if (openAddUserModalBtn) openAddUserModalBtn.addEventListener('click', openAddUserModal);
		const closeAddUserModalBtn = document.getElementById('closeAddUserModalBtn');
		const cancelAddUserBtn = document.getElementById('cancelAddUserBtn');
		if (closeAddUserModalBtn) closeAddUserModalBtn.addEventListener('click', closeAddUserModal);
		if (cancelAddUserBtn) cancelAddUserBtn.addEventListener('click', closeAddUserModal);

		const toggleAddUserPassword = document.getElementById('toggleAddUserPassword');
		if (toggleAddUserPassword) {
			toggleAddUserPassword.addEventListener('click', function() {
				const field = document.getElementById('addUserPassword');
				const icon = document.getElementById('toggleAddUserPasswordIcon');
				if (field.type === 'password') {
					field.type = 'text';
					icon.classList.replace('fa-eye', 'fa-eye-slash');
				} else {
					field.type = 'password';
					icon.classList.replace('fa-eye-slash', 'fa-eye');
				}
			});
		}

		if (addUserForm) {
			addUserForm.addEventListener('submit', function(e) {
				e.preventDefault();
				addUserError.style.display = 'none';

				const payload = {
					username: document.getElementById('addUserUsername').value.trim(),
					email: document.getElementById('addUserEmail').value.trim(),
					employee_id: document.getElementById('addUserEmployeeId').value.trim() || null,
					password: document.getElementById('addUserPassword').value,
					first_name: document.getElementById('addUserFirstName').value.trim(),
					middle_name: document.getElementById('addUserMiddleName').value.trim() || null,
					last_name: document.getElementById('addUserLastName').value.trim(),
					suffix: document.getElementById('addUserSuffix').value || null,
					mobile_number: document.getElementById('addUserMobileNumber').value.trim() || null,
					rc_id: document.getElementById('addUserRcId').value || null,
					position_id: document.getElementById('addUserPosition').value || null,
					is_active: document.getElementById('addUserIsActive').checked
				};

				const submitBtn = document.getElementById('submitAddUserBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/users', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('User created successfully', 'success');
							closeAddUserModal();
							location.reload();
						} else {
							addUserError.textContent = result.message || 'Error creating user';
							addUserError.style.display = 'block';
						}
					})
					.catch(function() {
						addUserError.textContent = 'Error connecting to the server. Please try again.';
						addUserError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// ── Create User Level modal ──
		const createUserLevelOverlay = document.getElementById('createUserLevelOverlay');
		const createUserLevelForm = document.getElementById('createUserLevelForm');
		const createUserLevelError = document.getElementById('createUserLevelError');
		const openCreateUserLevelModalBtn = document.getElementById('openCreateUserLevelModalBtn');

		function openCreateUserLevelModal() {
			if (!createUserLevelOverlay) return;
			createUserLevelForm.reset();
			createUserLevelError.style.display = 'none';
			createUserLevelOverlay.style.display = 'block';
		}

		function closeCreateUserLevelModal() {
			if (createUserLevelOverlay) createUserLevelOverlay.style.display = 'none';
		}

		if (openCreateUserLevelModalBtn) openCreateUserLevelModalBtn.addEventListener('click',
			openCreateUserLevelModal);
		const closeCreateUserLevelModalBtn = document.getElementById('closeCreateUserLevelModalBtn');
		const cancelCreateUserLevelBtn = document.getElementById('cancelCreateUserLevelBtn');
		if (closeCreateUserLevelModalBtn) closeCreateUserLevelModalBtn.addEventListener('click',
			closeCreateUserLevelModal);
		if (cancelCreateUserLevelBtn) cancelCreateUserLevelBtn.addEventListener('click',
		closeCreateUserLevelModal);

		if (createUserLevelForm) {
			createUserLevelForm.addEventListener('submit', function(e) {
				e.preventDefault();
				createUserLevelError.style.display = 'none';

				const payload = {
					name: document.getElementById('createUserLevelName').value.trim(),
					description: document.getElementById('createUserLevelDescription').value.trim() ||
						null,
					system_id: document.getElementById('createUserLevelSystem').value || null
				};

				const submitBtn = document.getElementById('submitCreateUserLevelBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/user-levels', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('User level created successfully', 'success');
							closeCreateUserLevelModal();
							location.reload();
						} else {
							createUserLevelError.textContent = result.message ||
								'Error creating user level';
							createUserLevelError.style.display = 'block';
						}
					})
					.catch(function() {
						createUserLevelError.textContent =
							'Error connecting to the server. Please try again.';
						createUserLevelError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// ── Edit User Level modal ──
		const editUserLevelOverlay = document.getElementById('editUserLevelOverlay');
		const editUserLevelForm = document.getElementById('editUserLevelForm');
		const editUserLevelError = document.getElementById('editUserLevelError');

		function openEditUserLevelModal(levelId) {
			if (!editUserLevelOverlay) return;
			const level = userLevelsById[levelId];
			if (!level) return;

			editUserLevelForm.reset();
			editUserLevelError.style.display = 'none';
			document.getElementById('editUserLevelId').value = levelId;
			document.getElementById('editUserLevelName').value = level.name || '';
			document.getElementById('editUserLevelDescription').value = level.description || '';
			document.getElementById('editUserLevelSystem').value = level.system_id || '';

			editUserLevelOverlay.style.display = 'block';
		}

		function closeEditUserLevelModal() {
			if (editUserLevelOverlay) editUserLevelOverlay.style.display = 'none';
		}

		const closeEditUserLevelModalBtn = document.getElementById('closeEditUserLevelModalBtn');
		const cancelEditUserLevelBtn = document.getElementById('cancelEditUserLevelBtn');
		if (closeEditUserLevelModalBtn) closeEditUserLevelModalBtn.addEventListener('click',
			closeEditUserLevelModal);
		if (cancelEditUserLevelBtn) cancelEditUserLevelBtn.addEventListener('click', closeEditUserLevelModal);

		if (editUserLevelForm) {
			editUserLevelForm.addEventListener('submit', function(e) {
				e.preventDefault();
				editUserLevelError.style.display = 'none';

				const levelId = document.getElementById('editUserLevelId').value;
				const payload = {
					name: document.getElementById('editUserLevelName').value.trim(),
					description: document.getElementById('editUserLevelDescription').value.trim() ||
						null,
					system_id: document.getElementById('editUserLevelSystem').value || null
				};

				const submitBtn = document.getElementById('submitEditUserLevelBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/user-levels/' + levelId, {
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('User level updated successfully', 'success');
							closeEditUserLevelModal();
							location.reload();
						} else {
							editUserLevelError.textContent = result.message ||
								'Error updating user level';
							editUserLevelError.style.display = 'block';
						}
					})
					.catch(function() {
						editUserLevelError.textContent =
							'Error connecting to the server. Please try again.';
						editUserLevelError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// ── Edit User modal ──
		const editUserOverlay = document.getElementById('editUserOverlay');
		const editUserForm = document.getElementById('editUserForm');
		const editUserError = document.getElementById('editUserError');

		// ── Edit User modal: step navigation ──
		const editUserSteps = Array.from(editUserForm.querySelectorAll('.edit-user-step'));
		const editUserStepperSegs = Array.from(editUserForm.querySelectorAll('.add-user-stepper-seg'));
		const editUserStepperLabels = Array.from(editUserForm.querySelectorAll('.add-user-stepper-labels span'));
		const editUserBackBtn = document.getElementById('editUserBackBtn');
		const editUserNextBtn = document.getElementById('editUserNextBtn');
		const submitEditUserBtn = document.getElementById('submitEditUserBtn');
		let editUserStepIndex = 0;

		function validateEditUserStep(stepEl) {
			const fields = Array.from(stepEl.querySelectorAll('input, select, textarea'));
			for (const field of fields) {
				if (!field.checkValidity()) {
					field.reportValidity();
					return false;
				}
			}
			return true;
		}

		function showEditUserStep(index) {
			editUserStepIndex = index;
			editUserSteps.forEach(function(stepEl, i) {
				stepEl.style.display = i === index ? '' : 'none';
				stepEl.classList.toggle('is-visible', i === index);
			});
			editUserStepperSegs.forEach(function(seg, i) {
				seg.classList.toggle('is-active', i === index);
				seg.classList.toggle('is-done', i < index);
			});
			editUserStepperLabels.forEach(function(label, i) {
				label.classList.toggle('is-active', i === index);
				label.classList.toggle('is-done', i < index);
			});
			if (editUserBackBtn) editUserBackBtn.style.display = index === 0 ? 'none' : '';
			const isLastStep = index === editUserSteps.length - 1;
			if (editUserNextBtn) editUserNextBtn.style.display = isLastStep ? 'none' : '';
			if (submitEditUserBtn) submitEditUserBtn.style.display = isLastStep ? '' : 'none';
		}

		if (editUserNextBtn) {
			editUserNextBtn.addEventListener('click', function() {
				if (!validateEditUserStep(editUserSteps[editUserStepIndex])) return;
				showEditUserStep(editUserStepIndex + 1);
			});
		}
		if (editUserBackBtn) {
			editUserBackBtn.addEventListener('click', function() {
				showEditUserStep(editUserStepIndex - 1);
			});
		}
		editUserForm.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' && e.target.tagName === 'INPUT' && editUserStepIndex < editUserSteps
				.length - 1) {
				e.preventDefault();
				if (editUserNextBtn) editUserNextBtn.click();
			}
		});

		function openEditUserModal(userId) {
			if (!editUserOverlay) return;
			const user = usersById[userId];
			if (!user) return;

			editUserForm.reset();
			editUserError.style.display = 'none';
			document.getElementById('editUserId').value = userId;
			document.getElementById('editUserUsername').value = user.username || '';
			document.getElementById('editUserEmail').value = user.email || '';
			document.getElementById('editUserEmployeeId').value = user.employee_id || '';
			document.getElementById('editUserFirstName').value = user.first_name || '';
			document.getElementById('editUserMiddleName').value = user.middle_name || '';
			document.getElementById('editUserLastName').value = user.last_name || '';
			document.getElementById('editUserSuffix').value = user.suffix || '';
			document.getElementById('editUserMobileNumber').value = user.mobile_number || '';
			const editUserRcIdWrap = document.getElementById('editUserRcId').closest('[data-searchable-select]');
			if (editUserRcIdWrap && editUserRcIdWrap.setSearchableSelectValue) {
				editUserRcIdWrap.setSearchableSelectValue(user.rc_id || '');
			}
			document.getElementById('editUserPosition').value = user.position_id || '';
			document.getElementById('editUserIsActive').checked = !!user.is_active;

			showEditUserStep(0);
			editUserOverlay.style.display = 'flex';
		}

		function closeEditUserModal() {
			if (editUserOverlay) editUserOverlay.style.display = 'none';
		}

		document.querySelectorAll('.edit-user-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				openEditUserModal(this.dataset.userId);
			});
		});

		const closeEditUserModalBtn = document.getElementById('closeEditUserModalBtn');
		const cancelEditUserBtn = document.getElementById('cancelEditUserBtn');
		if (closeEditUserModalBtn) closeEditUserModalBtn.addEventListener('click', closeEditUserModal);
		if (cancelEditUserBtn) cancelEditUserBtn.addEventListener('click', closeEditUserModal);

		const toggleEditUserPassword = document.getElementById('toggleEditUserPassword');
		if (toggleEditUserPassword) {
			toggleEditUserPassword.addEventListener('click', function() {
				const field = document.getElementById('editUserPassword');
				const icon = document.getElementById('toggleEditUserPasswordIcon');
				if (field.type === 'password') {
					field.type = 'text';
					icon.classList.replace('fa-eye', 'fa-eye-slash');
				} else {
					field.type = 'password';
					icon.classList.replace('fa-eye-slash', 'fa-eye');
				}
			});
		}

		if (editUserForm) {
			editUserForm.addEventListener('submit', function(e) {
				e.preventDefault();
				editUserError.style.display = 'none';

				const userId = document.getElementById('editUserId').value;
				const payload = {
					username: document.getElementById('editUserUsername').value.trim(),
					email: document.getElementById('editUserEmail').value.trim(),
					employee_id: document.getElementById('editUserEmployeeId').value.trim() || null,
					first_name: document.getElementById('editUserFirstName').value.trim(),
					middle_name: document.getElementById('editUserMiddleName').value.trim() || null,
					last_name: document.getElementById('editUserLastName').value.trim(),
					suffix: document.getElementById('editUserSuffix').value || null,
					mobile_number: document.getElementById('editUserMobileNumber').value.trim() ||
						null,
					rc_id: document.getElementById('editUserRcId').value || null,
					position_id: document.getElementById('editUserPosition').value || null,
					is_active: document.getElementById('editUserIsActive').checked
				};
				const password = document.getElementById('editUserPassword').value;
				if (password) {
					payload.password = password;
				}

				const submitBtn = document.getElementById('submitEditUserBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/users/' + userId, {
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('User updated successfully', 'success');
							closeEditUserModal();
							location.reload();
						} else {
							editUserError.textContent = result.message || 'Error updating user';
							editUserError.style.display = 'block';
						}
					})
					.catch(function() {
						editUserError.textContent =
						'Error connecting to the server. Please try again.';
						editUserError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// ── Rename Module modal ──
		const renameModuleOverlay = document.getElementById('renameModuleOverlay');
		const renameModuleForm = document.getElementById('renameModuleForm');
		const renameModuleError = document.getElementById('renameModuleError');

		function openRenameModuleModal(tableName, currentCaption) {
			if (!renameModuleOverlay) return;
			renameModuleForm.reset();
			renameModuleError.style.display = 'none';
			document.getElementById('renameModuleTableName').value = tableName;
			document.getElementById('renameModuleCaption').value = currentCaption || '';
			renameModuleOverlay.style.display = 'block';
		}

		function closeRenameModuleModal() {
			if (renameModuleOverlay) renameModuleOverlay.style.display = 'none';
		}

		const closeRenameModuleModalBtn = document.getElementById('closeRenameModuleModalBtn');
		const cancelRenameModuleBtn = document.getElementById('cancelRenameModuleBtn');
		if (closeRenameModuleModalBtn) closeRenameModuleModalBtn.addEventListener('click', closeRenameModuleModal);
		if (cancelRenameModuleBtn) cancelRenameModuleBtn.addEventListener('click', closeRenameModuleModal);

		if (renameModuleForm) {
			renameModuleForm.addEventListener('submit', function(e) {
				e.preventDefault();
				renameModuleError.style.display = 'none';

				const payload = {
					table_name: document.getElementById('renameModuleTableName').value,
					caption: document.getElementById('renameModuleCaption').value.trim()
				};

				const submitBtn = document.getElementById('submitRenameModuleBtn');
				submitBtn.disabled = true;

				fetch('/api/user-access/module-caption', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
						},
						body: JSON.stringify(payload)
					})
					.then(function(r) {
						return r.json();
					})
					.then(function(result) {
						if (result.success) {
							ew.showToast('Module renamed successfully', 'success');
							closeRenameModuleModal();
							location.reload();
						} else {
							renameModuleError.textContent = result.message || 'Error renaming module';
							renameModuleError.style.display = 'block';
						}
					})
					.catch(function() {
						renameModuleError.textContent =
							'Error connecting to the server. Please try again.';
						renameModuleError.style.display = 'block';
					})
					.finally(function() {
						submitBtn.disabled = false;
					});
			});
		}

		// Show the full module list (with rename/remove actions) immediately, even
		// before a user level is selected - only the per-level checkboxes need a level.
		renderPermissionMatrix('');
	});
</script