<?php

namespace PHPMaker2024\FIMS;

// Page object
$JournalEntryVoucher = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/journal-entry-voucher.php"); ?>
<?= GetDebugMessage() ?>
