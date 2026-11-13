<?php

namespace PHPMaker2024\FIMS;

// Page object
$VendorsPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/vendors-page.php"); ?>

<?= GetDebugMessage() ?>
