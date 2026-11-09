# API Routes Directory

This directory contains organized API route definitions for the __BASE__ application template.

## Structure

```
routes/
├── notifications.php         # Notification management routes
├── service.php               # General service routes
├── user-access.php           # UserAccess page routes (create user, save permissions)
├── department-management.php # Department Management page routes (create/update department)
├── position-management.php   # Position Management page routes (create/update category, position)
├── vendor.php          # Vendor + vendor type routes
├── purchase-order.php  # Purchase order CRUD + status routes
├── chart_of_accounts.php # Chart of Accounts CRUD + status routes
├── responsibility_center.php # Responsibility Center CRUD + status routes
└── README.md                 # This documentation
```

## Route Organization

**Note:** These routes are only registered when `app/api.php` is included via the `ApiActionEvent`
(`src/userfn.php` → `Api_Action()`), which is dispatched from `api/index.php` — a *separate* front
controller from the main `index.php` that renders pages. `api/index.php` sets its Slim base path to
`/api`, so every route below is reached externally (and from `fetch()` calls in `app/pages/*.php`)
with an `/api` prefix, e.g. `POST /user-access/users` below is actually `POST /api/user-access/users`.

### notifications.php
- `GET /notifications/recent` - Get recent notifications
- `GET /notifications/count` - Get unread count
- `POST /notifications/mark-read` - Mark notification as read
- `POST /notifications/mark-all-read` - Mark all as read
- `POST /notifications/send` - Send notification
- `GET /notifications/test` - Send test notifications

### service.php
- `GET /Service` - Generate OpenAPI documentation

### user-access.php
- `POST /user-access/users` - Create a user (backs the Add User modal on `app/pages/user-access.php`)
- `PUT /user-access/users/{id}` - Update a user (backs the Edit User modal; no redirect to `UsersEdit`)
- `POST /user-access/permissions` - Save a user level's module permission bitmask (backs the Permission Matrix on `app/pages/user-access.php`)
- `POST /user-access/sync-modules` - Reconcile `uac.user_level_permissions` with PHPMaker's own table/page registry (`$USER_LEVEL_TABLES`), adding zero-permission rows for any module missing from a user level
- `POST /user-access/module-caption` - Rename a module/page row's display caption in the Permissions Management matrix (upserts into `permission_module_captions`; does not touch the PHPMaker-generated `$USER_LEVEL_TABLES` registry)
- `POST /user-access/module-visibility` - Hide or restore a single module/page row in the Permissions Management matrix (`permission_module_captions.is_hidden`); hiding does not delete any `uac.user_level_permissions` rows, and is reversible via the "Show hidden modules" checkbox
- `POST /user-access/user-levels` - Create a user level / role (backs the Create User Level modal; also seeds zero-permission rows for every known module, matching `UserLevelsAdd`'s own behavior)
- `PUT /user-access/user-levels/{id}` - Update a user level / role's name, description, and system (backs the Edit User Level button next to the role dropdown; no redirect to `UserLevelsEdit`)
- `POST /user-access/assignments` - Assign a user level (role) to a user (backs the Assign Access button; no redirect to `UserLevelAssignmentsAdd`)

### department-management.php
- `POST /department-management/departments` - Create a department (backs the Add Department modal on `app/pages/department-management.php`; no redirect to `DepartmentsAdd`)
- `PUT /department-management/departments/{id}` - Update a department (backs the Edit Department modal; no redirect to `DepartmentsEdit`; rejects parent choices that would create a hierarchy cycle)

### position-management.php
- `POST /position-management/categories` - Create a position category (backs the Add Category modal; no redirect to `PositionCategoriesAdd`)
- `PUT /position-management/categories/{id}` - Update a position category (no redirect to `PositionCategoriesEdit`)
- `POST /position-management/positions` - Create a position (backs the Add Position modal; no redirect to `PositionsAdd`)
- `PUT /position-management/positions/{id}` - Update a position (no redirect to `PositionsEdit`)

### vendor.php (`VendorService`, table `fims.vendors` / `fims.vendor_types`)
- `GET /vendors/types` - Active vendor types (dropdown)
- `GET /vendors/all` - All vendors, no pagination (dropdown). Optional: `search`, `type`, `active=1` (hides non-ACTIVE)
- `GET /vendors/list` - Paginated; also returns `summary` {active, inactive} for every row matching the filters. Optional: `page`, `limit`, `search`, `type`, `active`, `status` (ACTIVE / INACTIVE / BLACKLISTED)
- `GET /vendors/read/{id}` - One vendor
- `POST /vendors/add` - Create (`vendor_code` and `vendor_name` required, `vendor_code` unique)
- `PUT /vendors/update/{id}` - Update

### purchase-order.php (`PurchaseOrderService`, tables `fims.purchase_orders` / `fims.purchase_order_items`)
- `GET /purchase-order/all` - All POs, no pagination. Optional filters: `status`, `fiscal_yr`, `expense_class`, `vendor_id`, `search`
- `GET /purchase-order/list` - Paginated (`page`, `limit` + same filters); also returns `summary` (totals / counts for every row matching the filters)
- `GET /purchase-order/fiscal-years` - Fiscal years for dropdowns
- `GET /purchase-order/po-number?po_date=YYYY-MM-DD` - Next free `PO-YYYY-MM-NNNN`
- `GET /purchase-order/read/{id}` - One PO (numeric id or po_number) with `items[]` and `allowed_transitions`
- `POST /purchase-order/add` - Create PO + items; `status` DRAFT (default) or SUBMITTED
- `PUT /purchase-order/update/{id}` - Replace fields + items; DRAFT / REJECTED only
- `POST /purchase-order/{id}/status` - `{status, notes?}`: SUBMITTED / APPROVED / REJECTED (notes required) / CANCELLED
- `DELETE /purchase-order/delete/{id}` - DRAFT or CANCELLED only; items cascade

### chart_of_accounts.php (`ChartOfAccountsService`, table `fims.chart_of_accounts`)
- `GET /chart-of-accounts/all` - All accounts, no pagination (dropdown / parent-account picker). Optional: `search`, `account_type`, `region_code`, `project_code`, `status`
- `GET /chart-of-accounts/regions` - `fims.region` lookup list (Region filter/dropdown)
- `GET /chart-of-accounts/list` - Paginated (`page`, `limit` + same filters as `/all`)
- `GET /chart-of-accounts/read/{id}` - One account (by `account_id`), joined with region/project/parent names
- `POST /chart-of-accounts/add` - Create (`account_code` + `account_title` required, `account_code` unique); `account_id` is server-generated (`ACC00001`, …)
- `PUT /chart-of-accounts/update/{id}` - Update
- `PATCH /chart-of-accounts/{id}/toggle-status` - Flip `status` between `Active` / `Inactive`
- `DELETE /chart-of-accounts/delete/{id}` - Blocked if the account has child accounts or is referenced by `fims.purchase_order_items` (deactivate instead)

### responsibility_center.php (`ResponsibilityCenterService`, table `fims.responsibility_code`)
- `GET /responsibility-center/all` - All RCs, no pagination (dropdown / parent-RC picker). Optional: `search`, `office_type`, `status`
- `GET /responsibility-center/list` - Paginated (`page`, `limit` + same filters as `/all`)
- `GET /responsibility-center/read/{id}` - One RC (by `rc_id`), joined with parent RC code/name
- `POST /responsibility-center/add` - Create (`rc_code` + `rc_name` required, `rc_code` unique); `rc_id` is server-generated (`RC-000`, …)
- `PUT /responsibility-center/update/{id}` - Update
- `PATCH /responsibility-center/{id}/toggle-status` - Flip `status` between `Active` / `Inactive`
- `DELETE /responsibility-center/delete/{id}` - Blocked if the RC has child RCs under it

Both files use `->add($accessMiddleware)->add($jwtMiddleware)`. `$accessMiddleware` is currently a
pass-through stub, so these routes are authenticated but not permission-gated.

## Template Usage

When duplicating this template:
1. Replace `ITBS_STANDARD_APP` with your project namespace
2. Replace `__BASE__` with your project code (e.g., UAC, DMS)
3. Update system references and documentation accordingly

## Adding New Routes

To add new route files:
1. Create new PHP file in this directory
2. Follow the naming convention: `feature-name.php`
3. Include the file in `/app/api.php`
4. Update this README with new endpoints

## Middleware Usage

- Add `->add($jwtMiddleware)` for authentication
- Add `->add($accessMiddleware)` for permission checks
- Chain middleware as needed: `->add($accessMiddleware)->add($jwtMiddleware)` 