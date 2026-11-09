<?php

namespace PHPMaker2024\FIMS;

// Page object
$UsersEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fusersedit" id="fusersedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { users: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fusersedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fusersedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["user_id", [fields.user_id.visible && fields.user_id.required ? ew.Validators.required(fields.user_id.caption) : null], fields.user_id.isInvalid],
            ["_username", [fields._username.visible && fields._username.required ? ew.Validators.required(fields._username.caption) : null], fields._username.isInvalid],
            ["_email", [fields._email.visible && fields._email.required ? ew.Validators.required(fields._email.caption) : null], fields._email.isInvalid],
            ["password_hash", [fields.password_hash.visible && fields.password_hash.required ? ew.Validators.required(fields.password_hash.caption) : null], fields.password_hash.isInvalid],
            ["mobile_number", [fields.mobile_number.visible && fields.mobile_number.required ? ew.Validators.required(fields.mobile_number.caption) : null], fields.mobile_number.isInvalid],
            ["first_name", [fields.first_name.visible && fields.first_name.required ? ew.Validators.required(fields.first_name.caption) : null], fields.first_name.isInvalid],
            ["middle_name", [fields.middle_name.visible && fields.middle_name.required ? ew.Validators.required(fields.middle_name.caption) : null], fields.middle_name.isInvalid],
            ["last_name", [fields.last_name.visible && fields.last_name.required ? ew.Validators.required(fields.last_name.caption) : null], fields.last_name.isInvalid],
            ["suffix", [fields.suffix.visible && fields.suffix.required ? ew.Validators.required(fields.suffix.caption) : null], fields.suffix.isInvalid],
            ["department_id", [fields.department_id.visible && fields.department_id.required ? ew.Validators.required(fields.department_id.caption) : null, ew.Validators.integer], fields.department_id.isInvalid],
            ["date_created", [fields.date_created.visible && fields.date_created.required ? ew.Validators.required(fields.date_created.caption) : null], fields.date_created.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["reports_to_user_id", [fields.reports_to_user_id.visible && fields.reports_to_user_id.required ? ew.Validators.required(fields.reports_to_user_id.caption) : null, ew.Validators.integer], fields.reports_to_user_id.isInvalid],
            ["photo", [fields.photo.visible && fields.photo.required ? ew.Validators.fileRequired(fields.photo.caption) : null], fields.photo.isInvalid],
            ["_profile", [fields._profile.visible && fields._profile.required ? ew.Validators.required(fields._profile.caption) : null], fields._profile.isInvalid],
            ["position_id", [fields.position_id.visible && fields.position_id.required ? ew.Validators.required(fields.position_id.caption) : null, ew.Validators.integer], fields.position_id.isInvalid],
            ["employee_id", [fields.employee_id.visible && fields.employee_id.required ? ew.Validators.required(fields.employee_id.caption) : null], fields.employee_id.isInvalid],
            ["must_change_password", [fields.must_change_password.visible && fields.must_change_password.required ? ew.Validators.required(fields.must_change_password.caption) : null], fields.must_change_password.isInvalid],
            ["rc_id", [fields.rc_id.visible && fields.rc_id.required ? ew.Validators.required(fields.rc_id.caption) : null], fields.rc_id.isInvalid]
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
            "department_id": <?= $Page->department_id->toClientList($Page) ?>,
            "is_active": <?= $Page->is_active->toClientList($Page) ?>,
            "must_change_password": <?= $Page->must_change_password->toClientList($Page) ?>,
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
<input type="hidden" name="t" value="users">
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
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->user_id->Visible) { // user_id ?>
    <div id="r_user_id"<?= $Page->user_id->rowAttributes() ?>>
        <label id="elh_users_user_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_id->caption() ?><?= $Page->user_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_id->cellAttributes() ?>>
<span id="el_users_user_id">
<span<?= $Page->user_id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Page->user_id->getDisplayValue($Page->user_id->EditValue))) ?>"></span>
<input type="hidden" data-table="users" data-field="x_user_id" data-hidden="1" name="x_user_id" id="x_user_id" value="<?= HtmlEncode($Page->user_id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->_username->Visible) { // username ?>
    <div id="r__username"<?= $Page->_username->rowAttributes() ?>>
        <label id="elh_users__username" for="x__username" class="<?= $Page->LeftColumnClass ?>"><?= $Page->_username->caption() ?><?= $Page->_username->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->_username->cellAttributes() ?>>
<span id="el_users__username">
<input type="<?= $Page->_username->getInputTextType() ?>" name="x__username" id="x__username" data-table="users" data-field="x__username" value="<?= $Page->_username->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->_username->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->_username->formatPattern()) ?>"<?= $Page->_username->editAttributes() ?> aria-describedby="x__username_help">
<?= $Page->_username->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->_username->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->_email->Visible) { // email ?>
    <div id="r__email"<?= $Page->_email->rowAttributes() ?>>
        <label id="elh_users__email" for="x__email" class="<?= $Page->LeftColumnClass ?>"><?= $Page->_email->caption() ?><?= $Page->_email->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->_email->cellAttributes() ?>>
<span id="el_users__email">
<input type="<?= $Page->_email->getInputTextType() ?>" name="x__email" id="x__email" data-table="users" data-field="x__email" value="<?= $Page->_email->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->_email->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->_email->formatPattern()) ?>"<?= $Page->_email->editAttributes() ?> aria-describedby="x__email_help">
<?= $Page->_email->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->_email->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->password_hash->Visible) { // password_hash ?>
    <div id="r_password_hash"<?= $Page->password_hash->rowAttributes() ?>>
        <label id="elh_users_password_hash" for="x_password_hash" class="<?= $Page->LeftColumnClass ?>"><?= $Page->password_hash->caption() ?><?= $Page->password_hash->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->password_hash->cellAttributes() ?>>
<span id="el_users_password_hash">
<div class="input-group">
    <input type="password" name="x_password_hash" id="x_password_hash" autocomplete="new-password" data-table="users" data-field="x_password_hash" value="<?= $Page->password_hash->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->password_hash->getPlaceHolder()) ?>"<?= $Page->password_hash->editAttributes() ?> aria-describedby="x_password_hash_help">
    <button type="button" class="btn btn-default ew-toggle-password rounded-end" data-ew-action="password"><i class="fa-solid fa-eye"></i></button>
</div>
<?= $Page->password_hash->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->password_hash->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->mobile_number->Visible) { // mobile_number ?>
    <div id="r_mobile_number"<?= $Page->mobile_number->rowAttributes() ?>>
        <label id="elh_users_mobile_number" for="x_mobile_number" class="<?= $Page->LeftColumnClass ?>"><?= $Page->mobile_number->caption() ?><?= $Page->mobile_number->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->mobile_number->cellAttributes() ?>>
<span id="el_users_mobile_number">
<input type="<?= $Page->mobile_number->getInputTextType() ?>" name="x_mobile_number" id="x_mobile_number" data-table="users" data-field="x_mobile_number" value="<?= $Page->mobile_number->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->mobile_number->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->mobile_number->formatPattern()) ?>"<?= $Page->mobile_number->editAttributes() ?> aria-describedby="x_mobile_number_help">
<?= $Page->mobile_number->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->mobile_number->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->first_name->Visible) { // first_name ?>
    <div id="r_first_name"<?= $Page->first_name->rowAttributes() ?>>
        <label id="elh_users_first_name" for="x_first_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->first_name->caption() ?><?= $Page->first_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->first_name->cellAttributes() ?>>
<span id="el_users_first_name">
<input type="<?= $Page->first_name->getInputTextType() ?>" name="x_first_name" id="x_first_name" data-table="users" data-field="x_first_name" value="<?= $Page->first_name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->first_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->first_name->formatPattern()) ?>"<?= $Page->first_name->editAttributes() ?> aria-describedby="x_first_name_help">
<?= $Page->first_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->first_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->middle_name->Visible) { // middle_name ?>
    <div id="r_middle_name"<?= $Page->middle_name->rowAttributes() ?>>
        <label id="elh_users_middle_name" for="x_middle_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->middle_name->caption() ?><?= $Page->middle_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->middle_name->cellAttributes() ?>>
<span id="el_users_middle_name">
<input type="<?= $Page->middle_name->getInputTextType() ?>" name="x_middle_name" id="x_middle_name" data-table="users" data-field="x_middle_name" value="<?= $Page->middle_name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->middle_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->middle_name->formatPattern()) ?>"<?= $Page->middle_name->editAttributes() ?> aria-describedby="x_middle_name_help">
<?= $Page->middle_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->middle_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->last_name->Visible) { // last_name ?>
    <div id="r_last_name"<?= $Page->last_name->rowAttributes() ?>>
        <label id="elh_users_last_name" for="x_last_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->last_name->caption() ?><?= $Page->last_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->last_name->cellAttributes() ?>>
<span id="el_users_last_name">
<input type="<?= $Page->last_name->getInputTextType() ?>" name="x_last_name" id="x_last_name" data-table="users" data-field="x_last_name" value="<?= $Page->last_name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->last_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->last_name->formatPattern()) ?>"<?= $Page->last_name->editAttributes() ?> aria-describedby="x_last_name_help">
<?= $Page->last_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->last_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->suffix->Visible) { // suffix ?>
    <div id="r_suffix"<?= $Page->suffix->rowAttributes() ?>>
        <label id="elh_users_suffix" for="x_suffix" class="<?= $Page->LeftColumnClass ?>"><?= $Page->suffix->caption() ?><?= $Page->suffix->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->suffix->cellAttributes() ?>>
<span id="el_users_suffix">
<input type="<?= $Page->suffix->getInputTextType() ?>" name="x_suffix" id="x_suffix" data-table="users" data-field="x_suffix" value="<?= $Page->suffix->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->suffix->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->suffix->formatPattern()) ?>"<?= $Page->suffix->editAttributes() ?> aria-describedby="x_suffix_help">
<?= $Page->suffix->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->suffix->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->department_id->Visible) { // department_id ?>
    <div id="r_department_id"<?= $Page->department_id->rowAttributes() ?>>
        <label id="elh_users_department_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->department_id->caption() ?><?= $Page->department_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->department_id->cellAttributes() ?>>
<span id="el_users_department_id">
<?php
if (IsRTL()) {
    $Page->department_id->EditAttrs["dir"] = "rtl";
}
?>
<span id="as_x_department_id" class="ew-auto-suggest">
    <input type="<?= $Page->department_id->getInputTextType() ?>" class="form-control" name="sv_x_department_id" id="sv_x_department_id" value="<?= RemoveHtml($Page->department_id->EditValue) ?>" autocomplete="off" size="30" placeholder="<?= HtmlEncode($Page->department_id->getPlaceHolder()) ?>" data-placeholder="<?= HtmlEncode($Page->department_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->department_id->formatPattern()) ?>"<?= $Page->department_id->editAttributes() ?> aria-describedby="x_department_id_help">
</span>
<selection-list hidden class="form-control" data-table="users" data-field="x_department_id" data-input="sv_x_department_id" data-value-separator="<?= $Page->department_id->displayValueSeparatorAttribute() ?>" name="x_department_id" id="x_department_id" value="<?= HtmlEncode($Page->department_id->CurrentValue) ?>"></selection-list>
<?= $Page->department_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->department_id->getErrorMessage() ?></div>
<script>
loadjs.ready("fusersedit", function() {
    fusersedit.createAutoSuggest(Object.assign({"id":"x_department_id","forceSelect":false}, { lookupAllDisplayFields: <?= $Page->department_id->Lookup->LookupAllDisplayFields ? "true" : "false" ?> }, ew.vars.tables.users.fields.department_id.autoSuggestOptions));
});
</script>
<?= $Page->department_id->Lookup->getParamTag($Page, "p_x_department_id") ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_users_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_users_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="users" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->reports_to_user_id->Visible) { // reports_to_user_id ?>
    <div id="r_reports_to_user_id"<?= $Page->reports_to_user_id->rowAttributes() ?>>
        <label id="elh_users_reports_to_user_id" for="x_reports_to_user_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->reports_to_user_id->caption() ?><?= $Page->reports_to_user_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->reports_to_user_id->cellAttributes() ?>>
<?php if (!$Security->isAdmin() && $Security->isLoggedIn()) { // Non system admin ?>
<?php if (SameString($Page->user_id->CurrentValue, CurrentUserID())) { ?>
    <span<?= $Page->reports_to_user_id->viewAttributes() ?>>
    <input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Page->reports_to_user_id->getDisplayValue($Page->reports_to_user_id->EditValue))) ?>"></span>
    <input type="hidden" data-table="users" data-field="x_reports_to_user_id" data-hidden="1" name="x_reports_to_user_id" id="x_reports_to_user_id" value="<?= HtmlEncode($Page->reports_to_user_id->CurrentValue) ?>">
<?php } else { ?>
<span id="el_users_reports_to_user_id">
    <select
        id="x_reports_to_user_id"
        name="x_reports_to_user_id"
        class="form-select ew-select<?= $Page->reports_to_user_id->isInvalidClass() ?>"
        <?php if (!$Page->reports_to_user_id->IsNativeSelect) { ?>
        data-select2-id="fusersedit_x_reports_to_user_id"
        <?php } ?>
        data-table="users"
        data-field="x_reports_to_user_id"
        data-value-separator="<?= $Page->reports_to_user_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Page->reports_to_user_id->getPlaceHolder()) ?>"
        <?= $Page->reports_to_user_id->editAttributes() ?>>
        <?= $Page->reports_to_user_id->selectOptionListHtml("x_reports_to_user_id") ?>
    </select>
    <?= $Page->reports_to_user_id->getCustomMessage() ?>
    <div class="invalid-feedback"><?= $Page->reports_to_user_id->getErrorMessage() ?></div>
<?php if (!$Page->reports_to_user_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fusersedit", function() {
    var options = { name: "x_reports_to_user_id", selectId: "fusersedit_x_reports_to_user_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fusersedit.lists.reports_to_user_id?.lookupOptions.length) {
        options.data = { id: "x_reports_to_user_id", form: "fusersedit" };
    } else {
        options.ajax = { id: "x_reports_to_user_id", form: "fusersedit", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.users.fields.reports_to_user_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
<?php } else { ?>
<span id="el_users_reports_to_user_id">
<input type="<?= $Page->reports_to_user_id->getInputTextType() ?>" name="x_reports_to_user_id" id="x_reports_to_user_id" data-table="users" data-field="x_reports_to_user_id" value="<?= $Page->reports_to_user_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->reports_to_user_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->reports_to_user_id->formatPattern()) ?>"<?= $Page->reports_to_user_id->editAttributes() ?> aria-describedby="x_reports_to_user_id_help">
<?= $Page->reports_to_user_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->reports_to_user_id->getErrorMessage() ?></div>
</span>
<?php } ?>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->photo->Visible) { // photo ?>
    <div id="r_photo"<?= $Page->photo->rowAttributes() ?>>
        <label id="elh_users_photo" class="<?= $Page->LeftColumnClass ?>"><?= $Page->photo->caption() ?><?= $Page->photo->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->photo->cellAttributes() ?>>
<span id="el_users_photo">
<div id="fd_x_photo" class="fileinput-button ew-file-drop-zone">
    <input
        type="file"
        id="x_photo"
        name="x_photo"
        class="form-control ew-file-input"
        title="<?= $Page->photo->title() ?>"
        lang="<?= CurrentLanguageID() ?>"
        data-table="users"
        data-field="x_photo"
        data-size="255"
        data-accept-file-types="<?= $Page->photo->acceptFileTypes() ?>"
        data-max-file-size="<?= $Page->photo->UploadMaxFileSize ?>"
        data-max-number-of-files="null"
        data-disable-image-crop="<?= $Page->photo->ImageCropper ? 0 : 1 ?>"
        aria-describedby="x_photo_help"
        <?= ($Page->photo->ReadOnly || $Page->photo->Disabled) ? " disabled" : "" ?>
        <?= $Page->photo->editAttributes() ?>
    >
    <div class="text-body-secondary ew-file-text"><?= $Language->phrase("ChooseFile") ?></div>
    <?= $Page->photo->getCustomMessage() ?>
    <div class="invalid-feedback"><?= $Page->photo->getErrorMessage() ?></div>
</div>
<input type="hidden" name="fn_x_photo" id= "fn_x_photo" value="<?= $Page->photo->Upload->FileName ?>">
<input type="hidden" name="fa_x_photo" id= "fa_x_photo" value="<?= (Post("fa_x_photo") == "0") ? "0" : "1" ?>">
<table id="ft_x_photo" class="table table-sm float-start ew-upload-table"><tbody class="files"></tbody></table>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->position_id->Visible) { // position_id ?>
    <div id="r_position_id"<?= $Page->position_id->rowAttributes() ?>>
        <label id="elh_users_position_id" for="x_position_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->position_id->caption() ?><?= $Page->position_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->position_id->cellAttributes() ?>>
<span id="el_users_position_id">
<input type="<?= $Page->position_id->getInputTextType() ?>" name="x_position_id" id="x_position_id" data-table="users" data-field="x_position_id" value="<?= $Page->position_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->position_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->position_id->formatPattern()) ?>"<?= $Page->position_id->editAttributes() ?> aria-describedby="x_position_id_help">
<?= $Page->position_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->position_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->employee_id->Visible) { // employee_id ?>
    <div id="r_employee_id"<?= $Page->employee_id->rowAttributes() ?>>
        <label id="elh_users_employee_id" for="x_employee_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->employee_id->caption() ?><?= $Page->employee_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->employee_id->cellAttributes() ?>>
<span id="el_users_employee_id">
<input type="<?= $Page->employee_id->getInputTextType() ?>" name="x_employee_id" id="x_employee_id" data-table="users" data-field="x_employee_id" value="<?= $Page->employee_id->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->employee_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->employee_id->formatPattern()) ?>"<?= $Page->employee_id->editAttributes() ?> aria-describedby="x_employee_id_help">
<?= $Page->employee_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->employee_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->must_change_password->Visible) { // must_change_password ?>
    <div id="r_must_change_password"<?= $Page->must_change_password->rowAttributes() ?>>
        <label id="elh_users_must_change_password" class="<?= $Page->LeftColumnClass ?>"><?= $Page->must_change_password->caption() ?><?= $Page->must_change_password->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->must_change_password->cellAttributes() ?>>
<span id="el_users_must_change_password">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->must_change_password->isInvalidClass() ?>" data-table="users" data-field="x_must_change_password" data-boolean name="x_must_change_password" id="x_must_change_password" value="1"<?= ConvertToBool($Page->must_change_password->CurrentValue) ? " checked" : "" ?><?= $Page->must_change_password->editAttributes() ?> aria-describedby="x_must_change_password_help">
    <div class="invalid-feedback"><?= $Page->must_change_password->getErrorMessage() ?></div>
</div>
<?= $Page->must_change_password->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
    <div id="r_rc_id"<?= $Page->rc_id->rowAttributes() ?>>
        <label id="elh_users_rc_id" for="x_rc_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->rc_id->caption() ?><?= $Page->rc_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->rc_id->cellAttributes() ?>>
<span id="el_users_rc_id">
<input type="<?= $Page->rc_id->getInputTextType() ?>" name="x_rc_id" id="x_rc_id" data-table="users" data-field="x_rc_id" value="<?= $Page->rc_id->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->rc_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->rc_id->formatPattern()) ?>"<?= $Page->rc_id->editAttributes() ?> aria-describedby="x_rc_id_help">
<?= $Page->rc_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->rc_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<span id="el_users__profile">
<input type="hidden" data-table="users" data-field="x__profile" data-hidden="1" name="x__profile" id="x__profile" value="<?= HtmlEncode($Page->_profile->CurrentValue) ?>">
</span>
<?php
    if (in_array("user_level_assignments", explode(",", $Page->getCurrentDetailTable())) && $user_level_assignments->DetailEdit) {
?>
<?php if ($Page->getCurrentDetailTable() != "") { ?>
<h4 class="ew-detail-caption"><?= $Language->tablePhrase("user_level_assignments", "TblCaption") ?></h4>
<?php } ?>
<?php include_once "UserLevelAssignmentsGrid.php" ?>
<?php } ?>
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fusersedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fusersedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("users");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
