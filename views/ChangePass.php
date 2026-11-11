<?php

namespace PHPMaker2024\FIMS;

// Page object
$ChangePass = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/change-pass.php"); ?>
<?= GetDebugMessage() ?>
