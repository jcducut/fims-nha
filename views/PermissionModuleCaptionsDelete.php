<?php

namespace PHPMaker2024\FIMS;

// Page object
$PermissionModuleCaptionsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { permission_module_captions: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fpermission_module_captionsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fpermission_module_captionsdelete")
        .setPageId("delete")
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
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<form name="fpermission_module_captionsdelete" id="fpermission_module_captionsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="permission_module_captions">
<input type="hidden" name="action" id="action" value="delete">
<?php foreach ($Page->RecKeys as $key) { ?>
<?php $keyvalue = is_array($key) ? implode(Config("COMPOSITE_KEY_SEPARATOR"), $key) : $key; ?>
<input type="hidden" name="key_m[]" value="<?= HtmlEncode($keyvalue) ?>">
<?php } ?>
<div class="card ew-card ew-grid <?= $Page->TableGridClass ?>">
<div class="card-body ew-grid-middle-panel <?= $Page->TableContainerClass ?>" style="<?= $Page->TableContainerStyle ?>">
<table class="<?= $Page->TableClass ?>">
    <thead>
    <tr class="ew-table-header">
<?php if ($Page->table_name->Visible) { // table_name ?>
        <th class="<?= $Page->table_name->headerCellClass() ?>"><span id="elh_permission_module_captions_table_name" class="permission_module_captions_table_name"><?= $Page->table_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->caption->Visible) { // caption ?>
        <th class="<?= $Page->caption->headerCellClass() ?>"><span id="elh_permission_module_captions_caption" class="permission_module_captions_caption"><?= $Page->caption->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_hidden->Visible) { // is_hidden ?>
        <th class="<?= $Page->is_hidden->headerCellClass() ?>"><span id="elh_permission_module_captions_is_hidden" class="permission_module_captions_is_hidden"><?= $Page->is_hidden->caption() ?></span></th>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <th class="<?= $Page->updated_at->headerCellClass() ?>"><span id="elh_permission_module_captions_updated_at" class="permission_module_captions_updated_at"><?= $Page->updated_at->caption() ?></span></th>
<?php } ?>
    </tr>
    </thead>
    <tbody>
<?php
$Page->RecordCount = 0;
$i = 0;
while ($Page->fetch()) {
    $Page->RecordCount++;
    $Page->RowCount++;

    // Set row properties
    $Page->resetAttributes();
    $Page->RowType = RowType::VIEW; // View

    // Get the field contents
    $Page->loadRowValues($Page->CurrentRow);

    // Render row
    $Page->renderRow();
?>
    <tr <?= $Page->rowAttributes() ?>>
<?php if ($Page->table_name->Visible) { // table_name ?>
        <td<?= $Page->table_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->table_name->viewAttributes() ?>>
<?= $Page->table_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->caption->Visible) { // caption ?>
        <td<?= $Page->caption->cellAttributes() ?>>
<span id="">
<span<?= $Page->caption->viewAttributes() ?>>
<?= $Page->caption->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->is_hidden->Visible) { // is_hidden ?>
        <td<?= $Page->is_hidden->cellAttributes() ?>>
<span id="">
<span<?= $Page->is_hidden->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_hidden->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <td<?= $Page->updated_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
    </tr>
<?php
}
$Page->Recordset?->free();
?>
</tbody>
</table>
</div>
</div>
<div class="ew-buttons ew-desktop-buttons">
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit"><?= $Language->phrase("DeleteBtn") ?></button>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
</div>
</form>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
