<?php

namespace PHPMaker2024\FIMS;

// Page object
$BudgetRevReview = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/form/budget-rev-review.php"); ?>

<?= GetDebugMessage() ?>
