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
- `PATCH /budget_allocation/process/{id}` - **FMD review**, one step at a time: `For Preview` (FMD Section Chief) -> `For Division Manager` (FMD Division Manager) -> `For Department Manager` (FMD Department Manager) -> `Approved`. Body `{ "decision": "approve" | "return", "remarks"?: string }`. **Every `return` needs `remarks`** (the reason: non-blank, at most 2000 characters, `BudgetAllocationService::REMARKS_MAX_LENGTH`) — a blank or too long one gets **400** and nothing changes; they are saved on `fims.budget_allocation.remarks` (added by `03 - alter_table/28 - budget_allocation_add_remarks.sql`, run `node install_system.js --sql-only`; until then a Return fails) in the same `UPDATE` as the status change, an approval leaves them as they were, and `GET /budget_allocation/read/{id}` returns them as `remarks` (the modal shows them on a request whose status is a `Returned…` one). Only a user of the Financial Management Department (rc_code `35-074-0000000-032`, read with `getUserRcId` — fails closed) who holds the user level of the current step (`uac.user_levels.name`, matched by name) may call it: anyone else gets **403**; a bad decision, an unknown id, a request that is not in a review status (the three steps plus the two `Returned to ...` ones), a missing status or a request somebody else changed meanwhile gets **400** and nothing changes. `approve` -> the next status of the chain; the last approval gives `Approved` **and** the allocation number in the same transaction (kept if it already has one). `return` goes ONE step back: the Department Manager's -> `Returned to Division Manager` (acted on by the Division Manager), the Division Manager's -> `Returned to Section Chief` (acted on by the Section Chief), who can endorse it again; the Section Chief's -> `Returned` (back to the requesting office). No number. Reply `{ success, message, status_name, allocation_number }`. Needs the statuses seeded by `sample_data/06`, `07` and `seed_data/04`, `05` (the API names the missing one). `GET /budget_allocation/read/{id}` adds `data.review` `{ in_review, status, actor, next_status, is_final, can_decide }` for the caller.
  - **Allocation number templates** (the ones the memo form shows, by allocation type and document type; y = year, yy = its last two digits, q = quarter 1-4, m = month in the quarter 1-3, of the day of approval, **Philippine time**): `CBA` -> `CBA <y>-<q><m>-86-<series>` (e.g. `CBA 2026-42-86-01`); `RFA` -> `<yy><q><m>-<code>-<series>` with code `57` for document type `COE`, `87` for `CO` (e.g. `2642-87-01`); `LAA` -> `LAA <y>-<q><m>-<series>`; any other type -> `<TYPE> <y>-<q><m>-<series>`. `<series>` has at least two digits and counts within one prefix (everything before it), so it restarts at `01` each month per type and never repeats; numbers of deleted requests count. Two approvals at once are numbered one after the other (a transaction-level advisory lock).

Validation (400 with a message): fiscal year / vendor / status / department / district / program / sub-program / project must exist; the sub-program must belong to the program and the project to the program (and sub-program, when the project has one); `parent_id` can't be the row itself or one of its descendants; amounts are 0 – 9,999,999,999,999.99; `line_transaction` must be a JSON array/object; `multiple_node_id` must be a UUID.

### print.php (`PrintService`) — printed forms (CBA, CAF, CFA, Memo Request, RFA, LAA, LAA release request, PO, PR, IAR, LV, JEV, FTLA, FTLA request, DV)
One endpoint for every form; each form is a service registered in `PrintService::DOCS` (`CbaPrintService`, `CafPrintService`, `RfaPrintService`, `LaaPrintService`, `LaaRequestPrintService`, `PurchaseOrderPrintService`, `PurchaseRequestPrintService`, `IarPrintService`, `LvPrintService`, `JevPrintService`, `FtlaPrintService`, `FtlaRequestPrintService`, `CfaPrintService`, `MemoRequestPrintService`, `DvPrintService`). One form per document across departments — a department's version is an option (`issuing_office: 'REGIONAL'` on `cba`, `caf`, `rfa`). Docs: `system_docs/modules/PRINT-FORMS.md`. Preview modal: `app/pages/readone/print-preview-readone.php` (`prp_open(payload, { type })`).
- `POST /print/{doc}` - Print a form from a payload, `{doc}` = `cba` | `caf` | `rfa` | `laa` | `laareq` | `po` | `pr` | `iar` | `lv` | `jev` | `ftla` | `ftlareq` | `cfa` | `memo` | `dv`. Body: `{ format: html|pdf|docx, ...payload, letterhead? }` (`letterhead` = `{items, footer_text}`, this print's header — see LetterheadLayoutService). `html` → `{success, html}`; `pdf`/`docx` → `{success, filename, mime, data: base64}` (PDF via Dompdf, DOCX via `node scripts/gen_<form>_docx.js`). Unknown `{doc}` → 404. Payload fields per form (all optional; documented on each form's `model()`):
  - `cba` - Certificate of Budget Allocation (A4): `allocation_number, document_type (CO|COE), project_description, requested_amount, allocated_amount, fiscal_year_label, fund_source_name, program_of_cob, category_name, is_bidding, date, approved_by_name`. Regional (`issuing_office: 'REGIONAL'`): `allocation_number, date, project_description, amount, fiscal_year_label, fund_source_name, signatory_name/_title/_office`
  - `caf` - Certificate as to Availability of Funds (A4, the CBA layout): `caf_number, allocation_number (linked RFA) | rfa_number, caf_amount | amount, purpose, payee_name, payee_title, payee_office_address, fund_source_name, date, approved_by_name`. Regional (`issuing_office: 'REGIONAL'`): `body_variant (entered_into_with|in_favor_of|in_favor_of_petty), caf_number, date, caf_amount, purpose, payee_name, payee_office_address, fund_source_name, allocation_number, ref_type (RFA|LAA), certified_name/_title/_office`
  - `rfa` - Request for Fund Allotment (legal paper): `allocation_number, date, payee_name, requesting_department_type | requesting_department_id | requesting_region_id, form_variant, filled_out_by, rfa_type, particulars, amount, cert_correct_name/_title/_office, project_name, total_estimated_cost, total_funds_available, total_funded_to_date, balance, payments_outstanding, this_request, total_payments, total_contract_cost, adjustment_additive, adjustment_deductive, total_allotment, funding_source{…}, certified_funds_name/_title, recommending_name, approved_name`; `issuing_office: 'REGIONAL'` adds the regional form's labels
  - `po` - Purchase Order (legal paper): `po_id` (or `id`) prints the saved PO (`PurchaseOrderService::fetchById`); payload fields override it or print alone: `po_number, po_date, vendor_name, vendor_address, vendor_tin, mode_of_procurement, delivery_place, delivery_terms, delivery_date_required, payment_terms, items[{item_description, unit_of_measure, quantity_ordered, unit_cost}], total_amount, purpose, requisitioners, references | pr_number, warranty, approving_officer_name/_title, general_manager_name/_title, certified_by_name/_title, prepared_by_initials, funds_available, alobs_no, alobs_amount, alobs_adj`. No letterhead by default (its own title block)
  - `pr` - Purchase Requisition (legal paper): `pr_number, pr_date, purpose, department_name | charge_to, delivery_date, items[{item_description, unit_of_measure, quantity_requested, unit_cost, warehouse_stock?, dept_qty_on_hand?}], total_amount, note, project_description, total_estimate_cost, total_funds_available, funded_to_date, fund_balance, outstanding_payable, request_if_approved, total_payments_project, total_contract_cost, additional_allotment, total_allotment, prior_payment, this_payment, total_payments, balance_to_complete, fund_allotment_no, sig1_name … sig4_name, sig1_role … sig4_role`
  - `laareq` - LAA release request, the regional office's memorandum (letter paper): `date, for_name/_title, thru_name/_title, subject, description, amount, sig_name/_title, laa_number`
  - `iar` - Inspection and Acceptance Report (letter paper, no letterhead by default): `iar_number, supplier_name, po_number, po_date, si_number, si_date, requesting_department, items[{stock_no?, unit_cost, description, quantity}], date_inspected, delivery (complete|partial) | is_complete_delivery, inspector_name/_label, div_manager_name/_label, dept_manager_name/_label`
  - `lv` - Liquidation Voucher (legal paper, its own title box): `lv_number, disbursing_officer, department_region, particulars (text | [{description, amount, sub?}]), gross_amount, amount_in_words, chargeability | fund_source_code + fund_source_name, ca_dv_number, ca_amount, prior_liquidations[{lv_number, amount}], tax_deposit_amount, cash_refund_deposit_amount, refund_or_number, refund_date, refund_amount, obligation{dept_proj, fund_source, budget_ref, budget, obligation_ref, obligation, balance, payments_ref, payments, this_request_ref, this_request, total_payments}, accounting_entries[{obligation?, account_code, debit, credit}], journalized_by_date, indexed_by_date, cert_a/b/c_name/_title, cert_b_amount, jev_no, pc_summary[{type, label, amount}]` (page 2)
  - `jev` - Journal Entry Voucher (A4 landscape, its own title box): `jev_number (blank | TEMP-… → No. blank + temporary reference), jev_date, office, responsibility_center, lines[{account_title, account_code, debit, credit, description?, sl_code?, sl_path?, responsibility_center?}], reference_no, payee_name, particulars, rfa_number, saro_number, prepared_by_name/_title, reviewed_by_name/_title, approved_by_name/_title, approved_by_label, footer_remarks`
  - `ftla` - Fund Transfer Letter of Advice, the letter to the bank (A4, NHA letterhead): `ftla_number, date | transfer_date, to_bank_name, to_bank_branch, subject, from_account_name, from_account_number, to_account_name, to_account_number, amount | transfer_amount, purpose` or `transfers[{from_account_number, from_account_name, to_account_number, to_account_name, amount}]` (body layout follows the accounts: single / several debits → one credit / FROM-TO table), `sig_left_name/_title, sig_right_name/_title, dv_numbers, fund_classification, bank_sig_verified, bank_checked, bank_approved, bank_posted`
  - `ftlareq` - FTLA request, the regional office's memo to Treasury (the `laareq` memo layout): `date, for_name/_title, thru_name/_title, subject, particulars, amount, amount_words, laa_number, laa_department, fiscal_year, rfa_number, rfa_date, bank_account_line, sig_name/_title/_office, ftla_number`
  - `cfa` - Certified Funds Available, the funding-request memo FMD certifies (A4, the shared `laareq` memo layout; letterhead on page 1, running title + "Page X of Y" after): `date, for_name/_title, thru_name/_title, subject, body | paragraphs, main_office, regional_offices, body_after, amount, rfa_number, fund_source_name, requested_by_name/_title, certified_by_name/_title, recommending[{name, title}], approved_by_name/_title, running_title, cfa_number` — fixed labels always print (FOR / THRU / SUBJECT, Main Office / Regional Offices, …); every value only as entered, no defaults
  - `memo` - Memo Request, the originating unit's memorandum to the GM (every unit except FMD; A4, the shared `laareq` memo layout; letterhead on page 1, "Page X of Y" + running title after): `date, for_name/_title, thru_name/_title, subject, body` (typed: `I.` headings, `a.` items, indented continuations, `1. text | aside` lists, `|` tables, `**bold**` — `MemoRequestPrintService::bodyBlocks()`), `amount` (blank → a blank line for FMD), `cba_number, fund_source_name, requested_by_name/_title, certified_by_name/_title, recommending[{name, title}], approved_by_name/_title, running_title, memo_number` — no defaults
  - `dv` - Disbursement Voucher (legal paper), **PDF only** (`html`/`docx` → 400; pdftk fill of the official form, no letterhead): `voucher_number, route (MAIN|REGIONAL), paying_agency, payee_name, payee_address, payee_tin, particulars, gross_amount | amount, deductions[{description, amount}], total_deductions, accounting_entries[{obligation, fpa, object_code, account_code, debit, credit}], cert_officer1_name/_title, cert_officer2_name/_title, fmd_officer_name/_title, accountant_name/_title, approving_officer, approver_title, paper (plain|preprinted), back_page`
  - `laa` - Letter of Advice of Allotment (A4), CO by default or COE with `document_type: 'COE'` (named Regional Manager addressee, "Dear Engr. …" greeting, "To transfer obligation made to …" wording): `document_type, allocation_number, date, office_name | requesting_department_id | requesting_region_id, project_description, amount, fund_source_name, program_of_cob, fiscal_year_label, to_name/_title/_org/_address, greeting, cert_name/_title/_department, close_name/_title/_department`
- `GET /print/resolve-signatory?role=FMD%20Department%20Manager` - `{success, name}` of the current role holder (only roles in `PrintService::SIGNATORY_ROLES`)

### letterhead.php (`LetterheadLayoutService`, table `fims.letterhead_layout`) — letterhead designer
`{doc}` = document code (`CBA`, `RFA`, `LAA`, `CAF`, `LAAREQ`, `PR`, `FTLA`, `FTLAREQ`, `CFA`, `MEMO` — `LetterheadLayoutService::DOCUMENTS`); each keeps its own saved default. The PO, IAR, LV, JEV and DV have no letterhead (their own printed title) — 404 here, and `/print/{doc}` ignores a `letterhead` sent for them.
- `GET /letterhead/{doc}` - What the print preview's letterhead editor starts from: `{layout, saved, can_save, factory, assets}`
- `POST /letterhead/{doc}` - Save as default header: `{layout: {items, footer_text}}`. System Administrator / FMD Department Manager only (403 otherwise)
- `DELETE /letterhead/{doc}` - Restore original (drop the saved default). Same roles

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

- **Review chain** (`BudgetReqService::REVIEW_STEPS`, same idea as the Memo Request's): `For Review` -> *Budget Officer endorses* -> `Endorsed` -> *Budget Head approves* -> `Approved`. A Return goes ONE step back: the Budget Head's gives `Returned to Budget Officer` (the Officer acts on it again — endorse once more, or return it further), the Budget Officer's gives `Returned` (the originating office). The three calls below act as the logged-in user: only an FMD user (central review RC) holding the user level of the request's CURRENT step (`Budget Officer` / `Budget Head`, matched by the user level's name) may act, anyone else gets **403**; a request outside the chain (Draft, Returned, Approved, Cancelled) or at another step gets 400. The status change re-checks the status, so two reviewers acting at once cannot both succeed. `GET /budget_req/list`, `GET /fmd/budget_req/list` (every row) and `GET /budget_req/read/{id}` carry `review` = `{ in_review, status, actor, next_status, is_final, return_status, return_actor, is_returned, can_decide }` for the caller (`can_decide`: they may act on it now), which the pages' row actions and modals are driven by. The `Returned to Budget Officer` status comes from `_assets/sql/01 - fims/04 - seed_data/07 - budget_req_review_chain_statuses.sql` (run `node install_system.js --sql-only`); without it the Budget Head's Return answers that the status is not set up and changes nothing.
- `PUT /budget_req/endorse/{id}` - Budget Processing "Endorse" (Budget Officer; a `For Review` or `Returned to Budget Officer` request only). Body `{ items: [{ budget_line_item_id, type_of_fund_id, component_id, suggested_amount?, account_id? }, ...] }`: `type_of_fund_id` and `component_id` are required on every item; `suggested_amount` is the modal's Suggested Amount column (blank = 0.00; the Amount column beside it is the line's read-only `proposed_amount`); `account_id` is optional and re-codes the line (the Budget Processing modal's editable Account Code): key absent = unchanged, blank = error, same as the current account = unchanged, any other id must be an Active, postable `fims.chart_of_accounts` entry, saved to `budget_line_items.account_id`. Moves the request to `Endorsed` in one transaction.
- `PUT /budget_req/approve/{id}` - Budget Preview "Approve" (Budget Head; an `Endorsed` request only): moves it to `Approved`.
- `PUT /budget_req/return/{id}` - Budget Processing / Budget Preview "Return": one step back along the review chain (see above) and saves the reason in `budget_req.remarks`. Body `{ remarks }` is **required** (non-blank, at most 2000 characters, `BudgetReqService::REMARKS_MAX_LENGTH`); both modals ask for it in a pop-up before sending. Only a request that is at a step of the chain can be returned, by the user that step belongs to. The list / read responses carry `remarks` (null until a request has been returned); the Budget Processing modal shows the Budget Head's remarks on a `Returned to Budget Officer` request

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

### property_record.php (`PropertyRecordService`, table `fims.property_records` + the PIS API; page `app/pages/property-record-page.php`)
All routes `->add($jwtMiddleware)`. PIS is called server-side only, with `PIS_API_TOKEN` from `.env` (Bearer).
- `GET /property-record/list` - Every saved item (reads only `fims.property_records`), grouped into the register rows (same PO + category + sub category + item name combined, quantity / cost / depreciation computed), with the responsibility center, the IAR number and the numbers for the category cards
- `GET /property-record/search?po_number=` - Search one PO in PIS: its items with status 1-4 (items whose TYPENO is not 1 are flagged `is_property: false`), what is already saved and the PO's one responsibility center. Only a PO that is in the IAR module; otherwise "…is not in the IAR module"
- `POST /property-record/save` - `{po_number, inv_ids[]}` — saves the selected PAR items (PIS is asked again; a TYPENO other than 1, a wrong status or an already saved `inv_id` is refused)

### fund_tracking.php (`FundTrackingServices` in `fund_trackingServices.php`, table `fims.fund_tracking`)
SARO / NCA / Trust / OR / Supplemental fund records. Soft-deleted via `deleted_at`; `user_id` is the JWT user, never read from the body. The table has no foreign keys, so the service checks that `tracking_type_id`, `nature_of_fund_id`, `sub_program_code`, `status_id` and `parent_id` exist (and that dates / amounts / UUIDs are valid). `agency_name` stores the agency's **name** as text — `fims.agency` is only the list it is picked from.
- `GET /fund_tracking/all` (public) - No pagination. Optional: `search` (reference_number), `lean=1` (skip `parent` / `children`)
- `GET /fund_tracking/list` - Paginated (`page`, `limit`). Filters: `fiscal_year`, `fiscal_year_from` / `fiscal_year_to`, `tracking_type_id`, `type` (repeatable, code or id), `nature_of_fund` (id or name), `exclude_nature_of_fund` (id or name — leaves that nature out, children included; records with no nature are kept), `sub_program_code`, `program_code`, `status_id`, `parent_id`, `multiple_node_id`, `search` / `reference_number`, `agency_or_program_search`, `roots_only`, `root_type`, `nature_tree`, `tracking_group`, `supplemental_status`. Each row carries nested `tracking_type`, `sub_program` (with its program), `agency`, `nature_of_fund`, `parent[]` and `children[]`
- `GET /fund_tracking/read/{id}` - One record, plus `file_attachments[]` (Supporting Documents) and `supplemental`
- `POST /fund_tracking/add` - `tracking_type_id` required; `reference_number` required unless the nature of fund is GAAAO (then generated as `GAAAO-<fiscal_year>`). Returns `id` and `nca_ids`. A **SARO** may also carry `ncas: [{ reference_number (required), name, date, amount, unreleased, bank_account_no, fiscal_year, sub_program_code, nature_of_fund_id }, …]` (at most 100): each becomes an NCA record whose `parent_id` is the new SARO. Its `fiscal_year`, `sub_program_code` and `nature_of_fund_id` are the SARO's unless the item carries its own: a key that is left out inherits the SARO's value, a value overrides it (and must exist), and an explicit `null` means none. `nca_ids` comes back in the order the items were sent (use it to attach each NCA's Supporting Documents). The SARO and all its NCAs are saved in **one transaction** — if any NCA is invalid (or the database rejects it) nothing is saved and the `400` message names it (`NCA #2: …`); `ncas` on anything but a SARO, a missing NCA reference, or the same reference twice are refused. A **Trust Fund** may likewise carry `ors: [{ reference_number (required), name, date, amount, unreleased, bank_account_no, remarks, fiscal_year, sub_program_code, nature_of_fund_id, agency_name }, …]` (at most 100): each becomes an OR record whose `parent_id` is the new Trust Fund and which takes the Trust Fund's fiscal year, sub-program, nature of fund and agency unless the item carries its own — same single transaction and same refusals (`OR #2: …`; `ors` on anything but a Trust Fund). The response carries `or_ids` next to `nca_ids`
- `PATCH /fund_tracking/update/{id}` - Partial update: for the optional ids / codes `''` leaves the value alone and `null` clears it
- `DELETE /fund_tracking/delete/{id}` - Soft delete; `409` while other (non-deleted) records still have it as `parent_id`
- `POST /fund_tracking/import`, `POST /fund_tracking/import-trust-fund` - Excel rows parsed in the browser (row keys are in the route file). A row whose sub-program matches nothing in `fims.sub_program` is skipped (create it first, then re-import — known references are skipped as duplicates)
- `GET /fund_tracking/fund_sourcing_determination`, `GET /fund_tracking/dashboard` (public) - SARO / NCA / Trust / Supplemental totals. Filters: `fiscal_year`, `sub_program_code`, `program_code` (+ `fiscal_year_from` / `fiscal_year_to` and `exclude_nature_of_fund` on the first — the latter leaves a nature of fund out of the SARO / NCA / Trust totals, not the Supplemental one). The dashboard has no Cross-Access section (`treasury.fund_transfers` does not exist here)
- `GET /fund_tracking/supplemental_status_summary` (public) - Needs `fmd.supplemental_fund_details`, which has no SQL in this repo yet; until it exists this route (and the `supplemental` payload / `supplemental_status` filter) report an error and the rest of the module is unaffected

### agency.php, nature_of_fund.php, fund_tracking_type.php (lookups for the above)
`AgencyServices` / `fims.agency` (`/agency/...`), `NatureOfFundServices` / `fims.nature_of_fund` (`/nature-of-fund/...`), `FundTrackingTypeServices` / `fims.fund_tracking_type` (`/fund-tracking-type/...`). Each has `GET all` (public), `GET list`, `GET read/{id}`, `POST add`, `PATCH update/{id}` and `DELETE delete/{id}`. Agency and nature of fund hard-delete (names are unique); fund tracking type soft-deletes (`deleted_at`) and also has `PATCH /fund-tracking-type/restore/{id}`.

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

