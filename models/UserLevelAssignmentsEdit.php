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
class UserLevelAssignmentsEdit extends UserLevelAssignments
{
    use MessagesTrait;

    // Page ID
    public $PageID = "edit";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "UserLevelAssignmentsEdit";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "UserLevelAssignmentsEdit";

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
        $this->assignment_id->setVisibility();
        $this->system_id->setVisibility();
        $this->user_level_id->setVisibility();
        $this->user_id->setVisibility();
        $this->assigned_by->setVisibility();
        $this->created_at->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'user_level_assignments';
        $this->TableName = 'user_level_assignments';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-edit-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (user_level_assignments)
        if (!isset($GLOBALS["user_level_assignments"]) || $GLOBALS["user_level_assignments"]::class == PROJECT_NAMESPACE . "user_level_assignments") {
            $GLOBALS["user_level_assignments"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'user_level_assignments');
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
                        $result["view"] = SameString($pageName, "UserLevelAssignmentsView"); // If View page, no primary button
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
            $key .= @$ar['assignment_id'];
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
            $this->assignment_id->Visible = false;
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

    // Properties
    public $FormClassName = "ew-form ew-edit-form overlay-wrapper";
    public $IsModal = false;
    public $IsMobileOrModal = false;
    public $DbMasterFilter;
    public $DbDetailFilter;
    public $HashValue; // Hash Value
    public $DisplayRecords = 1;
    public $StartRecord;
    public $StopRecord;
    public $TotalRecords = 0;
    public $RecordRange = 10;
    public $RecordCount;

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
        $this->setupLookupOptions($this->system_id);
        $this->setupLookupOptions($this->user_level_id);
        $this->setupLookupOptions($this->user_id);

        // Check modal
        if ($this->IsModal) {
            $SkipHeaderFooter = true;
        }
        $this->IsMobileOrModal = IsMobile() || $this->IsModal;
        $loaded = false;
        $postBack = false;

        // Set up current action and primary key
        if (IsApi()) {
            // Load key values
            $loaded = true;
            if (($keyValue = Get("assignment_id") ?? Key(0) ?? Route(2)) !== null) {
                $this->assignment_id->setQueryStringValue($keyValue);
                $this->assignment_id->setOldValue($this->assignment_id->QueryStringValue);
            } elseif (Post("assignment_id") !== null) {
                $this->assignment_id->setFormValue(Post("assignment_id"));
                $this->assignment_id->setOldValue($this->assignment_id->FormValue);
            } else {
                $loaded = false; // Unable to load key
            }

            // Load record
            if ($loaded) {
                $loaded = $this->loadRow();
            }
            if (!$loaded) {
                $this->setFailureMessage($Language->phrase("NoRecord")); // Set no record message
                $this->terminate();
                return;
            }
            $this->CurrentAction = "update"; // Update record directly
            $this->OldKey = $this->getKey(true); // Get from CurrentValue
            $postBack = true;
        } else {
            if (Post("action", "") !== "") {
                $this->CurrentAction = Post("action"); // Get action code
                if (!$this->isShow()) { // Not reload record, handle as postback
                    $postBack = true;
                }

                // Get key from Form
                $this->setKey(Post($this->OldKeyName), $this->isShow());
            } else {
                $this->CurrentAction = "show"; // Default action is display

                // Load key from QueryString
                $loadByQuery = false;
                if (($keyValue = Get("assignment_id") ?? Route("assignment_id")) !== null) {
                    $this->assignment_id->setQueryStringValue($keyValue);
                    $loadByQuery = true;
                } else {
                    $this->assignment_id->CurrentValue = null;
                }
            }

            // Set up master detail parameters
            $this->setupMasterParms();

            // Load result set
            if ($this->isShow()) {
                    // Load current record
                    $loaded = $this->loadRow();
                $this->OldKey = $loaded ? $this->getKey(true) : ""; // Get from CurrentValue
            }
        }

        // Process form if post back
        if ($postBack) {
            $this->loadFormValues(); // Get form values
        }

        // Validate form if post back
        if ($postBack) {
            if (!$this->validateForm()) {
                $this->EventCancelled = true; // Event cancelled
                $this->restoreFormValues();
                if (IsApi()) {
                    $this->terminate();
                    return;
                } else {
                    $this->CurrentAction = ""; // Form error, reset action
                }
            }
        }

        // Perform current action
        switch ($this->CurrentAction) {
            case "show": // Get a record to display
                    if (!$loaded) { // Load record based on key
                        if ($this->getFailureMessage() == "") {
                            $this->setFailureMessage($Language->phrase("NoRecord")); // No record found
                        }
                        $this->terminate("UserLevelAssignmentsList"); // No matching record, return to list
                        return;
                    }
                break;
            case "update": // Update
                $returnUrl = $this->getReturnUrl();
                if (GetPageName($returnUrl) == "UserLevelAssignmentsList") {
                    $returnUrl = $this->addMasterUrl($returnUrl); // List page, return to List page with correct master key if necessary
                }
                $this->SendEmail = true; // Send email on update success
                if ($this->editRow()) { // Update record based on key
                    if ($this->getSuccessMessage() == "") {
                        $this->setSuccessMessage($Language->phrase("UpdateSuccess")); // Update success
                    }

                    // Handle UseAjaxActions with return page
                    if ($this->IsModal && $this->UseAjaxActions && !$this->getCurrentMasterTable()) {
                        $this->IsModal = false;
                        if (GetPageName($returnUrl) != "UserLevelAssignmentsList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "UserLevelAssignmentsList"; // Return list page content
                        }
                    }
                    if (IsJsonResponse()) {
                        $this->terminate(true);
                        return;
                    } else {
                        $this->terminate($returnUrl); // Return to caller
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
                } elseif ($this->getFailureMessage() == $Language->phrase("NoRecord")) {
                    $this->terminate($returnUrl); // Return to caller
                    return;
                } else {
                    $this->EventCancelled = true; // Event cancelled
                    $this->restoreFormValues(); // Restore form values if update failed
                }
        }

        // Set up Breadcrumb
        $this->setupBreadcrumb();

        // Render the record
        $this->RowType = RowType::EDIT; // Render as Edit
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
    }

    // Load form values
    protected function loadFormValues()
    {
        // Load from form
        global $CurrentForm;
        $validate = !Config("SERVER_VALIDATE");

        // Check field name 'assignment_id' first before field var 'x_assignment_id'
        $val = $CurrentForm->hasValue("assignment_id") ? $CurrentForm->getValue("assignment_id") : $CurrentForm->getValue("x_assignment_id");
        if (!$this->assignment_id->IsDetailKey) {
            $this->assignment_id->setFormValue($val);
        }

        // Check field name 'system_id' first before field var 'x_system_id'
        $val = $CurrentForm->hasValue("system_id") ? $CurrentForm->getValue("system_id") : $CurrentForm->getValue("x_system_id");
        if (!$this->system_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->system_id->Visible = false; // Disable update for API request
            } else {
                $this->system_id->setFormValue($val);
            }
        }

        // Check field name 'user_level_id' first before field var 'x_user_level_id'
        $val = $CurrentForm->hasValue("user_level_id") ? $CurrentForm->getValue("user_level_id") : $CurrentForm->getValue("x_user_level_id");
        if (!$this->user_level_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->user_level_id->Visible = false; // Disable update for API request
            } else {
                $this->user_level_id->setFormValue($val);
            }
        }

        // Check field name 'user_id' first before field var 'x_user_id'
        $val = $CurrentForm->hasValue("user_id") ? $CurrentForm->getValue("user_id") : $CurrentForm->getValue("x_user_id");
        if (!$this->user_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->user_id->Visible = false; // Disable update for API request
            } else {
                $this->user_id->setFormValue($val);
            }
        }

        // Check field name 'assigned_by' first before field var 'x_assigned_by'
        $val = $CurrentForm->hasValue("assigned_by") ? $CurrentForm->getValue("assigned_by") : $CurrentForm->getValue("x_assigned_by");
        if (!$this->assigned_by->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->assigned_by->Visible = false; // Disable update for API request
            } else {
                $this->assigned_by->setFormValue($val);
            }
        }

        // Check field name 'created_at' first before field var 'x_created_at'
        $val = $CurrentForm->hasValue("created_at") ? $CurrentForm->getValue("created_at") : $CurrentForm->getValue("x_created_at");
        if (!$this->created_at->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->created_at->Visible = false; // Disable update for API request
            } else {
                $this->created_at->setFormValue($val);
            }
            $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        }
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->assignment_id->CurrentValue = $this->assignment_id->FormValue;
        $this->system_id->CurrentValue = $this->system_id->FormValue;
        $this->user_level_id->CurrentValue = $this->user_level_id->FormValue;
        $this->user_id->CurrentValue = $this->user_id->FormValue;
        $this->assigned_by->CurrentValue = $this->assigned_by->FormValue;
        $this->created_at->CurrentValue = $this->created_at->FormValue;
        $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
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
        $this->assignment_id->setDbValue($row['assignment_id']);
        $this->system_id->setDbValue($row['system_id']);
        $this->user_level_id->setDbValue($row['user_level_id']);
        $this->user_id->setDbValue($row['user_id']);
        $this->assigned_by->setDbValue($row['assigned_by']);
        $this->created_at->setDbValue($row['created_at']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['assignment_id'] = $this->assignment_id->DefaultValue;
        $row['system_id'] = $this->system_id->DefaultValue;
        $row['user_level_id'] = $this->user_level_id->DefaultValue;
        $row['user_id'] = $this->user_id->DefaultValue;
        $row['assigned_by'] = $this->assigned_by->DefaultValue;
        $row['created_at'] = $this->created_at->DefaultValue;
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

        // assignment_id
        $this->assignment_id->RowCssClass = "row";

        // system_id
        $this->system_id->RowCssClass = "row";

        // user_level_id
        $this->user_level_id->RowCssClass = "row";

        // user_id
        $this->user_id->RowCssClass = "row";

        // assigned_by
        $this->assigned_by->RowCssClass = "row";

        // created_at
        $this->created_at->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // assignment_id
            $this->assignment_id->ViewValue = $this->assignment_id->CurrentValue;

            // system_id
            $curVal = strval($this->system_id->CurrentValue);
            if ($curVal != "") {
                $this->system_id->ViewValue = $this->system_id->lookupCacheOption($curVal);
                if ($this->system_id->ViewValue === null) { // Lookup from database
                    $filterWrk = SearchFilter($this->system_id->Lookup->getTable()->Fields["system_id"]->searchExpression(), "=", $curVal, $this->system_id->Lookup->getTable()->Fields["system_id"]->searchDataType(), "");
                    $sqlWrk = $this->system_id->Lookup->getSql(false, $filterWrk, '', $this, true, true);
                    $conn = Conn();
                    $config = $conn->getConfiguration();
                    $config->setResultCache($this->Cache);
                    $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                    $ari = count($rswrk);
                    if ($ari > 0) { // Lookup values found
                        $arwrk = $this->system_id->Lookup->renderViewRow($rswrk[0]);
                        $this->system_id->ViewValue = $this->system_id->displayValue($arwrk);
                    } else {
                        $this->system_id->ViewValue = FormatNumber($this->system_id->CurrentValue, $this->system_id->formatPattern());
                    }
                }
            } else {
                $this->system_id->ViewValue = null;
            }

            // user_level_id
            $curVal = strval($this->user_level_id->CurrentValue);
            if ($curVal != "") {
                $this->user_level_id->ViewValue = $this->user_level_id->lookupCacheOption($curVal);
                if ($this->user_level_id->ViewValue === null) { // Lookup from database
                    $filterWrk = SearchFilter($this->user_level_id->Lookup->getTable()->Fields["user_level_id"]->searchExpression(), "=", $curVal, $this->user_level_id->Lookup->getTable()->Fields["user_level_id"]->searchDataType(), "");
                    $sqlWrk = $this->user_level_id->Lookup->getSql(false, $filterWrk, '', $this, true, true);
                    $conn = Conn();
                    $config = $conn->getConfiguration();
                    $config->setResultCache($this->Cache);
                    $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                    $ari = count($rswrk);
                    if ($ari > 0) { // Lookup values found
                        $arwrk = $this->user_level_id->Lookup->renderViewRow($rswrk[0]);
                        $this->user_level_id->ViewValue = $this->user_level_id->displayValue($arwrk);
                    } else {
                        $this->user_level_id->ViewValue = FormatNumber($this->user_level_id->CurrentValue, $this->user_level_id->formatPattern());
                    }
                }
            } else {
                $this->user_level_id->ViewValue = null;
            }

            // user_id
            $curVal = strval($this->user_id->CurrentValue);
            if ($curVal != "") {
                $this->user_id->ViewValue = $this->user_id->lookupCacheOption($curVal);
                if ($this->user_id->ViewValue === null) { // Lookup from database
                    $filterWrk = SearchFilter($this->user_id->Lookup->getTable()->Fields["user_id"]->searchExpression(), "=", $curVal, $this->user_id->Lookup->getTable()->Fields["user_id"]->searchDataType(), "");
                    $sqlWrk = $this->user_id->Lookup->getSql(false, $filterWrk, '', $this, true, true);
                    $conn = Conn();
                    $config = $conn->getConfiguration();
                    $config->setResultCache($this->Cache);
                    $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                    $ari = count($rswrk);
                    if ($ari > 0) { // Lookup values found
                        $arwrk = $this->user_id->Lookup->renderViewRow($rswrk[0]);
                        $this->user_id->ViewValue = $this->user_id->displayValue($arwrk);
                    } else {
                        $this->user_id->ViewValue = FormatNumber($this->user_id->CurrentValue, $this->user_id->formatPattern());
                    }
                }
            } else {
                $this->user_id->ViewValue = null;
            }

            // assigned_by
            $this->assigned_by->ViewValue = $this->assigned_by->CurrentValue;
            $this->assigned_by->ViewValue = FormatNumber($this->assigned_by->ViewValue, $this->assigned_by->formatPattern());

            // created_at
            $this->created_at->ViewValue = $this->created_at->CurrentValue;
            $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

            // assignment_id
            $this->assignment_id->HrefValue = "";

            // system_id
            $this->system_id->HrefValue = "";

            // user_level_id
            $this->user_level_id->HrefValue = "";

            // user_id
            $this->user_id->HrefValue = "";

            // assigned_by
            $this->assigned_by->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";
        } elseif ($this->RowType == RowType::EDIT) {
            // assignment_id
            $this->assignment_id->setupEditAttributes();
            $this->assignment_id->EditValue = $this->assignment_id->CurrentValue;

            // system_id
            $this->system_id->setupEditAttributes();
            $curVal = trim(strval($this->system_id->CurrentValue));
            if ($curVal != "") {
                $this->system_id->ViewValue = $this->system_id->lookupCacheOption($curVal);
            } else {
                $this->system_id->ViewValue = $this->system_id->Lookup !== null && is_array($this->system_id->lookupOptions()) && count($this->system_id->lookupOptions()) > 0 ? $curVal : null;
            }
            if ($this->system_id->ViewValue !== null) { // Load from cache
                $this->system_id->EditValue = array_values($this->system_id->lookupOptions());
            } else { // Lookup from database
                if ($curVal == "") {
                    $filterWrk = "0=1";
                } else {
                    $filterWrk = SearchFilter($this->system_id->Lookup->getTable()->Fields["system_id"]->searchExpression(), "=", $this->system_id->CurrentValue, $this->system_id->Lookup->getTable()->Fields["system_id"]->searchDataType(), "");
                }
                $sqlWrk = $this->system_id->Lookup->getSql(true, $filterWrk, '', $this, false, true);
                $conn = Conn();
                $config = $conn->getConfiguration();
                $config->setResultCache($this->Cache);
                $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                $ari = count($rswrk);
                $arwrk = $rswrk;
                $this->system_id->EditValue = $arwrk;
            }
            $this->system_id->PlaceHolder = RemoveHtml($this->system_id->caption());

            // user_level_id
            $this->user_level_id->setupEditAttributes();
            if ($this->user_level_id->getSessionValue() != "") {
                $this->user_level_id->CurrentValue = GetForeignKeyValue($this->user_level_id->getSessionValue());
                $curVal = strval($this->user_level_id->CurrentValue);
                if ($curVal != "") {
                    $this->user_level_id->ViewValue = $this->user_level_id->lookupCacheOption($curVal);
                    if ($this->user_level_id->ViewValue === null) { // Lookup from database
                        $filterWrk = SearchFilter($this->user_level_id->Lookup->getTable()->Fields["user_level_id"]->searchExpression(), "=", $curVal, $this->user_level_id->Lookup->getTable()->Fields["user_level_id"]->searchDataType(), "");
                        $sqlWrk = $this->user_level_id->Lookup->getSql(false, $filterWrk, '', $this, true, true);
                        $conn = Conn();
                        $config = $conn->getConfiguration();
                        $config->setResultCache($this->Cache);
                        $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                        $ari = count($rswrk);
                        if ($ari > 0) { // Lookup values found
                            $arwrk = $this->user_level_id->Lookup->renderViewRow($rswrk[0]);
                            $this->user_level_id->ViewValue = $this->user_level_id->displayValue($arwrk);
                        } else {
                            $this->user_level_id->ViewValue = FormatNumber($this->user_level_id->CurrentValue, $this->user_level_id->formatPattern());
                        }
                    }
                } else {
                    $this->user_level_id->ViewValue = null;
                }
            } else {
                $curVal = trim(strval($this->user_level_id->CurrentValue));
                if ($curVal != "") {
                    $this->user_level_id->ViewValue = $this->user_level_id->lookupCacheOption($curVal);
                } else {
                    $this->user_level_id->ViewValue = $this->user_level_id->Lookup !== null && is_array($this->user_level_id->lookupOptions()) && count($this->user_level_id->lookupOptions()) > 0 ? $curVal : null;
                }
                if ($this->user_level_id->ViewValue !== null) { // Load from cache
                    $this->user_level_id->EditValue = array_values($this->user_level_id->lookupOptions());
                } else { // Lookup from database
                    if ($curVal == "") {
                        $filterWrk = "0=1";
                    } else {
                        $filterWrk = SearchFilter($this->user_level_id->Lookup->getTable()->Fields["user_level_id"]->searchExpression(), "=", $this->user_level_id->CurrentValue, $this->user_level_id->Lookup->getTable()->Fields["user_level_id"]->searchDataType(), "");
                    }
                    $sqlWrk = $this->user_level_id->Lookup->getSql(true, $filterWrk, '', $this, false, true);
                    $conn = Conn();
                    $config = $conn->getConfiguration();
                    $config->setResultCache($this->Cache);
                    $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                    $ari = count($rswrk);
                    $arwrk = $rswrk;
                    $this->user_level_id->EditValue = $arwrk;
                }
                $this->user_level_id->PlaceHolder = RemoveHtml($this->user_level_id->caption());
            }

            // user_id
            $this->user_id->setupEditAttributes();
            if ($this->user_id->getSessionValue() != "") {
                $this->user_id->CurrentValue = GetForeignKeyValue($this->user_id->getSessionValue());
                $curVal = strval($this->user_id->CurrentValue);
                if ($curVal != "") {
                    $this->user_id->ViewValue = $this->user_id->lookupCacheOption($curVal);
                    if ($this->user_id->ViewValue === null) { // Lookup from database
                        $filterWrk = SearchFilter($this->user_id->Lookup->getTable()->Fields["user_id"]->searchExpression(), "=", $curVal, $this->user_id->Lookup->getTable()->Fields["user_id"]->searchDataType(), "");
                        $sqlWrk = $this->user_id->Lookup->getSql(false, $filterWrk, '', $this, true, true);
                        $conn = Conn();
                        $config = $conn->getConfiguration();
                        $config->setResultCache($this->Cache);
                        $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                        $ari = count($rswrk);
                        if ($ari > 0) { // Lookup values found
                            $arwrk = $this->user_id->Lookup->renderViewRow($rswrk[0]);
                            $this->user_id->ViewValue = $this->user_id->displayValue($arwrk);
                        } else {
                            $this->user_id->ViewValue = FormatNumber($this->user_id->CurrentValue, $this->user_id->formatPattern());
                        }
                    }
                } else {
                    $this->user_id->ViewValue = null;
                }
            } else {
                $curVal = trim(strval($this->user_id->CurrentValue));
                if ($curVal != "") {
                    $this->user_id->ViewValue = $this->user_id->lookupCacheOption($curVal);
                } else {
                    $this->user_id->ViewValue = $this->user_id->Lookup !== null && is_array($this->user_id->lookupOptions()) && count($this->user_id->lookupOptions()) > 0 ? $curVal : null;
                }
                if ($this->user_id->ViewValue !== null) { // Load from cache
                    $this->user_id->EditValue = array_values($this->user_id->lookupOptions());
                } else { // Lookup from database
                    if ($curVal == "") {
                        $filterWrk = "0=1";
                    } else {
                        $filterWrk = SearchFilter($this->user_id->Lookup->getTable()->Fields["user_id"]->searchExpression(), "=", $this->user_id->CurrentValue, $this->user_id->Lookup->getTable()->Fields["user_id"]->searchDataType(), "");
                    }
                    $sqlWrk = $this->user_id->Lookup->getSql(true, $filterWrk, '', $this, false, true);
                    $conn = Conn();
                    $config = $conn->getConfiguration();
                    $config->setResultCache($this->Cache);
                    $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                    $ari = count($rswrk);
                    $arwrk = $rswrk;
                    $this->user_id->EditValue = $arwrk;
                }
                $this->user_id->PlaceHolder = RemoveHtml($this->user_id->caption());
            }

            // assigned_by

            // created_at

            // Edit refer script

            // assignment_id
            $this->assignment_id->HrefValue = "";

            // system_id
            $this->system_id->HrefValue = "";

            // user_level_id
            $this->user_level_id->HrefValue = "";

            // user_id
            $this->user_id->HrefValue = "";

            // assigned_by
            $this->assigned_by->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";
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
            if ($this->assignment_id->Visible && $this->assignment_id->Required) {
                if (!$this->assignment_id->IsDetailKey && EmptyValue($this->assignment_id->FormValue)) {
                    $this->assignment_id->addErrorMessage(str_replace("%s", $this->assignment_id->caption(), $this->assignment_id->RequiredErrorMessage));
                }
            }
            if ($this->system_id->Visible && $this->system_id->Required) {
                if (!$this->system_id->IsDetailKey && EmptyValue($this->system_id->FormValue)) {
                    $this->system_id->addErrorMessage(str_replace("%s", $this->system_id->caption(), $this->system_id->RequiredErrorMessage));
                }
            }
            if ($this->user_level_id->Visible && $this->user_level_id->Required) {
                if (!$this->user_level_id->IsDetailKey && EmptyValue($this->user_level_id->FormValue)) {
                    $this->user_level_id->addErrorMessage(str_replace("%s", $this->user_level_id->caption(), $this->user_level_id->RequiredErrorMessage));
                }
            }
            if ($this->user_id->Visible && $this->user_id->Required) {
                if (!$this->user_id->IsDetailKey && EmptyValue($this->user_id->FormValue)) {
                    $this->user_id->addErrorMessage(str_replace("%s", $this->user_id->caption(), $this->user_id->RequiredErrorMessage));
                }
            }
            if ($this->assigned_by->Visible && $this->assigned_by->Required) {
                if (!$this->assigned_by->IsDetailKey && EmptyValue($this->assigned_by->FormValue)) {
                    $this->assigned_by->addErrorMessage(str_replace("%s", $this->assigned_by->caption(), $this->assigned_by->RequiredErrorMessage));
                }
            }
            if ($this->created_at->Visible && $this->created_at->Required) {
                if (!$this->created_at->IsDetailKey && EmptyValue($this->created_at->FormValue)) {
                    $this->created_at->addErrorMessage(str_replace("%s", $this->created_at->caption(), $this->created_at->RequiredErrorMessage));
                }
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

    // Update record based on key values
    protected function editRow()
    {
        global $Security, $Language;
        $oldKeyFilter = $this->getRecordFilter();
        $filter = $this->applyUserIDFilters($oldKeyFilter);
        $conn = $this->getConnection();

        // Load old row
        $this->CurrentFilter = $filter;
        $sql = $this->getCurrentSql();
        $rsold = $conn->fetchAssociative($sql);
        if (!$rsold) {
            $this->setFailureMessage($Language->phrase("NoRecord")); // Set no record message
            return false; // Update Failed
        } else {
            // Load old values
            $this->loadDbValues($rsold);
        }

        // Get new row
        $rsnew = $this->getEditRow($rsold);

        // Update current values
        $this->setCurrentValues($rsnew);

        // Call Row Updating event
        $updateRow = $this->rowUpdating($rsold, $rsnew);
        if ($updateRow) {
            if (count($rsnew) > 0) {
                $this->CurrentFilter = $filter; // Set up current filter
                $editRow = $this->update($rsnew, "", $rsold);
                if (!$editRow && !EmptyValue($this->DbErrorMessage)) { // Show database error
                    $this->setFailureMessage($this->DbErrorMessage);
                }
            } else {
                $editRow = true; // No field to update
            }
            if ($editRow) {
            }
        } else {
            if ($this->getSuccessMessage() != "" || $this->getFailureMessage() != "") {
                // Use the message, do nothing
            } elseif ($this->CancelMessage != "") {
                $this->setFailureMessage($this->CancelMessage);
                $this->CancelMessage = "";
            } else {
                $this->setFailureMessage($Language->phrase("UpdateCancelled"));
            }
            $editRow = false;
        }

        // Call Row_Updated event
        if ($editRow) {
            $this->rowUpdated($rsold, $rsnew);
        }

        // Write JSON response
        if (IsJsonResponse() && $editRow) {
            $row = $this->getRecordsFromRecordset([$rsnew], true);
            $table = $this->TableVar;
            WriteJson(["success" => true, "action" => Config("API_EDIT_ACTION"), $table => $row]);
        }
        return $editRow;
    }

    /**
     * Get edit row
     *
     * @return array
     */
    protected function getEditRow($rsold)
    {
        global $Security;
        $rsnew = [];

        // system_id
        $this->system_id->setDbValueDef($rsnew, $this->system_id->CurrentValue, $this->system_id->ReadOnly);

        // user_level_id
        if ($this->user_level_id->getSessionValue() != "") {
            $this->user_level_id->ReadOnly = true;
        }
        $this->user_level_id->setDbValueDef($rsnew, $this->user_level_id->CurrentValue, $this->user_level_id->ReadOnly);

        // user_id
        if ($this->user_id->getSessionValue() != "") {
            $this->user_id->ReadOnly = true;
        }
        $this->user_id->setDbValueDef($rsnew, $this->user_id->CurrentValue, $this->user_id->ReadOnly);

        // assigned_by
        $this->assigned_by->CurrentValue = $this->assigned_by->getAutoUpdateValue(); // PHP
        $this->assigned_by->setDbValueDef($rsnew, $this->assigned_by->CurrentValue, $this->assigned_by->ReadOnly);

        // created_at
        $this->created_at->CurrentValue = $this->created_at->getAutoUpdateValue(); // PHP
        $this->created_at->setDbValueDef($rsnew, UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()), $this->created_at->ReadOnly);
        return $rsnew;
    }

    /**
     * Restore edit form from row
     * @param array $row Row
     */
    protected function restoreEditFormFromRow($row)
    {
        if (isset($row['system_id'])) { // system_id
            $this->system_id->CurrentValue = $row['system_id'];
        }
        if (isset($row['user_level_id'])) { // user_level_id
            $this->user_level_id->CurrentValue = $row['user_level_id'];
        }
        if (isset($row['user_id'])) { // user_id
            $this->user_id->CurrentValue = $row['user_id'];
        }
        if (isset($row['assigned_by'])) { // assigned_by
            $this->assigned_by->CurrentValue = $row['assigned_by'];
        }
        if (isset($row['created_at'])) { // created_at
            $this->created_at->CurrentValue = $row['created_at'];
        }
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
            if ($masterTblVar == "users") {
                $validMaster = true;
                $masterTbl = Container("users");
                if (($parm = Get("fk_user_id", Get("user_id"))) !== null) {
                    $masterTbl->user_id->setQueryStringValue($parm);
                    $this->user_id->QueryStringValue = $masterTbl->user_id->QueryStringValue; // DO NOT change, master/detail key data type can be different
                    $this->user_id->setSessionValue($this->user_id->QueryStringValue);
                    $foreignKeys["user_id"] = $this->user_id->QueryStringValue;
                    if (!is_numeric($masterTbl->user_id->QueryStringValue)) {
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
            if ($masterTblVar == "users") {
                $validMaster = true;
                $masterTbl = Container("users");
                if (($parm = Post("fk_user_id", Post("user_id"))) !== null) {
                    $masterTbl->user_id->setFormValue($parm);
                    $this->user_id->FormValue = $masterTbl->user_id->FormValue;
                    $this->user_id->setSessionValue($this->user_id->FormValue);
                    $foreignKeys["user_id"] = $this->user_id->FormValue;
                    if (!is_numeric($masterTbl->user_id->FormValue)) {
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
            $this->setSessionWhere($this->getDetailFilterFromSession());

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
            if ($masterTblVar != "users") {
                if (!array_key_exists("user_id", $foreignKeys)) { // Not current foreign key
                    $this->user_id->setSessionValue("");
                }
            }
        }
        $this->DbMasterFilter = $this->getMasterFilterFromSession(); // Get master filter from session
        $this->DbDetailFilter = $this->getDetailFilterFromSession(); // Get detail filter from session
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("UserLevelAssignmentsList"), "", $this->TableVar, true);
        $pageId = "edit";
        $Breadcrumb->add("edit", $pageId, $url);
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
                case "x_system_id":
                    break;
                case "x_user_level_id":
                    break;
                case "x_user_id":
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

    // Set up starting record parameters
    public function setupStartRecord()
    {
        if ($this->DisplayRecords == 0) {
            return;
        }
        $pageNo = Get(Config("TABLE_PAGE_NUMBER"));
        $startRec = Get(Config("TABLE_START_REC"));
        $infiniteScroll = false;
        $recordNo = $pageNo ?? $startRec; // Record number = page number or start record
        if ($recordNo !== null && is_numeric($recordNo)) {
            $this->StartRecord = $recordNo;
        } else {
            $this->StartRecord = $this->getStartRecordNumber();
        }

        // Check if correct start record counter
        if (!is_numeric($this->StartRecord) || intval($this->StartRecord) <= 0) { // Avoid invalid start record counter
            $this->StartRecord = 1; // Reset start record counter
        } elseif ($this->StartRecord > $this->TotalRecords) { // Avoid starting record > total records
            $this->StartRecord = (int)(($this->TotalRecords - 1) / $this->DisplayRecords) * $this->DisplayRecords + 1; // Point to last page first record
        } elseif (($this->StartRecord - 1) % $this->DisplayRecords != 0) {
            $this->StartRecord = (int)(($this->StartRecord - 1) / $this->DisplayRecords) * $this->DisplayRecords + 1; // Point to page boundary
        }
        if (!$infiniteScroll) {
            $this->setStartRecordNumber($this->StartRecord);
        }
    }

    // Get page count
    public function pageCount() {
        return ceil($this->TotalRecords / $this->DisplayRecords);
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
