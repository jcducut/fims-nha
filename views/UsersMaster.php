<?php

namespace PHPMaker2024\FIMS;

// Table
$users = Container("users");
$users->TableClass = "table table-bordered table-hover table-sm ew-table ew-master-table";
?>
<?php if ($users->Visible) { ?>
<div class="ew-master-div">
<table id="tbl_usersmaster" class="table ew-view-table ew-master-table ew-vertical">
    <tbody>
<?php if ($users->user_id->Visible) { // user_id ?>
        <tr id="r_user_id"<?= $users->user_id->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->user_id->caption() ?></td>
            <td<?= $users->user_id->cellAttributes() ?>>
<span id="el_users_user_id">
<span<?= $users->user_id->viewAttributes() ?>>
<?= $users->user_id->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->_username->Visible) { // username ?>
        <tr id="r__username"<?= $users->_username->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->_username->caption() ?></td>
            <td<?= $users->_username->cellAttributes() ?>>
<span id="el_users__username">
<span<?= $users->_username->viewAttributes() ?>>
<?= $users->_username->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->_email->Visible) { // email ?>
        <tr id="r__email"<?= $users->_email->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->_email->caption() ?></td>
            <td<?= $users->_email->cellAttributes() ?>>
<span id="el_users__email">
<span<?= $users->_email->viewAttributes() ?>>
<?= $users->_email->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->first_name->Visible) { // first_name ?>
        <tr id="r_first_name"<?= $users->first_name->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->first_name->caption() ?></td>
            <td<?= $users->first_name->cellAttributes() ?>>
<span id="el_users_first_name">
<span<?= $users->first_name->viewAttributes() ?>>
<?= $users->first_name->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->last_name->Visible) { // last_name ?>
        <tr id="r_last_name"<?= $users->last_name->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->last_name->caption() ?></td>
            <td<?= $users->last_name->cellAttributes() ?>>
<span id="el_users_last_name">
<span<?= $users->last_name->viewAttributes() ?>>
<?= $users->last_name->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->suffix->Visible) { // suffix ?>
        <tr id="r_suffix"<?= $users->suffix->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->suffix->caption() ?></td>
            <td<?= $users->suffix->cellAttributes() ?>>
<span id="el_users_suffix">
<span<?= $users->suffix->viewAttributes() ?>>
<?= $users->suffix->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->department_id->Visible) { // department_id ?>
        <tr id="r_department_id"<?= $users->department_id->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->department_id->caption() ?></td>
            <td<?= $users->department_id->cellAttributes() ?>>
<span id="el_users_department_id">
<span<?= $users->department_id->viewAttributes() ?>>
<?= $users->department_id->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->is_active->Visible) { // is_active ?>
        <tr id="r_is_active"<?= $users->is_active->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->is_active->caption() ?></td>
            <td<?= $users->is_active->cellAttributes() ?>>
<span id="el_users_is_active">
<span<?= $users->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($users->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->user_level_id->Visible) { // user_level_id ?>
        <tr id="r_user_level_id"<?= $users->user_level_id->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->user_level_id->caption() ?></td>
            <td<?= $users->user_level_id->cellAttributes() ?>>
<span id="el_users_user_level_id">
<span<?= $users->user_level_id->viewAttributes() ?>>
<?= $users->user_level_id->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->position_id->Visible) { // position_id ?>
        <tr id="r_position_id"<?= $users->position_id->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->position_id->caption() ?></td>
            <td<?= $users->position_id->cellAttributes() ?>>
<span id="el_users_position_id">
<span<?= $users->position_id->viewAttributes() ?>>
<?= $users->position_id->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->employee_id->Visible) { // employee_id ?>
        <tr id="r_employee_id"<?= $users->employee_id->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->employee_id->caption() ?></td>
            <td<?= $users->employee_id->cellAttributes() ?>>
<span id="el_users_employee_id">
<span<?= $users->employee_id->viewAttributes() ?>>
<?= $users->employee_id->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->must_change_password->Visible) { // must_change_password ?>
        <tr id="r_must_change_password"<?= $users->must_change_password->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->must_change_password->caption() ?></td>
            <td<?= $users->must_change_password->cellAttributes() ?>>
<span id="el_users_must_change_password">
<span<?= $users->must_change_password->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($users->must_change_password->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($users->rc_id->Visible) { // rc_id ?>
        <tr id="r_rc_id"<?= $users->rc_id->rowAttributes() ?>>
            <td class="<?= $users->TableLeftColumnClass ?>"><?= $users->rc_id->caption() ?></td>
            <td<?= $users->rc_id->cellAttributes() ?>>
<span id="el_users_rc_id">
<span<?= $users->rc_id->viewAttributes() ?>>
<?= $users->rc_id->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
    </tbody>
</table>
</div>
<?php } ?>
