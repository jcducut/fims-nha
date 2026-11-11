<?php

namespace PHPMaker2024\FIMS;

// Set up and run Grid object
$Grid = Container("UsersGrid");
$Grid->run();
?>
<?php if (!$Grid->isExport()) { ?>
<script>
var fusersgrid;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let currentTable = <?= JsonEncode($Grid->toClientVar()) ?>;
    ew.deepAssign(ew.vars, { tables: { users: currentTable } });
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fusersgrid")
        .setPageId("grid")
        .setFormKeyCountName("<?= $Grid->FormKeyCountName ?>")

        // Add fields
        .setFields([
            ["user_id", [fields.user_id.visible && fields.user_id.required ? ew.Validators.required(fields.user_id.caption) : null], fields.user_id.isInvalid],
            ["_username", [fields._username.visible && fields._username.required ? ew.Validators.required(fields._username.caption) : null], fields._username.isInvalid],
            ["_email", [fields._email.visible && fields._email.required ? ew.Validators.required(fields._email.caption) : null], fields._email.isInvalid],
            ["first_name", [fields.first_name.visible && fields.first_name.required ? ew.Validators.required(fields.first_name.caption) : null], fields.first_name.isInvalid],
            ["last_name", [fields.last_name.visible && fields.last_name.required ? ew.Validators.required(fields.last_name.caption) : null], fields.last_name.isInvalid],
            ["suffix", [fields.suffix.visible && fields.suffix.required ? ew.Validators.required(fields.suffix.caption) : null], fields.suffix.isInvalid],
            ["department_id", [fields.department_id.visible && fields.department_id.required ? ew.Validators.required(fields.department_id.caption) : null, ew.Validators.integer], fields.department_id.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["user_level_id", [fields.user_level_id.visible && fields.user_level_id.required ? ew.Validators.required(fields.user_level_id.caption) : null], fields.user_level_id.isInvalid],
            ["position_id", [fields.position_id.visible && fields.position_id.required ? ew.Validators.required(fields.position_id.caption) : null, ew.Validators.integer], fields.position_id.isInvalid],
            ["employee_id", [fields.employee_id.visible && fields.employee_id.required ? ew.Validators.required(fields.employee_id.caption) : null], fields.employee_id.isInvalid],
            ["must_change_password", [fields.must_change_password.visible && fields.must_change_password.required ? ew.Validators.required(fields.must_change_password.caption) : null], fields.must_change_password.isInvalid],
            ["rc_id", [fields.rc_id.visible && fields.rc_id.required ? ew.Validators.required(fields.rc_id.caption) : null], fields.rc_id.isInvalid]
        ])

        // Check empty row
        .setEmptyRow(
            function (rowIndex) {
                let fobj = this.getForm(),
                    fields = [["_username",false],["_email",false],["first_name",false],["last_name",false],["suffix",false],["department_id",false],["is_active",true],["user_level_id[]",false],["position_id",false],["employee_id",false],["must_change_password",true],["rc_id",false]];
                if (fields.some(field => ew.valueChanged(fobj, rowIndex, ...field)))
                    return false;
                return true;
            }
        )

        // Form_CustomValidate
        .setCustomValidate(
            function (fobj) { // DO NOT CHANGE THIS LINE! (except for adding "async" keyword)!
                    // Your custom validation code in JAVASCRIPT here, return false if invalid.
                    return true;
                }
        )

        // Use JavaScript validation or not
        .setValidateRequired(ew.CLIENT_VALIDATE)

        // Dynamic selection lists
        .setLists({
            "department_id": <?= $Grid->department_id->toClientList($Grid) ?>,
            "is_active": <?= $Grid->is_active->toClientList($Grid) ?>,
            "user_level_id": <?= $Grid->user_level_id->toClientList($Grid) ?>,
            "must_change_password": <?= $Grid->must_change_password->toClientList($Grid) ?>,
        })
        .build();
    window[form.id] = form;
    loadjs.done(form.id);
});
</script>
<?php } ?>
<main class="list">
<div id="ew-header-options">
<?php $Grid->HeaderOptions?->render("body") ?>
</div>
<div id="ew-list">
<?php if ($Grid->TotalRecords > 0 || $Grid->CurrentAction) { ?>
<div class="card ew-card ew-grid<?= $Grid->isAddOrEdit() ? " ew-grid-add-edit" : "" ?> <?= $Grid->TableGridClass ?>">
<div id="fusersgrid" class="ew-form ew-list-form">
<div id="gmp_users" class="card-body ew-grid-middle-panel <?= $Grid->TableContainerClass ?>" style="<?= $Grid->TableContainerStyle ?>">
<table id="tbl_usersgrid" class="<?= $Grid->TableClass ?>"><!-- .ew-table -->
<thead>
    <tr class="ew-table-header">
<?php
// Header row
$Grid->RowType = RowType::HEADER;

// Render list options
$Grid->renderListOptions();

// Render list options (header, left)
$Grid->ListOptions->render("header", "left");
?>
<?php if ($Grid->user_id->Visible) { // user_id ?>
        <th data-name="user_id" class="<?= $Grid->user_id->headerCellClass() ?>"><div id="elh_users_user_id" class="users_user_id"><?= $Grid->renderFieldHeader($Grid->user_id) ?></div></th>
<?php } ?>
<?php if ($Grid->_username->Visible) { // username ?>
        <th data-name="_username" class="<?= $Grid->_username->headerCellClass() ?>"><div id="elh_users__username" class="users__username"><?= $Grid->renderFieldHeader($Grid->_username) ?></div></th>
<?php } ?>
<?php if ($Grid->_email->Visible) { // email ?>
        <th data-name="_email" class="<?= $Grid->_email->headerCellClass() ?>"><div id="elh_users__email" class="users__email"><?= $Grid->renderFieldHeader($Grid->_email) ?></div></th>
<?php } ?>
<?php if ($Grid->first_name->Visible) { // first_name ?>
        <th data-name="first_name" class="<?= $Grid->first_name->headerCellClass() ?>"><div id="elh_users_first_name" class="users_first_name"><?= $Grid->renderFieldHeader($Grid->first_name) ?></div></th>
<?php } ?>
<?php if ($Grid->last_name->Visible) { // last_name ?>
        <th data-name="last_name" class="<?= $Grid->last_name->headerCellClass() ?>"><div id="elh_users_last_name" class="users_last_name"><?= $Grid->renderFieldHeader($Grid->last_name) ?></div></th>
<?php } ?>
<?php if ($Grid->suffix->Visible) { // suffix ?>
        <th data-name="suffix" class="<?= $Grid->suffix->headerCellClass() ?>"><div id="elh_users_suffix" class="users_suffix"><?= $Grid->renderFieldHeader($Grid->suffix) ?></div></th>
<?php } ?>
<?php if ($Grid->department_id->Visible) { // department_id ?>
        <th data-name="department_id" class="<?= $Grid->department_id->headerCellClass() ?>"><div id="elh_users_department_id" class="users_department_id"><?= $Grid->renderFieldHeader($Grid->department_id) ?></div></th>
<?php } ?>
<?php if ($Grid->is_active->Visible) { // is_active ?>
        <th data-name="is_active" class="<?= $Grid->is_active->headerCellClass() ?>"><div id="elh_users_is_active" class="users_is_active"><?= $Grid->renderFieldHeader($Grid->is_active) ?></div></th>
<?php } ?>
<?php if ($Grid->user_level_id->Visible) { // user_level_id ?>
        <th data-name="user_level_id" class="<?= $Grid->user_level_id->headerCellClass() ?>"><div id="elh_users_user_level_id" class="users_user_level_id"><?= $Grid->renderFieldHeader($Grid->user_level_id) ?></div></th>
<?php } ?>
<?php if ($Grid->position_id->Visible) { // position_id ?>
        <th data-name="position_id" class="<?= $Grid->position_id->headerCellClass() ?>"><div id="elh_users_position_id" class="users_position_id"><?= $Grid->renderFieldHeader($Grid->position_id) ?></div></th>
<?php } ?>
<?php if ($Grid->employee_id->Visible) { // employee_id ?>
        <th data-name="employee_id" class="<?= $Grid->employee_id->headerCellClass() ?>"><div id="elh_users_employee_id" class="users_employee_id"><?= $Grid->renderFieldHeader($Grid->employee_id) ?></div></th>
<?php } ?>
<?php if ($Grid->must_change_password->Visible) { // must_change_password ?>
        <th data-name="must_change_password" class="<?= $Grid->must_change_password->headerCellClass() ?>"><div id="elh_users_must_change_password" class="users_must_change_password"><?= $Grid->renderFieldHeader($Grid->must_change_password) ?></div></th>
<?php } ?>
<?php if ($Grid->rc_id->Visible) { // rc_id ?>
        <th data-name="rc_id" class="<?= $Grid->rc_id->headerCellClass() ?>"><div id="elh_users_rc_id" class="users_rc_id"><?= $Grid->renderFieldHeader($Grid->rc_id) ?></div></th>
<?php } ?>
<?php
// Render list options (header, right)
$Grid->ListOptions->render("header", "right");
?>
    </tr>
</thead>
<tbody data-page="<?= $Grid->getPageNumber() ?>">
<?php
$Grid->setupGrid();
$isInlineAddOrCopy = ($Grid->isCopy() || $Grid->isAdd());
while ($Grid->RecordCount < $Grid->StopRecord || $Grid->RowIndex === '$rowindex$' || $isInlineAddOrCopy && $Grid->RowIndex == 0) {
    if (
        $Grid->CurrentRow !== false &&
        $Grid->RowIndex !== '$rowindex$' &&
        (!$Grid->isGridAdd() || $Grid->CurrentMode == "copy") &&
        !($isInlineAddOrCopy && $Grid->RowIndex == 0)
    ) {
        $Grid->fetch();
    }
    $Grid->RecordCount++;
    if ($Grid->RecordCount >= $Grid->StartRecord) {
        $Grid->setupRow();

        // Skip 1) delete row / empty row for confirm page, 2) hidden row
        if (
            $Grid->RowAction != "delete" &&
            $Grid->RowAction != "insertdelete" &&
            !($Grid->RowAction == "insert" && $Grid->isConfirm() && $Grid->emptyRow()) &&
            $Grid->RowAction != "hide"
        ) {
?>
    <tr <?= $Grid->rowAttributes() ?>>
<?php
// Render list options (body, left)
$Grid->ListOptions->render("body", "left", $Grid->RowCount);
?>
    <?php if ($Grid->user_id->Visible) { // user_id ?>
        <td data-name="user_id"<?= $Grid->user_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_id" class="el_users_user_id"></span>
<input type="hidden" data-table="users" data-field="x_user_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_user_id" id="o<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_id" class="el_users_user_id">
<span<?= $Grid->user_id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Grid->user_id->getDisplayValue($Grid->user_id->EditValue))) ?>"></span>
<input type="hidden" data-table="users" data-field="x_user_id" data-hidden="1" name="x<?= $Grid->RowIndex ?>_user_id" id="x<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->CurrentValue) ?>">
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_id" class="el_users_user_id">
<span<?= $Grid->user_id->viewAttributes() ?>>
<?= $Grid->user_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_user_id" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_user_id" id="fusersgrid$x<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_user_id" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_user_id" id="fusersgrid$o<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } else { ?>
            <input type="hidden" data-table="users" data-field="x_user_id" data-hidden="1" name="x<?= $Grid->RowIndex ?>_user_id" id="x<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->CurrentValue) ?>">
    <?php } ?>
    <?php if ($Grid->_username->Visible) { // username ?>
        <td data-name="_username"<?= $Grid->_username->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users__username" class="el_users__username">
<input type="<?= $Grid->_username->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>__username" id="x<?= $Grid->RowIndex ?>__username" data-table="users" data-field="x__username" value="<?= $Grid->_username->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->_username->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->_username->formatPattern()) ?>"<?= $Grid->_username->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->_username->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="users" data-field="x__username" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>__username" id="o<?= $Grid->RowIndex ?>__username" value="<?= HtmlEncode($Grid->_username->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users__username" class="el_users__username">
<input type="<?= $Grid->_username->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>__username" id="x<?= $Grid->RowIndex ?>__username" data-table="users" data-field="x__username" value="<?= $Grid->_username->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->_username->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->_username->formatPattern()) ?>"<?= $Grid->_username->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->_username->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users__username" class="el_users__username">
<span<?= $Grid->_username->viewAttributes() ?>>
<?= $Grid->_username->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x__username" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>__username" id="fusersgrid$x<?= $Grid->RowIndex ?>__username" value="<?= HtmlEncode($Grid->_username->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x__username" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>__username" id="fusersgrid$o<?= $Grid->RowIndex ?>__username" value="<?= HtmlEncode($Grid->_username->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->_email->Visible) { // email ?>
        <td data-name="_email"<?= $Grid->_email->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users__email" class="el_users__email">
<input type="<?= $Grid->_email->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>__email" id="x<?= $Grid->RowIndex ?>__email" data-table="users" data-field="x__email" value="<?= $Grid->_email->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Grid->_email->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->_email->formatPattern()) ?>"<?= $Grid->_email->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->_email->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="users" data-field="x__email" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>__email" id="o<?= $Grid->RowIndex ?>__email" value="<?= HtmlEncode($Grid->_email->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users__email" class="el_users__email">
<input type="<?= $Grid->_email->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>__email" id="x<?= $Grid->RowIndex ?>__email" data-table="users" data-field="x__email" value="<?= $Grid->_email->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Grid->_email->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->_email->formatPattern()) ?>"<?= $Grid->_email->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->_email->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users__email" class="el_users__email">
<span<?= $Grid->_email->viewAttributes() ?>>
<?= $Grid->_email->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x__email" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>__email" id="fusersgrid$x<?= $Grid->RowIndex ?>__email" value="<?= HtmlEncode($Grid->_email->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x__email" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>__email" id="fusersgrid$o<?= $Grid->RowIndex ?>__email" value="<?= HtmlEncode($Grid->_email->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->first_name->Visible) { // first_name ?>
        <td data-name="first_name"<?= $Grid->first_name->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_first_name" class="el_users_first_name">
<input type="<?= $Grid->first_name->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_first_name" id="x<?= $Grid->RowIndex ?>_first_name" data-table="users" data-field="x_first_name" value="<?= $Grid->first_name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->first_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->first_name->formatPattern()) ?>"<?= $Grid->first_name->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->first_name->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="users" data-field="x_first_name" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_first_name" id="o<?= $Grid->RowIndex ?>_first_name" value="<?= HtmlEncode($Grid->first_name->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_first_name" class="el_users_first_name">
<input type="<?= $Grid->first_name->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_first_name" id="x<?= $Grid->RowIndex ?>_first_name" data-table="users" data-field="x_first_name" value="<?= $Grid->first_name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->first_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->first_name->formatPattern()) ?>"<?= $Grid->first_name->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->first_name->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_first_name" class="el_users_first_name">
<span<?= $Grid->first_name->viewAttributes() ?>>
<?= $Grid->first_name->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_first_name" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_first_name" id="fusersgrid$x<?= $Grid->RowIndex ?>_first_name" value="<?= HtmlEncode($Grid->first_name->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_first_name" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_first_name" id="fusersgrid$o<?= $Grid->RowIndex ?>_first_name" value="<?= HtmlEncode($Grid->first_name->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->last_name->Visible) { // last_name ?>
        <td data-name="last_name"<?= $Grid->last_name->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_last_name" class="el_users_last_name">
<input type="<?= $Grid->last_name->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_last_name" id="x<?= $Grid->RowIndex ?>_last_name" data-table="users" data-field="x_last_name" value="<?= $Grid->last_name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->last_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->last_name->formatPattern()) ?>"<?= $Grid->last_name->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->last_name->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="users" data-field="x_last_name" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_last_name" id="o<?= $Grid->RowIndex ?>_last_name" value="<?= HtmlEncode($Grid->last_name->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_last_name" class="el_users_last_name">
<input type="<?= $Grid->last_name->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_last_name" id="x<?= $Grid->RowIndex ?>_last_name" data-table="users" data-field="x_last_name" value="<?= $Grid->last_name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->last_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->last_name->formatPattern()) ?>"<?= $Grid->last_name->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->last_name->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_last_name" class="el_users_last_name">
<span<?= $Grid->last_name->viewAttributes() ?>>
<?= $Grid->last_name->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_last_name" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_last_name" id="fusersgrid$x<?= $Grid->RowIndex ?>_last_name" value="<?= HtmlEncode($Grid->last_name->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_last_name" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_last_name" id="fusersgrid$o<?= $Grid->RowIndex ?>_last_name" value="<?= HtmlEncode($Grid->last_name->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->suffix->Visible) { // suffix ?>
        <td data-name="suffix"<?= $Grid->suffix->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_suffix" class="el_users_suffix">
<input type="<?= $Grid->suffix->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_suffix" id="x<?= $Grid->RowIndex ?>_suffix" data-table="users" data-field="x_suffix" value="<?= $Grid->suffix->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Grid->suffix->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->suffix->formatPattern()) ?>"<?= $Grid->suffix->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->suffix->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="users" data-field="x_suffix" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_suffix" id="o<?= $Grid->RowIndex ?>_suffix" value="<?= HtmlEncode($Grid->suffix->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_suffix" class="el_users_suffix">
<input type="<?= $Grid->suffix->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_suffix" id="x<?= $Grid->RowIndex ?>_suffix" data-table="users" data-field="x_suffix" value="<?= $Grid->suffix->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Grid->suffix->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->suffix->formatPattern()) ?>"<?= $Grid->suffix->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->suffix->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_suffix" class="el_users_suffix">
<span<?= $Grid->suffix->viewAttributes() ?>>
<?= $Grid->suffix->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_suffix" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_suffix" id="fusersgrid$x<?= $Grid->RowIndex ?>_suffix" value="<?= HtmlEncode($Grid->suffix->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_suffix" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_suffix" id="fusersgrid$o<?= $Grid->RowIndex ?>_suffix" value="<?= HtmlEncode($Grid->suffix->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->department_id->Visible) { // department_id ?>
        <td data-name="department_id"<?= $Grid->department_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_department_id" class="el_users_department_id">
<?php
if (IsRTL()) {
    $Grid->department_id->EditAttrs["dir"] = "rtl";
}
?>
<span id="as_x<?= $Grid->RowIndex ?>_department_id" class="ew-auto-suggest">
    <input type="<?= $Grid->department_id->getInputTextType() ?>" class="form-control" name="sv_x<?= $Grid->RowIndex ?>_department_id" id="sv_x<?= $Grid->RowIndex ?>_department_id" value="<?= RemoveHtml($Grid->department_id->EditValue) ?>" autocomplete="off" size="30" placeholder="<?= HtmlEncode($Grid->department_id->getPlaceHolder()) ?>" data-placeholder="<?= HtmlEncode($Grid->department_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->department_id->formatPattern()) ?>"<?= $Grid->department_id->editAttributes() ?>>
</span>
<selection-list hidden class="form-control" data-table="users" data-field="x_department_id" data-input="sv_x<?= $Grid->RowIndex ?>_department_id" data-value-separator="<?= $Grid->department_id->displayValueSeparatorAttribute() ?>" name="x<?= $Grid->RowIndex ?>_department_id" id="x<?= $Grid->RowIndex ?>_department_id" value="<?= HtmlEncode($Grid->department_id->CurrentValue) ?>"></selection-list>
<div class="invalid-feedback"><?= $Grid->department_id->getErrorMessage() ?></div>
<script>
loadjs.ready("fusersgrid", function() {
    fusersgrid.createAutoSuggest(Object.assign({"id":"x<?= $Grid->RowIndex ?>_department_id","forceSelect":false}, { lookupAllDisplayFields: <?= $Grid->department_id->Lookup->LookupAllDisplayFields ? "true" : "false" ?> }, ew.vars.tables.users.fields.department_id.autoSuggestOptions));
});
</script>
<?= $Grid->department_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_department_id") ?>
</span>
<input type="hidden" data-table="users" data-field="x_department_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_department_id" id="o<?= $Grid->RowIndex ?>_department_id" value="<?= HtmlEncode($Grid->department_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_department_id" class="el_users_department_id">
<?php
if (IsRTL()) {
    $Grid->department_id->EditAttrs["dir"] = "rtl";
}
?>
<span id="as_x<?= $Grid->RowIndex ?>_department_id" class="ew-auto-suggest">
    <input type="<?= $Grid->department_id->getInputTextType() ?>" class="form-control" name="sv_x<?= $Grid->RowIndex ?>_department_id" id="sv_x<?= $Grid->RowIndex ?>_department_id" value="<?= RemoveHtml($Grid->department_id->EditValue) ?>" autocomplete="off" size="30" placeholder="<?= HtmlEncode($Grid->department_id->getPlaceHolder()) ?>" data-placeholder="<?= HtmlEncode($Grid->department_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->department_id->formatPattern()) ?>"<?= $Grid->department_id->editAttributes() ?>>
</span>
<selection-list hidden class="form-control" data-table="users" data-field="x_department_id" data-input="sv_x<?= $Grid->RowIndex ?>_department_id" data-value-separator="<?= $Grid->department_id->displayValueSeparatorAttribute() ?>" name="x<?= $Grid->RowIndex ?>_department_id" id="x<?= $Grid->RowIndex ?>_department_id" value="<?= HtmlEncode($Grid->department_id->CurrentValue) ?>"></selection-list>
<div class="invalid-feedback"><?= $Grid->department_id->getErrorMessage() ?></div>
<script>
loadjs.ready("fusersgrid", function() {
    fusersgrid.createAutoSuggest(Object.assign({"id":"x<?= $Grid->RowIndex ?>_department_id","forceSelect":false}, { lookupAllDisplayFields: <?= $Grid->department_id->Lookup->LookupAllDisplayFields ? "true" : "false" ?> }, ew.vars.tables.users.fields.department_id.autoSuggestOptions));
});
</script>
<?= $Grid->department_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_department_id") ?>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_department_id" class="el_users_department_id">
<span<?= $Grid->department_id->viewAttributes() ?>>
<?= $Grid->department_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_department_id" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_department_id" id="fusersgrid$x<?= $Grid->RowIndex ?>_department_id" value="<?= HtmlEncode($Grid->department_id->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_department_id" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_department_id" id="fusersgrid$o<?= $Grid->RowIndex ?>_department_id" value="<?= HtmlEncode($Grid->department_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->is_active->Visible) { // is_active ?>
        <td data-name="is_active"<?= $Grid->is_active->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_is_active" class="el_users_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Grid->is_active->isInvalidClass() ?>" data-table="users" data-field="x_is_active" data-boolean name="x<?= $Grid->RowIndex ?>_is_active" id="x<?= $Grid->RowIndex ?>_is_active" value="1"<?= ConvertToBool($Grid->is_active->CurrentValue) ? " checked" : "" ?><?= $Grid->is_active->editAttributes() ?>>
    <div class="invalid-feedback"><?= $Grid->is_active->getErrorMessage() ?></div>
</div>
</span>
<input type="hidden" data-table="users" data-field="x_is_active" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_is_active" id="o<?= $Grid->RowIndex ?>_is_active" value="<?= HtmlEncode($Grid->is_active->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_is_active" class="el_users_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Grid->is_active->isInvalidClass() ?>" data-table="users" data-field="x_is_active" data-boolean name="x<?= $Grid->RowIndex ?>_is_active" id="x<?= $Grid->RowIndex ?>_is_active" value="1"<?= ConvertToBool($Grid->is_active->CurrentValue) ? " checked" : "" ?><?= $Grid->is_active->editAttributes() ?>>
    <div class="invalid-feedback"><?= $Grid->is_active->getErrorMessage() ?></div>
</div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_is_active" class="el_users_is_active">
<span<?= $Grid->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Grid->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_is_active" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_is_active" id="fusersgrid$x<?= $Grid->RowIndex ?>_is_active" value="<?= HtmlEncode($Grid->is_active->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_is_active" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_is_active" id="fusersgrid$o<?= $Grid->RowIndex ?>_is_active" value="<?= HtmlEncode($Grid->is_active->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->user_level_id->Visible) { // user_level_id ?>
        <td data-name="user_level_id"<?= $Grid->user_level_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<?php if ($Grid->user_level_id->getSessionValue() != "") { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_level_id" class="el_users_user_level_id">
<span<?= $Grid->user_level_id->viewAttributes() ?>>
<span class="form-control-plaintext"><?= $Grid->user_level_id->getDisplayValue($Grid->user_level_id->ViewValue) ?></span></span>
<input type="hidden" id="x<?= $Grid->RowIndex ?>_user_level_id" name="x<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->CurrentValue) ?>" data-hidden="1">
</span>
<?php } elseif (!$Security->isAdmin() && $Security->isLoggedIn()) { // Non system admin ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_level_id" class="el_users_user_level_id">
<span class="form-control-plaintext"><?= $Grid->user_level_id->getDisplayValue($Grid->user_level_id->EditValue) ?></span>
</span>
<?php } else { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_level_id" class="el_users_user_level_id">
    <select
        id="x<?= $Grid->RowIndex ?>_user_level_id[]"
        name="x<?= $Grid->RowIndex ?>_user_level_id[]"
        class="form-select ew-select<?= $Grid->user_level_id->isInvalidClass() ?>"
        <?php if (!$Grid->user_level_id->IsNativeSelect) { ?>
        data-select2-id="fusersgrid_x<?= $Grid->RowIndex ?>_user_level_id[]"
        <?php } ?>
        data-table="users"
        data-field="x_user_level_id"
        multiple
        size="1"
        data-value-separator="<?= $Grid->user_level_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Grid->user_level_id->getPlaceHolder()) ?>"
        <?= $Grid->user_level_id->editAttributes() ?>>
        <?= $Grid->user_level_id->selectOptionListHtml("x{$Grid->RowIndex}_user_level_id[]") ?>
    </select>
    <div class="invalid-feedback"><?= $Grid->user_level_id->getErrorMessage() ?></div>
<?= $Grid->user_level_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_user_level_id") ?>
<?php if (!$Grid->user_level_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fusersgrid", function() {
    var options = { name: "x<?= $Grid->RowIndex ?>_user_level_id[]", selectId: "fusersgrid_x<?= $Grid->RowIndex ?>_user_level_id[]" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.multiple = true;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fusersgrid.lists.user_level_id?.lookupOptions.length) {
        options.data = { id: "x<?= $Grid->RowIndex ?>_user_level_id[]", form: "fusersgrid" };
    } else {
        options.ajax = { id: "x<?= $Grid->RowIndex ?>_user_level_id[]", form: "fusersgrid", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.users.fields.user_level_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
<input type="hidden" data-table="users" data-field="x_user_level_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_user_level_id[]" id="o<?= $Grid->RowIndex ?>_user_level_id[]" value="<?= HtmlEncode($Grid->user_level_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<?php if ($Grid->user_level_id->getSessionValue() != "") { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_level_id" class="el_users_user_level_id">
<span<?= $Grid->user_level_id->viewAttributes() ?>>
<span class="form-control-plaintext"><?= $Grid->user_level_id->getDisplayValue($Grid->user_level_id->ViewValue) ?></span></span>
<input type="hidden" id="x<?= $Grid->RowIndex ?>_user_level_id" name="x<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->CurrentValue) ?>" data-hidden="1">
</span>
<?php } elseif (!$Security->isAdmin() && $Security->isLoggedIn()) { // Non system admin ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_level_id" class="el_users_user_level_id">
<span class="form-control-plaintext"><?= $Grid->user_level_id->getDisplayValue($Grid->user_level_id->EditValue) ?></span>
</span>
<?php } else { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_level_id" class="el_users_user_level_id">
    <select
        id="x<?= $Grid->RowIndex ?>_user_level_id[]"
        name="x<?= $Grid->RowIndex ?>_user_level_id[]"
        class="form-select ew-select<?= $Grid->user_level_id->isInvalidClass() ?>"
        <?php if (!$Grid->user_level_id->IsNativeSelect) { ?>
        data-select2-id="fusersgrid_x<?= $Grid->RowIndex ?>_user_level_id[]"
        <?php } ?>
        data-table="users"
        data-field="x_user_level_id"
        multiple
        size="1"
        data-value-separator="<?= $Grid->user_level_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Grid->user_level_id->getPlaceHolder()) ?>"
        <?= $Grid->user_level_id->editAttributes() ?>>
        <?= $Grid->user_level_id->selectOptionListHtml("x{$Grid->RowIndex}_user_level_id[]") ?>
    </select>
    <div class="invalid-feedback"><?= $Grid->user_level_id->getErrorMessage() ?></div>
<?= $Grid->user_level_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_user_level_id") ?>
<?php if (!$Grid->user_level_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fusersgrid", function() {
    var options = { name: "x<?= $Grid->RowIndex ?>_user_level_id[]", selectId: "fusersgrid_x<?= $Grid->RowIndex ?>_user_level_id[]" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.multiple = true;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fusersgrid.lists.user_level_id?.lookupOptions.length) {
        options.data = { id: "x<?= $Grid->RowIndex ?>_user_level_id[]", form: "fusersgrid" };
    } else {
        options.ajax = { id: "x<?= $Grid->RowIndex ?>_user_level_id[]", form: "fusersgrid", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.users.fields.user_level_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_user_level_id" class="el_users_user_level_id">
<span<?= $Grid->user_level_id->viewAttributes() ?>>
<?= $Grid->user_level_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_user_level_id" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_user_level_id" id="fusersgrid$x<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_user_level_id" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_user_level_id[]" id="fusersgrid$o<?= $Grid->RowIndex ?>_user_level_id[]" value="<?= HtmlEncode($Grid->user_level_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->position_id->Visible) { // position_id ?>
        <td data-name="position_id"<?= $Grid->position_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_position_id" class="el_users_position_id">
<input type="<?= $Grid->position_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_position_id" id="x<?= $Grid->RowIndex ?>_position_id" data-table="users" data-field="x_position_id" value="<?= $Grid->position_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Grid->position_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->position_id->formatPattern()) ?>"<?= $Grid->position_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->position_id->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="users" data-field="x_position_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_position_id" id="o<?= $Grid->RowIndex ?>_position_id" value="<?= HtmlEncode($Grid->position_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_position_id" class="el_users_position_id">
<input type="<?= $Grid->position_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_position_id" id="x<?= $Grid->RowIndex ?>_position_id" data-table="users" data-field="x_position_id" value="<?= $Grid->position_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Grid->position_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->position_id->formatPattern()) ?>"<?= $Grid->position_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->position_id->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_position_id" class="el_users_position_id">
<span<?= $Grid->position_id->viewAttributes() ?>>
<?= $Grid->position_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_position_id" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_position_id" id="fusersgrid$x<?= $Grid->RowIndex ?>_position_id" value="<?= HtmlEncode($Grid->position_id->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_position_id" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_position_id" id="fusersgrid$o<?= $Grid->RowIndex ?>_position_id" value="<?= HtmlEncode($Grid->position_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->employee_id->Visible) { // employee_id ?>
        <td data-name="employee_id"<?= $Grid->employee_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_employee_id" class="el_users_employee_id">
<input type="<?= $Grid->employee_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_employee_id" id="x<?= $Grid->RowIndex ?>_employee_id" data-table="users" data-field="x_employee_id" value="<?= $Grid->employee_id->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->employee_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->employee_id->formatPattern()) ?>"<?= $Grid->employee_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->employee_id->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="users" data-field="x_employee_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_employee_id" id="o<?= $Grid->RowIndex ?>_employee_id" value="<?= HtmlEncode($Grid->employee_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_employee_id" class="el_users_employee_id">
<input type="<?= $Grid->employee_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_employee_id" id="x<?= $Grid->RowIndex ?>_employee_id" data-table="users" data-field="x_employee_id" value="<?= $Grid->employee_id->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->employee_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->employee_id->formatPattern()) ?>"<?= $Grid->employee_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->employee_id->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_employee_id" class="el_users_employee_id">
<span<?= $Grid->employee_id->viewAttributes() ?>>
<?= $Grid->employee_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_employee_id" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_employee_id" id="fusersgrid$x<?= $Grid->RowIndex ?>_employee_id" value="<?= HtmlEncode($Grid->employee_id->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_employee_id" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_employee_id" id="fusersgrid$o<?= $Grid->RowIndex ?>_employee_id" value="<?= HtmlEncode($Grid->employee_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->must_change_password->Visible) { // must_change_password ?>
        <td data-name="must_change_password"<?= $Grid->must_change_password->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_must_change_password" class="el_users_must_change_password">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Grid->must_change_password->isInvalidClass() ?>" data-table="users" data-field="x_must_change_password" data-boolean name="x<?= $Grid->RowIndex ?>_must_change_password" id="x<?= $Grid->RowIndex ?>_must_change_password" value="1"<?= ConvertToBool($Grid->must_change_password->CurrentValue) ? " checked" : "" ?><?= $Grid->must_change_password->editAttributes() ?>>
    <div class="invalid-feedback"><?= $Grid->must_change_password->getErrorMessage() ?></div>
</div>
</span>
<input type="hidden" data-table="users" data-field="x_must_change_password" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_must_change_password" id="o<?= $Grid->RowIndex ?>_must_change_password" value="<?= HtmlEncode($Grid->must_change_password->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_must_change_password" class="el_users_must_change_password">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Grid->must_change_password->isInvalidClass() ?>" data-table="users" data-field="x_must_change_password" data-boolean name="x<?= $Grid->RowIndex ?>_must_change_password" id="x<?= $Grid->RowIndex ?>_must_change_password" value="1"<?= ConvertToBool($Grid->must_change_password->CurrentValue) ? " checked" : "" ?><?= $Grid->must_change_password->editAttributes() ?>>
    <div class="invalid-feedback"><?= $Grid->must_change_password->getErrorMessage() ?></div>
</div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_must_change_password" class="el_users_must_change_password">
<span<?= $Grid->must_change_password->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Grid->must_change_password->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_must_change_password" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_must_change_password" id="fusersgrid$x<?= $Grid->RowIndex ?>_must_change_password" value="<?= HtmlEncode($Grid->must_change_password->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_must_change_password" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_must_change_password" id="fusersgrid$o<?= $Grid->RowIndex ?>_must_change_password" value="<?= HtmlEncode($Grid->must_change_password->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->rc_id->Visible) { // rc_id ?>
        <td data-name="rc_id"<?= $Grid->rc_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_rc_id" class="el_users_rc_id">
<input type="<?= $Grid->rc_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_rc_id" id="x<?= $Grid->RowIndex ?>_rc_id" data-table="users" data-field="x_rc_id" value="<?= $Grid->rc_id->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Grid->rc_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->rc_id->formatPattern()) ?>"<?= $Grid->rc_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->rc_id->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="users" data-field="x_rc_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_rc_id" id="o<?= $Grid->RowIndex ?>_rc_id" value="<?= HtmlEncode($Grid->rc_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_rc_id" class="el_users_rc_id">
<input type="<?= $Grid->rc_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_rc_id" id="x<?= $Grid->RowIndex ?>_rc_id" data-table="users" data-field="x_rc_id" value="<?= $Grid->rc_id->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Grid->rc_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->rc_id->formatPattern()) ?>"<?= $Grid->rc_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->rc_id->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_users_rc_id" class="el_users_rc_id">
<span<?= $Grid->rc_id->viewAttributes() ?>>
<?= $Grid->rc_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="users" data-field="x_rc_id" data-hidden="1" name="fusersgrid$x<?= $Grid->RowIndex ?>_rc_id" id="fusersgrid$x<?= $Grid->RowIndex ?>_rc_id" value="<?= HtmlEncode($Grid->rc_id->FormValue) ?>">
<input type="hidden" data-table="users" data-field="x_rc_id" data-hidden="1" data-old name="fusersgrid$o<?= $Grid->RowIndex ?>_rc_id" id="fusersgrid$o<?= $Grid->RowIndex ?>_rc_id" value="<?= HtmlEncode($Grid->rc_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
<?php
// Render list options (body, right)
$Grid->ListOptions->render("body", "right", $Grid->RowCount);
?>
    </tr>
<?php if ($Grid->RowType == RowType::ADD || $Grid->RowType == RowType::EDIT) { ?>
<script data-rowindex="<?= $Grid->RowIndex ?>">
loadjs.ready(["fusersgrid","load"], () => fusersgrid.updateLists(<?= $Grid->RowIndex ?><?= $Grid->isAdd() || $Grid->isEdit() || $Grid->isCopy() || $Grid->RowIndex === '$rowindex$' ? ", true" : "" ?>));
</script>
<?php } ?>
<?php
    }
    } // End delete row checking

    // Reset for template row
    if ($Grid->RowIndex === '$rowindex$') {
        $Grid->RowIndex = 0;
    }
    // Reset inline add/copy row
    if (($Grid->isCopy() || $Grid->isAdd()) && $Grid->RowIndex == 0) {
        $Grid->RowIndex = 1;
    }
}
?>
</tbody>
</table><!-- /.ew-table -->
<?php if ($Grid->CurrentMode == "add" || $Grid->CurrentMode == "copy") { ?>
<input type="hidden" name="<?= $Grid->FormKeyCountName ?>" id="<?= $Grid->FormKeyCountName ?>" value="<?= $Grid->KeyCount ?>">
<?= $Grid->MultiSelectKey ?>
<?php } ?>
<?php if ($Grid->CurrentMode == "edit") { ?>
<input type="hidden" name="<?= $Grid->FormKeyCountName ?>" id="<?= $Grid->FormKeyCountName ?>" value="<?= $Grid->KeyCount ?>">
<?= $Grid->MultiSelectKey ?>
<?php } ?>
</div><!-- /.ew-grid-middle-panel -->
<?php if ($Grid->CurrentMode == "") { ?>
<input type="hidden" name="action" id="action" value="">
<?php } ?>
<input type="hidden" name="detailpage" value="fusersgrid">
</div><!-- /.ew-list-form -->
<?php
// Close result set
$Grid->Recordset?->free();
?>
<?php if ($Grid->ShowOtherOptions) { ?>
<div class="card-footer ew-grid-lower-panel">
<?php $Grid->OtherOptions->render("body", "bottom") ?>
</div>
<?php } ?>
</div><!-- /.ew-grid -->
<?php } else { ?>
<div class="ew-list-other-options">
<?php $Grid->OtherOptions->render("body") ?>
</div>
<?php } ?>
</div>
<div id="ew-footer-options">
<?php $Grid->FooterOptions?->render("body") ?>
</div>
</main>
<?php if (!$Grid->isExport()) { ?>
<script>
// Field event handlers
loadjs.ready("head", function() {
    ew.addEventHandlers("users");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
