<?php

namespace PHPMaker2024\FIMS;

// Page object
$UsersDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { users: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fusersdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fusersdelete")
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
<form name="fusersdelete" id="fusersdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="users">
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
<?php if ($Page->user_id->Visible) { // user_id ?>
        <th class="<?= $Page->user_id->headerCellClass() ?>"><span id="elh_users_user_id" class="users_user_id"><?= $Page->user_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->_username->Visible) { // username ?>
        <th class="<?= $Page->_username->headerCellClass() ?>"><span id="elh_users__username" class="users__username"><?= $Page->_username->caption() ?></span></th>
<?php } ?>
<?php if ($Page->_email->Visible) { // email ?>
        <th class="<?= $Page->_email->headerCellClass() ?>"><span id="elh_users__email" class="users__email"><?= $Page->_email->caption() ?></span></th>
<?php } ?>
<?php if ($Page->first_name->Visible) { // first_name ?>
        <th class="<?= $Page->first_name->headerCellClass() ?>"><span id="elh_users_first_name" class="users_first_name"><?= $Page->first_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->last_name->Visible) { // last_name ?>
        <th class="<?= $Page->last_name->headerCellClass() ?>"><span id="elh_users_last_name" class="users_last_name"><?= $Page->last_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->suffix->Visible) { // suffix ?>
        <th class="<?= $Page->suffix->headerCellClass() ?>"><span id="elh_users_suffix" class="users_suffix"><?= $Page->suffix->caption() ?></span></th>
<?php } ?>
<?php if ($Page->department_id->Visible) { // department_id ?>
        <th class="<?= $Page->department_id->headerCellClass() ?>"><span id="elh_users_department_id" class="users_department_id"><?= $Page->department_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_users_is_active" class="users_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->user_level_id->Visible) { // user_level_id ?>
        <th class="<?= $Page->user_level_id->headerCellClass() ?>"><span id="elh_users_user_level_id" class="users_user_level_id"><?= $Page->user_level_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->position_id->Visible) { // position_id ?>
        <th class="<?= $Page->position_id->headerCellClass() ?>"><span id="elh_users_position_id" class="users_position_id"><?= $Page->position_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->employee_id->Visible) { // employee_id ?>
        <th class="<?= $Page->employee_id->headerCellClass() ?>"><span id="elh_users_employee_id" class="users_employee_id"><?= $Page->employee_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->must_change_password->Visible) { // must_change_password ?>
        <th class="<?= $Page->must_change_password->headerCellClass() ?>"><span id="elh_users_must_change_password" class="users_must_change_password"><?= $Page->must_change_password->caption() ?></span></th>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
        <th class="<?= $Page->rc_id->headerCellClass() ?>"><span id="elh_users_rc_id" class="users_rc_id"><?= $Page->rc_id->caption() ?></span></th>
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
<?php if ($Page->user_id->Visible) { // user_id ?>
        <td<?= $Page->user_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->user_id->viewAttributes() ?>>
<?= $Page->user_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->_username->Visible) { // username ?>
        <td<?= $Page->_username->cellAttributes() ?>>
<span id="">
<span<?= $Page->_username->viewAttributes() ?>>
<?= $Page->_username->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->_email->Visible) { // email ?>
        <td<?= $Page->_email->cellAttributes() ?>>
<span id="">
<span<?= $Page->_email->viewAttributes() ?>>
<?= $Page->_email->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->first_name->Visible) { // first_name ?>
        <td<?= $Page->first_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->first_name->viewAttributes() ?>>
<?= $Page->first_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->last_name->Visible) { // last_name ?>
        <td<?= $Page->last_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->last_name->viewAttributes() ?>>
<?= $Page->last_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->suffix->Visible) { // suffix ?>
        <td<?= $Page->suffix->cellAttributes() ?>>
<span id="">
<span<?= $Page->suffix->viewAttributes() ?>>
<?= $Page->suffix->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->department_id->Visible) { // department_id ?>
        <td<?= $Page->department_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->department_id->viewAttributes() ?>>
<?= $Page->department_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <td<?= $Page->is_active->cellAttributes() ?>>
<span id="">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->user_level_id->Visible) { // user_level_id ?>
        <td<?= $Page->user_level_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->user_level_id->viewAttributes() ?>>
<?= $Page->user_level_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->position_id->Visible) { // position_id ?>
        <td<?= $Page->position_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->position_id->viewAttributes() ?>>
<?= $Page->position_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->employee_id->Visible) { // employee_id ?>
        <td<?= $Page->employee_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->employee_id->viewAttributes() ?>>
<?= $Page->employee_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->must_change_password->Visible) { // must_change_password ?>
        <td<?= $Page->must_change_password->cellAttributes() ?>>
<span id="">
<span<?= $Page->must_change_password->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->must_change_password->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
        <td<?= $Page->rc_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->rc_id->viewAttributes() ?>>
<?= $Page->rc_id->getViewValue() ?></span>
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
