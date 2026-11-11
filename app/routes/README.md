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
├── voucher.php         # Disbursement Voucher CRUD + status + RFA lookup routes
├── notifications.php   # Notification hub API (uac.notifications) for the bell
├── chart_of_accounts.php # Chart of Accounts CRUD + status routes
├── responsibility_center.php # Responsibility Center CRUD + status routes
├── budget_allocation.php     # Memo Request (budget allocation) CRUD + lookups
├── budget_category.php       # Budget category CRUD (Infrastructure, Goods and Services, Consultancy)
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

### voucher.php (`DisbursementVoucherService`, table `fims.disbursement_vouchers`)
One generic voucher shape for any claimant/payment type (vendor payment, payroll, reimbursement, etc.) — `claimant_name`/`claimant_address`/`employee_no_tin` are either snapshotted from a selected `vendor_id`, or typed free text.
- `GET /voucher/all` - All DVs, no pagination. Optional filters: `status`, `fiscal_yr`, `expense_class` (MOOE/PS/CAPITAL_OUTLAY/OTHER — classification tag only, same set `fims.purchase_orders.expense_class` uses), `search`
- `GET /voucher/list` - Paginated (`page`, `limit` + same filters); also returns `summary` (totals / counts for every row matching the filters)
- `GET /voucher/fiscal-years` - Fiscal years for dropdowns
- `GET /voucher/dv-number?dv_date=YYYY-MM-DD` - Next free `{YY}-{MM}-{NNNN}` DV number
- `GET /voucher/rfa/all` - RFA picker options: `fims.budget_allocation` rows where `allocation_type = 'RFA'` (that table has no CRUD backend of its own — this is a read-only lookup added for the DV form). Several DVs can be drawn against the same RFA over time (progress payments, matching the paper General Form No. 1's Box B "Payments / This Request / Total Payments" lines) — each option includes `budget_amount` (ceiling), `paid_amount` (sum of `net_amount` already drawn against it by other non-cancelled/rejected vouchers), and `remaining_balance`. Optional: `fiscal_yr`, `search`, `exclude_dv_id` (editing — excludes this voucher's own amount from `paid_amount`)
- `GET /voucher/rfa/read/{allocation_id}` - One RFA + its `budget_amount`/`paid_amount`/`remaining_balance`. Optional: `exclude_dv_id` when editing an existing DV so its own prior amount isn't double-counted
- `GET /voucher/read/{id}` - One DV with `allowed_transitions`, its deduction lines embedded as `deductions[]`, its ATC withholding lines as `atc_lines[]`, and its Journal Entry Voucher as `jev` (header + `lines[]`, each line with `sl_code`/`sl_name`/`sl_detail_id`/`sl_detail_name`; `jev` is `null` if Accounting hasn't prepared one yet)
- `POST /voucher/add` - Create (`rfa_allocation_id`, `claimant_name` or `vendor_id`, `particulars`, `gross_amount` required). Optional `deductions: [{deduction_type_id, rate?, gross_amount?, deduction_amount?}]` (non-tax deductions e.g. retention — `rate` applied to `gross_amount` when given, otherwise `deduction_amount` is used directly); `net_amount = gross_amount - sum(deduction_amount)` at this point (withholding tax isn't known yet — see the Journal Entry route below). Also optionally accepts `atc_lines`/`lines`, same shape as `POST /voucher/{id}/journal-entry` below — Originating can pre-fill the ATC Code / Accounting Entries sections here if already known, though it's normally Accounting's job during FOR_ACCOUNTING; unlike that route, 0 lines is valid (nothing filled in yet, not an error), but if any are given the usual ≥2-lines/balanced/posting_allowed checks still apply, and net_amount/balance_amount are recomputed the same way. `status` DRAFT (default) or FOR_FMD_REVIEW
- `PUT /voucher/update/{id}` - Replace fields + deductions + optional `atc_lines`/`lines` (full replace, same shape as `POST /voucher/add`); DRAFT / REJECTED only
- `POST /voucher/{id}/status` - `{status, notes?}` — moves the voucher along the four-office pipeline: `DRAFT -> FOR_FMD_REVIEW -> FOR_ACCOUNTING -> FOR_TREASURY -> RELEASED` (Originating -> FMD checks the obligation -> Accounting processes -> Treasury releases payment), or `REJECTED` (notes required) / `CANCELLED` from any non-terminal stage. `403` if the caller's `uac.users.rc_id` (Responsibility Center) doesn't match the office that owns the target stage (sysadmin bypasses) — see `DisbursementVoucherService::authorizeTransition()`
- `GET /voucher/journal-entries` - Paginated JEV register, each row joined with its DV (`dv_no`, `claimant_name`, `dv_status`). Read-only — a JEV is only created/edited from within its DV's Accounting stage. Optional: `page`, `limit`, `fiscal_yr`, `search` (JEV no / DV no / claimant)
- `GET /voucher/atc-codes` - Active BIR Alphanumeric Tax Codes (`fims.atc_codes`) for the ATC line picker
- `GET /voucher/deduction-types` - Active deduction types (`fims.deduction_types`) for the Deductions section's type dropdown
- `GET /voucher/sl-codes?search=` - Subsidiary Ledger code picker (`fims.sl_masters`), for the Accounting Entries grid's SL Code autocomplete
- `GET /voucher/sl-codes/{sl_code}/details` - SL Detail options (`fims.sl_details`) scoped to one SL Code
- `POST /voucher/{id}/journal-entry` - `{atc_lines: [{atc_code, rate, tax_base, chargeability?}], lines: [{account_code, debit, credit, description?, sl_code?, sl_detail_id?}]}` — Accounting saves/edits the DV's Journal Entry Voucher *and* its BIR withholding tax classification together (full replace of both, same shape as PO items). FOR_ACCOUNTING status + Accounting Department (RC `012`) only (sysadmin bypasses). `atc_lines` is optional (0 or more — a DV can withhold under several ATCs at once); each line's `withholding_tax` is server-computed (`tax_base * rate / 100`), never trusted from the client. `lines` must be ≥2, each exactly one of debit/credit, account codes must be `posting_allowed`; `sl_code`/`sl_detail_id` are optional and, if given, must exist and `sl_detail_id` must belong to `sl_code`. Total debits must equal total credits. Recomputes the DV's `net_amount`/`balance_amount` (`gross_amount - total deductions - total withholding_tax`). A balanced JEV is a prerequisite `POST /voucher/{id}/status` checks before allowing `FOR_ACCOUNTING -> FOR_TREASURY`
- `DELETE /voucher/delete/{id}` - DRAFT or CANCELLED only

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

### budget_allocation.php (`BudgetAllocationService`, table `fims.budget_allocation`; page `app/pages/memo-req-page.php`)
Shaped like `crud.php` (`all` / `list` / `read` / `add` / `PATCH update` / `delete`). Soft-deleted via `deleted_at`.
- `GET /budget_allocation/all` - All memo requests, no pagination (Parent picker). Optional: `search` (matches `request_referrence_no`, allocation / PR / PO numbers, description, vendor), `fiscal_year`, `status_id`, `status` (a status **name**, case-insensitive, among the memo-request statuses — e.g. `Approved`; a name that matches no status returns no rows), `allocation_type`, `exclude_id`. The form's Parent CBA dropdown asks for `allocation_type=CBA&status=Approved`. Each row also carries its parent's `parent_request_referrence_no` **Office scope (both list endpoints):** decided by the API from the logged-in user, the same rule as the budget requests — a user with an office sees that office's requests and those of the offices under it (a client-sent `rc_id` is ignored); a superadmin / a user with no office sees every office and may narrow with `rc_id`; the Financial Management Department (FMD) sees every office only when the page sends `central=1` (the parameter alone grants nothing). A request with no `requesting_rc_id` is invisible to office-scoped users.
- `GET /budget_allocation/list` - Paginated (`page`, `limit` + same filters); also returns `summary` {total, total_pr_amount, total_po_amount} for every row matching the filters. Rows exclude `line_transaction`
- `GET /budget_allocation/read/{id}` - One memo request (all columns, `line_transaction` decoded), plus `file_attachments[]` (its Supporting Documents: `file_meta` {`document_name`, `link`, `view_url`}), joined with status / vendor / requesting RC (`requesting_rc_code`, `requesting_rc_name`, `requesting_rc_office_type`) / district / program / project / parent names
- `POST /budget_allocation/add` - Create (`fiscal_year` is the only required field — there is no `memo_ref_no` any more: the column was dropped in `03 - alter_table/20`, and a client that still sends it is simply ignored; `request_referrence_no` is generated by the database — `RR-001`, `RR-002`, … in the order requests are created, never reused — so it is **not accepted** in the body (ignored on create and update); it is returned by the list and read routes, and by add for the row just created). Status defaults to `Draft`; `user_id` is the JWT user, never read from the body. So is `requesting_rc_id`: it is that user's own Responsibility Center (`uac.users.rc_id`, a `fims.responsibility_code.rc_id`; left empty for a user without one), set on create only. Returns `allocation_id` **Program / Sub-Program / Project** can be PICKED or TYPED: send the code of an existing one (`program_code`, `sub_program_code`, `project_code`) or the name of a new one (`program_name`, `sub_program_name`, `project_name`; a code, when sent, wins). A new one is created with a generated code (program `100`, sub-program `100-001`, project `<fiscal year>-<rc>-…`) — an existing one with the same name under the same parent is reused instead, case-insensitively — in the SAME transaction as the memo request, so a failed save leaves nothing behind. A new sub-program or project needs a program (picked or typed). The response lists the codes it created in `created`.
- `PATCH /budget_allocation/update/{id}` - Partial update: only the fields sent change (`''` / `null` clears an optional field). `user_id`, `requesting_rc_id`, `created_at`, `deleted_at` are never writable The typed names (`program_name`, `sub_program_name`, `project_name`) work here too (same rules as add); a different program than the record had clears its old sub-program and project unless the body supplies them, and a body with only typed names is a valid update.
- `DELETE /budget_allocation/delete/{id}` - Soft delete; blocked while other memo requests still have it as `parent_id`
- `GET /budget_allocation/departments` - Active `uac.departments` (District picker)
- `GET /budget_allocation/statuses` - `fims.settings_status` rows with `status_for = 'budget_allocation'`
- `PATCH /budget_allocation/process/{id}` - **FMD review**, one step at a time: `For Preview` (FMD Section Chief) -> `For Division Manager` (FMD Division Manager) -> `For Department Manager` (FMD Department Manager) -> `Approved`. Body `{ "decision": "approve" | "return" }`. Only a user of the Financial Management Department (rc_code `35-074-0000000-032`, read with `getUserRcId` — fails closed) who holds the user level of the current step (`uac.user_levels.name`, matched by name) may call it: anyone else gets **403**; a bad decision, an unknown id, a request that is not in a review status (the three steps plus the two `Returned to ...` ones), a missing status or a request somebody else changed meanwhile gets **400** and nothing changes. `approve` -> the next status of the chain; the last approval gives `Approved` **and** the allocation number in the same transaction (kept if it already has one). `return` goes ONE step back: the Department Manager's -> `Returned to Division Manager` (acted on by the Division Manager), the Division Manager's -> `Returned to Section Chief` (acted on by the Section Chief), who can endorse it again; the Section Chief's -> `Returned` (back to the requesting office). No number. Reply `{ success, message, status_name, allocation_number }`. Needs the statuses seeded by `sample_data/06`, `07` and `seed_data/04`, `05` (the API names the missing one). `GET /budget_allocation/read/{id}` adds `data.review` `{ in_review, status, actor, next_status, is_final, can_decide }` for the caller.
  - **Allocation number templates** (the ones the memo form shows, by allocation type and document type; y = year, yy = its last two digits, q = quarter 1-4, m = month in the quarter 1-3, of the day of approval, **Philippine time**): `CBA` -> `CBA <y>-<q><m>-86-<series>` (e.g. `CBA 2026-42-86-01`); `RFA` -> `<yy><q><m>-<code>-<series>` with code `57` for document type `COE`, `87` for `CO` (e.g. `2642-87-01`); `LAA` -> `LAA <y>-<q><m>-<series>`; any other type -> `<TYPE> <y>-<q><m>-<series>`. `<series>` has at least two digits and counts within one prefix (everything before it), so it restarts at `01` each month per type and never repeats; numbers of deleted requests count. Two approvals at once are numbered one after the other (a transaction-level advisory lock).

Validation (400 with a message): fiscal year / vendor / status / department / district / program / sub-program / project must exist; the sub-program must belong to the program and the project to the program (and sub-program, when the project has one); `parent_id` can't be the row itself or one of its descendants; amounts are 0 – 9,999,999,999,999.99; `line_transaction` must be a JSON array/object; `multiple_node_id` must be a UUID.

### budget_category.php (`BudgetCategoryService`, table `fims.budget_category`)
Shaped like `crud.php` (`all` / `list` / `read` / `add` / `PATCH update` / `delete`). Soft-deleted via `deleted_at`. Seeded with Infrastructure, Goods and Services, Consultancy (ids 1–3).
- `GET /budget_category/all` - All categories, no pagination (dropdown). Optional: `search` (case-insensitive, partial match on `name`)
- `GET /budget_category/list` - Paginated (`page`, `limit`, max 200; same `search`)
- `GET /budget_category/read/{id}` - One category (404 when missing or deleted)
- `POST /budget_category/add` - Create (`name` required, ≤ 50 chars, unique ignoring case among non-deleted rows). Returns `budget_category_id`
- `PATCH /budget_category/update/{id}` - Rename (`name` is the only writable field; same rules). Stamps `updated_at`
- `DELETE /budget_category/delete/{id}` - Soft delete; the name can be reused afterwards

### budget_req.php (`BudgetReqService`, table `fims.budget_req`; page `app/pages/budget-req-page.php`)
Only the list routes' RC handling is described here. `GET /budget_req/all` (no paging) and `GET /budget_req/list` (`page`, `limit`) return one header per fiscal year + RC, each with `programs[]` > `projects[]` > `line_items[]`, plus its RC (`rc_code`, `rc_name`, `rc_office_type`) and that RC's parent (`rc_parent_id`, `rc_parent_code`, `rc_parent_name`), its total proposed amount (`li_total`) and `submitted_at` (stamped with `NOW()` whenever the request's content is saved — `POST /budget_req/add` creating it or adding a Program to it, `PUT /budget_req/program/update/{id}` editing a Program — and again by `PUT /budget_req/submit-for-review/{id}`, so it is the date of the latest save / submission; `null` only for requests that existed before the column did). Filters: `fiscal_yr`, `program_code`, and:
- `rc_code_from` - only requests of RCs whose `rc_code` sorts at or after this value. The Financial Management Department's list sends `35-074-0000001-000` (the Regional and District offices; the central departments come before it).
- `central=1` - widens the list to every RC, but only for the central review RC (the Financial Management Department, recognised by its rc_code `GlobalService::CENTRAL_REVIEW_RC_CODE` = `35-074-0000000-032` — not by its rc_id, whose format differs between datasets), whose own RC has no children. Any other user keeps the usual scope (their RC and its descendants); the flag alone grants nothing. `GlobalService::resolveBudgetReqRcFilter()` decides this on the server.

- `PUT /budget_req/endorse/{id}` - Budget Processing "Endorse". Body `{ items: [{ budget_line_item_id, type_of_fund_id, component_id, suggested_amount?, program_source_code?, program_uses? }, ...] }`: `type_of_fund_id` and `component_id` are required on every item; `suggested_amount` is the modal's Suggested Amount column (blank = 0.00; the Amount column beside it is the line's read-only `proposed_amount`); `program_source_code` is optional and is a `fims.program.program_code` (a program source IS a program; key absent = unchanged, blank = cleared, unknown program = error), saved to `budget_line_items.program_source_code`; `program_uses` is optional too and is a `fims.sub_program.sub_program_code` (a program use IS a sub-program; same rules: key absent = unchanged, blank = cleared, unknown sub-program = error), saved to `budget_line_items.program_uses`. Moves the request to `Endorsed` in one transaction.
- `PUT /budget_req/return/{id}` - Budget Processing / Budget Preview "Return": moves the request to `Returned` and saves the reason in `budget_req.remarks`. Body `{ remarks }` is **required** (non-blank, at most 2000 characters, `BudgetReqService::REMARKS_MAX_LENGTH`); both modals ask for it in a pop-up before sending. A Cancelled, already-Returned or Approved request cannot be returned. The list / read responses carry `remarks` (null until a request has been returned)

The Financial Management Department's users (current user -> `uac.users.rc_id` -> `fims.responsibility_code`, whose `rc_code` is `35-074-0000000-032`) get a different list on the Budget Requirements page, one row per request over every Regional / District office, paged through `GET /budget_req/list` with `central=1` and `rc_code_from`: Submission Ref No., Region / Originating Unit, Date Submitted, Amount, Status (+ the row actions, which act on the whole request). Everyone else keeps the flat one-row-per-Program list.

### fmd_budget_req.php (`BudgetReqService`, table `fims.budget_req`; page `app/pages/budget-prop-page.php`, "Budget Proposal")
- `GET /fmd/budget_req/list` - the Financial Management Department's list: every Regional / District office's request, paged, in the same shape as `GET /budget_req/list` (header + `programs[]` > `projects[]` > `line_items[]`, its RC and parent RC, `li_total` = the amount, `submitted_at`, status). Query: `fiscal_yr`, `program_code`, `page` (default 1), `limit` (default 15, capped at 100).
  The scope is fixed **on the server**: the caller must belong to the central review RC (FMD, `GlobalService::isCentralReviewRc()`, by rc_code) — anyone else, including an unknown user, a user without an RC or an administrator without that RC, gets `403` — and the list is always every office from `GlobalService::CENTRAL_REVIEW_RC_CODE_FROM` on. `central`, `rc_id` and `rc_code_from` are not read. The RC comes from `GlobalService::getUserRcId()`, not `getUserRcScope()`, whose "unknown user = administrator" answer fails open.

### file_upload.php (`FileUploadServices` in `file_uploadServices.php`, tables `fims.ip_filemeta` + `fims.file_attachments`)
The Supporting Documents section of the CBA / Memo Request form. All three routes need the JWT; the uploader (`created_by`) is the verified user, never read from the body. Errors return a generic message (the details go to the log).
- `POST /upload/file` - Multipart, field `file` (max 25MB; pdf, doc, docx, xls, xlsx, jpg, jpeg, png). Streams it to object storage (MinIO / S3, `DO_SPACES_*` in `.env`) under `FIMS/supporting-documents/YYYY/MM/` and returns `link` (the object key), `document_name`, `size` and a one-hour `preview_url`. Says so when storage is not configured
- `POST /upload/multi-upload` - Body `{ file_data: [{ document_name (<= 225 chars), link }, ...] }` (1-50 entries). In one transaction: a row per file in `fims.ip_filemeta`, a row per file in `fims.file_attachments`, all sharing a new `multiple_node_id`, which is returned for the caller to store on its record (`budget_allocation.multiple_node_id`). 400 on validation errors, nothing saved
- `POST /upload/single-upload` - Body `{ document_name, link }`: one `fims.ip_filemeta` row; returns its `file_node_id`

### budget_line_item.php (`BudgetLineItemService`, table `fims.budget_line_items`)
Plus the Budget Request pages' own `all` / `list` / `read` / `add` / `update` / `delete`. Used by the Memo Request form:
- `GET /budget_line_item/approved` - Line items of **Approved** budget requests (`settings_status.status_for = 'budget_req'`, name `Approved`), each with its `type_of_fund` and `account_code` / `account_title`, plus `fiscal_yr`, `program_code`, `sub_program_code`, `project_code`, `proposed_amount`. Optional filters, each narrowing to what is attached to it: `program_code`, `sub_program_code`, `project_code`, `fiscal_yr`, `type_of_fund_id`. A line item reaches its program through `budget_req_project` -> `budget_req_program`, so rows without a program block only appear while no program / sub-program is chosen. Ordered by account code
- `GET /budget_line_item/approved/type_of_fund` - The types of fund (`type_of_fund_id`, `name`) that exist on those approved line items: the Memo Request form's Type of Fund options. Same optional filters except `type_of_fund_id`; a type of fund no approved line item uses is not listed

The `chart_of_accounts.php`, `responsibility_center.php`, `budget_allocation.php`, `budget_category.php` and (for `/budget_line_item/approved`) `budget_line_item.php` route files use `->add($accessMiddleware)->add($jwtMiddleware)`. `$accessMiddleware` is currently a
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

### notifications.php (`NotificationService`, tables `uac.notifications` / `uac.notification_reads`)
Implements `_shared/NOTIFICATION-IMPLEMENTATION-PLAN.md`. The caller is always the JWT user (`X-Authorization`).
- `GET /notifications/recent?limit=` - `{data:[{id,type,target,subject,body,link,from_system,created_at,time_ago,read_by_user}], unread_count}`; default 10, clamp 1-200, unread first
- `GET /notifications/count` - `{unread_count}`
- `POST /notifications/mark-read` `{id}` - only rows in the caller's audience; idempotent
- `POST /notifications/mark-all-read` - `{updated}`
- `POST /notifications/send` - superadmin only; `{type, target, subject, body?, link?, from_system?, event_key?}`
- Audience `type` is one of `all | system | user | userlevel | office | usergroup`. Application code sends with
  `NotificationService::toUser() / toUserLevel() / toOffice() / toUserGroup() / toSystem() / toAll()` (never throws; returns the id or null).
- Wired so far: Purchase Order approved / rejected / cancelled -> notifies the creator.

