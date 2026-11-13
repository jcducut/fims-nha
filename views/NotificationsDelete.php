<?php

namespace PHPMaker2024\FIMS;

// Page object
$NotificationsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { notifications: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fnotificationsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fnotificationsdelete")
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
<form name="fnotificationsdelete" id="fnotificationsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="notifications">
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
<?php if ($Page->id->Visible) { // id ?>
        <th class="<?= $Page->id->headerCellClass() ?>"><span id="elh_notifications_id" class="notifications_id"><?= $Page->id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->timestamp->Visible) { // timestamp ?>
        <th class="<?= $Page->timestamp->headerCellClass() ?>"><span id="elh_notifications_timestamp" class="notifications_timestamp"><?= $Page->timestamp->caption() ?></span></th>
<?php } ?>
<?php if ($Page->type->Visible) { // type ?>
        <th class="<?= $Page->type->headerCellClass() ?>"><span id="elh_notifications_type" class="notifications_type"><?= $Page->type->caption() ?></span></th>
<?php } ?>
<?php if ($Page->target->Visible) { // target ?>
        <th class="<?= $Page->target->headerCellClass() ?>"><span id="elh_notifications_target" class="notifications_target"><?= $Page->target->caption() ?></span></th>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
        <th class="<?= $Page->user_id->headerCellClass() ?>"><span id="elh_notifications_user_id" class="notifications_user_id"><?= $Page->user_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->subject->Visible) { // subject ?>
        <th class="<?= $Page->subject->headerCellClass() ?>"><span id="elh_notifications_subject" class="notifications_subject"><?= $Page->subject->caption() ?></span></th>
<?php } ?>
<?php if ($Page->link->Visible) { // link ?>
        <th class="<?= $Page->link->headerCellClass() ?>"><span id="elh_notifications_link" class="notifications_link"><?= $Page->link->caption() ?></span></th>
<?php } ?>
<?php if ($Page->from_system->Visible) { // from_system ?>
        <th class="<?= $Page->from_system->headerCellClass() ?>"><span id="elh_notifications_from_system" class="notifications_from_system"><?= $Page->from_system->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_read->Visible) { // is_read ?>
        <th class="<?= $Page->is_read->headerCellClass() ?>"><span id="elh_notifications_is_read" class="notifications_is_read"><?= $Page->is_read->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_notifications_created_at" class="notifications_created_at"><?= $Page->created_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->sender_id->Visible) { // sender_id ?>
        <th class="<?= $Page->sender_id->headerCellClass() ?>"><span id="elh_notifications_sender_id" class="notifications_sender_id"><?= $Page->sender_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->event_key->Visible) { // event_key ?>
        <th class="<?= $Page->event_key->headerCellClass() ?>"><span id="elh_notifications_event_key" class="notifications_event_key"><?= $Page->event_key->caption() ?></span></th>
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
<?php if ($Page->id->Visible) { // id ?>
        <td<?= $Page->id->cellAttributes() ?>>
<span id="">
<span<?= $Page->id->viewAttributes() ?>>
<?= $Page->id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->timestamp->Visible) { // timestamp ?>
        <td<?= $Page->timestamp->cellAttributes() ?>>
<span id="">
<span<?= $Page->timestamp->viewAttributes() ?>>
<?= $Page->timestamp->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->type->Visible) { // type ?>
        <td<?= $Page->type->cellAttributes() ?>>
<span id="">
<span<?= $Page->type->viewAttributes() ?>>
<?= $Page->type->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->target->Visible) { // target ?>
        <td<?= $Page->target->cellAttributes() ?>>
<span id="">
<span<?= $Page->target->viewAttributes() ?>>
<?= $Page->target->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
        <td<?= $Page->user_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->user_id->viewAttributes() ?>>
<?= $Page->user_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->subject->Visible) { // subject ?>
        <td<?= $Page->subject->cellAttributes() ?>>
<span id="">
<span<?= $Page->subject->viewAttributes() ?>>
<?= $Page->subject->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->link->Visible) { // link ?>
        <td<?= $Page->link->cellAttributes() ?>>
<span id="">
<span<?= $Page->link->viewAttributes() ?>>
<?= $Page->link->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->from_system->Visible) { // from_system ?>
        <td<?= $Page->from_system->cellAttributes() ?>>
<span id="">
<span<?= $Page->from_system->viewAttributes() ?>>
<?= $Page->from_system->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->is_read->Visible) { // is_read ?>
        <td<?= $Page->is_read->cellAttributes() ?>>
<span id="">
<span<?= $Page->is_read->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_read->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <td<?= $Page->created_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->sender_id->Visible) { // sender_id ?>
        <td<?= $Page->sender_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->sender_id->viewAttributes() ?>>
<?= $Page->sender_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->event_key->Visible) { // event_key ?>
        <td<?= $Page->event_key->cellAttributes() ?>>
<span id="">
<span<?= $Page->event_key->viewAttributes() ?>>
<?= $Page->event_key->getViewValue() ?></span>
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
