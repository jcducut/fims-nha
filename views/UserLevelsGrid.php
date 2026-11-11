<?php

namespace PHPMaker2024\FIMS;

// Set up and run Grid object
$Grid = Container("UserLevelsGrid");
$Grid->run();
?>
<?php if (!$Grid->isExport()) { ?>
<script>
var f_user_levelsgrid;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let currentTable = <?= JsonEncode($Grid->toClientVar()) ?>;
    ew.deepAssign(ew.vars, { tables: { _user_levels: currentTable } });
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("f_user_levelsgrid")
        .setPageId("grid")
        .setFormKeyCountName("<?= $Grid->FormKeyCountName ?>")

        // Add fields
        .setFields([
            ["user_level_id", [fields.user_level_id.visible && fields.user_level_id.required ? ew.Validators.required(fields.user_level_id.caption) : null, ew.Validators.userLevelId, ew.Validators.integer], fields.user_level_id.isInvalid],
            ["system_id", [fields.system_id.visible && fields.system_id.required ? ew.Validators.required(fields.system_id.caption) : null], fields.system_id.isInvalid],
            ["name", [fields.name.visible && fields.name.required ? ew.Validators.required(fields.name.caption) : null, ew.Validators.userLevelName('user_level_id')], fields.name.isInvalid]
        ])

        // Check empty row
        .setEmptyRow(
            function (rowIndex) {
                let fobj = this.getForm(),
                    fields = [["user_level_id",false],["system_id",false],["name",false]];
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
            "system_id": <?= $Grid->system_id->toClientList($Grid) ?>,
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
<div id="f_user_levelsgrid" class="ew-form ew-list-form">
<div id="gmp__user_levels" class="card-body ew-grid-middle-panel <?= $Grid->TableContainerClass ?>" style="<?= $Grid->TableContainerStyle ?>">
<table id="tbl__user_levelsgrid" class="<?= $Grid->TableClass ?>"><!-- .ew-table -->
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
<?php if ($Grid->user_level_id->Visible) { // user_level_id ?>
        <th data-name="user_level_id" class="<?= $Grid->user_level_id->headerCellClass() ?>"><div id="elh__user_levels_user_level_id" class="_user_levels_user_level_id"><?= $Grid->renderFieldHeader($Grid->user_level_id) ?></div></th>
<?php } ?>
<?php if ($Grid->system_id->Visible) { // system_id ?>
        <th data-name="system_id" class="<?= $Grid->system_id->headerCellClass() ?>"><div id="elh__user_levels_system_id" class="_user_levels_system_id"><?= $Grid->renderFieldHeader($Grid->system_id) ?></div></th>
<?php } ?>
<?php if ($Grid->name->Visible) { // name ?>
        <th data-name="name" class="<?= $Grid->name->headerCellClass() ?>"><div id="elh__user_levels_name" class="_user_levels_name"><?= $Grid->renderFieldHeader($Grid->name) ?></div></th>
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
    <?php if ($Grid->user_level_id->Visible) { // user_level_id ?>
        <td data-name="user_level_id"<?= $Grid->user_level_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>__user_levels_user_level_id" class="el__user_levels_user_level_id">
<input type="<?= $Grid->user_level_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_user_level_id" id="x<?= $Grid->RowIndex ?>_user_level_id" data-table="_user_levels" data-field="x_user_level_id" value="<?= $Grid->user_level_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Grid->user_level_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->user_level_id->formatPattern()) ?>"<?= $Grid->user_level_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->user_level_id->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="_user_levels" data-field="x_user_level_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_user_level_id" id="o<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>__user_levels_user_level_id" class="el__user_levels_user_level_id">
<input type="<?= $Grid->user_level_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_user_level_id" id="x<?= $Grid->RowIndex ?>_user_level_id" data-table="_user_levels" data-field="x_user_level_id" value="<?= $Grid->user_level_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Grid->user_level_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->user_level_id->formatPattern()) ?>"<?= $Grid->user_level_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->user_level_id->getErrorMessage() ?></div>
<input type="hidden" data-table="_user_levels" data-field="x_user_level_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_user_level_id" id="o<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->OldValue ?? $Grid->user_level_id->CurrentValue) ?>">
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>__user_levels_user_level_id" class="el__user_levels_user_level_id">
<span<?= $Grid->user_level_id->viewAttributes() ?>>
<?= $Grid->user_level_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="_user_levels" data-field="x_user_level_id" data-hidden="1" name="f_user_levelsgrid$x<?= $Grid->RowIndex ?>_user_level_id" id="f_user_levelsgrid$x<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->FormValue) ?>">
<input type="hidden" data-table="_user_levels" data-field="x_user_level_id" data-hidden="1" data-old name="f_user_levelsgrid$o<?= $Grid->RowIndex ?>_user_level_id" id="f_user_levelsgrid$o<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } else { ?>
            <input type="hidden" data-table="_user_levels" data-field="x_user_level_id" data-hidden="1" name="x<?= $Grid->RowIndex ?>_user_level_id" id="x<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->CurrentValue) ?>">
    <?php } ?>
    <?php if ($Grid->system_id->Visible) { // system_id ?>
        <td data-name="system_id"<?= $Grid->system_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>__user_levels_system_id" class="el__user_levels_system_id">
    <select
        id="x<?= $Grid->RowIndex ?>_system_id"
        name="x<?= $Grid->RowIndex ?>_system_id"
        class="form-select ew-select<?= $Grid->system_id->isInvalidClass() ?>"
        <?php if (!$Grid->system_id->IsNativeSelect) { ?>
        data-select2-id="f_user_levelsgrid_x<?= $Grid->RowIndex ?>_system_id"
        <?php } ?>
        data-table="_user_levels"
        data-field="x_system_id"
        data-value-separator="<?= $Grid->system_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Grid->system_id->getPlaceHolder()) ?>"
        <?= $Grid->system_id->editAttributes() ?>>
        <?= $Grid->system_id->selectOptionListHtml("x{$Grid->RowIndex}_system_id") ?>
    </select>
    <div class="invalid-feedback"><?= $Grid->system_id->getErrorMessage() ?></div>
<?= $Grid->system_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_system_id") ?>
<?php if (!$Grid->system_id->IsNativeSelect) { ?>
<script>
loadjs.ready("f_user_levelsgrid", function() {
    var options = { name: "x<?= $Grid->RowIndex ?>_system_id", selectId: "f_user_levelsgrid_x<?= $Grid->RowIndex ?>_system_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (f_user_levelsgrid.lists.system_id?.lookupOptions.length) {
        options.data = { id: "x<?= $Grid->RowIndex ?>_system_id", form: "f_user_levelsgrid" };
    } else {
        options.ajax = { id: "x<?= $Grid->RowIndex ?>_system_id", form: "f_user_levelsgrid", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables._user_levels.fields.system_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<input type="hidden" data-table="_user_levels" data-field="x_system_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_system_id" id="o<?= $Grid->RowIndex ?>_system_id" value="<?= HtmlEncode($Grid->system_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>__user_levels_system_id" class="el__user_levels_system_id">
    <select
        id="x<?= $Grid->RowIndex ?>_system_id"
        name="x<?= $Grid->RowIndex ?>_system_id"
        class="form-select ew-select<?= $Grid->system_id->isInvalidClass() ?>"
        <?php if (!$Grid->system_id->IsNativeSelect) { ?>
        data-select2-id="f_user_levelsgrid_x<?= $Grid->RowIndex ?>_system_id"
        <?php } ?>
        data-table="_user_levels"
        data-field="x_system_id"
        data-value-separator="<?= $Grid->system_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Grid->system_id->getPlaceHolder()) ?>"
        <?= $Grid->system_id->editAttributes() ?>>
        <?= $Grid->system_id->selectOptionListHtml("x{$Grid->RowIndex}_system_id") ?>
    </select>
    <div class="invalid-feedback"><?= $Grid->system_id->getErrorMessage() ?></div>
<?= $Grid->system_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_system_id") ?>
<?php if (!$Grid->system_id->IsNativeSelect) { ?>
<script>
loadjs.ready("f_user_levelsgrid", function() {
    var options = { name: "x<?= $Grid->RowIndex ?>_system_id", selectId: "f_user_levelsgrid_x<?= $Grid->RowIndex ?>_system_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (f_user_levelsgrid.lists.system_id?.lookupOptions.length) {
        options.data = { id: "x<?= $Grid->RowIndex ?>_system_id", form: "f_user_levelsgrid" };
    } else {
        options.ajax = { id: "x<?= $Grid->RowIndex ?>_system_id", form: "f_user_levelsgrid", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables._user_levels.fields.system_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>__user_levels_system_id" class="el__user_levels_system_id">
<span<?= $Grid->system_id->viewAttributes() ?>>
<?= $Grid->system_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="_user_levels" data-field="x_system_id" data-hidden="1" name="f_user_levelsgrid$x<?= $Grid->RowIndex ?>_system_id" id="f_user_levelsgrid$x<?= $Grid->RowIndex ?>_system_id" value="<?= HtmlEncode($Grid->system_id->FormValue) ?>">
<input type="hidden" data-table="_user_levels" data-field="x_system_id" data-hidden="1" data-old name="f_user_levelsgrid$o<?= $Grid->RowIndex ?>_system_id" id="f_user_levelsgrid$o<?= $Grid->RowIndex ?>_system_id" value="<?= HtmlEncode($Grid->system_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->name->Visible) { // name ?>
        <td data-name="name"<?= $Grid->name->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>__user_levels_name" class="el__user_levels_name">
<input type="<?= $Grid->name->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_name" id="x<?= $Grid->RowIndex ?>_name" data-table="_user_levels" data-field="x_name" value="<?= $Grid->name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->name->formatPattern()) ?>"<?= $Grid->name->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->name->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="_user_levels" data-field="x_name" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_name" id="o<?= $Grid->RowIndex ?>_name" value="<?= HtmlEncode($Grid->name->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>__user_levels_name" class="el__user_levels_name">
<input type="<?= $Grid->name->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_name" id="x<?= $Grid->RowIndex ?>_name" data-table="_user_levels" data-field="x_name" value="<?= $Grid->name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Grid->name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->name->formatPattern()) ?>"<?= $Grid->name->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->name->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>__user_levels_name" class="el__user_levels_name">
<span<?= $Grid->name->viewAttributes() ?>>
<?= $Grid->name->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="_user_levels" data-field="x_name" data-hidden="1" name="f_user_levelsgrid$x<?= $Grid->RowIndex ?>_name" id="f_user_levelsgrid$x<?= $Grid->RowIndex ?>_name" value="<?= HtmlEncode($Grid->name->FormValue) ?>">
<input type="hidden" data-table="_user_levels" data-field="x_name" data-hidden="1" data-old name="f_user_levelsgrid$o<?= $Grid->RowIndex ?>_name" id="f_user_levelsgrid$o<?= $Grid->RowIndex ?>_name" value="<?= HtmlEncode($Grid->name->OldValue) ?>">
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
loadjs.ready(["f_user_levelsgrid","load"], () => f_user_levelsgrid.updateLists(<?= $Grid->RowIndex ?><?= $Grid->isAdd() || $Grid->isEdit() || $Grid->isCopy() || $Grid->RowIndex === '$rowindex$' ? ", true" : "" ?>));
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
<input type="hidden" name="detailpage" value="f_user_levelsgrid">
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
    ew.addEventHandlers("_user_levels");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
