<?php

namespace PHPMaker2024\FIMS;

// Page object
$PurchaseOrder = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/po/page.php"); ?>

<?= GetDebugMessage() ?>
