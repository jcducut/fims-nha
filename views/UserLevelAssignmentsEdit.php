<?php

namespace PHPMaker2024\FIMS;

// Page object
$UserLevelAssignmentsEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fuser_level_assignmentsedit" id="fuser_level_assignmentsedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { user_level_assignments: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fuser_level_assignmentsedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fuser_level_assignmentsedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["assignment_id", [fields.assignment_id.visible && fields.assignment_id.required ? ew.Validators.required(fields.assignment_id.caption) : null], fields.assignment_id.isInvalid],
            ["system_id", [fields.system_id.visible && fields.system_id.required ? ew.Validators.required(fields.system_id.caption) : null], fields.system_id.isInvalid],
            ["user_level_id", [fields.user_level_id.visible && fields.user_level_id.required ? ew.Validators.required(fields.user_level_id.caption) : null], fields.user_level_id.isInvalid],
            ["user_id", [fields.user_id.visible && fields.user_id.required ? ew.Validators.required(fields.user_id.caption) : null], fields.user_id.isInvalid],
            ["assigned_by", [fields.assigned_by.visible && fields.assigned_by.required ? ew.Validators.required(fields.assigned_by.caption) : null], fields.assigned_by.isInvalid],
            ["created_at", [fields.created_at.visible && fields.created_at.required ? ew.Validators.required(fields.created_at.caption) : null], fields.created_at.isInvalid]
        ])

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
            "system_id": <?= $Page->system_id->toClientList($Page) ?>,
            "user_level_id": <?= $Page->user_level_id->toClientList($Page) ?>,
            "user_id": <?= $Page->user_id->toClientList($Page) ?>,
        })
        .build();
    window[form.id] = form;
    currentForm = form;
    loadjs.done(form.id);
});
</script>
<script>
loadjs.ready("head", function () {
    // Write your table-specific client script here, no need to add script tags.
});
</script>
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="user_level_assignments">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<?php if ($Page->getCurrentMasterTable() == "_user_levels") { ?>
<input type="hidden" name="<?= Config("TABLE_SHOW_MASTER") ?>" value="_user_levels">
<input type="hidden" name="fk_user_level_id" value="<?= HtmlEncode($Page->user_level_id->getSessionValue()) ?>">
<?php } ?>
<?php if ($Page->getCurrentMasterTable() == "users") { ?>
<input type="hidden" name="<?= Config("TABLE_SHOW_MASTER") ?>" value="users">
<input type="hidden" name="fk_user_id" value="<?= HtmlEncode($Page->user_id->getSessionValue()) ?>">
<?php } ?>
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->assignment_id->Visible) { // assignment_id ?>
    <div id="r_assignment_id"<?= $Page->assignment_id->rowAttributes() ?>>
        <label id="elh_user_level_assignments_assignment_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->assignment_id->caption() ?><?= $Page->assignment_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->assignment_id->cellAttributes() ?>>
<span id="el_user_level_assignments_assignment_id">
<span<?= $Page->assignment_id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Page->assignment_id->getDisplayValue($Page->assignment_id->EditValue))) ?>"></span>
<input type="hidden" data-table="user_level_assignments" data-field="x_assignment_id" data-hidden="1" name="x_assignment_id" id="x_assignment_id" value="<?= HtmlEncode($Page->assignment_id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->system_id->Visible) { // system_id ?>
    <div id="r_system_id"<?= $Page->system_id->rowAttributes() ?>>
        <label id="elh_user_level_assignments_system_id" for="x_system_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->system_id->caption() ?><?= $Page->system_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->system_id->cellAttributes() ?>>
<span id="el_user_level_assignments_system_id">
    <select
        id="x_system_id"
        name="x_system_id"
        class="form-select ew-select<?= $Page->system_id->isInvalidClass() ?>"
        <?php if (!$Page->system_id->IsNativeSelect) { ?>
        data-select2-id="fuser_level_assignmentsedit_x_system_id"
        <?php } ?>
        data-table="user_level_assignments"
        data-field="x_system_id"
        data-value-separator="<?= $Page->system_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Page->system_id->getPlaceHolder()) ?>"
        data-ew-action="update-options"
        <?= $Page->system_id->editAttributes() ?>>
        <?= $Page->system_id->selectOptionListHtml("x_system_id") ?>
    </select>
    <?= $Page->system_id->getCustomMessage() ?>
    <div class="invalid-feedback"><?= $Page->system_id->getErrorMessage() ?></div>
<?= $Page->system_id->Lookup->getParamTag($Page, "p_x_system_id") ?>
<?php if (!$Page->system_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fuser_level_assignmentsedit", function() {
    var options = { name: "x_system_id", selectId: "fuser_level_assignmentsedit_x_system_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fuser_level_assignmentsedit.lists.system_id?.lookupOptions.length) {
        options.data = { id: "x_system_id", form: "fuser_level_assignmentsedit" };
    } else {
        options.ajax = { id: "x_system_id", form: "fuser_level_assignmentsedit", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.user_level_assignments.fields.system_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->user_level_id->Visible) { // user_level_id ?>
    <div id="r_user_level_id"<?= $Page->user_level_id->rowAttributes() ?>>
        <label id="elh_user_level_assignments_user_level_id" for="x_user_level_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_level_id->caption() ?><?= $Page->user_level_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_level_id->cellAttributes() ?>>
<?php if ($Page->user_level_id->getSessionValue() != "") { ?>
<span id="el_user_level_assignments_user_level_id">
<span<?= $Page->user_level_id->viewAttributes() ?>>
<span class="form-control-plaintext"><?= $Page->user_level_id->getDisplayValue($Page->user_level_id->ViewValue) ?></span></span>
<input type="hidden" id="x_user_level_id" name="x_user_level_id" value="<?= HtmlEncode($Page->user_level_id->CurrentValue) ?>" data-hidden="1">
</span>
<?php } else { ?>
<span id="el_user_level_assignments_user_level_id">
    <select
        id="x_user_level_id"
        name="x_user_level_id"
        class="form-select ew-select<?= $Page->user_level_id->isInvalidClass() ?>"
        <?php if (!$Page->user_level_id->IsNativeSelect) { ?>
        data-select2-id="fuser_level_assignmentsedit_x_user_level_id"
        <?php } ?>
        data-table="user_level_assignments"
        data-field="x_user_level_id"
        data-value-separator="<?= $Page->user_level_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Page->user_level_id->getPlaceHolder()) ?>"
        <?= $Page->user_level_id->editAttributes() ?>>
        <?= $Page->user_level_id->selectOptionListHtml("x_user_level_id") ?>
    </select>
    <?= $Page->user_level_id->getCustomMessage() ?>
    <div class="invalid-feedback"><?= $Page->user_level_id->getErrorMessage() ?></div>
<?= $Page->user_level_id->Lookup->getParamTag($Page, "p_x_user_level_id") ?>
<?php if (!$Page->user_level_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fuser_level_assignmentsedit", function() {
    var options = { name: "x_user_level_id", selectId: "fuser_level_assignmentsedit_x_user_level_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fuser_level_assignmentsedit.lists.user_level_id?.lookupOptions.length) {
        options.data = { id: "x_user_level_id", form: "fuser_level_assignmentsedit" };
    } else {
        options.ajax = { id: "x_user_level_id", form: "fuser_level_assignmentsedit", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.user_level_assignments.fields.user_level_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
    <div id="r_user_id"<?= $Page->user_id->rowAttributes() ?>>
        <label id="elh_user_level_assignments_user_id" for="x_user_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_id->caption() ?><?= $Page->user_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_id->cellAttributes() ?>>
<?php if ($Page->user_id->getSessionValue() != "") { ?>
<span id="el_user_level_assignments_user_id">
<span<?= $Page->user_id->viewAttributes() ?>>
<span class="form-control-plaintext"><?= $Page->user_id->getDisplayValue($Page->user_id->ViewValue) ?></span></span>
<input type="hidden" id="x_user_id" name="x_user_id" value="<?= HtmlEncode($Page->user_id->CurrentValue) ?>" data-hidden="1">
</span>
<?php } else { ?>
<span id="el_user_level_assignments_user_id">
    <select
        id="x_user_id"
        name="x_user_id"
        class="form-select ew-select<?= $Page->user_id->isInvalidClass() ?>"
        <?php if (!$Page->user_id->IsNativeSelect) { ?>
        data-select2-id="fuser_level_assignmentsedit_x_user_id"
        <?php } ?>
        data-table="user_level_assignments"
        data-field="x_user_id"
        data-value-separator="<?= $Page->user_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Page->user_id->getPlaceHolder()) ?>"
        <?= $Page->user_id->editAttributes() ?>>
        <?= $Page->user_id->selectOptionListHtml("x_user_id") ?>
    </select>
    <?= $Page->user_id->getCustomMessage() ?>
    <div class="invalid-feedback"><?= $Page->user_id->getErrorMessage() ?></div>
<?= $Page->user_id->Lookup->getParamTag($Page, "p_x_user_id") ?>
<?php if (!$Page->user_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fuser_level_assignmentsedit", function() {
    var options = { name: "x_user_id", selectId: "fuser_level_assignmentsedit_x_user_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fuser_level_assignmentsedit.lists.user_id?.lookupOptions.length) {
        options.data = { id: "x_user_id", form: "fuser_level_assignmentsedit" };
    } else {
        options.ajax = { id: "x_user_id", form: "fuser_level_assignmentsedit", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.user_level_assignments.fields.user_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fuser_level_assignmentsedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fuser_level_assignmentsedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
<?php } ?>
    </div><!-- /buttons offset -->
<?= $Page->IsModal ? "</template>" : "</div>" ?><!-- /buttons .row -->
</form>
</main>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
// Field event handlers
loadjs.ready("head", function() {
    ew.addEventHandlers("user_level_assignments");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
