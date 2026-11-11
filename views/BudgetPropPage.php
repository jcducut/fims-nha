<?php

namespace PHPMaker2024\FIMS;

// Page object
$BudgetPropPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/budget-prop-page.php"); ?>
<?= GetDebugMessage() ?>
