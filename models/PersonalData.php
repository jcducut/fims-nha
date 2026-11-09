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
class PersonalData
{
    use MessagesTrait;

    // Page ID
    public $PageID = "personal_data";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Table name
    public $TableName;

    // Table variable
    public $TableVar;

    // Page object name
    public $PageObjName = "PersonalData";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "VoucherController";

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
        global $Language, $DashboardReport, $DebugTimer, $UserTable;

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Start timer
        $DebugTimer = Container("debug.timer");

        // Debug message
        LoadDebugMessage();

        // Open connection
        $GLOBALS["Conn"] ??= GetConnection();

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
        DispatchEvent(new PageUnloadedEvent($this), PageUnloadedEvent::NAME);

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
            SaveDebugMessage();
            Redirect(GetUrl($url));
        }
        return; // Return to controller
    }

    // Properties
    public $Password;

    /**
     * Page run
     *
     * @return void
     */
    public function run()
    {
        global $ExportType, $Language, $Security, $CurrentForm, $Breadcrumb;

        // Create Password field object (used by validation only)
        $this->Password = new DbField(Container("usertable"), "password", "password", "password", "", 202, 255, -1, false, "", false, false, false);
        $this->Password->EditAttrs->appendClass("form-control ew-form-control");

        // Use layout
        $this->UseLayout = $this->UseLayout && ConvertToBool(Param(Config("PAGE_LAYOUT"), true));

        // View
        $this->View = Get(Config("VIEW"));

        // Load user profile
        if (IsLoggedIn()) {
            Profile()->setUserName(CurrentUserName())->loadFromStorage();
        }

        // Global Page Loading event (in userfn*.php)
        DispatchEvent(new PageLoadingEvent($this), PageLoadingEvent::NAME);
        $Breadcrumb = Breadcrumb::create("index")->add("personal_data", "PersonalDataTitle", CurrentUrl(), "ew-personal-data", "", true);
        $this->Heading = $Language->phrase("PersonalDataTitle");
        $cmd = Param("cmd");
        if (SameText($cmd, "Download")) {
            if ($this->personalDataResult()) {
                $this->terminate();
                return;
            }
        } elseif (SameText($cmd, "Delete") && IsPost()) {
            if ($this->deletePersonalData()) {
                $this->terminate(GetUrl("logout?deleted=1"));
                return;
            }
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

    /**
     * Write personal data as JSON
     *
     * @return void
     */
    protected function personalDataResult()
    {
        $fldNames = [];
        $user = FindUserByUserName(CurrentUserName());
        if ($user) {
            $row = $user->toArray();

            // Call PersonalData_Downloading event
            PersonalData_Downloading($row);
            $personalDataFileName = Get("_personaldatafilename", "personaldata.json");
            AddHeader("Content-Disposition", "attachment; filename=\"" . $personalDataFileName . "\"");
            WriteJson($row);
            return true;
        } else {
            $this->setFailureMessage($Language->phrase("NoRecord")); // No record found
            return false;
        }
    }

    /**
     * Delete personal data
     *
     * @return bool
     */
    protected function deletePersonalData()
    {
        global $Language, $UserTable;
        $pwd = Post($this->Password->FieldVar, "");
        $userName = CurrentUserName();
        if ($UserTable->UpdateTable != $UserTable->TableName && $this->UpdateTable != $this->getSqlFrom()) { // Note: The username and password field name must be the same
            $entityClass = GetEntityClass($UserTable->UpdateTable);
            if ($entityClass) {
                $user = GetUserEntityManager()->getRepository($entityClass)->findOneBy(["username" => $userName]);
            } else {
                throw new \Exception("Entity class for UpdateTable not found.");
            }
        } else {
            $user = FindUserByUserName($userName);
        }
        if ($user) {
            if (ComparePassword($user->get(Config("LOGIN_PASSWORD_FIELD_NAME")), $pwd)) {
                $row = $user->toArray();
                if (Config("DELETE_UPLOADED_FILES")) { // Delete old files
                    $UserTable->deleteUploadedFiles($row);
                }
                try {
                    $em = GetUserEntityManager();
                    $em->remove($user);
                    $em->flush();

                    // Call PersonalData_Deleted event
                    PersonalData_Deleted($row);
                    return true;
                } catch (\Exception $e) {
                    $this->setFailureMessage($Language->phrase("PersonalDataDeleteFailure") . ": " . $e->getMessage());
                    return false;
                }
            } else {
                $this->Password->addErrorMessage($Language->phrase("InvalidPassword"));
                return false;
            }
        } else {
            $this->setFailureMessage($Language->phrase("NoRecord"));
            return false;
        }
    }
}
