<?php

namespace PHPMaker2024\FIMS;

// Page object
$BankAccountManagementPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/bank-position-page.php"); ?>

<?= GetDebugMessage() ?>
