<?php

namespace PHPMaker2024\FIMS;

// Page object
$ProgramPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/program-page.php"); ?>

<?= GetDebugMessage() ?>
