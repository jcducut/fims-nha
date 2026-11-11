<?php

namespace PHPMaker2024\FIMS;

// Page object
$CobPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/cob-page.php"); ?>

<?= GetDebugMessage() ?>
