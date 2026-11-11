<?php

namespace PHPMaker2024\FIMS;

// Page object
$BudgetReqPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/budget-req-page.php"); ?>

<?= GetDebugMessage() ?>
