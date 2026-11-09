<?php
/**
 * UAC - Position Management
 * Lists position categories and position templates, and lets an
 * administrator manage both directly.
 */
namespace PHPMaker2024\FIMS;

// ── Key metrics ──
$totalPositions = ExecuteScalar("SELECT COUNT(*) FROM positions", "DB");
$activePositions = ExecuteScalar("SELECT COUNT(*) FROM positions WHERE is_active = TRUE", "DB");
$totalCategories = ExecuteScalar("SELECT COUNT(*) FROM position_categories", "DB");
$inactivePositions = ExecuteScalar("SELECT COUNT(*) FROM positions WHERE is_active = FALSE", "DB");

// ── Position categories ──
$categories = ExecuteRowsAssociative("
    SELECT position_category_id, category_name, description, is_active
    FROM position_categories
    ORDER BY category_name
", "DB");

$categoriesById = [];
foreach ($categories as $category) {
    $categoriesById[$category['position_category_id']] = $category;
}
$categoriesByIdJson = json_encode($categoriesById);

// ── Positions (with category name) ──
$positions = ExecuteRowsAssociative("
    SELECT
        p.position_id,
        p.position_category_id,
        p.position_code,
        p.position_title,
        p.position_level,
        p.salary_grade,
        p.step_increment,
        p.job_description,
        p.qualification_standards,
        p.is_active,
        pc.category_name
    FROM positions p
    LEFT JOIN position_categories pc ON p.position_category_id = pc.position_category_id
    ORDER BY p.position_title
", "DB");

$positionsById = [];
foreach ($positions as $position) {
    $positionsById[$position['position_id']] = $position;
}
$positionsByIdJson = json_encode($positionsById);

// ── Permission checks ──
// Position categories and positions are managed under the "positions" table permission.
$canAddPosition = AllowAdd("positions");
$canEditPosition = AllowEdit("positions");
?>

<!-- Main content -->
<div class="container-fluid">
    <!-- Info boxes -->
    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-id-badge"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Positions</span>
                    <span class="info-box-number"><?php echo $totalPositions; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active Positions</span>
                    <span class="info-box-number"><?php echo $activePositions; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-layer-group"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Categories</span>
                    <span class="info-box-number"><?php echo $totalCategories; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-ban"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Inactive Positions</span>
                    <span class="info-box-number"><?php echo $inactivePositions; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Position Categories -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-layer-group mr-1"></i> Position Categories</h3>
            <div class="card-tools">
                <?php if ($canAddPosition): ?>
                <button type="button" class="btn btn-primary btn-sm" id="openAddCategoryModalBtn">
                    <i class="fas fa-plus mr-1"></i> Add Category
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover table-bordered text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo HtmlEncode($category['category_name']); ?></td>
                        <td><?php echo $category['description'] ? HtmlEncode($category['description']) : '—'; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $category['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($canEditPosition): ?>
                            <button type="button" class="btn btn-default btn-sm edit-category-btn" data-category-id="<?php echo $category['position_category_id']; ?>" title="Edit Category">
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

    <!-- Positions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-id-badge mr-1"></i> Positions</h3>
            <div class="card-tools">
                <?php if ($canAddPosition): ?>
                <button type="button" class="btn btn-primary btn-sm" id="openAddPositionModalBtn">
                    <i class="fas fa-plus mr-1"></i> Add Position
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="input-group mb-3" style="max-width: 350px;">
                <input type="text" id="positionSearchInput" class="form-control" placeholder="Search positions...">
                <div class="input-group-append">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
            </div>
            <div class="table-responsive">
                <table id="positionsTable" class="table table-hover table-bordered text-nowrap">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Level</th>
                            <th>Salary Grade</th>
                            <th>Step</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($positions as $position): ?>
                        <tr>
                            <td><?php echo HtmlEncode($position['position_code']); ?></td>
                            <td><?php echo HtmlEncode($position['position_title']); ?></td>
                            <td><?php echo $position['category_name'] ? HtmlEncode($position['category_name']) : '—'; ?></td>
                            <td><?php echo $position['position_level'] !== null ? (int)$position['position_level'] : '—'; ?></td>
                            <td><?php echo $position['salary_grade'] !== null ? (int)$position['salary_grade'] : '—'; ?></td>
                            <td><?php echo $position['step_increment'] !== null ? (int)$position['step_increment'] : '—'; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $position['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $position['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($canEditPosition): ?>
                                <button type="button" class="btn btn-default btn-sm edit-position-btn" data-position-id="<?php echo $position['position_id']; ?>" title="Edit Position">
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
// Shared field markup for Add/Edit Category modals
function renderCategoryFormFields($prefix)
{
?>
<div class="form-group">
    <label>Category Name *</label>
    <input type="text" class="form-control" id="<?php echo $prefix; ?>CategoryName" required maxlength="100" placeholder="e.g. First Level">
</div>
<div class="form-group">
    <label>Description</label>
    <textarea class="form-control" id="<?php echo $prefix; ?>CategoryDescription" rows="2"></textarea>
</div>
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="<?php echo $prefix; ?>CategoryIsActive" checked>
    <label class="custom-control-label" for="<?php echo $prefix; ?>CategoryIsActive">Active</label>
</div>
<?php
}

// Shared field markup for Add/Edit Position modals
function renderPositionFormFields($prefix, $categories)
{
?>
<div class="form-row">
    <div class="form-group col-md-6">
        <label>Position Code *</label>
        <input type="text" class="form-control" id="<?php echo $prefix; ?>PositionCode" required maxlength="50" placeholder="e.g. ADMIN-ASST-III">
    </div>
    <div class="form-group col-md-6">
        <label>Position Title *</label>
        <input type="text" class="form-control" id="<?php echo $prefix; ?>PositionTitle" required maxlength="200" placeholder="e.g. Administrative Assistant III">
    </div>
</div>
<div class="form-group">
    <label>Category</label>
    <select class="form-control" id="<?php echo $prefix; ?>PositionCategory">
        <option value="">(No Category)</option>
        <?php foreach ($categories as $category): ?>
        <option value="<?php echo $category['position_category_id']; ?>"><?php echo HtmlEncode($category['category_name']); ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="form-row">
    <div class="form-group col-md-4">
        <label>Position Level</label>
        <input type="number" class="form-control" id="<?php echo $prefix; ?>PositionLevel" min="1">
    </div>
    <div class="form-group col-md-4">
        <label>Salary Grade</label>
        <input type="number" class="form-control" id="<?php echo $prefix; ?>PositionSalaryGrade" min="1" max="33" placeholder="1-33">
    </div>
    <div class="form-group col-md-4">
        <label>Step Increment</label>
        <input type="number" class="form-control" id="<?php echo $prefix; ?>PositionStepIncrement" min="1" max="8" placeholder="1-8" value="1">
    </div>
</div>
<div class="form-group">
    <label>Job Description</label>
    <textarea class="form-control" id="<?php echo $prefix; ?>PositionJobDescription" rows="2"></textarea>
</div>
<div class="form-group">
    <label>Qualification Standards</label>
    <textarea class="form-control" id="<?php echo $prefix; ?>PositionQualificationStandards" rows="2" placeholder="Education, experience, training, eligibility"></textarea>
</div>
<div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="<?php echo $prefix; ?>PositionIsActive" checked>
    <label class="custom-control-label" for="<?php echo $prefix; ?>PositionIsActive">Active</label>
</div>
<?php
}
?>

<?php if ($canAddPosition): ?>
<!-- Add Category modal -->
<div id="addCategoryOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:500px; margin:8% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Add Category</h3>
                <button type="button" class="btn btn-tool" id="closeAddCategoryModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="addCategoryForm">
                <div class="card-body">
                    <div id="addCategoryError" class="alert alert-danger" style="display:none;"></div>
                    <?php renderCategoryFormFields('add'); ?>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelAddCategoryBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitAddCategoryBtn">
                        <i class="fas fa-save mr-1"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Position modal -->
<div id="addPositionOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:700px; margin:3% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Add Position</h3>
                <button type="button" class="btn btn-tool" id="closeAddPositionModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="addPositionForm">
                <div class="card-body">
                    <div id="addPositionError" class="alert alert-danger" style="display:none;"></div>
                    <?php renderPositionFormFields('add', $categories); ?>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelAddPositionBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitAddPositionBtn">
                        <i class="fas fa-save mr-1"></i> Save Position
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canEditPosition): ?>
<!-- Edit Category modal -->
<div id="editCategoryOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:500px; margin:8% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Edit Category</h3>
                <button type="button" class="btn btn-tool" id="closeEditCategoryModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="editCategoryForm">
                <input type="hidden" id="editCategoryId">
                <div class="card-body">
                    <div id="editCategoryError" class="alert alert-danger" style="display:none;"></div>
                    <?php renderCategoryFormFields('edit'); ?>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelEditCategoryBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitEditCategoryBtn">
                        <i class="fas fa-save mr-1"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Position modal -->
<div id="editPositionOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:700px; margin:3% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Edit Position</h3>
                <button type="button" class="btn btn-tool" id="closeEditPositionModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="editPositionForm">
                <input type="hidden" id="editPositionId">
                <div class="card-body">
                    <div id="editPositionError" class="alert alert-danger" style="display:none;"></div>
                    <?php renderPositionFormFields('edit', $categories); ?>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelEditPositionBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitEditPositionBtn">
                        <i class="fas fa-save mr-1"></i> Save Position
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const categoriesById = <?php echo $categoriesByIdJson ?: '{}'; ?>;
    const positionsById = <?php echo $positionsByIdJson ?: '{}'; ?>;

    // ── Position search / filter ──
    const positionSearchInput = document.getElementById('positionSearchInput');
    if (positionSearchInput) {
        positionSearchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('#positionsTable tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }

    function openModal(overlay, form, errorBox) {
        if (!overlay) return;
        if (form) form.reset();
        if (errorBox) errorBox.style.display = 'none';
        overlay.style.display = 'block';
    }

    function closeModal(overlay) {
        if (overlay) overlay.style.display = 'none';
    }

    function submitJson(url, method, payload, onSuccess, onError) {
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
            },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result.success) {
                    onSuccess(result);
                } else {
                    onError(result.message || 'Request failed');
                }
            })
            .catch(function () {
                onError('Error connecting to the server. Please try again.');
            });
    }

    // ── Category payload / prefill helpers ──
    function collectCategoryPayload(prefix) {
        return {
            category_name: document.getElementById(prefix + 'CategoryName').value.trim(),
            description: document.getElementById(prefix + 'CategoryDescription').value.trim() || null,
            is_active: document.getElementById(prefix + 'CategoryIsActive').checked
        };
    }

    // ── Position payload / prefill helpers ──
    function collectPositionPayload(prefix) {
        return {
            position_code: document.getElementById(prefix + 'PositionCode').value.trim(),
            position_title: document.getElementById(prefix + 'PositionTitle').value.trim(),
            position_category_id: document.getElementById(prefix + 'PositionCategory').value || null,
            position_level: document.getElementById(prefix + 'PositionLevel').value || null,
            salary_grade: document.getElementById(prefix + 'PositionSalaryGrade').value || null,
            step_increment: document.getElementById(prefix + 'PositionStepIncrement').value || null,
            job_description: document.getElementById(prefix + 'PositionJobDescription').value.trim() || null,
            qualification_standards: document.getElementById(prefix + 'PositionQualificationStandards').value.trim() || null,
            is_active: document.getElementById(prefix + 'PositionIsActive').checked
        };
    }

    // ── Add Category modal ──
    const addCategoryOverlay = document.getElementById('addCategoryOverlay');
    const addCategoryForm = document.getElementById('addCategoryForm');
    const addCategoryError = document.getElementById('addCategoryError');
    const openAddCategoryModalBtn = document.getElementById('openAddCategoryModalBtn');
    if (openAddCategoryModalBtn) {
        openAddCategoryModalBtn.addEventListener('click', function () {
            openModal(addCategoryOverlay, addCategoryForm, addCategoryError);
        });
    }
    const closeAddCategoryModalBtn = document.getElementById('closeAddCategoryModalBtn');
    const cancelAddCategoryBtn = document.getElementById('cancelAddCategoryBtn');
    if (closeAddCategoryModalBtn) closeAddCategoryModalBtn.addEventListener('click', function () { closeModal(addCategoryOverlay); });
    if (cancelAddCategoryBtn) cancelAddCategoryBtn.addEventListener('click', function () { closeModal(addCategoryOverlay); });

    if (addCategoryForm) {
        addCategoryForm.addEventListener('submit', function (e) {
            e.preventDefault();
            addCategoryError.style.display = 'none';
            const submitBtn = document.getElementById('submitAddCategoryBtn');
            submitBtn.disabled = true;
            submitJson('/api/position-management/categories', 'POST', collectCategoryPayload('add'),
                function () {
                    ew.showToast('Category created successfully');
                    closeModal(addCategoryOverlay);
                    location.reload();
                },
                function (message) {
                    addCategoryError.textContent = message;
                    addCategoryError.style.display = 'block';
                    submitBtn.disabled = false;
                });
        });
    }

    // ── Edit Category modal ──
    const editCategoryOverlay = document.getElementById('editCategoryOverlay');
    const editCategoryForm = document.getElementById('editCategoryForm');
    const editCategoryError = document.getElementById('editCategoryError');

    function openEditCategoryModal(categoryId) {
        const category = categoriesById[categoryId];
        if (!category) return;
        openModal(editCategoryOverlay, editCategoryForm, editCategoryError);
        document.getElementById('editCategoryId').value = categoryId;
        document.getElementById('editCategoryName').value = category.category_name || '';
        document.getElementById('editCategoryDescription').value = category.description || '';
        document.getElementById('editCategoryIsActive').checked = !!category.is_active;
    }

    document.querySelectorAll('.edit-category-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openEditCategoryModal(this.dataset.categoryId); });
    });
    const closeEditCategoryModalBtn = document.getElementById('closeEditCategoryModalBtn');
    const cancelEditCategoryBtn = document.getElementById('cancelEditCategoryBtn');
    if (closeEditCategoryModalBtn) closeEditCategoryModalBtn.addEventListener('click', function () { closeModal(editCategoryOverlay); });
    if (cancelEditCategoryBtn) cancelEditCategoryBtn.addEventListener('click', function () { closeModal(editCategoryOverlay); });

    if (editCategoryForm) {
        editCategoryForm.addEventListener('submit', function (e) {
            e.preventDefault();
            editCategoryError.style.display = 'none';
            const categoryId = document.getElementById('editCategoryId').value;
            const submitBtn = document.getElementById('submitEditCategoryBtn');
            submitBtn.disabled = true;
            submitJson('/api/position-management/categories/' + categoryId, 'PUT', collectCategoryPayload('edit'),
                function () {
                    ew.showToast('Category updated successfully');
                    closeModal(editCategoryOverlay);
                    location.reload();
                },
                function (message) {
                    editCategoryError.textContent = message;
                    editCategoryError.style.display = 'block';
                    submitBtn.disabled = false;
                });
        });
    }

    // ── Add Position modal ──
    const addPositionOverlay = document.getElementById('addPositionOverlay');
    const addPositionForm = document.getElementById('addPositionForm');
    const addPositionError = document.getElementById('addPositionError');
    const openAddPositionModalBtn = document.getElementById('openAddPositionModalBtn');
    if (openAddPositionModalBtn) {
        openAddPositionModalBtn.addEventListener('click', function () {
            openModal(addPositionOverlay, addPositionForm, addPositionError);
        });
    }
    const closeAddPositionModalBtn = document.getElementById('closeAddPositionModalBtn');
    const cancelAddPositionBtn = document.getElementById('cancelAddPositionBtn');
    if (closeAddPositionModalBtn) closeAddPositionModalBtn.addEventListener('click', function () { closeModal(addPositionOverlay); });
    if (cancelAddPositionBtn) cancelAddPositionBtn.addEventListener('click', function () { closeModal(addPositionOverlay); });

    if (addPositionForm) {
        addPositionForm.addEventListener('submit', function (e) {
            e.preventDefault();
            addPositionError.style.display = 'none';
            const submitBtn = document.getElementById('submitAddPositionBtn');
            submitBtn.disabled = true;
            submitJson('/api/position-management/positions', 'POST', collectPositionPayload('add'),
                function () {
                    ew.showToast('Position created successfully');
                    closeModal(addPositionOverlay);
                    location.reload();
                },
                function (message) {
                    addPositionError.textContent = message;
                    addPositionError.style.display = 'block';
                    submitBtn.disabled = false;
                });
        });
    }

    // ── Edit Position modal ──
    const editPositionOverlay = document.getElementById('editPositionOverlay');
    const editPositionForm = document.getElementById('editPositionForm');
    const editPositionError = document.getElementById('editPositionError');

    function openEditPositionModal(positionId) {
        const position = positionsById[positionId];
        if (!position) return;
        openModal(editPositionOverlay, editPositionForm, editPositionError);
        document.getElementById('editPositionId').value = positionId;
        document.getElementById('editPositionCode').value = position.position_code || '';
        document.getElementById('editPositionTitle').value = position.position_title || '';
        document.getElementById('editPositionCategory').value = position.position_category_id || '';
        document.getElementById('editPositionLevel').value = position.position_level !== null ? position.position_level : '';
        document.getElementById('editPositionSalaryGrade').value = position.salary_grade !== null ? position.salary_grade : '';
        document.getElementById('editPositionStepIncrement').value = position.step_increment !== null ? position.step_increment : '';
        document.getElementById('editPositionJobDescription').value = position.job_description || '';
        document.getElementById('editPositionQualificationStandards').value = position.qualification_standards || '';
        document.getElementById('editPositionIsActive').checked = !!position.is_active;
    }

    document.querySelectorAll('.edit-position-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openEditPositionModal(this.dataset.positionId); });
    });
    const closeEditPositionModalBtn = document.getElementById('closeEditPositionModalBtn');
    const cancelEditPositionBtn = document.getElementById('cancelEditPositionBtn');
    if (closeEditPositionModalBtn) closeEditPositionModalBtn.addEventListener('click', function () { closeModal(editPositionOverlay); });
    if (cancelEditPositionBtn) cancelEditPositionBtn.addEventListener('click', function () { closeModal(editPositionOverlay); });

    if (editPositionForm) {
        editPositionForm.addEventListener('submit', function (e) {
            e.preventDefault();
            editPositionError.style.display = 'none';
            const positionId = document.getElementById('editPositionId').value;
            const submitBtn = document.getElementById('submitEditPositionBtn');
            submitBtn.disabled = true;
            submitJson('/api/position-management/positions/' + positionId, 'PUT', collectPositionPayload('edit'),
                function () {
                    ew.showToast('Position updated successfully');
                    closeModal(editPositionOverlay);
                    location.reload();
                },
                function (message) {
                    editPositionError.textContent = message;
                    editPositionError.style.display = 'block';
                    submitBtn.disabled = false;
                });
        });
    }
});
</script>
