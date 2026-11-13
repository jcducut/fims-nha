<?php

namespace PHPMaker2024\FIMS;

use Slim\Views\PhpRenderer;
use Slim\Csrf\Guard;
use Slim\HttpCache\CacheProvider;
use Slim\Flash\Messages;
use Psr\Container\ContainerInterface;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Platforms;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\Events;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Mime\MimeTypes;
use FastRoute\RouteParser\Std;
use Illuminate\Encryption\Encrypter;
use HTMLPurifier_Config;
use HTMLPurifier;

// Connections and entity managers
$definitions = [];
$dbids = array_keys(Config("Databases"));
foreach ($dbids as $dbid) {
    $definitions["connection." . $dbid] = \DI\factory(function (string $dbid) {
        return ConnectDb(Db($dbid));
    })->parameter("dbid", $dbid);
    $definitions["entitymanager." . $dbid] = \DI\factory(function (ContainerInterface $c, string $dbid) {
        $cache = IsDevelopment()
            ? DoctrineProvider::wrap(new ArrayAdapter())
            : DoctrineProvider::wrap(new FilesystemAdapter(directory: Config("DOCTRINE.CACHE_DIR")));
        $config = Setup::createAttributeMetadataConfiguration(
            Config("DOCTRINE.METADATA_DIRS"),
            IsDevelopment(),
            null,
            $cache
        );
        $conn = $c->get("connection." . $dbid);
        return new EntityManager($conn, $config);
    })->parameter("dbid", $dbid);
}

return [
    "app.cache" => \DI\create(CacheProvider::class),
    "app.flash" => fn(ContainerInterface $c) => new Messages(),
    "app.view" => fn(ContainerInterface $c) => new PhpRenderer($GLOBALS["RELATIVE_PATH"] . "views/"),
    "email.view" => fn(ContainerInterface $c) => new PhpRenderer($GLOBALS["RELATIVE_PATH"] . "lang/"),
    "sms.view" => fn(ContainerInterface $c) => new PhpRenderer($GLOBALS["RELATIVE_PATH"] . "lang/"),
    "app.audit" => fn(ContainerInterface $c) => (new Logger("audit"))->pushHandler(new AuditTrailHandler($GLOBALS["RELATIVE_PATH"] . "log/audit.log")), // For audit trail
    "app.logger" => fn(ContainerInterface $c) => (new Logger("log"))->pushHandler(new RotatingFileHandler($GLOBALS["RELATIVE_PATH"] . "log/log.log")),
    "sql.logger" => function (ContainerInterface $c) {
        $loggers = [];
        if (Config("DEBUG")) {
            $loggers[] = $c->get("debug.stack");
        }
        return (count($loggers) > 0) ? new LoggerChain($loggers) : null;
    },
    "app.csrf" => fn(ContainerInterface $c) => new Guard($GLOBALS["ResponseFactory"], Config("CSRF_PREFIX")),
    "html.purifier.config" => fn(ContainerInterface $c) => HTMLPurifier_Config::createDefault(),
    "html.purifier" => fn(ContainerInterface $c) => new HTMLPurifier($c->get("html.purifier.config")),
    "debug.stack" => \DI\create(DebugStack::class),
    "debug.sql.logger" => \DI\create(DebugSqlLogger::class),
    "debug.timer" => \DI\create(Timer::class),
    "app.security" => \DI\create(AdvancedSecurity::class),
    "user.profile" => \DI\create(UserProfile::class),
    "app.session" => \DI\create(HttpSession::class),
    "mime.types" => \DI\create(MimeTypes::class),
    "app.language" => \DI\create(Language::class),
    PermissionMiddleware::class => \DI\create(PermissionMiddleware::class),
    ApiPermissionMiddleware::class => \DI\create(ApiPermissionMiddleware::class),
    JwtMiddleware::class => \DI\create(JwtMiddleware::class),
    Std::class => \DI\create(Std::class),
    Encrypter::class => fn(ContainerInterface $c) => new Encrypter(AesEncryptionKey(base64_decode(Config("AES_ENCRYPTION_KEY"))), Config("AES_ENCRYPTION_CIPHER")),

    // Tables
    "systems" => \DI\create(Systems::class),
    "user_level_assignments" => \DI\create(UserLevelAssignments::class),
    "user_level_permissions" => \DI\create(UserLevelPermissions::class),
    "_user_levels" => \DI\create(UserLevels::class),
    "users" => \DI\create(Users::class),
    "MainDashboard" => \DI\create(MainDashboard::class),
    "departments" => \DI\create(Departments::class),
    "notifications" => \DI\create(Notifications::class),
    "UserManagement" => \DI\create(UserManagement::class),
    "UserAccess" => \DI\create(UserAccess::class),
    "psgc" => \DI\create(Psgc::class),
    "PSGCMap" => \DI\create(PsgcMap::class),
    "FileManager" => \DI\create(FileManager::class),
    "PageEditor" => \DI\create(PageEditor::class),
    "AiAssistant" => \DI\create(AiAssistant::class),
    "position_categories" => \DI\create(PositionCategories::class),
    "positions" => \DI\create(Positions::class),
    "plantilla_items" => \DI\create(PlantillaItems::class),
    "WorkflowManager" => \DI\create(WorkflowManager::class),
    "DocumentTypeEditor" => \DI\create(DocumentTypeEditor::class),
    "DocumentTypeManager" => \DI\create(DocumentTypeManager::class),
    "IDGenerator" => \DI\create(IdGenerator::class),
    "DepartmentManagement" => \DI\create(DepartmentManagement::class),
    "EventPlayground" => \DI\create(EventPlayground::class),
    "department_systems" => \DI\create(DepartmentSystems::class),
    "offices" => \DI\create(Offices::class),
    "user_offices" => \DI\create(UserOffices::class),
    "PositionManagement" => \DI\create(PositionManagement::class),
    "SignatoryManagement" => \DI\create(SignatoryManagement::class),
    "document_asset_types" => \DI\create(DocumentAssetTypes::class),
    "document_header_assets" => \DI\create(DocumentHeaderAssets::class),
    "document_header_slots" => \DI\create(DocumentHeaderSlots::class),
    "jwt_sessions" => \DI\create(JwtSessions::class),
    "project_page" => \DI\create(ProjectPage::class),
    "program_page" => \DI\create(ProgramPage::class),
    "budget_req_page" => \DI\create(BudgetReqPage::class),
    "sub_program_page" => \DI\create(SubProgramPage::class),
    "chart_of_accounts_page" => \DI\create(ChartOfAccountsPage::class),
    "responsibility_center_page" => \DI\create(ResponsibilityCenterPage::class),
    "permission_module_captions" => \DI\create(PermissionModuleCaptions::class),
    "budget_revision_page" => \DI\create(BudgetRevisionPage::class),
    "budget_rev_form" => \DI\create(BudgetRevForm::class),
    "budget_rev_review" => \DI\create(BudgetRevReview::class),
    "memo_req_page" => \DI\create(MemoReqPage::class),
    "IARModule" => \DI\create(IarModule::class),
    "Voucher" => \DI\create(Voucher::class),
    "JournalEntryVoucher" => \DI\create(JournalEntryVoucher::class),
    "ChangePass" => \DI\create(ChangePass::class),
    "CheckDisbursements" => \DI\create(CheckDisbursements::class),
    "budget_prop_page" => \DI\create(BudgetPropPage::class),
    "notification_reads" => \DI\create(NotificationReads::class),
    "saro_registry_page" => \DI\create(SaroRegistryPage::class),
    "supplemental_funds_page" => \DI\create(SupplementalFundsPage::class),
    "trust_funds_page" => \DI\create(TrustFundsPage::class),
    "pricing_page" => \DI\create(PricingPage::class),
    "cash_position_page" => \DI\create(CashPositionPage::class),
    "bank_management_page" => \DI\create(BankManagementPage::class),
    "property_record_page" => \DI\create(PropertyRecordPage::class),
    "vendors_page" => \DI\create(VendorsPage::class),

    // User table
    "usertable" => \DI\get("users"),
] + $definitions;
