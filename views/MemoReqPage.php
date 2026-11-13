<?php

namespace PHPMaker2024\FIMS;

// Page object
$MemoReqPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/memo-req-page.php"); ?>

<?= GetDebugMessage() ?>
