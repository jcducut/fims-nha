<?php

namespace PHPMaker2024\FIMS;

// Page object
$ChangePassword2 = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/change-password.php"); ?>
<?= GetDebugMessage() ?>
