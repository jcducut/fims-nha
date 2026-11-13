<?php

namespace PHPMaker2024\FMD;

// Initialize variables
$allocationId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$user_id = CurrentUserID();

// Generate CBA Number (placeholder — regenerated client-side unless editing)
$cba_number = 'CBA#'.date('Y-m-d').'-'.str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

$apiBase = Config('API.FMD_URL');

?>

<div class="cba-form-container">

	<style>
		/* ── Full-page loading overlay ────────────────────────────────────────── */
		#cba_loading_overlay {
			position: fixed;
			inset: 0;
			z-index: 99999;
			background: color-mix(in srgb, var(--afims-surface, #FFFFFF) 92%, transparent);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			gap: 20px;
			backdrop-filter: blur(3px);
			transition: opacity .3s ease;
		}

		#cba_loading_overlay.cba-fade-out {
			opacity: 0;
			pointer-events: none;
		}

		.cba-spinner {
			width: 48px;
			height: 48px;
			border: 4px solid var(--afims-border, #E4ECF2);
			border-top-color: var(--afims-heading-fill, #000E3E);
			border-radius: 50%;
			animation: cba-spin .75s linear infinite;
		}

		@keyframes cba-spin {
			to {
				transform: rotate(360deg);
			}
		}

		.cba-loading-text {
			font-size: 14px;
			font-weight: 600;
			color: var(--afims-heading, #000E3E);
			letter-spacing: .3px;
		}

		.cba-loading-sub {
			font-size: 12px;
			color: var(--afims-text-muted, #62657E);
			margin-top: -12px;
		}
	</style>

	<!-- ── Full-page loading overlay (hidden once all dropdowns resolve) ──────── -->
	<div id="cba_loading_overlay">
		<div class="cba-spinner"></div>
		<p class="cba-loading-text">Loading form data&hellip;</p>
		<p class="cba-loading-sub">Fetching fiscal years, fund sources, programs &amp; more</p>
	</div>

	<!-- Form Header -->
	<div class="form-header">
		<div class="cba-number-card">
			<div>
				<p class="cba-number-label" id="cba_number_label">CBA Number</p>
				<p class="cba-number-value" id="cba_number_display">
					<?php echo htmlspecialchars($cba_number); ?>
				</p>
			</div>
			<div style="text-align: right;">
				<p class="cba-number-label">Date Created</p>
				<p style="font-size: 14px; font-weight: 600; color: var(--afims-heading, #000E3E); margin: 0;">
					<?php echo date('F d, Y'); ?>
				</p>
			</div>
		</div>
	</div>

	<form id="cba_form" method="POST">

		<!-- ── Program and Project ────────────────────────────────────── -->
		<div class="form-section" id="program_project_section">
			<h2 class="section-header" id="program_project_section_title">Program and Project Details</h2>

			<div class="form-row" id="program_project_row">
				<!-- Program → GET /program/all (searchable combobox) -->
				<div class="form-group">
					<label for="prog_search" class="form-label">
						Program
					</label>
					<div class="select-loading-wrapper is-loading" id="program_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="prog_search" class="form-input" placeholder="Search program&hellip;"
								autocomplete="off" disabled>
							<div id="prog_dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;
                                       background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-top:none;
                                       border-radius:0 0 6px 6px;max-height:220px;overflow-y:auto;
                                       box-shadow:0 4px 12px rgba(0,0,0,.1);">
							</div>
						</div>
						<input type="hidden" id="program_id" name="program_id" value="">
					</div>
					<div class="error-box" id="program_error">Failed to load programs. Please refresh the page.</div>
					<div id="prog_info"
						style="display:none;margin-top:8px;padding:10px 12px;
                        background:var(--afims-surface-subtle, #F4F7FA);border:1px solid var(--afims-primary-soft, #E6EBF6);border-radius:6px;font-size:12px;line-height:1.6;">
						<div style="display:grid;grid-template-columns:auto 1fr;gap:2px 10px;">
							<span style="color:var(--afims-text-muted, #62657E);font-weight:500;">Code</span>
							<span id="progi_code" style="color:var(--afims-heading, #000E3E);font-weight:600;"></span>
						</div>
					</div>
				</div>

				<!-- Project → GET /projects/all, filtered client-side by program_id (searchable combobox) -->
				<div class="form-group">
					<label for="proj_search" class="form-label">Project</label>
					<div class="select-loading-wrapper is-loading" id="project_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="proj_search" class="form-input" placeholder="Search project&hellip;"
								autocomplete="off" disabled>
							<div id="proj_dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;
                                       background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-top:none;
                                       border-radius:0 0 6px 6px;max-height:220px;overflow-y:auto;
                                       box-shadow:0 4px 12px rgba(0,0,0,.1);">
							</div>
						</div>
						<input type="hidden" id="project_id" name="project_id" value="">
					</div>
					<div class="error-box" id="project_error">Failed to load projects. Please refresh the page.</div>
					<p class="input-hint" id="project_hint">Search for a project.</p>
				</div>
			</div>

			<div class="form-row full-width">
				<div class="form-group">
					<label for="project_description" class="form-label">Purpose / Description</label>
					<textarea id="project_description" name="project_description" class="form-textarea"
						placeholder="Enter the purpose or description of this budget allocation..."></textarea>
					<p class="input-hint">Provide details about the purpose and scope of this budget allocation</p>
				</div>
			</div>
		</div>

		<!-- ── Basic Information ──────────────────────────────────────── -->
		<div class="form-section">
			<h2 class="section-header">Basic Information</h2>

			<div class="form-row">
				<!-- Allocation Type (static) -->
				<div class="form-group">
					<label for="allocation_type" class="form-label">
						Allocation Type <span class="required">*</span>
					</label>
					<select id="allocation_type" name="allocation_type" class="form-select" required>
						<option value="">Select allocation type</option>
						<option value="CBA">CBA - Certificate of Budget Allocation</option>
						<option value="RFA">RFA - Request for Fund Allotment (&lt;&#8369;50M)</option>
						<option value="LAA">LAA - Letter of Advice of Allotment (&ge;&#8369;50M)</option>
						<option value="CFA">CFA - Certificate of Fund Availability</option>
					</select>
				</div>

				<!-- Fiscal Year → GET /fiscal_year/all -->
				<!-- Value = fiscal_period_id (PK), Display = "FY {fiscal_year}" -->
				<div class="form-group">
					<label for="fiscal_year" class="form-label">
						Fiscal Year <span class="required">*</span>
					</label>
					<div class="select-loading-wrapper is-loading" id="fiscal_year_wrapper">
						<select id="fiscal_year" name="fiscal_year" class="form-select" required disabled>
							<option value="">Loading fiscal years&hellip;</option>
						</select>
					</div>
					<div class="error-box" id="fiscal_year_error">Failed to load fiscal years. Please refresh the page.
					</div>
				</div>
			</div>

			<div class="form-row">
				<!-- Category → GET /fmd/api/budget-allocation-category-type/all (searchable combobox) -->
				<div class="form-group">
					<label for="cat_search" class="form-label">Category</label>
					<div class="select-loading-wrapper is-loading" id="category_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="cat_search" class="form-input" placeholder="Search category&hellip;"
								autocomplete="off" disabled>
							<div id="cat_dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;
                                       background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-top:none;
                                       border-radius:0 0 6px 6px;max-height:220px;overflow-y:auto;
                                       box-shadow:0 4px 12px rgba(0,0,0,.1);">
							</div>
						</div>
						<input type="hidden" id="budget_allocation_category_id" name="budget_allocation_category_id"
							value="">
					</div>
					<div class="error-box" id="category_error">Failed to load categories. Please refresh the page.</div>
				</div>
			</div>

			<!-- ── Document Type ──────────────────────────────────────────── -->
			<div class="form-row" id="document_type_row">
				<div class="form-group">
					<label for="document_type" class="form-label">
						Document Type <span class="required">*</span>
					</label>
					<select id="document_type" name="document_type" class="form-select" required>
						<option value="">Select document type</option>
						<option value="CO">CO &mdash; Capital Outlay</option>
						<option value="COE">COE &mdash; Corporate Operating Expenditure</option>
					</select>
					<p class="input-hint">Budget document classification. Required for CBA allocations.</p>
				</div>
				<!-- Category Type → GET /coe-category-type/all -->
				<div class="form-group">
					<label for="category_type_id" class="form-label">Expense Type</label>
					<div class="select-loading-wrapper is-loading" id="category_type_wrapper">
						<select id="category_type_id" name="category_type_id" class="form-select" disabled>
							<option value="">Loading category types&hellip;</option>
						</select>
					</div>
					<div class="error-box" id="category_type_error">Failed to load category types. Please refresh the
						page.</div>
					<p class="input-hint" id="category_type_hint">Budget line classification (CO / PS / MOOE / EO)</p>
				</div>
			</div>

			<!-- ── Is this for bidding? ────────────────────────────────────── -->
			<div class="form-row">
				<div class="form-group">
					<label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
						<input type="checkbox" id="is_bidding" name="is_bidding"
							style="width:16px;height:16px;cursor:pointer;">
						<span>Is this for bidding?</span>
					</label>
					<p class="input-hint">Check if this allocation is being certified for bidding purposes. Affects the
						wording of the printed CO certificate.</p>
				</div>
			</div>

			<!-- ── Parent CBA searchable combobox (shown only for RFA / LAA) ─── -->
			<div class="form-row" id="cba_id_row" style="display:none;">
				<div class="form-group" style="flex:1;">
					<label class="form-label">
						Parent CBA
					</label>

					<!-- Combobox wrapper -->
					<div id="cba_combo" class="cba-combo" style="position:relative;">

						<!-- Search input (visible) -->
						<div style="position:relative;">
							<input type="text" id="cba_search" class="form-input"
								placeholder="Search by CBA number, program or fund…" autocomplete="off"
								style="padding-right:36px;" disabled />
							<!-- Spinner / chevron icon -->
							<span id="cba_combo_icon" style="
                                position:absolute;right:10px;top:50%;transform:translateY(-50%);
                                pointer-events:none;color:var(--sec,#475569);
                            ">
								<!-- chevron-down -->
								<svg id="cba_icon_chevron" width="16" height="16" fill="none" viewBox="0 0 24 24"
									stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M19 9l-7 7-7-7" />
								</svg>
								<!-- spinner (hidden by default) -->
								<svg id="cba_icon_spinner" width="16" height="16" viewBox="0 0 24 24" fill="none"
									stroke="currentColor" style="display:none;animation:cbaSpin .7s linear infinite;">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83" />
								</svg>
							</span>
						</div>

						<!-- Dropdown list -->
						<ul id="cba_dropdown" role="listbox" style="
                            display:none;position:absolute;z-index:999;
                            width:100%;max-height:240px;overflow-y:auto;
                            background:var(--afims-surface, #FFFFFF);border:1px solid var(--bdr,#e2e8f0);
                            border-radius:6px;margin-top:4px;padding:4px 0;
                            box-shadow:0 4px 16px rgba(0,0,0,.10);
                            list-style:none;
                        "></ul>

						<!-- Hidden real input submitted with the form -->
						<input type="hidden" id="cba_id" name="cba_id" value="" />
					</div>

					<div class="error-box" id="cba_id_error">Failed to load CBA list. Please refresh the page.</div>
					<p class="input-hint" id="cba_id_hint">
						Optionally link this RFA / LAA to an existing approved CBA.
					</p>
				</div>
			</div>

			<style>
				@keyframes cbaSpin {
					to {
						transform: rotate(360deg);
					}
				}

				#cba_dropdown li {
					padding: 8px 12px;
					cursor: pointer;
					font-size: 13px;
					line-height: 1.4;
					color: var(--txt, #0f172a);
					border-radius: 4px;
					margin: 0 4px;
				}

				#cba_dropdown li:hover,
				#cba_dropdown li.cba-active {
					background: var(--afims-heading-fill, #000E3E);
					color: var(--afims-pure-white, #FFFFFF);
				}

				#cba_dropdown li.cba-no-results {
					color: var(--muted, #94a3b8);
					cursor: default;
					font-style: italic;
				}

				#cba_dropdown li.cba-no-results:hover {
					background: none;
					color: var(--muted, #94a3b8);
				}

				#cba_dropdown li mark {
					background: var(--afims-warning-soft, #FFF8DB);
					color: inherit;
					border-radius: 2px;
					padding: 0 1px;
				}

				#cba_search.cba-has-value {
					border-color: var(--afims-heading-fill, #000E3E);
				}

				#cba_search.cba-invalid {
					border-color: var(--afims-danger, #DC3545);
				}

				/* ── Budget Version search dropdown ──────────────── */
				.bv-item {
					padding: 9px 14px;
					cursor: pointer;
					border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA);
				}

				.bv-item:hover {
					background: var(--afims-surface-subtle, #F4F7FA);
				}

				.bv-item-name {
					font-size: 13px;
					font-weight: 600;
					color: var(--afims-heading, #000E3E);
				}

				.bv-item-sub {
					font-size: 11px;
					color: var(--afims-text-muted, #62657E);
					margin-top: 2px;
				}

				.bv-item-balance {
					font-size: 11px;
					font-weight: 600;
					color: var(--afims-success, #11A752);
					margin-top: 2px;
				}

				/* ── Print Preview button ─────────────────────────── */
				.btn-print {
					display: inline-flex;
					align-items: center;
					gap: 6px;
					padding: 8px 16px;
					background: var(--teal, #0d9488);
					color: var(--afims-pure-white, #FFFFFF);
					border: none;
					border-radius: 6px;
					font-size: 13px;
					font-weight: 600;
					cursor: pointer;
					transition: background .15s;
				}

				.btn-print:hover:not(:disabled) {
					background: var(--afims-success-hover, #0C7539);
				}

				.btn-print:disabled {
					opacity: .6;
					cursor: not-allowed;
				}

				/* ── Program search loading state ────────────────── */
				#prog_dropdown .prog-searching {
					padding: 10px 14px;
					color: var(--afims-text-muted, #62657E);
					font-size: 13px;
					font-style: italic;
				}
			</style>
		</div>

		<!-- ── Budget Allocation Amount ───────────────────────────────── -->
		<div class="form-section">
			<h2 class="section-header">Budget Allocation Amount</h2>
			<p class="input-hint" style="margin-top:-4px;margin-bottom:12px;">
				Add one line per Type of Fund / Budget Version you're drawing this allocation from.
			</p>

			<div class="error-box" id="fund_source_error">Failed to load fund sources. Please refresh the page.</div>
			<div class="error-box" id="budget_version_error">Failed to load budget versions. Please refresh the page.
			</div>

			<div id="baa_lines_container"></div>

			<div class="form-row" style="margin-top:20px;">
				<button type="button" id="baa_add_line_btn" class="btn-add-line" onclick="addBudgetAllocationLine()">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
					</svg>
					Add Budget Allocation
				</button>
			</div>

			<div id="baa_totals" class="baa-totals">
				<div class="baa-totals-row">
					<span class="baa-totals-label">Total Requested Amount</span>
					<span class="baa-totals-value" id="baa_total_requested">&#8369;0.00</span>
				</div>
				<div class="baa-totals-row">
					<span class="baa-totals-label">Total Cash Requirements</span>
					<span class="baa-totals-value" id="baa_total_allocated">&#8369;0.00</span>
				</div>
			</div>

			<!-- Legacy single-value mirrors — kept in sync with line 0 (FK columns
                 budget_version_id/cob_id/fund_source_id can only reference one row)
                 and with the summed totals (requested_amount/allocated_amount).
                 The full itemized breakdown is submitted separately as
                 `line_transaction` (jsonb) — see collectBaaLines() / buildPayload(). -->
			<input type="hidden" id="fund_source_id" name="fund_source_id" value="">
			<input type="hidden" id="budget_version_id" name="budget_version_id" value="">
			<input type="hidden" id="requested_amount_raw" name="requested_amount" value="">
			<input type="hidden" id="allocated_amount_raw" name="allocated_amount" value="">

			<style>
				.baa-line {
					position: relative;
					padding-right: 34px;
					margin-bottom: 16px;
				}

				.baa-remove-line {
					position: absolute;
					top: 4px;
					right: 0;
					width: 24px;
					height: 24px;
					border: 1px solid var(--afims-border, #E4ECF2);
					background: var(--afims-surface, #FFFFFF);
					color: var(--afims-text-disabled, #A9AAB8);
					border-radius: 6px;
					font-size: 16px;
					line-height: 1;
					cursor: pointer;
					transition: background .15s, color .15s, border-color .15s;
				}

				.baa-remove-line:hover {
					background: var(--afims-danger-soft, #FAE1E3);
					color: var(--afims-danger, #DC3545);
					border-color: var(--afims-danger-soft, #FAE1E3);
				}

				.baa-bv-info {
					grid-column: 1 / -1;
					margin-top: 8px;
					padding: 10px 12px;
					background: var(--afims-surface-subtle, #F4F7FA);
					border: 1px solid var(--afims-primary-soft, #E6EBF6);
					border-radius: 6px;
					font-size: 12px;
					line-height: 1.6;
				}

				.baa-bv-info-grid {
					display: grid;
					grid-template-columns: auto 1fr;
					gap: 2px 10px;
				}

				.baa-bv-info-label {
					color: var(--afims-text-muted, #62657E);
					font-weight: 500;
				}

				.baa-bv-info-value {
					color: var(--afims-heading, #000E3E);
				}

				.baa-bv-info-value.strong {
					font-weight: 600;
				}

				/* ── Budget Version list filters (COE) ─────────────────────────────
				   Same look as the "Filter the COB list" row of budget-version-form.php
				   (.bv-cf-*), here as .baa-cf-* inside each Budget Allocation Amount line. */
				.baa-bv-filters {
					grid-column: 1 / -1;
					margin-bottom: -10px;   /* .form-row's 24px row gap is more than this panel needs */
					/* a panel of its own, so a line's filters read as belonging to that line's fields */
					padding: 10px 12px 12px;
					background: var(--afims-surface-subtle, #F4F7FA);
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 8px;
				}
				.baa-bv-filters .baa-cf-head {
					display: flex;
					align-items: center;
					justify-content: space-between;
					min-height: 18px;
					margin-bottom: 8px;
				}
				.baa-bv-filters .baa-cf-title {
					font-size: 12px;
					font-weight: 600;
					color: var(--afims-text-muted, #62657E);
				}
				.baa-bv-filters .baa-cf-clear {
					display: none;
					background: none;
					border: none;
					padding: 0;
					font-size: 12px;
					font-weight: 600;
					color: var(--afims-primary-text, #0035A9);
					cursor: pointer;
				}
				.baa-bv-filters .baa-cf-clear:hover { text-decoration: underline; }
				.baa-bv-filters .baa-cf-grid {
					display: grid;
					grid-template-columns: 1.5fr 1fr 1fr;
					gap: 10px 12px;
				}
				@media (max-width: 720px) {
					.baa-bv-filters .baa-cf-grid { grid-template-columns: 1fr; }
				}
				.baa-bv-filters .baa-cf-field {
					display: flex;
					flex-direction: column;
					gap: 4px;
					min-width: 0;
				}
				.baa-bv-filters .baa-cf-label {
					margin: 0;
					font-size: 11px !important;
					font-weight: 600 !important;
					text-transform: none !important;
					letter-spacing: 0 !important;
					color: var(--afims-text-muted, #62657E) !important;
				}

				/* Filter controls: the two selects and the searchable chart-of-account input */
				.baa-bv-filters .baa-cf-ctl,
				.baa-bv-filters select.baa-cf-sel {
					box-sizing: border-box;
					width: 100%;
					height: 36px !important;
					min-height: 0 !important;
					padding: 4px 30px 4px 12px !important;
					font-size: 13px !important;
					color: var(--afims-text, #1A1B21);
					background-color: var(--afims-surface, #FFFFFF);
					border: 1px solid var(--afims-border-strong, #A9AAB8) !important;
					border-radius: 8px !important;
					text-overflow: ellipsis;
				}
				.baa-bv-filters .baa-cf-ctl:focus,
				.baa-bv-filters select.baa-cf-sel:focus {
					outline: none;
					border-color: var(--afims-primary, #0035A9) !important;
					box-shadow: 0 0 0 3px var(--ui-ring, rgba(0, 53, 169, .15));
				}
				/* A filter that is narrowing the list: primary outline + soft tint. */
				.baa-bv-filters .baa-cf-ctl.is-set,
				.baa-bv-filters select.baa-cf-sel.is-set {
					border-color: var(--afims-primary, #0035A9) !important;
					background-color: var(--afims-primary-soft, #E6EBF6);
					font-weight: 600;
				}
				.baa-bv-filters .baa-cf-ctl:disabled,
				.baa-bv-filters select.baa-cf-sel:disabled { cursor: not-allowed; opacity: .6; }

				/* Searchable chart-of-account combobox */
				.baa-bv-filters .baa-cf-combo { position: relative; }
				.baa-bv-filters .baa-cf-combo .baa-cf-ctl { padding-right: 54px !important; }
				.baa-bv-filters .baa-cf-chev {
					position: absolute;
					right: 10px;
					top: 50%;
					width: 14px;
					height: 14px;
					transform: translateY(-50%);
					color: var(--afims-text-muted, #62657E);
					pointer-events: none;
				}
				.baa-bv-filters .baa-cf-x {
					display: none;
					position: absolute;
					right: 28px;
					top: 50%;
					width: 20px;
					height: 20px;
					padding: 0;
					transform: translateY(-50%);
					align-items: center;
					justify-content: center;
					border: none;
					border-radius: 50%;
					background: transparent;
					color: var(--afims-text-muted, #62657E);
					cursor: pointer;
				}
				.baa-bv-filters .baa-cf-x:hover {
					background: var(--afims-border, #E4ECF2);
					color: var(--afims-text, #1A1B21);
				}
				.baa-bv-filters .baa-cf-x svg { width: 12px; height: 12px; }
				.baa-bv-filters .baa-cf-dd {
					display: none;
					position: absolute;
					top: calc(100% + 4px);
					left: 0;
					right: 0;
					z-index: 210;
					max-height: 240px;
					overflow-y: auto;
					padding: 4px;
					background: var(--afims-surface, #FFFFFF);
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 8px;
					box-shadow: 0 8px 24px rgba(16, 24, 40, .12);
				}
				.baa-bv-filters .baa-cf-opt {
					display: flex;
					align-items: baseline;
					justify-content: space-between;
					gap: 12px;
					padding: 8px 10px;
					border-radius: 6px;
					cursor: pointer;
				}
				.baa-bv-filters .baa-cf-opt:hover { background: var(--afims-surface-subtle, #F4F7FA); }
				.baa-bv-filters .baa-cf-opt.is-sel { background: var(--afims-primary-soft, #E6EBF6); }
				.baa-bv-filters .baa-cf-opt-t {
					min-width: 0;
					font-size: 13px;
					font-weight: 600;
					color: var(--afims-heading, #000E3E);
				}
				.baa-bv-filters .baa-cf-opt-c {
					white-space: nowrap;
					font-size: 11px;
					font-variant-numeric: tabular-nums;
					color: var(--afims-text-muted, #62657E);
				}
				.baa-bv-filters .baa-cf-empty {
					padding: 10px 12px;
					font-size: 13px;
					color: var(--afims-text-disabled, #A9AAB8);
				}

				.btn-add-line {
					display: inline-flex;
					align-items: center;
					gap: 6px;
					padding: 8px 14px;
					background: var(--afims-surface, #FFFFFF);
					color: var(--afims-heading, #000E3E);
					border: 1px dashed var(--afims-text-disabled, #A9AAB8);
					border-radius: 6px;
					font-size: 13px;
					font-weight: 600;
					cursor: pointer;
					transition: background .15s, border-color .15s;
				}

				.btn-add-line:hover {
					background: var(--afims-surface-subtle, #F4F7FA);
					border-color: var(--afims-heading-fill, #000E3E);
				}

				.baa-totals {
					margin-top: 16px;
					padding: 12px 16px;
					background: var(--afims-surface-subtle, #F4F7FA);
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 8px;
				}

				.baa-totals-row {
					display: flex;
					justify-content: space-between;
					align-items: center;
					padding: 3px 0;
					font-size: 13px;
				}

				.baa-totals-label {
					color: var(--afims-text-muted, #62657E);
					font-weight: 500;
				}

				.baa-totals-value {
					color: var(--afims-heading, #000E3E);
					font-weight: 700;
					font-family: 'IBM Plex Mono', monospace;
				}
			</style>
		</div>

		<!-- ── Requesting Office ──────────────────────────────────────── -->
		<div class="form-section">
			<h2 class="section-header">Requesting Office Information</h2>

			<div class="form-row">
				<div class="form-group">
					<label for="dept_search" class="form-label">
						Requesting Department
					</label>
					<div class="select-loading-wrapper is-loading" id="department_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="dept_search" class="form-input"
								placeholder="Search department&hellip;" autocomplete="off" disabled>
							<div id="dept_dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;
                                       background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-top:none;
                                       border-radius:0 0 6px 6px;max-height:220px;overflow-y:auto;
                                       box-shadow:0 4px 12px rgba(0,0,0,.1);">
							</div>
						</div>
						<input type="hidden" id="requesting_department_id" name="requesting_department_id" value="">
					</div>
					<div class="error-box" id="department_error">Failed to load departments. Please refresh the page.
					</div>
				</div>

				<div class="form-group">
					<label for="rc_search" class="form-label">Region / District Office</label>
					<div class="select-loading-wrapper is-loading" id="rc_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="rc_search" class="form-input"
								placeholder="Search region / district office&hellip;" autocomplete="off" disabled>
							<div id="rc_dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;
                                       background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-top:none;
                                       border-radius:0 0 6px 6px;max-height:220px;overflow-y:auto;
                                       box-shadow:0 4px 12px rgba(0,0,0,.1);">
							</div>
						</div>
						<input type="hidden" id="requesting_region_id" name="requesting_region_id" value="">
					</div>
					<div class="error-box" id="rc_error">Failed to load responsibility centers. Please refresh the page.
					</div>
				</div>
			</div>
		</div>

		<!-- ── Supporting Documents ───────────────────────────────────── -->
		<div class="form-section">
			<h2 class="section-header">Supporting Documents</h2>
			<p class="input-hint" style="margin-bottom:12px;">
				Drag &amp; drop a file (max 25MB) for each document, or add a link/reference instead.
			</p>

			<div id="supporting_docs_list">
				<!-- Rows injected by JS -->
			</div>

			<button type="button" class="btn btn-secondary" id="add_doc_btn"
				style="margin-top:10px;display:inline-flex;align-items:center;gap:6px;font-size:13px;"
				onclick="addSupportingDocRow()">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16"
					height="16">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
				</svg>
				Add Document
			</button>

			<style>
				.sdoc-row {
					display: grid;
					grid-template-columns: 1fr auto;
					gap: 10px;
					align-items: flex-end;
					margin-bottom: 10px;
				}

				.sdoc-row .form-input {
					font-size: 13px;
				}

				.sdoc-remove-btn {
					background: none;
					border: 1px solid var(--afims-danger-soft, #FAE1E3);
					border-radius: 8px;
					width: 40px;
					height: 40px;
					box-sizing: border-box;
					padding: 0;
					cursor: pointer;
					color: var(--afims-danger, #DC3545);
					display: flex;
					align-items: center;
					justify-content: center;
					flex-shrink: 0;
					transition: background .15s;
				}

				.sdoc-remove-btn:hover {
					background: var(--afims-danger-soft, #FAE1E3);
				}

				.sdoc-label {
					font-size: 12px;
					font-weight: 500;
					color: var(--afims-text, #1A1B21);
					margin-bottom: 4px;
					display: block;
				}

				.sdoc-dropzone {
					position: relative;
					border: 2px dashed var(--afims-border, #E4ECF2);
					border-radius: 8px;
					background: var(--afims-surface-subtle, #F4F7FA);
					padding: 12px;
					text-align: center;
					cursor: pointer;
					transition: border-color .15s, background .15s;
				}

				.sdoc-dropzone.drag-over {
					border-color: var(--afims-primary, #0035A9);
					background: var(--afims-primary-soft, #E6EBF6);
				}

				.sdoc-dropzone.uploading {
					pointer-events: none;
					opacity: .6;
				}

				.sdoc-dropzone input[type="file"] {
					position: absolute;
					inset: 0;
					opacity: 0;
					width: 100%;
					height: 100%;
					cursor: pointer;
				}

				.sdoc-dz-icon {
					width: 18px;
					height: 18px;
					color: var(--afims-text-disabled, #A9AAB8);
					margin: 0 auto 4px;
					display: block;
				}

				.sdoc-dz-title {
					font-size: 12.5px;
					color: var(--afims-text-muted, #62657E);
					margin: 0;
				}

				.sdoc-dz-hint {
					font-size: 11px;
					color: var(--afims-text-disabled, #A9AAB8);
				}

				.sdoc-file-chip {
					display: flex;
					align-items: center;
					gap: 10px;
					box-sizing: border-box;
					min-height: 40px;
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 8px;
					padding: 6px 8px 6px 10px;
					background: var(--afims-surface, #FFFFFF);
					font-size: 12.5px;
					transition: border-color .15s, background .15s;
				}

				.sdoc-file-chip:hover {
					background: var(--afims-surface-subtle, #F4F7FA);
				}

				.sdoc-file-ico {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					width: 26px;
					height: 26px;
					border-radius: 6px;
					flex-shrink: 0;
					background: var(--afims-primary-soft, #E6EBF6);
					color: var(--afims-primary, #0035A9);
				}

				.sdoc-file-ico svg {
					width: 13px;
					height: 13px;
				}

				.sdoc-file-name {
					flex: 0 1 auto;
					min-width: 0;
					font-weight: 500;
					color: var(--afims-heading, #000E3E);
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
					text-decoration: none;
				}

				/* Only a real link (http/https or a signed storage URL) is clickable. */
				a.sdoc-file-name[href] {
					color: var(--afims-primary, #0035A9);
					cursor: pointer;
				}

				a.sdoc-file-name[href]:hover {
					text-decoration: underline;
				}

				.sdoc-file-size {
					flex-shrink: 0;
					padding: 2px 8px;
					border-radius: 999px;
					background: var(--afims-surface-subtle, #F4F7FA);
					border: 1px solid var(--afims-border, #E4ECF2);
					color: var(--afims-text-muted, #62657E);
					font-size: 11px;
					white-space: nowrap;
				}

				.sdoc-file-size:empty {
					display: none;
				}

				.sdoc-file-actions {
					margin-left: auto;
					display: flex;
					align-items: center;
					gap: 6px;
					flex-shrink: 0;
				}

				.sdoc-file-open {
					display: inline-flex;
					align-items: center;
					gap: 5px;
					padding: 5px 12px;
					border-radius: 6px;
					border: 1px solid var(--afims-primary-soft, #E6EBF6);
					background: var(--afims-primary-soft, #E6EBF6);
					color: var(--afims-primary, #0035A9);
					font-size: 12px;
					font-weight: 600;
					text-decoration: none;
					cursor: pointer;
					transition: background .15s, color .15s;
				}

				.sdoc-file-open:hover {
					background: var(--afims-primary, #0035A9);
					border-color: var(--afims-primary, #0035A9);
					color: var(--afims-pure-white, #FFFFFF);
				}

				.sdoc-file-remove {
					background: none;
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 6px;
					padding: 5px 12px;
					color: var(--afims-text-muted, #62657E);
					cursor: pointer;
					font-size: 12px;
					font-weight: 500;
					font-family: inherit;
					transition: background .15s, color .15s;
				}

				.sdoc-file-remove:hover {
					background: var(--afims-surface-subtle, #F4F7FA);
					color: var(--afims-heading, #000E3E);
				}

				.sdoc-upload-note {
					font-size: 11.5px;
					color: var(--afims-warning-text, #7E6908);
					margin: 6px 0 0;
				}

				.sdoc-upload-note.error {
					color: var(--afims-danger, #DC3545);
				}
			</style>
		</div>

		<!-- ── Remarks ────────────────────────────────────────────────── -->
		<div class="form-section">
			<h2 class="section-header">Remarks / Notes</h2>
			<div class="form-row full-width">
				<div class="form-group">
					<label for="remarks" class="form-label">Additional Remarks or Notes</label>
					<textarea id="remarks" name="remarks" class="form-textarea"
						placeholder="Enter any additional remarks, notes, or special instructions..."></textarea>
				</div>
			</div>
		</div>

		<!-- Hidden Fields -->
		<input type="hidden" id="allocation_number" name="allocation_number"
			value="<?php echo htmlspecialchars($cba_number); ?>">
		<input type="hidden" id="created_by" name="created_by"
			value="<?php echo (int) $user_id; ?>">
		<input type="hidden" id="cob_id" name="cob_id" value="">
		<input type="hidden" id="multiple_node_id" name="multiple_node_id" value="">
		<input type="hidden" id="allocation_id" name="allocation_id"
			value="<?php echo $allocationId ? (int) $allocationId : ''; ?>">

		<!-- Form Actions -->
		<div class="form-actions">
			<button type="button" class="btn btn-secondary" onclick="window.history.back()">
				<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
					stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
						d="M10 19l-7-7m0 0l7-7m-7 7h18" />
				</svg>
				Cancel
			</button>

			<div class="btn-group">
				<button type="button" class="btn btn-draft" onclick="saveDraft()">
					<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
						stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
							d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
					</svg>
					Save as Draft
				</button>

				<button type="button" class="btn btn-print" onclick="printCBA()">
					<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
						stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
							d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
					</svg>
					Print Preview
				</button>

				<button type="submit" class="btn btn-primary" id="submit_cba_btn">
					<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
						stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
					</svg>
					<span id="submit_btn_label">Submit CBA</span>
				</button>
			</div>
		</div>
	</form>

	<!-- Information Card -->
	<div class="info-card">
		<div class="info-card-header">
			<svg class="info-card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
				stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
					d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
			</svg>
			<div class="info-card-content">
				<p class="info-card-title">Important Reminders</p>
				<ul class="info-list">
					<li>CBA requests above &#8369;50 Million require General Manager approval (RFA process)</li>
					<li>CBA requests below &#8369;50 Million follow streamlined LAA process</li>
					<li>Ensure all supporting documents are attached before submission</li>
					<li>Budget allocations must be validated against approved COB</li>
					<li>This certification is valid for the current fiscal year only</li>
				</ul>
			</div>
		</div>
	</div>
</div>

<script>
	const API_BASE = '<?php echo $apiBase; ?>';
	const CBA_TOKEN = window.ew?.API_JWT_TOKEN ?? '';

	// ── Edit mode ────────────────────────────────────────────────────────────────
	const CBA_CONFIG = {
		allocationId: <?php echo $allocationId ? json_encode($allocationId) : 'null'; ?> ,
	};
	const CBA_EDIT_MODE = !!CBA_CONFIG.allocationId;
	// created_at of the allocation being edited (set by populateEditForm). Stays
	// null in add mode — nothing is saved yet, so the RFA print date is today's.
	var CBA_CREATED_AT = null;

	/* ── API fetch helper (GET) ─────────────────────────────────────────────── */
	async function cba_apiFetch(url) {
		const res = await fetch(url, {
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				...(CBA_TOKEN ? {
					'X-Authorization': 'Bearer ' + CBA_TOKEN
				} : {}),
			},
		});
		if (!res.ok) throw new Error('HTTP ' + res.status);
		return res.json();
	}

	/* ── API fetch helper (POST) ────────────────────────────────────────────── */
	async function cba_apiFetchPost(url, body) {
		const res = await fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				...(CBA_TOKEN ? {
					'X-Authorization': 'Bearer ' + CBA_TOKEN
				} : {}),
			},
			body: JSON.stringify(body),
		});
		if (!res.ok) {
			const err = await res.json().catch(() => ({}));
			throw new Error(err.message || 'Server error ' + res.status);
		}
		return res.json();
	}

	/* ── API fetch helper (PATCH — used for updates in edit mode) ──────────── */
	async function cba_apiFetchPatch(url, body) {
		const res = await fetch(url, {
			method: 'PATCH',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				...(CBA_TOKEN ? {
					'X-Authorization': 'Bearer ' + CBA_TOKEN
				} : {}),
			},
			body: JSON.stringify(body),
		});
		if (!res.ok) {
			const err = await res.json().catch(() => ({}));
			throw new Error(err.message || 'Server error ' + res.status);
		}
		return res.json();
	}

	// ── Shared state ─────────────────────────────────────────────────────────────
	let ALL_PROJECTS = [];
	const CURRENT_YEAR = <?php echo (int) date('Y'); ?> ;

	// ── Per-type series counters (populated by refreshAllocationCount) ────────────
	var CBA_SERIES = {
		CBA: 1,
		RFA: 1,
		LAA: 1
	};

	// ── Boot ─────────────────────────────────────────────────────────────────────
	document.addEventListener('DOMContentLoaded', function() {
		generateCBANumber();
		addBudgetAllocationLine(); // render the first Budget Allocation Amount row

		document.getElementById('allocation_type').addEventListener('change', generateCBANumber);
		document.getElementById('allocation_type').addEventListener('change', toggleCbaIdRow);
		document.getElementById('allocation_type').addEventListener('change', updateSubmitLabel);
		document.getElementById('allocation_type').addEventListener('change',
			applyBudgetVersionVisibilityForDocType);
		document.getElementById('allocation_type').addEventListener('change', baaResyncBvSource);
		document.getElementById('document_type').addEventListener('change', applyAllocationTypeOptionsForDocType);
		document.getElementById('document_type').addEventListener('change', generateCBANumber); // RFA code depends on document type
		document.getElementById('document_type').addEventListener('change', toggleDepartmentByDocType);
		document.getElementById('document_type').addEventListener('change', applyDeptWhitelistForDocType);
		document.getElementById('document_type').addEventListener('change', applyCategoryTypeForDocType);
		document.getElementById('document_type').addEventListener('change', applyFundSourceForDocType);
		document.getElementById('document_type').addEventListener('change',
			applyProgramProjectVisibilityForDocType);
		document.getElementById('document_type').addEventListener('change',
			applyBudgetVersionVisibilityForDocType);
		document.getElementById('document_type').addEventListener('change', function() {
			baaRefreshBvLabels();
		});
		document.getElementById('document_type').addEventListener('change', baaResyncBvSource);

		// ── Pre-populate from URL params (?type=RFA|LAA&parent_cba_id=N) ──
		// Skipped entirely in edit mode — editing an existing record should never
		// trigger the "generate RFA/LAA from parent CBA" autofill flow.
		const _urlParams = new URLSearchParams(window.location.search);
		const _presetType = CBA_EDIT_MODE ? '' : (_urlParams.get('type') || '').toUpperCase();
		const _presetCbaId = CBA_EDIT_MODE ? '' : (_urlParams.get('parent_cba_id') || '').trim();

		if (_presetType === 'RFA' || _presetType === 'LAA') {
			document.getElementById('allocation_type').value = _presetType;
			generateCBANumber();
			toggleCbaIdRow();
		}

		// ── Pre-populate Document Type from URL param (?document_type=CO|COE) ──
		// Used by the "Create" button on allocation-variance.php (CO) and
		// coe-allocation-variance.php (COE) so the form opens with the correct
		// document type already selected instead of defaulting to blank.
		// IMPORTANT: this must happen BEFORE the one-and-only toggleDepartmentByDocType()
		// call below — calling it twice (once before the doc type is known, once after)
		// fires two concurrent /department/all requests that race each other, and
		// whichever resolves last wins, sometimes leaving the CO/COE-aware (MAIN-inclusive)
		// result clobbered by the earlier mode-less one.
		const _presetDocType = CBA_EDIT_MODE ? '' : (_urlParams.get('document_type') || '').toUpperCase();
		if (_presetDocType === 'CO' || _presetDocType === 'COE') {
			document.getElementById('document_type').value = _presetDocType;
			generateCBANumber(); // RFA code depends on document type (COE → 57)
			baaRefreshBvLabels(); // .value set programmatically — no 'change' event fires
			applyBudgetVersionVisibilityForDocType();
		}

		// document_type is final here (URL preset applied) — hide CFA if it is CO.
		applyAllocationTypeOptionsForDocType();

		// Single authoritative Region/District (+ Requesting Department) load —
		// document_type is already set above if it was preset via URL, so this
		// fires exactly one /department/all request with the correct filters.
		var _rcLoadPromise = toggleDepartmentByDocType();
		applyProgramProjectVisibilityForDocType();
		if (_presetDocType === 'CO' || _presetDocType === 'COE') {
			applyCategoryTypeForDocType();
			applyFundSourceForDocType();
		}

		// Run all dropdown loaders; after they settle, apply CBA autofill if requested
		// NOTE: Budget Versions are no longer loaded here — each row only loads
		// its own list once that row's Source of Fund is selected (see
		// onBaaFundSourceChange / baaLoadBudgetVersionsByFundSource).
		var _dropdownLoaders = Promise.allSettled([
			loadFiscalYears(),
			loadFundSources(),
			loadPrograms(),
			loadAllProjects(),
			_rcLoadPromise,
			loadDepartments(),
			refreshAllocationCount(),
			loadCategories(),
			loadCategoryTypes(),
		]);

		// Hide loading overlay once all dropdowns have settled
		_dropdownLoaders.then(function() {
			var overlay = document.getElementById('cba_loading_overlay');
			if (overlay) {
				overlay.classList.add('cba-fade-out');
				setTimeout(function() {
					overlay.style.display = 'none';
				}, 320);
			}
		});

		if ((_presetType === 'RFA' || _presetType === 'LAA') && _presetCbaId) {
			// Wire CBA combobox events before loading
			if (!document.getElementById('cba_search').dataset.wired) {
				cbaWireEvents();
				document.getElementById('cba_search').dataset.wired = '1';
			}

			// Load parent CBA list and dropdowns in parallel, then autofill
			Promise.all([
				loadParentCBAs(),
				_dropdownLoaders,
			]).then(async function() {
				var cba = CBA_DATA.find(function(c) {
					return String(c.allocation_id) === String(_presetCbaId);
				});
				if (!cba) return;

				// 1. Select the CBA combobox
				var cbaLabel = cba.allocation_number || ('#' + cba.allocation_id);
				cbaSelect(cba.allocation_id, cbaLabel);

				// 2. Fiscal year
				if (cba.fiscal_year) {
					document.getElementById('fiscal_year').value = cba.fiscal_year;
					generateCBANumber();
				}

				// 3. Budget Allocation Amount lines + Budget Version — one row per
				//    line_transaction entry, carried over from the parent CBA's
				//    own itemized breakdown (same shape as the _hasLineTransaction
				//    branch in populateEditForm() below), so an RFA/LAA generated
				//    from a multi-line CBA starts with the same multiple Source of
				//    Fund / Budget Version rows instead of collapsing to one.
				//    Falls back to the parent's flat fund_source_id/
				//    budget_version_id/requested_amount/allocated_amount (row 0
				//    only) for legacy single-line CBAs with no line_transaction.
				var cbaLines = Array.isArray(cba.line_transaction) && cba.line_transaction.length > 0 ?
					cba.line_transaction : [{
						fund_source_id: cba.fund_source_id,
						budget_version_id: cba.budget_version_id,
						requested_amount: cba.requested_amount,
						allocated_amount: cba.allocated_amount,
					}];

				while (BAA_ROWS.length < cbaLines.length) addBudgetAllocationLine();

				for (var cli = 0; cli < cbaLines.length; cli++) {
					var cLine = cbaLines[cli];
					var cIdx = BAA_ROWS[cli];
					if (cIdx === undefined) continue;

					var cFsPromise = Promise.resolve();
					var cFsSelect = document.getElementById('fund_source_id_' + cIdx);
					if (cFsSelect && cLine.fund_source_id) {
						cFsSelect.value = cLine.fund_source_id;
						cFsPromise = onBaaFundSourceChange(cIdx);
					}

					baaSetAmount(cIdx, 'requested', cLine.requested_amount);
					baaSetAmount(cIdx, 'allocated', cLine.allocated_amount);

					if (cLine.budget_version_id) {
						try {
							await cFsPromise;
						} catch (e) {
							/* ignore */
						}
						var cBvData = BAA_BV_DATA[cIdx] || [];
						var cBv = cBvData.find(function(b) {
							return String(b.budget_version_id) === String(cLine
								.budget_version_id);
						});
						if (cBv) {
							// #document_type isn't written until step 4 below, so pass
							// cba.document_type explicitly — the RFA/LAA being generated
							// inherits it, and every row must show the right field
							// (Fund ID vs. Program Source) immediately, not just once
							// step 4 catches up.
							baaBvSelect(
								cIdx,
								cBv.budget_version_id,
								cBv.cob_id,
								baaBvDisplayName(cBv, cba.document_type),
								cBv.version_number || cBv.vnum || '',
								cBv.version_name || cBv.vname || '',
								cBv.status
							);
						}
					}
				}
				recomputeBaaTotals();

				// 4. Document type — set it first, then await the RC reload it triggers
				//    (toggleDepartmentByDocType calls loadResponsibilityCenters async,
				//     so we must await that reload before setting requesting_region_id)
				if (cba.document_type) {
					var docTypeEl = document.getElementById('document_type');
					docTypeEl.value = cba.document_type;
					generateCBANumber(); // RFA code depends on document type (COE → 57)
					// Replicate toggleDepartmentByDocType logic but await the RC load
					var deptGroup = document.getElementById('requesting_department_id').closest(
						'.form-group');
					var rcMode = (cba.document_type === 'CO') ? 'DISTRICT' : undefined;
					if (cba.document_type === 'CO') {
						deptGroup.style.display = 'none';
						deptClearSelection();
					} else {
						deptGroup.style.display = '';
					}
					await loadResponsibilityCenters(rcMode);
					// document_type was set programmatically (no 'change' event fired),
					// so re-apply the CO auto-select/lock behavior manually.
					applyAllocationTypeOptionsForDocType();
					applyDeptWhitelistForDocType();
					applyCategoryTypeForDocType();
					applyFundSourceForDocType();
					applyBudgetVersionVisibilityForDocType();
					baaRefreshBvLabels();
				}

				// 4b. Category Type — only override if not already locked to CO
				// NOTE: /budget_allocation/all nests this as category_type{category_type_id,...}
				// (reshapeFetchAllsRow() overwrites the flat FK column), not a flat category_type_id.
				var cbaCategoryTypeId = cba.category_type && cba.category_type.category_type_id !=
					null ?
					cba.category_type.category_type_id : null;
				if (cbaCategoryTypeId != null && document.getElementById('document_type').value !==
					'CO') {
					document.getElementById('category_type_id').value = cbaCategoryTypeId;
				}

				// 5/6. Requesting department / region — use the parent CBA's own
				//      nested requesting_department/requesting_region objects
				//      directly (fetchAlls() now resolves these via a plain JOIN
				//      to uac.departments, same as fetchById()) instead of
				//      looking the id up in DEPT_ALL_DATA/RC_ALL_DATA. Those two
				//      lists are filtered down to a fixed whitelist for the
				//      combobox's own search results (cbaIsWhitelistedDept()),
				//      so a parent CBA whose department/region falls outside
				//      that whitelist would otherwise silently fail to
				//      auto-select here even though it's a valid saved value.
				if (cba.requesting_department_id && cba.requesting_department) {
					deptSelect(cba.requesting_department_id, deptLabelFor(cba.requesting_department));
				}
				if (cba.requesting_region_id && cba.requesting_region) {
					rcSelect(cba.requesting_region_id, rcLabelFor(cba.requesting_region));
				}

				// 7. Amounts — already populated per-row in step 3 above (from
				//    line_transaction, or the flat fallback).

				// 8. Program (searchable combobox — find in loaded PROG_ALL_DATA)
				if (cba.program_id && Array.isArray(PROG_ALL_DATA) && PROG_ALL_DATA.length) {
					var prog = PROG_ALL_DATA.find(function(p) {
						return parseInt(p.program_id, 10) === parseInt(cba.program_id, 10);
					});
					if (prog) {
						progSelect(prog.program_id, prog.program_name, prog.program_code || '');
					}
				}

				// 9. Project (independent of program — find in ALL_PROJECTS)
				if (cba.project_id && Array.isArray(ALL_PROJECTS) && ALL_PROJECTS.length) {
					var proj = ALL_PROJECTS.find(function(p) {
						return parseInt(p.project_id, 10) === parseInt(cba.project_id, 10);
					});
					if (proj) {
						projSelect(proj.project_id, proj.project_name, proj.project_code || '');
					}
				}

				// 10. Purpose / description
				if (cba.project_description) {
					document.getElementById('project_description').value = cba.project_description;
				}

				// 11. Remarks
				if (cba.remarks) {
					document.getElementById('remarks').value = cba.remarks;
				}

				// 11b. Is this for bidding?
				var isBiddingEl = document.getElementById('is_bidding');
				if (isBiddingEl) isBiddingEl.checked = !!cba.is_bidding;

				// 12. Category (searchable combobox — find in loaded CAT_ALL_DATA)
				// NOTE: /budget_allocation/all nests this as category{category_id,...}
				// (reshapeFetchAllsRow() overwrites the flat FK column), not a flat category.
				var cbaCategoryId = cba.category && cba.category.category_id != null ? cba.category
					.category_id : null;
				if (cbaCategoryId != null && Array.isArray(CAT_ALL_DATA) && CAT_ALL_DATA.length) {
					var cat = CAT_ALL_DATA.find(function(c) {
						return parseInt(c.budget_allocation_category_id, 10) === parseInt(
							cbaCategoryId, 10);
					});
					if (cat) {
						catSelect(cat.budget_allocation_category_id, cat
							.budget_allocation_category_name);
					}
				}

				// 13. Supporting Documents — carry over files already attached to the parent CBA
				if (cba.multiple_node_id) {
					try {
						var cbaReadJson = await cba_apiFetch(
							`${API_BASE}/budget_allocation/read/${cba.allocation_id}`);
						var attachments = cbaReadJson.success ? (cbaReadJson.data?.file_attachments ||
							[]) : [];
						attachments
							.filter(function(a) {
								return !a.deleted_at && a.file_meta && !a.file_meta.deleted_at;
							})
							.forEach(function(a) {
								addExistingSupportingDocRow(a.file_meta.document_name, a.file_meta.link,
									a.file_meta.view_url, 'Carried over from parent CBA');
							});
					} catch (err) {
						console.error('[cba autofill] Failed to load parent supporting documents:',
							err);
					}
				}

				// 14. Apply COE visibility rules last, now that program/project/amount
				//     autofill above has already run.
				applyProgramProjectVisibilityForDocType();
			});
		}
		// ─────────────────────────────────────────────────────────────────

		updateSubmitLabel();
		setupFormValidation();

		if (CBA_EDIT_MODE) {
			// Load the existing allocation once dropdowns have settled, then
			// populate every field from it (see populateEditForm below).
			_dropdownLoaders.then(function() {
				loadExistingAllocation(CBA_CONFIG.allocationId);
			});
			// "Save as Draft" only makes sense for a brand-new record — hide it
			// in edit mode so an in-progress allocation can't be reset to DRAFT.
			var draftBtn = document.querySelector('.btn-draft');
			if (draftBtn) draftBtn.style.display = 'none';
		} else {
			// Default supporting document rows (create mode only)
			addSupportingDocRow('Memo Proposal');
		}
	});

	// ── Edit mode: load + populate an existing allocation ─────────────────────────
	// GET /budget_allocation/read/{id} → same nested response shape used
	// throughout this file (program{}, project{}, requesting_department{},
	// requesting_region{}, category_type{}, file_attachments[]).
	async function loadExistingAllocation(id) {
		try {
			var json = await cba_apiFetch(`${API_BASE}/budget_allocation/read/${id}`);
			if (!json.success || !json.data) throw new Error(json.message || 'Failed to load allocation.');
			await populateEditForm(json.data);
		} catch (err) {
			console.error('[loadExistingAllocation]', err);
			alert('Failed to load allocation #' + id + ': ' + err.message);
		} finally {
			var overlay = document.getElementById('cba_loading_overlay');
			if (overlay) {
				overlay.classList.add('cba-fade-out');
				setTimeout(function() {
					overlay.style.display = 'none';
				}, 320);
			}
		}
	}

	async function populateEditForm(a) {
		var g = function(id) {
			return document.getElementById(id);
		};

		CBA_CREATED_AT = a.created_at || null;

		// 1. Allocation type — set first since it drives visibility of several
		//    other fields (CBA parent row, category, doc type).
		g('allocation_type').value = a.allocation_type || 'CBA';
		toggleCbaIdRow();
		updateSubmitLabel();

		// 2. Allocation number — locked to the value loaded from the server
		//    (generateCBANumber() is a no-op in edit mode). If the existing
		//    record has no allocation_number (null/empty — e.g. an old or
		//    incomplete record), generate one now using the same per-type
		//    CBA/RFA/LAA format as create mode, so it's never left blank and
		//    gets carried into the update payload (buildPayload() reads
		//    straight from this hidden field).
		var allocNumber = a.allocation_number || buildAllocationNumber(a.allocation_type || 'CBA', a.document_type);
		g('allocation_number').value = allocNumber;
		var fsTextForDisplay = '';
		if (a.fund_source_id && Array.isArray(FUND_SOURCE_DATA) && FUND_SOURCE_DATA.length) {
			var fsRec = FUND_SOURCE_DATA.find(function(fs) {
				return String(fs.fund_source_id) === String(a.fund_source_id);
			});
			fsTextForDisplay = fsRec ? (fsRec.source_name + ' (' + fsRec.source_code + ')') : '';
		}
		g('cba_number_display').textContent = fsTextForDisplay ?
			(allocNumber + ' / ' + fsTextForDisplay) :
			allocNumber;

		// 3. cba_id (parent, for RFA/LAA)
		if (a.allocation_type === 'RFA' || a.allocation_type === 'LAA') {
			if (!g('cba_search').dataset.wired) {
				cbaWireEvents();
				g('cba_search').dataset.wired = '1';
			}
			if (a.cba_id) {
				try {
					await loadParentCBAs();
					cbaCloseDropdown();
					var parent = CBA_DATA.find(function(c) {
						return String(c.allocation_id) === String(a.cba_id);
					});
					var label = parent ? (parent.allocation_number || ('#' + a.cba_id)) :
						(a.cba_id != null ? ('#' + a.cba_id) : '');
					if (label) cbaSelect(a.cba_id, label);
				} catch (err) {
					console.error('[populateEditForm] parent CBA load failed:', err);
				}
			}
		}

		// 4. Fiscal year
		if (a.fiscal_year) g('fiscal_year').value = a.fiscal_year;

		// 5. Fund source (row 0) → triggers that row's Budget Version list load.
		//    Skipped when a.line_transaction carries the full multi-row breakdown
		//    (step 9/10 below handles every row, including row 0, in that case).
		var _bvLoadPromise = Promise.resolve();
		var _hasLineTransaction = Array.isArray(a.line_transaction) && a.line_transaction.length > 0;
		if (!_hasLineTransaction && a.fund_source_id) {
			var fsSelect0 = g('fund_source_id_0');
			if (fsSelect0) {
				fsSelect0.value = a.fund_source_id;
				var fsOpt0 = fsSelect0.options[fsSelect0.selectedIndex];
				var fsSourceName0 = (fsOpt0 && fsOpt0.value) ? (fsOpt0.dataset.sourceName || '') : '';
				var mirror0 = g('fund_source_id');
				if (mirror0) {
					mirror0.value = fsSelect0.value;
					mirror0.dataset.label = (fsOpt0 && fsOpt0.value) ? fsOpt0.textContent : '';
				}
				_bvLoadPromise = baaLoadBudgetVersionsByFundSource(0, fsSourceName0);
			}
		}

		// 6. Document type — set first, then await the RC reload it triggers
		if (a.document_type) {
			var docTypeEl = g('document_type');
			docTypeEl.value = a.document_type;
			var deptGroup = g('requesting_department_id').closest('.form-group');
			var rcMode = (a.document_type === 'CO') ? 'DISTRICT' : undefined;
			if (a.document_type === 'CO') {
				deptGroup.style.display = 'none';
				deptClearSelection();
			} else {
				deptGroup.style.display = '';
			}
			await loadResponsibilityCenters(rcMode);
			applyAllocationTypeOptionsForDocType();
			applyDeptWhitelistForDocType();
			applyCategoryTypeForDocType();
			applyFundSourceForDocType();
			applyBudgetVersionVisibilityForDocType();
			baaRefreshBvLabels();
		}

		// 7. Category Type — only override if not already locked to CO
		// NOTE: fetchById() nests this as category_type{category_type_id,...}
		// (reshapeFetchAllsRow() overwrites the flat FK column), not a flat category_type_id.
		var aCategoryTypeId = a.category_type && a.category_type.category_type_id != null ?
			a.category_type.category_type_id : null;
		if (aCategoryTypeId != null && g('document_type').value !== 'CO') {
			g('category_type_id').value = aCategoryTypeId;
		}

		// 8. Requesting department / region — use the API's own nested
		//    requesting_department/requesting_region objects directly
		//    (budget_allocationServices.php::fetchById() already resolves
		//    these via a plain JOIN to uac.departments) instead of looking the
		//    id up in DEPT_ALL_DATA/RC_ALL_DATA. Those two lists are filtered
		//    down to a fixed whitelist for the combobox's own search results
		//    (cbaIsWhitelistedDept()), so an existing record whose department/
		//    region falls outside that whitelist would otherwise silently fail
		//    to auto-select here even though it's a perfectly valid saved value.
		if (a.requesting_department_id && a.requesting_department) {
			deptSelect(a.requesting_department_id, deptLabelFor(a.requesting_department));
		}
		if (a.requesting_region_id && a.requesting_region) {
			rcSelect(a.requesting_region_id, rcLabelFor(a.requesting_region));
		}

		// 9/10. Budget Allocation Amount lines + Budget Version (CBA, and RFA/LAA
		// carried over from their parent CBA — see the "Generate RFA/LAA" preset
		// autofill flow in the boot handler above)
		if (_hasLineTransaction) {
			// Multi-row record — repopulate one Budget Allocation Amount row per
			// line_transaction entry (row 0 already exists from boot).
			while (BAA_ROWS.length < a.line_transaction.length) addBudgetAllocationLine();
			for (var li = 0; li < a.line_transaction.length; li++) {
				var line = a.line_transaction[li];
				var idx = BAA_ROWS[li];
				if (idx === undefined) continue;

				var lineBvPromise = Promise.resolve();
				var lineFsSelect = g('fund_source_id_' + idx);
				if (lineFsSelect && line.fund_source_id) {
					lineFsSelect.value = line.fund_source_id;
					var lineFsOpt = lineFsSelect.options[lineFsSelect.selectedIndex];
					var lineFsName = (lineFsOpt && lineFsOpt.value) ? (lineFsOpt.dataset.sourceName || '') : '';
					if (idx === 0) {
						var lineMirror = g('fund_source_id');
						if (lineMirror) {
							lineMirror.value = lineFsSelect.value;
							lineMirror.dataset.label = (lineFsOpt && lineFsOpt.value) ? lineFsOpt.textContent : '';
						}
					}
					lineBvPromise = baaLoadBudgetVersionsByFundSource(idx, lineFsName);
				}

				baaSetAmount(idx, 'requested', line.requested_amount);
				baaSetAmount(idx, 'allocated', line.allocated_amount);

				if (line.budget_version_id) {
					try {
						await lineBvPromise;
					} catch (e) {
						/* ignore */
					}
					var lineBvData = BAA_BV_DATA[idx] || [];
					var lineBv = lineBvData.find(function(b) {
						return String(b.budget_version_id) === String(line.budget_version_id);
					});
					if (lineBv) {
						baaBvSelect(
							idx, lineBv.budget_version_id, lineBv.cob_id,
							baaBvDisplayName(lineBv),
							lineBv.version_number || lineBv.vnum || '',
							lineBv.version_name || lineBv.vname || '',
							lineBv.status
						);
					}
				}
			}
		} else {
			// Single flat record (no line_transaction yet) — populate row 0 only.
			if (a.budget_version_id) {
				try {
					await _bvLoadPromise;
				} catch (e) {
					/* ignore */
				}
				var bv = (BAA_BV_DATA[0] || []).find(function(b) {
					return String(b.budget_version_id) === String(a.budget_version_id);
				});
				if (bv) {
					baaBvSelect(
						0,
						bv.budget_version_id,
						bv.cob_id,
						baaBvDisplayName(bv),
						bv.version_number || bv.vnum || '',
						bv.version_name || bv.vname || '',
						bv.status
					);
				}
			}
			baaSetAmount(0, 'requested', a.requested_amount);
			baaSetAmount(0, 'allocated', a.allocated_amount);
		}
		recomputeBaaTotals();

		// 11. Program
		if (a.program_id && Array.isArray(PROG_ALL_DATA) && PROG_ALL_DATA.length) {
			var prog = PROG_ALL_DATA.find(function(p) {
				return parseInt(p.program_id, 10) === parseInt(a.program_id, 10);
			});
			if (prog) progSelect(prog.program_id, prog.program_name, prog.program_code || '');
		}

		// 12. Project
		if (a.project_id && Array.isArray(ALL_PROJECTS) && ALL_PROJECTS.length) {
			var proj = ALL_PROJECTS.find(function(p) {
				return parseInt(p.project_id, 10) === parseInt(a.project_id, 10);
			});
			if (proj) projSelect(proj.project_id, proj.project_name, proj.project_code || '');
		}

		// 13. Purpose / description / remarks
		if (a.project_description) g('project_description').value = a.project_description;
		if (a.remarks) g('remarks').value = a.remarks;
		if (g('is_bidding')) g('is_bidding').checked = !!a.is_bidding;

		// 14. Category (budget_allocation_category) — API may return either a
		//     flat `category` id or a nested `category: {category_id, ...}` /
		//     `budget_allocation_category: {...}` object depending on endpoint
		//     version; handle both shapes defensively.
		var categoryId = null;
		if (a.category && typeof a.category === 'object') {
			categoryId = a.category.category_id ?? a.category.budget_allocation_category_id ?? null;
		} else if (a.category != null) {
			categoryId = a.category;
		} else if (a.budget_allocation_category && typeof a.budget_allocation_category === 'object') {
			categoryId = a.budget_allocation_category.budget_allocation_category_id ?? null;
		}
		if (categoryId != null && Array.isArray(CAT_ALL_DATA) && CAT_ALL_DATA.length) {
			var cat = CAT_ALL_DATA.find(function(c) {
				return parseInt(c.budget_allocation_category_id, 10) === parseInt(categoryId, 10);
			});
			if (cat) catSelect(cat.budget_allocation_category_id, cat.budget_allocation_category_name);
		}

		// 17. Existing supporting documents — replace the blank default row
		var list = g('supporting_docs_list');
		if (list) list.innerHTML = '';
		var attachments = a.file_attachments || [];
		var activeAttachments = attachments.filter(function(att) {
			return !att.deleted_at && att.file_meta && !att.file_meta.deleted_at;
		});
		if (activeAttachments.length) {
			activeAttachments.forEach(function(att) {
				addExistingSupportingDocRow(att.file_meta.document_name, att.file_meta.link,
					att.file_meta.view_url);
			});
		} else {
			addSupportingDocRow('Memo Proposal');
		}
		if (a.multiple_node_id && g('multiple_node_id')) {
			g('multiple_node_id').value = a.multiple_node_id;
		}

		// 18. Misc hidden fields carried through on update
		g('cob_id').value = a.cob_id || '';

		// 19. Apply COE visibility rules last, now that program/project/amount
		//     autofill above has already run.
		applyProgramProjectVisibilityForDocType();
	}

	// ── Loader helpers ────────────────────────────────────────────────────────────
	function setLoading(wrapperId, loading) {
		document.getElementById(wrapperId).classList.toggle('is-loading', loading);
	}

	function showError(errorId) {
		document.getElementById(errorId).classList.add('visible');
	}

	// ── 1. Fiscal Years ───────────────────────────────────────────────────────────
	async function loadFiscalYears() {
		var select = document.getElementById('fiscal_year');
		try {
			var json = await cba_apiFetch(`${API_BASE}/fiscal_year/all?period_type=ANNUAL`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			select.innerHTML = '<option value="">Select fiscal year</option>';
			var sorted = json.data.slice().sort((a, b) => parseInt(a.fiscal_period_id, 10) - parseInt(b
				.fiscal_period_id, 10));
			sorted.forEach(function(row) {
				var opt = document.createElement('option');
				opt.value = row.fiscal_period_id;
				opt.textContent = row.period_name;
				opt.dataset.fiscalYear = row.fiscal_year;
				if (parseInt(row.fiscal_year, 10) === CURRENT_YEAR) opt.selected = true;
				select.appendChild(opt);
			});
			select.disabled = false;
		} catch (err) {
			console.error('[loadFiscalYears]', err);
			select.innerHTML = '<option value="">Could not load</option>';
			showError('fiscal_year_error');
		} finally {
			setLoading('fiscal_year_wrapper', false);
		}
	}

	// ── 2. Fund Sources ───────────────────────────────────────────────────────────
	async function loadFundSources() {
		try {
			var json = await cba_apiFetch(`${API_BASE}/fund_source/all`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			FUND_SOURCE_DATA = json.data;
			BAA_ROWS.forEach(function(idx) {
				populateFundSourceSelect(document.getElementById('fund_source_id_' + idx));
			});
			generateCBANumber();
			// Apply COE auto-select/lock in case document_type is already set
			// (e.g. preset before this loader resolved).
			applyFundSourceForDocType();
		} catch (err) {
			console.error('[loadFundSources]', err);
			BAA_ROWS.forEach(function(idx) {
				var select = document.getElementById('fund_source_id_' + idx);
				if (select) select.innerHTML = '<option value="">Could not load</option>';
			});
			showError('fund_source_error');
		}
	}

	// ── NEW: Category Type  →  GET /coe-category-type/all ─────────────────────────
	var CAT_TYPE_ALL_DATA = [];

	async function loadCategoryTypes() {
		var select = document.getElementById('category_type_id');
		try {
			var json = await cba_apiFetch(`${API_BASE}/coe-category-type/all`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			CAT_TYPE_ALL_DATA = json.data;
			renderCategoryTypeOptions();
			select.disabled = false;

			// Apply CO auto-select/lock (or COE exclusion) in case document_type
			// is already set (e.g. preset from URL params before this loader resolved).
			applyCategoryTypeForDocType();
		} catch (err) {
			console.error('[loadCategoryTypes]', err);
			select.innerHTML = '<option value="">Could not load</option>';
			showError('category_type_error');
		} finally {
			setLoading('category_type_wrapper', false);
		}
	}

	// Rebuilds the <option> list, optionally excluding a category_code (e.g. 'CO' for COE docs).
	function renderCategoryTypeOptions(excludeCode) {
		var select = document.getElementById('category_type_id');
		var currentValue = select.value;

		var rows = CAT_TYPE_ALL_DATA;
		if (excludeCode) {
			rows = rows.filter(function(ct) {
				return (ct.category_code || '').toUpperCase() !== excludeCode.toUpperCase();
			});
		}

		select.innerHTML = '<option value="">Select Expense Type</option>';
		rows.forEach(function(ct) {
			var opt = document.createElement('option');
			opt.value = ct.category_type_id;
			opt.textContent = ct.category_name + ' (' + ct.category_code + ')';
			opt.dataset.categoryCode = ct.category_code;
			select.appendChild(opt);
		});

		// Re-select the previous value if it still exists in the filtered list;
		// otherwise clear it (e.g. "CO" was selected but doc type became COE).
		var stillExists = rows.some(function(ct) {
			return String(ct.category_type_id) === String(currentValue);
		});
		select.value = stillExists ? currentValue : '';
	}

	// ── Auto-select + lock Category Type to "CO" when Document Type = CO ──────────
	// Also hides the "CO — Capital Outlay" option entirely when Document Type = COE.
	function applyCategoryTypeForDocType() {
		var docType = document.getElementById('document_type').value;
		var select = document.getElementById('category_type_id');
		var hint = document.getElementById('category_type_hint');
		if (!select) return;

		// COE has no "Infrastructure" Category — drop a stale pick made under
		// another document type (the dropdown list itself is filtered by
		// catVisibleData()).
		if (docType === 'COE' && isInfrastructureCategorySelected()) {
			var catHidden = document.getElementById('budget_allocation_category_id');
			catHidden.value = '';
			catHidden.dataset.label = '';
			var catSearch = document.getElementById('cat_search');
			if (catSearch) catSearch.value = '';
		}

		if (docType === 'CO') {
			renderCategoryTypeOptions(); // show all options, including CO
			var coType = CAT_TYPE_ALL_DATA.find(function(ct) {
				return (ct.category_code || '').toUpperCase() === 'CO';
			});
			if (coType) {
				select.value = coType.category_type_id;
			}
			select.disabled = true;
			if (hint) hint.textContent = 'Locked to Capital Outlay for CO document type.';
		} else if (docType === 'COE') {
			renderCategoryTypeOptions('CO'); // hide the CO option for COE docs
			var wrapper = document.getElementById('category_type_wrapper');
			if (!wrapper || !wrapper.classList.contains('is-loading')) {
				select.disabled = false;
			}
			if (hint) hint.textContent = 'Budget line classification (PS / MOOE / EO)';
		} else {
			renderCategoryTypeOptions(); // no document type selected — show all
			var wrapper2 = document.getElementById('category_type_wrapper');
			if (!wrapper2 || !wrapper2.classList.contains('is-loading')) {
				select.disabled = false;
			}
			if (hint) hint.textContent = 'Budget line classification (CO / PS / MOOE / EO)';
		}
	}

	// ── Auto-select + lock Source of Fund to "Corporate Receipts" for COE docs ────
	// COE no longer forces/locks the source of fund to "Corporate Receipts" — the
	// user is free to pick any fund source regardless of document type. This just
	// makes sure every Budget Allocation Amount row's select is unlocked once
	// fund sources have finished loading.
	function applyFundSourceForDocType() {
		if (!FUND_SOURCE_DATA.length) return;
		document.querySelectorAll('.baa-fund-source').forEach(function(select) {
			select.disabled = false;
		});
	}

	// True when the selected Category (budget_allocation_category) is
	// "Infrastructure" — looked up by id in CAT_ALL_DATA rather than trusting
	// the hidden field's dataset.label, since catClearSelection() only clears
	// .value (not dataset.label) while the user is typing a new search query.
	function isInfrastructureCategorySelected() {
		var hidden = document.getElementById('budget_allocation_category_id');
		var id = hidden ? hidden.value : '';
		if (!id) return false;
		var cat = (CAT_ALL_DATA || []).find(function(c) {
			return String(c.budget_allocation_category_id) === String(id);
		});
		return !!cat && (cat.budget_allocation_category_name || '').trim().toLowerCase() === 'infrastructure';
	}

	// ── Hide Program/Project (not Purpose/Description) + Cash Requirements for
	// COE docs; for CO docs, Program/Project only shows when Category =
	// Infrastructure (hidden for every other category) ─────────────────────────
	// COE allocations never carry a program/project, but they still need the
	// Purpose / Description ("particulars") field — only the Program/Project
	// row is hidden, the section itself (and its Purpose/Description row) stays
	// visible. Neither program_id/project_id nor allocated_amount carry a
	// `required` attribute, so hiding them just clears out any stale values so
	// they aren't sent.
	function applyProgramProjectVisibilityForDocType() {
		var docType = document.getElementById('document_type').value;
		var progProjRow = document.getElementById('program_project_row');
		var sectionTitle = document.getElementById('program_project_section_title');
		var hideProgProj = docType === 'COE' || (docType === 'CO' && !isInfrastructureCategorySelected());

		if (hideProgProj) {
			if (progProjRow) progProjRow.style.display = 'none';
			if (sectionTitle) sectionTitle.textContent = 'Purpose Details';

			if (typeof progClearSelection === 'function') progClearSelection();
			if (typeof projClearSelection === 'function') projClearSelection();
			var progSearch = document.getElementById('prog_search');
			if (progSearch) progSearch.value = '';
			var projSearch = document.getElementById('proj_search');
			if (projSearch) projSearch.value = '';
		} else {
			if (progProjRow) progProjRow.style.display = '';
			if (sectionTitle) sectionTitle.textContent = 'Program and Project Details';
		}

		// Cash Requirements is hidden for COE only, on every row — Requested
		// Amount just flows into the next open grid cell (.form-row is auto-fit,
		// so it naturally fills the gap; no explicit span needed). Clear out any
		// stale allocated_amount value so it isn't sent.
		if (docType === 'COE') {
			document.querySelectorAll('.baa-line').forEach(function(row) {
				var cashGroup = row.querySelector('.baa-cash-group');
				if (cashGroup) cashGroup.style.display = 'none';
				var allocDisplay = row.querySelector('.baa-allocated-amount');
				if (allocDisplay) {
					allocDisplay.value = '';
					baaSyncRaw(allocDisplay);
				}
			});
			recomputeBaaTotals();
		} else {
			document.querySelectorAll('.baa-line').forEach(function(row) {
				var cashGroup = row.querySelector('.baa-cash-group');
				if (cashGroup) cashGroup.style.display = '';
			});
		}
	}

	// ── 3. Programs (searchable combobox) ─────────────────────────────────────────
	var PROG_ALL_DATA = [];
	var PROG_DEBOUNCE = null;
	var PROG_LAST_QUERY = null;

	async function loadPrograms() {
		var searchEl = document.getElementById('prog_search');
		var wrapper = document.getElementById('program_wrapper');
		try {
			var json = await cba_apiFetch(`${API_BASE}/program/all`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			PROG_ALL_DATA = json.data;
			searchEl.disabled = false;
			searchEl.placeholder = 'Search program…';
			progSetupSearch();
		} catch (err) {
			console.error('[loadPrograms]', err);
			showError('program_error');
		} finally {
			if (wrapper) wrapper.classList.remove('is-loading');
		}
	}

	function progRenderDropdown(data) {
		var dd = document.getElementById('prog_dropdown');
		if (!data.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}
		dd.innerHTML = data.map(function(prog) {
			var catStr = prog.program_category != null ? String(prog.program_category) : '';
			return '<div class="bv-item"' +
				' data-id="' + prog.program_id + '"' +
				' data-code="' + (prog.program_code || '').replace(/"/g, '&quot;') + '"' +
				' data-name="' + (prog.program_name || '').replace(/"/g, '&quot;') + '"' +
				' data-cat="' + catStr.replace(/"/g, '&quot;') + '"' +
				'>' +
				'<div class="bv-item-name">' + (prog.program_name || '') + '</div>' +
				'<div class="bv-item-sub">' + (prog.program_code || '') + '</div>' +
				'</div>';
		}).join('');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				progSelect(parseInt(this.dataset.id, 10), this.dataset.name, this.dataset.code);
			});
		});
	}

	function progSelect(id, name, code) {
		var hidden = document.getElementById('program_id');
		hidden.value = id;
		hidden.dataset.label = name + ' (' + code + ')';

		document.getElementById('prog_search').value = name + ' (' + code + ')';
		document.getElementById('prog_dropdown').style.display = 'none';
		document.getElementById('progi_code').textContent = code || '—';
		document.getElementById('prog_info').style.display = 'block';
	}

	function progClearSelection() {
		var hidden = document.getElementById('program_id');
		hidden.value = '';
		hidden.dataset.label = '';
		document.getElementById('prog_info').style.display = 'none';
	}

	function progSetupSearch() {
		var searchEl = document.getElementById('prog_search');
		var dd = document.getElementById('prog_dropdown');

		searchEl.addEventListener('input', function() {
			var q = this.value.trim();
			progClearSelection();
			clearTimeout(PROG_DEBOUNCE);
			PROG_LAST_QUERY = q;

			if (!q) {
				progRenderDropdown(PROG_ALL_DATA);
				cba_apiFetch(API_BASE + '/program/all')
					.then(function(json) {
						if (json.success && Array.isArray(json.data)) {
							PROG_ALL_DATA = json.data;
							if (!document.getElementById('prog_search').value.trim()) {
								progRenderDropdown(PROG_ALL_DATA);
							}
						}
					})
					.catch(function(err) {
						console.error('[prog empty reload]', err);
					});
				return;
			}

			dd.innerHTML = '<div class="prog-searching">Searching&hellip;</div>';
			dd.style.display = 'block';

			PROG_DEBOUNCE = setTimeout(async function() {
				var capturedQuery = q;
				try {
					var json = await cba_apiFetch(API_BASE + '/program/all?program_name=' +
						encodeURIComponent(capturedQuery));
					if (PROG_LAST_QUERY !== capturedQuery) return;
					if (json.success && Array.isArray(json.data)) {
						PROG_ALL_DATA = json.data;
						progRenderDropdown(json.data);
					}
				} catch (err) {
					console.error('[prog search]', err);
					if (PROG_LAST_QUERY !== capturedQuery) return;
					dd.innerHTML =
						'<div style="padding:10px 14px;color:var(--afims-danger, #DC3545);font-size:13px;">Search failed. Try again.</div>';
					dd.style.display = 'block';
				}
			}, 250);
		});

		searchEl.addEventListener('focus', function() {
			if (PROG_ALL_DATA.length && !document.getElementById('program_id').value) {
				progRenderDropdown(PROG_ALL_DATA);
			}
			cba_apiFetch(API_BASE + '/program/all')
				.then(function(json) {
					if (json.success && Array.isArray(json.data)) {
						PROG_ALL_DATA = json.data;
						if (!document.getElementById('program_id').value && !searchEl.value.trim()) {
							progRenderDropdown(PROG_ALL_DATA);
						}
					}
				})
				.catch(function(err) {
					console.error('[prog focus refresh]', err);
				});
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── NEW: Category (searchable combobox) ───────────────────────────────────────
	var CAT_ALL_DATA = [];
	var CAT_DEBOUNCE = null;

	async function loadCategories() {
		var searchEl = document.getElementById('cat_search');
		var wrapper = document.getElementById('category_wrapper');
		try {
			var json = await cba_apiFetch(API_BASE + '/budget-allocation-category-type/all');
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			CAT_ALL_DATA = json.data;
			searchEl.disabled = false;
			searchEl.placeholder = 'Search category…';
			catSetupSearch();
		} catch (err) {
			console.error('[loadCategories]', err);
			showError('category_error');
		} finally {
			if (wrapper) wrapper.classList.remove('is-loading');
		}
	}

	// Categories offered in the dropdown — Document Type = COE never uses
	// "Infrastructure", so it is dropped from the list for COE docs. CAT_ALL_DATA
	// itself stays complete (isInfrastructureCategorySelected() and the edit/
	// autofill lookups resolve ids against it).
	function catVisibleData() {
		if (document.getElementById('document_type').value !== 'COE') return CAT_ALL_DATA;
		return CAT_ALL_DATA.filter(function(c) {
			return (c.budget_allocation_category_name || '').trim().toLowerCase() !== 'infrastructure';
		});
	}

	function catRenderDropdown(data) {
		var dd = document.getElementById('cat_dropdown');
		if (!data.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}
		dd.innerHTML = data.map(function(cat) {
			return '<div class="bv-item"' +
				' data-id="' + cat.budget_allocation_category_id + '"' +
				' data-name="' + (cat.budget_allocation_category_name || '').replace(/"/g, '&quot;') + '"' +
				'>' +
				'<div class="bv-item-name">' + (cat.budget_allocation_category_name || '') + '</div>' +
				'</div>';
		}).join('');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				catSelect(parseInt(this.dataset.id, 10), this.dataset.name);
			});
		});
	}

	function catSelect(id, name) {
		var hidden = document.getElementById('budget_allocation_category_id');
		hidden.value = id;
		hidden.dataset.label = name;

		document.getElementById('cat_search').value = name;
		document.getElementById('cat_dropdown').style.display = 'none';

		applyProgramProjectVisibilityForDocType();
	}

	function catClearSelection() {
		document.getElementById('budget_allocation_category_id').value = '';
		applyProgramProjectVisibilityForDocType();
	}

	function catSetupSearch() {
		var searchEl = document.getElementById('cat_search');
		var dd = document.getElementById('cat_dropdown');

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			catClearSelection();
			clearTimeout(CAT_DEBOUNCE);

			if (!q) {
				catRenderDropdown(catVisibleData());
				return;
			}

			CAT_DEBOUNCE = setTimeout(function() {
				var filtered = catVisibleData().filter(function(c) {
					return (c.budget_allocation_category_name || '').toLowerCase().includes(q);
				});
				catRenderDropdown(filtered);
			}, 150);
		});

		searchEl.addEventListener('focus', function() {
			if (!document.getElementById('budget_allocation_category_id').value) {
				catRenderDropdown(catVisibleData());
			}
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── 4. Projects (searchable combobox, filtered by program) ────────────────────
	var PROJ_CURRENT_DATA = [];

	async function loadAllProjects() {
		var wrapper = document.getElementById('project_wrapper');
		var searchEl = document.getElementById('proj_search');
		var hint = document.getElementById('project_hint');
		try {
			var json = await cba_apiFetch(`${API_BASE}/projects/all`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			ALL_PROJECTS = json.data;
			searchEl.disabled = false;
			searchEl.placeholder = 'Search project…';
			if (hint) hint.textContent = ALL_PROJECTS.length + ' project(s) available.';
			projSetupSearch(ALL_PROJECTS);
		} catch (err) {
			console.error('[loadAllProjects]', err);
			showError('project_error');
		} finally {
			if (wrapper) wrapper.classList.remove('is-loading');
		}
	}

	function projRenderDropdown(filtered) {
		var dd = document.getElementById('proj_dropdown');
		if (!filtered.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}
		dd.innerHTML = filtered.map(function(proj) {
			return '<div class="bv-item"' +
				' data-id="' + proj.project_id + '"' +
				' data-code="' + (proj.project_code || '').replace(/"/g, '&quot;') + '"' +
				' data-name="' + (proj.project_name || '').replace(/"/g, '&quot;') + '"' +
				'>' +
				'<div class="bv-item-name">' + (proj.project_name || '') + '</div>' +
				'<div class="bv-item-sub">' + (proj.project_code || '') + '</div>' +
				'</div>';
		}).join('');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				projSelect(parseInt(this.dataset.id, 10), this.dataset.name, this.dataset.code);
			});
		});
	}

	function projSelect(id, name, code) {
		var hidden = document.getElementById('project_id');
		hidden.value = id;
		hidden.dataset.label = name + ' (' + code + ')';

		document.getElementById('proj_search').value = name + ' (' + code + ')';
		document.getElementById('proj_dropdown').style.display = 'none';
		document.getElementById('project_hint').textContent = 'Selected: ' + name;
	}

	function projClearSelection() {
		var hidden = document.getElementById('project_id');
		hidden.value = '';
		hidden.dataset.label = '';
	}

	function projSetupSearch(data) {
		PROJ_CURRENT_DATA = data;
		var searchEl = document.getElementById('proj_search');
		var dd = document.getElementById('proj_dropdown');

		var newSearch = searchEl.cloneNode(true);
		searchEl.parentNode.replaceChild(newSearch, searchEl);
		searchEl = newSearch;

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			projClearSelection();
			if (!q) {
				dd.style.display = 'none';
				return;
			}
			var filtered = PROJ_CURRENT_DATA.filter(function(proj) {
				return (proj.project_name || '').toLowerCase().includes(q) ||
					(proj.project_code || '').toLowerCase().includes(q);
			}).slice(0, 50);
			projRenderDropdown(filtered);
		});

		searchEl.addEventListener('focus', function() {
			projRenderDropdown(PROJ_CURRENT_DATA.slice(0, 50));
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── 5. Budget Allocation Amount lines (multiple Source of Fund / Budget
	// Version / Requested Amount / Cash Requirements rows) ─────────────────────
	// Each line is independent; row 0's fund source / budget version mirror into
	// the legacy singular hidden fields (fund_source_id / budget_version_id /
	// cob_id — FK columns can only hold one value), and every row's amounts are
	// summed into requested_amount_raw / allocated_amount_raw. The full itemized
	// breakdown is sent separately as `line_transaction` (jsonb) — see
	// collectBaaLines() / buildPayload().
	var FUND_SOURCE_DATA = []; // cached fund source list — shared across all rows
	var BAA_ROWS = []; // ordered array of live row idx values
	var BAA_ROW_SEQ = -1;
	var BAA_BV_DATA = {}; // idx -> budget version array for that row's fund source
	var BAA_BV_WIRED = {}; // idx -> bool (search listeners wired once per row)
	var BAA_BV_REQ_TOKEN = {}; // idx -> token, guards stale async responses when a row's fund source changes quickly
	// The Budget Version ("Fund ID" / "Budget Version (COB)") list is restricted to
	// COBs under these components (shared.component_type ids), sent as repeated
	// &component_id=… params on /budget_version_v2/all:
	//   Document Type CO  + Allocation Type RFA / LAA → components 1 and 2
	//   Document Type COE (any Allocation Type)       → component 3 only
	// Every other combination is not component-filtered.
	var BAA_CO_RFA_LAA_COMPONENT_IDS = [1, 2];
	var BAA_COE_COMPONENT_IDS = [3];
	var BAA_BV_GATE = {}; // idx -> "1,2" / "3" / "" — the component filter the row's list was last loaded with

	function baaComponentIdsForBv() {
		var type = document.getElementById('allocation_type').value;
		var docType = document.getElementById('document_type').value;
		if (docType === 'CO' && (type === 'RFA' || type === 'LAA')) return BAA_CO_RFA_LAA_COMPONENT_IDS;
		if (docType === 'COE') return BAA_COE_COMPONENT_IDS;
		return [];
	}

	// Document Type / Allocation Type decide which component filter a row's list
	// gets, but the list is only fetched when the fund source is picked — so when
	// either changes afterwards, re-load every row (with a fund source already
	// chosen) whose filter just changed. Re-loading clears that row's selection,
	// which no longer belongs to the new list.
	function baaResyncBvSource() {
		var key = baaComponentIdsForBv().join(',');
		BAA_ROWS.forEach(function(idx) {
			var fsSel = document.getElementById('fund_source_id_' + idx);
			if (!fsSel || !fsSel.value) return;
			if (BAA_BV_GATE[idx] === key) return;
			onBaaFundSourceChange(idx);
		});
	}

	function baaRowTemplate(idx) {
		return '' +
			'<div class="baa-line form-row" data-idx="' + idx + '">' +
			// COE only — narrows this line's Budget Version list (see baaFiltersPopulate)
			'<div class="baa-bv-filters" id="baa_cf_wrap_' + idx + '" style="display:none;">' +
			'<div class="baa-cf-head">' +
			'<span class="baa-cf-title">Filter the Budget Version list</span>' +
			'<button type="button" class="baa-cf-clear" id="baa_cf_clear_' + idx + '">Clear filters</button>' +
			'</div>' +
			'<div class="baa-cf-grid">' +
			'<div class="baa-cf-field">' +
			'<label class="baa-cf-label" for="baa_cf_account_search_' + idx + '">Chart of Account</label>' +
			'<div class="baa-cf-combo">' +
			'<input type="text" id="baa_cf_account_search_' + idx + '" class="baa-cf-ctl" ' +
			'placeholder="Select a source of fund first&hellip;" autocomplete="off" disabled>' +
			'<button type="button" class="baa-cf-x" id="baa_cf_account_x_' + idx + '" ' +
			'aria-label="Clear chart of account filter" tabindex="-1"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>' +
			'<svg class="baa-cf-chev" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>' +
			'<div id="baa_cf_account_dd_' + idx + '" class="baa-cf-dd"></div>' +
			'</div>' +
			'<input type="hidden" id="baa_cf_account_' + idx + '" value="">' +
			'</div>' +
			'<div class="baa-cf-field">' +
			'<label class="baa-cf-label" for="baa_cf_dept_' + idx + '">Requesting Department</label>' +
			'<select id="baa_cf_dept_' + idx + '" class="form-select baa-cf-sel" disabled>' +
			'<option value="">All Requesting Departments</option>' +
			'</select>' +
			'</div>' +
			'<div class="baa-cf-field">' +
			'<label class="baa-cf-label" for="baa_cf_dist_' + idx + '">Requesting District</label>' +
			'<select id="baa_cf_dist_' + idx + '" class="form-select baa-cf-sel" disabled>' +
			'<option value="">All Requesting Districts</option>' +
			'</select>' +
			'</div>' +
			'</div>' +
			'</div>' +
			'<div class="form-group">' +
			'<label for="fund_source_id_' + idx +
			'" class="form-label">Type of Fund <span class="required">*</span></label>' +
			'<select id="fund_source_id_' + idx + '" class="form-select baa-fund-source" data-idx="' + idx +
			'" required disabled>' +
			'<option value="">Loading fund sources&hellip;</option>' +
			'</select>' +
			'</div>' +
			'<div class="form-group baa-bv-group">' +
			'<label for="bv_search_' + idx + '" class="form-label">' +
			'<span id="bv_field_label_' + idx + '">' + baaBvFieldLabelText() + '</span>' +
			' <span class="required baa-bv-required" id="bv_required_mark_' + idx + '" style="display:none;">*</span>' +
			'</label>' +
			'<div style="position:relative;">' +
			'<input type="text" id="bv_search_' + idx + '" class="form-input baa-bv-search" data-idx="' + idx + '" ' +
			'placeholder="Select a source of fund first&hellip;" autocomplete="off" disabled>' +
			'<div id="bv_dropdown_' + idx + '" class="baa-bv-dropdown" ' +
			'style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;' +
			'background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-top:none;' +
			'border-radius:0 0 6px 6px;max-height:220px;overflow-y:auto;' +
			'box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>' +
			'</div>' +
			'<input type="hidden" id="budget_version_id_' + idx + '" value="">' +
			'<input type="hidden" id="line_cob_id_' + idx + '" value="">' +
			'<p class="input-hint" id="budget_version_hint_' + idx +
			'">Select a source of fund to filter available budget versions.</p>' +
			'</div>' +
			'<div class="form-group has-prefix baa-req-group">' +
			'<label for="requested_amount_' + idx +
			'" class="form-label">Requested Amount <span class="required">*</span></label>' +
			'<span class="input-prefix">&#8369;</span>' +
			'<input type="text" id="requested_amount_' + idx +
			'" class="form-input amt-input baa-requested-amount" data-idx="' + idx +
			'" placeholder="0.00" required autocomplete="off">' +
			'<input type="hidden" id="requested_amount_' + idx + '_raw" value="">' +
			'</div>' +
			'<div class="form-group has-prefix baa-cash-group">' +
			'<label for="allocated_amount_' + idx + '" class="form-label">Cash Requirements</label>' +
			'<span class="input-prefix">&#8369;</span>' +
			'<input type="text" id="allocated_amount_' + idx +
			'" class="form-input amt-input baa-allocated-amount" data-idx="' + idx +
			'" placeholder="0.00" autocomplete="off">' +
			'<input type="hidden" id="allocated_amount_' + idx + '_raw" value="">' +
			'</div>' +
			'<button type="button" class="baa-remove-line" data-idx="' + idx +
			'" title="Remove this budget allocation">&times;</button>' +
			'<div class="baa-bv-info" id="bv_info_' + idx + '" style="display:none;">' +
			'<div class="baa-bv-info-grid">' +
			'<span class="baa-bv-info-label" id="bvi_progsrc_label_' + idx + '">' + baaBvLabelText() + '</span>' +
			'<span id="bvi_program_source_' + idx + '" class="baa-bv-info-value strong"></span>' +
			'<span class="baa-bv-info-label">Version</span>' +
			'<span id="bvi_version_' + idx + '" class="baa-bv-info-value"></span>' +
			'<span class="baa-bv-info-label" id="bvi_req_dept_label_' + idx +
			'" style="display:none;">Requesting Department</span>' +
			'<span id="bvi_req_dept_' + idx + '" class="baa-bv-info-value" style="display:none;"></span>' +
			'<span class="baa-bv-info-label" id="bvi_req_region_label_' + idx +
			'" style="display:none;">Requesting Region</span>' +
			'<span id="bvi_req_region_' + idx + '" class="baa-bv-info-value" style="display:none;"></span>' +
			'<span class="baa-bv-info-label">Status</span>' +
			'<span id="bvi_status_' + idx + '" class="baa-bv-info-value"></span>' +
			'<span class="baa-bv-info-label">Fund Balance</span>' +
			'<span id="bvi_fund_balance_' + idx + '" class="baa-bv-info-value strong"></span>' +
			'</div>' +
			'<div class="error-box" id="bv_balance_warning_' + idx + '"></div>' +
			'</div>' +
			'</div>';
	}

	function addBudgetAllocationLine() {
		var idx = ++BAA_ROW_SEQ;
		BAA_ROWS.push(idx);

		var container = document.getElementById('baa_lines_container');
		var holder = document.createElement('div');
		holder.innerHTML = baaRowTemplate(idx);
		var rowEl = holder.firstElementChild;
		container.appendChild(rowEl);

		populateFundSourceSelect(document.getElementById('fund_source_id_' + idx));
		document.getElementById('fund_source_id_' + idx).addEventListener('change', function() {
			onBaaFundSourceChange(idx);
		});
		wireAmtInput(document.getElementById('requested_amount_' + idx));
		wireAmtInput(document.getElementById('allocated_amount_' + idx));
		baaFiltersWire(idx);
		rowEl.querySelector('.baa-remove-line').addEventListener('click', function() {
			removeBudgetAllocationLine(idx);
		});

		// Keep a newly-added row consistent with the current doc/allocation type.
		var docType = document.getElementById('document_type');
		if (docType && docType.value === 'COE') {
			var cashGroup = rowEl.querySelector('.baa-cash-group');
			if (cashGroup) cashGroup.style.display = 'none';
		}
		applyBudgetVersionVisibilityForDocType();
		updateBaaRemoveButtons();
		recomputeBaaTotals();
		return idx;
	}

	function removeBudgetAllocationLine(idx) {
		if (BAA_ROWS.length <= 1) return; // always keep at least one line
		var rowEl = document.querySelector('.baa-line[data-idx="' + idx + '"]');
		if (rowEl) rowEl.remove();
		BAA_ROWS = BAA_ROWS.filter(function(i) {
			return i !== idx;
		});
		delete BAA_BV_DATA[idx];
		delete BAA_BV_WIRED[idx];
		delete BAA_BV_REQ_TOKEN[idx];
		delete BAA_BV_GATE[idx];
		updateBaaRemoveButtons();
		recomputeBaaTotals();
	}

	function updateBaaRemoveButtons() {
		var onlyOne = BAA_ROWS.length <= 1;
		document.querySelectorAll('.baa-remove-line').forEach(function(btn) {
			btn.style.visibility = onlyOne ? 'hidden' : 'visible';
		});
	}

	function populateFundSourceSelect(select) {
		if (!select) return;
		var currentValue = select.value;
		select.innerHTML = '<option value="">Select fund source</option>';
		FUND_SOURCE_DATA.forEach(function(fs) {
			var opt = document.createElement('option');
			opt.value = fs.fund_source_id;
			opt.textContent = fs.source_name + ' (' + fs.source_code + ')';
			// Store the raw source_name separately — this is the value sent to
			// /budget_version_v2/all?fund_source=<source_name>, NOT the
			// "name (code)" display text.
			opt.dataset.sourceName = fs.source_name || '';
			opt.dataset.sourceCode = fs.source_code || '';
			select.appendChild(opt);
		});
		if (currentValue) select.value = currentValue;
		select.disabled = false;
	}

	function onBaaFundSourceChange(idx) {
		var select = document.getElementById('fund_source_id_' + idx);
		var opt = select.options[select.selectedIndex];
		var sourceName = (opt && opt.value) ? (opt.dataset.sourceName || '') : '';

		if (idx === 0) {
			var mirror = document.getElementById('fund_source_id');
			mirror.value = select.value;
			mirror.dataset.label = (opt && opt.value) ? opt.textContent : '';
			generateCBANumber();
		}

		return baaLoadBudgetVersionsByFundSource(idx, sourceName);
	}

	// ── Budget Version  →  GET /budget_version_v2/all?fund_source=<source_name> ──
	// Gated behind Source of Fund per row: a row's search stays disabled until
	// its own fund source is selected, and every change re-fetches filtered to it.
	function baaBvSetDisabledState(idx, message) {
		var searchEl = document.getElementById('bv_search_' + idx);
		var hint = document.getElementById('budget_version_hint_' + idx);
		var dd = document.getElementById('bv_dropdown_' + idx);
		BAA_BV_DATA[idx] = [];
		delete BAA_BV_HINT[idx];
		baaFiltersReset(idx, true);
		baaBvClearSelection(idx);
		if (searchEl) {
			searchEl.value = '';
			searchEl.disabled = true;
			searchEl.placeholder = message;
		}
		if (hint) hint.textContent = message;
		if (dd) dd.style.display = 'none';
	}

	async function baaLoadBudgetVersionsByFundSource(idx, sourceName) {
		var searchEl = document.getElementById('bv_search_' + idx);
		var hint = document.getElementById('budget_version_hint_' + idx);
		if (!searchEl) return; // row was removed before this resolved

		if (!sourceName) {
			baaBvSetDisabledState(idx, 'Select a source of fund first…');
			return;
		}

		BAA_BV_REQ_TOKEN[idx] = (BAA_BV_REQ_TOKEN[idx] || 0) + 1;
		var myToken = BAA_BV_REQ_TOKEN[idx];
		searchEl.disabled = true;
		searchEl.value = '';
		searchEl.placeholder = 'Loading budget versions…';
		baaFiltersReset(idx, true);
		baaBvClearSelection(idx);

		try {
			// CO + RFA / LAA → &component_id=1&component_id=2; COE → &component_id=3.
			// The API matches them against each version's parent COB
			// (bv.cob_id → corporate_operating_budget.component_id).
			// baaResyncBvSource() re-loads the row if Document Type / Allocation
			// Type change afterwards.
			var componentIds = baaComponentIdsForBv();
			BAA_BV_GATE[idx] = componentIds.join(',');
			var componentQs = componentIds.map(function(id) {
				return '&component_id=' + id;
			}).join('');
			var json = await cba_apiFetch(
				`${API_BASE}/budget_version_v2/all?fund_source=${encodeURIComponent(sourceName)}&current_year=true${componentQs}`
			);
			if (myToken !== BAA_BV_REQ_TOKEN[idx]) return; // superseded by a newer fund source change on this row
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			BAA_BV_DATA[idx] = json.data;
			searchEl = document.getElementById('bv_search_' + idx); // row may have re-rendered while awaiting
			if (!searchEl) return;
			searchEl.disabled = false;
			searchEl.placeholder = 'Search version name or ' + baaBvLabelText().toLowerCase() + '…';
			var scopeNote = componentIds.length ? ' (component ' + componentIds.join(', ') + ')' : '';
			BAA_BV_HINT[idx] = { source: sourceName, scope: scopeNote };
			baaFiltersPopulate(idx); // COE: the filter options come from this list
			baaBvUpdateHint(idx); // "n version(s) available for …" (or "n of m … match the filters")
			baaBvSetupSearch(idx);
		} catch (err) {
			if (myToken !== BAA_BV_REQ_TOKEN[idx]) return;
			console.error('[baaLoadBudgetVersionsByFundSource]', err);
			if (searchEl) searchEl.placeholder = 'Could not load';
			showError('budget_version_error');
		}
	}

	// Budget Version display name — for document_type = CO this shows the
	// linked Fund ID's name (bv.fund_tracking.name, from fmd.fund_tracking, via
	// cob.fund_id) instead of the Program Source, since CO allocations are
	// organized by Fund ID rather than by program. For document_type = COE it
	// shows the Account Code name (bv.chart_of_account.account_title — the COA
	// entry the parent COB is coded to), falling back to the Program Source when
	// the COB has no account code. $docTypeOverride lets callers
	// that already know the target document_type (but haven't written it into
	// the #document_type select yet — e.g. the "Generate RFA/LAA from parent
	// CBA" autofill, which sets the Budget Version before the Document Type
	// field) resolve the correct name without waiting on the DOM.
	function baaBvDisplayName(bv, docTypeOverride) {
		var docType = docTypeOverride || (document.getElementById('document_type') || {}).value;
		if (docType === 'CO') return (bv.fund_tracking && bv.fund_tracking.name) || '';
		if (docType === 'COE' && bv.chart_of_account) {
			var coaName = bv.chart_of_account.account_title || bv.chart_of_account.account_code || '';
			if (coaName) return coaName;
		}
		return bv.program_source ? (bv.program_source.program_name || '') : '';
	}

	// Requesting Department / Region names of a budget version's parent COB —
	// /budget_version_v2/all nests the COB under cob_details[0] and resolves its
	// requesting_department / requesting_district FKs (uac.departments) into
	// *_info objects. Empty strings when the COB doesn't have one set.
	function baaBvRequesting(bv) {
		var cob = (bv && Array.isArray(bv.cob_details)) ? bv.cob_details[0] : null;
		var dept = cob && cob.requesting_department_info;
		var region = cob && cob.requesting_district_info;
		return {
			department: (dept && dept.department_name) || '',
			region: (region && region.department_name) || ''
		};
	}

	// Row field label (above the search/select input itself) — "Fund ID" for CO,
	// "Budget Version (COB)" otherwise.
	function baaBvFieldLabelText(docTypeOverride) {
		var docType = docTypeOverride || (document.getElementById('document_type') || {}).value;
		return docType === 'CO' ? 'Fund ID' : 'Budget Version (COB)';
	}

	// Info-box sub-label (Program Source vs. Fund ID vs. Account Code) — shown
	// right above the selected Budget Version's resolved name in each row's info box.
	function baaBvLabelText(docTypeOverride) {
		var docType = docTypeOverride || (document.getElementById('document_type') || {}).value;
		if (docType === 'CO') return 'Fund ID';
		if (docType === 'COE') return 'Account Code';
		return 'Program Source';
	}

	// Re-labels every row's field label + info box (Program Source vs. Fund ID)
	// and, for a row that already has a selection, re-renders its displayed
	// value/search text using the field that matches the (possibly just-
	// changed) document_type. Wired to #document_type's 'change' event — also
	// called manually wherever document_type.value is set programmatically (no
	// 'change' event fires for that).
	function baaRefreshBvLabels(docTypeOverride) {
		var label = baaBvLabelText(docTypeOverride);
		var fieldLabel = baaBvFieldLabelText(docTypeOverride);
		BAA_ROWS.forEach(function(idx) {
			var fieldLabelEl = document.getElementById('bv_field_label_' + idx);
			if (fieldLabelEl) fieldLabelEl.textContent = fieldLabel;

			var labelEl = document.getElementById('bvi_progsrc_label_' + idx);
			if (labelEl) labelEl.textContent = label;

			var bvIdEl = document.getElementById('budget_version_id_' + idx);
			var infoEl = document.getElementById('bvi_program_source_' + idx);
			var searchEl = document.getElementById('bv_search_' + idx);
			if (!bvIdEl || !bvIdEl.value || !infoEl) return;

			var bv = (BAA_BV_DATA[idx] || []).find(function(b) {
				return String(b.budget_version_id) === String(bvIdEl.value);
			});
			if (!bv) return;

			var name = baaBvDisplayName(bv, docTypeOverride);
			infoEl.textContent = name || '—';
			if (searchEl) searchEl.value = '[V' + bv.version_number + '] ' + name;
		});
	}

	// ── Budget Version (COB) required for RFA / LAA ────────────────────────────
	// RFA and LAA must always be tied to a Budget Version, on every Budget
	// Allocation Amount line. Enforced on submit by validateBudgetVersionsRequired()
	// — this only drives the "*" marker, which is also what keeps the CBA + CO
	// case (field hidden, nothing to require) in sync.
	function isBudgetVersionRequired() {
		var type = document.getElementById('allocation_type').value;
		return type === 'RFA' || type === 'LAA';
	}

	// Clears the red error state set by validateBudgetVersionsRequired() on a row.
	function baaBvClearError(idx) {
		var searchEl = document.getElementById('bv_search_' + idx);
		if (!searchEl) return;
		var group = searchEl.closest('.form-group');
		if (group) group.classList.remove('has-error');
		searchEl.classList.remove('error');
	}

	// Submit-time check: every line must have a Budget Version when the
	// allocation type is RFA / LAA. Checks the hidden budget_version_id_{idx}
	// (the actual selection), not the search box text, so half-typed text that
	// was never picked from the dropdown doesn't pass. Returns true when valid.
	function validateBudgetVersionsRequired() {
		if (!isBudgetVersionRequired()) return true;

		var firstInvalid = null;
		BAA_ROWS.forEach(function(idx) {
			var bvIdEl = document.getElementById('budget_version_id_' + idx);
			var searchEl = document.getElementById('bv_search_' + idx);
			if (!bvIdEl || !searchEl) return;

			var missing = bvIdEl.value.trim() === '';
			var group = searchEl.closest('.form-group');
			if (group) group.classList.toggle('has-error', missing);
			searchEl.classList.toggle('error', missing);
			if (missing && !firstInvalid) firstInvalid = searchEl;
		});

		if (firstInvalid) {
			firstInvalid.scrollIntoView({
				behavior: 'smooth',
				block: 'center'
			});
			return false;
		}
		return true;
	}

	// ── Hide CFA for document_type = CO ────────────────────────────────────────
	// CFA (Certificate of Fund Availability) isn't offered for a CO document, so
	// its <option> is taken out of Allocation Type while document_type = CO and put
	// back for anything else. It is removed rather than hidden: <option hidden> is
	// ignored by some browsers. Wired to #document_type's 'change' event and called
	// wherever document_type.value is set programmatically (no 'change' fires then).
	// - CFA already selected when CO is chosen → Allocation Type goes back to blank
	//   (not silently to CBA) and 'change' fires so the number, labels and Budget
	//   Version fields follow; the required field makes the user pick again.
	// - Edit mode on an existing CFA record keeps the option, so the saved type
	//   isn't wiped out by merely opening the record.
	var ALLOC_CFA_OPTION = null;

	function applyAllocationTypeOptionsForDocType() {
		var sel = document.getElementById('allocation_type');
		var docType = document.getElementById('document_type').value;
		if (!ALLOC_CFA_OPTION) ALLOC_CFA_OPTION = sel.querySelector('option[value="CFA"]');
		if (!ALLOC_CFA_OPTION) return;

		var present = ALLOC_CFA_OPTION.parentNode === sel;
		var hide = docType === 'CO' && !(CBA_EDIT_MODE && sel.value === 'CFA');

		if (hide && present) {
			var wasCfa = sel.value === 'CFA';
			ALLOC_CFA_OPTION.remove();
			if (wasCfa) {
				sel.value = '';
				sel.dispatchEvent(new Event('change'));
			}
		} else if (!hide && !present) {
			sel.appendChild(ALLOC_CFA_OPTION);
		}
	}

	// ── Hide Budget Version (COB) for CBA + CO ─────────────────────────────────
	// A CBA allocation with document_type = CO isn't linked to a Budget Version/
	// COB yet — that link is made later, downstream of the CBA. RFA/LAA still
	// need it (they can carry over a Budget Version from their parent CBA — see
	// toggleCbaIdRow), so this only hides it for allocation_type = CBA.
	function applyBudgetVersionVisibilityForDocType() {
		var type = document.getElementById('allocation_type').value;
		var docType = document.getElementById('document_type').value;
		var hide = (type === 'CBA' && docType === 'CO');
		var bvRequired = isBudgetVersionRequired();

		document.querySelectorAll('.baa-line').forEach(function(rowEl) {
			var bvGroup = rowEl.querySelector('.baa-bv-group');
			if (bvGroup) bvGroup.style.display = hide ? 'none' : '';
			// COE: the Budget Version list can be narrowed by chart of account / requesting department / district
			var cfWrap = rowEl.querySelector('.baa-bv-filters');
			if (cfWrap) {
				var showCf = docType === 'COE' && !hide;
				cfWrap.style.display = showCf ? '' : 'none';
				if (!showCf) {
					baaFiltersReset(rowEl.dataset.idx, false);
					baaBvUpdateHint(rowEl.dataset.idx);
				}
			}

			var mark = rowEl.querySelector('.baa-bv-required');
			if (mark) mark.style.display = bvRequired ? '' : 'none';
			// Switching away from RFA/LAA — drop any stale "missing" error.
			if (!bvRequired) baaBvClearError(rowEl.dataset.idx);
		});

		if (hide) {
			BAA_ROWS.forEach(function(idx) {
				baaBvClearSelection(idx);
			});
		}

		// A CBA + CO allocation is limited to a single Budget Allocation Amount
		// line (no Budget Version to differentiate additional lines by), so hide
		// the "Add Budget Allocation" button too.
		var addLineBtn = document.getElementById('baa_add_line_btn');
		var addLineRow = addLineBtn ? addLineBtn.closest('.form-row') : null;
		if (addLineRow) addLineRow.style.display = hide ? 'none' : '';
	}

	// ── Budget Version list filters (COE) ───────────────────────────────────────
	// Same idea as the "Filter the COB list" row of budget-version-form.php:
	// Chart of Account (searchable), Requesting Department, Requesting District.
	// There is no Component filter — a COE allocation is always component 3
	// (BAA_COE_COMPONENT_IDS), which the list request already applies.
	// Each line has its own strip, because each line has its own Type of Fund and
	// therefore its own list: the options are built from that line's loaded
	// versions (BAA_BV_DATA[idx]), so a choice can never leave the list empty.
	// The filters only narrow the search dropdown — they never change a version
	// that is already selected. Shown for document_type = COE only (see
	// applyBudgetVersionVisibilityForDocType).
	var BAA_BV_HINT = {}; // idx -> { source, scope } for the "n version(s) available…" hint
	var BAA_CF_ACCOUNTS = {}; // idx -> [{ value, code, title, label }] chart-of-account options

	function baaCfEl(idx, name) {
		return document.getElementById('baa_cf_' + name + '_' + idx);
	}

	// The parent COB of a budget version (/budget_version_v2/all nests it under cob_details[0]).
	function baaBvCob(bv) {
		return (bv && Array.isArray(bv.cob_details) ? bv.cob_details[0] : null) || {};
	}

	function baaCfValues(idx) {
		var acct = baaCfEl(idx, 'account');
		var dept = baaCfEl(idx, 'dept');
		var dist = baaCfEl(idx, 'dist');
		return {
			account: acct ? acct.value : '',
			dept: dept ? dept.value : '',
			dist: dist ? dist.value : ''
		};
	}

	// The line's versions that pass the three filters (all of them when none is set).
	function baaBvFiltered(idx) {
		var f = baaCfValues(idx);
		return (BAA_BV_DATA[idx] || []).filter(function(bv) {
			var cob = baaBvCob(bv);
			if (f.account && String((bv.chart_of_account || {}).account_id || '') !== f.account) return false;
			if (f.dept && String((cob.requesting_department_info || {}).department_id || '') !== f.dept) return false;
			if (f.dist && String((cob.requesting_district_info || {}).department_id || '') !== f.dist) return false;
			return true;
		});
	}

	// "5 version(s) available for …" — or "2 of 5 version(s) match the filters for …".
	function baaBvUpdateHint(idx) {
		var hint = document.getElementById('budget_version_hint_' + idx);
		var ctx = BAA_BV_HINT[idx];
		if (!hint || !ctx) return;
		var total = (BAA_BV_DATA[idx] || []).length;
		var shown = baaBvFiltered(idx).length;
		var where = ' for "' + ctx.source + '"' + ctx.scope;
		hint.textContent = !total ? 'No budget versions found' + where + '.' :
			shown === total ? total + ' version(s) available' + where + '.' :
			shown + ' of ' + total + ' version(s) match the filters' + where + '.';
	}

	// Highlight the filters that are narrowing the list; show the ✕ / "Clear filters" only then.
	function baaCfSyncUi(idx) {
		var f = baaCfValues(idx);
		if (!baaCfEl(idx, 'account')) return;
		baaCfEl(idx, 'account_search').classList.toggle('is-set', f.account !== '');
		baaCfEl(idx, 'dept').classList.toggle('is-set', f.dept !== '');
		baaCfEl(idx, 'dist').classList.toggle('is-set', f.dist !== '');
		baaCfEl(idx, 'account_x').style.display = f.account !== '' ? 'flex' : 'none';
		baaCfEl(idx, 'clear').style.display = (f.account || f.dept || f.dist) ? 'inline' : 'none';
	}

	function baaCfChanged(idx) {
		baaCfSyncUi(idx);
		baaBvUpdateHint(idx);
	}

	// Clears the three filters. disable = true also greys them out and empties the
	// option lists (the line has no versions loaded — no source of fund yet, or a
	// reload is under way).
	function baaFiltersReset(idx, disable) {
		if (!baaCfEl(idx, 'account')) return;
		baaCfEl(idx, 'account').value = '';
		baaCfEl(idx, 'account_search').value = '';
		baaCfEl(idx, 'dept').value = '';
		baaCfEl(idx, 'dist').value = '';
		if (disable) {
			BAA_CF_ACCOUNTS[idx] = [];
			var inp = baaCfEl(idx, 'account_search');
			inp.disabled = true;
			inp.placeholder = 'Select a source of fund first…';
			['dept', 'dist'].forEach(function(n) {
				var sel = baaCfEl(idx, n);
				while (sel.options.length > 1) sel.remove(1); // keep the "All …" option
				sel.disabled = true;
			});
			baaCfEl(idx, 'account_dd').style.display = 'none';
		}
		baaCfSyncUi(idx);
	}

	function baaCfFillSelect(idx, name, entries) {
		var sel = baaCfEl(idx, name);
		while (sel.options.length > 1) sel.remove(1);
		entries.sort(function(a, b) {
			return a.label.localeCompare(b.label, undefined, { numeric: true });
		}).forEach(function(e) {
			var opt = document.createElement('option');
			opt.value = e.value;
			opt.textContent = e.label;
			sel.appendChild(opt);
		});
		sel.disabled = !entries.length; // nothing to choose from
	}

	// Builds the three option lists from the line's loaded versions.
	function baaFiltersPopulate(idx) {
		if (!baaCfEl(idx, 'account')) return;
		var accounts = {},
			depts = {},
			dists = {};
		(BAA_BV_DATA[idx] || []).forEach(function(bv) {
			var a = bv.chart_of_account;
			if (a && a.account_id && !accounts[a.account_id]) {
				accounts[a.account_id] = {
					value: String(a.account_id),
					code: a.account_code || '',
					title: a.account_title || '',
					label: [a.account_code, a.account_title].filter(Boolean).join(' - ') || ('#' + a.account_id)
				};
			}
			var cob = baaBvCob(bv);
			var d = cob.requesting_department_info;
			if (d && d.department_id && !depts[d.department_id]) {
				depts[d.department_id] = { value: String(d.department_id), label: d.department_name || ('#' + d.department_id) };
			}
			var t = cob.requesting_district_info;
			if (t && t.department_id && !dists[t.department_id]) {
				dists[t.department_id] = { value: String(t.department_id), label: t.department_name || ('#' + t.department_id) };
			}
		});

		BAA_CF_ACCOUNTS[idx] = Object.keys(accounts).map(function(k) {
			return accounts[k];
		}).sort(function(a, b) {
			return a.label.localeCompare(b.label, undefined, { numeric: true });
		});
		var inp = baaCfEl(idx, 'account_search');
		inp.disabled = !BAA_CF_ACCOUNTS[idx].length;
		inp.placeholder = BAA_CF_ACCOUNTS[idx].length ? 'All chart of accounts' : 'No chart of accounts in this list';
		baaCfFillSelect(idx, 'dept', Object.keys(depts).map(function(k) {
			return depts[k];
		}));
		baaCfFillSelect(idx, 'dist', Object.keys(dists).map(function(k) {
			return dists[k];
		}));
		baaCfSyncUi(idx);
	}

	// Chart of Account — searchable dropdown (text input + option list + the chosen
	// account_id kept in a hidden input), as in budget-version-form.php.
	function baaCfRenderAccounts(idx, query) {
		var dd = baaCfEl(idx, 'account_dd');
		var q = (query || '').trim().toLowerCase();
		var sel = baaCfEl(idx, 'account').value;

		var list = (BAA_CF_ACCOUNTS[idx] || []).filter(function(o) {
			return !q || o.label.toLowerCase().includes(q);
		});
		var html = '';
		if (!q) {
			html += '<div class="baa-cf-opt' + (sel === '' ? ' is-sel' : '') + '" data-v="">' +
				'<span class="baa-cf-opt-t">All Chart of Accounts</span></div>';
		}
		html += list.map(function(o) {
			return '<div class="baa-cf-opt' + (o.value === sel ? ' is-sel' : '') + '" data-v="' + escSdoc(o.value) + '">' +
				'<span class="baa-cf-opt-t">' + escSdoc(o.title || o.label) + '</span>' +
				(o.code ? '<span class="baa-cf-opt-c">' + escSdoc(o.code) + '</span>' : '') +
				'</div>';
		}).join('');
		if (!list.length) html += '<div class="baa-cf-empty">No matching accounts</div>';

		dd.innerHTML = html;
		dd.style.display = 'block';
	}

	// Wired once per line, when the line is created.
	function baaFiltersWire(idx) {
		var inp = baaCfEl(idx, 'account_search');
		var hid = baaCfEl(idx, 'account');
		var dd = baaCfEl(idx, 'account_dd');
		if (!inp || !hid || !dd) return;

		function pick(value) {
			var o = (BAA_CF_ACCOUNTS[idx] || []).find(function(x) {
				return x.value === value;
			});
			hid.value = o ? o.value : '';
			inp.value = o ? o.label : '';
			dd.style.display = 'none';
			baaCfChanged(idx);
		}

		// With an account chosen, focus lists every account (text selected for easy
		// replacing); otherwise the list follows whatever has been typed.
		inp.addEventListener('focus', function() {
			this.select();
			baaCfRenderAccounts(idx, hid.value ? '' : this.value);
		});
		inp.addEventListener('input', function() {
			if (hid.value) {
				hid.value = '';
				baaCfChanged(idx);
			}
			baaCfRenderAccounts(idx, this.value);
		});
		inp.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
				if (!hid.value) inp.value = ''; // unfinished typing doesn't stay behind
			}, 150);
		});
		dd.addEventListener('mousedown', function(e) {
			var opt = e.target.closest('.baa-cf-opt');
			if (!opt) return;
			e.preventDefault();
			pick(opt.dataset.v);
		});
		baaCfEl(idx, 'account_x').addEventListener('mousedown', function(e) {
			e.preventDefault();
			pick('');
		});
		['dept', 'dist'].forEach(function(n) {
			baaCfEl(idx, n).addEventListener('change', function() {
				baaCfChanged(idx);
			});
		});
		baaCfEl(idx, 'clear').addEventListener('click', function() {
			baaFiltersReset(idx, false);
			baaBvUpdateHint(idx);
		});
	}

	function baaBvRenderDropdown(idx, filtered) {
		var dd = document.getElementById('bv_dropdown_' + idx);
		if (!dd) return;
		if (!filtered.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}
		dd.innerHTML = filtered.map(function(bv) {
			var displayName = baaBvDisplayName(bv);
			var fundBalance = parseFloat(bv.fund_balance) || 0;
			var req = baaBvRequesting(bv);
			return '<div class="bv-item"' +
				' data-id="' + bv.budget_version_id + '"' +
				' data-cob-id="' + (bv.cob_id || '') + '"' +
				' data-display-name="' + displayName.replace(/"/g, '&quot;') + '"' +
				' data-vnum="' + bv.version_number + '"' +
				' data-vname="' + (bv.version_name || '').replace(/"/g, '&quot;') + '"' +
				' data-status="' + (bv.status || '') + '"' +
				'>' +
				'<div class="bv-item-name">[V' + bv.version_number + '] ' + displayName + '</div>' +
				'<div class="bv-item-sub">' + (bv.version_name || '') + '</div>' +
				(req.department ? '<div class="bv-item-sub">Requesting Department: ' + escSdoc(req.department) +
					'</div>' : '') +
				(req.region ? '<div class="bv-item-sub">Requesting Region: ' + escSdoc(req.region) + '</div>' :
					'') +
				'<div class="bv-item-balance">Fund Balance: &#8369;' + baaFormatAmt(fundBalance) + '</div>' +
				'</div>';
		}).join('');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				baaBvSelect(
					idx,
					parseInt(this.dataset.id, 10),
					this.dataset.cobId,
					this.dataset.displayName,
					this.dataset.vnum,
					this.dataset.vname,
					this.dataset.status
				);
			});
		});
	}

	function baaBvSelect(idx, id, cobId, displayName, vnum, vname, status) {
		var bvIdEl = document.getElementById('budget_version_id_' + idx);
		var cobEl = document.getElementById('line_cob_id_' + idx);
		var searchEl = document.getElementById('bv_search_' + idx);
		var dd = document.getElementById('bv_dropdown_' + idx);
		if (bvIdEl) bvIdEl.value = id;
		if (cobEl) cobEl.value = cobId || '';
		if (searchEl) searchEl.value = '[V' + vnum + '] ' + displayName;
		if (dd) dd.style.display = 'none';
		baaBvClearError(idx);

		// Every row shows its own Program Source (or, for document_type = CO,
		// Fund ID — see baaBvDisplayName()) / Version / Status / Fund Balance
		// info box right below it.
		var infoProg = document.getElementById('bvi_program_source_' + idx);
		var infoVer = document.getElementById('bvi_version_' + idx);
		var infoStat = document.getElementById('bvi_status_' + idx);
		var infoBal = document.getElementById('bvi_fund_balance_' + idx);
		var info = document.getElementById('bv_info_' + idx);
		if (infoProg) infoProg.textContent = displayName || '—';
		if (infoVer) infoVer.textContent = 'V' + vnum + ' — ' + vname;
		if (infoStat) infoStat.textContent = (status || '').toUpperCase();

		var bv = (BAA_BV_DATA[idx] || []).find(function(b) {
			return String(b.budget_version_id) === String(id);
		});
		var fundBalance = bv ? (parseFloat(bv.fund_balance) || 0) : 0;
		if (infoBal) infoBal.textContent = '₱' + baaFormatAmt(fundBalance);

		// Requesting Department / Region from the parent COB — rows are hidden
		// when the COB has none set.
		var req = baaBvRequesting(bv);
		[
			['req_dept', req.department],
			['req_region', req.region]
		].forEach(function(pair) {
			var lbl = document.getElementById('bvi_' + pair[0] + '_label_' + idx);
			var val = document.getElementById('bvi_' + pair[0] + '_' + idx);
			if (val) val.textContent = pair[1];
			var show = pair[1] ? '' : 'none';
			if (lbl) lbl.style.display = show;
			if (val) val.style.display = show;
		});

		if (info) info.style.display = 'block';

		// Row 0 mirrors into the legacy singular fields — FK columns can only
		// reference one budget_version_id / cob_id.
		if (idx === 0) {
			var mirrorBv = document.getElementById('budget_version_id');
			var mirrorCob = document.getElementById('cob_id');
			if (mirrorBv) mirrorBv.value = id;
			if (mirrorCob) mirrorCob.value = cobId || '';
		}

		// Requested Amount may already have a value (edit mode / carryover) —
		// check it against this Budget Version's fund_balance right away.
		baaCheckFundBalance(idx);
	}

	function baaBvClearSelection(idx) {
		var bvIdEl = document.getElementById('budget_version_id_' + idx);
		var cobEl = document.getElementById('line_cob_id_' + idx);
		if (bvIdEl) bvIdEl.value = '';
		if (cobEl) cobEl.value = '';
		var info = document.getElementById('bv_info_' + idx);
		if (info) info.style.display = 'none';
		var warnEl = document.getElementById('bv_balance_warning_' + idx);
		if (warnEl) warnEl.classList.remove('visible');
		if (idx === 0) {
			var mirrorBv = document.getElementById('budget_version_id');
			var mirrorCob = document.getElementById('cob_id');
			if (mirrorBv) mirrorBv.value = '';
			if (mirrorCob) mirrorCob.value = '';
		}
	}

	// Compares this row's Requested Amount against its selected Budget
	// Version's fund_balance, showing an "Insufficient Balance" warning when
	// the requested amount exceeds what's available.
	function baaCheckFundBalance(idx) {
		var warnEl = document.getElementById('bv_balance_warning_' + idx);
		if (!warnEl) return;

		var bvIdEl = document.getElementById('budget_version_id_' + idx);
		if (!bvIdEl || !bvIdEl.value) {
			warnEl.classList.remove('visible');
			return;
		}

		var bv = (BAA_BV_DATA[idx] || []).find(function(b) {
			return String(b.budget_version_id) === String(bvIdEl.value);
		});
		if (!bv) {
			warnEl.classList.remove('visible');
			return;
		}

		var fundBalance = parseFloat(bv.fund_balance) || 0;
		var reqRawEl = document.getElementById('requested_amount_' + idx + '_raw');
		var requestedAmount = reqRawEl ? (parseFloat(reqRawEl.value) || 0) : 0;

		if (requestedAmount > 0 && requestedAmount > fundBalance) {
			warnEl.textContent = 'Insufficient Balance — the requested amount (₱' + baaFormatAmt(requestedAmount) +
				') exceeds this Budget Version’s available fund balance (₱' + baaFormatAmt(fundBalance) + ').';
			warnEl.classList.add('visible');
		} else {
			warnEl.classList.remove('visible');
		}
	}

	// Wired once per row (BAA_BV_WIRED guards against duplicate listeners, since
	// baaLoadBudgetVersionsByFundSource can run many times for the same row).
	function baaBvSetupSearch(idx) {
		var searchEl = document.getElementById('bv_search_' + idx);
		var dd = document.getElementById('bv_dropdown_' + idx);
		if (!searchEl || !dd) return;
		if (BAA_BV_WIRED[idx]) return;
		BAA_BV_WIRED[idx] = true;

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			baaBvClearSelection(idx);
			if (!q) {
				dd.style.display = 'none';
				return;
			}
			var data = baaBvFiltered(idx);
			var filtered = data.filter(function(bv) {
				var displayName = baaBvDisplayName(bv);
				var coaCode = bv.chart_of_account ? (bv.chart_of_account.account_code || '') : '';
				return (bv.version_name || '').toLowerCase().includes(q) ||
					displayName.toLowerCase().includes(q) ||
					coaCode.toLowerCase().includes(q);
			}).slice(0, 50);
			baaBvRenderDropdown(idx, filtered);
		});

		searchEl.addEventListener('focus', function() {
			baaBvRenderDropdown(idx, baaBvFiltered(idx).slice(0, 50));
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── Amount inputs: comma-formatted display, raw value in hidden field ────────
	function baaParseAmt(str) {
		return parseFloat((str || '').replace(/[^0-9.]/g, '')) || 0;
	}

	function baaFormatAmt(num) {
		if (!num && num !== 0) return '';
		return num.toLocaleString('en-PH', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	function baaSyncRaw(input) {
		var raw = baaParseAmt(input.value);
		var hidden = document.getElementById(input.id + '_raw');
		if (hidden) hidden.value = raw > 0 ? raw.toFixed(2) : '';
	}

	function wireAmtInput(input) {
		if (!input) return;
		var isRequestedAmount = input.classList.contains('baa-requested-amount');
		input.addEventListener('blur', function() {
			var raw = baaParseAmt(this.value);
			this.value = raw > 0 ? baaFormatAmt(raw) : '';
			baaSyncRaw(this);
			if (isRequestedAmount) baaCheckFundBalance(this.dataset.idx);
		});
		input.addEventListener('keydown', function(e) {
			var allowed = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home',
				'End'
			];
			if (allowed.includes(e.key)) return;
			if (e.key === '.' && !this.value.includes('.')) return;
			if (e.key >= '0' && e.key <= '9') return;
			e.preventDefault();
		});
		input.addEventListener('input', function() {
			baaSyncRaw(this);
			recomputeBaaTotals();
			if (isRequestedAmount) baaCheckFundBalance(this.dataset.idx);
		});
	}

	function baaSetAmount(idx, kind, val) {
		var num = parseFloat(val) || 0;
		var display = document.getElementById(kind + '_amount_' + idx);
		var raw = document.getElementById(kind + '_amount_' + idx + '_raw');
		if (!display) return;
		if (num > 0) {
			display.value = baaFormatAmt(num);
			if (raw) raw.value = num.toFixed(2);
		} else {
			display.value = '';
			if (raw) raw.value = '';
		}
		if (kind === 'requested') baaCheckFundBalance(idx);
	}

	// Sums every row's Requested/Cash amounts, updates the totals footer, mirrors
	// the totals into the legacy singular hidden fields (requested_amount_raw /
	// allocated_amount_raw — read by buildPayload, printCBA, the alloc>req submit
	// check, etc.).
	function recomputeBaaTotals() {
		var totalReq = 0,
			totalAlloc = 0;
		BAA_ROWS.forEach(function(idx) {
			var reqRaw = document.getElementById('requested_amount_' + idx + '_raw');
			var allocRaw = document.getElementById('allocated_amount_' + idx + '_raw');
			totalReq += reqRaw ? (parseFloat(reqRaw.value) || 0) : 0;
			totalAlloc += allocRaw ? (parseFloat(allocRaw.value) || 0) : 0;
		});

		var totalReqEl = document.getElementById('baa_total_requested');
		var totalAllocEl = document.getElementById('baa_total_allocated');
		if (totalReqEl) totalReqEl.textContent = '₱' + baaFormatAmt(totalReq);
		if (totalAllocEl) totalAllocEl.textContent = '₱' + baaFormatAmt(totalAlloc);

		var reqMirror = document.getElementById('requested_amount_raw');
		var allocMirror = document.getElementById('allocated_amount_raw');
		if (reqMirror) reqMirror.value = totalReq > 0 ? totalReq.toFixed(2) : '';
		if (allocMirror) allocMirror.value = totalAlloc > 0 ? totalAlloc.toFixed(2) : '';
	}

	// Collects every row into the itemized array sent as `line_transaction`.
	function collectBaaLines() {
		return BAA_ROWS.map(function(idx) {
			var fsSelect = document.getElementById('fund_source_id_' + idx);
			var opt = fsSelect ? fsSelect.options[fsSelect.selectedIndex] : null;
			return {
				fund_source_id: fsSelect ? fsSelect.value : '',
				fund_source_name: (opt && opt.value) ? opt.textContent : '',
				budget_version_id: (document.getElementById('budget_version_id_' + idx) || {}).value || '',
				cob_id: (document.getElementById('line_cob_id_' + idx) || {}).value || '',
				requested_amount: (document.getElementById('requested_amount_' + idx + '_raw') || {}).value || '',
				allocated_amount: (document.getElementById('allocated_amount_' + idx + '_raw') || {}).value || '',
			};
		}).filter(function(line) {
			// Drop a fully-empty trailing row (e.g. one added then left untouched).
			return line.fund_source_id || line.requested_amount;
		});
	}

	// ── 6. Departments ────────────────────────────────────────────────────────────
	// Both the "Requesting Department" dropdown (MAIN only) and the "Region /
	// District Office" dropdown (REGIONAL + DISTRICT, plus MAIN when the
	// document type is CO) are scoped down to this fixed whitelist: HTTRD and
	// GSD among MAIN offices, NCR East/West/North/South Sector and Region I
	// through XIII Office among REGIONAL ones — matched by department_code
	// rather than department_id so it survives reseeding. "GSD" matches both the
	// department literally named "GSD" (department_code GSD, seeded alongside
	// HTTRD) and the older, separate "General Services Department" record that
	// also uses the GSD code — both are shown so either legacy or current data
	// referencing GSD resolves correctly.
	// Regional Offices are matched under both seeds: the legacy REGIONAL-OPS-* codes
	// and the current NHA-RO<n> codes (NHA-RO1 … NHA-RO17: Regions 1-13 + the four
	// NCR sectors).
	function cbaIsWhitelistedDept(dept) {
		var code = dept.department_code || '';
		if (code.indexOf('REGIONAL-OPS-') === 0) return true; // legacy seed: NCR sectors + Region I-XIII
		if (/^NHA-RO\d+$/.test(code)) return true; // current seed: Regional Offices
		if (dept.department_type === 'MAIN' && (dept.department_name === 'GSD' || dept.department_name ===
				'General Services Department' || dept.department_name === 'HTTRD')) return true;
		return false;
	}

	// The whitelisted offices PLUS every office connected beneath them through
	// parent_department_id — e.g. a Regional Office and all of its District
	// Offices (NHA-DO* rows whose parent is that NHA-RO*), at any depth. The
	// District Offices carry no whitelisted code of their own, so without this
	// they were always dropped. `rows` is the full REGIONAL/DISTRICT(/MAIN) fetch,
	// so the children are already in it.
	function cbaWithWhitelistedChildren(rows) {
		var keep = {};
		rows.forEach(function(d) {
			if (cbaIsWhitelistedDept(d)) keep[d.department_id] = true;
		});

		var grew = true;
		while (grew) { // repeat until no new child is found, so grandchildren are reached too
			grew = false;
			rows.forEach(function(d) {
				if (!keep[d.department_id] && d.parent_department_id != null && keep[d.parent_department_id]) {
					keep[d.department_id] = true;
					grew = true;
				}
			});
		}
		return rows.filter(function(d) {
			return keep[d.department_id];
		});
	}

	var DEPT_ALL_RAW = []; // unfiltered MAIN offices, as fetched from the API
	var DEPT_ALL_DATA = []; // DEPT_ALL_RAW, narrowed by applyDeptWhitelistForDocType()
	var DEPT_WIRED = false;

	async function loadDepartments() {
		var searchEl = document.getElementById('dept_search');
		try {
			var json = await cba_apiFetch(`${API_BASE}/department/all?department_type=MAIN`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			DEPT_ALL_RAW = json.data;
			applyDeptWhitelistForDocType();
			searchEl.disabled = false;
			searchEl.placeholder = 'Search department\u2026';
			deptSetupSearch();
		} catch (err) {
			console.error('[loadDepartments]', err);
			showError('department_error');
		} finally {
			setLoading('department_wrapper', false);
		}
	}

	// \u2500\u2500 Requesting Department whitelist \u2014 Document Type = COE isn't scoped to
	// the fixed GSD/HTTRD whitelist (cbaIsWhitelistedDept): a COE allocation can
	// be requested by any MAIN office, so this shows every MAIN office fetched
	// above unfiltered. Document Type = CO hides this field entirely (see
	// toggleDepartmentByDocType); every other document type (including none
	// selected yet) keeps the existing GSD/HTTRD-only whitelist. Wired to
	// #document_type's 'change' event, and called manually wherever
	// document_type.value is set programmatically (no 'change' event fires for
	// that) \u2014 same pattern as applyCategoryTypeForDocType()/applyFundSourceForDocType().
	function applyDeptWhitelistForDocType() {
		var docType = document.getElementById('document_type').value;
		var rows = (docType === 'COE') ? DEPT_ALL_RAW : DEPT_ALL_RAW.filter(cbaIsWhitelistedDept);

		DEPT_ALL_DATA = rows.slice().sort(function(a, b) {
			if (a.hierarchy_level !== b.hierarchy_level) return a.hierarchy_level - b.hierarchy_level;
			return (a.department_name || '').localeCompare(b.department_name || '');
		});

		// Clear the current selection if it no longer exists in the (narrower)
		// filtered list \u2014 e.g. document type switched away from COE and the
		// previously-picked department fell outside the whitelist.
		var currentValue = document.getElementById('requesting_department_id').value;
		var stillExists = DEPT_ALL_DATA.some(function(d) {
			return String(d.department_id) === String(currentValue);
		});
		if (currentValue && !stillExists) deptClearSelection();

		// Refresh the dropdown in place if it's currently open.
		var dd = document.getElementById('dept_dropdown');
		if (dd && dd.style.display !== 'none') deptRenderDropdown(DEPT_ALL_DATA);
	}

	function deptLabelFor(dept) {
		return dept.department_code ?
			dept.department_name + ' (' + dept.department_code + ')' :
			(dept.department_name || '');
	}

	function deptRenderDropdown(data) {
		var dd = document.getElementById('dept_dropdown');
		if (!data.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}
		dd.innerHTML = data.map(function(dept) {
			var indent = dept.hierarchy_level > 1 ? '\u2514 ' : '';
			return '<div class="bv-item"' +
				' data-id="' + dept.department_id + '"' +
				' data-label="' + deptLabelFor(dept).replace(/"/g, '&quot;') + '"' +
				'>' +
				'<div class="bv-item-name">' + indent + (dept.department_name || '') + '</div>' +
				'<div class="bv-item-sub">' + (dept.department_code || '') + '</div>' +
				'</div>';
		}).join('');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				deptSelect(parseInt(this.dataset.id, 10), this.dataset.label);
			});
		});
	}

	function deptSelect(id, label) {
		var hidden = document.getElementById('requesting_department_id');
		hidden.value = id;
		hidden.dataset.label = label || '';

		document.getElementById('dept_search').value = label || '';
		document.getElementById('dept_dropdown').style.display = 'none';
	}

	function deptClearSelection() {
		var hidden = document.getElementById('requesting_department_id');
		if (hidden) {
			hidden.value = '';
			hidden.dataset.label = '';
		}
		var searchEl = document.getElementById('dept_search');
		if (searchEl) searchEl.value = '';
	}

	function deptSetupSearch() {
		var searchEl = document.getElementById('dept_search');
		var dd = document.getElementById('dept_dropdown');
		if (DEPT_WIRED) return;
		DEPT_WIRED = true;

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			var hidden = document.getElementById('requesting_department_id');
			hidden.value = '';
			hidden.dataset.label = '';

			if (!q) {
				deptRenderDropdown(DEPT_ALL_DATA);
				return;
			}
			var filtered = DEPT_ALL_DATA.filter(function(dept) {
				return (dept.department_name || '').toLowerCase().includes(q) ||
					(dept.department_code || '').toLowerCase().includes(q);
			});
			deptRenderDropdown(filtered);
		});

		searchEl.addEventListener('focus', function() {
			deptRenderDropdown(DEPT_ALL_DATA);
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── 7. Responsibility Centers (searchable combobox) ───────────────────────────
	var RC_ALL_DATA = [];
	var RC_WIRED = false;

	async function loadResponsibilityCenters(mode) {
		var searchEl = document.getElementById('rc_search');
		var url = `${API_BASE}/department/all?department_type=REGIONAL&department_type=DISTRICT`;
		if (mode === 'DISTRICT') {
			// Document Type = CO — also include MAIN offices in the Region / District Office list
			url += '&department_type=MAIN';
		}

		searchEl.disabled = true;
		rcClearSelection();
		document.getElementById('rc_dropdown').style.display = 'none';
		setLoading('rc_wrapper', true);

		try {
			var json = await cba_apiFetch(url);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			// Document Type = COE isn't scoped to the whitelist
			// (cbaIsWhitelistedDept) — it shows every REGIONAL/DISTRICT office
			// fetched above (mode is never 'DISTRICT' for COE, so MAIN offices
			// are already excluded from `url` above). Every other document type
			// keeps the whitelist, now extended with the offices connected under
			// each whitelisted one via parent_department_id.
			var docType = document.getElementById('document_type').value;
			var rows = (docType === 'COE') ? json.data : cbaWithWhitelistedChildren(json.data);

			RC_ALL_DATA = cbaTreeOrder(rows);
			searchEl.disabled = false;
			searchEl.placeholder = 'Search region / district office…';
			rcSetupSearch();
		} catch (err) {
			console.error('[loadResponsibilityCenters]', err);
			showError('rc_error');
		} finally {
			setLoading('rc_wrapper', false);
		}
	}

	// Region / District Office label: the office name only — no "(department_code)".
	function rcLabelFor(dept) {
		return dept.department_name || '';
	}

	// Orders offices as a tree: each parent immediately followed by its children
	// (via parent_department_id), siblings sorted by name — numerically, so
	// "Region 2" comes before "Region 10". An office whose parent isn't in `rows`
	// (e.g. a Regional Office, whose parent "Regional Operations" is never listed)
	// is a top-level entry. Anything left unreached (a parent cycle in the data) is
	// appended flat rather than dropped.
	function cbaTreeOrder(rows) {
		var ids = {},
			byParent = {},
			seen = {},
			out = [];
		rows.forEach(function(d) {
			ids[d.department_id] = true;
		});
		rows.forEach(function(d) {
			var key = (d.parent_department_id != null && ids[d.parent_department_id]) ? d.parent_department_id :
				'root';
			(byParent[key] = byParent[key] || []).push(d);
		});
		var byName = function(a, b) {
			return (a.department_name || '').localeCompare(b.department_name || '', undefined, {
				numeric: true
			});
		};
		(function walk(key) {
			(byParent[key] || []).sort(byName).forEach(function(d) {
				if (seen[d.department_id]) return;
				seen[d.department_id] = true;
				out.push(d);
				walk(d.department_id);
			});
		})('root');
		rows.forEach(function(d) {
			if (!seen[d.department_id]) out.push(d);
		});
		return out;
	}

	function rcRenderDropdown(data) {
		var dd = document.getElementById('rc_dropdown');
		if (!data.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}

		// Indent an office under its parent only when that parent is in the list
		// being shown — so a search that matches just a District Office doesn't
		// leave it dangling indented with no parent above it.
		var shown = {},
			parentOf = {};
		data.forEach(function(d) {
			shown[d.department_id] = true;
			parentOf[d.department_id] = d.parent_department_id;
		});
		var depthOf = function(d) {
			var depth = 0,
				p = d.parent_department_id;
			while (p != null && shown[p] && depth < 10) {
				depth++;
				p = parentOf[p];
			}
			return depth;
		};

		dd.innerHTML = data.map(function(dept) {
			var depth = depthOf(dept);
			return '<div class="bv-item"' +
				' data-id="' + dept.department_id + '"' +
				' data-label="' + rcLabelFor(dept).replace(/"/g, '&quot;') + '"' +
				(depth ? ' style="padding-left:' + (14 + depth * 22) + 'px;"' : '') +
				'>' +
				'<div class="bv-item-name">' + (depth ?
					'<span style="color:var(--afims-text-disabled, #A9AAB8);font-weight:400;">&#9492;</span> ' : ''
				) + (dept.department_name || '') + '</div>' +
				'<div class="bv-item-sub">' + (dept.department_type || '') + (dept.department_code ? ' &middot; ' +
					dept.department_code : '') + '</div>' +
				'</div>';
		}).join('');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				rcSelect(parseInt(this.dataset.id, 10), this.dataset.label);
			});
		});
	}

	function rcSelect(id, label) {
		var hidden = document.getElementById('requesting_region_id');
		hidden.value = id;
		hidden.dataset.label = label || '';

		document.getElementById('rc_search').value = label || '';
		document.getElementById('rc_dropdown').style.display = 'none';
	}

	function rcClearSelection() {
		var hidden = document.getElementById('requesting_region_id');
		if (hidden) {
			hidden.value = '';
			hidden.dataset.label = '';
		}
		var searchEl = document.getElementById('rc_search');
		if (searchEl) searchEl.value = '';
	}

	function rcSetupSearch() {
		var searchEl = document.getElementById('rc_search');
		var dd = document.getElementById('rc_dropdown');
		if (RC_WIRED) return;
		RC_WIRED = true;

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			var hidden = document.getElementById('requesting_region_id');
			hidden.value = '';
			hidden.dataset.label = '';

			if (!q) {
				rcRenderDropdown(RC_ALL_DATA);
				return;
			}
			var filtered = RC_ALL_DATA.filter(function(dept) {
				return (dept.department_name || '').toLowerCase().includes(q) ||
					(dept.department_code || '').toLowerCase().includes(q);
			});
			rcRenderDropdown(filtered);
		});

		searchEl.addEventListener('focus', function() {
			rcRenderDropdown(RC_ALL_DATA);
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── 8. Parent CBA searchable combobox ────────────────────────────────────────
	var CBA_DATA = [];
	var CBA_LOADED = false;
	var CBA_ACTIVE_IDX = -1;

	function cbaShowSpinner(on) {
		document.getElementById('cba_icon_chevron').style.display = on ? 'none' : '';
		document.getElementById('cba_icon_spinner').style.display = on ? '' : 'none';
	}

	function cbaHighlight(str, query) {
		if (!query) return str;
		var esc = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		return str.replace(new RegExp('(' + esc + ')', 'gi'), '<mark>$1</mark>');
	}

	function cbaBuildItem(c, query) {
		var label = c.allocation_number +
			(c.prog_name ? ' — ' + c.prog_name : '') +
			(c.fs_code ? ' [' + c.fs_code + ']' : '') +
			(c.fp_period_name ? ' · FY' + c.fp_period_name : '');
		var li = document.createElement('li');
		li.setAttribute('role', 'option');
		li.dataset.id = c.allocation_id;
		li.dataset.label = label;
		li.innerHTML = cbaHighlight(label, query);
		li.addEventListener('mousedown', function(e) {
			e.preventDefault();
			cbaSelect(c.allocation_id, label);
		});
		return li;
	}

	function cbaRenderList(query) {
		var ul = document.getElementById('cba_dropdown');
		var q = (query || '').toLowerCase().trim();
		var filtered = q ?
			CBA_DATA.filter(function(c) {
				return (c.allocation_number || '').toLowerCase().includes(q) ||
					(c.prog_name || '').toLowerCase().includes(q) ||
					(c.fs_code || '').toLowerCase().includes(q) ||
					(c.fs_name || '').toLowerCase().includes(q);
			}) :
			CBA_DATA;

		ul.innerHTML = '';
		CBA_ACTIVE_IDX = -1;

		if (filtered.length === 0) {
			var li = document.createElement('li');
			li.className = 'cba-no-results';
			li.textContent = q ? 'No CBA matches "' + q + '"' : 'No approved CBA found.';
			ul.appendChild(li);
		} else {
			filtered.forEach(function(c) {
				ul.appendChild(cbaBuildItem(c, q));
			});
		}
		ul.style.display = '';
	}

	function cbaOpenDropdown() {
		cbaRenderList(document.getElementById('cba_search').value);
	}

	function cbaCloseDropdown() {
		document.getElementById('cba_dropdown').style.display = 'none';
		CBA_ACTIVE_IDX = -1;
	}

	function cbaSelect(id, label) {
		document.getElementById('cba_id').value = id;
		document.getElementById('cba_search').value = label;
		document.getElementById('cba_search').classList.add('cba-has-value');
		document.getElementById('cba_search').classList.remove('cba-invalid');
		document.getElementById('cba_id_hint').textContent = 'Selected: ' + label;
		cbaCloseDropdown();
	}

	function cbaClearSelection() {
		document.getElementById('cba_id').value = '';
		document.getElementById('cba_search').classList.remove('cba-has-value');
	}

	function cbaKeyNav(e) {
		var ul = document.getElementById('cba_dropdown');
		var items = ul.querySelectorAll('li:not(.cba-no-results)');
		if (!items.length) return;

		if (e.key === 'ArrowDown') {
			e.preventDefault();
			CBA_ACTIVE_IDX = Math.min(CBA_ACTIVE_IDX + 1, items.length - 1);
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			CBA_ACTIVE_IDX = Math.max(CBA_ACTIVE_IDX - 1, 0);
		} else if (e.key === 'Enter') {
			e.preventDefault();
			if (CBA_ACTIVE_IDX >= 0 && items[CBA_ACTIVE_IDX]) {
				var it = items[CBA_ACTIVE_IDX];
				cbaSelect(it.dataset.id, it.dataset.label);
			}
			return;
		} else if (e.key === 'Escape') {
			cbaCloseDropdown();
			return;
		} else {
			return;
		}

		items.forEach(function(li, i) {
			li.classList.toggle('cba-active', i === CBA_ACTIVE_IDX);
		});
		if (items[CBA_ACTIVE_IDX]) items[CBA_ACTIVE_IDX].scrollIntoView({
			block: 'nearest'
		});
	}

	async function loadParentCBAs() {
		if (CBA_LOADED) {
			cbaOpenDropdown();
			return;
		}

		var search = document.getElementById('cba_search');
		search.disabled = true;
		cbaShowSpinner(true);

		try {
			var json = await cba_apiFetch(`${API_BASE}/budget_allocation/all?allocation_type=CBA`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message || 'Bad response');

			CBA_DATA = json.data;
			CBA_LOADED = true;
			search.disabled = false;
			search.placeholder = 'Search by CBA number, program or fund…';
			search.focus();
			cbaOpenDropdown();
		} catch (err) {
			console.error('[loadParentCBAs]', err);
			document.getElementById('cba_id_error').classList.add('visible');
			search.placeholder = 'Could not load — please refresh';
		} finally {
			cbaShowSpinner(false);
		}
	}

	function cbaWireEvents() {
		var search = document.getElementById('cba_search');
		var ul = document.getElementById('cba_dropdown');

		search.addEventListener('input', function() {
			cbaClearSelection();
			cbaRenderList(this.value);
		});
		search.addEventListener('focus', function() {
			if (CBA_LOADED) cbaOpenDropdown();
			else loadParentCBAs();
		});
		search.addEventListener('keydown', function(e) {
			if (ul.style.display === 'none' && e.key !== 'Tab') cbaOpenDropdown();
			cbaKeyNav(e);
		});
		document.addEventListener('mousedown', function(e) {
			if (!document.getElementById('cba_combo').contains(e.target)) cbaCloseDropdown();
		});
	}

	// ── Toggle CBA parent row ─────────────────────────────────────────────────────
	function toggleCbaIdRow() {
		var type = document.getElementById('allocation_type').value;
		var row = document.getElementById('cba_id_row');
		var search = document.getElementById('cba_search');
		var hidden = document.getElementById('cba_id');
		var docTypeRow = document.getElementById('document_type_row');

		if (type === 'RFA' || type === 'LAA') {
			row.style.display = '';
			if (docTypeRow) docTypeRow.style.display = '';
			document.getElementById('document_type').setAttribute('required', 'required');
			if (!search.dataset.wired) {
				cbaWireEvents();
				search.dataset.wired = '1';
			}
			if (!CBA_LOADED) loadParentCBAs();
		} else {
			row.style.display = 'none';
			if (docTypeRow) docTypeRow.style.display = '';
			document.getElementById('document_type').setAttribute('required', 'required');
			cbaCloseDropdown();
			search.value = '';
			hidden.value = '';
			document.getElementById('cba_id_hint').textContent =
				'Optionally link this RFA / LAA to an existing approved CBA.';
		}
		// RFA/LAA can carry over a Budget Version from their parent CBA (see the
		// ?parent_cba_id "Generate RFA/LAA" preset autofill flow), so the
		// Budget Version field is only hidden for allocation_type = CBA + CO.
		applyBudgetVersionVisibilityForDocType();
	}

	// ── Toggle Department by Document Type ───────────────────────────────────────
	// Region / District Office fetch includes MAIN offices only for CO; COE (and
	// every other doc type) fetches REGIONAL + DISTRICT only. Only CO hides/clears
	// the separate Requesting Department field.
	function toggleDepartmentByDocType() {
		var docType = document.getElementById('document_type').value;
		var deptGroup = document.getElementById('requesting_department_id').closest('.form-group');
		var rcMode = (docType === 'CO') ? 'DISTRICT' : undefined;

		if (docType === 'CO') {
			deptGroup.style.display = 'none';
			deptClearSelection();
		} else {
			deptGroup.style.display = '';
		}
		return loadResponsibilityCenters(rcMode);
	}

	// ── Allocation count  →  GET /budget_allocation/counts ───────────────────────
	async function refreshAllocationCount() {
		try {
			var json = await cba_apiFetch(`${API_BASE}/budget_allocation/counts`);
			if (json.success && json.data) {
				var d = json.data;
				CBA_SERIES.CBA = parseInt(d.next_cba_series, 10) || 1;
				CBA_SERIES.RFA = parseInt(d.next_rfa_series, 10) || 1;
				CBA_SERIES.LAA = parseInt(d.next_laa_series, 10) || 1;
				generateCBANumber();
			}
		} catch (err) {
			console.warn('[refreshAllocationCount]', err);
		}
	}

	setInterval(refreshAllocationCount, 2 * 60 * 1000);

	// ── Allocation Number generator ───────────────────────────────────────────────
	// Shared by create mode (generateCBANumber) and edit mode (populateEditForm,
	// when the loaded record has no allocation_number yet) so both paths always
	// produce the same per-type format.
	function buildAllocationNumber(type, docType) {
		type = type || 'CBA';

		var d = new Date();
		var yr = d.getFullYear();
		var yy = String(yr).slice(-2);
		var mo = d.getMonth() + 1;
		var quarter = Math.ceil(mo / 3);
		var moInQuarter = mo - (quarter - 1) * 3;

		var qq = String(quarter).padStart(1, '0');
		var mm = String(moInQuarter).padStart(1, '0');
		var series = String(CBA_SERIES[type] || 1).padStart(2, '0');

		if (type === 'CBA') return 'CBA ' + yr + '-' + qq + mm + '-86-' + series;
		// RFA's middle code: 57 for document type COE, 87 for everything else (CO)
		var rfaCode = (docType === 'COE') ? '57' : '87';
		if (type === 'RFA') return yy + '' + qq + mm + '-' + rfaCode + '-' + series;
		if (type === 'LAA') return 'LAA ' + yr + '-' + qq + mm + '-' + series;
		return type + ' ' + yr + '-' + qq + mm + '-' + series;
	}

	function generateCBANumber() {
		// In edit mode the allocation_number is locked to the value loaded from
		// the server — never overwrite it with a freshly generated series number.
		if (CBA_EDIT_MODE) return;

		var type = document.getElementById('allocation_type').value || 'CBA';
		var fsEl = document.getElementById('fund_source_id');
		var fsText = fsEl ? (fsEl.dataset.label || '') : '';

		var num = buildAllocationNumber(type, document.getElementById('document_type').value);

		document.getElementById('allocation_number').value = num;
		document.getElementById('cba_number_display').textContent = fsText ? (num + ' / ' + fsText) : num;
	}

	// ── Validation ────────────────────────────────────────────────────────────────
	function setupFormValidation() {
		document.querySelectorAll('[required]').forEach(function(field) {
			field.addEventListener('blur', function() {
				validateField(field);
			});
			field.addEventListener('input', function() {
				if (field.classList.contains('error')) validateField(field);
			});
		});
	}

	function validateField(field) {
		var group = field.closest('.form-group');
		if (!group) return;
		var isEmpty = field.value.trim() === '';
		group.classList.toggle('has-error', isEmpty);
		field.classList.toggle('error', isEmpty);
		field.classList.toggle('success', !isEmpty);
	}

	// ── Supporting Documents ──────────────────────────────────────────────────────
	var _sdocCount = 0;
	var SDOC_MAX_BYTES = 25 * 1024 * 1024; // 25MB

	function addSupportingDocRow(docLabel) {
		_sdocCount++;
		var idx = _sdocCount;
		var list = document.getElementById('supporting_docs_list');
		var row = document.createElement('div');
		row.className = 'sdoc-row';
		row.dataset.idx = idx;
		row.innerHTML =
			'<div class="form-group">' +
			'<label class="sdoc-label">' + (docLabel ? escSdoc(docLabel) : 'Attach File') +
			' <span class="required">*</span></label>' +
			'<div class="sdoc-dropzone" id="sdoc_dz_' + idx + '"' +
			' ondragover="sdocDragOver(event,' + idx + ')"' +
			' ondragleave="sdocDragLeave(event,' + idx + ')"' +
			' ondrop="sdocDrop(event,' + idx + ')">' +
			'<input type="file" id="sdoc_file_' + idx + '"' +
			' accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"' +
			' onchange="sdocFileChange(event,' + idx + ')">' +
			'<svg class="sdoc-dz-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6H16a5 5 0 011 9.9M12 12v9m0-9l-3 3m3-3l3 3"/>' +
			'</svg>' +
			'<p class="sdoc-dz-title">Drag &amp; drop a file, or click to browse</p>' +
			'<span class="sdoc-dz-hint">Max 25MB &middot; PDF, DOC, XLS, JPG, PNG &mdash; the document name is taken from the file</span>' +
			'</div>' +
			'<div class="sdoc-file-chip" id="sdoc_chip_' + idx + '" style="display:none;">' +
			'<span class="sdoc-file-ico"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' +
			'</svg></span>' +
			'<a class="sdoc-file-name" id="sdoc_fname_' + idx + '" target="_blank" rel="noopener noreferrer"></a>' +
			'<span class="sdoc-file-size" id="sdoc_fsize_' + idx + '"></span>' +
			'<span class="sdoc-file-actions">' +
			'<a class="sdoc-file-open" id="sdoc_open_' + idx + '" target="_blank" rel="noopener noreferrer" style="display:none;">' +
			'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="12" height="12">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>' +
			'</svg>Open</a>' +
			'<button type="button" class="sdoc-file-remove" title="Remove this file and choose another" onclick="sdocClearFile(' + idx + ')">Replace</button>' +
			'</span>' +
			'</div>' +
			'<input type="text" class="form-input sdoc-link" id="sdoc_link_' + idx + '"' +
			' placeholder="e.g. https://drive.google.com/…"' +
			' style="display:none;margin-top:8px;">' +
			'<p class="sdoc-upload-note" id="sdoc_note_' + idx + '" style="display:none;"></p>' +
			'<input type="hidden" class="sdoc-name" id="sdoc_name_' + idx + '" value="">' +
			'</div>' +
			'<button type="button" class="sdoc-remove-btn" title="Remove row" aria-label="Remove row" onclick="removeSupportingDocRow(this)">' +
			'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
			'</svg>' +
			'</button>';
		list.appendChild(row);
		return idx;
	}

	// Only these are made clickable: absolute http(s) URLs and root-relative paths.
	// An uploaded file's stored "link" is a storage key, not a URL, and a typed
	// "reference" may be free text — neither is opened (see sdocSetOpen).
	function sdocSafeUrl(u) {
		u = String(u || '').trim();
		return (/^https?:\/\//i.test(u) || /^\/(?!\/)/.test(u)) ? u : '';
	}

	// Wires the chip's file name and Open button to the document's URL, or hides
	// Open (and leaves the name as plain text) when there is nothing to open.
	function sdocSetOpen(idx, rawUrl) {
		var url = sdocSafeUrl(rawUrl);
		var open = document.getElementById('sdoc_open_' + idx);
		var name = document.getElementById('sdoc_fname_' + idx);
		if (url) {
			open.href = url;
			name.href = url;
			open.style.display = 'inline-flex';
		} else {
			open.removeAttribute('href');
			name.removeAttribute('href');
			open.style.display = 'none';
		}
	}

	// Swaps the dropzone for the attached-file chip.
	//   note    — small pill after the name (a file size, or e.g. "Carried over
	//             from parent CBA"); hidden when empty
	//   openUrl — where the name / Open button go (see sdocSetOpen)
	function sdocShowChip(idx, name, note, openUrl) {
		var fname = document.getElementById('sdoc_fname_' + idx);
		fname.textContent = name;
		fname.title = name;
		document.getElementById('sdoc_fsize_' + idx).textContent = note || '';
		sdocSetOpen(idx, openUrl);
		document.getElementById('sdoc_dz_' + idx).style.display = 'none';
		document.getElementById('sdoc_chip_' + idx).style.display = 'flex';
	}

	// Pre-fills a supporting-doc row with a document that is already attached —
	// carried over from the parent CBA when generating an RFA/LAA (see the
	// ?parent_cba_id autofill in the DOMContentLoaded handler), or this
	// allocation's own documents in edit mode. There is no local File object to
	// re-select, so the dropzone is skipped and the row goes straight to the chip
	// state with the document_name/link, which is exactly what getSupportingDocs()
	// reads on submit.
	//   viewUrl — the read endpoint's presigned URL for an uploaded file
	//             (file_meta.view_url); falls back to the link itself when that is
	//             already a URL (a typed Google Drive link, etc.)
	//   note    — optional pill text, e.g. 'Carried over from parent CBA'
	function addExistingSupportingDocRow(name, link, viewUrl, note) {
		// The label is generic: the file name is shown on the chip right below, so
		// repeating it as the label too just doubled it up.
		var idx = addSupportingDocRow('Supporting Document');
		document.getElementById('sdoc_name_' + idx).value = name || '';
		document.getElementById('sdoc_link_' + idx).value = link || '';
		sdocShowChip(idx, name || 'Supporting Document', note, viewUrl || link);
	}

	function removeSupportingDocRow(btn) {
		btn.closest('.sdoc-row').remove();
	}

	function sdocFormatSize(bytes) {
		if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
		return Math.ceil(bytes / 1024) + ' KB';
	}

	function sdocSetNote(idx, message, isError) {
		var note = document.getElementById('sdoc_note_' + idx);
		if (!message) {
			note.style.display = 'none';
			note.textContent = '';
			return;
		}
		note.textContent = message;
		note.className = 'sdoc-upload-note' + (isError ? ' error' : '');
		note.style.display = 'block';
	}

	// Reverts this row to the original manual Document Name / Link fields —
	// used when a dropped file is over the 25MB limit.
	function sdocShowManualMode(idx, message) {
		document.getElementById('sdoc_dz_' + idx).style.display = 'none';
		document.getElementById('sdoc_chip_' + idx).style.display = 'none';
		var link = document.getElementById('sdoc_link_' + idx);
		link.style.display = 'block';
		link.focus();
		sdocSetNote(idx, message, true);
	}

	function sdocDragOver(e, idx) {
		e.preventDefault();
		document.getElementById('sdoc_dz_' + idx).classList.add('drag-over');
	}

	function sdocDragLeave(e, idx) {
		document.getElementById('sdoc_dz_' + idx).classList.remove('drag-over');
	}

	function sdocDrop(e, idx) {
		e.preventDefault();
		document.getElementById('sdoc_dz_' + idx).classList.remove('drag-over');
		if (e.dataTransfer.files && e.dataTransfer.files[0]) {
			sdocProcessFile(idx, e.dataTransfer.files[0]);
		}
	}

	function sdocFileChange(e, idx) {
		if (e.target.files && e.target.files[0]) {
			sdocProcessFile(idx, e.target.files[0]);
		}
	}

	function sdocDeriveName(fileName) {
		return fileName.replace(/\.[^./]+$/, '');
	}

	function sdocProcessFile(idx, file) {
		sdocSetNote(idx, '', false);

		// Document name always comes from the file itself — set it up front so
		// it's captured even for oversized files that fall back to a manual link.
		document.getElementById('sdoc_name_' + idx).value = sdocDeriveName(file.name);

		if (file.size > SDOC_MAX_BYTES) {
			sdocShowManualMode(
				idx,
				'That file is ' + sdocFormatSize(file.size) +
				', which is over the 25MB limit. Please provide a link instead.'
			);
			return;
		}

		var dz = document.getElementById('sdoc_dz_' + idx);
		dz.classList.add('uploading');
		sdocSetNote(idx, 'Uploading…', false);

		var formData = new FormData();
		formData.append('file', file);

		fetch(`${API_BASE}/upload/file`, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					...(CBA_TOKEN ? {
						'X-Authorization': 'Bearer ' + CBA_TOKEN
					} : {})
				},
				body: formData,
			})
			.then(function(res) {
				return res.json().then(function(json) {
					if (!res.ok || !json.success) throw new Error(json.message || 'Upload failed.');
					return json;
				});
			})
			.then(function(json) {
				dz.classList.remove('uploading');
				sdocSetNote(idx, '', false);

				document.getElementById('sdoc_link_' + idx).value = json.link;
				sdocShowChip(idx, file.name, sdocFormatSize(file.size), json.preview_url);
			})
			.catch(function(err) {
				dz.classList.remove('uploading');
				sdocSetNote(idx, err.message || 'Upload failed. Please try again or use a link instead.', true);
			});
	}

	function sdocClearFile(idx) {
		document.getElementById('sdoc_name_' + idx).value = '';
		document.getElementById('sdoc_link_' + idx).value = '';
		document.getElementById('sdoc_file_' + idx).value = '';
		sdocSetOpen(idx, '');
		document.getElementById('sdoc_chip_' + idx).style.display = 'none';
		document.getElementById('sdoc_dz_' + idx).style.display = 'block';
		sdocSetNote(idx, '', false);
	}

	function getSupportingDocs() {
		var rows = document.querySelectorAll('#supporting_docs_list .sdoc-row');
		var result = [];
		rows.forEach(function(row) {
			var name = row.querySelector('.sdoc-name').value.trim();
			var link = row.querySelector('.sdoc-link').value.trim();
			if (name || link) {
				result.push({
					document_name: name,
					link: link
				});
			}
		});
		return result;
	}

	function escSdoc(str) {
		return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	// ── Build payload ─────────────────────────────────────────────────────────────
	// statusOverride: pass a status string to set it explicitly (create/draft
	// flows), or the sentinel '__NO_CHANGE__' in edit mode to omit `status` from
	// the payload entirely so an in-progress workflow status is never clobbered
	// by a plain field edit (see postToApi, which strips the sentinel).
	function buildPayload(statusOverride) {
		var g = function(id) {
			return document.getElementById(id);
		};
		var v = function(id) {
			return g(id) ? g(id).value : '';
		};

		return {
			allocation_type: v('allocation_type'),
			cba_id: v('cba_id'),
			allocation_number: v('allocation_number'),
			fiscal_year: v('fiscal_year'),
			budget_version_id: v('budget_version_id'),
			cob_id: v('cob_id'),
			document_type: v('document_type'),
			category_type_id: v('category_type_id'),
			requesting_department_id: v('requesting_department_id'),
			requesting_region_id: v('requesting_region_id'),
			program_id: v('program_id'),
			project_id: v('project_id'),
			fund_source_id: v('fund_source_id'),
			requested_amount: v('requested_amount_raw'),
			allocated_amount: v('allocated_amount_raw'),
			// Itemized Budget Allocation Amount lines (fmd.budget_allocations.line_transaction, jsonb).
			line_transaction: JSON.stringify(collectBaaLines()),
			documents_complete: '1',
			status: statusOverride != null ? statusOverride : 'DRAFT',
			created_by: v('created_by'),
			multiple_node_id: v('multiple_node_id'),
			funding_status: 'PENDING',
			notification_sent: '0',
			project_description: v('project_description'),
			remarks: v('remarks'),
			is_bidding: g('is_bidding') ? (g('is_bidding').checked ? '1' : '0') : '0',
			category: v('budget_allocation_category_id'),
			file_data: getSupportingDocs(),
		};
	}

	// ── POST to API ───────────────────────────────────────────────────────────────
	async function postToApi(payload) {
		var nullableFKFields = [
			'budget_version_id',
			'cob_id',
			'requesting_region_id',
			'requesting_department_id',
			'project_id',
			'category',
			'category_type_id',
			'multiple_node_id',
		];
		nullableFKFields.forEach(function(key) {
			if (payload[key] === '' || payload[key] == null) delete payload[key];
		});

		if (payload.cba_id === '' || payload.cba_id == null) {
			payload.cba_id = null;
		} else {
			payload.cba_id = parseInt(payload.cba_id, 10);
		}

		var fileDocs = Array.isArray(payload.file_data) ? payload.file_data.filter(function(d) {
			return d.document_name || d.link;
		}) : [];
		delete payload.file_data;

		// ── Step 1: Save the main record first, WITHOUT touching multiple_node_id ─
		// Supporting documents are only uploaded after this succeeds — uploading
		// first (the old order) created the file rows / multiple_node_id group
		// up front, so a validation failure on the main record still left those
		// orphaned in the DB, and every retry produced another orphaned group.
		var mainJson;
		if (CBA_EDIT_MODE) {
			// create-only fields the update endpoint doesn't accept / shouldn't
			// be allowed to silently change on an existing record
			delete payload.created_by;
			if (payload.status === '__NO_CHANGE__') delete payload.status;
			mainJson = await cba_apiFetchPatch(`${API_BASE}/budget_allocation/update/${CBA_CONFIG.allocationId}`,
				payload);
		} else {
			mainJson = await cba_apiFetchPost(`${API_BASE}/budget_allocation/add`, payload);
		}

		if (!mainJson.success || fileDocs.length === 0) return mainJson;

		// ── Step 2: Upload supporting documents & attach multiple_node_id ─────────
		var allocationId = CBA_EDIT_MODE ? CBA_CONFIG.allocationId : mainJson.allocation_id;
		if (allocationId) {
			var createdBy = parseInt(document.getElementById('created_by').value, 10) || 1;
			try {
				var uploadJson = await cba_apiFetchPost(`${API_BASE}/upload/multi-upload`, {
					created_by: createdBy,
					file_data: fileDocs,
				});
				if (uploadJson.success && uploadJson.multiple_node_id) {
					await cba_apiFetchPatch(`${API_BASE}/budget_allocation/update/${allocationId}`, {
						multiple_node_id: uploadJson.multiple_node_id,
					});
					mainJson.multiple_node_id = uploadJson.multiple_node_id;
				}
			} catch (uploadErr) {
				// Non-fatal: the main record is already saved; log and continue
				// without attaching supporting documents.
				console.warn('[postToApi] Supporting documents upload failed:', uploadErr);
			}
		}

		return mainJson;
	}

	// ── Draft ─────────────────────────────────────────────────────────────────────
	async function saveDraft() {
		var btn = document.querySelector('.btn-draft');
		btn.disabled = true;
		btn.innerHTML = '<span>Saving&hellip;</span>';
		try {
			var json = await postToApi(buildPayload('DRAFT'));
			if (!json.success) throw new Error(json.message || 'Failed to save draft.');
			alert('Draft saved successfully.');
		} catch (err) {
			console.error('[saveDraft]', err);
			alert('Error saving draft: ' + err.message);
		} finally {
			btn.disabled = false;
			btn.innerHTML =
				'<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg> Save as Draft';
		}
	}

	// ── Submit label helpers ──────────────────────────────────────────────────────
	function getTypeLabel() {
		var type = document.getElementById('allocation_type').value || 'CBA';
		if (type === 'RFA') return {
			short: 'RFA',
			full: 'Request for Fund Allotment'
		};
		if (type === 'LAA') return {
			short: 'LAA',
			full: 'Letter of Advice of Allotment'
		};
		return {
			short: 'CBA',
			full: 'Certificate of Budget Allocation'
		};
	}

	function updateSubmitLabel() {
		var label = getTypeLabel();
		var span = document.getElementById('submit_btn_label');
		if (span) span.textContent = CBA_EDIT_MODE ? ('Update ' + label.short) : ('Submit ' + label.short);

		// "CBA Number" header card label — follows the selected allocation type
		// (CBA / RFA / LAA) instead of always reading "CBA Number".
		var numLabel = document.getElementById('cba_number_label');
		if (numLabel) numLabel.textContent = label.short + ' Number';
	}

	// ── Submit ────────────────────────────────────────────────────────────────────
	document.getElementById('cba_form').addEventListener('submit', async function(e) {
		e.preventDefault();

		var valid = true;
		this.querySelectorAll('[required]').forEach(function(field) {
			if (field.value.trim() === '') {
				validateField(field);
				valid = false;
			}
		});
		if (!valid) {
			alert('Please fill in all required fields marked with *');
			return;
		}

		// Budget Version is required on every line for RFA / LAA.
		if (!validateBudgetVersionsRequired()) {
			alert('Budget Version is required for ' + getTypeLabel().short +
				'. Please select one for every Budget Allocation line.');
			return;
		}

		// Supporting documents are required on create only — an existing
		// allocation being edited may already have its documents attached
		// (or intentionally removed via workflow), so don't block updates.
		if (!CBA_EDIT_MODE && getSupportingDocs().length === 0) {
			alert('Please attach at least one supporting document before submitting.');
			return;
		}

		var req = parseFloat(document.getElementById('requested_amount_raw').value) || 0;
		var alloc = parseFloat(document.getElementById('allocated_amount_raw').value) || 0;
		if (alloc > req && alloc > 0) {
			if (!confirm('Warning: Allocated amount exceeds requested amount. Continue?')) return;
		}
		var typeLabel = getTypeLabel();
		var confirmMsg = CBA_EDIT_MODE ?
			('Are you sure you want to save these changes to this ' + typeLabel.full + '?') :
			('Are you sure you want to submit this ' + typeLabel.full + '?');
		if (!confirm(confirmMsg)) return;

		var submitBtn = this.querySelector('button[type="submit"]');
		submitBtn.disabled = true;
		submitBtn.innerHTML = CBA_EDIT_MODE ? '<span>Saving&hellip;</span>' :
			'<span>Submitting&hellip;</span>';

		try {
			// In edit mode, don't force status back to FOR_REVIEW — pass null so
			// buildPayload's statusOverride is unused and the existing status on
			// the server is left alone (the update endpoint only touches fields
			// explicitly included in the allowed-fields list; status changes go
			// through the workflow action routes, not this form).
			var json = CBA_EDIT_MODE ?
				await postToApi(buildPayload('__NO_CHANGE__')) :
				await postToApi(buildPayload('FOR_REVIEW'));
			if (!json.success) throw new Error(json.message || (CBA_EDIT_MODE ? 'Update failed.' :
				'Submission failed.'));
			alert(CBA_EDIT_MODE ? (typeLabel.full + ' updated successfully!') : (typeLabel.full +
				' submitted successfully!'));
			var docType = document.getElementById('document_type').value;
			window.location.href = docType === 'COE' ? '/FMD/CoeBudgetAllocationDashboard' :
				'/FMD/BudgetAllocationDashboard';
		} catch (err) {
			console.error('[submitCBA]', err);
			alert((CBA_EDIT_MODE ? 'Update error: ' : 'Submission error: ') + err.message);
			submitBtn.disabled = false;
			var restoredLabel = CBA_EDIT_MODE ? ('Update ' + typeLabel.short) : ('Submit ' + typeLabel.short);
			submitBtn.innerHTML =
				'<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> <span id="submit_btn_label">' +
				restoredLabel + '</span>';
		}
	});

	// ── Block Enter outside textarea ──────────────────────────────────────────────
	document.getElementById('cba_form').addEventListener('keydown', function(e) {
		if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') e.preventDefault();
	});

	// ── Print Preview ─────────────────────────────────────────────────────────────
	// Routes to the correct endpoint based on allocation_type:
	//   CBA → /budget_allocation/print-docx/cba
	//   RFA → /budget_allocation/print-docx/rfa
	//   LAA → /budget_allocation/print-docx/laa
	async function printCBA() {
		var btn = document.querySelector('.btn-print');
		if (!btn) return;
		btn.disabled = true;
		btn.innerHTML = '<span>Generating&hellip;</span>';

		try {
			var g = function(id) {
				return document.getElementById(id);
			};
			var v = function(id) {
				return g(id) ? g(id).value : '';
			};
			var gt = function(id) {
				var el = g(id);
				return (el && el.options && el.selectedIndex >= 0) ?
					el.options[el.selectedIndex].text :
					(el ? el.value : '');
			};

			var allocType = v('allocation_type') || 'CBA';

			// ── Route selector ────────────────────────────────────────────────────
			var routeMap = {
				CBA: 'cba',
				RFA: 'rfa',
				LAA: 'laa'
			};
			var docxRoute = routeMap[allocType] || 'cba';
			var endpoint = `${API_BASE}/budget_allocation/print-docx/${docxRoute}`;

			// ── Base payload (fields common to all three types) ───────────────────
			var payload = {
				allocation_number: v('allocation_number'),
				allocation_type: allocType,
				fiscal_year: v('fiscal_year'),
				fiscal_year_label: (function() {
					var s = g('fiscal_year');
					return s && s.selectedIndex >= 0 ? (s.options[s.selectedIndex].dataset.fiscalYear ||
						'') : '';
				})(),
				fund_source_id: v('fund_source_id'),
				fund_source_name: g('fund_source_id') ? (g('fund_source_id').dataset.label || '') : '',
				document_type: v('document_type'),
				category_type_id: v('category_type_id'),
				category_type_name: gt('category_type_id'),
				// Budget Allocation Category (e.g. "Goods and Services") — printed
				// below "Sources of Fund" on CO documents (see gen_cba_docx.js).
				category_name: g('budget_allocation_category_id') ? (g('budget_allocation_category_id').dataset
					.label || '') : '',
				program_id: v('program_id'),
				program_name: g('program_id') ? (g('program_id').dataset.label || '') : '',
				project_id: v('project_id'),
				project_name: g('project_id') ? (g('project_id').dataset.label || '') : '',
				project_description: v('project_description'),
				requested_amount: v('requested_amount_raw'),
				allocated_amount: v('allocated_amount_raw'),
				requesting_department_id: v('requesting_department_id'),
				requesting_region_id: v('requesting_region_id'),
				remarks: v('remarks'),
				budget_version_id: v('budget_version_id'),
				// "Program of COB" — row 0's selected Budget Version's Program
				// Source name (or, for document_type = CO, its Fund ID's name —
				// see baaBvDisplayName()), shown in that row's
				// #bvi_program_source_0 info box once a Budget Version is
				// picked. gen_cba_docx.js prints this in parens right after the
				// Source of Fund name on CO documents.
				program_of_cob: (function() {
					var el = g('bvi_program_source_0');
					var text = el ? el.textContent : '';
					return (text && text !== '—') ? text : '';
				})(),
				// Controls the CO certificate's validity-clause wording — see
				// gen_cba_docx.js: checked = "for bidding purposes", unchecked =
				// "for whatever legal purpose it may serve".
				is_bidding: g('is_bidding') ? g('is_bidding').checked : false,
				// Edit mode: the record's created_at; add mode: null -> today's date.
				date: CBA_CREATED_AT ? new Date(CBA_CREATED_AT).toLocaleDateString('en-US', { month: 'long', day: allocType === 'LAA' ? 'numeric' : '2-digit', year: 'numeric' }) : null,
			};

			// ── RFA-specific fields ───────────────────────────────────────────────
			// gen_rfa_docx.js reads: rfa_no, payee_name, particulars, amount,
			// rfa_type, cert_correct_name/title/office, signatory_name_2..4,
			// project_name, total_estimated_cost, total_funds_available,
			// total_funded_to_date, balance, payments_outstanding, this_request,
			// total_payments, total_contract_cost, adjustment_additive/deductive,
			// total_allotment, funding_source (object with saro_no, saro_date,
			// or_no, or_date, corporate_receipts, ng_support, subsidy, equity,
			// agency_name), certFundsName/Title, recommendingName/Title,
			// approvedName/Title.
			if (allocType === 'RFA') {
				payload.rfa_no = v('allocation_number');
				payload.amount = v('requested_amount_raw');
				payload.particulars = v('project_description');
				// "To be filled-out by" is deliberately not set here: gen_rfa_docx.js /
				// print-preview.js derive it from requesting_department_type below
				// (MAIN -> FMD, REGIONAL -> Regional Offices).
				// Payee = the requesting department / region label
				payload.payee_name = (g('requesting_department_id') ? (g('requesting_department_id').dataset.label ||
						'') : '') ||
					(g('requesting_region_id') ? (g('requesting_region_id').dataset.label || '') : '') ||
					'';
				// Picks the RFA template: a MAIN office prints the STAFF form
				// ("(STAFF)", filled out by FMD), anything else the REGIONAL OFFICE
				// form. Same department-then-region precedence as the payee above; for
				// document_type = CO the department field is hidden, so a MAIN
				// office like GSD arrives via the Region / District Office list
				// (RC_ALL_DATA) instead of DEPT_ALL_RAW.
				payload.requesting_department_type = (function() {
					var findType = function(rows, id) {
						var row = (rows || []).find(function(d) {
							return String(d.department_id) === String(id);
						});
						return row ? (row.department_type || '') : '';
					};
					return (v('requesting_department_id') ? findType(DEPT_ALL_RAW, v(
							'requesting_department_id')) : '') ||
						(v('requesting_region_id') ? findType(RC_ALL_DATA, v('requesting_region_id')) : '');
				})();
				// RFA type line = "<CATEGORY> – Php 30 Million and below (<OFFICE>)", using
				// the selected Budget Allocation Category's name. print-preview.js and
				// gen_rfa_docx.js both use payload.rfa_type ahead of their default
				// ("GOODS AND SERVICES – …"), so setting it here changes the preview and
				// the DOCX together. No category selected -> left unset (default applies).
				(function() {
					var cat = String(payload.category_name || '').trim();
					if (!cat) return;
					var isStaff = String(payload.requesting_department_type || '').toUpperCase() === 'MAIN';
					payload.rfa_type = cat.toUpperCase() + ' – Php 30 Million and below (' +
						(isStaff ? 'STAFF' : 'REGIONAL OFFICE') + ')';
				})();
				// Pass through any pre-filled signatory fields if present on the form;
				// fall back to empty strings so the JS blanks them cleanly.
				payload.cert_correct_name = v('cert_correct_name') || '';
				payload.cert_correct_title = v('cert_correct_title') || '';
				payload.cert_correct_office = v('cert_correct_office') || '';
				payload.signatory_name_2 = v('signatory_name_2') || '';
				payload.signatory_position_2 = v('signatory_position_2') || '';
				payload.signatory_name_3 = v('signatory_name_3') || '';
				payload.signatory_position_3 = v('signatory_position_3') || '';
				payload.signatory_name_4 = v('signatory_name_4') || '';
				payload.signatory_position_4 = v('signatory_position_4') || '';
				// Row 0's selected Budget Version — same lookup baaBvSelect() already
				// uses (BAA_BV_DATA[idx], loaded from /budget_version_v2/all for that
				// row's Source of Fund). Now that endpoint also resolves each version's
				// SARO reference (bv.cob_id -> corporate_operating_budget.fund_id ->
				// fmd.fund_tracking.reference_number/date — see
				// BudgetVersionV2Services::fetchAlls()), so both the fund balance and
				// SARO No./Date print from real data instead of being left blank.
				var rfaBv = (BAA_BV_DATA[0] || []).find(function(b) {
					return String(b.budget_version_id) === String(v('budget_version_id'));
				});
				var rfaBvFundBalance = rfaBv ? (parseFloat(rfaBv.fund_balance) || 0) : 0;
				var rfaSaroRef = (rfaBv && rfaBv.fund_tracking) ? (rfaBv.fund_tracking.reference_number || '') : '';
				var rfaSaroDate = (rfaBv && rfaBv.fund_tracking) ? (rfaBv.fund_tracking.date || '') : '';

				// Financial reference fields — use raw amounts where applicable
				payload.total_estimated_cost = v('requested_amount_raw') || '0';
				// "Total funds made available for the year" is the fund balance of
				// the requested Budget Version, not the requested/allocated amount.
				payload.total_funds_available = rfaBvFundBalance > 0 ?
					rfaBvFundBalance :
					(v('allocated_amount_raw') || v('requested_amount_raw') || '0');
				payload.this_request = v('requested_amount_raw') || '0';
				payload.total_allotment = v('requested_amount_raw') || '0';
				payload.total_contract_cost = v('requested_amount_raw') || '0';
				// Funding source sub-object (SARO/OR details)
				payload.funding_source = {
					saro_no: rfaSaroRef,
					saro_date: rfaSaroDate,
					or_no: v('or_no') || '',
					or_date: v('or_date') || '',
					corporate_receipts: g('fs_corporate') ? g('fs_corporate').checked : false,
					ng_support: g('fs_ng_support') ? g('fs_ng_support').checked : false,
					subsidy: g('fs_subsidy') ? g('fs_subsidy').checked : false,
					equity: g('fs_equity') ? g('fs_equity').checked : false,
					agency_name: v('agency_name') || '',
				};
			}

			// ── LAA-specific fields ───────────────────────────────────────────────
			// gen_laa_docx.js reads: date (ISO), for_name, for_title, thru_name,
			// thru_title, subject, description, amount, sig_name, sig_title,
			// laa_number.
			if (allocType === 'LAA') {
				payload.laa_number = v('allocation_number');
				payload.amount = v('requested_amount_raw');
				payload.description = v('project_description');
				// Signatory fields — use dedicated hidden inputs if present, else blank
				payload.for_name = v('laa_for_name') || '';
				payload.for_title = v('laa_for_title') || '';
				payload.thru_name = v('laa_thru_name') || '';
				payload.thru_title = v('laa_thru_title') || '';
				payload.sig_name = v('laa_sig_name') || '';
				payload.sig_title = v('laa_sig_title') || '';
				// subject has a sensible default inside gen_laa_docx.js; only
				// override if there is a dedicated field on this form.
				if (v('laa_subject')) payload.subject = v('laa_subject');

				// office_name = the requesting department/region — same label RFA
				// uses for its payee. COE LAA (gen_coe_laa_docx.js) reads "To
				// transfer obligation made to <office> to cover the payment for
				// the <description>"; CO LAA (gen_laa_docx.js) prints it as the
				// addressee and in the "<office> to cover the <description>"
				// transfer text (not in the title, which is just the LAA number). The
				// generated DOCX derives it server-side, but the on-screen preview
				// never calls the server, so pass it here. The COE addressee
				// ("ENGR. ... / Regional Manager" + "Dear Engr. ...,") is resolved
				// by BAP_openPrintPreview() / the print-docx/laa route, not set here.
				payload.office_name = (g('requesting_department_id') ? (g('requesting_department_id').dataset.label ||
						'') : '') ||
					(g('requesting_region_id') ? (g('requesting_region_id').dataset.label || '') : '') ||
					'';
			}

			// ── Show the HTML preview dialog — "Download DOCX" / "View / Print PDF"
			// are wired up inside BAP_openPrintPreview() (print-preview.js).

			BAP_openPrintPreview({
				payload: payload,
				resolveSignatoryUrl: `${API_BASE}/budget_allocation/resolve-signatory`,
				authToken: CBA_TOKEN,
				onDownloadDocx: async function() {
					var json = await cba_apiFetchPost(endpoint, payload);
					if (!json.success) throw new Error(json.message || 'Server error');

					// ── Decode base64 → Blob → trigger download ───────────────────
					var binary = atob(json.data);
					var bytes = new Uint8Array(binary.length);
					for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
					var blob = new Blob([bytes], {
						type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
					});
					var url = URL.createObjectURL(blob);
					var a = document.createElement('a');
					a.href = url;
					a.download = json.filename || (payload.allocation_number || allocType) + '.docx';
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					URL.revokeObjectURL(url);
				},
			});

		} catch (err) {
			console.error('[printCBA]', err);
			alert('Print error: ' + err.message);
		} finally {
			btn.disabled = false;
			btn.innerHTML =
				'<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg> Print Preview';
		}
	}
</script>