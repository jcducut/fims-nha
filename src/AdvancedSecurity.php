<?php

namespace PHPMaker2024\FIMS;

/**
 * Advanced Security class
 */
class AdvancedSecurity
{
    // User level contants
    public const ANONYMOUS_USER_LEVEL_ID = -2;
    public const ADMIN_USER_LEVEL_ID = -1;
    public const DEFAULT_USER_LEVEL_ID = 0;

    // User ID constant
    public const ADMIN_USER_ID = -1;

    // For all users
    public array $UserLevel = []; // All User Levels
    public array $UserLevelPriv = []; // All User Level permissions

    // Current user
    public array $UserLevelID = []; // User Level ID array
    public array $UserID = []; // User ID array
    public array $ParentUserID = []; // Parent User ID array
    public int $CurrentUserLevel = 0; // Permissions
    public $CurrentUserLevelID = self::ANONYMOUS_USER_LEVEL_ID; // User Level (Anonymous by default)
    public $CurrentUserID = null;
    public $CurrentUserPrimaryKey = null;
    protected bool $AnoymousUserLevelChecked = false; // Dynamic User Level security
    private bool $isLoggedIn = false;
    private bool $isSysAdmin = false;
    private string $userName = "";

    // Constructor
    public function __construct()
    {
        global $Security;
        $Security = $this;
        // Init User Level
        if ($this->isLoggedIn()) {
            $this->CurrentUserLevelID = $this->sessionUserLevelID();
            $this->setUserLevelID($this->CurrentUserLevelID);
        } else { // Anonymous user
            $this->CurrentUserLevelID = self::ANONYMOUS_USER_LEVEL_ID;
            $this->UserLevelID[] = $this->CurrentUserLevelID;
        }
        $_SESSION[SESSION_USER_LEVEL_LIST] = $this->userLevelList();

        // Load user ID, Parent User ID and primary key
        $this->setCurrentUserID($this->sessionUserID());
        $this->setParentUserID($this->sessionParentUserID());
        $this->setCurrentUserPrimaryKey($this->sessionUserPrimaryKey());

        // Load user level
        $this->loadUserLevel();
    }

    /**
     * User ID
     */

    // Get session User ID
    protected function sessionUserID()
    {
        return isset($_SESSION[SESSION_USER_ID]) ? strval(Session(SESSION_USER_ID)) : $this->CurrentUserID;
    }

    // Set session User ID
    protected function setSessionUserID($v)
    {
        $this->CurrentUserID = trim(strval($v));
        $_SESSION[SESSION_USER_ID] = $this->CurrentUserID;
    }

    // Current User ID
    public function currentUserID()
    {
        return $this->CurrentUserID;
    }

    // Set current User ID
    public function setCurrentUserID($v)
    {
        $this->CurrentUserID = trim(strval($v));
    }

    /**
     * Parent User ID
     */

    // Get session Parent User ID
    protected function sessionParentUserID()
    {
        return isset($_SESSION[SESSION_PARENT_USER_ID]) ? strval(Session(SESSION_PARENT_USER_ID)) : $this->getParentUserID();
    }

    // Set session Parent User ID
    protected function setSessionParentUserID($v)
    {
        $this->setParentUserID($v);
        $_SESSION[SESSION_PARENT_USER_ID] = $this->getParentUserID();
    }

    // Set Parent User ID to array
    public function setParentUserID($v)
    {
        $ids = is_array($v) ? $v : explode(Config("MULTIPLE_OPTION_SEPARATOR"), strval($v));
        $this->ParentUserID = [];
        foreach ($ids as $id) {
            $this->addParentUserID($id);
        }
    }

    // Get Parent User ID
    public function getParentUserID()
    {
        return implode(Config("MULTIPLE_OPTION_SEPARATOR"), $this->ParentUserID);
    }

    // Check if Parent User ID in array
    public function hasParentUserID($v)
    {
        $ids = is_array($v) ? $v : explode(Config("MULTIPLE_OPTION_SEPARATOR"), strval($v));
        return array_any($ids, fn($id) => in_array($id, $this->ParentUserID));
    }

    // Current Parent User ID
    public function currentParentUserID()
    {
        return $this->getParentUserID();
    }

    /**
     * User Level ID
     */

    // Get session User Level ID
    protected function sessionUserLevelID()
    {
        return $_SESSION[SESSION_USER_LEVEL_ID] ?? $this->CurrentUserLevelID;
    }

    // Set session User Level ID
    protected function setSessionUserLevelID($v)
    {
        $this->CurrentUserLevelID = $v;
        $_SESSION[SESSION_USER_LEVEL_ID] = $this->CurrentUserLevelID;
        $this->setUserLevelID($v);
    }

    // Current User Level ID
    public function currentUserLevelID()
    {
        return $this->CurrentUserLevelID;
    }

    /**
     * User Level (Permissions)
     */

    // Get session User Level
    protected function sessionUserLevel()
    {
        return isset($_SESSION[SESSION_USER_LEVEL]) ? (int)$_SESSION[SESSION_USER_LEVEL] : $this->CurrentUserLevel;
    }

    // Set session User Level
    protected function setSessionUserLevel($v)
    {
        $this->CurrentUserLevel = $v;
        $_SESSION[SESSION_USER_LEVEL] = $this->CurrentUserLevel;
    }

    // Current User Level value
    public function currentUserLevel()
    {
        return $this->CurrentUserLevel;
    }

    /**
     * User name
     */

    // Get current user name
    public function getCurrentUserName()
    {
        return isset($_SESSION[SESSION_USER_NAME]) ? strval($_SESSION[SESSION_USER_NAME]) : $this->userName;
    }

    // Set current user name
    public function setCurrentUserName($v)
    {
        $this->userName = $v;
        $_SESSION[SESSION_USER_NAME] = $this->userName;
    }

    // Get current user name (alias)
    public function currentUserName()
    {
        return $this->getCurrentUserName();
    }

    /**
     * User primary key
     */

    // Get session user primary key
    protected function sessionUserPrimaryKey()
    {
        return isset($_SESSION[SESSION_USER_PRIMARY_KEY]) ? strval(Session(SESSION_USER_PRIMARY_KEY)) : $this->CurrentUserPrimaryKey;
    }

    // Set session user primary key
    protected function setSessionUserPrimaryKey($v)
    {
        $this->setCurrentUserPrimaryKey($v);
        $_SESSION[SESSION_USER_PRIMARY_KEY] = $this->CurrentUserPrimaryKey;
    }

    // Get current user primary key
    public function currentUserPrimaryKey()
    {
        return $this->CurrentUserPrimaryKey;
    }

    // Set current user primary key
    public function setCurrentUserPrimaryKey($v)
    {
        $this->CurrentUserPrimaryKey = $v;
    }

    /**
     * Other methods
     */

    // Set User Level ID to array
    public function setUserLevelID($v)
    {
        $ids = is_array($v) ? $v : explode(Config("MULTIPLE_OPTION_SEPARATOR"), strval($v));
        $this->UserLevelID = [];
        foreach ($ids as $id) {
            if ((int)$id >= self::ANONYMOUS_USER_LEVEL_ID) {
                $this->UserLevelID[] = (int)$id;
            }
        }
    }

    // Check if User Level ID in array
    public function hasUserLevelID($v)
    {
        $ids = is_array($v) ? $v : explode(Config("MULTIPLE_OPTION_SEPARATOR"), strval($v));
        return array_any($ids, fn($id) => in_array((int)$id, $this->UserLevelID));
    }

    // Get JWT Token
    public function createJwt($expiry = 0, $permission = 0)
    {
        return CreateJwt([
            "username" => $this->currentUserName(),
            "userid" => $this->currentUserID(),
            "parentuserid" => $this->currentParentUserID(),
            "userlevel" => $this->currentUserLevelID(),
            "userprimarykey" => $this->currentUserPrimaryKey(),
            "permission" => $permission
        ], $expiry);
    }

    // Can add
    public function canAdd()
    {
        return ($this->CurrentUserLevel & Allow::ADD->value) == Allow::ADD->value;
    }

    // Set can add
    public function setCanAdd($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::ADD->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::ADD->value);
        }
    }

    // Can delete
    public function canDelete()
    {
        return ($this->CurrentUserLevel & Allow::DELETE->value) == Allow::DELETE->value;
    }

    // Set can delete
    public function setCanDelete($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::DELETE->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::DELETE->value);
        }
    }

    // Can edit
    public function canEdit()
    {
        return ($this->CurrentUserLevel & Allow::EDIT->value) == Allow::EDIT->value;
    }

    // Set can edit
    public function setCanEdit($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::EDIT->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::EDIT->value);
        }
    }

    // Can view
    public function canView()
    {
        return ($this->CurrentUserLevel & Allow::VIEW->value) == Allow::VIEW->value;
    }

    // Set can view
    public function setCanView($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::VIEW->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::VIEW->value);
        }
    }

    // Can list
    public function canList()
    {
        return ($this->CurrentUserLevel & Allow::LIST->value) == Allow::LIST->value;
    }

    // Set can list
    public function setCanList($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::LIST->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::LIST->value);
        }
    }

    // Can search
    public function canSearch()
    {
        return ($this->CurrentUserLevel & Allow::SEARCH->value) == Allow::SEARCH->value;
    }

    // Set can search
    public function setCanSearch($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::SEARCH->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::SEARCH->value);
        }
    }

    // Can admin
    public function canAdmin()
    {
        return ($this->CurrentUserLevel & Allow::ADMIN->value) == Allow::ADMIN->value;
    }

    // Set can admin
    public function setCanAdmin($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::ADMIN->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::ADMIN->value);
        }
    }

    // Can import
    public function canImport()
    {
        return ($this->CurrentUserLevel & Allow::IMPORT->value) == Allow::IMPORT->value;
    }

    // Set can import
    public function setCanImport($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::IMPORT->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::IMPORT->value);
        }
    }

    // Can lookup
    public function canLookup()
    {
        return ($this->CurrentUserLevel & Allow::LOOKUP->value) == Allow::LOOKUP->value;
    }

    // Set can lookup
    public function setCanLookup($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::LOOKUP->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::LOOKUP->value);
        }
    }

    // Can push
    public function canPush()
    {
        return ($this->CurrentUserLevel & Allow::PUSH->value) == Allow::PUSH->value;
    }

    // Set can push
    public function setCanPush($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::PUSH->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::PUSH->value);
        }
    }

    // Can export
    public function canExport()
    {
        return ($this->CurrentUserLevel & Allow::EXPORT->value) == Allow::EXPORT->value;
    }

    // Set can push
    public function setCanExport($b)
    {
        if ($b) {
            $this->CurrentUserLevel |= Allow::EXPORT->value;
        } else {
            $this->CurrentUserLevel &= ~(Allow::EXPORT->value);
        }
    }

    // Last URL
    public function lastUrl()
    {
        return ReadCookie("LastUrl");
    }

    // Save last URL
    public function saveLastUrl()
    {
        $s = CurrentUrl();
        $q = ServerVar("QUERY_STRING");
        if ($q != "") {
            $s .= "?" . $q;
        }
        if ($this->lastUrl() == $s) {
            $s = "";
        }
        if (!preg_match('/[?&]modal=1(&|$)/', $s)) { // Query string does not contain "modal=1"
            WriteCookie("LastUrl", $s);
        }
    }

    // Remove last URL
    public function removeLastUrl()
    {
        RemoveCookie("LastUrl");
    }

    // Auto login
    public function autoLogin()
    {
        $valid = false;
        if (!$valid && $jwt = ReadCookie("AutoLogin")) {
            $data = DecodeJwt($jwt);
            $usr = $data["username"] ?? "";
            $autologin = $data["autologin"] ?? false;
            if (!EmptyValue($usr) && $autologin) {
                $valid = $this->validateUser($usr, $jwt, "cookie");
            }
        }
        if (!$valid && Config("ALLOW_LOGIN_BY_URL") && Get("username") !== null) {
            $usr = RemoveXss(Get("username"));
            $pwd = RemoveXss(Get("password"));
            if ($usr !== false && $pwd !== false) {
                if (!EmptyValue(DecodeJwt($pwd)["username"] ?? "")) { // Password is valid JWT token
                    $valid = $this->validateUser($usr, $pwd, "token");
                } else {
                    $valid = $this->validateUser($usr, $pwd, "querystring");
                }
            }
        }
        if ($valid) {
            WriteAuditLog($usr, $GLOBALS["Language"]->phrase("AuditTrailAutoLogin"), CurrentUserIP());
        }
        return $valid;
    }

    // Login user
    public function loginUser(
        string $userName,
        $userId = null,
        $parentUserId = null,
        $userLevel = AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID,
        $userPrimaryKey = null,
        $permission = 0
    ) {
        if ($userName != "") {
            $this->setCurrentUserName($userName);
            $this->isLoggedIn = true;
            $_SESSION[SESSION_STATUS] = "login";
            if ($userName == Language()->phrase("UserAdministrator")) { // Handle language phrase as well
                $userName = Config("ADMIN_USER_NAME");
            }
            $this->isSysAdmin = $this->validateSysAdmin($userName);
        }
        if ($userId != null) {
            $this->setSessionUserID($userId);
            $this->touchLastLogin($userId);
        }
        if ($parentUserId != null) {
            $this->setSessionParentUserID($parentUserId);
        }
        if ($userLevel >= AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID) {
            $this->setSessionUserLevelID($userLevel);
            $this->setupUserLevel();
        }
        if ($userPrimaryKey != null) {
            $this->setSessionUserPrimaryKey($userPrimaryKey);
        }
        // Set allowed permission
        $this->setAllowedPermissions($permission);
    }

    // Record a successful login on uac.users.last_login. Best-effort: the
    // column was never written anywhere in this project, so every user
    // always showed "Never" in the Users list regardless of real activity.
    // Guarded against the System Administrator sentinel ID (ADMIN_USER_ID =
    // -1, not a real row in users) and anything non-numeric/non-positive.
    private function touchLastLogin($userId)
    {
        if (!is_numeric($userId) || (int)$userId <= 0) {
            return;
        }
        try {
            Execute(
                "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = " .
                QuotedValue((int)$userId, DataType::NUMBER, Config("USER_TABLE_DBID")),
                Config("USER_TABLE_DBID")
            );
        } catch (\Throwable $e) {
            // Never block login over this.
        }
    }

    // Logout user
    public function logoutUser()
    {
        $this->isLoggedIn = false;
        $_SESSION[SESSION_STATUS] = "";
        $this->setCurrentUserName("");
        $this->setSessionUserID(null);
        $this->setSessionParentUserID([]);
        $this->setSessionUserLevelID(self::ANONYMOUS_USER_LEVEL_ID);
        $this->setSessionUserPrimaryKey(null);
        $this->setupUserLevel();
        Container(["app.user" => null, "user.profile" => null]);
    }

    /**
     * Validate user
     *
     * @param string $usr User name
     * @param string $pwd Password
     * @param string $loginType Login type. Possible values:
     *  'windows' - Windows authenticaton
     *  'cookie' - Auto login
     *  'token' - Account activation or Login by URL with token as password
     *  'querystring' - Login by URL
     *  'register' - Login after registration
     * @param string $provider External provider, e.g. "Google"
     * @param string $securityCode Security code from Google Authenticator
     * @return bool
     */
    public function validateUser(&$usr, &$pwd, $loginType = "", $provider = "", $securityCode = "")
    {
        global $Language;
        $valid = false;
        $customValid = false;
        $providerValid = false;
        $userProfile = Container("user.profile");
        $user = null;

        // External provider
        if ($provider != "") {
            $providers = Config("AUTH_CONFIG.providers");
            if (array_key_exists($provider, $providers) && $providers[$provider]["enabled"]) {
                try {
                    $hybridauth = Container("hybridauth");
                    $adapter = $hybridauth->authenticate($provider); // Authenticate with the selected provider
                    $profile = $adapter->getUserProfile(); // Hybridauth\User\Profile
                    $usr = $usr ?: $profile->email;
                    $userProfile->setUserName($usr)
                        ->setProvider($provider)
                        ->assign($profile); // Save profile
                    $providerValid = true;
                } catch (\Throwable $e) {
                    if (Config("DEBUG")) {
                        throw new \Exception($e->getMessage());
                    }
                    return false;
                }
            } else {
                if (Config("DEBUG")) {
                    throw new \Exception("Provider for " . $provider . " not found or not enabled");
                }
                return false;
            }
        }

        // Call User Custom Validate event
        if (Config("USE_CUSTOM_LOGIN")) {
            $customValid = $this->userCustomValidate($usr, $pwd);
        }

        // Handle provider login as custom login
        if ($providerValid) {
            $customValid = true;
        }
        if ($customValid) {
            $userProfile->setUserName($usr); // User name might be changed by userCustomValidate()
            $this->loginUser(...$userProfile->getLoginArguments());
        }

        // Check system admin first
        if (!$valid) {
            $checkUserNameOnly = $customValid || // Custom validated
                Config("OTP_ONLY") && Config("USE_TWO_FACTOR_AUTHENTICATION") && Config("FORCE_TWO_FACTOR_AUTHENTICATION") && !EmptyValue(Config("ADMIN_OTP_ACCOUNT")); // OTP only + 2FA + OTP account enabled
            if (in_array($loginType, ["cookie", "token"])) {
                $userName = DecodeJwt($pwd)["username"] ?? "";
                $checkUserNameOnly = $checkUserNameOnly || !EmptyValue($userName) && $userName == $usr;
            }
            $valid = $this->validateSysAdmin($usr, $pwd, $checkUserNameOnly);

            // Set user name
            if ($valid) {
                $userProfile->setUserName($usr)->loadFromStorage();
            }

            // Check two factor authentication for system admin
            if (
                $valid &&
                Config("USE_TWO_FACTOR_AUTHENTICATION") &&
                Config("FORCE_TWO_FACTOR_AUTHENTICATION") &&
                in_array(strtolower(Config("TWO_FACTOR_AUTHENTICATION_TYPE")), ["email", "sms"]) &&
                !EmptyValue(Config("ADMIN_OTP_ACCOUNT")) &&
                !$userProfile->hasUserSecret(true)
            ) {
                $this->isSysAdmin = true;
                $_SESSION[SESSION_SYS_ADMIN] = 1; // System Administrator
                return $valid;
            }
            if ($valid) {
                $this->isLoggedIn = true;
                $_SESSION[SESSION_STATUS] = "login";
                $this->isSysAdmin = true;
                $_SESSION[SESSION_SYS_ADMIN] = 1; // System Administrator
                $userProfile->setUserName($Language->phrase("UserAdministrator"))
                    ->setUserID(self::ADMIN_USER_ID)
                    ->setUserLevel(self::ADMIN_USER_LEVEL_ID);
                $this->loginUser(...$userProfile->getLoginArguments());
            }
        }

        // Check other users
        if (!$valid) {
            // Find user
            if (Config("REGISTER_ACTIVATE") && !EmptyValue(Config("REGISTER_ACTIVATE_FIELD_NAME"))) {
                $user = FindUserByUserName($usr, [Config("REGISTER_ACTIVATE_PROPERTY_NAME") => Config("REGISTER_ACTIVATE_FIELD_VALUE")]);
            } else {
                $user = FindUserByUserName($usr);
            }
            if ($user) {
                $valid = $customValid;

                // Set user
                $userProfile->setUser($user);

                // Force logout
                if ($userProfile->isForceLogout(session_id())) {
                    if ($loginType == "cookie") {
                        RemoveCookie("AutoLogin"); // Clear auto login cookie
                    }
                    return $valid;
                }

                // Check password
                if (!$valid) {
                    $valid = Config("OTP_ONLY") && Config("USE_TWO_FACTOR_AUTHENTICATION") && Config("FORCE_TWO_FACTOR_AUTHENTICATION"); // OTP only + 2FA enabled
                }
                if (!$valid) {
                    if (in_array($loginType, ["cookie", "token"])) {
                        $userName = DecodeJwt($pwd)["username"] ?? "";
                        $valid = !EmptyValue($userName) && $userName == $usr;
                    } else {
                        $valid = ComparePassword($user->get(Config("LOGIN_PASSWORD_FIELD_NAME")), $pwd);
                    }
                }

                // Check user login session
                if ($valid && !$userProfile->isValidUser(session_id())) {
                    return true; // Return true but do not login
                }
                if ($valid) {
                    // Check two factor authentication
                    if (Config("USE_TWO_FACTOR_AUTHENTICATION")) {
                        // Check API login
                        if (IsApi()) {
                            if (
                                SameText(Config("TWO_FACTOR_AUTHENTICATION_TYPE"), "google") &&
                                (Config("FORCE_TWO_FACTOR_AUTHENTICATION") || $userProfile->hasUserSecret(true))
                            ) { // Verify security code for Google Authenticator
                                if (!$userProfile->verify2FACode($securityCode)) {
                                    return false;
                                }
                            }
                        } elseif (Config("FORCE_TWO_FACTOR_AUTHENTICATION") && !$userProfile->hasUserSecret(true)) { // Non API, go to 2fa page
                            if ($valid && $user) {
                                Container("app.user", $user); // Save the user
                            }
                            return $valid;
                        }
                    }
                    $this->isLoggedIn = true;
                    $this->isSysAdmin = false;
                    $_SESSION[SESSION_STATUS] = "login";
                    $_SESSION[SESSION_SYS_ADMIN] = 0; // Non System Administrator
                    $this->loginUser(...$user->getLoginArguments());

                    // Call User Validated event
                    $valid = $this->userValidated($user->toArray()) !== false; // For backward compatibility

                    // Set up user image
                    if (!EmptyValue(Config("USER_IMAGE_FIELD_NAME"))) {
                        $imageField = Container("usertable")->Fields[Config("USER_IMAGE_FIELD_NAME")];
                        if ($imageField->hasMethod("getUploadPath")) {
                            $imageField->UploadPath = $imageField->getUploadPath();
                        }
                        $image = GetFileImage($imageField, $user->get(Config("USER_IMAGE_FIELD_NAME")), Config("USER_IMAGE_SIZE"), Config("USER_IMAGE_SIZE"), Config("USER_IMAGE_CROP"));
                        $userProfile->set(UserProfile::$IMAGE, base64_encode($image))->saveToStorage(); // Save as base64 encoded
                    }
                }
            } else { // User not found in user table
                if ($customValid) { // Grant default permissions
                    $this->loginUser($usr);
                    $customValid = $this->userValidated($userProfile->toArray()) !== false;
                }
            }
        }
        if ($customValid) {
            return true;
        }
        if (!$valid && !IsPasswordExpired()) {
            $this->isLoggedIn = false;
            $_SESSION[SESSION_STATUS] = ""; // Clear login status
        }
        if ($valid && $user) {
            Container("app.user", $user); // Save the user
        }
        return $valid;
    }

    // Valdiate System Administrator
    private function validateSysAdmin($userName, $password = "", $checkUserNameOnly = true)
    {
        $adminUserName = Config("ENCRYPT_USER_NAME_AND_PASSWORD") ? PhpDecrypt(Config("ADMIN_USER_NAME")) : Config("ADMIN_USER_NAME");
        $adminPassword = Config("ENCRYPT_USER_NAME_AND_PASSWORD") ? PhpDecrypt(Config("ADMIN_PASSWORD")) : Config("ADMIN_PASSWORD");
        if (Config("CASE_SENSITIVE_PASSWORD")) {
            return !$checkUserNameOnly && $adminUserName === $userName && $adminPassword === $password ||
                $checkUserNameOnly && $adminUserName === $userName;
        } else {
            return !$checkUserNameOnly && SameText($adminUserName, $userName) && SameText($adminPassword, $password) ||
                $checkUserNameOnly && SameText($adminUserName, $userName);
        }
    }

    // Get User Level settings from database
    public function setupUserLevel()
    {
        $this->setupUserLevelEx(); // Load all user levels

        // User Level loaded event
        $this->userLevelLoaded();

        // Check permissions
        $this->checkPermissions();

        // Save the User Level to Session variable
        $this->saveUserLevel();
    }

    // Get all User Level settings from database
    public function setupUserLevelEx()
    {
        global $Language, $Page, $USER_LEVELS, $USER_LEVEL_PRIVS, $USER_LEVEL_TABLES;

        // Load user level from user level settings first
        $this->UserLevel = $USER_LEVELS;
        $this->UserLevelPriv = $USER_LEVEL_PRIVS;
        $arTable = $USER_LEVEL_TABLES;

        // Add Anonymous user level
        $conn = Conn(Config("USER_LEVEL_DBID"));
        if (!$this->AnoymousUserLevelChecked) {
            $sql = "SELECT COUNT(*) FROM " . Config("USER_LEVEL_TABLE") . " WHERE " . Config("USER_LEVEL_ID_FIELD") . " = " . self::ANONYMOUS_USER_LEVEL_ID;
            if (ExecuteScalar($sql, $conn) == 0) {
                $sql = "INSERT INTO " . Config("USER_LEVEL_TABLE") .
                    " (" . Config("USER_LEVEL_ID_FIELD") . ", " . Config("USER_LEVEL_NAME_FIELD") . ") VALUES (" . self::ANONYMOUS_USER_LEVEL_ID . ", '" . AdjustSql($Language->phrase("UserAnonymous"), Config("USER_LEVEL_DBID")) . "')";
                $conn->executeStatement($sql);
            }
        }

        // Get the User Level definitions
        $sql = "SELECT " . Config("USER_LEVEL_ID_FIELD") . ", " . Config("USER_LEVEL_NAME_FIELD") . " FROM " . Config("USER_LEVEL_TABLE");
        $this->UserLevel = $conn->fetchAllNumeric($sql);

        // Add Anonymous user privileges
        $conn = Conn(Config("USER_LEVEL_PRIV_DBID"));
        if (!$this->AnoymousUserLevelChecked) {
            $sql = "SELECT COUNT(*) FROM " . Config("USER_LEVEL_PRIV_TABLE") . " WHERE " . Config("USER_LEVEL_PRIV_USER_LEVEL_ID_FIELD") . " = " . self::ANONYMOUS_USER_LEVEL_ID;
            if (ExecuteScalar($sql, $conn) == 0) {
                $wrkUserLevel = $USER_LEVELS;
                $wrkUserLevelPriv = $USER_LEVEL_PRIVS;
                $wrkTable = $USER_LEVEL_TABLES;
                foreach ($wrkTable as $table) {
                    $wrkPriv = 0;
                    foreach ($wrkUserLevelPriv as $userpriv) {
                        if (@$userpriv[0] == @$table[4] . @$table[0] && @$userpriv[1] == self::ANONYMOUS_USER_LEVEL_ID) {
                            $wrkPriv = @$userpriv[2];
                            break;
                        }
                    }
                    $sql = "INSERT INTO " . Config("USER_LEVEL_PRIV_TABLE") .
                        " (" . Config("USER_LEVEL_PRIV_USER_LEVEL_ID_FIELD") . ", " . Config("USER_LEVEL_PRIV_TABLE_NAME_FIELD") . ", " . Config("USER_LEVEL_PRIV_PRIV_FIELD") .
                        ") VALUES (" . self::ANONYMOUS_USER_LEVEL_ID . ", '" . AdjustSql(@$table[4] . @$table[0], Config("USER_LEVEL_PRIV_DBID")) . "', " . $wrkPriv . ")";
                    $conn->executeStatement($sql);
                }
            }
            $this->AnoymousUserLevelChecked = true;
        }

        // Get the User Level privileges
        $userPrivSql = "SELECT " . Config("USER_LEVEL_PRIV_TABLE_NAME_FIELD") . ", " . Config("USER_LEVEL_PRIV_USER_LEVEL_ID_FIELD") . ", " . Config("USER_LEVEL_PRIV_PRIV_FIELD") . " FROM " . Config("USER_LEVEL_PRIV_TABLE");
        if (!IsApi() && !$this->isAdmin() && count($this->UserLevelID) > 0) {
            $userPrivSql .= " WHERE " . Config("USER_LEVEL_PRIV_USER_LEVEL_ID_FIELD") . " IN (" . $this->userLevelList() . ")";
            $_SESSION[SESSION_USER_LEVEL_LIST_LOADED] = $this->userLevelList(); // Save last loaded list
        } else {
            $_SESSION[SESSION_USER_LEVEL_LIST_LOADED] = ""; // Save last loaded list
        }
        $this->UserLevelPriv = $conn->fetchAllNumeric($userPrivSql);

        // Update User Level privileges record if necessary
        $projectID = CurrentProjectID();
        $relatedProjectID = Config("RELATED_PROJECT_ID");
        $reloadUserPriv = 0;

        // Update tables with report maker prefix
        if ($relatedProjectID) {
            $sql = "SELECT COUNT(*) FROM " . Config("USER_LEVEL_PRIV_TABLE") . " WHERE EXISTS(SELECT * FROM " .
                Config("USER_LEVEL_PRIV_TABLE") . " WHERE " . Config("USER_LEVEL_PRIV_TABLE_NAME_FIELD") . " LIKE '" . AdjustSql($relatedProjectID, Config("USER_LEVEL_PRIV_DBID")) . "%')";
            if (ExecuteScalar($sql, $conn) > 0) {
                $ar = array_map(fn($t) => "'" . AdjustSql($relatedProjectID . $t[0], Config("USER_LEVEL_PRIV_DBID")) . "'", $arTable);
                $sql = "UPDATE " . Config("USER_LEVEL_PRIV_TABLE") . " SET " .
                    Config("USER_LEVEL_PRIV_TABLE_NAME_FIELD") . " = " . $conn->getDatabasePlatform()->getConcatExpression("'" . AdjustSql($projectID, Config("USER_LEVEL_PRIV_DBID")) . "'", Config("USER_LEVEL_PRIV_TABLE_NAME_FIELD")) . " WHERE " .
                    Config("USER_LEVEL_PRIV_TABLE_NAME_FIELD") . " IN (" . implode(",", $ar) . ")";
                $reloadUserPriv += $conn->executeStatement($sql);
            }
        }

        // Reload the User Level privileges
        if ($reloadUserPriv) {
            $this->UserLevelPriv = $conn->fetchAllNumeric($userPrivSql);
        }

        // Warn user if user level not setup
        if (count($this->UserLevelPriv) == 0 && $this->isAdmin() && $Page != null && Session(SESSION_USER_LEVEL_MSG) == "") {
            $Page->setFailureMessage($Language->phrase("NoUserLevel"));
            $_SESSION[SESSION_USER_LEVEL_MSG] = "1"; // Show only once
            $Page->terminate("UserLevelsList");
        }
        return true;
    }

    // Update user level permissions
    public function updatePermissions($userLevel, $privs)
    {
        $c = Conn(Config("USER_LEVEL_PRIV_DBID"));
        foreach ($privs as $table => $priv) {
            if (is_numeric($priv)) {
                $sql = "SELECT * FROM " . Config("USER_LEVEL_PRIV_TABLE") . " WHERE " .
                    Config("USER_LEVEL_PRIV_TABLE_NAME_FIELD") . " = '" . AdjustSql($table, Config("USER_LEVEL_PRIV_DBID")) . "' AND " .
                    Config("USER_LEVEL_PRIV_USER_LEVEL_ID_FIELD") . " = " . $userLevel;
                if ($c->fetchAssociative($sql)) {
                    $sql = "UPDATE " . Config("USER_LEVEL_PRIV_TABLE") . " SET " . Config("USER_LEVEL_PRIV_PRIV_FIELD") . " = " . $priv . " WHERE " .
                        Config("USER_LEVEL_PRIV_TABLE_NAME_FIELD") . " = '" . AdjustSql($table, Config("USER_LEVEL_PRIV_DBID")) . "' AND " .
                        Config("USER_LEVEL_PRIV_USER_LEVEL_ID_FIELD") . " = " . $userLevel;
                    $c->executeStatement($sql);
                } else {
                    $sql = "INSERT INTO " . Config("USER_LEVEL_PRIV_TABLE") . " (" . Config("USER_LEVEL_PRIV_TABLE_NAME_FIELD") . ", " . Config("USER_LEVEL_PRIV_USER_LEVEL_ID_FIELD") . ", " . Config("USER_LEVEL_PRIV_PRIV_FIELD") . ") VALUES ('" . AdjustSql($table, Config("USER_LEVEL_PRIV_DBID")) . "', " . $userLevel . ", " . $priv . ")";
                    $c->executeStatement($sql);
                }
            }
        }
    }

    // Check permissions
    protected function checkPermissions()
    {
    }

    // Set allowed permissions
    protected function setAllowedPermissions($permission = 0)
    {
        if ($permission > 0) {
            if (is_array($this->UserLevelPriv)) {
                foreach ($this->UserLevelPriv as &$row) {
                    $priv = &$row[2];
                    if (is_numeric($priv)) {
                        $priv &= $permission;
                    }
                }
            }
        }
    }

    // Add user permission
    protected function addUserPermissionEx($userLevelName, $tableName, $userPermission)
    {
        // Get User Level ID from user name
        $userLevelID = "";
        $permission = GetPrivilege($userPermission);
        if (is_array($this->UserLevel)) {
            foreach ($this->UserLevel as $row) {
                list($levelid, $name) = $row;
                if (SameText($userLevelName, $name)) {
                    $userLevelID = $levelid;
                    break;
                }
            }
        }
        if (is_array($this->UserLevelPriv) && $userLevelID != "") {
            $cnt = count($this->UserLevelPriv);
            for ($i = 0; $i < $cnt; $i++) {
                list($table, $levelid, $priv) = $this->UserLevelPriv[$i];
                if (SameText($table, PROJECT_ID . $tableName) && SameString($levelid, $userLevelID)) {
                    $this->UserLevelPriv[$i][2] = $priv | $permission; // Add permission
                    return;
                }
            }
            // Add new entry
            $this->UserLevelPriv[] = [PROJECT_ID . $tableName, $userLevelID, $permission];
        }
    }

    // Add user permission
    public function addUserPermission($userLevelName, $tableName, $userPermission)
    {
        $arUserLevelName = is_array($userLevelName) ? $userLevelName : [$userLevelName];
        $arTableName = is_array($tableName) ? $tableName : [$tableName];
        foreach ($arUserLevelName as $userLevelName) {
            foreach ($arTableName as $tableName) {
                $this->addUserPermissionEx($userLevelName, $tableName, $userPermission);
            }
        }
    }

    // Delete user permission
    protected function deleteUserPermissionEx($userLevelName, $tableName, $userPermission)
    {
        // Get User Level ID from user name
        $userLevelID = "";
        $permission = GetPrivilege($userPermission);
        if (is_array($this->UserLevel)) {
            foreach ($this->UserLevel as $row) {
                list($levelid, $name) = $row;
                if (SameText($userLevelName, $name)) {
                    $userLevelID = $levelid;
                    break;
                }
            }
        }
        if (is_array($this->UserLevelPriv) && $userLevelID != "") {
            $cnt = count($this->UserLevelPriv);
            for ($i = 0; $i < $cnt; $i++) {
                list($table, $levelid, $priv) = $this->UserLevelPriv[$i];
                if (SameText($table, PROJECT_ID . $tableName) && SameString($levelid, $userLevelID)) {
                    $this->UserLevelPriv[$i][2] = $priv & ~$permission; // Remove permission
                    break;
                }
            }
        }
    }

    // Delete user permission
    public function deleteUserPermission($userLevelName, $tableName, $userPermission)
    {
        $arUserLevelName = is_array($userLevelName) ? $userLevelName : [$userLevelName];
        $arTableName = is_array($tableName) ? $tableName : [$tableName];
        foreach ($arUserLevelName as $userLevelName) {
            foreach ($arTableName as $tableName) {
                $this->deleteUserPermissionEx($userLevelName, $tableName, $userPermission);
            }
        }
    }

    // Load table permissions
    public function loadTablePermissions($tblVar)
    {
        $tblName = GetTableName($tblVar);
        if ($this->isLoggedIn() && method_exists($this, "tablePermissionLoading")) {
            $this->tablePermissionLoading();
        }
        $this->loadCurrentUserLevel(PROJECT_ID . $tblName);
        if ($this->isLoggedIn() && method_exists($this, "tablePermissionLoaded")) {
            $this->tablePermissionLoaded();
        }
        if ($this->isLoggedIn()) {
            if (method_exists($this, "userIDLoading")) {
                $this->userIDLoading();
            }
            if (method_exists($this, "loadUserID")) {
                $this->loadUserID();
            }
            if (method_exists($this, "userIDLoaded")) {
                $this->userIDLoaded();
            }
        }
    }

    // Load current User Level
    public function loadCurrentUserLevel($table)
    {
        // Load again if user level list changed
        if (Session(SESSION_USER_LEVEL_LIST_LOADED) != "" && Session(SESSION_USER_LEVEL_LIST_LOADED) != Session(SESSION_USER_LEVEL_LIST)) {
            $_SESSION[SESSION_USER_LEVEL_PRIVS] = "";
        }
        $this->loadUserLevel();
        $this->setSessionUserLevel($this->currentUserLevelPriv($table));
    }

    // Get current user privilege
    protected function currentUserLevelPriv($tableName)
    {
        if ($this->isLoggedIn()) {
            $priv = 0;
            foreach ($this->UserLevelID as $userLevelID) {
                $priv |= $this->getUserLevelPrivEx($tableName, $userLevelID);
            }
            return $priv;
        } else { // Anonymous
            return $this->getUserLevelPrivEx($tableName, self::ANONYMOUS_USER_LEVEL_ID);
        }
    }

    // Get User Level ID by User Level name
    public function getUserLevelID($userLevelName)
    {
        global $Language;
        if (SameString($userLevelName, "Anonymous")) {
            return self::ANONYMOUS_USER_LEVEL_ID;
        } elseif ($Language && SameString($userLevelName, $Language->phrase("UserAnonymous"))) {
            return self::ANONYMOUS_USER_LEVEL_ID;
        } elseif (SameString($userLevelName, "Administrator")) {
            return self::ADMIN_USER_LEVEL_ID;
        } elseif ($Language && SameString($userLevelName, $Language->phrase("UserAdministrator"))) {
            return self::ADMIN_USER_LEVEL_ID;
        } elseif (SameString($userLevelName, "Default")) {
            return self::DEFAULT_USER_LEVEL_ID;
        } elseif ($Language && SameString($userLevelName, $Language->phrase("UserDefault"))) {
            return self::DEFAULT_USER_LEVEL_ID;
        } elseif ($userLevelName != "") {
            if (is_array($this->UserLevel)) {
                foreach ($this->UserLevel as $row) {
                    list($levelid, $name) = $row;
                    if (SameString($name, $userLevelName)) {
                        return $levelid;
                    }
                }
            }
        }
        return self::ANONYMOUS_USER_LEVEL_ID; // Anonymous
    }

    // Add User Level by name
    public function addUserLevel($userLevelName)
    {
        if (strval($userLevelName) == "") {
            return;
        }
        $userLevelID = $this->getUserLevelID($userLevelName);
        $this->addUserLevelID($userLevelID);
    }

    // Add User Level by ID
    public function addUserLevelID($userLevelID)
    {
        if (!is_numeric($userLevelID)) {
            return;
        }
        if ($userLevelID < self::ADMIN_USER_LEVEL_ID) {
            return;
        }
        if (!in_array($userLevelID, $this->UserLevelID)) {
            $this->UserLevelID[] = $userLevelID;
            $_SESSION[SESSION_USER_LEVEL_LIST] = $this->userLevelList(); // Update session variable
        }
    }

    // Delete User Level by name
    public function deleteUserLevel($userLevelName)
    {
        if (strval($userLevelName) == "") {
            return;
        }
        $userLevelID = $this->getUserLevelID($userLevelName);
        $this->deleteUserLevelID($userLevelID);
    }

    // Delete User Level by ID
    public function deleteUserLevelID($userLevelID)
    {
        if (!is_numeric($userLevelID)) {
            return;
        }
        if ($userLevelID < self::ADMIN_USER_LEVEL_ID) {
            return;
        }
        $cnt = count($this->UserLevelID);
        for ($i = 0; $i < $cnt; $i++) {
            if ($this->UserLevelID[$i] == $userLevelID) {
                unset($this->UserLevelID[$i]);
                $_SESSION[SESSION_USER_LEVEL_LIST] = $this->userLevelList(); // Update session variable
                break;
            }
        }
    }

    // User Level list
    public function userLevelList()
    {
        return implode(", ", $this->UserLevelID);
    }

    // User level ID exists
    public function userLevelIDExists($id)
    {
        if (is_array($this->UserLevel)) {
            foreach ($this->UserLevel as $row) {
                list($levelid, $name) = $row;
                if (SameString($levelid, $id)) {
                    return true;
                }
            }
        }
        return false;
    }

    // User Level name list
    public function userLevelNameList()
    {
        $list = "";
        foreach ($this->UserLevelID as $userLevelID) {
            if ($list != "") {
                $list .= ", ";
            }
            $list .= QuotedValue($this->getUserLevelName($userLevelID), DataType::STRING, Config("USER_LEVEL_DBID"));
        }
        return $list;
    }

    // Get user privilege based on table name and User Level
    public function getUserLevelPrivEx($tableName, $userLevelID)
    {
        $ids = explode(Config("MULTIPLE_OPTION_SEPARATOR"), strval($userLevelID));
        $userPriv = 0;
        foreach ($ids as $id) {
            if ($id == self::ADMIN_USER_LEVEL_ID) { // System Administrator
                return Allow::ALL->value;
            } elseif ((int)$id >= self::DEFAULT_USER_LEVEL_ID || $id == self::ANONYMOUS_USER_LEVEL_ID) {
                if (is_array($this->UserLevelPriv)) {
                    foreach ($this->UserLevelPriv as $row) {
                        list($table, $levelid, $priv) = $row;
                        if (SameText($table, $tableName) && SameText($levelid, $id)) {
                            if (is_numeric($priv)) {
                                $userPriv |= (int)$priv;
                            }
                        }
                    }
                }
            }
        }
        return $userPriv;
    }

    // Get current User Level name
    public function currentUserLevelName()
    {
        return $this->getUserLevelName($this->currentUserLevelID());
    }

    // Get User Level name based on User Level
    public function getUserLevelName($userLevelID, $lang = true)
    {
        global $Language;
        if ($userLevelID === self::ANONYMOUS_USER_LEVEL_ID) {
            return $lang ? $Language->phrase("UserAnonymous") : "Anonymous";
        } elseif ($userLevelID === self::ADMIN_USER_LEVEL_ID) {
            return $lang ? $Language->phrase("UserAdministrator") : "Administrator";
        } elseif ($userLevelID === self::DEFAULT_USER_LEVEL_ID) {
            return $lang ? $Language->phrase("UserDefault") : "Default";
        } elseif ($userLevelID > self::DEFAULT_USER_LEVEL_ID) {
            if (is_array($this->UserLevel)) {
                foreach ($this->UserLevel as $row) {
                    list($levelid, $name) = $row;
                    if (SameString($levelid, $userLevelID)) {
                        $userLevelName = "";
                        if ($lang) {
                            $userLevelName = $Language->phrase($name);
                        }
                        return ($userLevelName != "") ? $userLevelName : $name;
                    }
                }
            }
        }
        return "";
    }

    // Display all the User Level settings (for debug only)
    public function showUserLevelInfo()
    {
        Write("<pre>");
        Write(print_r($this->UserLevel, true));
        Write(print_r($this->UserLevelPriv, true));
        Write("</pre>");
        Write("<p>Current User Level ID = " . $this->currentUserLevelID() . "</p>");
        Write("<p>Current User Level ID List = " . $this->userLevelList() . "</p>");
    }

    // Check privilege for List page (for menu items)
    public function allowList($tableName)
    {
        return ($this->currentUserLevelPriv($tableName) & Allow::LIST->value);
    }

    // Check privilege for View page (for Allow-View / Detail-View)
    public function allowView($tableName)
    {
        return ($this->currentUserLevelPriv($tableName) & Allow::VIEW->value);
    }

    // Check privilege for Add page (for Allow-Add / Detail-Add)
    public function allowAdd($tableName)
    {
        return ($this->currentUserLevelPriv($tableName) & Allow::ADD->value);
    }

    // Check privilege for Edit page (for Detail-Edit)
    public function allowEdit($tableName)
    {
        return ($this->currentUserLevelPriv($tableName) & Allow::EDIT->value);
    }

    // Check privilege for Edit page (for Detail-Edit)
    public function allowDelete($tableName)
    {
        return ($this->currentUserLevelPriv($tableName) & Allow::DELETE->value);
    }

    // Check privilege for lookup
    public function allowLookup($tableName)
    {
        return ($this->currentUserLevelPriv($tableName) & Allow::LOOKUP->value);
    }

    // Check privilege for export
    public function allowExport($tableName)
    {
        return ($this->currentUserLevelPriv($tableName) & Allow::EXPORT->value);
    }

    // Check if user password expired
    public function isPasswordExpired()
    {
        return (Session(SESSION_STATUS) == "passwordexpired");
    }

    // Set session password expired
    public function setSessionPasswordExpired()
    {
        $_SESSION[SESSION_STATUS] = "passwordexpired";
    }

    // Set login status
    public function setLoginStatus($status = "")
    {
        $_SESSION[SESSION_STATUS] = $status;
    }

    // Check if user password reset
    public function isPasswordReset()
    {
        return (Session(SESSION_STATUS) == "passwordreset");
    }

    // Check if user is logging in (after changing password)
    public function isLoggingIn()
    {
        return (Session(SESSION_STATUS) == "loggingin");
    }

    // Check if user is logging in (2FA)
    public function isLoggingIn2FA()
    {
        return (Session(SESSION_STATUS) == "loggingin2fa");
    }

    // Check if user is logged in
    public function isLoggedIn()
    {
        return ($this->isLoggedIn || Session(SESSION_STATUS) == "login");
    }

    // Check if user is system administrator
    public function isSysAdmin()
    {
        return ($this->isSysAdmin || Session(SESSION_SYS_ADMIN) === 1);
    }

    // Check if user is administrator
    public function isAdmin()
    {
        $isAdmin = $this->isSysAdmin();
        if (!$isAdmin) {
            $isAdmin = $this->CurrentUserLevelID == self::ADMIN_USER_LEVEL_ID || $this->hasUserLevelID(self::ADMIN_USER_LEVEL_ID) || $this->canAdmin();
        }
        if (!$isAdmin) {
            $isAdmin = $this->CurrentUserID == self::ADMIN_USER_LEVEL_ID || in_array(self::ADMIN_USER_LEVEL_ID, $this->UserID);
        }
        return $isAdmin;
    }

    // Save User Level to Session
    public function saveUserLevel()
    {
        $_SESSION[SESSION_USER_LEVELS] = $this->UserLevel;
        $_SESSION[SESSION_USER_LEVEL_PRIVS] = $this->UserLevelPriv;
    }

    // Load User Level from Session
    public function loadUserLevel()
    {
        if (empty(Session(SESSION_USER_LEVELS)) || empty(Session(SESSION_USER_LEVEL_PRIVS))) {
            $this->setupUserLevel();
            $this->saveUserLevel();
        } else {
            $this->UserLevel = Session(SESSION_USER_LEVELS);
            $this->UserLevelPriv = Session(SESSION_USER_LEVEL_PRIVS);
        }
    }

    // Get user email
    public function currentUserEmail()
    {
        return Config("USER_EMAIL_FIELD_NAME") ? $this->currentUserInfo(Config("USER_EMAIL_FIELD_NAME")) : null;
    }

    // Get current user info
    public function currentUserInfo(string $fldname)
    {
        if (!$this->isSysAdmin() && Config("USER_TABLE") && $this->currentUserName()) {
            return FindUserByUserName($this->currentUserName())?->get($fldname);
        }
        return null;
    }

    // Get User ID by user name
    public function getUserIDByUserName($userName)
    {
        return FindUserByUserName($userName)?->get(Config("USER_ID_FIELD_NAME")) ?? "";
    }

    // Load User ID
    public function loadUserID()
    {
        global $UserTable;
        $this->UserID = [];
        if (strval($this->CurrentUserID) == "") {
            // Handle empty User ID here
        } elseif ($this->CurrentUserID != self::ADMIN_USER_LEVEL_ID) {
            // Get first level
            $this->addUserID($this->CurrentUserID);
            $UserTable = Container("usertable");
            $filter = "";
            if (method_exists($UserTable, "getUserIDFilter")) {
                $filter = $UserTable->getUserIDFilter($this->CurrentUserID);
            }
            $sql = $UserTable->getSql($filter);
            $rows = Conn($UserTable->Dbid)->executeQuery($sql)->fetchAll();
            foreach ($rows as $row) {
                $this->addUserID($row[Config("USER_ID_FIELD_NAME")]);
            }

            // Recurse all levels
            $curUserIDList = $this->userIDList();
            $userIDList = "";
            while ($userIDList != $curUserIDList) {
                $filter = '"reports_to_user_id" IN (' . $curUserIDList . ')';
                $sql = $UserTable->getSql($filter);
                $rows = Conn($UserTable->Dbid)->executeQuery($sql)->fetchAll();
                foreach ($rows as $row) {
                    $this->addUserID($row['user_id']);
                }
                $userIDList = $curUserIDList;
                $curUserIDList = $this->userIDList();
            }
        }
    }

    // Add user name
    public function addUserName($userName)
    {
        $this->addUserID($this->getUserIDByUserName($userName));
    }

    // Add User ID
    public function addUserID($userId)
    {
        if (strval($userId) == "") {
            return;
        }
        if (!is_numeric($userId)) {
            return;
        }
        $userId = trim($userId);
        if (!in_array($userId, $this->UserID)) {
            $this->UserID[] = $userId;
        }
    }

    // Delete user name
    public function deleteUserName($userName)
    {
        $this->deleteUserID($this->getUserIDByUserName($userName));
    }

    // Delete User ID
    public function deleteUserID($userId)
    {
        if (strval($userId) == "") {
            return;
        }
        if (!is_numeric($userId)) {
            return;
        }
        $cnt = count($this->UserID);
        for ($i = 0; $i < $cnt; $i++) {
            if (SameString($this->UserID[$i], $userId)) {
                unset($this->UserID[$i]);
                break;
            }
        }
    }

    // User ID list
    public function userIDList()
    {
        return implode(", ", array_map(fn($userId) => QuotedValue($userId, DataType::NUMBER, Config("USER_TABLE_DBID")), $this->UserID));
    }

    // Add Parent User ID
    public function addParentUserID($userId)
    {
        if (strval($userId) == "" || SameString($userId, $this->CurrentUserID)) {
            return;
        }
        if (!is_numeric($userId)) {
            return;
        }
        $userId = trim($userId);
        if (!in_array($userId, $this->ParentUserID)) {
            $this->ParentUserID[] = $userId;
        }
    }

    // Delete Parent User ID
    public function deleteParentUserID($userId)
    {
        if (strval($userId) == "" || SameString($userId, $this->CurrentUserID)) {
            return;
        }
        if (!is_numeric($userId)) {
            return;
        }
        $cnt = count($this->ParentUserID);
        for ($i = 0; $i < $cnt; $i++) {
            if (SameString($this->ParentUserID[$i], $userId)) {
                unset($this->ParentUserID[$i]);
                break;
            }
        }
    }

    // Parent User ID list
    public function parentUserIDList($userId)
    {
        // Own record
        $res = [];
        if (SameString($userId, $this->CurrentUserID)) {
            foreach ($this->ParentUserID as $userId) {
                $res[] = QuotedValue($userId, DataType::NUMBER, Config("USER_TABLE_DBID"));
            }
        } else {
            // All users except user ID
            $ar = $this->UserID;
            $len = count($ar);
            for ($i = 0; $i < $len; $i++) {
                if (!SameString($ar[$i], $userId)) {
                    $res[] = QuotedValue($ar[$i], DataType::NUMBER, Config("USER_TABLE_DBID"));
                }
            }
        }
        return implode(", ", $res);
    }

    // List of allowed User IDs for this user
    public function isValidUserID($userId)
    {
        return strval($userId) !== "" && in_array(trim($userId), $this->UserID);
    }

    // UserID Loading event
    public function userIdLoading()
    {
        //Log("UserID Loading: " . $this->currentUserID());
    }

    // UserID Loaded event
    public function userIdLoaded()
    {
        //Log("UserID Loaded: " . $this->userIDList());
    }

    // User Level Loaded event
    public function userLevelLoaded()
    {
        //$this->addUserPermission(<UserLevelName>, <TableName>, <UserPermission>);
        //$this->deleteUserPermission(<UserLevelName>, <TableName>, <UserPermission>);
    }

    // Table Permission Loading event
    public function tablePermissionLoading()
    {
        //Log("Table Permission Loading: " . $this->CurrentUserLevelID);
    }

    // Table Permission Loaded event
    public function tablePermissionLoaded()
    {
        //Log("Table Permission Loaded: " . $this->CurrentUserLevel);
    }

    // User Custom Validate event
    public function userCustomValidate(&$usr, &$pwd)
    {
        // Enter your custom code to validate user, return true if valid.
        return false;
    }

    // User Validated event
    public function userValidated($rs)
    {
        // Example:
        //$_SESSION['UserEmail'] = $rs['Email'];
    }

    // User PasswordExpired event
    public function userPasswordExpired($rs)
    {
        //Log("User_PasswordExpired");
    }
}
