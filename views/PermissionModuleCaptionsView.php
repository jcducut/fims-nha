<?php

namespace PHPMaker2024\FIMS;

// Page object
$PermissionModuleCaptionsView = &$Page;
?>
<?php if (!$Page->isExport()) { ?>
<div class="btn-toolbar ew-toolbar">
<?php $Page->ExportOptions->render("body") ?>
<?php $Page->OtherOptions->render("body") ?>
</div>
<?php } ?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="view">
<form name="fpermission_module_captionsview" id="fpermission_module_captionsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { permission_module_captions: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fpermission_module_captionsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fpermission_module_captionsview")
        .setPageId("view")
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
<?php } ?>
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="permission_module_captions">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->table_name->Visible) { // table_name ?>
    <tr id="r_table_name"<?= $Page->table_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_permission_module_captions_table_name"><?= $Page->table_name->caption() ?></span></td>
        <td data-name="table_name"<?= $Page->table_name->cellAttributes() ?>>
<span id="el_permission_module_captions_table_name">
<span<?= $Page->table_name->viewAttributes() ?>>
<?= $Page->table_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->caption->Visible) { // caption ?>
    <tr id="r_caption"<?= $Page->caption->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_permission_module_captions_caption"><?= $Page->caption->caption() ?></span></td>
        <td data-name="caption"<?= $Page->caption->cellAttributes() ?>>
<span id="el_permission_module_captions_caption">
<span<?= $Page->caption->viewAttributes() ?>>
<?= $Page->caption->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_hidden->Visible) { // is_hidden ?>
    <tr id="r_is_hidden"<?= $Page->is_hidden->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_permission_module_captions_is_hidden"><?= $Page->is_hidden->caption() ?></span></td>
        <td data-name="is_hidden"<?= $Page->is_hidden->cellAttributes() ?>>
<span id="el_permission_module_captions_is_hidden">
<span<?= $Page->is_hidden->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_hidden->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <tr id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_permission_module_captions_updated_at"><?= $Page->updated_at->caption() ?></span></td>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_permission_module_captions_updated_at">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
</table>
</form>
</main>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<?php if (!$Page->isExport()) { ?>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
