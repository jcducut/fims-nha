<?php

namespace PHPMaker2024\FIMS;

// Page object
$PermissionModuleCaptionsEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fpermission_module_captionsedit" id="fpermission_module_captionsedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { permission_module_captions: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fpermission_module_captionsedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fpermission_module_captionsedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["table_name", [fields.table_name.visible && fields.table_name.required ? ew.Validators.required(fields.table_name.caption) : null], fields.table_name.isInvalid],
            ["caption", [fields.caption.visible && fields.caption.required ? ew.Validators.required(fields.caption.caption) : null], fields.caption.isInvalid],
            ["is_hidden", [fields.is_hidden.visible && fields.is_hidden.required ? ew.Validators.required(fields.is_hidden.caption) : null], fields.is_hidden.isInvalid],
            ["updated_at", [fields.updated_at.visible && fields.updated_at.required ? ew.Validators.required(fields.updated_at.caption) : null, ew.Validators.datetime(fields.updated_at.clientFormatPattern)], fields.updated_at.isInvalid]
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
            "is_hidden": <?= $Page->is_hidden->toClientList($Page) ?>,
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
<input type="hidden" name="t" value="permission_module_captions">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->table_name->Visible) { // table_name ?>
    <div id="r_table_name"<?= $Page->table_name->rowAttributes() ?>>
        <label id="elh_permission_module_captions_table_name" for="x_table_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->table_name->caption() ?><?= $Page->table_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->table_name->cellAttributes() ?>>
<span id="el_permission_module_captions_table_name">
<input type="<?= $Page->table_name->getInputTextType() ?>" name="x_table_name" id="x_table_name" data-table="permission_module_captions" data-field="x_table_name" value="<?= $Page->table_name->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->table_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->table_name->formatPattern()) ?>"<?= $Page->table_name->editAttributes() ?> aria-describedby="x_table_name_help">
<?= $Page->table_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->table_name->getErrorMessage() ?></div>
<input type="hidden" data-table="permission_module_captions" data-field="x_table_name" data-hidden="1" data-old name="o_table_name" id="o_table_name" value="<?= HtmlEncode($Page->table_name->OldValue ?? $Page->table_name->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->caption->Visible) { // caption ?>
    <div id="r_caption"<?= $Page->caption->rowAttributes() ?>>
        <label id="elh_permission_module_captions_caption" for="x_caption" class="<?= $Page->LeftColumnClass ?>"><?= $Page->caption->caption() ?><?= $Page->caption->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->caption->cellAttributes() ?>>
<span id="el_permission_module_captions_caption">
<input type="<?= $Page->caption->getInputTextType() ?>" name="x_caption" id="x_caption" data-table="permission_module_captions" data-field="x_caption" value="<?= $Page->caption->EditValue ?>" size="30" maxlength="150" placeholder="<?= HtmlEncode($Page->caption->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->caption->formatPattern()) ?>"<?= $Page->caption->editAttributes() ?> aria-describedby="x_caption_help">
<?= $Page->caption->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->caption->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_hidden->Visible) { // is_hidden ?>
    <div id="r_is_hidden"<?= $Page->is_hidden->rowAttributes() ?>>
        <label id="elh_permission_module_captions_is_hidden" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_hidden->caption() ?><?= $Page->is_hidden->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_hidden->cellAttributes() ?>>
<span id="el_permission_module_captions_is_hidden">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_hidden->isInvalidClass() ?>" data-table="permission_module_captions" data-field="x_is_hidden" data-boolean name="x_is_hidden" id="x_is_hidden" value="1"<?= ConvertToBool($Page->is_hidden->CurrentValue) ? " checked" : "" ?><?= $Page->is_hidden->editAttributes() ?> aria-describedby="x_is_hidden_help">
    <div class="invalid-feedback"><?= $Page->is_hidden->getErrorMessage() ?></div>
</div>
<?= $Page->is_hidden->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <div id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <label id="elh_permission_module_captions_updated_at" for="x_updated_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->updated_at->caption() ?><?= $Page->updated_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_permission_module_captions_updated_at">
<input type="<?= $Page->updated_at->getInputTextType() ?>" name="x_updated_at" id="x_updated_at" data-table="permission_module_captions" data-field="x_updated_at" value="<?= $Page->updated_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->updated_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->updated_at->formatPattern()) ?>"<?= $Page->updated_at->editAttributes() ?> aria-describedby="x_updated_at_help">
<?= $Page->updated_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->updated_at->getErrorMessage() ?></div>
<?php if (!$Page->updated_at->ReadOnly && !$Page->updated_at->Disabled && !isset($Page->updated_at->EditAttrs["readonly"]) && !isset($Page->updated_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fpermission_module_captionsedit", "datetimepicker"], function () {
    let format = "<?= DateFormat(0) ?>",
        options = {
            localization: {
                locale: ew.LANGUAGE_ID + "-u-nu-" + ew.getNumberingSystem(),
                hourCycle: format.match(/H/) ? "h24" : "h12",
                format,
                ...ew.language.phrase("datetimepicker")
            },
            display: {
                icons: {
                    previous: ew.IS_RTL ? "fa-solid fa-chevron-right" : "fa-solid fa-chevron-left",
                    next: ew.IS_RTL ? "fa-solid fa-chevron-left" : "fa-solid fa-chevron-right"
                },
                components: {
                    clock: !!format.match(/h/i) || !!format.match(/m/) || !!format.match(/s/i),
                    hours: !!format.match(/h/i),
                    minutes: !!format.match(/m/),
                    seconds: !!format.match(/s/i)
                },
                theme: ew.getPreferredTheme()
            }
        };
    ew.createDateTimePicker("fpermission_module_captionsedit", "x_updated_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fpermission_module_captionsedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fpermission_module_captionsedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("permission_module_captions");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
