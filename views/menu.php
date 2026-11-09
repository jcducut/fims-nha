<?php

namespace PHPMaker2024\FIMS;

// ── Custom page-name overrides (set via User Access > Permissions Management > rename) ──
// Keyed by the page-key used below (4th addMenuItem arg), via each
// $USER_LEVEL_TABLES row's [0]=table_name (filename) / [5]=page key mapping.
// See _assets/sql/00 - uac/02 - alter_table/04 - permission_module_captions.sql
$menuCaptionOverrides = [];
try {
    $registryTables = $GLOBALS["USER_LEVEL_TABLES"] ?? [];
    $pageKeyToTableName = [];
    foreach ($registryTables as $table) {
        if (!empty($table[5])) {
            $pageKeyToTableName[$table[5]] = $table[0];
        }
    }
    $overrideRows = ExecuteRowsAssociative("SELECT table_name, caption FROM permission_module_captions", "DB");
    foreach ($overrideRows as $row) {
        foreach ($pageKeyToTableName as $pageKey => $tableName) {
            if ($tableName === $row['table_name']) {
                $menuCaptionOverrides[$pageKey] = $row['caption'];
            }
        }
    }
} catch (\Exception $e) {
    // Table not created yet (migration not run) - fall back to default captions only.
    $menuCaptionOverrides = [];
}

// Returns the custom caption for $pageKey when one was set, else the default language phrase.
function menuCaption($menuCaptionOverrides, $id, $pageKey, $Language)
{
    return $menuCaptionOverrides[$pageKey] ?? $Language->menuPhrase($id, "MenuText");
}

// Navbar menu
$topMenu = new Menu("navbar", true, true);
echo $topMenu->toScript();

// Sidebar menu
$sideMenu = new Menu("menu", true, false);
$sideMenu->addMenuItem(9, "mi_MainDashboard", menuCaption($menuCaptionOverrides, "9", "MainDashboard", $Language), "MainDashboard", -1, "", AllowListMenu('{FIMS}MainDashboard.php'), false, false, "fas fa-chart-line", "", false, true);
$sideMenu->addMenuItem(146, "mci_Budget", $Language->menuPhrase("146", "MenuText"), "", -1, "", true, false, true, "fas fa-wallet", "", false, true);
$sideMenu->addMenuItem(88, "mi_budget_allocation_form", menuCaption($menuCaptionOverrides, "88", "BudgetAllocationForm", $Language), "BudgetAllocationForm", 146, "", AllowListMenu('{FIMS}budget-allocation-form.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(81, "mi_budget_req_page", menuCaption($menuCaptionOverrides, "81", "BudgetReqPage", $Language), "BudgetReqPage", 146, "", AllowListMenu('{FIMS}budget-req-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(85, "mi_budget_revision_page", menuCaption($menuCaptionOverrides, "85", "BudgetRevisionPage", $Language), "BudgetRevisionPage", 146, "", AllowListMenu('{FIMS}budget-revision-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(147, "mci_Programs_&_Projects_", $Language->menuPhrase("147", "MenuText"), "", -1, "", true, false, true, "fas fa-sitemap", "", false, true);
$sideMenu->addMenuItem(80, "mi_program_page", menuCaption($menuCaptionOverrides, "80", "ProgramPage", $Language), "ProgramPage", 147, "", AllowListMenu('{FIMS}program-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(82, "mi_sub_program_page", menuCaption($menuCaptionOverrides, "82", "SubProgramPage", $Language), "SubProgramPage", 147, "", AllowListMenu('{FIMS}sub-program-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(79, "mi_project_page", menuCaption($menuCaptionOverrides, "79", "ProjectPage", $Language), "ProjectPage", 147, "", AllowListMenu('{FIMS}project-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(148, "mci_Accounting_Records", $Language->menuPhrase("148", "MenuText"), "", -1, "", true, false, true, "fas fa-book", "", false, true);
$sideMenu->addMenuItem(89, "mi_chart_of_accounts_page", menuCaption($menuCaptionOverrides, "89", "ChartOfAccountsPage", $Language), "ChartOfAccountsPage", 148, "", AllowListMenu('{FIMS}chart-of-accounts-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(90, "mi_responsibility_center_page", menuCaption($menuCaptionOverrides, "90", "ResponsibilityCenterPage", $Language), "ResponsibilityCenterPage", 148, "", AllowListMenu('{FIMS}responsibility-center-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(91, "mi_AIRModule", menuCaption($menuCaptionOverrides, "91", "AirModule", $Language), "AirModule", -1, "", AllowListMenu('{FIMS}AIRModule.php'), false, false, "fas fa-clipboard-check", "", false, true);
$sideMenu->addMenuItem(207, "mi_Voucher", menuCaption($menuCaptionOverrides, "207", "Voucher", $Language), "Voucher", -1, "", AllowListMenu('{FIMS}Voucher.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(206, "mci_Administration", $Language->menuPhrase("206", "MenuText"), "", -1, "", true, false, true, "fas fa-user-cog", "", false, true);
$sideMenu->addMenuItem(70, "mi_PositionManagement", menuCaption($menuCaptionOverrides, "70", "PositionManagement", $Language), "PositionManagement", 206, "", AllowListMenu('{FIMS}PositionManagement.php'), false, false, "fas fa-briefcase", "", false, true);
$sideMenu->addMenuItem(13, "mi_UserAccess", menuCaption($menuCaptionOverrides, "13", "UserAccess", $Language), "UserAccess", 206, "", AllowListMenu('{FIMS}UserAccess.php'), false, false, "fas fa-user-lock", "", false, true);
$sideMenu->addMenuItem(12, "mi_UserManagement", menuCaption($menuCaptionOverrides, "12", "UserManagement", $Language), "UserManagement", 206, "", AllowListMenu('{FIMS}UserManagement.php'), false, false, "fas fa-user-shield", "", false, true);
$sideMenu->addMenuItem(69, "mi_DepartmentManagement", menuCaption($menuCaptionOverrides, "69", "DepartmentManagement", $Language), "DepartmentManagement", 206, "", AllowListMenu('{FIMS}DepartmentManagement.php'), false, false, "fas fa-building", "", false, true);
echo $sideMenu->toScript();
