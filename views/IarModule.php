<?php

namespace PHPMaker2024\FIMS;

// Page object
$IarModule = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/iar-module.php"); ?>

<?= GetDebugMessage() ?>
