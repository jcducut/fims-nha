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
class ChangePassword extends Users
{
    use MessagesTrait;

    // Page ID
    public $PageID = "change_password";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "ChangePassword";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "changepassword";

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
    public $IsModal = false;
    public $OldPassword;
    public $NewPassword;
    public $ConfirmPassword;
    public $OffsetColumnClass = ""; // Override user table

    /**
     * Page run
     *
     * @return void
     */
    public function run()
    {
        global $ExportType, $Language, $Security, $CurrentForm, $UserTable, $Breadcrumb, $SkipHeaderFooter;

        // Create Password fields object (used by validation only)
        $this->OldPassword = new DbField(Container("usertable"), "opwd", "opwd", "opwd", "", 202, 255, -1, false, "", false, false, false);
        $this->OldPassword->EditAttrs->appendClass("form-control ew-form-control");
        $this->NewPassword = new DbField(Container("usertable"), "npwd", "npwd", "npwd", "", 202, 255, -1, false, "", false, false, false);
        $this->NewPassword->EditAttrs->appendClass("form-control ew-form-control");
        $this->ConfirmPassword = new DbField(Container("usertable"), "cpwd", "cpwd", "cpwd", "", 202, 255, -1, false, "", false, false, false);
        $this->ConfirmPassword->EditAttrs->appendClass("form-control ew-form-control");
        if (Config("ENCRYPTED_PASSWORD")) {
            $this->OldPassword->Raw = true;
            $this->NewPassword->Raw = true;
            $this->ConfirmPassword->Raw = true;
        }

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
        $Breadcrumb = Breadcrumb::create("index")->add("change_password", "ChangePasswordPage", CurrentUrl(), "", "", true);
        $this->Heading = $Language->phrase("ChangePasswordPage");
        $postBack = IsPost();
        $validate = true;
        if ($postBack) {
            $this->OldPassword->setFormValue(Post($this->OldPassword->FieldVar));
            $this->NewPassword->setFormValue(Post($this->NewPassword->FieldVar));
            $this->ConfirmPassword->setFormValue(Post($this->ConfirmPassword->FieldVar));
            $validate = $this->validateForm();
        }
        $pwdUpdated = false;
        $user = null;
        if ($postBack && $validate) {
            // Setup variables
            $userName = $Security->currentUserName();
            if (IsPasswordReset()) {
                $userName = Session(SESSION_USER_PROFILE_USER_NAME);
            }

            // Find user
            if ($this->UpdateTable != $this->TableName && $this->UpdateTable != $this->getSqlFrom()) { // Note: The username field name must be the same
                $entityClass = GetEntityClass($this->UpdateTable);
                if ($entityClass) {
                    $user = GetUserEntityManager()->getRepository($entityClass)->findOneBy(["username" => $userName]);
                } else {
                    throw new \Exception("Entity class for UpdateTable not found.");
                }
            } else {
                $user = FindUserByUserName($userName);
            }
            if ($user) {
                if (IsPasswordReset() || ComparePassword($user->get(Config("LOGIN_PASSWORD_FIELD_NAME")), $this->OldPassword->CurrentValue)) {
                    $validPwd = true;
                    if (!IsPasswordReset()) {
                        $validPwd = $this->userChangePassword($user->toArray(), $userName, $this->OldPassword->CurrentValue, $this->NewPassword->CurrentValue);
                    }
                    if ($validPwd) {
                        $user->set(Config("LOGIN_PASSWORD_FIELD_NAME"), $this->NewPassword->CurrentValue); // Change Password
                        GetUserEntityManager()->flush();
                        $pwdUpdated = true;
                    } else {
                        $this->setFailureMessage($Language->phrase("InvalidNewPassword"));
                        // Same reasoning as the InvalidPassword branch below —
                        // see the comment there.
                        if (!IsApi() && !$this->IsModal) {
                            $this->terminate("/app/pages/change-pass.php?err=newpwd");
                            return;
                        }
                    }
                } else {
                    $this->setFailureMessage($Language->phrase("InvalidPassword"));
                    // Falling through to the bottom of this method would
                    // re-render the framework's own default views/ChangePassword.php
                    // template in place — a different, unbranded page than our
                    // custom app/pages/change-pass.php. That custom page's own JS
                    // expects to parse this re-rendered HTML for a ".ew-error"
                    // element to extract the failure reason, which is fragile and,
                    // in practice, falls back to its generic "Could not update your
                    // password..." message. Redirect to the custom page instead,
                    // flagged via query string, same pattern as the /login fix.
                    if (!IsApi() && !$this->IsModal) {
                        $this->terminate("/app/pages/change-pass.php?err=oldpwd");
                        return;
                    }
                }
            }
        }
        if ($pwdUpdated) {
            // Captured before IsPasswordReset() gets cleared below — it reads
            // $_SESSION[SESSION_STATUS], which line ~409 blanks out as part of
            // completing the reset flow.
            $wasPasswordReset = IsPasswordReset();
            $emailAddress = $user->get(Config("USER_EMAIL_FIELD_NAME"));
            if ($emailAddress != "") {
                // Load Email Content
                $email = new Email();
                $email->load(Config("EMAIL_CHANGE_PASSWORD_TEMPLATE"), data: [
                    "From" => Config("SENDER_EMAIL"), // Replace Sender
                    "To" => $emailAddress // Replace Recipient
                ]);
                $args = ["rs" => $user->toArray()];
                $emailSent = false;
                if ($this->emailSending($email, $args)) {
                    $emailSent = $email->send();
                }

                // Send email failed
                if (!$emailSent) {
                    $this->setFailureMessage($email->SendErrDescription);
                }
            }
            if ($this->getSuccessMessage() == "") {
                $this->setSuccessMessage($Language->phrase("PasswordChanged")); // Set up success message
            }
            if (IsPasswordReset()) {
                $_SESSION[SESSION_STATUS] = "";
                $_SESSION[SESSION_USER_PROFILE_USER_NAME] = "";
            }
            $this->terminate("index"); // Return to default page
            return;
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
        $valid = true;
        if (!IsPasswordReset() && EmptyValue($this->OldPassword->CurrentValue)) {
            $this->OldPassword->addErrorMessage($Language->phrase("EnterOldPassword"));
            $valid = false;
        }
        if (EmptyValue($this->NewPassword->CurrentValue)) {
            $this->NewPassword->addErrorMessage($Language->phrase("EnterNewPassword"));
            $valid = false;
        }
        if (!$this->NewPassword->Raw && Config("REMOVE_XSS") && CheckPassword($this->NewPassword->CurrentValue)) {
            $this->NewPassword->addErrorMessage($Language->phrase("InvalidPasswordChars"));
            $valid = false;
        }
        if ($this->NewPassword->CurrentValue != $this->ConfirmPassword->CurrentValue) {
            $this->ConfirmPassword->addErrorMessage($Language->phrase("MismatchPassword"));
            $valid = false;
        }

        // Call Form CustomValidate event
        $formCustomError = "";
        $valid = $valid && $this->formCustomValidate($formCustomError);
        if ($formCustomError != "") {
            $this->setFailureMessage($formCustomError);
        }
        return $valid;
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

    // Email Sending event
    public function emailSending($email, $args)
    {
        //var_dump($email, $args); exit();
        return true;
    }

    // Form Custom Validate event
    public function formCustomValidate(&$customError)
    {
        // Return error message in $customError
        return true;
    }

    // User ChangePassword event
    public function userChangePassword($rs, $usr, $oldpwd, &$newpwd)
    {
        // Return false to abort
        return true;
    }
}
