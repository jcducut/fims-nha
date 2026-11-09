<?php
/**
 * UAC - Department Management
 * Lists organizational departments and lets an administrator manage
 * the department hierarchy, classification, and contact details directly.
 */
namespace PHPMaker2024\FIMS;

// ── Key metrics ──
$totalDepartments = ExecuteScalar("SELECT COUNT(*) FROM departments", "DB");
$activeDepartments = ExecuteScalar("SELECT COUNT(*) FROM departments WHERE is_active = TRUE", "DB");
$budgetUnits = ExecuteScalar("SELECT COUNT(*) FROM departments WHERE is_budget_unit = TRUE", "DB");
$mainDepartments = ExecuteScalar("SELECT COUNT(*) FROM departments WHERE hierarchy_level = 1", "DB");

// ── Department list (with parent / head / office names) ──
$departments = ExecuteRowsAssociative("
    SELECT
        d.department_id,
        d.department_name,
        d.department_acronym,
        d.department_code,
        d.description,
        d.department_type,
        d.hierarchy_level,
        d.is_active,
        d.is_budget_unit,
        d.contact_number,
        d.email,
        d.office_location,
        d.parent_department_id,
        d.department_head_id,
        d.office_id,
        pd.department_name AS parent_name,
        o.office_name,
        NULLIF(TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, '')), '') AS head_name
    FROM departments d
    LEFT JOIN departments pd ON d.parent_department_id = pd.department_id
    LEFT JOIN offices o ON d.office_id = o.office_id
    LEFT JOIN users u ON d.department_head_id = u.user_id
    ORDER BY d.hierarchy_level, d.department_name
", "DB");

$departmentsById = [];
foreach ($departments as $dept) {
    $departmentsById[$dept['department_id']] = $dept;
}
$departmentsByIdJson = json_encode($departmentsById);

// ── Dropdown data ──
$departmentOptions = ExecuteRowsAssociative("SELECT department_id, department_name FROM departments ORDER BY department_name", "DB");

$departmentTypes = ['MAIN', 'DIVISION', 'SECTION', 'UNIT', 'REGIONAL', 'DISTRICT'];

// ── Permission checks ──
$canAddDepartment = AllowAdd("departments");
$canEditDepartment = AllowEdit("departments");
?>

<!-- Main content -->
<div class="container-fluid">
    <!-- Info boxes -->
    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-building"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Departments</span>
                    <span class="info-box-number"><?php echo $totalDepartments; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active Departments</span>
                    <span class="info-box-number"><?php echo $activeDepartments; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-sitemap"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Main Departments</span>
                    <span class="info-box-number"><?php echo $mainDepartments; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-coins"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Budget Units</span>
                    <span class="info-box-number"><?php echo $budgetUnits; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Department list -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-building mr-1"></i> Departments</h3>
            <div class="card-tools">
                <?php if ($canAddDepartment): ?>
                <button type="button" class="btn btn-primary btn-sm" id="openAddDepartmentModalBtn">
                    <i class="fas fa-plus mr-1"></i> Add Department
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="input-group mb-3" style="max-width: 350px;">
                <input type="text" id="departmentSearchInput" class="form-control" placeholder="Search departments...">
                <div class="input-group-append">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
            </div>
            <div class="table-responsive">
                <table id="departmentsTable" class="table table-hover table-bordered text-nowrap">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Level</th>
                            <th>Parent</th>
                            <th>Head</th>
                            <th>Office</th>
                            <th>Budget Unit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td>
                                <?php echo HtmlEncode($dept['department_name']); ?>
                                <?php if ($dept['department_acronym']): ?>
                                <span class="badge badge-secondary ml-1"><?php echo HtmlEncode($dept['department_acronym']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $dept['department_type'] ? HtmlEncode($dept['department_type']) : '—'; ?></td>
                            <td><?php echo (int)$dept['hierarchy_level']; ?></td>
                            <td><?php echo $dept['parent_name'] ? HtmlEncode($dept['parent_name']) : '—'; ?></td>
                            <td><?php echo $dept['head_name'] ? HtmlEncode($dept['head_name']) : '—'; ?></td>
                            <td><?php echo $dept['office_name'] ? HtmlEncode($dept['office_name']) : '—'; ?></td>
                            <td>
                                <?php if ($dept['is_budget_unit']): ?>
                                <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                <span class="badge badge-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $dept['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($canEditDepartment): ?>
                                <button type="button" class="btn btn-default btn-sm edit-department-btn" data-department-id="<?php echo $dept['department_id']; ?>" title="Edit Department">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Shared field markup for Add/Edit Department modals, parameterized by an ID prefix
// ("add" or "edit") so element IDs don't collide when both modals are on the page.
function renderDepartmentFormFields($prefix, $departmentOptions, $departmentTypes)
{
?>
<div class="form-group">
    <label>Name *</label>
    <input type="text" class="form-control" id="<?php echo $prefix; ?>DepartmentName" required maxlength="150" placeholder="e.g. Budget Division">
</div>
<div class="form-row">
    <div class="form-group col-md-6">
        <label>Acronym</label>
        <input type="text" class="form-control" id="<?php echo $prefix; ?>DepartmentAcronym" maxlength="20" placeholder="e.g. BD">
    </div>
    <div class="form-group col-md-6">
        <label>Code</label>
        <input type="text" class="form-control" id="<?php echo $prefix; ?>DepartmentCode" maxlength="50" placeholder="e.g. DEPT-BD">
    </div>
</div>
<div class="form-group">
    <label>Description</label>
    <textarea class="form-control" id="<?php echo $prefix; ?>DepartmentDescription" rows="2"></textarea>
</div>

<hr>
<h6 class="text-primary font-weight-bold">Classification &amp; Hierarchy</h6>
<div class="form-row">
    <div class="form-group col-md-6">
        <label>Department Type</label>
        <select class="form-control" id="<?php echo $prefix; ?>DepartmentType">
            <option value="">(Not Set)</option>
            <?php foreach ($departmentTypes as $type): ?>
            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group col-md-6">
        <label>Parent Department</label>
        <select class="form-control" id="<?php echo $prefix; ?>DepartmentParent">
            <option value="">(No Parent — Top Level)</option>
            <?php foreach ($departmentOptions as $option): ?>
            <option value="<?php echo $option['department_id']; ?>" id="<?php echo $prefix; ?>ParentOption<?php echo $option['department_id']; ?>">
                <?php echo HtmlEncode($option['department_name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <small class="form-text text-muted">Hierarchy level is derived automatically from the parent</small>
    </div>
</div>
<div class="custom-control custom-switch mb-3">
    <input type="checkbox" class="custom-control-input" id="<?php echo $prefix; ?>DepartmentIsBudgetUnit">
    <label class="custom-control-label" for="<?php echo $prefix; ?>DepartmentIsBudgetUnit">Budget Unit (holds and manages budget allocations)</label>
</div>

<hr>
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="<?php echo $prefix; ?>DepartmentIsActive" checked>
    <label class="custom-control-label" for="<?php echo $prefix; ?>DepartmentIsActive">Active</label>
</div>
<?php
}
?>

<?php if ($canAddDepartment): ?>
<!-- Add Department modal (self-contained, no Bootstrap JS dependency) -->
<div id="addDepartmentOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:700px; margin:3% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Add Department</h3>
                <button type="button" class="btn btn-tool" id="closeAddDepartmentModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="addDepartmentForm">
                <div class="card-body">
                    <div id="addDepartmentError" class="alert alert-danger" style="display:none;"></div>
                    <?php renderDepartmentFormFields('add', $departmentOptions, $departmentTypes); ?>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelAddDepartmentBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitAddDepartmentBtn">
                        <i class="fas fa-save mr-1"></i> Save Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canEditDepartment): ?>
<!-- Edit Department modal (self-contained, no Bootstrap JS dependency) -->
<div id="editDepartmentOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:700px; margin:3% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Edit Department</h3>
                <button type="button" class="btn btn-tool" id="closeEditDepartmentModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="editDepartmentForm">
                <input type="hidden" id="editDepartmentId">
                <div class="card-body">
                    <div id="editDepartmentError" class="alert alert-danger" style="display:none;"></div>
                    <?php renderDepartmentFormFields('edit', $departmentOptions, $departmentTypes); ?>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelEditDepartmentBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitEditDepartmentBtn">
                        <i class="fas fa-save mr-1"></i> Save Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const departmentsById = <?php echo $departmentsByIdJson ?: '{}'; ?>;

    // ── Department search / filter ──
    const searchInput = document.getElementById('departmentSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('#departmentsTable tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }

    function collectDepartmentPayload(prefix) {
        return {
            department_name: document.getElementById(prefix + 'DepartmentName').value.trim(),
            department_acronym: document.getElementById(prefix + 'DepartmentAcronym').value.trim() || null,
            department_code: document.getElementById(prefix + 'DepartmentCode').value.trim() || null,
            description: document.getElementById(prefix + 'DepartmentDescription').value.trim() || null,
            department_type: document.getElementById(prefix + 'DepartmentType').value || null,
            parent_department_id: document.getElementById(prefix + 'DepartmentParent').value || null,
            is_budget_unit: document.getElementById(prefix + 'DepartmentIsBudgetUnit').checked,
            is_active: document.getElementById(prefix + 'DepartmentIsActive').checked
        };
    }

    // ── Add Department modal ──
    const addDepartmentOverlay = document.getElementById('addDepartmentOverlay');
    const addDepartmentForm = document.getElementById('addDepartmentForm');
    const addDepartmentError = document.getElementById('addDepartmentError');
    const openAddDepartmentModalBtn = document.getElementById('openAddDepartmentModalBtn');

    function openAddDepartmentModal() {
        if (!addDepartmentOverlay) return;
        addDepartmentForm.reset();
        addDepartmentError.style.display = 'none';
        addDepartmentOverlay.style.display = 'block';
    }

    function closeAddDepartmentModal() {
        if (addDepartmentOverlay) addDepartmentOverlay.style.display = 'none';
    }

    if (openAddDepartmentModalBtn) openAddDepartmentModalBtn.addEventListener('click', openAddDepartmentModal);
    const closeAddDepartmentModalBtn = document.getElementById('closeAddDepartmentModalBtn');
    const cancelAddDepartmentBtn = document.getElementById('cancelAddDepartmentBtn');
    if (closeAddDepartmentModalBtn) closeAddDepartmentModalBtn.addEventListener('click', closeAddDepartmentModal);
    if (cancelAddDepartmentBtn) cancelAddDepartmentBtn.addEventListener('click', closeAddDepartmentModal);

    if (addDepartmentForm) {
        addDepartmentForm.addEventListener('submit', function (e) {
            e.preventDefault();
            addDepartmentError.style.display = 'none';

            const submitBtn = document.getElementById('submitAddDepartmentBtn');
            submitBtn.disabled = true;

            fetch('/api/department-management/departments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify(collectDepartmentPayload('add'))
            })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        ew.showToast('Department created successfully');
                        closeAddDepartmentModal();
                        location.reload();
                    } else {
                        addDepartmentError.textContent = result.message || 'Error creating department';
                        addDepartmentError.style.display = 'block';
                    }
                })
                .catch(function () {
                    addDepartmentError.textContent = 'Error connecting to the server. Please try again.';
                    addDepartmentError.style.display = 'block';
                })
                .finally(function () {
                    submitBtn.disabled = false;
                });
        });
    }

    // ── Edit Department modal ──
    const editDepartmentOverlay = document.getElementById('editDepartmentOverlay');
    const editDepartmentForm = document.getElementById('editDepartmentForm');
    const editDepartmentError = document.getElementById('editDepartmentError');

    function openEditDepartmentModal(departmentId) {
        if (!editDepartmentOverlay) return;
        const dept = departmentsById[departmentId];
        if (!dept) return;

        editDepartmentForm.reset();
        editDepartmentError.style.display = 'none';
        document.getElementById('editDepartmentId').value = departmentId;
        document.getElementById('editDepartmentName').value = dept.department_name || '';
        document.getElementById('editDepartmentAcronym').value = dept.department_acronym || '';
        document.getElementById('editDepartmentCode').value = dept.department_code || '';
        document.getElementById('editDepartmentDescription').value = dept.description || '';
        document.getElementById('editDepartmentType').value = dept.department_type || '';
        document.getElementById('editDepartmentIsBudgetUnit').checked = !!dept.is_budget_unit;
        document.getElementById('editDepartmentIsActive').checked = !!dept.is_active;

        // A department cannot be its own parent - hide that option in this instance of the modal.
        const selfOption = document.getElementById('editParentOption' + departmentId);
        if (selfOption) selfOption.style.display = 'none';
        document.getElementById('editDepartmentParent').value = dept.parent_department_id || '';

        editDepartmentOverlay.style.display = 'block';
    }

    function closeEditDepartmentModal() {
        if (editDepartmentOverlay) editDepartmentOverlay.style.display = 'none';
    }

    document.querySelectorAll('.edit-department-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openEditDepartmentModal(this.dataset.departmentId);
        });
    });

    const closeEditDepartmentModalBtn = document.getElementById('closeEditDepartmentModalBtn');
    const cancelEditDepartmentBtn = document.getElementById('cancelEditDepartmentBtn');
    if (closeEditDepartmentModalBtn) closeEditDepartmentModalBtn.addEventListener('click', closeEditDepartmentModal);
    if (cancelEditDepartmentBtn) cancelEditDepartmentBtn.addEventListener('click', closeEditDepartmentModal);

    if (editDepartmentForm) {
        editDepartmentForm.addEventListener('submit', function (e) {
            e.preventDefault();
            editDepartmentError.style.display = 'none';

            const departmentId = document.getElementById('editDepartmentId').value;
            const submitBtn = document.getElementById('submitEditDepartmentBtn');
            submitBtn.disabled = true;

            fetch('/api/department-management/departments/' + departmentId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify(collectDepartmentPayload('edit'))
            })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        ew.showToast('Department updated successfully');
                        closeEditDepartmentModal();
                        location.reload();
                    } else {
                        editDepartmentError.textContent = result.message || 'Error updating department';
                        editDepartmentError.style.display = 'block';
                    }
                })
                .catch(function () {
                    editDepartmentError.textContent = 'Error connecting to the server. Please try again.';
                    editDepartmentError.style.display = 'block';
                })
                .finally(function () {
                    submitBtn.disabled = false;
                });
        });
    }
});
</script>
