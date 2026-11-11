<?php

namespace PHPMaker2024\FIMS;

// Page object
$BudgetRevForm = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/form/budget-rev-form.php"); ?>

<?= GetDebugMessage() ?>
