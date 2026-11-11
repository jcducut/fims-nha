# Developer Guide ↔ SRS Cross-Match — FIMS NHA

> Built 2026-11-09 by reading every module/feature row in
> `system_docs/developerguide/DEVELOPER-GUIDE.md` §8 ("Feature reference") against the numbered
> requirement tables in all five SRS Word docs under `system_docs/modules/` (also duplicated at
> `_assets/System Requirements/`):
>
> | Short name used below | File |
> |---|---|
> | **Accounting** | `SOFTWARE-REQUIREMENT-SPECIFICATION-Accounting-Department-v4-final-revision.docx` |
> | **FMD** | `SOFTWARE-REQUIREMENT-SPECIFICATION-FMD-v4.docx` |
> | **Regional** | `SOFTWARE-REQUIREMENT-SPECIFICATION-REGIONAL-V4.docx` |
> | **District** | `SOFTWARE-REQUIREMENT-SPECIFICATION-District-V4.docx` |
> | **TD** | `SOFTWARE-REQUIREMENT-SPECIFICATION-TD-v4.docx` |
>
> Every SRS citation below (`§2.2.1.6`, `§1.1.1.2`, …) is the requirement's own numbered ID inside
> that document's tables — open the docx and search for the number to see the full user-story text.

## How to read this

Each implemented feature gets an **alignment rating**, not just a citation — several rows below
cite the *closest* SRS section while plainly stating it is **not** a 1:1 match:

| Rating | Meaning |
|---|---|
| ✅ **Exact** | The SRS section's title and described behavior match what's built |
| 🟡 **Partial** | Same business object/section, but scope or data flow differs in a specific, named way |
| 🔵 **Conceptual** | No SRS section names this feature; the closest requirement is cited for context only |
| ⬜ **No SRS** | Nothing in any of the five SRS docs covers this — it's platform infrastructure the TOR implies is necessary but never enumerates as a numbered requirement |

Don't read a 🔵/⬜ row as "this feature is wrong." Several of the richest things built so far
(the whole Budget Request cascade, all of User Access & Administration) are **deliberate
engineering abstractions that don't exist as named SRS requirements at all** — see
[§6 Findings](#6-findings--honesty-notes) for why that's a real, useful thing to flag rather than
paper over.

---

## 1. User Access & Administration (`uac` schema) — DEVELOPER-GUIDE §8.1

| Feature | Alignment | SRS section | Note |
|---|---|---|---|
| Login/session, `UserAccess`, `UserManagement`, `DepartmentManagement`, `PositionManagement`, `SecurityManagement`, `SignatoryManagement`, `Systems`/`DepartmentSystems`/`Api*`, `AuditLogs`, `ChangePassword`/`ResetPassword`, `WorkflowManager`, `DocumentTypeManager`/document branding, `FileManager`/`PageEditor`, `IdGenerator`, `PsgcMap`, `EventPlayground`/`BullMqManager`, `AiAssistant`, `PermissionModuleCaptions` | ⬜ **No SRS** | — | Searched all five docs for "user access", "user level", "login", "permission", "department management", "position management", "plantilla", "audit log" (as a section title), "document header"/"letterhead", "file manager", "id generator", "notification system" (as a module), "security management" — **none appear as a named requirement**. The only near-hits are incidental "audit trail" bullets *inside* unrelated business features (e.g. Accounting §2.2.1.4 "Posting and Generation of General Ledger" lists an audit trail as one bullet among several) — that's a property a ledger page needs, not a System Administration module spec |

**Why this whole group is unmatched:** the five SRS docs are organized **by department's business
process** (Accounting, FMD, Regional, District, Treasury), not by platform capability. User/role/
permission administration, department & position management, document branding, file sharing,
notifications-as-infrastructure, and the AI assistant are cross-cutting platform features every
department needs, but none of the five docs specify them as their own module — they're assumed.
Treat `_guidelines/` + `DEVELOPER-GUIDE.md` §8.1 as the only spec these features have today.

---

## 2. Budgeting & Planning (`fims` schema) — DEVELOPER-GUIDE §8.2

| Feature | Alignment | SRS section(s) | Note |
|---|---|---|---|
| Budget Request (Program → Project → COA-line-item cascade, state machine Draft → For Review → Endorsed → Approved/Returned) | 🔵 **Conceptual** | FMD §1.1.1.1 *Budget Allocation Dashboard*, §1.1.1.2 *Certificate of Budget Allocation (CBA) Issuance*, §1.1.2.2 *Corporate Operating Budget (COB) Preparation* | No SRS section describes a self-service "Budget Request" form that a department fills in with nested Program/Project/COA line items. The SRS's actual budgeting workflow is **document-centric**: FMD *issues* a CBA against an already-approved COB, departments/regions *request* RFA/LAA against an issued CBA (FMD §1.1.1.2/.4/.5, Regional §1.1.1–.3, District §1.2.1–.3). What's built is a different shape — a planning-side data-entry cascade — standing in for that document chain, not an implementation of it |
| Budget Revision | 🔵 **Conceptual** | FMD §1.1.2.8.8 *Realignment of budget*, §1.1.3.6.3 (COE) | SRS calls this "budget realignment," one bullet inside monitoring sections, not its own workflow with a reviewer approve/reject step like `budget-rev-review.php` implements |
| Budget Allocation (CBA form) | 🟡 **Partial — and currently broken** | FMD §1.1.1.2 *CBA Issuance*, Regional §1.1.4 *Certificate of Budget Allocation (CBA)* | The code's own naming (`'CBA#'.date(...)`) shows the dev intent *was* to implement CBA issuance here. But per DEVELOPER-GUIDE §7.3 this page targets a non-existent `PHPMaker2024\FMD` namespace/API and generates the CBA number **client-side** rather than server-side like §1.1.1.2/§1.1.4 both specify ("system shall generate CBA with number in an official format") |
| Certificate of Budget (COB) page | 🟡 **Partial** | FMD §1.1.2.2 *Corporate Operating Budget (COB) Preparation*, §1.1.2.4 *Approved COB Submission to DBM* | Page exists (`cob-page.php`) but per DEVELOPER-GUIDE §7.2 is missing its shared stylesheet; not evaluated here for functional completeness against the multi-version/multi-format requirements in §1.1.2.2 |
| Memo Request | 🔵 **Conceptual, empty stub** | FMD §1.1.3.5 *MEMO Proposal* | Title match ("Memo…"/"MEMO Proposal") but `memo-req-page.php` is a confirmed zero-byte file (DEVELOPER-GUIDE §7.5) — nothing to compare against §1.1.3.5's funding-request/approval-workflow/authorization-issuance bullets yet |
| Fiscal Year | 🔵 **Conceptual** | — (implicit everywhere; every FMD/Accounting table has a "per year" dependency) | No section defines fiscal year as its own master-data object; it's a cross-cutting dimension in the SRS, same as here |
| Expense Class (`CO`/`PS`/`MOOE`/`EO`) | 🔵 **Conceptual, and orphaned in code** | FMD §1.1.3.4 *COE Budget Allocations Management* (PS/MOOE/Trust Fund/Equipment Outlay), Accounting §2.3.10.3/§2.3.10.4 (MOOE/Capital Outlay voucher categories) | Closest SRS anchor is the COE allocation-category list, which is a loose match at best. Separately — see `system_docs/ERD/README.md` §3 — `fims.expense_class` is no longer referenced by any FK in this codebase (`budget_req.expense_class_id` was dropped), so this table is currently disconnected from both the SRS concept *and* the live schema |
| Component Type (CO/Other-CO/COE) | 🔵 **Conceptual** | FMD §1.1.3.4 *COE Budget Allocations Management* | Same source list as Expense Class above; SRS doesn't distinguish a separate "Component Type" lookup from "Expense Class" the way this codebase does |
| Type of Fund (NG Subsidy-GAA/NF/UF, Trust Fund, Corporate Receipt) | ✅ **Exact vocabulary, no dedicated section** | FMD §1.1.2.6.3 *Calculate budget utilization rates by… Fund source (NG Subsidy, Trust Fund, Supplemental, Corporate Receipts)* | The fund-source list matches almost word-for-word; it's just never promoted to its own numbered requirement in the SRS, only a sub-bullet inside monitoring |

---

## 3. Reference / Master Data — DEVELOPER-GUIDE §8.3

| Feature | Alignment | SRS section(s) | Note |
|---|---|---|---|
| Program / Sub-Program / Project (master data + cascade) | 🔵 **Conceptual** | Accounting §2.1.2.1 *Project Costing Dashboard*, §2.1.2.2 *Ledgers per Program/Project Maintenance* | SRS treats Program/Project as an existing dimension that *ledgers* are kept per, not as its own CRUD master-data feature with a Program → Sub-Program → Project hierarchy and server-generated codes (`GlobalService::generateProgramCode()`) the way it's built here |
| **Chart of Accounts** | ✅ **Exact** | **Accounting §2.2.1.6** *Chart of Accounts (UACS) Management* | Direct, strong match. §2.2.1.6's bullets are: *"Unified Account Code Structure per IPSAS"*, *"Responsibility Center Code"*, *"Account Hierarchy and Classifications"*, *"Account types: Asset, Liability, Equity, Income, Expense"* — all four map onto what `ChartOfAccountsService`/`fims.chart_of_accounts` actually implements (hierarchical parent/child, `account_type`, server-generated `ACC00001…` ids). Also referenced (view-only, by a consumer) in Regional §1.6.1 *Chart of Accounts* ("view…maintained by Accounting Department for reference") and District §1.5.5/§1.5.6 |
| **Responsibility Center** | ✅ **Exact — same SRS requirement as COA** | **Accounting §2.2.1.6** *Chart of Accounts (UACS) Management* | This is **not** a separate SRS section — "Responsibility Center Code" is one of the four bullets *inside* §2.2.1.6, consistent with RCA being a UACS segment (see `project_coa_book_selector`/`project_coa_code_pathb`-style context in this repo's own ERD notes). The codebase splits it into its own `fims.responsibility_code` table/page/service where the SRS keeps it as one feature — a reasonable implementation choice, but worth knowing there's one SRS citation behind two built features |

---

## 4. Procurement — DEVELOPER-GUIDE §8.4

| Feature | Alignment | SRS section(s) | Note |
|---|---|---|---|
| **Purchase Order** | 🟡 **Partial — different shape, not just missing scope** | **Regional §1.3.2** *Purchase Order (PO)*; also FMD §1.1.3.2 *Processing of PR/PO* | Title/object matches, but the *direction* of the workflow doesn't: Regional §1.3.2 says the system "shall accept only POs that have been **fully approved and certified as to Funds Available**" — i.e. Regional's PO feature is a **read/record-only intake** of a PO that was approved *elsewhere* (District/Program office), with no PR linkage requirement stated. What's built (`fims.purchase_orders`) is the opposite shape: an **originating** `DRAFT → SUBMITTED → APPROVED/REJECTED → CANCELLED` workflow that owns its own approval chain. DEVELOPER-GUIDE §8.4 already flags "no PR linkage" as a deliberate omission vs. the predecessor `GLOBAL/` module — this cross-match adds that the *direction* (originate vs. record-approved-input) also differs from Regional §1.3.2. Purchase Request (PR) itself (Regional §1.3.1) has **no built equivalent at all** |
| **Vendors** | 🟡 **Partial — one generic master vs. three scoped ones** | Accounting §2.3.10.9 *Supplier/Contractor Records*; Regional §1.3.3 *Supplier/Contractor Records*; District §1.1.1 *Supplier/Contractor Masterlist* | Same title/fields (TIN, address, contacts, bank details, payment method) appears **independently in three SRS docs**, each scoped to its own office (Accounting's Voucher Section, each Regional office, each District office) with District additionally wanting "filter by region/district, program, project" (Accounting §2.3.10.9) and Excel export (District §1.1.1). What's built is a single flat `fims.vendors` table with no office/region scoping — closest to Accounting's version (it's the one with `vendor_type`/`tax_classification`/`is_vat_registered`, matching "tax classification" in §2.3.10.9), but doesn't yet implement payment-history tracking or the region/district/program filter both SRS docs ask for |

---

## 5. Platform utilities — DEVELOPER-GUIDE §8.5

| Feature | Alignment | SRS section | Note |
|---|---|---|---|
| Notifications (`app/routes/notifications.php`) | 🔵 **Conceptual** | FMD §1.1.2.5 *Notification System* ("Dashboard and email notifications… alerted to important updates and actions") | Title/intent match, but §1.1.2.5 is written as one bullet inside Fund Sourcing monitoring, not a platform-wide service the way it's built here — same cross-cutting-vs-department-specific gap as §1 above |
| Swagger generation trigger | ⬜ **No SRS** | — | Internal API-documentation tooling; not a business requirement |
| Generic CRUD template (`crud.php`/`crudServices.php`) | ⬜ **No SRS** | — | Confirmed dead code pointing at a non-existent `shared` schema (DEVELOPER-GUIDE §7.4) — nothing to cross-match |

---

## 6. Findings & honesty notes

1. **`uac` has zero SRS coverage.** Every feature in DEVELOPER-GUIDE §8.1 is platform
   infrastructure the department SRS docs simply never describe, because none of the five docs is
   about system administration — they're each one department's business process. This isn't a gap
   in what's built; it's a gap in what these particular SRS documents cover at all. If a System
   Administration SRS exists elsewhere (it isn't under `system_docs/modules/` or
   `_assets/System Requirements/` today), this doc should be updated to cite it.
2. **The built "Budget Request" feature is a reinterpretation, not an implementation, of the SRS's
   CBA/RFA/LAA/COB document chain.** The SRS's actual Financial Planning & Control workflow (FMD
   §1.1.1.1–§1.1.1.9) is almost entirely **not built**: CBA issuance with official-format numbering
   and GM-signed transmittal, RFA processing with CAF issuance, LAA with streamlined (no-GM)
   approval, document-completeness checklist validation, and the whole Financial Monitoring Section
   (§1.1.2.1–§1.1.2.16 — COB prep, FMR generation, SARO/NCA tracking, Trust Fund/NDRRMC status,
   fund-balance aging) and Fund Sourcing Division (§1.2.1.1–§1.2.1.10 — cross-access/borrowing,
   SARO/NCA processing from DBM, supplemental fund requests) have **no built equivalent at all**.
   What exists instead is a Program/Project/COA-line-item planning cascade that covers some of the
   same ground conceptually (budget ceilings, line-item detail, an approval state machine) but
   isn't the document-centric workflow the SRS specifies. Don't assume "Budget Request is done" —
   it's a different design occupying similar territory.
3. **Purchase Order direction mismatch is a real design decision to revisit, not just missing
   scope.** Regional §1.3.2 wants a record-only intake of externally-pre-approved POs; what's built
   is a self-contained origination-to-approval workflow. Both are legitimate designs — but if this
   feature is ever asked to satisfy Regional's SRS literally, the gap is the *workflow shape*, not
   a missing field.
4. **Chart of Accounts and Responsibility Center are the strongest matches in the whole repo** —
   both land cleanly on Accounting §2.2.1.6, with the SRS's own bullet list almost exactly
   describing what's implemented. If asked "what's actually SRS-compliant today," lead with these
   two.
5. **Vendors/Supplier-Contractor Records exists in three separate SRS docs with three different
   scopes** (Accounting/Voucher-Section-wide, per-Regional-office, per-District-office) and this
   repo has one unscoped version. Worth a deliberate decision later: either the one `fims.vendors`
   table stays the single source of truth system-wide (simplest, diverges from all three SRS
   scopings), or region/district scoping gets added when Regional/District work actually starts.
6. **`fims.expense_class` sits at the intersection of two gaps**: loosely SRS-matched (closest is
   FMD §1.1.3.4's PS/MOOE/Trust Fund/Equipment Outlay list, not an exact title match) *and*
   structurally orphaned in the live schema (see `system_docs/ERD/README.md` §3 — no FK references
   it since `budget_req.expense_class_id` was dropped). Both facts point the same direction: this
   table's design hasn't been revisited since the Budget Request redesign that removed its only
   consumer.

## 7. Coverage by SRS document (rough, feature-count basis)

| SRS document | Sections cited above | Total numbered sections in doc | Rough coverage |
|---|---|---|---|
| **FMD** (`SOFTWARE-REQUIREMENT-SPECIFICATION-FMD-v4.docx`) | 7 of 42 tables cited (mostly as 🔵 conceptual anchors, not direct implementations) | 42 | Low — the richest-built part of this repo (Budgeting & Planning) still only loosely echoes a handful of FMD's 42 requirement tables |
| **Accounting** (`…Accounting-Department-v4-final-revision.docx`) | 3 of 68 tables cited (§2.2.1.6 COA/RC, §2.3.10.9 Vendors) | 68 | Low, but what *is* cited (§2.2.1.6) is the strongest match in this whole document |
| **Regional** (`…REGIONAL-V4.docx`) | 3 of 29 tables cited (§1.3.1 PR — unmatched, §1.3.2 PO — partial, §1.3.3 Vendors — partial, §1.6.1 COA — consumer-only) | 29 | Near-zero — Regional offices aren't built at all (DEVELOPER-GUIDE §12) |
| **District** (`…District-V4.docx`) | 2 of 18 tables cited | 18 | Near-zero — same reason |
| **TD** (`…TD-v4.docx`) | 0 cited | — | None — Treasury Department isn't built at all |

This table is a feature-count sanity check, not a certification — a cited section being "covered"
above usually means 🟡/🔵, not ✅. Only §2.2.1.6 (Chart of Accounts/Responsibility Center) earns an
unqualified ✅ against its full SRS text.

---

## Related docs

- `system_docs/developerguide/DEVELOPER-GUIDE.md` §8 — the feature inventory this doc cross-matches
  against; §12 — the department-level roadmap gap (Accounting/Treasury/Regional/District not built).
- `system_docs/ERD/README.md` — table-level schema detail for every `fims`/`uac` table named above.
- `system_docs/modules/*.docx` (also `_assets/System Requirements/`) — the five SRS source documents.
