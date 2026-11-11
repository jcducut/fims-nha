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
 * Table class for users
 */
class Users extends DbTable
{
    protected $SqlFrom = "";
    protected $SqlSelect = null;
    protected $SqlSelectList = null;
    protected $SqlWhere = "";
    protected $SqlGroupBy = "";
    protected $SqlHaving = "";
    protected $SqlOrderBy = "";
    public $DbErrorMessage = "";
    public $UseSessionForListSql = true;

    // Column CSS classes
    public $LeftColumnClass = "col-sm-2 col-form-label ew-label";
    public $RightColumnClass = "col-sm-10";
    public $OffsetColumnClass = "col-sm-10 offset-sm-2";
    public $TableLeftColumnClass = "w-col-2";

    // Audit trail
    public $AuditTrailOnAdd = true;
    public $AuditTrailOnEdit = true;
    public $AuditTrailOnDelete = true;
    public $AuditTrailOnView = false;
    public $AuditTrailOnViewData = false;
    public $AuditTrailOnSearch = false;

    // Ajax / Modal
    public $UseAjaxActions = false;
    public $ModalSearch = false;
    public $ModalView = false;
    public $ModalAdd = false;
    public $ModalEdit = false;
    public $ModalUpdate = false;
    public $InlineDelete = false;
    public $ModalGridAdd = false;
    public $ModalGridEdit = false;
    public $ModalMultiEdit = false;

    // Fields
    public $user_id;
    public $_username;
    public $_email;
    public $password_hash;
    public $mobile_number;
    public $first_name;
    public $middle_name;
    public $last_name;
    public $suffix;
    public $department_id;
    public $date_created;
    public $last_login;
    public $is_active;
    public $user_level_id;
    public $reports_to_user_id;
    public $photo;
    public $_profile;
    public $position_id;
    public $employee_id;
    public $must_change_password;
    public $rc_id;

    // Page ID
    public $PageID = ""; // To be overridden by subclass

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $CurrentLanguage, $CurrentLocale;

        // Language object
        $Language = Container("app.language");
        $this->TableVar = "users";
        $this->TableName = 'users';
        $this->TableType = "TABLE";
        $this->ImportUseTransaction = $this->supportsTransaction() && Config("IMPORT_USE_TRANSACTION");
        $this->UseTransaction = $this->supportsTransaction() && Config("USE_TRANSACTION");

        // Update Table
        $this->UpdateTable = "uac.users";
        $this->Dbid = 'DB';
        $this->ExportAll = true;
        $this->ExportPageBreakCount = 0; // Page break per every n record (PDF only)

        // PDF
        $this->ExportPageOrientation = "portrait"; // Page orientation (PDF only)
        $this->ExportPageSize = "a4"; // Page size (PDF only)

        // PhpSpreadsheet
        $this->ExportExcelPageOrientation = null; // Page orientation (PhpSpreadsheet only)
        $this->ExportExcelPageSize = null; // Page size (PhpSpreadsheet only)

        // PHPWord
        $this->ExportWordPageOrientation = ""; // Page orientation (PHPWord only)
        $this->ExportWordPageSize = ""; // Page orientation (PHPWord only)
        $this->ExportWordColumnWidth = null; // Cell width (PHPWord only)
        $this->DetailAdd = false; // Allow detail add
        $this->DetailEdit = false; // Allow detail edit
        $this->DetailView = false; // Allow detail view
        $this->ShowMultipleDetails = false; // Show multiple details
        $this->GridAddRowCount = 5;
        $this->AllowAddDeleteRow = true; // Allow add/delete row
        $this->UseAjaxActions = $this->UseAjaxActions || Config("USE_AJAX_ACTIONS");
        $this->BasicSearch = new BasicSearch($this);

        // user_id
        $this->user_id = new DbField(
            $this, // Table
            'x_user_id', // Variable name
            'user_id', // Name
            '"user_id"', // Expression
            'CAST("user_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"user_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'NO' // Edit Tag
        );
        $this->user_id->InputTextType = "text";
        $this->user_id->Raw = true;
        $this->user_id->IsAutoIncrement = true; // Autoincrement field
        $this->user_id->IsPrimaryKey = true; // Primary key field
        $this->user_id->IsForeignKey = true; // Foreign key field
        $this->user_id->Nullable = false; // NOT NULL field
        $this->user_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->user_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['user_id'] = &$this->user_id;

        // username
        $this->_username = new DbField(
            $this, // Table
            'x__username', // Variable name
            'username', // Name
            '"username"', // Expression
            '"username"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"username"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->_username->InputTextType = "text";
        $this->_username->Raw = true;
        $this->_username->Nullable = false; // NOT NULL field
        $this->_username->Required = true; // Required field
        $this->_username->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['username'] = &$this->_username;

        // email
        $this->_email = new DbField(
            $this, // Table
            'x__email', // Variable name
            'email', // Name
            '"email"', // Expression
            '"email"', // Basic search expression
            200, // Type
            100, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"email"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->_email->InputTextType = "text";
        $this->_email->Nullable = false; // NOT NULL field
        $this->_email->Required = true; // Required field
        $this->_email->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['email'] = &$this->_email;

        // password_hash
        $this->password_hash = new DbField(
            $this, // Table
            'x_password_hash', // Variable name
            'password_hash', // Name
            '"password_hash"', // Expression
            '"password_hash"', // Basic search expression
            200, // Type
            255, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"password_hash"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'PASSWORD' // Edit Tag
        );
        $this->password_hash->InputTextType = "text";
        $this->password_hash->Raw = true;
        $this->password_hash->Nullable = false; // NOT NULL field
        $this->password_hash->Required = true; // Required field
        $this->password_hash->SearchOperators = ["=", "<>"];
        $this->Fields['password_hash'] = &$this->password_hash;

        // mobile_number
        $this->mobile_number = new DbField(
            $this, // Table
            'x_mobile_number', // Variable name
            'mobile_number', // Name
            '"mobile_number"', // Expression
            '"mobile_number"', // Basic search expression
            200, // Type
            20, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"mobile_number"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->mobile_number->InputTextType = "text";
        $this->mobile_number->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['mobile_number'] = &$this->mobile_number;

        // first_name
        $this->first_name = new DbField(
            $this, // Table
            'x_first_name', // Variable name
            'first_name', // Name
            '"first_name"', // Expression
            '"first_name"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"first_name"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->first_name->InputTextType = "text";
        $this->first_name->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['first_name'] = &$this->first_name;

        // middle_name
        $this->middle_name = new DbField(
            $this, // Table
            'x_middle_name', // Variable name
            'middle_name', // Name
            '"middle_name"', // Expression
            '"middle_name"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"middle_name"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->middle_name->InputTextType = "text";
        $this->middle_name->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['middle_name'] = &$this->middle_name;

        // last_name
        $this->last_name = new DbField(
            $this, // Table
            'x_last_name', // Variable name
            'last_name', // Name
            '"last_name"', // Expression
            '"last_name"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"last_name"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->last_name->InputTextType = "text";
        $this->last_name->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['last_name'] = &$this->last_name;

        // suffix
        $this->suffix = new DbField(
            $this, // Table
            'x_suffix', // Variable name
            'suffix', // Name
            '"suffix"', // Expression
            '"suffix"', // Basic search expression
            200, // Type
            10, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"suffix"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->suffix->InputTextType = "text";
        $this->suffix->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['suffix'] = &$this->suffix;

        // department_id
        $this->department_id = new DbField(
            $this, // Table
            'x_department_id', // Variable name
            'department_id', // Name
            '"department_id"', // Expression
            'CAST("department_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"department_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->department_id->InputTextType = "text";
        $this->department_id->Raw = true;
        $this->department_id->Lookup = new Lookup($this->department_id, 'departments', false, 'department_id', ["department_name","","",""], '', '', [], [], [], [], [], [], false, '', '', "\"department_name\"");
        $this->department_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->department_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['department_id'] = &$this->department_id;

        // date_created
        $this->date_created = new DbField(
            $this, // Table
            'x_date_created', // Variable name
            'date_created', // Name
            '"date_created"', // Expression
            CastDateFieldForLike("\"date_created\"", 1, "DB"), // Basic search expression
            135, // Type
            0, // Size
            1, // Date/Time format
            false, // Is upload field
            '"date_created"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'HIDDEN' // Edit Tag
        );
        $this->date_created->addMethod("getAutoUpdateValue", fn() => CurrentDateTime());
        $this->date_created->InputTextType = "text";
        $this->date_created->Raw = true;
        $this->date_created->DefaultErrorMessage = str_replace("%s", DateFormat(1), $Language->phrase("IncorrectDate"));
        $this->date_created->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['date_created'] = &$this->date_created;

        // last_login
        $this->last_login = new DbField(
            $this, // Table
            'x_last_login', // Variable name
            'last_login', // Name
            '"last_login"', // Expression
            CastDateFieldForLike("\"last_login\"", 1, "DB"), // Basic search expression
            135, // Type
            0, // Size
            1, // Date/Time format
            false, // Is upload field
            '"last_login"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'HIDDEN' // Edit Tag
        );
        $this->last_login->InputTextType = "text";
        $this->last_login->Raw = true;
        $this->last_login->DefaultErrorMessage = str_replace("%s", DateFormat(1), $Language->phrase("IncorrectDate"));
        $this->last_login->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['last_login'] = &$this->last_login;

        // is_active
        $this->is_active = new DbField(
            $this, // Table
            'x_is_active', // Variable name
            'is_active', // Name
            '"is_active"', // Expression
            'CAST("is_active" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"is_active"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->is_active->InputTextType = "text";
        $this->is_active->Raw = true;
        $this->is_active->setDataType(DataType::BOOLEAN);
        $this->is_active->Lookup = new Lookup($this->is_active, 'users', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->is_active->OptionCount = 2;
        $this->is_active->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['is_active'] = &$this->is_active;

        // user_level_id
        $this->user_level_id = new DbField(
            $this, // Table
            'x_user_level_id', // Variable name
            'user_level_id', // Name
            '"user_level_id"', // Expression
            '"user_level_id"', // Basic search expression
            200, // Type
            255, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"user_level_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'SELECT' // Edit Tag
        );
        $this->user_level_id->InputTextType = "text";
        $this->user_level_id->Raw = true;
        $this->user_level_id->IsForeignKey = true; // Foreign key field
        $this->user_level_id->setSelectMultiple(true); // Select multiple
        $this->user_level_id->Lookup = new Lookup($this->user_level_id, '_user_levels', false, 'user_level_id', ["name","","",""], '', '', [], [], [], [], [], [], false, '', '', "\"name\"");
        $this->user_level_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->user_level_id->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['user_level_id'] = &$this->user_level_id;

        // reports_to_user_id
        $this->reports_to_user_id = new DbField(
            $this, // Table
            'x_reports_to_user_id', // Variable name
            'reports_to_user_id', // Name
            '"reports_to_user_id"', // Expression
            'CAST("reports_to_user_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"reports_to_user_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->reports_to_user_id->InputTextType = "text";
        $this->reports_to_user_id->Raw = true;
        $this->reports_to_user_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->reports_to_user_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['reports_to_user_id'] = &$this->reports_to_user_id;

        // photo
        $this->photo = new DbField(
            $this, // Table
            'x_photo', // Variable name
            'photo', // Name
            '"photo"', // Expression
            '"photo"', // Basic search expression
            200, // Type
            255, // Size
            -1, // Date/Time format
            true, // Is upload field
            '"photo"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'FILE' // Edit Tag
        );
        $this->photo->addMethod("getUploadPath", fn() => AppConfig('UAC.users.photo'));
        $this->photo->InputTextType = "text";
        $this->photo->SearchOperators = ["=", "<>", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['photo'] = &$this->photo;

        // profile
        $this->_profile = new DbField(
            $this, // Table
            'x__profile', // Variable name
            'profile', // Name
            '"profile"', // Expression
            '"profile"', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"profile"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'HIDDEN' // Edit Tag
        );
        $this->_profile->InputTextType = "text";
        $this->_profile->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['profile'] = &$this->_profile;

        // position_id
        $this->position_id = new DbField(
            $this, // Table
            'x_position_id', // Variable name
            'position_id', // Name
            '"position_id"', // Expression
            'CAST("position_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"position_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->position_id->InputTextType = "text";
        $this->position_id->Raw = true;
        $this->position_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->position_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['position_id'] = &$this->position_id;

        // employee_id
        $this->employee_id = new DbField(
            $this, // Table
            'x_employee_id', // Variable name
            'employee_id', // Name
            '"employee_id"', // Expression
            '"employee_id"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"employee_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->employee_id->InputTextType = "text";
        $this->employee_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['employee_id'] = &$this->employee_id;

        // must_change_password
        $this->must_change_password = new DbField(
            $this, // Table
            'x_must_change_password', // Variable name
            'must_change_password', // Name
            '"must_change_password"', // Expression
            'CAST("must_change_password" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"must_change_password"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->must_change_password->InputTextType = "text";
        $this->must_change_password->Raw = true;
        $this->must_change_password->Nullable = false; // NOT NULL field
        $this->must_change_password->setDataType(DataType::BOOLEAN);
        $this->must_change_password->Lookup = new Lookup($this->must_change_password, 'users', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->must_change_password->OptionCount = 2;
        $this->must_change_password->SearchOperators = ["=", "<>"];
        $this->Fields['must_change_password'] = &$this->must_change_password;

        // rc_id
        $this->rc_id = new DbField(
            $this, // Table
            'x_rc_id', // Variable name
            'rc_id', // Name
            '"rc_id"', // Expression
            '"rc_id"', // Basic search expression
            200, // Type
            10, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"rc_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->rc_id->InputTextType = "text";
        $this->rc_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['rc_id'] = &$this->rc_id;

        // Add Doctrine Cache
        $this->Cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $this->CacheProfile = new \Doctrine\DBAL\Cache\QueryCacheProfile(0, $this->TableVar);

        // Call Table Load event
        $this->tableLoad();
    }

    // Field Visibility
    public function getFieldVisibility($fldParm)
    {
        global $Security;
        return $this->$fldParm->Visible; // Returns original value
    }

    // Set left column class (must be predefined col-*-* classes of Bootstrap grid system)
    public function setLeftColumnClass($class)
    {
        if (preg_match('/^col\-(\w+)\-(\d+)$/', $class, $match)) {
            $this->LeftColumnClass = $class . " col-form-label ew-label";
            $this->RightColumnClass = "col-" . $match[1] . "-" . strval(12 - (int)$match[2]);
            $this->OffsetColumnClass = $this->RightColumnClass . " " . str_replace("col-", "offset-", $class);
            $this->TableLeftColumnClass = preg_replace('/^col-\w+-(\d+)$/', "w-col-$1", $class); // Change to w-col-*
        }
    }

    // Single column sort
    public function updateSort(&$fld)
    {
        if ($this->CurrentOrder == $fld->Name) {
            $sortField = $fld->Expression;
            $lastSort = $fld->getSort();
            if (in_array($this->CurrentOrderType, ["ASC", "DESC", "NO"])) {
                $curSort = $this->CurrentOrderType;
            } else {
                $curSort = $lastSort;
            }
            $orderBy = in_array($curSort, ["ASC", "DESC"]) ? $sortField . " " . $curSort : "";
            $this->setSessionOrderBy($orderBy); // Save to Session
        }
    }

    // Update field sort
    public function updateFieldSort()
    {
        $orderBy = $this->getSessionOrderBy(); // Get ORDER BY from Session
        $flds = GetSortFields($orderBy);
        foreach ($this->Fields as $field) {
            $fldSort = "";
            foreach ($flds as $fld) {
                if ($fld[0] == $field->Expression || $fld[0] == $field->VirtualExpression) {
                    $fldSort = $fld[1];
                }
            }
            $field->setSort($fldSort);
        }
    }

    // Current master table name
    public function getCurrentMasterTable()
    {
        return Session(PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_MASTER_TABLE"));
    }

    public function setCurrentMasterTable($v)
    {
        $_SESSION[PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_MASTER_TABLE")] = $v;
    }

    // Get master WHERE clause from session values
    public function getMasterFilterFromSession()
    {
        // Master filter
        $masterFilter = "";
        if ($this->getCurrentMasterTable() == "_user_levels") {
            $masterTable = Container("_user_levels");
            if ($this->user_level_id->getSessionValue() != "") {
                $masterFilter .= "" . GetKeyFilter($masterTable->user_level_id, $this->user_level_id->getSessionValue(), $masterTable->user_level_id->DataType, $masterTable->Dbid);
            } else {
                return "";
            }
        }
        return $masterFilter;
    }

    // Get detail WHERE clause from session values
    public function getDetailFilterFromSession()
    {
        // Detail filter
        $detailFilter = "";
        if ($this->getCurrentMasterTable() == "_user_levels") {
            $masterTable = Container("_user_levels");
            if ($this->user_level_id->getSessionValue() != "") {
                $detailFilter .= "" . GetKeyFilter($this->user_level_id, $this->user_level_id->getSessionValue(), $masterTable->user_level_id->DataType, $this->Dbid);
            } else {
                return "";
            }
        }
        return $detailFilter;
    }

    /**
     * Get master filter
     *
     * @param object $masterTable Master Table
     * @param array $keys Detail Keys
     * @return mixed NULL is returned if all keys are empty, Empty string is returned if some keys are empty and is required
     */
    public function getMasterFilter($masterTable, $keys)
    {
        $validKeys = true;
        switch ($masterTable->TableVar) {
            case "_user_levels":
                $key = $keys["user_level_id"] ?? "";
                if (EmptyValue($key)) {
                    if ($masterTable->user_level_id->Required) { // Required field and empty value
                        return ""; // Return empty filter
                    }
                    $validKeys = false;
                } elseif (!$validKeys) { // Already has empty key
                    return ""; // Return empty filter
                }
                if ($validKeys) {
                    return GetKeyFilter($masterTable->user_level_id, $keys["user_level_id"], $this->user_level_id->DataType, $this->Dbid);
                }
                break;
        }
        return null; // All null values and no required fields
    }

    // Get detail filter
    public function getDetailFilter($masterTable)
    {
        switch ($masterTable->TableVar) {
            case "_user_levels":
                return GetKeyFilter($this->user_level_id, $masterTable->user_level_id->DbValue, $masterTable->user_level_id->DataType, $masterTable->Dbid);
        }
        return "";
    }

    // Current detail table name
    public function getCurrentDetailTable()
    {
        return Session(PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_DETAIL_TABLE")) ?? "";
    }

    public function setCurrentDetailTable($v)
    {
        $_SESSION[PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_DETAIL_TABLE")] = $v;
    }

    // Get detail url
    public function getDetailUrl()
    {
        // Detail url
        $detailUrl = "";
        if ($this->getCurrentDetailTable() == "user_level_assignments") {
            $detailUrl = Container("user_level_assignments")->getListUrl() . "?" . Config("TABLE_SHOW_MASTER") . "=" . $this->TableVar;
            $detailUrl .= "&" . GetForeignKeyUrl("fk_user_id", $this->user_id->CurrentValue);
        }
        if ($detailUrl == "") {
            $detailUrl = "UsersList";
        }
        return $detailUrl;
    }

    // Render X Axis for chart
    public function renderChartXAxis($chartVar, $chartRow)
    {
        return $chartRow;
    }

    // Get FROM clause
    public function getSqlFrom()
    {
        return ($this->SqlFrom != "") ? $this->SqlFrom : "uac.users";
    }

    // Get FROM clause (for backward compatibility)
    public function sqlFrom()
    {
        return $this->getSqlFrom();
    }

    // Set FROM clause
    public function setSqlFrom($v)
    {
        $this->SqlFrom = $v;
    }

    // Get SELECT clause
    public function getSqlSelect() // Select
    {
        return $this->SqlSelect ?? $this->getQueryBuilder()->select($this->sqlSelectFields());
    }

    // Get list of fields
    private function sqlSelectFields()
    {
        $useFieldNames = false;
        $fieldNames = [];
        $platform = $this->getConnection()->getDatabasePlatform();
        foreach ($this->Fields as $field) {
            $expr = $field->Expression;
            $customExpr = $field->CustomDataType?->convertToPHPValueSQL($expr, $platform) ?? $expr;
            if ($customExpr != $expr) {
                $fieldNames[] = $customExpr . " AS " . QuotedName($field->Name, $this->Dbid);
                $useFieldNames = true;
            } else {
                $fieldNames[] = $expr;
            }
        }
        return $useFieldNames ? implode(", ", $fieldNames) : "*";
    }

    // Get SELECT clause (for backward compatibility)
    public function sqlSelect()
    {
        return $this->getSqlSelect();
    }

    // Set SELECT clause
    public function setSqlSelect($v)
    {
        $this->SqlSelect = $v;
    }

    // Get WHERE clause
    public function getSqlWhere()
    {
        $where = ($this->SqlWhere != "") ? $this->SqlWhere : "";
        $this->DefaultFilter = "";
        AddFilter($where, $this->DefaultFilter);
        return $where;
    }

    // Get WHERE clause (for backward compatibility)
    public function sqlWhere()
    {
        return $this->getSqlWhere();
    }

    // Set WHERE clause
    public function setSqlWhere($v)
    {
        $this->SqlWhere = $v;
    }

    // Get GROUP BY clause
    public function getSqlGroupBy()
    {
        return $this->SqlGroupBy != "" ? $this->SqlGroupBy : "";
    }

    // Get GROUP BY clause (for backward compatibility)
    public function sqlGroupBy()
    {
        return $this->getSqlGroupBy();
    }

    // set GROUP BY clause
    public function setSqlGroupBy($v)
    {
        $this->SqlGroupBy = $v;
    }

    // Get HAVING clause
    public function getSqlHaving() // Having
    {
        return ($this->SqlHaving != "") ? $this->SqlHaving : "";
    }

    // Get HAVING clause (for backward compatibility)
    public function sqlHaving()
    {
        return $this->getSqlHaving();
    }

    // Set HAVING clause
    public function setSqlHaving($v)
    {
        $this->SqlHaving = $v;
    }

    // Get ORDER BY clause
    public function getSqlOrderBy()
    {
        return ($this->SqlOrderBy != "") ? $this->SqlOrderBy : "";
    }

    // Get ORDER BY clause (for backward compatibility)
    public function sqlOrderBy()
    {
        return $this->getSqlOrderBy();
    }

    // set ORDER BY clause
    public function setSqlOrderBy($v)
    {
        $this->SqlOrderBy = $v;
    }

    // Apply User ID filters
    public function applyUserIDFilters($filter, $id = "")
    {
        global $Security;
        // Add User ID filter
        if ($Security->currentUserID() != "" && !$Security->isAdmin()) { // Non system admin
            $filter = $this->addUserIDFilter($filter, $id);
        }
        return $filter;
    }

    // Check if User ID security allows view all
    public function userIDAllow($id = "")
    {
        $allow = $this->UserIDAllowSecurity;
        switch ($id) {
            case "add":
            case "copy":
            case "gridadd":
            case "register":
            case "addopt":
                return ($allow & Allow::ADD->value) == Allow::ADD->value;
            case "edit":
            case "gridedit":
            case "update":
            case "changepassword":
            case "resetpassword":
                return ($allow & Allow::EDIT->value) == Allow::EDIT->value;
            case "delete":
                return ($allow & Allow::DELETE->value) == Allow::DELETE->value;
            case "view":
                return ($allow & Allow::VIEW->value) == Allow::VIEW->value;
            case "search":
                return ($allow & Allow::SEARCH->value) == Allow::SEARCH->value;
            case "lookup":
                return ($allow & Allow::LOOKUP->value) == Allow::LOOKUP->value;
            default:
                return ($allow & Allow::LIST->value) == Allow::LIST->value;
        }
    }

    /**
     * Get record count
     *
     * @param string|QueryBuilder $sql SQL or QueryBuilder
     * @param mixed $c Connection
     * @return int
     */
    public function getRecordCount($sql, $c = null)
    {
        $cnt = -1;
        $sqlwrk = $sql instanceof QueryBuilder // Query builder
            ? (clone $sql)->resetQueryPart("orderBy")->getSQL()
            : $sql;
        $pattern = '/^SELECT\s([\s\S]+?)\sFROM\s/i';
        // Skip Custom View / SubQuery / SELECT DISTINCT / ORDER BY
        if (
            in_array($this->TableType, ["TABLE", "VIEW", "LINKTABLE"]) &&
            preg_match($pattern, $sqlwrk) &&
            !preg_match('/\(\s*(SELECT[^)]+)\)/i', $sqlwrk) &&
            !preg_match('/^\s*SELECT\s+DISTINCT\s+/i', $sqlwrk) &&
            !preg_match('/\s+ORDER\s+BY\s+/i', $sqlwrk)
        ) {
            $sqlcnt = "SELECT COUNT(*) FROM " . preg_replace($pattern, "", $sqlwrk);
        } else {
            $sqlcnt = "SELECT COUNT(*) FROM (" . $sqlwrk . ") COUNT_TABLE";
        }
        $conn = $c ?? $this->getConnection();
        $cnt = $conn->fetchOne($sqlcnt);
        if ($cnt !== false) {
            return (int)$cnt;
        }
        // Unable to get count by SELECT COUNT(*), execute the SQL to get record count directly
        $result = $conn->executeQuery($sqlwrk);
        $cnt = $result->rowCount();
        if ($cnt == 0) { // Unable to get record count, count directly
            while ($result->fetch()) {
                $cnt++;
            }
        }
        return $cnt;
    }

    // Get SQL
    public function getSql($where, $orderBy = "")
    {
        return $this->getSqlAsQueryBuilder($where, $orderBy)->getSQL();
    }

    // Get QueryBuilder
    public function getSqlAsQueryBuilder($where, $orderBy = "")
    {
        return $this->buildSelectSql(
            $this->getSqlSelect(),
            $this->getSqlFrom(),
            $this->getSqlWhere(),
            $this->getSqlGroupBy(),
            $this->getSqlHaving(),
            $this->getSqlOrderBy(),
            $where,
            $orderBy
        );
    }

    // Table SQL
    public function getCurrentSql()
    {
        $filter = $this->CurrentFilter;
        $filter = $this->applyUserIDFilters($filter);
        $sort = $this->getSessionOrderBy();
        return $this->getSql($filter, $sort);
    }

    /**
     * Table SQL with List page filter
     *
     * @return QueryBuilder
     */
    public function getListSql()
    {
        $filter = $this->UseSessionForListSql ? $this->getSessionWhere() : "";
        AddFilter($filter, $this->CurrentFilter);
        $filter = $this->applyUserIDFilters($filter);
        $this->recordsetSelecting($filter);
        $select = $this->getSqlSelect();
        $from = $this->getSqlFrom();
        $sort = $this->UseSessionForListSql ? $this->getSessionOrderBy() : "";
        $this->Sort = $sort;
        return $this->buildSelectSql(
            $select,
            $from,
            $this->getSqlWhere(),
            $this->getSqlGroupBy(),
            $this->getSqlHaving(),
            $this->getSqlOrderBy(),
            $filter,
            $sort
        );
    }

    // Get ORDER BY clause
    public function getOrderBy()
    {
        $orderBy = $this->getSqlOrderBy();
        $sort = $this->getSessionOrderBy();
        if ($orderBy != "" && $sort != "") {
            $orderBy .= ", " . $sort;
        } elseif ($sort != "") {
            $orderBy = $sort;
        }
        return $orderBy;
    }

    // Get record count based on filter (for detail record count in master table pages)
    public function loadRecordCount($filter)
    {
        $origFilter = $this->CurrentFilter;
        $this->CurrentFilter = $filter;
        $this->recordsetSelecting($this->CurrentFilter);
        $isCustomView = $this->TableType == "CUSTOMVIEW";
        $select = $isCustomView ? $this->getSqlSelect() : $this->getQueryBuilder()->select("*");
        $groupBy = $isCustomView ? $this->getSqlGroupBy() : "";
        $having = $isCustomView ? $this->getSqlHaving() : "";
        $sql = $this->buildSelectSql($select, $this->getSqlFrom(), $this->getSqlWhere(), $groupBy, $having, "", $this->CurrentFilter, "");
        $cnt = $this->getRecordCount($sql);
        $this->CurrentFilter = $origFilter;
        return $cnt;
    }

    // Get record count (for current List page)
    public function listRecordCount()
    {
        $filter = $this->getSessionWhere();
        AddFilter($filter, $this->CurrentFilter);
        $filter = $this->applyUserIDFilters($filter);
        $this->recordsetSelecting($filter);
        $isCustomView = $this->TableType == "CUSTOMVIEW";
        $select = $isCustomView ? $this->getSqlSelect() : $this->getQueryBuilder()->select("*");
        $groupBy = $isCustomView ? $this->getSqlGroupBy() : "";
        $having = $isCustomView ? $this->getSqlHaving() : "";
        $sql = $this->buildSelectSql($select, $this->getSqlFrom(), $this->getSqlWhere(), $groupBy, $having, "", $filter, "");
        $cnt = $this->getRecordCount($sql);
        return $cnt;
    }

    /**
     * INSERT statement
     *
     * @param mixed $rs
     * @return QueryBuilder
     */
    public function insertSql(&$rs)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->insert($this->UpdateTable);
        $platform = $this->getConnection()->getDatabasePlatform();
        foreach ($rs as $name => $value) {
            if (!isset($this->Fields[$name]) || $this->Fields[$name]->IsCustom) {
                continue;
            }
            if (Config("ENCRYPTED_PASSWORD") && $name == Config("LOGIN_PASSWORD_FIELD_NAME")) {
                $value = EncryptPassword(Config("CASE_SENSITIVE_PASSWORD") ? $value : strtolower($value));
            }
            $field = $this->Fields[$name];
            $parm = $queryBuilder->createPositionalParameter($value, $field->getParameterType());
            $parm = $field->CustomDataType?->convertToDatabaseValueSQL($parm, $platform) ?? $parm; // Convert database SQL
            $queryBuilder->setValue($field->Expression, $parm);
        }
        return $queryBuilder;
    }

    // Insert
    public function insert(&$rs)
    {
        $conn = $this->getConnection();
        try {
            $queryBuilder = $this->insertSql($rs);
            $result = $conn->executeQuery(
                $queryBuilder->getSQL() . " RETURNING user_id",
                $queryBuilder->getParameters(),
                $queryBuilder->getParameterTypes()
            )->fetchOne();
            $this->DbErrorMessage = "";
        } catch (\Exception $e) {
            $result = false;
            $this->DbErrorMessage = $e->getMessage();
        }
        if ($result) {
            $this->user_id->setDbValue($result);
            $rs['user_id'] = $this->user_id->DbValue;
            if ($this->AuditTrailOnAdd) {
                $this->writeAuditTrailOnAdd($rs);
            }
        }
        return $result !== false ? 1 : false;
    }

    /**
     * UPDATE statement
     *
     * @param array $rs Data to be updated
     * @param string|array $where WHERE clause
     * @param string $curfilter Filter
     * @return QueryBuilder
     */
    public function updateSql(&$rs, $where = "", $curfilter = true)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->update($this->UpdateTable);
        $platform = $this->getConnection()->getDatabasePlatform();
        foreach ($rs as $name => $value) {
            if (!isset($this->Fields[$name]) || $this->Fields[$name]->IsCustom || $this->Fields[$name]->IsAutoIncrement) {
                continue;
            }
            if (Config("ENCRYPTED_PASSWORD") && $name == Config("LOGIN_PASSWORD_FIELD_NAME")) {
                if ($value == $this->Fields[$name]->OldValue) { // No need to update hashed password if not changed
                    continue;
                }
                $value = EncryptPassword(Config("CASE_SENSITIVE_PASSWORD") ? $value : strtolower($value));
            }
            $field = $this->Fields[$name];
            $parm = $queryBuilder->createPositionalParameter($value, $field->getParameterType());
            $parm = $field->CustomDataType?->convertToDatabaseValueSQL($parm, $platform) ?? $parm; // Convert database SQL
            $queryBuilder->set($field->Expression, $parm);
        }
        $filter = $curfilter ? $this->CurrentFilter : "";
        if (is_array($where)) {
            $where = $this->arrayToFilter($where);
        }
        AddFilter($filter, $where);
        if ($filter != "") {
            $queryBuilder->where($filter);
        }
        return $queryBuilder;
    }

    // Update
    public function update(&$rs, $where = "", $rsold = null, $curfilter = true)
    {
        // If no field is updated, execute may return 0. Treat as success
        try {
            $success = $this->updateSql($rs, $where, $curfilter)->executeStatement();
            $success = $success > 0 ? $success : true;
            $this->DbErrorMessage = "";
        } catch (\Exception $e) {
            $success = false;
            $this->DbErrorMessage = $e->getMessage();
        }

        // Return auto increment field
        if ($success) {
            if (!isset($rs['user_id']) && !EmptyValue($this->user_id->CurrentValue)) {
                $rs['user_id'] = $this->user_id->CurrentValue;
            }
        }
        if ($success && $this->AuditTrailOnEdit && $rsold) {
            $rsaudit = $rs;
            $fldname = 'user_id';
            if (!array_key_exists($fldname, $rsaudit)) {
                $rsaudit[$fldname] = $rsold[$fldname];
            }
            $this->writeAuditTrailOnEdit($rsold, $rsaudit);
        }
        return $success;
    }

    /**
     * DELETE statement
     *
     * @param array $rs Key values
     * @param string|array $where WHERE clause
     * @param string $curfilter Filter
     * @return QueryBuilder
     */
    public function deleteSql(&$rs, $where = "", $curfilter = true)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->delete($this->UpdateTable);
        if (is_array($where)) {
            $where = $this->arrayToFilter($where);
        }
        if ($rs) {
            if (array_key_exists('user_id', $rs)) {
                AddFilter($where, QuotedName('user_id', $this->Dbid) . '=' . QuotedValue($rs['user_id'], $this->user_id->DataType, $this->Dbid));
            }
        }
        $filter = $curfilter ? $this->CurrentFilter : "";
        AddFilter($filter, $where);
        return $queryBuilder->where($filter != "" ? $filter : "0=1");
    }

    // Delete
    public function delete(&$rs, $where = "", $curfilter = false)
    {
        $success = true;
        if ($success) {
            try {
                $success = $this->deleteSql($rs, $where, $curfilter)->executeStatement();
                $this->DbErrorMessage = "";
            } catch (\Exception $e) {
                $success = false;
                $this->DbErrorMessage = $e->getMessage();
            }
        }
        if ($success && $this->AuditTrailOnDelete) {
            $this->writeAuditTrailOnDelete($rs);
        }
        return $success;
    }

    // Load DbValue from result set or array
    protected function loadDbValues($row)
    {
        if (!is_array($row)) {
            return;
        }
        $this->user_id->DbValue = $row['user_id'];
        $this->_username->DbValue = $row['username'];
        $this->_email->DbValue = $row['email'];
        $this->password_hash->DbValue = $row['password_hash'];
        $this->mobile_number->DbValue = $row['mobile_number'];
        $this->first_name->DbValue = $row['first_name'];
        $this->middle_name->DbValue = $row['middle_name'];
        $this->last_name->DbValue = $row['last_name'];
        $this->suffix->DbValue = $row['suffix'];
        $this->department_id->DbValue = $row['department_id'];
        $this->date_created->DbValue = $row['date_created'];
        $this->last_login->DbValue = $row['last_login'];
        $this->is_active->DbValue = (ConvertToBool($row['is_active']) ? "1" : "0");
        $this->user_level_id->DbValue = $row['user_level_id'];
        $this->reports_to_user_id->DbValue = $row['reports_to_user_id'];
        $this->photo->Upload->DbValue = $row['photo'];
        $this->_profile->DbValue = $row['profile'];
        $this->position_id->DbValue = $row['position_id'];
        $this->employee_id->DbValue = $row['employee_id'];
        $this->must_change_password->DbValue = (ConvertToBool($row['must_change_password']) ? "1" : "0");
        $this->rc_id->DbValue = $row['rc_id'];
    }

    // Delete uploaded files
    public function deleteUploadedFiles($row)
    {
        $this->loadDbValues($row);
        $this->photo->OldUploadPath = $this->photo->getUploadPath(); // PHP
        $oldFiles = EmptyValue($row['photo']) ? [] : [$row['photo']];
        foreach ($oldFiles as $oldFile) {
            if (file_exists($this->photo->oldPhysicalUploadPath() . $oldFile)) {
                @unlink($this->photo->oldPhysicalUploadPath() . $oldFile);
            }
        }
    }

    // Record filter WHERE clause
    protected function sqlKeyFilter()
    {
        return "\"user_id\" = @user_id@";
    }

    // Get Key
    public function getKey($current = false, $keySeparator = null)
    {
        $keys = [];
        $val = $current ? $this->user_id->CurrentValue : $this->user_id->OldValue;
        if (EmptyValue($val)) {
            return "";
        } else {
            $keys[] = $val;
        }
        $keySeparator ??= Config("COMPOSITE_KEY_SEPARATOR");
        return implode($keySeparator, $keys);
    }

    // Set Key
    public function setKey($key, $current = false, $keySeparator = null)
    {
        $keySeparator ??= Config("COMPOSITE_KEY_SEPARATOR");
        $this->OldKey = strval($key);
        $keys = explode($keySeparator, $this->OldKey);
        if (count($keys) == 1) {
            if ($current) {
                $this->user_id->CurrentValue = $keys[0];
            } else {
                $this->user_id->OldValue = $keys[0];
            }
        }
    }

    // Get record filter
    public function getRecordFilter($row = null, $current = false)
    {
        $keyFilter = $this->sqlKeyFilter();
        if (is_array($row)) {
            $val = array_key_exists('user_id', $row) ? $row['user_id'] : null;
        } else {
            $val = !EmptyValue($this->user_id->OldValue) && !$current ? $this->user_id->OldValue : $this->user_id->CurrentValue;
        }
        if (!is_numeric($val)) {
            return "0=1"; // Invalid key
        }
        if ($val === null) {
            return "0=1"; // Invalid key
        } else {
            $keyFilter = str_replace("@user_id@", AdjustSql($val, $this->Dbid), $keyFilter); // Replace key value
        }
        return $keyFilter;
    }

    // Return page URL
    public function getReturnUrl()
    {
        $referUrl = ReferUrl();
        $referPageName = ReferPageName();
        $name = PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_RETURN_URL");
        // Get referer URL automatically
        if ($referUrl != "" && $referPageName != CurrentPageName() && $referPageName != "login") { // Referer not same page or login page
            $_SESSION[$name] = $referUrl; // Save to Session
        }
        return $_SESSION[$name] ?? GetUrl("UsersList");
    }

    // Set return page URL
    public function setReturnUrl($v)
    {
        $_SESSION[PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_RETURN_URL")] = $v;
    }

    // Get modal caption
    public function getModalCaption($pageName)
    {
        global $Language;
        return match ($pageName) {
            "UsersView" => $Language->phrase("View"),
            "UsersEdit" => $Language->phrase("Edit"),
            "UsersAdd" => $Language->phrase("Add"),
            default => ""
        };
    }

    // Default route URL
    public function getDefaultRouteUrl()
    {
        return "UsersList";
    }

    // API page name
    public function getApiPageName($action)
    {
        return match (strtolower($action)) {
            Config("API_VIEW_ACTION") => "UsersView",
            Config("API_ADD_ACTION") => "UsersAdd",
            Config("API_EDIT_ACTION") => "UsersEdit",
            Config("API_DELETE_ACTION") => "UsersDelete",
            Config("API_LIST_ACTION") => "UsersList",
            default => ""
        };
    }

    // Current URL
    public function getCurrentUrl($parm = "")
    {
        $url = CurrentPageUrl(false);
        if ($parm != "") {
            $url = $this->keyUrl($url, $parm);
        } else {
            $url = $this->keyUrl($url, Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // List URL
    public function getListUrl()
    {
        return "UsersList";
    }

    // View URL
    public function getViewUrl($parm = "")
    {
        if ($parm != "") {
            $url = $this->keyUrl("UsersView", $parm);
        } else {
            $url = $this->keyUrl("UsersView", Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // Add URL
    public function getAddUrl($parm = "")
    {
        if ($parm != "") {
            $url = "UsersAdd?" . $parm;
        } else {
            $url = "UsersAdd";
        }
        return $this->addMasterUrl($url);
    }

    // Edit URL
    public function getEditUrl($parm = "")
    {
        if ($parm != "") {
            $url = $this->keyUrl("UsersEdit", $parm);
        } else {
            $url = $this->keyUrl("UsersEdit", Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // Inline edit URL
    public function getInlineEditUrl()
    {
        $url = $this->keyUrl("UsersList", "action=edit");
        return $this->addMasterUrl($url);
    }

    // Copy URL
    public function getCopyUrl($parm = "")
    {
        if ($parm != "") {
            $url = $this->keyUrl("UsersAdd", $parm);
        } else {
            $url = $this->keyUrl("UsersAdd", Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // Inline copy URL
    public function getInlineCopyUrl()
    {
        $url = $this->keyUrl("UsersList", "action=copy");
        return $this->addMasterUrl($url);
    }

    // Delete URL
    public function getDeleteUrl($parm = "")
    {
        if ($this->UseAjaxActions && ConvertToBool(Param("infinitescroll")) && CurrentPageID() == "list") {
            return $this->keyUrl(GetApiUrl(Config("API_DELETE_ACTION") . "/" . $this->TableVar));
        } else {
            return $this->keyUrl("UsersDelete", $parm);
        }
    }

    // Add master url
    public function addMasterUrl($url)
    {
        if ($this->getCurrentMasterTable() == "_user_levels" && !ContainsString($url, Config("TABLE_SHOW_MASTER") . "=")) {
            $url .= (ContainsString($url, "?") ? "&" : "?") . Config("TABLE_SHOW_MASTER") . "=" . $this->getCurrentMasterTable();
            $url .= "&" . GetForeignKeyUrl("fk_user_level_id", $this->user_level_id->getSessionValue()); // Use Session Value
        }
        return $url;
    }

    public function keyToJson($htmlEncode = false)
    {
        $json = "";
        $json .= "\"user_id\":" . VarToJson($this->user_id->CurrentValue, "number");
        $json = "{" . $json . "}";
        if ($htmlEncode) {
            $json = HtmlEncode($json);
        }
        return $json;
    }

    // Add key value to URL
    public function keyUrl($url, $parm = "")
    {
        if ($this->user_id->CurrentValue !== null) {
            $url .= "/" . $this->encodeKeyValue($this->user_id->CurrentValue);
        } else {
            return "javascript:ew.alert(ew.language.phrase('InvalidRecord'));";
        }
        if ($parm != "") {
            $url .= "?" . $parm;
        }
        return $url;
    }

    // Render sort
    public function renderFieldHeader($fld)
    {
        global $Security, $Language;
        $sortUrl = "";
        $attrs = "";
        if ($this->PageID != "grid" && $fld->Sortable) {
            $sortUrl = $this->sortUrl($fld);
            $attrs = ' role="button" data-ew-action="sort" data-ajax="' . ($this->UseAjaxActions ? "true" : "false") . '" data-sort-url="' . $sortUrl . '" data-sort-type="1"';
            if ($this->ContextClass) { // Add context
                $attrs .= ' data-context="' . HtmlEncode($this->ContextClass) . '"';
            }
        }
        $html = '<div class="ew-table-header-caption"' . $attrs . '>' . $fld->caption() . '</div>';
        if ($sortUrl) {
            $html .= '<div class="ew-table-header-sort">' . $fld->getSortIcon() . '</div>';
        }
        if ($this->PageID != "grid" && !$this->isExport() && $fld->UseFilter && $Security->canSearch()) {
            $html .= '<div class="ew-filter-dropdown-btn" data-ew-action="filter" data-table="' . $fld->TableVar . '" data-field="' . $fld->FieldVar .
                '"><div class="ew-table-header-filter" role="button" aria-haspopup="true">' . $Language->phrase("Filter") .
                (is_array($fld->EditValue) ? str_replace("%c", count($fld->EditValue), $Language->phrase("FilterCount")) : '') .
                '</div></div>';
        }
        $html = '<div class="ew-table-header-btn">' . $html . '</div>';
        if ($this->UseCustomTemplate) {
            $scriptId = str_replace("{id}", $fld->TableVar . "_" . $fld->Param, "tpc_{id}");
            $html = '<template id="' . $scriptId . '">' . $html . '</template>';
        }
        return $html;
    }

    // Sort URL
    public function sortUrl($fld)
    {
        global $DashboardReport;
        if (
            $this->CurrentAction || $this->isExport() ||
            in_array($fld->Type, [128, 204, 205])
        ) { // Unsortable data type
                return "";
        } elseif ($fld->Sortable) {
            $urlParm = "order=" . urlencode($fld->Name) . "&amp;ordertype=" . $fld->getNextSort();
            if ($DashboardReport) {
                $urlParm .= "&amp;" . Config("PAGE_DASHBOARD") . "=" . $DashboardReport;
            }
            return $this->addMasterUrl($this->CurrentPageName . "?" . $urlParm);
        } else {
            return "";
        }
    }

    // Get record keys from Post/Get/Session
    public function getRecordKeys()
    {
        $arKeys = [];
        $arKey = [];
        if (Param("key_m") !== null) {
            $arKeys = Param("key_m");
            $cnt = count($arKeys);
        } else {
            $isApi = IsApi();
            $keyValues = $isApi
                ? (Route(0) == "export"
                    ? array_map(fn ($i) => Route($i + 3), range(0, 0))  // Export API
                    : array_map(fn ($i) => Route($i + 2), range(0, 0))) // Other API
                : []; // Non-API
            if (($keyValue = Param("user_id") ?? Route("user_id")) !== null) {
                $arKeys[] = $keyValue;
            } elseif ($isApi && (($keyValue = Key(0) ?? $keyValues[0] ?? null) !== null)) {
                $arKeys[] = $keyValue;
            } else {
                $arKeys = null; // Do not setup
            }
        }
        // Check keys
        $ar = [];
        if (is_array($arKeys)) {
            foreach ($arKeys as $key) {
                if (!is_numeric($key)) {
                    continue;
                }
                $ar[] = $key;
            }
        }
        return $ar;
    }

    // Get filter from records
    public function getFilterFromRecords($rows)
    {
        return implode(" OR ", array_map(fn($row) => "(" . $this->getRecordFilter($row) . ")", $rows));
    }

    // Get filter from record keys
    public function getFilterFromRecordKeys($setCurrent = true)
    {
        $arKeys = $this->getRecordKeys();
        $keyFilter = "";
        foreach ($arKeys as $key) {
            if ($keyFilter != "") {
                $keyFilter .= " OR ";
            }
            if ($setCurrent) {
                $this->user_id->CurrentValue = $key;
            } else {
                $this->user_id->OldValue = $key;
            }
            $keyFilter .= "(" . $this->getRecordFilter() . ")";
        }
        return $keyFilter;
    }

    // Load result set based on filter/sort
    public function loadRs($filter, $sort = "")
    {
        $sql = $this->getSql($filter, $sort); // Set up filter (WHERE Clause) / sort (ORDER BY Clause)
        $conn = $this->getConnection();
        return $conn->executeQuery($sql);
    }

    // Load row values from record
    public function loadListRowValues(&$rs)
    {
        if (is_array($rs)) {
            $row = $rs;
        } elseif ($rs && property_exists($rs, "fields")) { // Recordset
            $row = $rs->fields;
        } else {
            return;
        }
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
        $this->is_active->setDbValue(ConvertToBool($row['is_active']) ? "1" : "0");
        $this->user_level_id->setDbValue($row['user_level_id']);
        $this->reports_to_user_id->setDbValue($row['reports_to_user_id']);
        $this->photo->Upload->DbValue = $row['photo'];
        $this->_profile->setDbValue($row['profile']);
        $this->position_id->setDbValue($row['position_id']);
        $this->employee_id->setDbValue($row['employee_id']);
        $this->must_change_password->setDbValue(ConvertToBool($row['must_change_password']) ? "1" : "0");
        $this->rc_id->setDbValue($row['rc_id']);
    }

    // Render list content
    public function renderListContent($filter)
    {
        global $Response;
        $listPage = "UsersList";
        $listClass = PROJECT_NAMESPACE . $listPage;
        $page = new $listClass();
        $page->loadRecordsetFromFilter($filter);
        $view = Container("app.view");
        $template = $listPage . ".php"; // View
        $GLOBALS["Title"] ??= $page->Title; // Title
        try {
            $Response = $view->render($Response, $template, $GLOBALS);
        } finally {
            $page->terminate(); // Terminate page and clean up
        }
    }

    // Render list row values
    public function renderListRow()
    {
        global $Security, $CurrentLanguage, $Language;

        // Call Row Rendering event
        $this->rowRendering();

        // Common render codes

        // user_id

        // username

        // email

        // password_hash

        // mobile_number

        // first_name

        // middle_name

        // last_name

        // suffix

        // department_id

        // date_created

        // last_login

        // is_active

        // user_level_id

        // reports_to_user_id

        // photo

        // profile

        // position_id

        // employee_id

        // must_change_password

        // rc_id

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

        // profile
        $this->_profile->ViewValue = $this->_profile->CurrentValue;

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

        // user_id
        $this->user_id->HrefValue = "";
        $this->user_id->TooltipValue = "";

        // username
        $this->_username->HrefValue = "";
        $this->_username->TooltipValue = "";

        // email
        $this->_email->HrefValue = "";
        $this->_email->TooltipValue = "";

        // password_hash
        $this->password_hash->HrefValue = "";
        $this->password_hash->TooltipValue = "";

        // mobile_number
        $this->mobile_number->HrefValue = "";
        $this->mobile_number->TooltipValue = "";

        // first_name
        $this->first_name->HrefValue = "";
        $this->first_name->TooltipValue = "";

        // middle_name
        $this->middle_name->HrefValue = "";
        $this->middle_name->TooltipValue = "";

        // last_name
        $this->last_name->HrefValue = "";
        $this->last_name->TooltipValue = "";

        // suffix
        $this->suffix->HrefValue = "";
        $this->suffix->TooltipValue = "";

        // department_id
        $this->department_id->HrefValue = "";
        $this->department_id->TooltipValue = "";

        // date_created
        $this->date_created->HrefValue = "";
        $this->date_created->TooltipValue = "";

        // last_login
        $this->last_login->HrefValue = "";
        $this->last_login->TooltipValue = "";

        // is_active
        $this->is_active->HrefValue = "";
        $this->is_active->TooltipValue = "";

        // user_level_id
        $this->user_level_id->HrefValue = "";
        $this->user_level_id->TooltipValue = "";

        // reports_to_user_id
        $this->reports_to_user_id->HrefValue = "";
        $this->reports_to_user_id->TooltipValue = "";

        // photo
        $this->photo->HrefValue = "";
        $this->photo->ExportHrefValue = $this->photo->UploadPath . $this->photo->Upload->DbValue;
        $this->photo->TooltipValue = "";

        // profile
        $this->_profile->HrefValue = "";
        $this->_profile->TooltipValue = "";

        // position_id
        $this->position_id->HrefValue = "";
        $this->position_id->TooltipValue = "";

        // employee_id
        $this->employee_id->HrefValue = "";
        $this->employee_id->TooltipValue = "";

        // must_change_password
        $this->must_change_password->HrefValue = "";
        $this->must_change_password->TooltipValue = "";

        // rc_id
        $this->rc_id->HrefValue = "";
        $this->rc_id->TooltipValue = "";

        // Call Row Rendered event
        $this->rowRendered();

        // Save data for Custom Template
        $this->Rows[] = $this->customTemplateFieldValues();
    }

    // Render edit row values
    public function renderEditRow()
    {
        global $Security, $CurrentLanguage, $Language;

        // Call Row Rendering event
        $this->rowRendering();

        // user_id
        $this->user_id->setupEditAttributes();
        $this->user_id->EditValue = $this->user_id->CurrentValue;

        // username
        $this->_username->setupEditAttributes();
        if (!$this->_username->Raw) {
            $this->_username->CurrentValue = HtmlDecode($this->_username->CurrentValue);
        }
        $this->_username->EditValue = $this->_username->CurrentValue;
        $this->_username->PlaceHolder = RemoveHtml($this->_username->caption());

        // email
        $this->_email->setupEditAttributes();
        if (!$this->_email->Raw) {
            $this->_email->CurrentValue = HtmlDecode($this->_email->CurrentValue);
        }
        $this->_email->EditValue = $this->_email->CurrentValue;
        $this->_email->PlaceHolder = RemoveHtml($this->_email->caption());

        // password_hash
        $this->password_hash->setupEditAttributes();
        $this->password_hash->EditValue = $this->password_hash->CurrentValue;
        $this->password_hash->PlaceHolder = RemoveHtml($this->password_hash->caption());

        // mobile_number
        $this->mobile_number->setupEditAttributes();
        if (!$this->mobile_number->Raw) {
            $this->mobile_number->CurrentValue = HtmlDecode($this->mobile_number->CurrentValue);
        }
        $this->mobile_number->EditValue = $this->mobile_number->CurrentValue;
        $this->mobile_number->PlaceHolder = RemoveHtml($this->mobile_number->caption());

        // first_name
        $this->first_name->setupEditAttributes();
        if (!$this->first_name->Raw) {
            $this->first_name->CurrentValue = HtmlDecode($this->first_name->CurrentValue);
        }
        $this->first_name->EditValue = $this->first_name->CurrentValue;
        $this->first_name->PlaceHolder = RemoveHtml($this->first_name->caption());

        // middle_name
        $this->middle_name->setupEditAttributes();
        if (!$this->middle_name->Raw) {
            $this->middle_name->CurrentValue = HtmlDecode($this->middle_name->CurrentValue);
        }
        $this->middle_name->EditValue = $this->middle_name->CurrentValue;
        $this->middle_name->PlaceHolder = RemoveHtml($this->middle_name->caption());

        // last_name
        $this->last_name->setupEditAttributes();
        if (!$this->last_name->Raw) {
            $this->last_name->CurrentValue = HtmlDecode($this->last_name->CurrentValue);
        }
        $this->last_name->EditValue = $this->last_name->CurrentValue;
        $this->last_name->PlaceHolder = RemoveHtml($this->last_name->caption());

        // suffix
        $this->suffix->setupEditAttributes();
        if (!$this->suffix->Raw) {
            $this->suffix->CurrentValue = HtmlDecode($this->suffix->CurrentValue);
        }
        $this->suffix->EditValue = $this->suffix->CurrentValue;
        $this->suffix->PlaceHolder = RemoveHtml($this->suffix->caption());

        // department_id
        $this->department_id->setupEditAttributes();
        $this->department_id->EditValue = $this->department_id->CurrentValue;
        $this->department_id->PlaceHolder = RemoveHtml($this->department_id->caption());

        // date_created

        // last_login
        $this->last_login->setupEditAttributes();
        $this->last_login->CurrentValue = FormatDateTime($this->last_login->CurrentValue, $this->last_login->formatPattern());

        // is_active
        $this->is_active->EditValue = $this->is_active->options(false);
        $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

        // user_level_id
        $this->user_level_id->setupEditAttributes();
        if ($this->user_level_id->getSessionValue() != "") {
            $this->user_level_id->CurrentValue = GetForeignKeyValue($this->user_level_id->getSessionValue());
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
        } elseif (!$Security->canAdmin()) { // System admin
            $this->user_level_id->EditValue = $Language->phrase("PasswordMask");
        } else {
            $this->user_level_id->PlaceHolder = RemoveHtml($this->user_level_id->caption());
        }

        // reports_to_user_id
        $this->reports_to_user_id->setupEditAttributes();
        if (!$Security->isAdmin() && $Security->isLoggedIn()) { // Non system admin
            if (SameString($this->user_id->CurrentValue, CurrentUserID())) {
                $this->reports_to_user_id->EditValue = $this->reports_to_user_id->CurrentValue;
                $this->reports_to_user_id->EditValue = FormatNumber($this->reports_to_user_id->EditValue, $this->reports_to_user_id->formatPattern());
            } else {
            }
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

        // profile
        $this->_profile->setupEditAttributes();

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
        $this->employee_id->EditValue = $this->employee_id->CurrentValue;
        $this->employee_id->PlaceHolder = RemoveHtml($this->employee_id->caption());

        // must_change_password
        $this->must_change_password->EditValue = $this->must_change_password->options(false);
        $this->must_change_password->PlaceHolder = RemoveHtml($this->must_change_password->caption());

        // rc_id
        $this->rc_id->setupEditAttributes();
        if (!$this->rc_id->Raw) {
            $this->rc_id->CurrentValue = HtmlDecode($this->rc_id->CurrentValue);
        }
        $this->rc_id->EditValue = $this->rc_id->CurrentValue;
        $this->rc_id->PlaceHolder = RemoveHtml($this->rc_id->caption());

        // Call Row Rendered event
        $this->rowRendered();
    }

    // Aggregate list row values
    public function aggregateListRowValues()
    {
    }

    // Aggregate list row (for rendering)
    public function aggregateListRow()
    {
        // Call Row Rendered event
        $this->rowRendered();
    }

    // Export data in HTML/CSV/Word/Excel/Email/PDF format
    public function exportDocument($doc, $result, $startRec = 1, $stopRec = 1, $exportPageType = "")
    {
        if (!$result || !$doc) {
            return;
        }
        if (!$doc->ExportCustom) {
            // Write header
            $doc->exportTableHeader();
            if ($doc->Horizontal) { // Horizontal format, write header
                $doc->beginExportRow();
                if ($exportPageType == "view") {
                    $doc->exportCaption($this->user_id);
                    $doc->exportCaption($this->_username);
                    $doc->exportCaption($this->_email);
                    $doc->exportCaption($this->password_hash);
                    $doc->exportCaption($this->mobile_number);
                    $doc->exportCaption($this->first_name);
                    $doc->exportCaption($this->middle_name);
                    $doc->exportCaption($this->last_name);
                    $doc->exportCaption($this->suffix);
                    $doc->exportCaption($this->department_id);
                    $doc->exportCaption($this->date_created);
                    $doc->exportCaption($this->last_login);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->user_level_id);
                    $doc->exportCaption($this->reports_to_user_id);
                    $doc->exportCaption($this->photo);
                    $doc->exportCaption($this->position_id);
                    $doc->exportCaption($this->employee_id);
                    $doc->exportCaption($this->must_change_password);
                    $doc->exportCaption($this->rc_id);
                } else {
                    $doc->exportCaption($this->user_id);
                    $doc->exportCaption($this->_username);
                    $doc->exportCaption($this->_email);
                    $doc->exportCaption($this->password_hash);
                    $doc->exportCaption($this->mobile_number);
                    $doc->exportCaption($this->first_name);
                    $doc->exportCaption($this->middle_name);
                    $doc->exportCaption($this->last_name);
                    $doc->exportCaption($this->suffix);
                    $doc->exportCaption($this->department_id);
                    $doc->exportCaption($this->date_created);
                    $doc->exportCaption($this->last_login);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->user_level_id);
                    $doc->exportCaption($this->reports_to_user_id);
                    $doc->exportCaption($this->photo);
                    $doc->exportCaption($this->position_id);
                    $doc->exportCaption($this->employee_id);
                    $doc->exportCaption($this->must_change_password);
                    $doc->exportCaption($this->rc_id);
                }
                $doc->endExportRow();
            }
        }
        $recCnt = $startRec - 1;
        $stopRec = $stopRec > 0 ? $stopRec : PHP_INT_MAX;
        while (($row = $result->fetch()) && $recCnt < $stopRec) {
            $recCnt++;
            if ($recCnt >= $startRec) {
                $rowCnt = $recCnt - $startRec + 1;

                // Page break
                if ($this->ExportPageBreakCount > 0) {
                    if ($rowCnt > 1 && ($rowCnt - 1) % $this->ExportPageBreakCount == 0) {
                        $doc->exportPageBreak();
                    }
                }
                $this->loadListRowValues($row);

                // Render row
                $this->RowType = RowType::VIEW; // Render view
                $this->resetAttributes();
                $this->renderListRow();
                if (!$doc->ExportCustom) {
                    $doc->beginExportRow($rowCnt); // Allow CSS styles if enabled
                    if ($exportPageType == "view") {
                        $doc->exportField($this->user_id);
                        $doc->exportField($this->_username);
                        $doc->exportField($this->_email);
                        $doc->exportField($this->password_hash);
                        $doc->exportField($this->mobile_number);
                        $doc->exportField($this->first_name);
                        $doc->exportField($this->middle_name);
                        $doc->exportField($this->last_name);
                        $doc->exportField($this->suffix);
                        $doc->exportField($this->department_id);
                        $doc->exportField($this->date_created);
                        $doc->exportField($this->last_login);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->user_level_id);
                        $doc->exportField($this->reports_to_user_id);
                        $doc->exportField($this->photo);
                        $doc->exportField($this->position_id);
                        $doc->exportField($this->employee_id);
                        $doc->exportField($this->must_change_password);
                        $doc->exportField($this->rc_id);
                    } else {
                        $doc->exportField($this->user_id);
                        $doc->exportField($this->_username);
                        $doc->exportField($this->_email);
                        $doc->exportField($this->password_hash);
                        $doc->exportField($this->mobile_number);
                        $doc->exportField($this->first_name);
                        $doc->exportField($this->middle_name);
                        $doc->exportField($this->last_name);
                        $doc->exportField($this->suffix);
                        $doc->exportField($this->department_id);
                        $doc->exportField($this->date_created);
                        $doc->exportField($this->last_login);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->user_level_id);
                        $doc->exportField($this->reports_to_user_id);
                        $doc->exportField($this->photo);
                        $doc->exportField($this->position_id);
                        $doc->exportField($this->employee_id);
                        $doc->exportField($this->must_change_password);
                        $doc->exportField($this->rc_id);
                    }
                    $doc->endExportRow($rowCnt);
                }
            }

            // Call Row Export server event
            if ($doc->ExportCustom) {
                $this->rowExport($doc, $row);
            }
        }
        if (!$doc->ExportCustom) {
            $doc->exportTableFooter();
        }
    }

    // User ID filter
    public function getUserIDFilter($userId)
    {
        global $Security;
        $userIdFilter = '"user_id" = ' . QuotedValue($userId, DataType::NUMBER, Config("USER_TABLE_DBID"));
        $parentUserIdFilter = '"user_id" IN (SELECT "user_id" FROM ' . "uac.users" . ' WHERE "reports_to_user_id" = ' . QuotedValue($userId, DataType::NUMBER, Config("USER_TABLE_DBID")) . ')';
        $userIdFilter = "(" . $userIdFilter . ") OR (" . $parentUserIdFilter . ")";
        return $userIdFilter;
    }

    // Add User ID filter
    public function addUserIDFilter($filter = "", $id = "")
    {
        global $Security;
        $filterWrk = "";
        if ($id == "") {
            $id = CurrentPageID() == "list" ? $this->CurrentAction : CurrentPageID();
        }
        if (!$this->userIDAllow($id) && !$Security->isAdmin()) {
            $filterWrk = $Security->userIdList();
            if ($filterWrk != "") {
                $filterWrk = '"user_id" IN (' . $filterWrk . ')';
            }
        }

        // Call User ID Filtering event
        $this->userIdFiltering($filterWrk);
        AddFilter($filter, $filterWrk);
        return $filter;
    }

    // Add Parent User ID filter
    public function addParentUserIDFilter($userId)
    {
        global $Security;
        if (!$Security->isAdmin()) {
            $result = $Security->parentUserIDList($userId);
            if ($result != "") {
                $result = '"user_id" IN (' . $result . ')';
            }
            return $result;
        }
        return "";
    }

    // User ID subquery
    public function getUserIDSubquery(&$fld, &$masterfld)
    {
        $wrk = "";
        $sql = "SELECT " . $masterfld->Expression . " FROM uac.users";
        $filter = $this->addUserIDFilter("");
        if ($filter != "") {
            $sql .= " WHERE " . $filter;
        }

        // List all values
        $conn = Conn($this->Dbid);
        $config = $conn->getConfiguration();
        $config->setResultCache($this->Cache);
        if ($rows = $conn->executeCacheQuery($sql, [], [], $this->CacheProfile)->fetchAllNumeric()) {
            $wrk = implode(",", array_map(fn($row) => QuotedValue($row[0], $masterfld->DataType, $this->Dbid), $rows));
        }
        if ($wrk != "") {
            $wrk = $fld->Expression . " IN (" . $wrk . ")";
        } else { // No User ID value found
            $wrk = "0=1";
        }
        return $wrk;
    }

    // Get file data
    public function getFileData($fldparm, $key, $resize, $width = 0, $height = 0, $plugins = [])
    {
        global $DownloadFileName;
        $width = ($width > 0) ? $width : Config("THUMBNAIL_DEFAULT_WIDTH");
        $height = ($height > 0) ? $height : Config("THUMBNAIL_DEFAULT_HEIGHT");

        // Set up field name / file name field / file type field
        $fldName = "";
        $fileNameFld = "";
        $fileTypeFld = "";
        if ($fldparm == 'photo') {
            $fldName = "photo";
            $fileNameFld = "photo";
        } else {
            return false; // Incorrect field
        }

        // Set up key values
        $ar = explode(Config("COMPOSITE_KEY_SEPARATOR"), $key);
        if (count($ar) == 1) {
            $this->user_id->CurrentValue = $ar[0];
        } else {
            return false; // Incorrect key
        }

        // Set up filter (WHERE Clause)
        $filter = $this->getRecordFilter();
        $this->CurrentFilter = $filter;
        $sql = $this->getCurrentSql();
        $conn = $this->getConnection();
        $dbtype = GetConnectionType($this->Dbid);
        if ($row = $conn->fetchAssociative($sql)) {
            $val = $row[$fldName];
            if (!EmptyValue($val)) {
                $fld = $this->Fields[$fldName];

                // Binary data
                if ($fld->DataType == DataType::BLOB) {
                    if ($dbtype != "MYSQL") {
                        if (is_resource($val) && get_resource_type($val) == "stream") { // Byte array
                            $val = stream_get_contents($val);
                        }
                    }
                    if ($resize) {
                        ResizeBinary($val, $width, $height, $plugins);
                    }

                    // Write file type
                    if ($fileTypeFld != "" && !EmptyValue($row[$fileTypeFld])) {
                        AddHeader("Content-type", $row[$fileTypeFld]);
                    } else {
                        AddHeader("Content-type", ContentType($val));
                    }

                    // Write file name
                    $downloadPdf = !Config("EMBED_PDF") && Config("DOWNLOAD_PDF_FILE");
                    if ($fileNameFld != "" && !EmptyValue($row[$fileNameFld])) {
                        $fileName = $row[$fileNameFld];
                        $pathinfo = pathinfo($fileName);
                        $ext = strtolower($pathinfo["extension"] ?? "");
                        $isPdf = SameText($ext, "pdf");
                        if ($downloadPdf || !$isPdf) { // Skip header if not download PDF
                            AddHeader("Content-Disposition", "attachment; filename=\"" . $fileName . "\"");
                        }
                    } else {
                        $ext = ContentExtension($val);
                        $isPdf = SameText($ext, ".pdf");
                        if ($isPdf && $downloadPdf) { // Add header if download PDF
                            AddHeader("Content-Disposition", "attachment" . ($DownloadFileName ? "; filename=\"" . $DownloadFileName . "\"" : ""));
                        }
                    }

                    // Write file data
                    if (
                        StartsString("PK", $val) &&
                        ContainsString($val, "[Content_Types].xml") &&
                        ContainsString($val, "_rels") &&
                        ContainsString($val, "docProps")
                    ) { // Fix Office 2007 documents
                        if (!EndsString("\0\0\0", $val)) { // Not ends with 3 or 4 \0
                            $val .= "\0\0\0\0";
                        }
                    }

                    // Clear any debug message
                    if (ob_get_length()) {
                        ob_end_clean();
                    }

                    // Write binary data
                    Write($val);

                // Upload to folder
                } else {
                    if ($fld->UploadMultiple) {
                        $files = explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $val);
                    } else {
                        $files = [$val];
                    }
                    $data = [];
                    $ar = [];
                    if ($fld->hasMethod("getUploadPath")) { // Check field level upload path
                        $fld->UploadPath = $fld->getUploadPath();
                    }
                    foreach ($files as $file) {
                        if (!EmptyValue($file)) {
                            if (Config("ENCRYPT_FILE_PATH")) {
                                $ar[$file] = FullUrl(GetApiUrl(Config("API_FILE_ACTION") .
                                    "/" . $this->TableVar . "/" . Encrypt($fld->physicalUploadPath() . $file)));
                            } else {
                                $ar[$file] = FullUrl($fld->hrefPath() . $file);
                            }
                        }
                    }
                    $data[$fld->Param] = $ar;
                    WriteJson($data);
                }
            }
            return true;
        }
        return false;
    }

    // Write audit trail start/end for grid update
    public function writeAuditTrailDummy($typ)
    {
        WriteAuditLog(CurrentUserIdentifier(), $typ, 'users');
    }

    // Write audit trail (add page)
    public function writeAuditTrailOnAdd(&$rs)
    {
        global $Language;
        if (!$this->AuditTrailOnAdd) {
            return;
        }

        // Get key value
        $key = "";
        if ($key != "") {
            $key .= Config("COMPOSITE_KEY_SEPARATOR");
        }
        $key .= $rs['user_id'];

        // Write audit trail
        $usr = CurrentUserIdentifier();
        foreach (array_keys($rs) as $fldname) {
            if (array_key_exists($fldname, $this->Fields) && $this->Fields[$fldname]->DataType != DataType::BLOB) { // Ignore BLOB fields
                if ($this->Fields[$fldname]->HtmlTag == "PASSWORD") { // Password Field
                    $newvalue = $Language->phrase("PasswordMask");
                } elseif ($this->Fields[$fldname]->DataType == DataType::MEMO) { // Memo Field
                    $newvalue = Config("AUDIT_TRAIL_TO_DATABASE") ? $rs[$fldname] : "[MEMO]";
                } elseif ($this->Fields[$fldname]->DataType == DataType::XML) { // XML Field
                    $newvalue = "[XML]";
                } else {
                    $newvalue = $rs[$fldname];
                }
                if ($fldname == Config("LOGIN_PASSWORD_FIELD_NAME")) {
                    $newvalue = $Language->phrase("PasswordMask");
                }
                WriteAuditLog($usr, "A", 'users', $fldname, $key, "", $newvalue);
            }
        }
    }

    // Write audit trail (edit page)
    public function writeAuditTrailOnEdit(&$rsold, &$rsnew)
    {
        global $Language;
        if (!$this->AuditTrailOnEdit) {
            return;
        }

        // Get key value
        $key = "";
        if ($key != "") {
            $key .= Config("COMPOSITE_KEY_SEPARATOR");
        }
        $key .= $rsold['user_id'];

        // Write audit trail
        $usr = CurrentUserIdentifier();
        foreach (array_keys($rsnew) as $fldname) {
            if (array_key_exists($fldname, $this->Fields) && array_key_exists($fldname, $rsold) && $this->Fields[$fldname]->DataType != DataType::BLOB) { // Ignore BLOB fields
                if ($this->Fields[$fldname]->DataType == DataType::DATE) { // DateTime field
                    $modified = (FormatDateTime($rsold[$fldname], 0) != FormatDateTime($rsnew[$fldname], 0));
                } else {
                    $modified = !CompareValue($rsold[$fldname], $rsnew[$fldname]);
                }
                if ($modified) {
                    if ($this->Fields[$fldname]->HtmlTag == "PASSWORD") { // Password Field
                        $oldvalue = $Language->phrase("PasswordMask");
                        $newvalue = $Language->phrase("PasswordMask");
                    } elseif ($this->Fields[$fldname]->DataType == DataType::MEMO) { // Memo field
                        $oldvalue = Config("AUDIT_TRAIL_TO_DATABASE") ? $rsold[$fldname] : "[MEMO]";
                        $newvalue = Config("AUDIT_TRAIL_TO_DATABASE") ? $rsnew[$fldname] : "[MEMO]";
                    } elseif ($this->Fields[$fldname]->DataType == DataType::XML) { // XML field
                        $oldvalue = "[XML]";
                        $newvalue = "[XML]";
                    } else {
                        $oldvalue = $rsold[$fldname];
                        $newvalue = $rsnew[$fldname];
                    }
                    if ($fldname == Config("LOGIN_PASSWORD_FIELD_NAME")) {
                        $oldvalue = $Language->phrase("PasswordMask");
                        $newvalue = $Language->phrase("PasswordMask");
                    }
                    WriteAuditLog($usr, "U", 'users', $fldname, $key, $oldvalue, $newvalue);
                }
            }
        }
    }

    // Write audit trail (delete page)
    public function writeAuditTrailOnDelete(&$rs)
    {
        global $Language;
        if (!$this->AuditTrailOnDelete) {
            return;
        }

        // Get key value
        $key = "";
        if ($key != "") {
            $key .= Config("COMPOSITE_KEY_SEPARATOR");
        }
        $key .= $rs['user_id'];

        // Write audit trail
        $usr = CurrentUserIdentifier();
        foreach (array_keys($rs) as $fldname) {
            if (array_key_exists($fldname, $this->Fields) && $this->Fields[$fldname]->DataType != DataType::BLOB) { // Ignore BLOB fields
                if ($this->Fields[$fldname]->HtmlTag == "PASSWORD") { // Password Field
                    $oldvalue = $Language->phrase("PasswordMask");
                } elseif ($this->Fields[$fldname]->DataType == DataType::MEMO) { // Memo field
                    $oldvalue = Config("AUDIT_TRAIL_TO_DATABASE") ? $rs[$fldname] : "[MEMO]";
                } elseif ($this->Fields[$fldname]->DataType == DataType::XML) { // XML field
                    $oldvalue = "[XML]";
                } else {
                    $oldvalue = $rs[$fldname];
                }
                if ($fldname == Config("LOGIN_PASSWORD_FIELD_NAME")) {
                    $oldvalue = $Language->phrase("PasswordMask");
                }
                WriteAuditLog($usr, "D", 'users', $fldname, $key, $oldvalue);
            }
        }
    }

    // Table level events

    // Table Load event
    public function tableLoad()
    {
        // Enter your code here
    }

    // Recordset Selecting event
    public function recordsetSelecting(&$filter)
    {
        // Enter your code here
    }

    // Recordset Selected event
    public function recordsetSelected($rs)
    {
        //Log("Recordset Selected");
    }

    // Recordset Search Validated event
    public function recordsetSearchValidated()
    {
        // Example:
        //$this->MyField1->AdvancedSearch->SearchValue = "your search criteria"; // Search value
    }

    // Recordset Searching event
    public function recordsetSearching(&$filter)
    {
        // Enter your code here
    }

    // Row_Selecting event
    public function rowSelecting(&$filter)
    {
        // Enter your code here
    }

    // Row Selected event
    public function rowSelected(&$rs)
    {
        //Log("Row Selected");
    }

    // Row Inserting event
    public function rowInserting($rsold, &$rsnew)
    {
        // Enter your code here
        // To cancel, set return value to false
        return true;
    }

    // Row Inserted event
    public function rowInserted($rsold, $rsnew)
    {
        //Log("Row Inserted");
    }

    // Row Updating event
    public function rowUpdating($rsold, &$rsnew)
    {
        // Enter your code here
        // To cancel, set return value to false
        return true;
    }

    // Row Updated event
    public function rowUpdated($rsold, $rsnew)
    {
        //Log("Row Updated");
    }

    // Row Update Conflict event
    public function rowUpdateConflict($rsold, &$rsnew)
    {
        // Enter your code here
        // To ignore conflict, set return value to false
        return true;
    }

    // Grid Inserting event
    public function gridInserting()
    {
        // Enter your code here
        // To reject grid insert, set return value to false
        return true;
    }

    // Grid Inserted event
    public function gridInserted($rsnew)
    {
        //Log("Grid Inserted");
    }

    // Grid Updating event
    public function gridUpdating($rsold)
    {
        // Enter your code here
        // To reject grid update, set return value to false
        return true;
    }

    // Grid Updated event
    public function gridUpdated($rsold, $rsnew)
    {
        //Log("Grid Updated");
    }

    // Row Deleting event
    public function rowDeleting(&$rs)
    {
        // Enter your code here
        // To cancel, set return value to False
        return true;
    }

    // Row Deleted event
    public function rowDeleted($rs)
    {
        //Log("Row Deleted");
    }

    // Email Sending event
    public function emailSending($email, $args)
    {
        //var_dump($email, $args); exit();
        return true;
    }

    // Lookup Selecting event
    public function lookupSelecting($fld, &$filter)
    {
        //var_dump($fld->Name, $fld->Lookup, $filter); // Uncomment to view the filter
        // Enter your code here
    }

    // Row Rendering event
    public function rowRendering()
    {
        // Enter your code here
    }

    // Row Rendered event
    public function rowRendered()
    {
        // To view properties of field class, use:
        //var_dump($this-><FieldName>);
    }

    // User ID Filtering event
    public function userIdFiltering(&$filter)
    {
        // Enter your code here
    }
}
