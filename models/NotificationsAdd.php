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
class NotificationsAdd extends Notifications
{
    use MessagesTrait;

    // Page ID
    public $PageID = "add";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "NotificationsAdd";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "NotificationsAdd";

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
        $this->id->setVisibility();
        $this->timestamp->setVisibility();
        $this->type->setVisibility();
        $this->target->setVisibility();
        $this->user_id->setVisibility();
        $this->subject->setVisibility();
        $this->body->setVisibility();
        $this->link->setVisibility();
        $this->from_system->setVisibility();
        $this->is_read->setVisibility();
        $this->created_at->setVisibility();
        $this->read_users->setVisibility();
        $this->sender_id->setVisibility();
        $this->event_key->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'notifications';
        $this->TableName = 'notifications';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-add-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (notifications)
        if (!isset($GLOBALS["notifications"]) || $GLOBALS["notifications"]::class == PROJECT_NAMESPACE . "notifications") {
            $GLOBALS["notifications"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'notifications');
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
                        $result["view"] = SameString($pageName, "NotificationsView"); // If View page, no primary button
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
            $key .= @$ar['id'];
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
        $this->setupLookupOptions($this->is_read);

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
            if (($keyValue = Get("id") ?? Route("id")) !== null) {
                $this->id->setQueryStringValue($keyValue);
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

        // Load form values
        if ($postBack) {
            $this->loadFormValues(); // Load form values
        }

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
                    $this->terminate("NotificationsList"); // No matching record, return to list
                    return;
                }
                break;
            case "insert": // Add new record
                $this->SendEmail = true; // Send email on add success
                if ($this->addRow($rsold)) { // Add successful
                    if ($this->getSuccessMessage() == "" && Post("addopt") != "1") { // Skip success message for addopt (done in JavaScript)
                        $this->setSuccessMessage($Language->phrase("AddSuccess")); // Set up success message
                    }
                    $returnUrl = $this->getReturnUrl();
                    if (GetPageName($returnUrl) == "NotificationsList") {
                        $returnUrl = $this->addMasterUrl($returnUrl); // List page, return to List page with correct master key if necessary
                    } elseif (GetPageName($returnUrl) == "NotificationsView") {
                        $returnUrl = $this->getViewUrl(); // View page, return to View page with keyurl directly
                    }

                    // Handle UseAjaxActions with return page
                    if ($this->IsModal && $this->UseAjaxActions) {
                        $this->IsModal = false;
                        if (GetPageName($returnUrl) != "NotificationsList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "NotificationsList"; // Return list page content
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

        // Check field name 'id' first before field var 'x_id'
        $val = $CurrentForm->hasValue("id") ? $CurrentForm->getValue("id") : $CurrentForm->getValue("x_id");
        if (!$this->id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->id->Visible = false; // Disable update for API request
            } else {
                $this->id->setFormValue($val);
            }
        }

        // Check field name 'timestamp' first before field var 'x_timestamp'
        $val = $CurrentForm->hasValue("timestamp") ? $CurrentForm->getValue("timestamp") : $CurrentForm->getValue("x_timestamp");
        if (!$this->timestamp->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->timestamp->Visible = false; // Disable update for API request
            } else {
                $this->timestamp->setFormValue($val, true, $validate);
            }
            $this->timestamp->CurrentValue = UnFormatDateTime($this->timestamp->CurrentValue, $this->timestamp->formatPattern());
        }

        // Check field name 'type' first before field var 'x_type'
        $val = $CurrentForm->hasValue("type") ? $CurrentForm->getValue("type") : $CurrentForm->getValue("x_type");
        if (!$this->type->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->type->Visible = false; // Disable update for API request
            } else {
                $this->type->setFormValue($val);
            }
        }

        // Check field name 'target' first before field var 'x_target'
        $val = $CurrentForm->hasValue("target") ? $CurrentForm->getValue("target") : $CurrentForm->getValue("x_target");
        if (!$this->target->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->target->Visible = false; // Disable update for API request
            } else {
                $this->target->setFormValue($val);
            }
        }

        // Check field name 'user_id' first before field var 'x_user_id'
        $val = $CurrentForm->hasValue("user_id") ? $CurrentForm->getValue("user_id") : $CurrentForm->getValue("x_user_id");
        if (!$this->user_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->user_id->Visible = false; // Disable update for API request
            } else {
                $this->user_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'subject' first before field var 'x_subject'
        $val = $CurrentForm->hasValue("subject") ? $CurrentForm->getValue("subject") : $CurrentForm->getValue("x_subject");
        if (!$this->subject->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->subject->Visible = false; // Disable update for API request
            } else {
                $this->subject->setFormValue($val);
            }
        }

        // Check field name 'body' first before field var 'x_body'
        $val = $CurrentForm->hasValue("body") ? $CurrentForm->getValue("body") : $CurrentForm->getValue("x_body");
        if (!$this->body->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->body->Visible = false; // Disable update for API request
            } else {
                $this->body->setFormValue($val);
            }
        }

        // Check field name 'link' first before field var 'x_link'
        $val = $CurrentForm->hasValue("link") ? $CurrentForm->getValue("link") : $CurrentForm->getValue("x_link");
        if (!$this->link->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->link->Visible = false; // Disable update for API request
            } else {
                $this->link->setFormValue($val);
            }
        }

        // Check field name 'from_system' first before field var 'x_from_system'
        $val = $CurrentForm->hasValue("from_system") ? $CurrentForm->getValue("from_system") : $CurrentForm->getValue("x_from_system");
        if (!$this->from_system->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->from_system->Visible = false; // Disable update for API request
            } else {
                $this->from_system->setFormValue($val);
            }
        }

        // Check field name 'is_read' first before field var 'x_is_read'
        $val = $CurrentForm->hasValue("is_read") ? $CurrentForm->getValue("is_read") : $CurrentForm->getValue("x_is_read");
        if (!$this->is_read->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->is_read->Visible = false; // Disable update for API request
            } else {
                $this->is_read->setFormValue($val);
            }
        }

        // Check field name 'created_at' first before field var 'x_created_at'
        $val = $CurrentForm->hasValue("created_at") ? $CurrentForm->getValue("created_at") : $CurrentForm->getValue("x_created_at");
        if (!$this->created_at->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->created_at->Visible = false; // Disable update for API request
            } else {
                $this->created_at->setFormValue($val, true, $validate);
            }
            $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        }

        // Check field name 'read_users' first before field var 'x_read_users'
        $val = $CurrentForm->hasValue("read_users") ? $CurrentForm->getValue("read_users") : $CurrentForm->getValue("x_read_users");
        if (!$this->read_users->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->read_users->Visible = false; // Disable update for API request
            } else {
                $this->read_users->setFormValue($val);
            }
        }

        // Check field name 'sender_id' first before field var 'x_sender_id'
        $val = $CurrentForm->hasValue("sender_id") ? $CurrentForm->getValue("sender_id") : $CurrentForm->getValue("x_sender_id");
        if (!$this->sender_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->sender_id->Visible = false; // Disable update for API request
            } else {
                $this->sender_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'event_key' first before field var 'x_event_key'
        $val = $CurrentForm->hasValue("event_key") ? $CurrentForm->getValue("event_key") : $CurrentForm->getValue("x_event_key");
        if (!$this->event_key->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->event_key->Visible = false; // Disable update for API request
            } else {
                $this->event_key->setFormValue($val);
            }
        }
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->id->CurrentValue = $this->id->FormValue;
        $this->timestamp->CurrentValue = $this->timestamp->FormValue;
        $this->timestamp->CurrentValue = UnFormatDateTime($this->timestamp->CurrentValue, $this->timestamp->formatPattern());
        $this->type->CurrentValue = $this->type->FormValue;
        $this->target->CurrentValue = $this->target->FormValue;
        $this->user_id->CurrentValue = $this->user_id->FormValue;
        $this->subject->CurrentValue = $this->subject->FormValue;
        $this->body->CurrentValue = $this->body->FormValue;
        $this->link->CurrentValue = $this->link->FormValue;
        $this->from_system->CurrentValue = $this->from_system->FormValue;
        $this->is_read->CurrentValue = $this->is_read->FormValue;
        $this->created_at->CurrentValue = $this->created_at->FormValue;
        $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->read_users->CurrentValue = $this->read_users->FormValue;
        $this->sender_id->CurrentValue = $this->sender_id->FormValue;
        $this->event_key->CurrentValue = $this->event_key->FormValue;
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
        $this->id->setDbValue($row['id']);
        $this->timestamp->setDbValue($row['timestamp']);
        $this->type->setDbValue($row['type']);
        $this->target->setDbValue($row['target']);
        $this->user_id->setDbValue($row['user_id']);
        $this->subject->setDbValue($row['subject']);
        $this->body->setDbValue($row['body']);
        $this->link->setDbValue($row['link']);
        $this->from_system->setDbValue($row['from_system']);
        $this->is_read->setDbValue((ConvertToBool($row['is_read']) ? "1" : "0"));
        $this->created_at->setDbValue($row['created_at']);
        $this->read_users->setDbValue($row['read_users']);
        $this->sender_id->setDbValue($row['sender_id']);
        $this->event_key->setDbValue($row['event_key']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['id'] = $this->id->DefaultValue;
        $row['timestamp'] = $this->timestamp->DefaultValue;
        $row['type'] = $this->type->DefaultValue;
        $row['target'] = $this->target->DefaultValue;
        $row['user_id'] = $this->user_id->DefaultValue;
        $row['subject'] = $this->subject->DefaultValue;
        $row['body'] = $this->body->DefaultValue;
        $row['link'] = $this->link->DefaultValue;
        $row['from_system'] = $this->from_system->DefaultValue;
        $row['is_read'] = $this->is_read->DefaultValue;
        $row['created_at'] = $this->created_at->DefaultValue;
        $row['read_users'] = $this->read_users->DefaultValue;
        $row['sender_id'] = $this->sender_id->DefaultValue;
        $row['event_key'] = $this->event_key->DefaultValue;
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

        // id
        $this->id->RowCssClass = "row";

        // timestamp
        $this->timestamp->RowCssClass = "row";

        // type
        $this->type->RowCssClass = "row";

        // target
        $this->target->RowCssClass = "row";

        // user_id
        $this->user_id->RowCssClass = "row";

        // subject
        $this->subject->RowCssClass = "row";

        // body
        $this->body->RowCssClass = "row";

        // link
        $this->link->RowCssClass = "row";

        // from_system
        $this->from_system->RowCssClass = "row";

        // is_read
        $this->is_read->RowCssClass = "row";

        // created_at
        $this->created_at->RowCssClass = "row";

        // read_users
        $this->read_users->RowCssClass = "row";

        // sender_id
        $this->sender_id->RowCssClass = "row";

        // event_key
        $this->event_key->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // id
            $this->id->ViewValue = $this->id->CurrentValue;

            // timestamp
            $this->timestamp->ViewValue = $this->timestamp->CurrentValue;
            $this->timestamp->ViewValue = FormatDateTime($this->timestamp->ViewValue, $this->timestamp->formatPattern());

            // type
            $this->type->ViewValue = $this->type->CurrentValue;

            // target
            $this->target->ViewValue = $this->target->CurrentValue;

            // user_id
            $this->user_id->ViewValue = $this->user_id->CurrentValue;
            $this->user_id->ViewValue = FormatNumber($this->user_id->ViewValue, $this->user_id->formatPattern());

            // subject
            $this->subject->ViewValue = $this->subject->CurrentValue;

            // body
            $this->body->ViewValue = $this->body->CurrentValue;

            // link
            $this->link->ViewValue = $this->link->CurrentValue;

            // from_system
            $this->from_system->ViewValue = $this->from_system->CurrentValue;

            // is_read
            if (ConvertToBool($this->is_read->CurrentValue)) {
                $this->is_read->ViewValue = $this->is_read->tagCaption(1) != "" ? $this->is_read->tagCaption(1) : "Yes";
            } else {
                $this->is_read->ViewValue = $this->is_read->tagCaption(2) != "" ? $this->is_read->tagCaption(2) : "No";
            }

            // created_at
            $this->created_at->ViewValue = $this->created_at->CurrentValue;
            $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

            // read_users
            $this->read_users->ViewValue = $this->read_users->CurrentValue;

            // sender_id
            $this->sender_id->ViewValue = $this->sender_id->CurrentValue;
            $this->sender_id->ViewValue = FormatNumber($this->sender_id->ViewValue, $this->sender_id->formatPattern());

            // event_key
            $this->event_key->ViewValue = $this->event_key->CurrentValue;

            // id
            $this->id->HrefValue = "";

            // timestamp
            $this->timestamp->HrefValue = "";

            // type
            $this->type->HrefValue = "";

            // target
            $this->target->HrefValue = "";

            // user_id
            $this->user_id->HrefValue = "";

            // subject
            $this->subject->HrefValue = "";

            // body
            $this->body->HrefValue = "";

            // link
            $this->link->HrefValue = "";

            // from_system
            $this->from_system->HrefValue = "";

            // is_read
            $this->is_read->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // read_users
            $this->read_users->HrefValue = "";

            // sender_id
            $this->sender_id->HrefValue = "";

            // event_key
            $this->event_key->HrefValue = "";
        } elseif ($this->RowType == RowType::ADD) {
            // id
            $this->id->setupEditAttributes();
            if (!$this->id->Raw) {
                $this->id->CurrentValue = HtmlDecode($this->id->CurrentValue);
            }
            $this->id->EditValue = HtmlEncode($this->id->CurrentValue);
            $this->id->PlaceHolder = RemoveHtml($this->id->caption());

            // timestamp
            $this->timestamp->setupEditAttributes();
            $this->timestamp->EditValue = HtmlEncode(FormatDateTime($this->timestamp->CurrentValue, $this->timestamp->formatPattern()));
            $this->timestamp->PlaceHolder = RemoveHtml($this->timestamp->caption());

            // type
            $this->type->setupEditAttributes();
            if (!$this->type->Raw) {
                $this->type->CurrentValue = HtmlDecode($this->type->CurrentValue);
            }
            $this->type->EditValue = HtmlEncode($this->type->CurrentValue);
            $this->type->PlaceHolder = RemoveHtml($this->type->caption());

            // target
            $this->target->setupEditAttributes();
            if (!$this->target->Raw) {
                $this->target->CurrentValue = HtmlDecode($this->target->CurrentValue);
            }
            $this->target->EditValue = HtmlEncode($this->target->CurrentValue);
            $this->target->PlaceHolder = RemoveHtml($this->target->caption());

            // user_id
            $this->user_id->setupEditAttributes();
            $this->user_id->EditValue = $this->user_id->CurrentValue;
            $this->user_id->PlaceHolder = RemoveHtml($this->user_id->caption());
            if (strval($this->user_id->EditValue) != "" && is_numeric($this->user_id->EditValue)) {
                $this->user_id->EditValue = FormatNumber($this->user_id->EditValue, null);
            }

            // subject
            $this->subject->setupEditAttributes();
            if (!$this->subject->Raw) {
                $this->subject->CurrentValue = HtmlDecode($this->subject->CurrentValue);
            }
            $this->subject->EditValue = HtmlEncode($this->subject->CurrentValue);
            $this->subject->PlaceHolder = RemoveHtml($this->subject->caption());

            // body
            $this->body->setupEditAttributes();
            $this->body->EditValue = HtmlEncode($this->body->CurrentValue);
            $this->body->PlaceHolder = RemoveHtml($this->body->caption());

            // link
            $this->link->setupEditAttributes();
            if (!$this->link->Raw) {
                $this->link->CurrentValue = HtmlDecode($this->link->CurrentValue);
            }
            $this->link->EditValue = HtmlEncode($this->link->CurrentValue);
            $this->link->PlaceHolder = RemoveHtml($this->link->caption());

            // from_system
            $this->from_system->setupEditAttributes();
            if (!$this->from_system->Raw) {
                $this->from_system->CurrentValue = HtmlDecode($this->from_system->CurrentValue);
            }
            $this->from_system->EditValue = HtmlEncode($this->from_system->CurrentValue);
            $this->from_system->PlaceHolder = RemoveHtml($this->from_system->caption());

            // is_read
            $this->is_read->EditValue = $this->is_read->options(false);
            $this->is_read->PlaceHolder = RemoveHtml($this->is_read->caption());

            // created_at
            $this->created_at->setupEditAttributes();
            $this->created_at->EditValue = HtmlEncode(FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()));
            $this->created_at->PlaceHolder = RemoveHtml($this->created_at->caption());

            // read_users
            $this->read_users->setupEditAttributes();
            $this->read_users->EditValue = HtmlEncode($this->read_users->CurrentValue);
            $this->read_users->PlaceHolder = RemoveHtml($this->read_users->caption());

            // sender_id
            $this->sender_id->setupEditAttributes();
            $this->sender_id->EditValue = $this->sender_id->CurrentValue;
            $this->sender_id->PlaceHolder = RemoveHtml($this->sender_id->caption());
            if (strval($this->sender_id->EditValue) != "" && is_numeric($this->sender_id->EditValue)) {
                $this->sender_id->EditValue = FormatNumber($this->sender_id->EditValue, null);
            }

            // event_key
            $this->event_key->setupEditAttributes();
            if (!$this->event_key->Raw) {
                $this->event_key->CurrentValue = HtmlDecode($this->event_key->CurrentValue);
            }
            $this->event_key->EditValue = HtmlEncode($this->event_key->CurrentValue);
            $this->event_key->PlaceHolder = RemoveHtml($this->event_key->caption());

            // Add refer script

            // id
            $this->id->HrefValue = "";

            // timestamp
            $this->timestamp->HrefValue = "";

            // type
            $this->type->HrefValue = "";

            // target
            $this->target->HrefValue = "";

            // user_id
            $this->user_id->HrefValue = "";

            // subject
            $this->subject->HrefValue = "";

            // body
            $this->body->HrefValue = "";

            // link
            $this->link->HrefValue = "";

            // from_system
            $this->from_system->HrefValue = "";

            // is_read
            $this->is_read->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // read_users
            $this->read_users->HrefValue = "";

            // sender_id
            $this->sender_id->HrefValue = "";

            // event_key
            $this->event_key->HrefValue = "";
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
            if ($this->id->Visible && $this->id->Required) {
                if (!$this->id->IsDetailKey && EmptyValue($this->id->FormValue)) {
                    $this->id->addErrorMessage(str_replace("%s", $this->id->caption(), $this->id->RequiredErrorMessage));
                }
            }
            if ($this->timestamp->Visible && $this->timestamp->Required) {
                if (!$this->timestamp->IsDetailKey && EmptyValue($this->timestamp->FormValue)) {
                    $this->timestamp->addErrorMessage(str_replace("%s", $this->timestamp->caption(), $this->timestamp->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->timestamp->FormValue, $this->timestamp->formatPattern())) {
                $this->timestamp->addErrorMessage($this->timestamp->getErrorMessage(false));
            }
            if ($this->type->Visible && $this->type->Required) {
                if (!$this->type->IsDetailKey && EmptyValue($this->type->FormValue)) {
                    $this->type->addErrorMessage(str_replace("%s", $this->type->caption(), $this->type->RequiredErrorMessage));
                }
            }
            if ($this->target->Visible && $this->target->Required) {
                if (!$this->target->IsDetailKey && EmptyValue($this->target->FormValue)) {
                    $this->target->addErrorMessage(str_replace("%s", $this->target->caption(), $this->target->RequiredErrorMessage));
                }
            }
            if ($this->user_id->Visible && $this->user_id->Required) {
                if (!$this->user_id->IsDetailKey && EmptyValue($this->user_id->FormValue)) {
                    $this->user_id->addErrorMessage(str_replace("%s", $this->user_id->caption(), $this->user_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->user_id->FormValue)) {
                $this->user_id->addErrorMessage($this->user_id->getErrorMessage(false));
            }
            if ($this->subject->Visible && $this->subject->Required) {
                if (!$this->subject->IsDetailKey && EmptyValue($this->subject->FormValue)) {
                    $this->subject->addErrorMessage(str_replace("%s", $this->subject->caption(), $this->subject->RequiredErrorMessage));
                }
            }
            if ($this->body->Visible && $this->body->Required) {
                if (!$this->body->IsDetailKey && EmptyValue($this->body->FormValue)) {
                    $this->body->addErrorMessage(str_replace("%s", $this->body->caption(), $this->body->RequiredErrorMessage));
                }
            }
            if ($this->link->Visible && $this->link->Required) {
                if (!$this->link->IsDetailKey && EmptyValue($this->link->FormValue)) {
                    $this->link->addErrorMessage(str_replace("%s", $this->link->caption(), $this->link->RequiredErrorMessage));
                }
            }
            if ($this->from_system->Visible && $this->from_system->Required) {
                if (!$this->from_system->IsDetailKey && EmptyValue($this->from_system->FormValue)) {
                    $this->from_system->addErrorMessage(str_replace("%s", $this->from_system->caption(), $this->from_system->RequiredErrorMessage));
                }
            }
            if ($this->is_read->Visible && $this->is_read->Required) {
                if ($this->is_read->FormValue == "") {
                    $this->is_read->addErrorMessage(str_replace("%s", $this->is_read->caption(), $this->is_read->RequiredErrorMessage));
                }
            }
            if ($this->created_at->Visible && $this->created_at->Required) {
                if (!$this->created_at->IsDetailKey && EmptyValue($this->created_at->FormValue)) {
                    $this->created_at->addErrorMessage(str_replace("%s", $this->created_at->caption(), $this->created_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->created_at->FormValue, $this->created_at->formatPattern())) {
                $this->created_at->addErrorMessage($this->created_at->getErrorMessage(false));
            }
            if ($this->read_users->Visible && $this->read_users->Required) {
                if (!$this->read_users->IsDetailKey && EmptyValue($this->read_users->FormValue)) {
                    $this->read_users->addErrorMessage(str_replace("%s", $this->read_users->caption(), $this->read_users->RequiredErrorMessage));
                }
            }
            if ($this->sender_id->Visible && $this->sender_id->Required) {
                if (!$this->sender_id->IsDetailKey && EmptyValue($this->sender_id->FormValue)) {
                    $this->sender_id->addErrorMessage(str_replace("%s", $this->sender_id->caption(), $this->sender_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->sender_id->FormValue)) {
                $this->sender_id->addErrorMessage($this->sender_id->getErrorMessage(false));
            }
            if ($this->event_key->Visible && $this->event_key->Required) {
                if (!$this->event_key->IsDetailKey && EmptyValue($this->event_key->FormValue)) {
                    $this->event_key->addErrorMessage(str_replace("%s", $this->event_key->caption(), $this->event_key->RequiredErrorMessage));
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

    // Add record
    protected function addRow($rsold = null)
    {
        global $Language, $Security;

        // Get new row
        $rsnew = $this->getAddRow();

        // Update current values
        $this->setCurrentValues($rsnew);
        if ($this->id->CurrentValue != "") { // Check field with unique index
            $filter = "(\"id\" = '" . AdjustSql($this->id->CurrentValue, $this->Dbid) . "')";
            $rsChk = $this->loadRs($filter)->fetch();
            if ($rsChk !== false) {
                $idxErrMsg = str_replace("%f", $this->id->caption(), $Language->phrase("DupIndex"));
                $idxErrMsg = str_replace("%v", $this->id->CurrentValue, $idxErrMsg);
                $this->setFailureMessage($idxErrMsg);
                return false;
            }
        }
        if ($this->event_key->CurrentValue != "") { // Check field with unique index
            $filter = "(\"event_key\" = '" . AdjustSql($this->event_key->CurrentValue, $this->Dbid) . "')";
            $rsChk = $this->loadRs($filter)->fetch();
            if ($rsChk !== false) {
                $idxErrMsg = str_replace("%f", $this->event_key->caption(), $Language->phrase("DupIndex"));
                $idxErrMsg = str_replace("%v", $this->event_key->CurrentValue, $idxErrMsg);
                $this->setFailureMessage($idxErrMsg);
                return false;
            }
        }
        $conn = $this->getConnection();

        // Load db values from old row
        $this->loadDbValues($rsold);

        // Call Row Inserting event
        $insertRow = $this->rowInserting($rsold, $rsnew);

        // Check if key value entered
        if ($insertRow && $this->ValidateKey && strval($rsnew['id']) == "") {
            $this->setFailureMessage($Language->phrase("InvalidKeyValue"));
            $insertRow = false;
        }

        // Check for duplicate key
        if ($insertRow && $this->ValidateKey) {
            $filter = $this->getRecordFilter($rsnew);
            $rsChk = $this->loadRs($filter)->fetch();
            if ($rsChk !== false) {
                $keyErrMsg = str_replace("%f", $filter, $Language->phrase("DupKey"));
                $this->setFailureMessage($keyErrMsg);
                $insertRow = false;
            }
        }
        if ($insertRow) {
            $addRow = $this->insert($rsnew);
            if ($addRow) {
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

        // id
        $this->id->setDbValueDef($rsnew, $this->id->CurrentValue, false);

        // timestamp
        $this->timestamp->setDbValueDef($rsnew, UnFormatDateTime($this->timestamp->CurrentValue, $this->timestamp->formatPattern()), false);

        // type
        $this->type->setDbValueDef($rsnew, $this->type->CurrentValue, false);

        // target
        $this->target->setDbValueDef($rsnew, $this->target->CurrentValue, false);

        // user_id
        $this->user_id->setDbValueDef($rsnew, $this->user_id->CurrentValue, false);

        // subject
        $this->subject->setDbValueDef($rsnew, $this->subject->CurrentValue, false);

        // body
        $this->body->setDbValueDef($rsnew, $this->body->CurrentValue, false);

        // link
        $this->link->setDbValueDef($rsnew, $this->link->CurrentValue, false);

        // from_system
        $this->from_system->setDbValueDef($rsnew, $this->from_system->CurrentValue, false);

        // is_read
        $tmpBool = $this->is_read->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_read->setDbValueDef($rsnew, $tmpBool, strval($this->is_read->CurrentValue) == "");

        // created_at
        $this->created_at->setDbValueDef($rsnew, UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()), strval($this->created_at->CurrentValue) == "");

        // read_users
        $this->read_users->setDbValueDef($rsnew, $this->read_users->CurrentValue, false);

        // sender_id
        $this->sender_id->setDbValueDef($rsnew, $this->sender_id->CurrentValue, false);

        // event_key
        $this->event_key->setDbValueDef($rsnew, $this->event_key->CurrentValue, false);
        return $rsnew;
    }

    /**
     * Restore add form from row
     * @param array $row Row
     */
    protected function restoreAddFormFromRow($row)
    {
        if (isset($row['id'])) { // id
            $this->id->setFormValue($row['id']);
        }
        if (isset($row['timestamp'])) { // timestamp
            $this->timestamp->setFormValue($row['timestamp']);
        }
        if (isset($row['type'])) { // type
            $this->type->setFormValue($row['type']);
        }
        if (isset($row['target'])) { // target
            $this->target->setFormValue($row['target']);
        }
        if (isset($row['user_id'])) { // user_id
            $this->user_id->setFormValue($row['user_id']);
        }
        if (isset($row['subject'])) { // subject
            $this->subject->setFormValue($row['subject']);
        }
        if (isset($row['body'])) { // body
            $this->body->setFormValue($row['body']);
        }
        if (isset($row['link'])) { // link
            $this->link->setFormValue($row['link']);
        }
        if (isset($row['from_system'])) { // from_system
            $this->from_system->setFormValue($row['from_system']);
        }
        if (isset($row['is_read'])) { // is_read
            $this->is_read->setFormValue($row['is_read']);
        }
        if (isset($row['created_at'])) { // created_at
            $this->created_at->setFormValue($row['created_at']);
        }
        if (isset($row['read_users'])) { // read_users
            $this->read_users->setFormValue($row['read_users']);
        }
        if (isset($row['sender_id'])) { // sender_id
            $this->sender_id->setFormValue($row['sender_id']);
        }
        if (isset($row['event_key'])) { // event_key
            $this->event_key->setFormValue($row['event_key']);
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("NotificationsList"), "", $this->TableVar, true);
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
                case "x_is_read":
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
