<?php

namespace PHPMaker2024\FIMS;

// Page object
$NotificationsEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fnotificationsedit" id="fnotificationsedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { notifications: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fnotificationsedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fnotificationsedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["id", [fields.id.visible && fields.id.required ? ew.Validators.required(fields.id.caption) : null], fields.id.isInvalid],
            ["timestamp", [fields.timestamp.visible && fields.timestamp.required ? ew.Validators.required(fields.timestamp.caption) : null, ew.Validators.datetime(fields.timestamp.clientFormatPattern)], fields.timestamp.isInvalid],
            ["type", [fields.type.visible && fields.type.required ? ew.Validators.required(fields.type.caption) : null], fields.type.isInvalid],
            ["target", [fields.target.visible && fields.target.required ? ew.Validators.required(fields.target.caption) : null], fields.target.isInvalid],
            ["user_id", [fields.user_id.visible && fields.user_id.required ? ew.Validators.required(fields.user_id.caption) : null, ew.Validators.integer], fields.user_id.isInvalid],
            ["subject", [fields.subject.visible && fields.subject.required ? ew.Validators.required(fields.subject.caption) : null], fields.subject.isInvalid],
            ["body", [fields.body.visible && fields.body.required ? ew.Validators.required(fields.body.caption) : null], fields.body.isInvalid],
            ["link", [fields.link.visible && fields.link.required ? ew.Validators.required(fields.link.caption) : null], fields.link.isInvalid],
            ["from_system", [fields.from_system.visible && fields.from_system.required ? ew.Validators.required(fields.from_system.caption) : null], fields.from_system.isInvalid],
            ["is_read", [fields.is_read.visible && fields.is_read.required ? ew.Validators.required(fields.is_read.caption) : null], fields.is_read.isInvalid],
            ["created_at", [fields.created_at.visible && fields.created_at.required ? ew.Validators.required(fields.created_at.caption) : null, ew.Validators.datetime(fields.created_at.clientFormatPattern)], fields.created_at.isInvalid],
            ["read_users", [fields.read_users.visible && fields.read_users.required ? ew.Validators.required(fields.read_users.caption) : null], fields.read_users.isInvalid],
            ["sender_id", [fields.sender_id.visible && fields.sender_id.required ? ew.Validators.required(fields.sender_id.caption) : null, ew.Validators.integer], fields.sender_id.isInvalid],
            ["event_key", [fields.event_key.visible && fields.event_key.required ? ew.Validators.required(fields.event_key.caption) : null], fields.event_key.isInvalid]
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
            "is_read": <?= $Page->is_read->toClientList($Page) ?>,
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
<input type="hidden" name="t" value="notifications">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->id->Visible) { // id ?>
    <div id="r_id"<?= $Page->id->rowAttributes() ?>>
        <label id="elh_notifications_id" for="x_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->id->caption() ?><?= $Page->id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->id->cellAttributes() ?>>
<span id="el_notifications_id">
<input type="<?= $Page->id->getInputTextType() ?>" name="x_id" id="x_id" data-table="notifications" data-field="x_id" value="<?= $Page->id->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->id->formatPattern()) ?>"<?= $Page->id->editAttributes() ?> aria-describedby="x_id_help">
<?= $Page->id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->id->getErrorMessage() ?></div>
<input type="hidden" data-table="notifications" data-field="x_id" data-hidden="1" data-old name="o_id" id="o_id" value="<?= HtmlEncode($Page->id->OldValue ?? $Page->id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->timestamp->Visible) { // timestamp ?>
    <div id="r_timestamp"<?= $Page->timestamp->rowAttributes() ?>>
        <label id="elh_notifications_timestamp" for="x_timestamp" class="<?= $Page->LeftColumnClass ?>"><?= $Page->timestamp->caption() ?><?= $Page->timestamp->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->timestamp->cellAttributes() ?>>
<span id="el_notifications_timestamp">
<input type="<?= $Page->timestamp->getInputTextType() ?>" name="x_timestamp" id="x_timestamp" data-table="notifications" data-field="x_timestamp" value="<?= $Page->timestamp->EditValue ?>" placeholder="<?= HtmlEncode($Page->timestamp->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->timestamp->formatPattern()) ?>"<?= $Page->timestamp->editAttributes() ?> aria-describedby="x_timestamp_help">
<?= $Page->timestamp->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->timestamp->getErrorMessage() ?></div>
<?php if (!$Page->timestamp->ReadOnly && !$Page->timestamp->Disabled && !isset($Page->timestamp->EditAttrs["readonly"]) && !isset($Page->timestamp->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fnotificationsedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fnotificationsedit", "x_timestamp", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->type->Visible) { // type ?>
    <div id="r_type"<?= $Page->type->rowAttributes() ?>>
        <label id="elh_notifications_type" for="x_type" class="<?= $Page->LeftColumnClass ?>"><?= $Page->type->caption() ?><?= $Page->type->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->type->cellAttributes() ?>>
<span id="el_notifications_type">
<input type="<?= $Page->type->getInputTextType() ?>" name="x_type" id="x_type" data-table="notifications" data-field="x_type" value="<?= $Page->type->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->type->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->type->formatPattern()) ?>"<?= $Page->type->editAttributes() ?> aria-describedby="x_type_help">
<?= $Page->type->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->type->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->target->Visible) { // target ?>
    <div id="r_target"<?= $Page->target->rowAttributes() ?>>
        <label id="elh_notifications_target" for="x_target" class="<?= $Page->LeftColumnClass ?>"><?= $Page->target->caption() ?><?= $Page->target->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->target->cellAttributes() ?>>
<span id="el_notifications_target">
<input type="<?= $Page->target->getInputTextType() ?>" name="x_target" id="x_target" data-table="notifications" data-field="x_target" value="<?= $Page->target->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->target->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->target->formatPattern()) ?>"<?= $Page->target->editAttributes() ?> aria-describedby="x_target_help">
<?= $Page->target->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->target->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
    <div id="r_user_id"<?= $Page->user_id->rowAttributes() ?>>
        <label id="elh_notifications_user_id" for="x_user_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_id->caption() ?><?= $Page->user_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_id->cellAttributes() ?>>
<span id="el_notifications_user_id">
<input type="<?= $Page->user_id->getInputTextType() ?>" name="x_user_id" id="x_user_id" data-table="notifications" data-field="x_user_id" value="<?= $Page->user_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->user_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->user_id->formatPattern()) ?>"<?= $Page->user_id->editAttributes() ?> aria-describedby="x_user_id_help">
<?= $Page->user_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->user_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->subject->Visible) { // subject ?>
    <div id="r_subject"<?= $Page->subject->rowAttributes() ?>>
        <label id="elh_notifications_subject" for="x_subject" class="<?= $Page->LeftColumnClass ?>"><?= $Page->subject->caption() ?><?= $Page->subject->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->subject->cellAttributes() ?>>
<span id="el_notifications_subject">
<input type="<?= $Page->subject->getInputTextType() ?>" name="x_subject" id="x_subject" data-table="notifications" data-field="x_subject" value="<?= $Page->subject->EditValue ?>" size="30" maxlength="200" placeholder="<?= HtmlEncode($Page->subject->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->subject->formatPattern()) ?>"<?= $Page->subject->editAttributes() ?> aria-describedby="x_subject_help">
<?= $Page->subject->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->subject->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->body->Visible) { // body ?>
    <div id="r_body"<?= $Page->body->rowAttributes() ?>>
        <label id="elh_notifications_body" for="x_body" class="<?= $Page->LeftColumnClass ?>"><?= $Page->body->caption() ?><?= $Page->body->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->body->cellAttributes() ?>>
<span id="el_notifications_body">
<textarea data-table="notifications" data-field="x_body" name="x_body" id="x_body" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->body->getPlaceHolder()) ?>"<?= $Page->body->editAttributes() ?> aria-describedby="x_body_help"><?= $Page->body->EditValue ?></textarea>
<?= $Page->body->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->body->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->link->Visible) { // link ?>
    <div id="r_link"<?= $Page->link->rowAttributes() ?>>
        <label id="elh_notifications_link" for="x_link" class="<?= $Page->LeftColumnClass ?>"><?= $Page->link->caption() ?><?= $Page->link->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->link->cellAttributes() ?>>
<span id="el_notifications_link">
<input type="<?= $Page->link->getInputTextType() ?>" name="x_link" id="x_link" data-table="notifications" data-field="x_link" value="<?= $Page->link->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->link->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->link->formatPattern()) ?>"<?= $Page->link->editAttributes() ?> aria-describedby="x_link_help">
<?= $Page->link->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->link->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->from_system->Visible) { // from_system ?>
    <div id="r_from_system"<?= $Page->from_system->rowAttributes() ?>>
        <label id="elh_notifications_from_system" for="x_from_system" class="<?= $Page->LeftColumnClass ?>"><?= $Page->from_system->caption() ?><?= $Page->from_system->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->from_system->cellAttributes() ?>>
<span id="el_notifications_from_system">
<input type="<?= $Page->from_system->getInputTextType() ?>" name="x_from_system" id="x_from_system" data-table="notifications" data-field="x_from_system" value="<?= $Page->from_system->EditValue ?>" size="30" maxlength="30" placeholder="<?= HtmlEncode($Page->from_system->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->from_system->formatPattern()) ?>"<?= $Page->from_system->editAttributes() ?> aria-describedby="x_from_system_help">
<?= $Page->from_system->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->from_system->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_read->Visible) { // is_read ?>
    <div id="r_is_read"<?= $Page->is_read->rowAttributes() ?>>
        <label id="elh_notifications_is_read" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_read->caption() ?><?= $Page->is_read->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_read->cellAttributes() ?>>
<span id="el_notifications_is_read">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_read->isInvalidClass() ?>" data-table="notifications" data-field="x_is_read" data-boolean name="x_is_read" id="x_is_read" value="1"<?= ConvertToBool($Page->is_read->CurrentValue) ? " checked" : "" ?><?= $Page->is_read->editAttributes() ?> aria-describedby="x_is_read_help">
    <div class="invalid-feedback"><?= $Page->is_read->getErrorMessage() ?></div>
</div>
<?= $Page->is_read->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_notifications_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_notifications_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="notifications" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fnotificationsedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fnotificationsedit", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->read_users->Visible) { // read_users ?>
    <div id="r_read_users"<?= $Page->read_users->rowAttributes() ?>>
        <label id="elh_notifications_read_users" for="x_read_users" class="<?= $Page->LeftColumnClass ?>"><?= $Page->read_users->caption() ?><?= $Page->read_users->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->read_users->cellAttributes() ?>>
<span id="el_notifications_read_users">
<textarea data-table="notifications" data-field="x_read_users" name="x_read_users" id="x_read_users" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->read_users->getPlaceHolder()) ?>"<?= $Page->read_users->editAttributes() ?> aria-describedby="x_read_users_help"><?= $Page->read_users->EditValue ?></textarea>
<?= $Page->read_users->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->read_users->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->sender_id->Visible) { // sender_id ?>
    <div id="r_sender_id"<?= $Page->sender_id->rowAttributes() ?>>
        <label id="elh_notifications_sender_id" for="x_sender_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->sender_id->caption() ?><?= $Page->sender_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->sender_id->cellAttributes() ?>>
<span id="el_notifications_sender_id">
<input type="<?= $Page->sender_id->getInputTextType() ?>" name="x_sender_id" id="x_sender_id" data-table="notifications" data-field="x_sender_id" value="<?= $Page->sender_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->sender_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->sender_id->formatPattern()) ?>"<?= $Page->sender_id->editAttributes() ?> aria-describedby="x_sender_id_help">
<?= $Page->sender_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->sender_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->event_key->Visible) { // event_key ?>
    <div id="r_event_key"<?= $Page->event_key->rowAttributes() ?>>
        <label id="elh_notifications_event_key" for="x_event_key" class="<?= $Page->LeftColumnClass ?>"><?= $Page->event_key->caption() ?><?= $Page->event_key->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->event_key->cellAttributes() ?>>
<span id="el_notifications_event_key">
<input type="<?= $Page->event_key->getInputTextType() ?>" name="x_event_key" id="x_event_key" data-table="notifications" data-field="x_event_key" value="<?= $Page->event_key->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->event_key->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->event_key->formatPattern()) ?>"<?= $Page->event_key->editAttributes() ?> aria-describedby="x_event_key_help">
<?= $Page->event_key->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->event_key->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fnotificationsedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fnotificationsedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("notifications");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
