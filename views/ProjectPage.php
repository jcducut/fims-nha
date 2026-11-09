<?php

namespace PHPMaker2024\FIMS;

// Page object
$ProjectPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/project-page.php"); ?>

<?= GetDebugMessage() ?>
