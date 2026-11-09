<?php

namespace PHPMaker2024\FIMS;

// Page object
$BudgetRevisionPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/budget-revision-page.php"); ?>

<?= GetDebugMessage() ?>
