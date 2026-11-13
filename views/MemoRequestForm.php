<?php

namespace PHPMaker2024\FIMS;

// Page object
$MemoRequestForm = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/form/memo-request-form.php"); ?>

<?= GetDebugMessage() ?>
