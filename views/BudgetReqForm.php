<?php

namespace PHPMaker2024\FIMS;

// Page object
$BudgetReqForm = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/form/budget-req-form.php"); ?>

<?= GetDebugMessage() ?>
