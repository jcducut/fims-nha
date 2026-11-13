<?php

namespace PHPMaker2024\FIMS;

// Page object
$SaroRegistryPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/saro-registry-page.php"); ?>
<?= GetDebugMessage() ?>
