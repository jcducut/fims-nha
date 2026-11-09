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
class Login extends Users
{
    use MessagesTrait;

    // Page ID
    public $PageID = "login";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "Login";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "login";

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

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'users';
        $this->TableName = 'users';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-view-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (users)
        if (!isset($GLOBALS["users"]) || $GLOBALS["users"]::class == PROJECT_NAMESPACE . "users") {
            $GLOBALS["users"] = &$this;
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
                WriteJson(["url" => $url]);
            } else {
                SaveDebugMessage();
                Redirect(GetUrl($url));
            }
        }
        return; // Return to controller
    }

    // Properties
    public $Username;
    public $Password;
    public $LoginType;
    public $IsModal = false;
    public $OffsetColumnClass = ""; // Override user table

    /**
     * Page run
     *
     * @return void
     */
    public function run()
    {
        global $ExportType, $Language, $Security, $CurrentForm, $Breadcrumb, $SkipHeaderFooter;

        // Create Username/Password field object (used by validation only)
        $this->Username = new DbField("users", "username", "username", "username", "", 202, 255, -1, false, "", false, false, false);
        $this->Username->EditAttrs->appendClass("form-control ew-form-control");
        $this->Password = new DbField("users", "password", "password", "password", "", 202, 255, -1, false, "", false, false, false);
        $this->Password->EditAttrs->appendClass("form-control ew-form-control");
        if (Config("ENCRYPTED_PASSWORD")) {
            $this->Password->Raw = true;
        }
        $this->LoginType = new DbField("users", "type", "logintype", "logintype", "", 202, 255, -1, false, "", false, false, false);

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
        }
        $this->CurrentAction = Param("action"); // Set up current action

        // Global Page Loading event (in userfn*.php)
        DispatchEvent(new PageLoadingEvent($this), PageLoadingEvent::NAME);

        // Page Load event
        if (method_exists($this, "pageLoad")) {
            $this->pageLoad();
        }

        // Check modal
        if ($this->IsModal) {
            $SkipHeaderFooter = true;
        }
        $Breadcrumb = Breadcrumb::create("index")->add("login", "LoginPage", CurrentUrl(), "", "", true);
        $this->Heading = $Language->phrase("LoginPage");
        $this->Username->setFormValue(""); // Initialize
        $this->Password->setFormValue("");
        $this->LoginType->setFormValue("");
        $lastUrl = $Security->lastUrl(); // Get last URL
        if ($lastUrl == "") {
            $lastUrl = "index";
        }

        // Show messages
        $flash = Container("app.flash");
        if ($heading = $flash->getFirstMessage("heading")) {
            $this->setMessageHeading($heading);
        }
        if ($failure = $flash->getFirstMessage("failure")) {
            $this->setFailureMessage($failure);
        }
        if ($success = $flash->getFirstMessage("success")) {
            $this->setSuccessMessage($success);
        }
        if ($warning = $flash->getFirstMessage("warning")) {
            $this->setWarningMessage(warning);
        }

        // Login
        $provider = trim(RemoveXss(Get("provider") ?? Route("provider") ?? "")); // Get provider
        if (IsLoggingIn()) { // After changing password or authorized by 2FA
            $this->Username->setFormValue(Session(SESSION_USER_PROFILE_USER_NAME));
            $this->Password->setFormValue(Session(SESSION_USER_PROFILE_PASSWORD));
            $this->LoginType->setFormValue(Session(SESSION_USER_PROFILE_LOGIN_TYPE));
            $validPwd = $Security->validateUser($this->Username->CurrentValue, $this->Password->CurrentValue);
            if ($validPwd) {
                $_SESSION[SESSION_USER_PROFILE_USER_NAME] = "";
                $_SESSION[SESSION_USER_PROFILE_PASSWORD] = "";
                $_SESSION[SESSION_USER_PROFILE_LOGIN_TYPE] = "";
                $this->terminate($lastUrl); // Redirect to last page
                RemoveCookie("LastUrl");
                return;
            }
        } elseif (Config("USE_TWO_FACTOR_AUTHENTICATION") && IsLoggingIn2FA()) { // Logging in via 2FA, redirect
            $this->terminate("login2fa");
            return;
        } elseif (!EmptyValue($provider)) { // External provider
            $provider = ucfirst(strtolower($provider)); // e.g. Google, Facebook
            $validate = $Security->validateUser($this->Username->CurrentValue, $this->Password->CurrentValue, provider: $provider); // Authenticate by provider
            $validPwd = $validate;
            if ($validate) {
                $this->Username->setFormValue(Profile()->email ?? "");
                if (Config("DEBUG") && !$Security->isLoggedIn()) {
                    $validPwd = false;
                    $this->setFailureMessage(str_replace("%u", $this->Username->CurrentValue ?? "", $Language->phrase("UserNotFound"))); // Show debug message
                }
            } else {
                $this->setFailureMessage(str_replace("%p", $provider, $Language->phrase("LoginFailed")));
            }
        } else { // Normal login
            if (!$Security->isLoggedIn()) {
                $Security->autoLogin();
            }
            $Security->loadUserLevel(); // Load user level
            if ($Security->isLoggedIn()) {
                $this->terminate($lastUrl); // Redirect to last page
                RemoveCookie("LastUrl");
                return;
            }
            $validate = false;
            if (Post($this->Username->FieldVar) !== null) {
                $this->Username->setFormValue(Post($this->Username->FieldVar));
                $this->Password->setFormValue(Post($this->Password->FieldVar));
                $this->LoginType->setFormValue(strtolower(Post($this->LoginType->FieldVar, "")));
                $validate = $this->validateForm();
            } elseif (Config("ALLOW_LOGIN_BY_URL") && Get($this->Username->FieldVar) !== null) {
                $this->Username->setQueryStringValue(Get($this->Username->FieldVar));
                $this->Password->setQueryStringValue(Get($this->Password->FieldVar));
                $this->LoginType->setQueryStringValue(strtolower(Get($this->LoginType->FieldVar, "")));
                $validate = $this->validateForm();
            } else { // Restore settings
                if ($jwt = ReadCookie("AutoLogin")) {
                    $this->Username->setFormValue(DecodeJwt($jwt)["username"] ?? "");
                    $this->LoginType->setFormValue("a");
                } else { // Restore settings
                    $this->LoginType->setFormValue("");
                }
            }
            if (!EmptyValue($this->Username->CurrentValue)) {
                $_SESSION[SESSION_USER_LOGIN_TYPE] = $this->LoginType->CurrentValue; // Save user login type
                $_SESSION[SESSION_USER_PROFILE_USER_NAME] = $this->Username->CurrentValue; // Save login user name
                $_SESSION[SESSION_USER_PROFILE_LOGIN_TYPE] = $this->LoginType->CurrentValue; // Save login type
            }
            $validPwd = false;
            if ($validate) {
                // Call Logging In event
                $validate = $this->userLoggingIn($this->Username->CurrentValue, $this->Password->CurrentValue);
                if ($validate) {
                    $validPwd = $Security->validateUser($this->Username->CurrentValue, $this->Password->CurrentValue); // Manual login
                    if (!$validPwd) {
                        $this->Username->setFormValue(""); // Clear login name
                        $this->Username->addErrorMessage($Language->phrase("InvalidUidPwd")); // Invalid user name or password
                        $this->Password->addErrorMessage($Language->phrase("InvalidUidPwd")); // Invalid user name or password
                    }
                    $profile = Profile();

                    // Check user login session
                    if ($validPwd && !IsSysAdmin() && !$profile->isValidUser(session_id())) {
                        WriteAuditLog($this->Username->CurrentValue, $Language->phrase("AuditTrailUserLoggedIn"), CurrentUserIP());
                        $message = str_replace("%u", $this->Username->CurrentValue, $Language->phrase("UserLoggedIn"));
                        if ($this->IsModal) {
                            WriteJson(["error" => ["description" => $message]]);
                            $this->terminate();
                            return;
                        } else {
                            $this->setFailureMessage($message);
                            $validPwd = false;
                        }
                    }

                    // Two factor authentication enabled (go to 2fa page)
                    $sendOtp = in_array(strtolower(Config("TWO_FACTOR_AUTHENTICATION_TYPE")), ["email", "sms"]);
                    if (
                        $validPwd &&
                        (!IsSysAdmin() || Config("OTP_ONLY") && $sendOtp && !EmptyValue(Config("ADMIN_OTP_ACCOUNT"))) && // Non-Admin or Admin + Disable password checking + send OTP
                        Config("USE_TWO_FACTOR_AUTHENTICATION") &&
                        (Config("FORCE_TWO_FACTOR_AUTHENTICATION") || $profile->hasUserSecret(true))
                    ) {
                        if ($sendOtp) { // Send one time password
                            $_SESSION[SESSION_USER_PROFILE_RECORD] = IsSysAdmin() // System admin, use session for profile field
                                ? (strtolower(Config("TWO_FACTOR_AUTHENTICATION_TYPE")) == "email"
                                    ? [SYS_ADMIN_EMAIL_ADDRESS => Config("ADMIN_OTP_ACCOUNT")]
                                    : [SYS_ADMIN_PHONE_NUMBER => Config("ADMIN_OTP_ACCOUNT")])
                                : "";
                            $res = TwoFactorAuthenticationClass()::sendOneTimePassword($this->Username->CurrentValue);
                        } else {
                            $res = true;
                        }
                        if ($res === true) { // Go to 2FA page
                            $_SESSION[SESSION_STATUS] = "loggingin2fa";
                            $_SESSION[SESSION_USER_PROFILE_USER_NAME] = $this->Username->CurrentValue;
                            $_SESSION[SESSION_USER_PROFILE_PASSWORD] = $this->Password->CurrentValue;
                            $_SESSION[SESSION_USER_PROFILE_LOGIN_TYPE] = $this->LoginType->CurrentValue;
                            $this->IsModal = false; // Redirect
                            $this->terminate("login2fa?" . Config("PAGE_LAYOUT") . "=false");
                        } else {
                            $this->setFailureMessage($res); // Show error message
                            $Security->logoutUser(); // Logout user
                            $validPwd = false; // Handle as invalid password
                        }
                    }
                } else {
                    if ($this->getFailureMessage() == "") {
                        $this->setFailureMessage($Language->phrase("LoginCancelled")); // Login cancelled
                    }
                }
            }
        }

        // After login
        if ($validPwd) {
            if ($this->LoginType->CurrentValue == "a") { // Auto login
                WriteCookie(
                    "AutoLogin",
                    CreateJwt(["username" => $this->Username->CurrentValue, "autologin" => true], Config("REMEMBER_ME_EXPIRY_TIME")),
                    time() + Config("REMEMBER_ME_EXPIRY_TIME")
                ); // Write cookie
            } else {
                RemoveCookie("AutoLogin"); // Clear cookie
            }
            $this->writeAuditTrailOnLogin();

            // Call loggedin event
            $this->userLoggedIn($this->Username->CurrentValue);

            // External provider, just redirect
            if (!EmptyValue($provider)) {
                $this->IsModal = false;
            // Two factor authentication enabled (login directly), return JSON
            } elseif (Config("USE_TWO_FACTOR_AUTHENTICATION")) {
                $this->IsModal = true;
            }
            $this->terminate($lastUrl); // Return to last accessed URL
            RemoveCookie("LastUrl");
            return;
        } elseif (!EmptyValue($this->Username->CurrentValue) && !EmptyValue($this->Password->CurrentValue)) {
            // Call user login error event
            $this->userLoginError($this->Username->CurrentValue, $this->Password->CurrentValue);
        }

        // Set up error message
        if (EmptyValue($this->Username->ErrorMessage)) {
            $this->Username->ErrorMessage = $Language->phrase("EnterUserName");
        }
        if (EmptyValue($this->Password->ErrorMessage)) {
            $this->Password->ErrorMessage = $Language->phrase("EnterPassword");
        }

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

    // Validate form
    protected function validateForm()
    {
        global $Language;

        // Check if validation required
        if (!Config("SERVER_VALIDATE")) {
            return true;
        }
        $validateForm = true;
        if (EmptyValue($this->Username->CurrentValue)) {
            $this->Username->addErrorMessage($Language->phrase("EnterUserName"));
            $validateForm = false;
        }
        if (EmptyValue($this->Password->CurrentValue) && !CONFIG("OTP_ONLY")) { // Ignore if password checking disabled
            $this->Password->addErrorMessage($Language->phrase("EnterPassword"));
            $validateForm = false;
        }

        // Call Form Custom Validate event
        $formCustomError = "";
        $validateForm = $validateForm && $this->formCustomValidate($formCustomError);
        if ($formCustomError != "") {
            $this->setFailureMessage($formCustomError);
        }
        return $validateForm;
    }

    // Write audit trail on login
    protected function writeAuditTrailOnLogin()
    {
        global $Language;
        $usr = CurrentUserIdentifier();
        WriteAuditLog($usr, $Language->phrase("AuditTrailLogin"), CurrentUserIP());
    }

    public function pageLoad()
        {
            global $Security, $Language;

            // If user is already logged in to UAC, check system access and redirect
            if ($Security->isLoggedIn()) {
                // Check which systems the user has access to based on database permissions
                $sql = "SELECT DISTINCT s.system_code, s.url 
                        FROM " . QuotedName("user_level_assignments", "DB") . " ula
                        INNER JOIN " . QuotedName("user_levels", "DB") . " ul ON ula.user_level_id = ul.user_level_id
                        INNER JOIN " . QuotedName("systems", "DB") . " s ON ul.system_id = s.system_id
                        WHERE ula.user_id = ? AND s.is_active = TRUE
                        ORDER BY s.system_code";
                $accessibleSystems = Conn("DB")->executeQuery($sql, [$_SESSION[SESSION_USER_ID]])->fetchAllAssociative();
                if ($accessibleSystems && count($accessibleSystems) > 0) {
                    $firstSystemInfo = $accessibleSystems[0];
                    $systemCode = $firstSystemInfo['system_code'];
                    $systemUrl = $firstSystemInfo['url'] ?? '/' . $systemCode . '/';

                    // Check if user has access to UAC
                    if ($systemCode === 'UAC') {
                        $this->terminate('MainDashboard');
                    } else {
                        // Redirect to first accessible system using URL from database
                        header("Location: " . $systemUrl);
                        exit;
                    }
                } else {
                    // No system access, logout and show error
                    $Security->logoutUser();
                    $_SESSION['flash_failure'] = "You do not have access to any system. Please contact your administrator.";
                    $this->terminate('/');
                }
                return;
            }

            // Check for auto-login from other subsystems
            $username = null;

            // Load subsystems configuration
            $SYSTEM = require dirname(__DIR__, 1) . "/_shared/system.php";
            $subsystems = array_filter(array_keys($SYSTEM), fn($key) => $key !== 'UAC');

            // Check for existing session from other subsystems
            foreach ($subsystems as $subsystem) {
                $userNameKey = "{$subsystem}_Status_UserName";
                $userIdKey = "{$subsystem}_Status_UserID";
                if (!empty($_SESSION[$userNameKey]) && !empty($_SESSION[$userIdKey])) {
                    $username = $_SESSION[$userNameKey];
                    break;
                }
            }
            if ($username) {
                // Validate user in UAC database
                $sql = "SELECT * FROM " . QuotedName("users", "DB") . "
                        WHERE " . QuotedName("username", "DB") . " = ?";
                $rs = Conn("DB")->executeQuery($sql, [$username])->fetchAssociative();
                if ($rs) {
                    // Set session variables directly (no form validation)
                    $_SESSION[SESSION_USER_ID] = $rs['user_id'];
                    $_SESSION[SESSION_USER_NAME] = $rs['username'];
                    $_SESSION[SESSION_USER_LEVEL_ID] = $rs['user_level_id'];
                    $_SESSION[SESSION_PARENT_USER_ID] = $rs['reports_to_user_id'] ?? -1;
                    $_SESSION[SESSION_STATUS] = "login";

                    // Setup security
                    $Security->setupUserLevel($rs['user_level_id']);

                    // Audit trail
                    WriteAuditTrail(
                        "log",
                        CurrentDateTime(),
                        CurrentPageName(),
                        $rs['user_id'],
                        "login",
                        "users",
                        "user_id",
                        $rs['user_id'],
                        "",
                        "Auto-login from subsystem"
                    );

                    // User logged in callback
                    if (method_exists($this, 'userLoggedIn')) {
                        $this->userLoggedIn($rs['username']);
                    }

                    // Check system access and redirect accordingly
                    $sql = "SELECT DISTINCT s.system_code, s.url 
                            FROM " . QuotedName("user_level_assignments", "DB") . " ula
                            INNER JOIN " . QuotedName("user_levels", "DB") . " ul ON ula.user_level_id = ul.user_level_id
                            INNER JOIN " . QuotedName("systems", "DB") . " s ON ul.system_id = s.system_id
                            WHERE ula.user_id = ? AND s.is_active = TRUE
                            ORDER BY s.system_code";
                    $accessibleSystems = Conn("DB")->executeQuery($sql, [$rs['user_id']])->fetchAllAssociative();
                    if ($accessibleSystems && count($accessibleSystems) > 0) {
                        $firstSystemInfo = $accessibleSystems[0];
                        $systemCode = $firstSystemInfo['system_code'];
                        $systemUrl = $firstSystemInfo['url'] ?? '/' . $systemCode . '/';

                        // Check if user has access to UAC
                        if ($systemCode === 'UAC') {
                            $this->terminate('MainDashboard');
                        } else {
                            // Redirect to first accessible system using URL from database
                            header("Location: " . $systemUrl);
                            exit;
                        }
                    } else {
                        // No system access, show error
                        $_SESSION['flash_failure'] = "You do not have access to any system. Please contact your administrator.";
                        $this->terminate('/');
                    }
                }
            }

            // Redirect GET requests that are NOT fetch/AJAX calls (direct browser access)
            // Allow fetch requests to get CSRF tokens for the form at index.php
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Check if this is a fetch/AJAX request or direct browser access
                $isFetchRequest = (
                    !empty($_SERVER['HTTP_SEC_FETCH_MODE']) && $_SERVER['HTTP_SEC_FETCH_MODE'] === 'cors'
                ) || (
                    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
                );

                // If it's a direct browser access (not fetch), redirect to root
                if (!$isFetchRequest) {
                    $this->terminate('/');
                }
            }
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
    // $type = ''|'success'|'failure'
    public function messageShowing(&$msg, $type)
    {
        // Example:
        //if ($type == "success") $msg = "your success message";
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

    // User Logging In event
    public function userLoggingIn($usr, &$pwd)
    {
        // Enter your code here
        // To cancel, set return value to false
        return true;
    }

    // User Logged In event
    public function userLoggedIn($usr)
    {
        //Log("User Logged In");
    }

    // User Login Error event
    public function userLoginError($usr, $pwd)
    {
        //Log("User Login Error");
    }

    // Form Custom Validate event
    public function formCustomValidate(&$customError)
    {
        // Return error message in $customError
        return true;
    }
}
