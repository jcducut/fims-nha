<?php

namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;
use Slim\App;
use Closure;

/**
 * Page class
 */
class UsersAdd extends Users
{
    use MessagesTrait;

    // Page ID
    public $PageID = "add";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "UsersAdd";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "UsersAdd";

    // Audit Trail
    public $AuditTrailOnAdd = true;
    public $AuditTrailOnEdit = true;
    public $AuditTrailOnDelete = true;
    public $AuditTrailOnView = false;
    public $AuditTrailOnViewData = false;
    public $AuditTrailOnSearch = false;

    // Page headings
    public $Heading = "";
    public $Subheading = "";
    public $PageHeader;
    public $PageFooter;

    // Page layout
    public $UseLayout = true;

    // Page terminated
    private $terminated = false;

    // Page heading
    public function pageHeading()
    {
        global $Language;
        if ($this->Heading != "") {
            return $this->Heading;
        }
        if (method_exists($this, "tableCaption")) {
            return $this->tableCaption();
        }
        return "";
    }

    // Page subheading
    public function pageSubheading()
    {
        global $Language;
        if ($this->Subheading != "") {
            return $this->Subheading;
        }
        if ($this->TableName) {
            return $Language->phrase($this->PageID);
        }
        return "";
    }

    // Page name
    public function pageName()
    {
        return CurrentPageName();
    }

    // Page URL
    public function pageUrl($withArgs = true)
    {
        $route = GetRoute();
        $args = RemoveXss($route->getArguments());
        if (!$withArgs) {
            foreach ($args as $key => &$val) {
                $val = "";
            }
            unset($val);
        }
        return rtrim(UrlFor($route->getName(), $args), "/") . "?";
    }

    // Show Page Header
    public function showPageHeader()
    {
        $header = $this->PageHeader;
        $this->pageDataRendering($header);
        if ($header != "") { // Header exists, display
            echo '<div id="ew-page-header">' . $header . '</div>';
        }
    }

    // Show Page Footer
    public function showPageFooter()
    {
        $footer = $this->PageFooter;
        $this->pageDataRendered($footer);
        if ($footer != "") { // Footer exists, display
            echo '<div id="ew-page-footer">' . $footer . '</div>';
        }
    }

    // Set field visibility
    public function setVisibility()
    {
        $this->user_id->Visible = false;
        $this->_username->setVisibility();
        $this->_email->setVisibility();
        $this->password_hash->setVisibility();
        $this->mobile_number->setVisibility();
        $this->first_name->setVisibility();
        $this->middle_name->setVisibility();
        $this->last_name->setVisibility();
        $this->suffix->setVisibility();
        $this->department_id->setVisibility();
        $this->date_created->setVisibility();
        $this->last_login->Visible = false;
        $this->is_active->setVisibility();
        $this->user_level_id->Visible = false;
        $this->reports_to_user_id->setVisibility();
        $this->photo->setVisibility();
        $this->_profile->Visible = false;
        $this->position_id->setVisibility();
        $this->employee_id->setVisibility();
        $this->must_change_password->setVisibility();
        $this->rc_id->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'users';
        $this->TableName = 'users';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-add-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (users)
        if (!isset($GLOBALS["users"]) || $GLOBALS["users"]::class == PROJECT_NAMESPACE . "users") {
            $GLOBALS["users"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'users');
        }

        // Start timer
        $DebugTimer = Container("debug.timer");

        // Debug message
        LoadDebugMessage();

        // Open connection
        $GLOBALS["Conn"] ??= $this->getConnection();

        // User table object
        $UserTable = Container("usertable");
    }

    // Get content from stream
    public function getContents(): string
    {
        global $Response;
        return $Response?->getBody() ?? ob_get_clean();
    }

    // Is lookup
    public function isLookup()
    {
        return SameText(Route(0), Config("API_LOOKUP_ACTION"));
    }

    // Is AutoFill
    public function isAutoFill()
    {
        return $this->isLookup() && SameText(Post("ajax"), "autofill");
    }

    // Is AutoSuggest
    public function isAutoSuggest()
    {
        return $this->isLookup() && SameText(Post("ajax"), "autosuggest");
    }

    // Is modal lookup
    public function isModalLookup()
    {
        return $this->isLookup() && SameText(Post("ajax"), "modal");
    }

    // Is terminated
    public function isTerminated()
    {
        return $this->terminated;
    }

    /**
     * Terminate page
     *
     * @param string $url URL for direction
     * @return void
     */
    public function terminate($url = "")
    {
        if ($this->terminated) {
            return;
        }
        global $TempImages, $DashboardReport, $Response;

        // Page is terminated
        $this->terminated = true;

        // Page Unload event
        if (method_exists($this, "pageUnload")) {
            $this->pageUnload();
        }
        DispatchEvent(new PageUnloadedEvent($this), PageUnloadedEvent::NAME);
        if (!IsApi() && method_exists($this, "pageRedirecting")) {
            $this->pageRedirecting($url);
        }

        // Close connection
        CloseConnections();

        // Return for API
        if (IsApi()) {
            $res = $url === true;
            if (!$res) { // Show response for API
                $ar = array_merge($this->getMessages(), $url ? ["url" => GetUrl($url)] : []);
                WriteJson($ar);
            }
            $this->clearMessages(); // Clear messages for API request
            return;
        } else { // Check if response is JSON
            if (WithJsonResponse()) { // With JSON response
                $this->clearMessages();
                return;
            }
        }

        // Go to URL if specified
        if ($url != "") {
            if (!Config("DEBUG") && ob_get_length()) {
                ob_end_clean();
            }

            // Handle modal response
            if ($this->IsModal) { // Show as modal
                $pageName = GetPageName($url);
                $result = ["url" => GetUrl($url), "modal" => "1"];  // Assume return to modal for simplicity
                if (
                    SameString($pageName, GetPageName($this->getListUrl())) ||
                    SameString($pageName, GetPageName($this->getViewUrl())) ||
                    SameString($pageName, GetPageName(CurrentMasterTable()?->getViewUrl() ?? ""))
                ) { // List / View / Master View page
                    if (!SameString($pageName, GetPageName($this->getListUrl()))) { // Not List page
                        $result["caption"] = $this->getModalCaption($pageName);
                        $result["view"] = SameString($pageName, "UsersView"); // If View page, no primary button
                    } else { // List page
                        $result["error"] = $this->getFailureMessage(); // List page should not be shown as modal => error
                        $this->clearFailureMessage();
                    }
                } else { // Other pages (add messages and then clear messages)
                    $result = array_merge($this->getMessages(), ["modal" => "1"]);
                    $this->clearMessages();
                }
                WriteJson($result);
            } else {
                SaveDebugMessage();
                Redirect(GetUrl($url));
            }
        }
        return; // Return to controller
    }

    // Get records from result set
    protected function getRecordsFromRecordset($rs, $current = false)
    {
        $rows = [];
        if (is_object($rs)) { // Result set
            while ($row = $rs->fetch()) {
                $this->loadRowValues($row); // Set up DbValue/CurrentValue
                $this->photo->OldUploadPath = $this->photo->getUploadPath(); // PHP
                $this->photo->UploadPath = $this->photo->OldUploadPath;
                $row = $this->getRecordFromArray($row);
                if ($current) {
                    return $row;
                } else {
                    $rows[] = $row;
                }
            }
        } elseif (is_array($rs)) {
            foreach ($rs as $ar) {
                $row = $this->getRecordFromArray($ar);
                if ($current) {
                    return $row;
                } else {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    // Get record from array
    protected function getRecordFromArray($ar)
    {
        $row = [];
        if (is_array($ar)) {
            foreach ($ar as $fldname => $val) {
                if (array_key_exists($fldname, $this->Fields) && ($this->Fields[$fldname]->Visible || $this->Fields[$fldname]->IsPrimaryKey)) { // Primary key or Visible
                    $fld = &$this->Fields[$fldname];
                    if ($fld->HtmlTag == "FILE") { // Upload field
                        if (EmptyValue($val)) {
                            $row[$fldname] = null;
                        } else {
                            if ($fld->DataType == DataType::BLOB) {
                                $url = FullUrl(GetApiUrl(Config("API_FILE_ACTION") .
                                    "/" . $fld->TableVar . "/" . $fld->Param . "/" . rawurlencode($this->getRecordKeyValue($ar))));
                                $row[$fldname] = ["type" => ContentType($val), "url" => $url, "name" => $fld->Param . ContentExtension($val)];
                            } elseif (!$fld->UploadMultiple || !ContainsString($val, Config("MULTIPLE_UPLOAD_SEPARATOR"))) { // Single file
                                $url = FullUrl(GetApiUrl(Config("API_FILE_ACTION") .
                                    "/" . $fld->TableVar . "/" . Encrypt($fld->physicalUploadPath() . $val)));
                                $row[$fldname] = ["type" => MimeContentType($val), "url" => $url, "name" => $val];
                            } else { // Multiple files
                                $files = explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $val);
                                $ar = [];
                                foreach ($files as $file) {
                                    $url = FullUrl(GetApiUrl(Config("API_FILE_ACTION") .
                                        "/" . $fld->TableVar . "/" . Encrypt($fld->physicalUploadPath() . $file)));
                                    if (!EmptyValue($file)) {
                                        $ar[] = ["type" => MimeContentType($file), "url" => $url, "name" => $file];
                                    }
                                }
                                $row[$fldname] = $ar;
                            }
                        }
                    } else {
                        $row[$fldname] = $val;
                    }
                }
            }
        }
        return $row;
    }

    // Get record key value from array
    protected function getRecordKeyValue($ar)
    {
        $key = "";
        if (is_array($ar)) {
            $key .= @$ar['user_id'];
        }
        return $key;
    }

    /**
     * Hide fields for add/edit
     *
     * @return void
     */
    protected function hideFieldsForAddEdit()
    {
        if ($this->isAdd() || $this->isCopy() || $this->isGridAdd()) {
            $this->user_id->Visible = false;
        }
    }

    // Lookup data
    public function lookup(array $req = [], bool $response = true)
    {
        global $Language, $Security;

        // Get lookup object
        $fieldName = $req["field"] ?? null;
        if (!$fieldName) {
            return [];
        }
        $fld = $this->Fields[$fieldName];
        $lookup = $fld->Lookup;
        $name = $req["name"] ?? "";
        if (ContainsString($name, "query_builder_rule")) {
            $lookup->FilterFields = []; // Skip parent fields if any
        }

        // Get lookup parameters
        $lookupType = $req["ajax"] ?? "unknown";
        $pageSize = -1;
        $offset = -1;
        $searchValue = "";
        if (SameText($lookupType, "modal") || SameText($lookupType, "filter")) {
            $searchValue = $req["q"] ?? $req["sv"] ?? "";
            $pageSize = $req["n"] ?? $req["recperpage"] ?? 10;
        } elseif (SameText($lookupType, "autosuggest")) {
            $searchValue = $req["q"] ?? "";
            $pageSize = $req["n"] ?? -1;
            $pageSize = is_numeric($pageSize) ? (int)$pageSize : -1;
            if ($pageSize <= 0) {
                $pageSize = Config("AUTO_SUGGEST_MAX_ENTRIES");
            }
        }
        $start = $req["start"] ?? -1;
        $start = is_numeric($start) ? (int)$start : -1;
        $page = $req["page"] ?? -1;
        $page = is_numeric($page) ? (int)$page : -1;
        $offset = $start >= 0 ? $start : ($page > 0 && $pageSize > 0 ? ($page - 1) * $pageSize : 0);
        $userSelect = Decrypt($req["s"] ?? "");
        $userFilter = Decrypt($req["f"] ?? "");
        $userOrderBy = Decrypt($req["o"] ?? "");
        $keys = $req["keys"] ?? null;
        $lookup->LookupType = $lookupType; // Lookup type
        $lookup->FilterValues = []; // Clear filter values first
        if ($keys !== null) { // Selected records from modal
            if (is_array($keys)) {
                $keys = implode(Config("MULTIPLE_OPTION_SEPARATOR"), $keys);
            }
            $lookup->FilterFields = []; // Skip parent fields if any
            $lookup->FilterValues[] = $keys; // Lookup values
            $pageSize = -1; // Show all records
        } else { // Lookup values
            $lookup->FilterValues[] = $req["v0"] ?? $req["lookupValue"] ?? "";
        }
        $cnt = is_array($lookup->FilterFields) ? count($lookup->FilterFields) : 0;
        for ($i = 1; $i <= $cnt; $i++) {
            $lookup->FilterValues[] = $req["v" . $i] ?? "";
        }
        $lookup->SearchValue = $searchValue;
        $lookup->PageSize = $pageSize;
        $lookup->Offset = $offset;
        if ($userSelect != "") {
            $lookup->UserSelect = $userSelect;
        }
        if ($userFilter != "") {
            $lookup->UserFilter = $userFilter;
        }
        if ($userOrderBy != "") {
            $lookup->UserOrderBy = $userOrderBy;
        }
        return $lookup->toJson($this, $response); // Use settings from current page
    }
    public $FormClassName = "ew-form ew-add-form";
    public $IsModal = false;
    public $IsMobileOrModal = false;
    public $DbMasterFilter = "";
    public $DbDetailFilter = "";
    public $StartRecord;
    public $Priv = 0;
    public $CopyRecord;

    /**
     * Page run
     *
     * @return void
     */
    public function run()
    {
        global $ExportType, $Language, $Security, $CurrentForm, $SkipHeaderFooter;

        // Is modal
        $this->IsModal = ConvertToBool(Param("modal"));
        $this->UseLayout = $this->UseLayout && !$this->IsModal;

        // Use layout
        $this->UseLayout = $this->UseLayout && ConvertToBool(Param(Config("PAGE_LAYOUT"), true));

        // View
        $this->View = Get(Config("VIEW"));

        // Load user profile
        if (IsLoggedIn()) {
            Profile()->setUserName(CurrentUserName())->loadFromStorage();

            // Force logout user
            if (!IsSysAdmin() && Profile()->isForceLogout(session_id())) {
                $this->terminate("logout");
                return;
            }

            // Check if valid user and update last accessed time
            if (!IsSysAdmin() && !IsPasswordExpired() && !Profile()->isValidUser(session_id(), false)) {
                $this->terminate("logout"); // Handle as session expired
                return;
            }
        }

        // Create form object
        $CurrentForm = new HttpForm();
        $this->CurrentAction = Param("action"); // Set up current action
        $this->setVisibility();

        // Set lookup cache
        if (!in_array($this->PageID, Config("LOOKUP_CACHE_PAGE_IDS"))) {
            $this->setUseLookupCache(false);
        }

        // Global Page Loading event (in userfn*.php)
        DispatchEvent(new PageLoadingEvent($this), PageLoadingEvent::NAME);

        // Page Load event
        if (method_exists($this, "pageLoad")) {
            $this->pageLoad();
        }

        // Hide fields for add/edit
        if (!$this->UseAjaxActions) {
            $this->hideFieldsForAddEdit();
        }
        // Use inline delete
        if ($this->UseAjaxActions) {
            $this->InlineDelete = true;
        }

        // Set up lookup cache
        $this->setupLookupOptions($this->department_id);
        $this->setupLookupOptions($this->is_active);
        $this->setupLookupOptions($this->user_level_id);
        $this->setupLookupOptions($this->must_change_password);

        // Load default values for add
        $this->loadDefaultValues();

        // Check modal
        if ($this->IsModal) {
            $SkipHeaderFooter = true;
        }
        $this->IsMobileOrModal = IsMobile() || $this->IsModal;
        $postBack = false;

        // Set up current action
        if (IsApi()) {
            $this->CurrentAction = "insert"; // Add record directly
            $postBack = true;
        } elseif (Post("action", "") !== "") {
            $this->CurrentAction = Post("action"); // Get form action
            $this->setKey(Post($this->OldKeyName));
            $postBack = true;
        } else {
            // Load key values from QueryString
            if (($keyValue = Get("user_id") ?? Route("user_id")) !== null) {
                $this->user_id->setQueryStringValue($keyValue);
            }
            $this->OldKey = $this->getKey(true); // Get from CurrentValue
            $this->CopyRecord = !EmptyValue($this->OldKey);
            if ($this->CopyRecord) {
                $this->CurrentAction = "copy"; // Copy record
                $this->setKey($this->OldKey); // Set up record key
            } else {
                $this->CurrentAction = "show"; // Display blank record
            }
        }

        // Load old record or default values
        $rsold = $this->loadOldRecord();

        // Set up master/detail parameters
        // NOTE: Must be after loadOldRecord to prevent master key values being overwritten
        $this->setupMasterParms();

        // Load form values
        if ($postBack) {
            $this->loadFormValues(); // Load form values
        }

        // Set up detail parameters
        $this->setupDetailParms();

        // Validate form if post back
        if ($postBack) {
            if (!$this->validateForm()) {
                $this->EventCancelled = true; // Event cancelled
                $this->restoreFormValues(); // Restore form values
                if (IsApi()) {
                    $this->terminate();
                    return;
                } else {
                    $this->CurrentAction = "show"; // Form error, reset action
                }
            }
        }

        // Perform current action
        switch ($this->CurrentAction) {
            case "copy": // Copy an existing record
                if (!$rsold) { // Record not loaded
                    if ($this->getFailureMessage() == "") {
                        $this->setFailureMessage($Language->phrase("NoRecord")); // No record found
                    }
                    $this->terminate("UsersList"); // No matching record, return to list
                    return;
                }

                // Set up detail parameters
                $this->setupDetailParms();
                break;
            case "insert": // Add new record
                $this->SendEmail = true; // Send email on add success
                if ($this->addRow($rsold)) { // Add successful
                    if ($this->getSuccessMessage() == "" && Post("addopt") != "1") { // Skip success message for addopt (done in JavaScript)
                        $this->setSuccessMessage($Language->phrase("AddSuccess")); // Set up success message
                    }
                    if ($this->getCurrentDetailTable() != "") { // Master/detail add
                        $returnUrl = $this->getDetailUrl();
                    } else {
                        $returnUrl = $this->getReturnUrl();
                    }
                    if (GetPageName($returnUrl) == "UsersList") {
                        $returnUrl = $this->addMasterUrl($returnUrl); // List page, return to List page with correct master key if necessary
                    } elseif (GetPageName($returnUrl) == "UsersView") {
                        $returnUrl = $this->getViewUrl(); // View page, return to View page with keyurl directly
                    }

                    // Handle UseAjaxActions with return page
                    if ($this->IsModal && $this->UseAjaxActions && !$this->getCurrentMasterTable()) {
                        $this->IsModal = false;
                        if (GetPageName($returnUrl) != "UsersList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "UsersList"; // Return list page content
                        }
                    }
                    if (IsJsonResponse()) { // Return to caller
                        $this->terminate(true);
                        return;
                    } else {
                        $this->terminate($returnUrl);
                        return;
                    }
                } elseif (IsApi()) { // API request, return
                    $this->terminate();
                    return;
                } elseif ($this->IsModal && $this->UseAjaxActions) { // Return JSON error message
                    WriteJson(["success" => false, "validation" => $this->getValidationErrors(), "error" => $this->getFailureMessage()]);
                    $this->clearFailureMessage();
                    $this->terminate();
                    return;
                } else {
                    $this->EventCancelled = true; // Event cancelled
                    $this->restoreFormValues(); // Add failed, restore form values

                    // Set up detail parameters
                    $this->setupDetailParms();
                }
        }

        // Set up Breadcrumb
        $this->setupBreadcrumb();

        // Render row based on row type
        $this->RowType = RowType::ADD; // Render add type

        // Render row
        $this->resetAttributes();
        $this->renderRow();

        // Set LoginStatus / Page_Rendering / Page_Render
        if (!IsApi() && !$this->isTerminated()) {
            // Setup login status
            SetupLoginStatus();

            // Pass login status to client side
            SetClientVar("login", LoginStatus());

            // Global Page Rendering event (in userfn*.php)
            DispatchEvent(new PageRenderingEvent($this), PageRenderingEvent::NAME);

            // Page Render event
            if (method_exists($this, "pageRender")) {
                $this->pageRender();
            }

            // Render search option
            if (method_exists($this, "renderSearchOptions")) {
                $this->renderSearchOptions();
            }
        }
    }

    // Get upload files
    protected function getUploadFiles()
    {
        global $CurrentForm, $Language;
        $this->photo->Upload->Index = $CurrentForm->Index;
        $this->photo->Upload->uploadFile();
        $this->photo->CurrentValue = $this->photo->Upload->FileName;
    }

    // Load default values
    protected function loadDefaultValues()
    {
    }

    // Load form values
    protected function loadFormValues()
    {
        // Load from form
        global $CurrentForm;
        $validate = !Config("SERVER_VALIDATE");

        // Check field name 'username' first before field var 'x__username'
        $val = $CurrentForm->hasValue("username") ? $CurrentForm->getValue("username") : $CurrentForm->getValue("x__username");
        if (!$this->_username->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->_username->Visible = false; // Disable update for API request
            } else {
                $this->_username->setFormValue($val);
            }
        }

        // Check field name 'email' first before field var 'x__email'
        $val = $CurrentForm->hasValue("email") ? $CurrentForm->getValue("email") : $CurrentForm->getValue("x__email");
        if (!$this->_email->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->_email->Visible = false; // Disable update for API request
            } else {
                $this->_email->setFormValue($val);
            }
        }

        // Check field name 'password_hash' first before field var 'x_password_hash'
        $val = $CurrentForm->hasValue("password_hash") ? $CurrentForm->getValue("password_hash") : $CurrentForm->getValue("x_password_hash");
        if (!$this->password_hash->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->password_hash->Visible = false; // Disable update for API request
            } else {
                $this->password_hash->setFormValue($val);
            }
        }

        // Check field name 'mobile_number' first before field var 'x_mobile_number'
        $val = $CurrentForm->hasValue("mobile_number") ? $CurrentForm->getValue("mobile_number") : $CurrentForm->getValue("x_mobile_number");
        if (!$this->mobile_number->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->mobile_number->Visible = false; // Disable update for API request
            } else {
                $this->mobile_number->setFormValue($val);
            }
        }

        // Check field name 'first_name' first before field var 'x_first_name'
        $val = $CurrentForm->hasValue("first_name") ? $CurrentForm->getValue("first_name") : $CurrentForm->getValue("x_first_name");
        if (!$this->first_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->first_name->Visible = false; // Disable update for API request
            } else {
                $this->first_name->setFormValue($val);
            }
        }

        // Check field name 'middle_name' first before field var 'x_middle_name'
        $val = $CurrentForm->hasValue("middle_name") ? $CurrentForm->getValue("middle_name") : $CurrentForm->getValue("x_middle_name");
        if (!$this->middle_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->middle_name->Visible = false; // Disable update for API request
            } else {
                $this->middle_name->setFormValue($val);
            }
        }

        // Check field name 'last_name' first before field var 'x_last_name'
        $val = $CurrentForm->hasValue("last_name") ? $CurrentForm->getValue("last_name") : $CurrentForm->getValue("x_last_name");
        if (!$this->last_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->last_name->Visible = false; // Disable update for API request
            } else {
                $this->last_name->setFormValue($val);
            }
        }

        // Check field name 'suffix' first before field var 'x_suffix'
        $val = $CurrentForm->hasValue("suffix") ? $CurrentForm->getValue("suffix") : $CurrentForm->getValue("x_suffix");
        if (!$this->suffix->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->suffix->Visible = false; // Disable update for API request
            } else {
                $this->suffix->setFormValue($val);
            }
        }

        // Check field name 'department_id' first before field var 'x_department_id'
        $val = $CurrentForm->hasValue("department_id") ? $CurrentForm->getValue("department_id") : $CurrentForm->getValue("x_department_id");
        if (!$this->department_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->department_id->Visible = false; // Disable update for API request
            } else {
                $this->department_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'date_created' first before field var 'x_date_created'
        $val = $CurrentForm->hasValue("date_created") ? $CurrentForm->getValue("date_created") : $CurrentForm->getValue("x_date_created");
        if (!$this->date_created->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->date_created->Visible = false; // Disable update for API request
            } else {
                $this->date_created->setFormValue($val);
            }
            $this->date_created->CurrentValue = UnFormatDateTime($this->date_created->CurrentValue, $this->date_created->formatPattern());
        }

        // Check field name 'is_active' first before field var 'x_is_active'
        $val = $CurrentForm->hasValue("is_active") ? $CurrentForm->getValue("is_active") : $CurrentForm->getValue("x_is_active");
        if (!$this->is_active->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->is_active->Visible = false; // Disable update for API request
            } else {
                $this->is_active->setFormValue($val);
            }
        }

        // Check field name 'reports_to_user_id' first before field var 'x_reports_to_user_id'
        $val = $CurrentForm->hasValue("reports_to_user_id") ? $CurrentForm->getValue("reports_to_user_id") : $CurrentForm->getValue("x_reports_to_user_id");
        if (!$this->reports_to_user_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->reports_to_user_id->Visible = false; // Disable update for API request
            } else {
                $this->reports_to_user_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'position_id' first before field var 'x_position_id'
        $val = $CurrentForm->hasValue("position_id") ? $CurrentForm->getValue("position_id") : $CurrentForm->getValue("x_position_id");
        if (!$this->position_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->position_id->Visible = false; // Disable update for API request
            } else {
                $this->position_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'employee_id' first before field var 'x_employee_id'
        $val = $CurrentForm->hasValue("employee_id") ? $CurrentForm->getValue("employee_id") : $CurrentForm->getValue("x_employee_id");
        if (!$this->employee_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->employee_id->Visible = false; // Disable update for API request
            } else {
                $this->employee_id->setFormValue($val);
            }
        }

        // Check field name 'must_change_password' first before field var 'x_must_change_password'
        $val = $CurrentForm->hasValue("must_change_password") ? $CurrentForm->getValue("must_change_password") : $CurrentForm->getValue("x_must_change_password");
        if (!$this->must_change_password->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->must_change_password->Visible = false; // Disable update for API request
            } else {
                $this->must_change_password->setFormValue($val);
            }
        }

        // Check field name 'rc_id' first before field var 'x_rc_id'
        $val = $CurrentForm->hasValue("rc_id") ? $CurrentForm->getValue("rc_id") : $CurrentForm->getValue("x_rc_id");
        if (!$this->rc_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->rc_id->Visible = false; // Disable update for API request
            } else {
                $this->rc_id->setFormValue($val);
            }
        }

        // Check field name 'user_id' first before field var 'x_user_id'
        $val = $CurrentForm->hasValue("user_id") ? $CurrentForm->getValue("user_id") : $CurrentForm->getValue("x_user_id");
		$this->photo->OldUploadPath = $this->photo->getUploadPath(); // PHP
		$this->photo->UploadPath = $this->photo->OldUploadPath;
        $this->getUploadFiles(); // Get upload files
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->_username->CurrentValue = $this->_username->FormValue;
        $this->_email->CurrentValue = $this->_email->FormValue;
        $this->password_hash->CurrentValue = $this->password_hash->FormValue;
        $this->mobile_number->CurrentValue = $this->mobile_number->FormValue;
        $this->first_name->CurrentValue = $this->first_name->FormValue;
        $this->middle_name->CurrentValue = $this->middle_name->FormValue;
        $this->last_name->CurrentValue = $this->last_name->FormValue;
        $this->suffix->CurrentValue = $this->suffix->FormValue;
        $this->department_id->CurrentValue = $this->department_id->FormValue;
        $this->date_created->CurrentValue = $this->date_created->FormValue;
        $this->date_created->CurrentValue = UnFormatDateTime($this->date_created->CurrentValue, $this->date_created->formatPattern());
        $this->is_active->CurrentValue = $this->is_active->FormValue;
        $this->reports_to_user_id->CurrentValue = $this->reports_to_user_id->FormValue;
        $this->position_id->CurrentValue = $this->position_id->FormValue;
        $this->employee_id->CurrentValue = $this->employee_id->FormValue;
        $this->must_change_password->CurrentValue = $this->must_change_password->FormValue;
        $this->rc_id->CurrentValue = $this->rc_id->FormValue;
    }

    /**
     * Load row based on key values
     *
     * @return void
     */
    public function loadRow()
    {
        global $Security, $Language;
        $filter = $this->getRecordFilter();

        // Call Row Selecting event
        $this->rowSelecting($filter);

        // Load SQL based on filter
        $this->CurrentFilter = $filter;
        $sql = $this->getCurrentSql();
        $conn = $this->getConnection();
        $res = false;
        $row = $conn->fetchAssociative($sql);
        if ($row) {
            $res = true;
            $this->loadRowValues($row); // Load row values
        }

        // Check if valid User ID
        if ($res) {
            $res = $this->showOptionLink("add");
            if (!$res) {
                $userIdMsg = DeniedMessage();
                $this->setFailureMessage($userIdMsg);
            }
        }
        return $res;
    }

    /**
     * Load row values from result set or record
     *
     * @param array $row Record
     * @return void
     */
    public function loadRowValues($row = null)
    {
        $row = is_array($row) ? $row : $this->newRow();

        // Call Row Selected event
        $this->rowSelected($row);
        $this->user_id->setDbValue($row['user_id']);
        $this->_username->setDbValue($row['username']);
        $this->_email->setDbValue($row['email']);
        $this->password_hash->setDbValue($row['password_hash']);
        $this->mobile_number->setDbValue($row['mobile_number']);
        $this->first_name->setDbValue($row['first_name']);
        $this->middle_name->setDbValue($row['middle_name']);
        $this->last_name->setDbValue($row['last_name']);
        $this->suffix->setDbValue($row['suffix']);
        $this->department_id->setDbValue($row['department_id']);
        $this->date_created->setDbValue($row['date_created']);
        $this->last_login->setDbValue($row['last_login']);
        $this->is_active->setDbValue((ConvertToBool($row['is_active']) ? "1" : "0"));
        $this->user_level_id->setDbValue($row['user_level_id']);
        $this->reports_to_user_id->setDbValue($row['reports_to_user_id']);
        $this->photo->Upload->DbValue = $row['photo'];
        $this->photo->setDbValue($this->photo->Upload->DbValue);
        $this->_profile->setDbValue($row['profile']);
        $this->position_id->setDbValue($row['position_id']);
        $this->employee_id->setDbValue($row['employee_id']);
        $this->must_change_password->setDbValue((ConvertToBool($row['must_change_password']) ? "1" : "0"));
        $this->rc_id->setDbValue($row['rc_id']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['user_id'] = $this->user_id->DefaultValue;
        $row['username'] = $this->_username->DefaultValue;
        $row['email'] = $this->_email->DefaultValue;
        $row['password_hash'] = $this->password_hash->DefaultValue;
        $row['mobile_number'] = $this->mobile_number->DefaultValue;
        $row['first_name'] = $this->first_name->DefaultValue;
        $row['middle_name'] = $this->middle_name->DefaultValue;
        $row['last_name'] = $this->last_name->DefaultValue;
        $row['suffix'] = $this->suffix->DefaultValue;
        $row['department_id'] = $this->department_id->DefaultValue;
        $row['date_created'] = $this->date_created->DefaultValue;
        $row['last_login'] = $this->last_login->DefaultValue;
        $row['is_active'] = $this->is_active->DefaultValue;
        $row['user_level_id'] = $this->user_level_id->DefaultValue;
        $row['reports_to_user_id'] = $this->reports_to_user_id->DefaultValue;
        $row['photo'] = $this->photo->DefaultValue;
        $row['profile'] = $this->_profile->DefaultValue;
        $row['position_id'] = $this->position_id->DefaultValue;
        $row['employee_id'] = $this->employee_id->DefaultValue;
        $row['must_change_password'] = $this->must_change_password->DefaultValue;
        $row['rc_id'] = $this->rc_id->DefaultValue;
        return $row;
    }

    // Load old record
    protected function loadOldRecord()
    {
        // Load old record
        if ($this->OldKey != "") {
            $this->setKey($this->OldKey);
            $this->CurrentFilter = $this->getRecordFilter();
            $sql = $this->getCurrentSql();
            $conn = $this->getConnection();
            $rs = ExecuteQuery($sql, $conn);
            if ($row = $rs->fetch()) {
                $this->loadRowValues($row); // Load row values
                return $row;
            }
        }
        $this->loadRowValues(); // Load default row values
        return null;
    }

    // Render row values based on field settings
    public function renderRow()
    {
        global $Security, $Language, $CurrentLanguage;

        // Initialize URLs

        // Call Row_Rendering event
        $this->rowRendering();

        // Common render codes for all row types

        // user_id
        $this->user_id->RowCssClass = "row";

        // username
        $this->_username->RowCssClass = "row";

        // email
        $this->_email->RowCssClass = "row";

        // password_hash
        $this->password_hash->RowCssClass = "row";

        // mobile_number
        $this->mobile_number->RowCssClass = "row";

        // first_name
        $this->first_name->RowCssClass = "row";

        // middle_name
        $this->middle_name->RowCssClass = "row";

        // last_name
        $this->last_name->RowCssClass = "row";

        // suffix
        $this->suffix->RowCssClass = "row";

        // department_id
        $this->department_id->RowCssClass = "row";

        // date_created
        $this->date_created->RowCssClass = "row";

        // last_login
        $this->last_login->RowCssClass = "row";

        // is_active
        $this->is_active->RowCssClass = "row";

        // user_level_id
        $this->user_level_id->RowCssClass = "row";

        // reports_to_user_id
        $this->reports_to_user_id->RowCssClass = "row";

        // photo
        $this->photo->RowCssClass = "row";

        // profile
        $this->_profile->RowCssClass = "row";

        // position_id
        $this->position_id->RowCssClass = "row";

        // employee_id
        $this->employee_id->RowCssClass = "row";

        // must_change_password
        $this->must_change_password->RowCssClass = "row";

        // rc_id
        $this->rc_id->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // user_id
            $this->user_id->ViewValue = $this->user_id->CurrentValue;

            // username
            $this->_username->ViewValue = $this->_username->CurrentValue;

            // email
            $this->_email->ViewValue = $this->_email->CurrentValue;

            // password_hash
            $this->password_hash->ViewValue = $Language->phrase("PasswordMask");

            // mobile_number
            $this->mobile_number->ViewValue = $this->mobile_number->CurrentValue;

            // first_name
            $this->first_name->ViewValue = $this->first_name->CurrentValue;

            // middle_name
            $this->middle_name->ViewValue = $this->middle_name->CurrentValue;

            // last_name
            $this->last_name->ViewValue = $this->last_name->CurrentValue;

            // suffix
            $this->suffix->ViewValue = $this->suffix->CurrentValue;

            // department_id
            $this->department_id->ViewValue = $this->department_id->CurrentValue;
            $curVal = strval($this->department_id->CurrentValue);
            if ($curVal != "") {
                $this->department_id->ViewValue = $this->department_id->lookupCacheOption($curVal);
                if ($this->department_id->ViewValue === null) { // Lookup from database
                    $filterWrk = SearchFilter($this->department_id->Lookup->getTable()->Fields["department_id"]->searchExpression(), "=", $curVal, $this->department_id->Lookup->getTable()->Fields["department_id"]->searchDataType(), "");
                    $sqlWrk = $this->department_id->Lookup->getSql(false, $filterWrk, '', $this, true, true);
                    $conn = Conn();
                    $config = $conn->getConfiguration();
                    $config->setResultCache($this->Cache);
                    $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                    $ari = count($rswrk);
                    if ($ari > 0) { // Lookup values found
                        $arwrk = $this->department_id->Lookup->renderViewRow($rswrk[0]);
                        $this->department_id->ViewValue = $this->department_id->displayValue($arwrk);
                    } else {
                        $this->department_id->ViewValue = FormatNumber($this->department_id->CurrentValue, $this->department_id->formatPattern());
                    }
                }
            } else {
                $this->department_id->ViewValue = null;
            }

            // date_created
            $this->date_created->ViewValue = $this->date_created->CurrentValue;
            $this->date_created->ViewValue = FormatDateTime($this->date_created->ViewValue, $this->date_created->formatPattern());

            // last_login
            $this->last_login->ViewValue = $this->last_login->CurrentValue;
            $this->last_login->ViewValue = FormatDateTime($this->last_login->ViewValue, $this->last_login->formatPattern());

            // is_active
            if (ConvertToBool($this->is_active->CurrentValue)) {
                $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
            } else {
                $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
            }

            // user_level_id
            if ($Security->canAdmin()) { // System admin
                $curVal = strval($this->user_level_id->CurrentValue);
                if ($curVal != "") {
                    $this->user_level_id->ViewValue = $this->user_level_id->lookupCacheOption($curVal);
                    if ($this->user_level_id->ViewValue === null) { // Lookup from database
                        $arwrk = explode(Config("MULTIPLE_OPTION_SEPARATOR"), $curVal);
                        $filterWrk = "";
                        foreach ($arwrk as $wrk) {
                            AddFilter($filterWrk, SearchFilter($this->user_level_id->Lookup->getTable()->Fields["user_level_id"]->searchExpression(), "=", trim($wrk), $this->user_level_id->Lookup->getTable()->Fields["user_level_id"]->searchDataType(), ""), "OR");
                        }
                        $sqlWrk = $this->user_level_id->Lookup->getSql(false, $filterWrk, '', $this, true, true);
                        $conn = Conn();
                        $config = $conn->getConfiguration();
                        $config->setResultCache($this->Cache);
                        $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                        $ari = count($rswrk);
                        if ($ari > 0) { // Lookup values found
                            $this->user_level_id->ViewValue = new OptionValues();
                            foreach ($rswrk as $row) {
                                $arwrk = $this->user_level_id->Lookup->renderViewRow($row);
                                $this->user_level_id->ViewValue->add($this->user_level_id->displayValue($arwrk));
                            }
                        } else {
                            $this->user_level_id->ViewValue = $this->user_level_id->CurrentValue;
                        }
                    }
                } else {
                    $this->user_level_id->ViewValue = null;
                }
            } else {
                $this->user_level_id->ViewValue = $Language->phrase("PasswordMask");
            }

            // reports_to_user_id
            $this->reports_to_user_id->ViewValue = $this->reports_to_user_id->CurrentValue;
            $this->reports_to_user_id->ViewValue = FormatNumber($this->reports_to_user_id->ViewValue, $this->reports_to_user_id->formatPattern());

            // photo
            $this->photo->UploadPath = $this->photo->getUploadPath(); // PHP
            if (!EmptyValue($this->photo->Upload->DbValue)) {
                $this->photo->ViewValue = $this->photo->Upload->DbValue;
            } else {
                $this->photo->ViewValue = "";
            }

            // position_id
            $this->position_id->ViewValue = $this->position_id->CurrentValue;
            $this->position_id->ViewValue = FormatNumber($this->position_id->ViewValue, $this->position_id->formatPattern());

            // employee_id
            $this->employee_id->ViewValue = $this->employee_id->CurrentValue;

            // must_change_password
            if (ConvertToBool($this->must_change_password->CurrentValue)) {
                $this->must_change_password->ViewValue = $this->must_change_password->tagCaption(1) != "" ? $this->must_change_password->tagCaption(1) : "Yes";
            } else {
                $this->must_change_password->ViewValue = $this->must_change_password->tagCaption(2) != "" ? $this->must_change_password->tagCaption(2) : "No";
            }

            // rc_id
            $this->rc_id->ViewValue = $this->rc_id->CurrentValue;

            // username
            $this->_username->HrefValue = "";

            // email
            $this->_email->HrefValue = "";

            // password_hash
            $this->password_hash->HrefValue = "";

            // mobile_number
            $this->mobile_number->HrefValue = "";

            // first_name
            $this->first_name->HrefValue = "";

            // middle_name
            $this->middle_name->HrefValue = "";

            // last_name
            $this->last_name->HrefValue = "";

            // suffix
            $this->suffix->HrefValue = "";

            // department_id
            $this->department_id->HrefValue = "";

            // date_created
            $this->date_created->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // reports_to_user_id
            $this->reports_to_user_id->HrefValue = "";

            // photo
            $this->photo->HrefValue = "";
            $this->photo->ExportHrefValue = $this->photo->UploadPath . $this->photo->Upload->DbValue;

            // position_id
            $this->position_id->HrefValue = "";

            // employee_id
            $this->employee_id->HrefValue = "";

            // must_change_password
            $this->must_change_password->HrefValue = "";

            // rc_id
            $this->rc_id->HrefValue = "";
        } elseif ($this->RowType == RowType::ADD) {
            // username
            $this->_username->setupEditAttributes();
            if (!$this->_username->Raw) {
                $this->_username->CurrentValue = HtmlDecode($this->_username->CurrentValue);
            }
            $this->_username->EditValue = HtmlEncode($this->_username->CurrentValue);
            $this->_username->PlaceHolder = RemoveHtml($this->_username->caption());

            // email
            $this->_email->setupEditAttributes();
            if (!$this->_email->Raw) {
                $this->_email->CurrentValue = HtmlDecode($this->_email->CurrentValue);
            }
            $this->_email->EditValue = HtmlEncode($this->_email->CurrentValue);
            $this->_email->PlaceHolder = RemoveHtml($this->_email->caption());

            // password_hash
            $this->password_hash->setupEditAttributes();
            $this->password_hash->PlaceHolder = RemoveHtml($this->password_hash->caption());

            // mobile_number
            $this->mobile_number->setupEditAttributes();
            if (!$this->mobile_number->Raw) {
                $this->mobile_number->CurrentValue = HtmlDecode($this->mobile_number->CurrentValue);
            }
            $this->mobile_number->EditValue = HtmlEncode($this->mobile_number->CurrentValue);
            $this->mobile_number->PlaceHolder = RemoveHtml($this->mobile_number->caption());

            // first_name
            $this->first_name->setupEditAttributes();
            if (!$this->first_name->Raw) {
                $this->first_name->CurrentValue = HtmlDecode($this->first_name->CurrentValue);
            }
            $this->first_name->EditValue = HtmlEncode($this->first_name->CurrentValue);
            $this->first_name->PlaceHolder = RemoveHtml($this->first_name->caption());

            // middle_name
            $this->middle_name->setupEditAttributes();
            if (!$this->middle_name->Raw) {
                $this->middle_name->CurrentValue = HtmlDecode($this->middle_name->CurrentValue);
            }
            $this->middle_name->EditValue = HtmlEncode($this->middle_name->CurrentValue);
            $this->middle_name->PlaceHolder = RemoveHtml($this->middle_name->caption());

            // last_name
            $this->last_name->setupEditAttributes();
            if (!$this->last_name->Raw) {
                $this->last_name->CurrentValue = HtmlDecode($this->last_name->CurrentValue);
            }
            $this->last_name->EditValue = HtmlEncode($this->last_name->CurrentValue);
            $this->last_name->PlaceHolder = RemoveHtml($this->last_name->caption());

            // suffix
            $this->suffix->setupEditAttributes();
            if (!$this->suffix->Raw) {
                $this->suffix->CurrentValue = HtmlDecode($this->suffix->CurrentValue);
            }
            $this->suffix->EditValue = HtmlEncode($this->suffix->CurrentValue);
            $this->suffix->PlaceHolder = RemoveHtml($this->suffix->caption());

            // department_id
            $this->department_id->setupEditAttributes();
            $this->department_id->EditValue = $this->department_id->CurrentValue;
            $curVal = strval($this->department_id->CurrentValue);
            if ($curVal != "") {
                $this->department_id->EditValue = $this->department_id->lookupCacheOption($curVal);
                if ($this->department_id->EditValue === null) { // Lookup from database
                    $filterWrk = SearchFilter($this->department_id->Lookup->getTable()->Fields["department_id"]->searchExpression(), "=", $curVal, $this->department_id->Lookup->getTable()->Fields["department_id"]->searchDataType(), "");
                    $sqlWrk = $this->department_id->Lookup->getSql(false, $filterWrk, '', $this, true, true);
                    $conn = Conn();
                    $config = $conn->getConfiguration();
                    $config->setResultCache($this->Cache);
                    $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                    $ari = count($rswrk);
                    if ($ari > 0) { // Lookup values found
                        $arwrk = $this->department_id->Lookup->renderViewRow($rswrk[0]);
                        $this->department_id->EditValue = $this->department_id->displayValue($arwrk);
                    } else {
                        $this->department_id->EditValue = HtmlEncode(FormatNumber($this->department_id->CurrentValue, $this->department_id->formatPattern()));
                    }
                }
            } else {
                $this->department_id->EditValue = null;
            }
            $this->department_id->PlaceHolder = RemoveHtml($this->department_id->caption());

            // date_created

            // is_active
            $this->is_active->EditValue = $this->is_active->options(false);
            $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

            // reports_to_user_id
            $this->reports_to_user_id->setupEditAttributes();
            if (!$Security->isAdmin() && $Security->isLoggedIn()) { // Non system admin
            } else {
                $this->reports_to_user_id->EditValue = $this->reports_to_user_id->CurrentValue;
                $this->reports_to_user_id->PlaceHolder = RemoveHtml($this->reports_to_user_id->caption());
                if (strval($this->reports_to_user_id->EditValue) != "" && is_numeric($this->reports_to_user_id->EditValue)) {
                    $this->reports_to_user_id->EditValue = FormatNumber($this->reports_to_user_id->EditValue, null);
                }
            }

            // photo
            $this->photo->setupEditAttributes();
            $this->photo->UploadPath = $this->photo->getUploadPath(); // PHP
            if (!EmptyValue($this->photo->Upload->DbValue)) {
                $this->photo->EditValue = $this->photo->Upload->DbValue;
            } else {
                $this->photo->EditValue = "";
            }
            if (!EmptyValue($this->photo->CurrentValue)) {
                $this->photo->Upload->FileName = $this->photo->CurrentValue;
            }
            if (!Config("CREATE_UPLOAD_FILE_ON_COPY")) {
                $this->photo->Upload->DbValue = null;
            }
            if ($this->isShow() || $this->isCopy()) {
                RenderUploadField($this->photo);
            }

            // position_id
            $this->position_id->setupEditAttributes();
            $this->position_id->EditValue = $this->position_id->CurrentValue;
            $this->position_id->PlaceHolder = RemoveHtml($this->position_id->caption());
            if (strval($this->position_id->EditValue) != "" && is_numeric($this->position_id->EditValue)) {
                $this->position_id->EditValue = FormatNumber($this->position_id->EditValue, null);
            }

            // employee_id
            $this->employee_id->setupEditAttributes();
            if (!$this->employee_id->Raw) {
                $this->employee_id->CurrentValue = HtmlDecode($this->employee_id->CurrentValue);
            }
            $this->employee_id->EditValue = HtmlEncode($this->employee_id->CurrentValue);
            $this->employee_id->PlaceHolder = RemoveHtml($this->employee_id->caption());

            // must_change_password
            $this->must_change_password->EditValue = $this->must_change_password->options(false);
            $this->must_change_password->PlaceHolder = RemoveHtml($this->must_change_password->caption());

            // rc_id
            $this->rc_id->setupEditAttributes();
            if (!$this->rc_id->Raw) {
                $this->rc_id->CurrentValue = HtmlDecode($this->rc_id->CurrentValue);
            }
            $this->rc_id->EditValue = HtmlEncode($this->rc_id->CurrentValue);
            $this->rc_id->PlaceHolder = RemoveHtml($this->rc_id->caption());

            // Add refer script

            // username
            $this->_username->HrefValue = "";

            // email
            $this->_email->HrefValue = "";

            // password_hash
            $this->password_hash->HrefValue = "";

            // mobile_number
            $this->mobile_number->HrefValue = "";

            // first_name
            $this->first_name->HrefValue = "";

            // middle_name
            $this->middle_name->HrefValue = "";

            // last_name
            $this->last_name->HrefValue = "";

            // suffix
            $this->suffix->HrefValue = "";

            // department_id
            $this->department_id->HrefValue = "";

            // date_created
            $this->date_created->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // reports_to_user_id
            $this->reports_to_user_id->HrefValue = "";

            // photo
            $this->photo->HrefValue = "";
            $this->photo->ExportHrefValue = $this->photo->UploadPath . $this->photo->Upload->DbValue;

            // position_id
            $this->position_id->HrefValue = "";

            // employee_id
            $this->employee_id->HrefValue = "";

            // must_change_password
            $this->must_change_password->HrefValue = "";

            // rc_id
            $this->rc_id->HrefValue = "";
        }
        if ($this->RowType == RowType::ADD || $this->RowType == RowType::EDIT || $this->RowType == RowType::SEARCH) { // Add/Edit/Search row
            $this->setupFieldTitles();
        }

        // Call Row Rendered event
        if ($this->RowType != RowType::AGGREGATEINIT) {
            $this->rowRendered();
        }
    }

    // Validate form
    protected function validateForm()
    {
        global $Language, $Security;

        // Check if validation required
        if (!Config("SERVER_VALIDATE")) {
            return true;
        }
        $validateForm = true;
            if ($this->_username->Visible && $this->_username->Required) {
                if (!$this->_username->IsDetailKey && EmptyValue($this->_username->FormValue)) {
                    $this->_username->addErrorMessage(str_replace("%s", $this->_username->caption(), $this->_username->RequiredErrorMessage));
                }
            }
            if (!$this->_username->Raw && Config("REMOVE_XSS") && CheckUsername($this->_username->FormValue)) {
                $this->_username->addErrorMessage($Language->phrase("InvalidUsernameChars"));
            }
            if ($this->_email->Visible && $this->_email->Required) {
                if (!$this->_email->IsDetailKey && EmptyValue($this->_email->FormValue)) {
                    $this->_email->addErrorMessage(str_replace("%s", $this->_email->caption(), $this->_email->RequiredErrorMessage));
                }
            }
            if ($this->password_hash->Visible && $this->password_hash->Required) {
                if (!$this->password_hash->IsDetailKey && EmptyValue($this->password_hash->FormValue)) {
                    $this->password_hash->addErrorMessage(str_replace("%s", $this->password_hash->caption(), $this->password_hash->RequiredErrorMessage));
                }
            }
            if (!$this->password_hash->Raw && Config("REMOVE_XSS") && CheckPassword($this->password_hash->FormValue)) {
                $this->password_hash->addErrorMessage($Language->phrase("InvalidPasswordChars"));
            }
            if ($this->mobile_number->Visible && $this->mobile_number->Required) {
                if (!$this->mobile_number->IsDetailKey && EmptyValue($this->mobile_number->FormValue)) {
                    $this->mobile_number->addErrorMessage(str_replace("%s", $this->mobile_number->caption(), $this->mobile_number->RequiredErrorMessage));
                }
            }
            if ($this->first_name->Visible && $this->first_name->Required) {
                if (!$this->first_name->IsDetailKey && EmptyValue($this->first_name->FormValue)) {
                    $this->first_name->addErrorMessage(str_replace("%s", $this->first_name->caption(), $this->first_name->RequiredErrorMessage));
                }
            }
            if ($this->middle_name->Visible && $this->middle_name->Required) {
                if (!$this->middle_name->IsDetailKey && EmptyValue($this->middle_name->FormValue)) {
                    $this->middle_name->addErrorMessage(str_replace("%s", $this->middle_name->caption(), $this->middle_name->RequiredErrorMessage));
                }
            }
            if ($this->last_name->Visible && $this->last_name->Required) {
                if (!$this->last_name->IsDetailKey && EmptyValue($this->last_name->FormValue)) {
                    $this->last_name->addErrorMessage(str_replace("%s", $this->last_name->caption(), $this->last_name->RequiredErrorMessage));
                }
            }
            if ($this->suffix->Visible && $this->suffix->Required) {
                if (!$this->suffix->IsDetailKey && EmptyValue($this->suffix->FormValue)) {
                    $this->suffix->addErrorMessage(str_replace("%s", $this->suffix->caption(), $this->suffix->RequiredErrorMessage));
                }
            }
            if ($this->department_id->Visible && $this->department_id->Required) {
                if (!$this->department_id->IsDetailKey && EmptyValue($this->department_id->FormValue)) {
                    $this->department_id->addErrorMessage(str_replace("%s", $this->department_id->caption(), $this->department_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->department_id->FormValue)) {
                $this->department_id->addErrorMessage($this->department_id->getErrorMessage(false));
            }
            if ($this->date_created->Visible && $this->date_created->Required) {
                if (!$this->date_created->IsDetailKey && EmptyValue($this->date_created->FormValue)) {
                    $this->date_created->addErrorMessage(str_replace("%s", $this->date_created->caption(), $this->date_created->RequiredErrorMessage));
                }
            }
            if ($this->is_active->Visible && $this->is_active->Required) {
                if ($this->is_active->FormValue == "") {
                    $this->is_active->addErrorMessage(str_replace("%s", $this->is_active->caption(), $this->is_active->RequiredErrorMessage));
                }
            }
            if ($this->reports_to_user_id->Visible && $this->reports_to_user_id->Required) {
                if (!$this->reports_to_user_id->IsDetailKey && EmptyValue($this->reports_to_user_id->FormValue)) {
                    $this->reports_to_user_id->addErrorMessage(str_replace("%s", $this->reports_to_user_id->caption(), $this->reports_to_user_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->reports_to_user_id->FormValue)) {
                $this->reports_to_user_id->addErrorMessage($this->reports_to_user_id->getErrorMessage(false));
            }
            if ($this->photo->Visible && $this->photo->Required) {
                if ($this->photo->Upload->FileName == "" && !$this->photo->Upload->KeepFile) {
                    $this->photo->addErrorMessage(str_replace("%s", $this->photo->caption(), $this->photo->RequiredErrorMessage));
                }
            }
            if ($this->position_id->Visible && $this->position_id->Required) {
                if (!$this->position_id->IsDetailKey && EmptyValue($this->position_id->FormValue)) {
                    $this->position_id->addErrorMessage(str_replace("%s", $this->position_id->caption(), $this->position_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->position_id->FormValue)) {
                $this->position_id->addErrorMessage($this->position_id->getErrorMessage(false));
            }
            if ($this->employee_id->Visible && $this->employee_id->Required) {
                if (!$this->employee_id->IsDetailKey && EmptyValue($this->employee_id->FormValue)) {
                    $this->employee_id->addErrorMessage(str_replace("%s", $this->employee_id->caption(), $this->employee_id->RequiredErrorMessage));
                }
            }
            if ($this->must_change_password->Visible && $this->must_change_password->Required) {
                if ($this->must_change_password->FormValue == "") {
                    $this->must_change_password->addErrorMessage(str_replace("%s", $this->must_change_password->caption(), $this->must_change_password->RequiredErrorMessage));
                }
            }
            if ($this->rc_id->Visible && $this->rc_id->Required) {
                if (!$this->rc_id->IsDetailKey && EmptyValue($this->rc_id->FormValue)) {
                    $this->rc_id->addErrorMessage(str_replace("%s", $this->rc_id->caption(), $this->rc_id->RequiredErrorMessage));
                }
            }

        // Validate detail grid
        $detailTblVar = explode(",", $this->getCurrentDetailTable());
        $detailPage = Container("UserLevelAssignmentsGrid");
        if (in_array("user_level_assignments", $detailTblVar) && $detailPage->DetailAdd) {
            $detailPage->run();
            $validateForm = $validateForm && $detailPage->validateGridForm();
        }

        // Return validate result
        $validateForm = $validateForm && !$this->hasInvalidFields();

        // Call Form_CustomValidate event
        $formCustomError = "";
        $validateForm = $validateForm && $this->formCustomValidate($formCustomError);
        if ($formCustomError != "") {
            $this->setFailureMessage($formCustomError);
        }
        return $validateForm;
    }

    // Add record
    protected function addRow($rsold = null)
    {
        global $Language, $Security;

        // Get new row
        $rsnew = $this->getAddRow();
        if ($this->photo->Visible && !$this->photo->Upload->KeepFile) {
            $this->photo->UploadPath = $this->photo->getUploadPath();
            if (!EmptyValue($this->photo->Upload->FileName)) {
                $this->photo->Upload->DbValue = null;
                FixUploadFileNames($this->photo);
                $this->photo->setDbValueDef($rsnew, $this->photo->Upload->FileName, false);
            }
        }

        // Update current values
        $this->setCurrentValues($rsnew);

        // Check if valid User ID
        if (
            !EmptyValue($Security->currentUserID()) &&
            !$Security->isAdmin() && // Non system admin
            !$Security->isValidUserID($this->user_id->CurrentValue)
        ) {
            $userIdMsg = str_replace("%c", CurrentUserID(), $Language->phrase("UnAuthorizedUserID"));
            $userIdMsg = str_replace("%u", strval($this->user_id->CurrentValue), $userIdMsg);
            $this->setFailureMessage($userIdMsg);
            return false;
        }

        // Check if valid Parent User ID
        if (
            !EmptyValue($Security->currentUserID()) &&
            !EmptyValue($this->reports_to_user_id->CurrentValue) && // Allow empty value
            !$Security->isAdmin() && // Non system admin
            !$Security->isValidUserID($this->reports_to_user_id->CurrentValue)
        ) {
            $parentUserIdMsg = str_replace("%c", CurrentUserID(), $Language->phrase("UnAuthorizedParentUserID"));
            $parentUserIdMsg = str_replace("%p", strval($this->reports_to_user_id->CurrentValue), $parentUserIdMsg);
            $this->setFailureMessage($parentUserIdMsg);
            return false;
        }
        if ($this->_username->CurrentValue != "") { // Check field with unique index
            $filter = "(\"username\" = '" . AdjustSql($this->_username->CurrentValue, $this->Dbid) . "')";
            $rsChk = $this->loadRs($filter)->fetch();
            if ($rsChk !== false) {
                $idxErrMsg = str_replace("%f", $this->_username->caption(), $Language->phrase("DupIndex"));
                $idxErrMsg = str_replace("%v", $this->_username->CurrentValue, $idxErrMsg);
                $this->setFailureMessage($idxErrMsg);
                return false;
            }
        }
        if ($this->_email->CurrentValue != "") { // Check field with unique index
            $filter = "(\"email\" = '" . AdjustSql($this->_email->CurrentValue, $this->Dbid) . "')";
            $rsChk = $this->loadRs($filter)->fetch();
            if ($rsChk !== false) {
                $idxErrMsg = str_replace("%f", $this->_email->caption(), $Language->phrase("DupIndex"));
                $idxErrMsg = str_replace("%v", $this->_email->CurrentValue, $idxErrMsg);
                $this->setFailureMessage($idxErrMsg);
                return false;
            }
        }
        $conn = $this->getConnection();

        // Begin transaction
        if ($this->getCurrentDetailTable() != "" && $this->UseTransaction) {
            $conn->beginTransaction();
        }

        // Load db values from old row
        $this->loadDbValues($rsold);
        $this->photo->OldUploadPath = $this->photo->getUploadPath(); // PHP
        $this->photo->UploadPath = $this->photo->OldUploadPath;

        // Call Row Inserting event
        $insertRow = $this->rowInserting($rsold, $rsnew);
        if ($insertRow) {
            $addRow = $this->insert($rsnew);
            if ($addRow) {
                if ($this->photo->Visible && !$this->photo->Upload->KeepFile) {
                    $this->photo->Upload->DbValue = null;
                    if (!SaveUploadFiles($this->photo, $rsnew['photo'], false)) {
                        $this->setFailureMessage($Language->phrase("UploadError7"));
                        return false;
                    }
                }
            } elseif (!EmptyValue($this->DbErrorMessage)) { // Show database error
                $this->setFailureMessage($this->DbErrorMessage);
            }
        } else {
            if ($this->getSuccessMessage() != "" || $this->getFailureMessage() != "") {
                // Use the message, do nothing
            } elseif ($this->CancelMessage != "") {
                $this->setFailureMessage($this->CancelMessage);
                $this->CancelMessage = "";
            } else {
                $this->setFailureMessage($Language->phrase("InsertCancelled"));
            }
            $addRow = false;
        }

        // Add detail records
        if ($addRow) {
            $detailTblVar = explode(",", $this->getCurrentDetailTable());
            $detailPage = Container("UserLevelAssignmentsGrid");
            if (in_array("user_level_assignments", $detailTblVar) && $detailPage->DetailAdd && $addRow) {
                $detailPage->user_id->setSessionValue($this->user_id->CurrentValue); // Set master key
                $Security->loadCurrentUserLevel($this->ProjectID . "user_level_assignments"); // Load user level of detail table
                $addRow = $detailPage->gridInsert();
                $Security->loadCurrentUserLevel($this->ProjectID . $this->TableName); // Restore user level of master table
                if (!$addRow) {
                $detailPage->user_id->setSessionValue(""); // Clear master key if insert failed
                }
            }
        }

        // Commit/Rollback transaction
        if ($this->getCurrentDetailTable() != "") {
            if ($addRow) {
                if ($this->UseTransaction) { // Commit transaction
                    if ($conn->isTransactionActive()) {
                        $conn->commit();
                    }
                }
            } else {
                if ($this->UseTransaction) { // Rollback transaction
                    if ($conn->isTransactionActive()) {
                        $conn->rollback();
                    }
                }
            }
        }
        if ($addRow) {
            // Call Row Inserted event
            $this->rowInserted($rsold, $rsnew);
        }

        // Write JSON response
        if (IsJsonResponse() && $addRow) {
            $row = $this->getRecordsFromRecordset([$rsnew], true);
            $table = $this->TableVar;
            WriteJson(["success" => true, "action" => Config("API_ADD_ACTION"), $table => $row]);
        }
        return $addRow;
    }

    /**
     * Get add row
     *
     * @return array
     */
    protected function getAddRow()
    {
        global $Security;
        $rsnew = [];

        // username
        $this->_username->setDbValueDef($rsnew, $this->_username->CurrentValue, false);

        // email
        $this->_email->setDbValueDef($rsnew, $this->_email->CurrentValue, false);

        // password_hash
        if (!IsMaskedPassword($this->password_hash->CurrentValue)) {
            $this->password_hash->setDbValueDef($rsnew, $this->password_hash->CurrentValue, false);
        }

        // mobile_number
        $this->mobile_number->setDbValueDef($rsnew, $this->mobile_number->CurrentValue, false);

        // first_name
        $this->first_name->setDbValueDef($rsnew, $this->first_name->CurrentValue, false);

        // middle_name
        $this->middle_name->setDbValueDef($rsnew, $this->middle_name->CurrentValue, false);

        // last_name
        $this->last_name->setDbValueDef($rsnew, $this->last_name->CurrentValue, false);

        // suffix
        $this->suffix->setDbValueDef($rsnew, $this->suffix->CurrentValue, false);

        // department_id
        $this->department_id->setDbValueDef($rsnew, $this->department_id->CurrentValue, false);

        // date_created
        $this->date_created->CurrentValue = $this->date_created->getAutoUpdateValue(); // PHP
        $this->date_created->setDbValueDef($rsnew, UnFormatDateTime($this->date_created->CurrentValue, $this->date_created->formatPattern()), false);

        // is_active
        $tmpBool = $this->is_active->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_active->setDbValueDef($rsnew, $tmpBool, strval($this->is_active->CurrentValue) == "");

        // reports_to_user_id
        $this->reports_to_user_id->setDbValueDef($rsnew, $this->reports_to_user_id->CurrentValue, false);

        // photo
        if ($this->photo->Visible && !$this->photo->Upload->KeepFile) {
            if ($this->photo->Upload->FileName == "") {
                $rsnew['photo'] = null;
            } else {
                FixUploadTempFileNames($this->photo);
                $rsnew['photo'] = $this->photo->Upload->FileName;
            }
        }

        // position_id
        $this->position_id->setDbValueDef($rsnew, $this->position_id->CurrentValue, false);

        // employee_id
        $this->employee_id->setDbValueDef($rsnew, $this->employee_id->CurrentValue, false);

        // must_change_password
        $tmpBool = $this->must_change_password->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->must_change_password->setDbValueDef($rsnew, $tmpBool, strval($this->must_change_password->CurrentValue) == "");

        // rc_id
        $this->rc_id->setDbValueDef($rsnew, $this->rc_id->CurrentValue, false);

        // user_id

        // user_level_id
        if ($this->user_level_id->getSessionValue() != "") {
            $rsnew['user_level_id'] = $this->user_level_id->getSessionValue();
        }
        return $rsnew;
    }

    /**
     * Restore add form from row
     * @param array $row Row
     */
    protected function restoreAddFormFromRow($row)
    {
        if (isset($row['username'])) { // username
            $this->_username->setFormValue($row['username']);
        }
        if (isset($row['email'])) { // email
            $this->_email->setFormValue($row['email']);
        }
        if (isset($row['password_hash'])) { // password_hash
            $this->password_hash->setFormValue($row['password_hash']);
        }
        if (isset($row['mobile_number'])) { // mobile_number
            $this->mobile_number->setFormValue($row['mobile_number']);
        }
        if (isset($row['first_name'])) { // first_name
            $this->first_name->setFormValue($row['first_name']);
        }
        if (isset($row['middle_name'])) { // middle_name
            $this->middle_name->setFormValue($row['middle_name']);
        }
        if (isset($row['last_name'])) { // last_name
            $this->last_name->setFormValue($row['last_name']);
        }
        if (isset($row['suffix'])) { // suffix
            $this->suffix->setFormValue($row['suffix']);
        }
        if (isset($row['department_id'])) { // department_id
            $this->department_id->setFormValue($row['department_id']);
        }
        if (isset($row['date_created'])) { // date_created
            $this->date_created->setFormValue($row['date_created']);
        }
        if (isset($row['is_active'])) { // is_active
            $this->is_active->setFormValue($row['is_active']);
        }
        if (isset($row['reports_to_user_id'])) { // reports_to_user_id
            $this->reports_to_user_id->setFormValue($row['reports_to_user_id']);
        }
        if (isset($row['photo'])) { // photo
            $this->photo->setFormValue($row['photo']);
        }
        if (isset($row['position_id'])) { // position_id
            $this->position_id->setFormValue($row['position_id']);
        }
        if (isset($row['employee_id'])) { // employee_id
            $this->employee_id->setFormValue($row['employee_id']);
        }
        if (isset($row['must_change_password'])) { // must_change_password
            $this->must_change_password->setFormValue($row['must_change_password']);
        }
        if (isset($row['rc_id'])) { // rc_id
            $this->rc_id->setFormValue($row['rc_id']);
        }
        if (isset($row['user_id'])) { // user_id
            $this->user_id->setFormValue($row['user_id']);
        }
        if (isset($row['user_level_id'])) { // user_level_id
            $this->user_level_id->setFormValue($row['user_level_id']);
        }
    }

    // Show link optionally based on User ID
    protected function showOptionLink($id = "")
    {
        global $Security;
        if ($Security->isLoggedIn() && !$Security->isAdmin() && !$this->userIDAllow($id)) {
            return $Security->isValidUserID($this->user_id->CurrentValue);
        }
        return true;
    }

    // Set up master/detail based on QueryString
    protected function setupMasterParms()
    {
        $validMaster = false;
        $foreignKeys = [];
        // Get the keys for master table
        if (($master = Get(Config("TABLE_SHOW_MASTER"), Get(Config("TABLE_MASTER")))) !== null) {
            $masterTblVar = $master;
            if ($masterTblVar == "") {
                $validMaster = true;
                $this->DbMasterFilter = "";
                $this->DbDetailFilter = "";
            }
            if ($masterTblVar == "_user_levels") {
                $validMaster = true;
                $masterTbl = Container("_user_levels");
                if (($parm = Get("fk_user_level_id", Get("user_level_id"))) !== null) {
                    $masterTbl->user_level_id->setQueryStringValue($parm);
                    $this->user_level_id->QueryStringValue = $masterTbl->user_level_id->QueryStringValue; // DO NOT change, master/detail key data type can be different
                    $this->user_level_id->setSessionValue($this->user_level_id->QueryStringValue);
                    $foreignKeys["user_level_id"] = $this->user_level_id->QueryStringValue;
                    if (!is_numeric($masterTbl->user_level_id->QueryStringValue)) {
                        $validMaster = false;
                    }
                } else {
                    $validMaster = false;
                }
            }
        } elseif (($master = Post(Config("TABLE_SHOW_MASTER"), Post(Config("TABLE_MASTER")))) !== null) {
            $masterTblVar = $master;
            if ($masterTblVar == "") {
                    $validMaster = true;
                    $this->DbMasterFilter = "";
                    $this->DbDetailFilter = "";
            }
            if ($masterTblVar == "_user_levels") {
                $validMaster = true;
                $masterTbl = Container("_user_levels");
                if (($parm = Post("fk_user_level_id", Post("user_level_id"))) !== null) {
                    $masterTbl->user_level_id->setFormValue($parm);
                    $this->user_level_id->FormValue = $masterTbl->user_level_id->FormValue;
                    $this->user_level_id->setSessionValue($this->user_level_id->FormValue);
                    $foreignKeys["user_level_id"] = $this->user_level_id->FormValue;
                    if (!is_numeric($masterTbl->user_level_id->FormValue)) {
                        $validMaster = false;
                    }
                } else {
                    $validMaster = false;
                }
            }
        }
        if ($validMaster) {
            // Save current master table
            $this->setCurrentMasterTable($masterTblVar);

            // Reset start record counter (new master key)
            if (!$this->isAddOrEdit() && !$this->isGridUpdate()) {
                $this->StartRecord = 1;
                $this->setStartRecordNumber($this->StartRecord);
            }

            // Clear previous master key from Session
            if ($masterTblVar != "_user_levels") {
                if (!array_key_exists("user_level_id", $foreignKeys)) { // Not current foreign key
                    $this->user_level_id->setSessionValue("");
                }
            }
        }
        $this->DbMasterFilter = $this->getMasterFilterFromSession(); // Get master filter from session
        $this->DbDetailFilter = $this->getDetailFilterFromSession(); // Get detail filter from session
    }

    // Set up detail parms based on QueryString
    protected function setupDetailParms()
    {
        // Get the keys for master table
        $detailTblVar = Get(Config("TABLE_SHOW_DETAIL"));
        if ($detailTblVar !== null) {
            $this->setCurrentDetailTable($detailTblVar);
        } else {
            $detailTblVar = $this->getCurrentDetailTable();
        }
        if ($detailTblVar != "") {
            $detailTblVar = explode(",", $detailTblVar);
            if (in_array("user_level_assignments", $detailTblVar)) {
                $detailPageObj = Container("UserLevelAssignmentsGrid");
                if ($detailPageObj->DetailAdd) {
                    $detailPageObj->EventCancelled = $this->EventCancelled;
                    if ($this->CopyRecord) {
                        $detailPageObj->CurrentMode = "copy";
                    } else {
                        $detailPageObj->CurrentMode = "add";
                    }
                    $detailPageObj->CurrentAction = "gridadd";

                    // Save current master table to detail table
                    $detailPageObj->setCurrentMasterTable($this->TableVar);
                    $detailPageObj->setStartRecordNumber(1);
                    $detailPageObj->user_id->IsDetailKey = true;
                    $detailPageObj->user_id->CurrentValue = $this->user_id->CurrentValue;
                    $detailPageObj->user_id->setSessionValue($detailPageObj->user_id->CurrentValue);
                    $detailPageObj->system_id->setSessionValue(""); // Clear session key
                    $detailPageObj->user_level_id->setSessionValue(""); // Clear session key
                }
            }
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("UsersList"), "", $this->TableVar, true);
        $pageId = ($this->isCopy()) ? "Copy" : "Add";
        $Breadcrumb->add("add", $pageId, $url);
    }

    // Setup lookup options
    public function setupLookupOptions($fld)
    {
        if ($fld->Lookup && $fld->Lookup->Options === null) {
            // Get default connection and filter
            $conn = $this->getConnection();
            $lookupFilter = "";

            // No need to check any more
            $fld->Lookup->Options = [];

            // Set up lookup SQL and connection
            switch ($fld->FieldVar) {
                case "x_department_id":
                    break;
                case "x_is_active":
                    break;
                case "x_user_level_id":
                    break;
                case "x_must_change_password":
                    break;
                default:
                    $lookupFilter = "";
                    break;
            }

            // Always call to Lookup->getSql so that user can setup Lookup->Options in Lookup_Selecting server event
            $sql = $fld->Lookup->getSql(false, "", $lookupFilter, $this);

            // Set up lookup cache
            if (!$fld->hasLookupOptions() && $fld->UseLookupCache && $sql != "" && count($fld->Lookup->Options) == 0 && count($fld->Lookup->FilterFields) == 0) {
                $totalCnt = $this->getRecordCount($sql, $conn);
                if ($totalCnt > $fld->LookupCacheCount) { // Total count > cache count, do not cache
                    return;
                }
                $rows = $conn->executeQuery($sql)->fetchAll();
                $ar = [];
                foreach ($rows as $row) {
                    $row = $fld->Lookup->renderViewRow($row, Container($fld->Lookup->LinkTable));
                    $key = $row["lf"];
                    if (IsFloatType($fld->Type)) { // Handle float field
                        $key = (float)$key;
                    }
                    $ar[strval($key)] = $row;
                }
                $fld->Lookup->Options = $ar;
            }
        }
    }

    // Page Load event
    public function pageLoad()
    {
        //Log("Page Load");
    }

    // Page Unload event
    public function pageUnload()
    {
        //Log("Page Unload");
    }

    // Page Redirecting event
    public function pageRedirecting(&$url)
    {
        // Example:
        //$url = "your URL";
    }

    // Message Showing event
    // $type = ''|'success'|'failure'|'warning'
    public function messageShowing(&$msg, $type)
    {
        if ($type == "success") {
            //$msg = "your success message";
        } elseif ($type == "failure") {
            //$msg = "your failure message";
        } elseif ($type == "warning") {
            //$msg = "your warning message";
        } else {
            //$msg = "your message";
        }
    }

    // Page Render event
    public function pageRender()
    {
        //Log("Page Render");
    }

    // Page Data Rendering event
    public function pageDataRendering(&$header)
    {
        // Example:
        //$header = "your header";
    }

    // Page Data Rendered event
    public function pageDataRendered(&$footer)
    {
        // Example:
        //$footer = "your footer";
    }

    // Page Breaking event
    public function pageBreaking(&$break, &$content)
    {
        // Example:
        //$break = false; // Skip page break, or
        //$content = "<div style=\"break-after:page;\"></div>"; // Modify page break content
    }

    // Form Custom Validate event
    public function formCustomValidate(&$customError)
    {
        // Return error message in $customError
        return true;
    }
}
