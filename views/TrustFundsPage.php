<?php

namespace PHPMaker2024\FIMS;

// Page object
$TrustFundsPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/trust-funds-page.php"); ?>
<?= GetDebugMessage() ?>
