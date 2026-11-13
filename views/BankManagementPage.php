<?php

namespace PHPMaker2024\FIMS;

// Page object
$BankManagementPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/bank-management-page.php"); ?>

<?= GetDebugMessage() ?>
