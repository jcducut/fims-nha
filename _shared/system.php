<?php
// _shared/system.php - List of the GovFlow360 Systems
global $SYSTEM;
$SYSTEM = [
    // This project's own login sets FIMS_Status_UserName/FIMS_Status_UserID
    // (PROJECT_NAME = "FIMS" in src/constants.php) — without an entry here,
    // isAnyUserLoggedIn() (used by app/pages/login.php, change-pass.php) can
    // never match those keys and always reports "not logged in", regardless
    // of actual login state.
    "FIMS" => [
        "TITLE" => "National Housing Authority - Financial Information Management System",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/finance.png",
    ],
    "UAC" => [
        "TITLE" => "National Housing Authority - User Access Control",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/control.png",
    ],
    "FMD" => [
        "TITLE" => "National Housing Authority - Finance & Management System",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/finance.png",
    ],    
     "ACCOUNTING-GAD" => [
        "TITLE" => "National Housing Authority - General Accounting Division",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/finance.png",
    ],
     "ACCOUNTING-BAPD" => [
        "TITLE" => "National Housing Authority - Branch Accounting Property Division",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/finance.png",
    ],
      "ACCOUNTING-PCDS" => [
        "TITLE" => "National Housing Authority - Branch Accounting Property Division",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/finance.png",
    ],
     "ACCOUNTING-DCD" => [
        "TITLE" => "National Housing Authority - Disbursement Control Division",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/finance.png",
    ],
    "TREASURY" => [
        "TITLE" => "National Housing Authority - Treasury System",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/finance.png",
    ],
    "REGIONAL" => [
        "TITLE" => "National Housing Authority - Regional & District System",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/finance.png",
    ],
    "GSD" => [
        "TITLE" => "National Housing Authority - General Services Department",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/finance.png",
    ],
    "DOCUMENT" => [
        "TITLE" => "National Housing Authority - Document Management",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/document.png",
    ],
    // No dedicated LIBIS icon yet - generic placeholder so the app switcher
    // doesn't show a broken image (it fell back to the missing icon/libis.png)
    "LIBIS" => [
        "TITLE" => "National Housing Authority - LIBIS Integration",
        "VERSION" => "1.0.0",
        "IMAGE" => "icon/control.png",
    ],
];
return $SYSTEM;
?>