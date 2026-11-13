<?php

namespace PHPMaker2024\FIMS;

// Navbar menu
$topMenu = new Menu("navbar", true, true);
echo $topMenu->toScript();

// Sidebar menu
$sideMenu = new Menu("menu", true, false);
$sideMenu->addMenuItem(262, "mi_notification_reads", $Language->menuPhrase("262", "MenuText"), "NotificationReadsList", -1, "", AllowListMenu('{FIMS}notification_reads'), false, false, "", "", false, true);
$sideMenu->addMenuItem(9, "mi_MainDashboard", $Language->menuPhrase("9", "MenuText"), "MainDashboard", -1, "", AllowListMenu('{FIMS}MainDashboard.php'), false, false, "fas fa-chart-line", "", false, true);
$sideMenu->addMenuItem(146, "mci_Budget", $Language->menuPhrase("146", "MenuText"), "", -1, "", true, false, true, "fas fa-wallet", "", false, true);
$sideMenu->addMenuItem(88, "mi_memo_req_page", $Language->menuPhrase("88", "MenuText"), "MemoReqPage", 146, "", AllowListMenu('{FIMS}memo-req-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(81, "mi_budget_req_page", $Language->menuPhrase("81", "MenuText"), "BudgetReqPage", 146, "", AllowListMenu('{FIMS}budget-req-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(261, "mi_budget_prop_page", $Language->menuPhrase("261", "MenuText"), "BudgetPropPage", 146, "", AllowListMenu('{FIMS}budget-prop-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(85, "mi_budget_revision_page", $Language->menuPhrase("85", "MenuText"), "BudgetRevisionPage", 146, "", AllowListMenu('{FIMS}budget-revision-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(147, "mci_Programs_&_Projects_", $Language->menuPhrase("147", "MenuText"), "", -1, "", true, false, true, "fas fa-sitemap", "", false, true);
$sideMenu->addMenuItem(80, "mi_program_page", $Language->menuPhrase("80", "MenuText"), "ProgramPage", 147, "", AllowListMenu('{FIMS}program-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(82, "mi_sub_program_page", $Language->menuPhrase("82", "MenuText"), "SubProgramPage", 147, "", AllowListMenu('{FIMS}sub-program-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(79, "mi_project_page", $Language->menuPhrase("79", "MenuText"), "ProjectPage", 147, "", AllowListMenu('{FIMS}project-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(148, "mci_Accounting_Records", $Language->menuPhrase("148", "MenuText"), "", -1, "", true, false, true, "fas fa-book", "", false, true);
$sideMenu->addMenuItem(89, "mi_chart_of_accounts_page", $Language->menuPhrase("89", "MenuText"), "ChartOfAccountsPage", 148, "", AllowListMenu('{FIMS}chart-of-accounts-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(90, "mi_responsibility_center_page", $Language->menuPhrase("90", "MenuText"), "ResponsibilityCenterPage", 148, "", AllowListMenu('{FIMS}responsibility-center-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(391, "mi_vendors_page", $Language->menuPhrase("391", "MenuText"), "VendorsPage", 148, "", AllowListMenu('{FIMS}vendors-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(390, "mci_Inventory", $Language->menuPhrase("390", "MenuText"), "", -1, "", true, false, true, "fas fa-clipboard-check", "", false, true);
$sideMenu->addMenuItem(91, "mi_IARModule", $Language->menuPhrase("91", "MenuText"), "IarModule", 390, "", AllowListMenu('{FIMS}IARModule.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(333, "mi_property_record_page", $Language->menuPhrase("333", "MenuText"), "PropertyRecordPage", 390, "", AllowListMenu('{FIMS}property-record-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(258, "mci_Vouchers", $Language->menuPhrase("258", "MenuText"), "", -1, "", true, false, true, "fas fa-file-invoice-dollar", "", false, true);
$sideMenu->addMenuItem(208, "mi_JournalEntryVoucher", $Language->menuPhrase("208", "MenuText"), "JournalEntryVoucher", 258, "", AllowListMenu('{FIMS}JournalEntryVoucher.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(207, "mi_Voucher", $Language->menuPhrase("207", "MenuText"), "Voucher", 258, "", AllowListMenu('{FIMS}Voucher.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(260, "mi_CheckDisbursements", $Language->menuPhrase("260", "MenuText"), "CheckDisbursements", 258, "", AllowListMenu('{FIMS}CheckDisbursements.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(332, "mi_bank_management_page", $Language->menuPhrase("332", "MenuText"), "BankManagementPage", -1, "", AllowListMenu('{FIMS}bank-management-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(331, "mi_cash_position_page", $Language->menuPhrase("331", "MenuText"), "CashPositionPage", -1, "", AllowListMenu('{FIMS}cash-position-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(330, "mci_Fund_Sourcing", $Language->menuPhrase("330", "MenuText"), "", -1, "", true, false, true, "fas fa-hand-holding-dollar", "", false, true);
$sideMenu->addMenuItem(263, "mi_saro_registry_page", $Language->menuPhrase("263", "MenuText"), "SaroRegistryPage", 330, "", AllowListMenu('{FIMS}saro-registry-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(264, "mi_supplemental_funds_page", $Language->menuPhrase("264", "MenuText"), "SupplementalFundsPage", 330, "", AllowListMenu('{FIMS}supplemental-funds-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(265, "mi_trust_funds_page", $Language->menuPhrase("265", "MenuText"), "TrustFundsPage", 330, "", AllowListMenu('{FIMS}trust-funds-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(266, "mi_pricing_page", $Language->menuPhrase("266", "MenuText"), "PricingPage", 330, "", AllowListMenu('{FIMS}pricing-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(206, "mci_Administration", $Language->menuPhrase("206", "MenuText"), "", -1, "", true, false, true, "fas fa-user-cog", "", false, true);
$sideMenu->addMenuItem(70, "mi_PositionManagement", $Language->menuPhrase("70", "MenuText"), "PositionManagement", 206, "", AllowListMenu('{FIMS}PositionManagement.php'), false, false, "fas fa-briefcase", "", false, true);
$sideMenu->addMenuItem(13, "mi_UserAccess", $Language->menuPhrase("13", "MenuText"), "UserAccess", 206, "", AllowListMenu('{FIMS}UserAccess.php'), false, false, "fas fa-user-lock", "", false, true);
$sideMenu->addMenuItem(12, "mi_UserManagement", $Language->menuPhrase("12", "MenuText"), "UserManagement", 206, "", AllowListMenu('{FIMS}UserManagement.php'), false, false, "fas fa-user-shield", "", false, true);
$sideMenu->addMenuItem(69, "mi_DepartmentManagement", $Language->menuPhrase("69", "MenuText"), "DepartmentManagement", 206, "", AllowListMenu('{FIMS}DepartmentManagement.php'), false, false, "fas fa-building", "", false, true);
$sideMenu->addMenuItem(259, "mi_ChangePass", $Language->menuPhrase("259", "MenuText"), "ChangePass", 206, "", AllowListMenu('{FIMS}ChangePass.php'), false, false, "", "", false, true);
echo $sideMenu->toScript();
