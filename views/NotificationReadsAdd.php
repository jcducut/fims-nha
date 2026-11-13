<?php

namespace PHPMaker2024\FIMS;

// Page object
$NotificationReadsAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { notification_reads: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fnotification_readsadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fnotification_readsadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["notification_id", [fields.notification_id.visible && fields.notification_id.required ? ew.Validators.required(fields.notification_id.caption) : null], fields.notification_id.isInvalid],
            ["user_id", [fields.user_id.visible && fields.user_id.required ? ew.Validators.required(fields.user_id.caption) : null, ew.Validators.integer], fields.user_id.isInvalid],
            ["read_at", [fields.read_at.visible && fields.read_at.required ? ew.Validators.required(fields.read_at.caption) : null, ew.Validators.datetime(fields.read_at.clientFormatPattern)], fields.read_at.isInvalid]
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
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<form name="fnotification_readsadd" id="fnotification_readsadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="notification_reads">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->notification_id->Visible) { // notification_id ?>
    <div id="r_notification_id"<?= $Page->notification_id->rowAttributes() ?>>
        <label id="elh_notification_reads_notification_id" for="x_notification_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->notification_id->caption() ?><?= $Page->notification_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->notification_id->cellAttributes() ?>>
<span id="el_notification_reads_notification_id">
<input type="<?= $Page->notification_id->getInputTextType() ?>" name="x_notification_id" id="x_notification_id" data-table="notification_reads" data-field="x_notification_id" value="<?= $Page->notification_id->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->notification_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->notification_id->formatPattern()) ?>"<?= $Page->notification_id->editAttributes() ?> aria-describedby="x_notification_id_help">
<?= $Page->notification_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->notification_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
    <div id="r_user_id"<?= $Page->user_id->rowAttributes() ?>>
        <label id="elh_notification_reads_user_id" for="x_user_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_id->caption() ?><?= $Page->user_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_id->cellAttributes() ?>>
<span id="el_notification_reads_user_id">
<input type="<?= $Page->user_id->getInputTextType() ?>" name="x_user_id" id="x_user_id" data-table="notification_reads" data-field="x_user_id" value="<?= $Page->user_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->user_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->user_id->formatPattern()) ?>"<?= $Page->user_id->editAttributes() ?> aria-describedby="x_user_id_help">
<?= $Page->user_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->user_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->read_at->Visible) { // read_at ?>
    <div id="r_read_at"<?= $Page->read_at->rowAttributes() ?>>
        <label id="elh_notification_reads_read_at" for="x_read_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->read_at->caption() ?><?= $Page->read_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->read_at->cellAttributes() ?>>
<span id="el_notification_reads_read_at">
<input type="<?= $Page->read_at->getInputTextType() ?>" name="x_read_at" id="x_read_at" data-table="notification_reads" data-field="x_read_at" value="<?= $Page->read_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->read_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->read_at->formatPattern()) ?>"<?= $Page->read_at->editAttributes() ?> aria-describedby="x_read_at_help">
<?= $Page->read_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->read_at->getErrorMessage() ?></div>
<?php if (!$Page->read_at->ReadOnly && !$Page->read_at->Disabled && !isset($Page->read_at->EditAttrs["readonly"]) && !isset($Page->read_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fnotification_readsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fnotification_readsadd", "x_read_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
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
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fnotification_readsadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fnotification_readsadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
<?php } ?>
    </div><!-- /buttons offset -->
<?= $Page->IsModal ? "</template>" : "</div>" ?><!-- /buttons .row -->
</form>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
// Field event handlers
loadjs.ready("head", function() {
    ew.addEventHandlers("notification_reads");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
