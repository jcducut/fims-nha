<?php

namespace PHPMaker2024\FIMS;

// Page object
$PricingPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/pricing-page.php"); ?>
<?= GetDebugMessage() ?>
