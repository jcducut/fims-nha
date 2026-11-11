<?php

namespace PHPMaker2024\FIMS;

// Page object
$CheckDisbursements = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/check-disbursement-voucher.php"); ?>
<?= GetDebugMessage() ?>
