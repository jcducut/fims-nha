<?php

namespace PHPMaker2024\FIMS;

// Page object
$NotificationsView = &$Page;
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
<form name="fnotificationsview" id="fnotificationsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { notifications: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fnotificationsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fnotificationsview")
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
<input type="hidden" name="t" value="notifications">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->id->Visible) { // id ?>
    <tr id="r_id"<?= $Page->id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_id"><?= $Page->id->caption() ?></span></td>
        <td data-name="id"<?= $Page->id->cellAttributes() ?>>
<span id="el_notifications_id">
<span<?= $Page->id->viewAttributes() ?>>
<?= $Page->id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->timestamp->Visible) { // timestamp ?>
    <tr id="r_timestamp"<?= $Page->timestamp->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_timestamp"><?= $Page->timestamp->caption() ?></span></td>
        <td data-name="timestamp"<?= $Page->timestamp->cellAttributes() ?>>
<span id="el_notifications_timestamp">
<span<?= $Page->timestamp->viewAttributes() ?>>
<?= $Page->timestamp->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->type->Visible) { // type ?>
    <tr id="r_type"<?= $Page->type->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_type"><?= $Page->type->caption() ?></span></td>
        <td data-name="type"<?= $Page->type->cellAttributes() ?>>
<span id="el_notifications_type">
<span<?= $Page->type->viewAttributes() ?>>
<?= $Page->type->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->target->Visible) { // target ?>
    <tr id="r_target"<?= $Page->target->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_target"><?= $Page->target->caption() ?></span></td>
        <td data-name="target"<?= $Page->target->cellAttributes() ?>>
<span id="el_notifications_target">
<span<?= $Page->target->viewAttributes() ?>>
<?= $Page->target->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
    <tr id="r_user_id"<?= $Page->user_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_user_id"><?= $Page->user_id->caption() ?></span></td>
        <td data-name="user_id"<?= $Page->user_id->cellAttributes() ?>>
<span id="el_notifications_user_id">
<span<?= $Page->user_id->viewAttributes() ?>>
<?= $Page->user_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->subject->Visible) { // subject ?>
    <tr id="r_subject"<?= $Page->subject->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_subject"><?= $Page->subject->caption() ?></span></td>
        <td data-name="subject"<?= $Page->subject->cellAttributes() ?>>
<span id="el_notifications_subject">
<span<?= $Page->subject->viewAttributes() ?>>
<?= $Page->subject->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->body->Visible) { // body ?>
    <tr id="r_body"<?= $Page->body->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_body"><?= $Page->body->caption() ?></span></td>
        <td data-name="body"<?= $Page->body->cellAttributes() ?>>
<span id="el_notifications_body">
<span<?= $Page->body->viewAttributes() ?>>
<?= $Page->body->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->link->Visible) { // link ?>
    <tr id="r_link"<?= $Page->link->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_link"><?= $Page->link->caption() ?></span></td>
        <td data-name="link"<?= $Page->link->cellAttributes() ?>>
<span id="el_notifications_link">
<span<?= $Page->link->viewAttributes() ?>>
<?= $Page->link->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->from_system->Visible) { // from_system ?>
    <tr id="r_from_system"<?= $Page->from_system->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_from_system"><?= $Page->from_system->caption() ?></span></td>
        <td data-name="from_system"<?= $Page->from_system->cellAttributes() ?>>
<span id="el_notifications_from_system">
<span<?= $Page->from_system->viewAttributes() ?>>
<?= $Page->from_system->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_read->Visible) { // is_read ?>
    <tr id="r_is_read"<?= $Page->is_read->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_is_read"><?= $Page->is_read->caption() ?></span></td>
        <td data-name="is_read"<?= $Page->is_read->cellAttributes() ?>>
<span id="el_notifications_is_read">
<span<?= $Page->is_read->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_read->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_notifications_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->read_users->Visible) { // read_users ?>
    <tr id="r_read_users"<?= $Page->read_users->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_read_users"><?= $Page->read_users->caption() ?></span></td>
        <td data-name="read_users"<?= $Page->read_users->cellAttributes() ?>>
<span id="el_notifications_read_users">
<span<?= $Page->read_users->viewAttributes() ?>>
<?= $Page->read_users->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->sender_id->Visible) { // sender_id ?>
    <tr id="r_sender_id"<?= $Page->sender_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_sender_id"><?= $Page->sender_id->caption() ?></span></td>
        <td data-name="sender_id"<?= $Page->sender_id->cellAttributes() ?>>
<span id="el_notifications_sender_id">
<span<?= $Page->sender_id->viewAttributes() ?>>
<?= $Page->sender_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->event_key->Visible) { // event_key ?>
    <tr id="r_event_key"<?= $Page->event_key->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_notifications_event_key"><?= $Page->event_key->caption() ?></span></td>
        <td data-name="event_key"<?= $Page->event_key->cellAttributes() ?>>
<span id="el_notifications_event_key">
<span<?= $Page->event_key->viewAttributes() ?>>
<?= $Page->event_key->getViewValue() ?></span>
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
