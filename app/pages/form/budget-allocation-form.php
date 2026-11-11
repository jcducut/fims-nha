<?php

// Must stay: a file included from views/BudgetAllocationForm.php does NOT inherit that
// file's namespace, so without this line Config(), CurrentUserID() and GetUrl() below
// resolve in the global namespace and fatal with "Call to undefined function Config()".
namespace PHPMaker2024\FIMS;

// Initialize variables
$allocationId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$page_title = $allocationId ? 'Edit Budget Allocation Form' : 'Certificate of Budget Allocation (CBA) Form';
$current_date = date('Y-m-d');
$user_id = CurrentUserID();

// The requesting office of a memo request is the logged-in user's own Responsibility Center: uac.users.rc_id
// first (that column only), then the RC itself in fims.responsibility_code (its name and office type are
// what the RFA / LAA print preview shows). The API stores the same rc_id on the record, from the verified
// user (BudgetAllocationService::create), so the form never sends it.
$requestingRc = ['rc_id' => '', 'rc_code' => '', 'rc_name' => '', 'office_type' => ''];
try {
    $userRcId = trim((string) ExecuteScalar('SELECT rc_id FROM uac.users WHERE user_id = ' . (int) $user_id, 'DB'));
    if ($userRcId !== '') {
        $rcRow = ExecuteRow(
            'SELECT rc_id, rc_code, rc_name, office_type FROM fims.responsibility_code WHERE rc_id = ' . QuotedValue($userRcId, DataType::STRING, 'DB'),
            'DB'
        );
        if ($rcRow) {
            $requestingRc = [
                'rc_id'       => (string) $rcRow['rc_id'],
                'rc_code'     => (string) ($rcRow['rc_code'] ?? ''),
                'rc_name'     => (string) ($rcRow['rc_name'] ?? ''),
                'office_type' => (string) ($rcRow['office_type'] ?? ''),
            ];
        }
    }
} catch (\Throwable $e) {
    // can't tell which RC this user belongs to: the print preview just has no requesting office, never an error page
}

// Generate CBA Number (placeholder — regenerated client-side unless editing)
$cba_number = 'CBA#'.date('Y-m-d').'-'.str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

// FIMS's own API (/api → app/routes/budget_allocation.php). API.FMD_URL is the separate
// FMD application's API and is not a FIMS config key.
$apiBase = Config('API.FIMS_URL');

// Rendering mode. The including page sets $cbaAsModal = true before include() (see
// app/pages/memo-req-page.php) to get the form inside an overlay modal that it opens with
// window.cbaModal.open(). Left unset, the form renders as a normal page, which is what
// views/BudgetAllocationForm.php (the "Memo Request Form" menu item) relies on.
$cbaAsModal = !empty($cbaAsModal);

?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet" />

<?php if ($cbaAsModal): ?>
<style>
	/* SAFETY NET — the essential overlay / modal rules of _shared/css/nha-fims.css, repeated here on purpose.
	   What makes this form a modal (hidden until opened, fixed over the page, centred) used to live ONLY in that
	   shared stylesheet. On a computer where it is blocked, 404s (the app in a sub-folder) or is an older copy
	   cached by the browser, the whole form was drawn INLINE in the page — about 2,500px of it — and never became
	   a modal. These rules are deliberately LOWER in specificity than the shared ones (no .nha-root prefix), so
	   whenever the shared stylesheet is present it still wins and nothing looks different. */
	.nha-overlay {
		display: none;
		position: fixed;
		inset: 0;
		z-index: 9050;
		box-sizing: border-box;
		padding: 16px;
		align-items: center;
		justify-content: center;
		background: rgba(15, 32, 69, .55);
	}

	.nha-overlay.open {
		display: flex;
	}

	.nha-modal {
		display: flex;
		flex-direction: column;
		width: 100%;
		max-width: 680px;
		max-height: 90vh;
		overflow: hidden;
		background: var(--afims-surface, #FFFFFF);
		border-radius: 14px;
		box-shadow: 0 24px 64px rgba(0, 0, 0, .22);
	}

	.nha-modal-xl {
		max-width: 1100px;
	}

	.nha-modal-hdr {
		display: flex;
		flex-shrink: 0;
		align-items: center;
		justify-content: space-between;
		padding: 16px 20px;
		background: var(--afims-heading-fill, #000E3E);
	}

	.nha-modal-title {
		margin: 0;
		font-size: 14px;
		font-weight: 700;
		color: var(--afims-pure-white, #FFFFFF);
	}

	.nha-modal-close {
		width: 28px;
		height: 28px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 18px;
		line-height: 1;
		color: var(--afims-pure-white, #FFFFFF);
		cursor: pointer;
		background: rgba(255, 255, 255, .15);
		border: 1px solid rgba(255, 255, 255, .25);
		border-radius: 6px;
	}

	.nha-modal-body {
		flex: 1;
		padding: 20px;
		overflow-y: auto;
	}

	/* Modal mode - the form lives inside .nha-modal-body. The base layer below was written
	   for a full page, so drop its page-level width/padding. */
	.nha-root .cba-modal { position: relative; }
	.nha-root .cba-modal .nha-modal-body { padding: 18px 22px; }
	.nha-root .cba-modal .cba-form-container { max-width: none; margin: 0; padding: 0; }
</style>

<!-- The wrapper makes the .nha-overlay / .nha-modal rules apply wherever this form is included
     (they are scoped to .nha-root); padding/background are neutralised so it takes no space. -->
<div class="nha-root" style="padding:0;background:none;">
<div id="cba-overlay" class="nha-overlay">
	<div class="nha-modal nha-modal-xl cba-modal">
		<div class="nha-modal-hdr">
			<h3 class="nha-modal-title" id="cba-modal-title">New Memo Request</h3>
			<button type="button" class="nha-modal-close" id="cba-modal-close" onclick="cbaCancel()" aria-label="Close">&times;</button>
		</div>
		<div class="nha-modal-body" id="cba-modal-body">
<?php endif; ?>
<div class="cba-form-container">

	<style>
		/* ══════════════════════════════════════════════════════════════════════════
		   BASE LAYER — layout, fields, buttons, cards
		   This form was written for the FMD app's stylesheet, which FIMS does not
		   load, so none of these classes (.form-row, .form-section, .form-input,
		   .error-box …) had any rules. Everything is scoped under .cba-form-container
		   so it cannot leak into the rest of the app, and uses the shared --afims-*
		   tokens so it follows the light / dark theme like the other FIMS pages.
		   The widget-specific rules (combobox list, budget lines, document rows,
		   Print button) live in the <style> blocks further down.
		══════════════════════════════════════════════════════════════════════════ */
		.cba-form-container {
			font-family: 'IBM Plex Sans', sans-serif;
			/* The app forces input / select / textarea / button text to
			   calc(1em * var(--input-scale)) !important, i.e. "whatever the parent
			   is, times the user's scale". So the size of every control in this form
			   is set HERE, once — a font-size on the controls themselves is ignored. */
			font-size: 14px;
			max-width: 1040px;
			margin: 0 auto;
			padding: 4px 0 40px;
			color: var(--afims-text, #1A1B21);
		}

		.cba-form-container *,
		.cba-form-container *::before,
		.cba-form-container *::after {
			box-sizing: border-box;
		}

		/* ── Number card ─────────────────────────────────────────────────────── */
		.cba-form-container .form-header {
			margin-bottom: 18px;
		}

		.cba-form-container .cba-number-card {
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 16px;
			padding: 16px 22px;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-left: 4px solid var(--afims-primary, #0035A9);
			border-radius: 12px;
			box-shadow: 0 1px 2px rgba(15, 32, 69, .05);
		}

		.cba-form-container .cba-number-label {
			margin: 0 0 4px;
			font-size: 10.5px;
			font-weight: 700;
			letter-spacing: .08em;
			text-transform: uppercase;
			color: var(--afims-text-muted, #62657E);
		}

		.cba-form-container .cba-number-value {
			margin: 0;
			font-family: 'IBM Plex Mono', monospace;
			font-size: 22px;
			font-weight: 600;
			letter-spacing: -.01em;
			color: var(--afims-heading, #000E3E);
		}

		/* ── Section cards ───────────────────────────────────────────────────── */
		.cba-form-container .form-section {
			margin-bottom: 18px;
			padding: 22px 24px 24px;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 12px;
			box-shadow: 0 1px 2px rgba(15, 32, 69, .05);
		}

		.cba-form-container .section-header {
			display: flex;
			align-items: center;
			gap: 10px;
			margin: 0 0 18px;
			padding-bottom: 12px;
			border-bottom: 1px solid var(--afims-border, #E4ECF2);
			font-family: inherit;
			font-size: 15px;
			font-weight: 700;
			line-height: 1.3;
			color: var(--afims-heading, #000E3E);
		}

		.cba-form-container .section-header::before {
			content: '';
			flex-shrink: 0;
			width: 4px;
			height: 16px;
			border-radius: 2px;
			background: var(--afims-primary, #0035A9);
		}

		/* ── Grid ────────────────────────────────────────────────────────────── */
		.cba-form-container .form-row {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 18px 20px;
			align-items: start;
			margin-bottom: 18px;
		}

		.cba-form-container .form-row.full-width {
			grid-template-columns: minmax(0, 1fr);
		}

		/* three comboboxes side by side; auto-fit drops to two / one column as the room shrinks */
		.cba-form-container .form-row.cols-3 {
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
		}

		/* vendor row: Vendor Name | Vendor Code | Vendor Type, then Address (and the hint) on rows of their own */
		.cba-form-container .form-row.cols-vendor {
			grid-template-columns: repeat(3, minmax(0, 1fr));
		}

		@media (max-width: 960px) {
			.cba-form-container .form-row.cols-vendor {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}
		}

		@media (max-width: 760px) {
			.cba-form-container .form-row.cols-vendor {
				grid-template-columns: minmax(0, 1fr);
			}
		}

		/* a field that takes the whole row of a multi-column .form-row (the vendor's Address) */
		.cba-form-container .form-row > .form-group-wide {
			grid-column: 1 / -1;
		}

		/* option list of the Program / Sub-Program / Project comboboxes (JS toggles display) */
		.cba-form-container .pc-dropdown {
			display: none;
			position: absolute;
			top: 100%;
			left: 0;
			right: 0;
			z-index: 9999;
			max-height: 220px;
			overflow-y: auto;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-top: none;
			border-radius: 0 0 6px 6px;
			box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
		}

		.cba-form-container .form-section > .form-row:last-child {
			margin-bottom: 0;
		}

		.cba-form-container .form-group {
			display: flex;
			flex-direction: column;
			gap: 6px;
			min-width: 0;
			position: relative;
		}

		/* ── Labels, hints, errors ───────────────────────────────────────────── */
		/* custom.css sets `body.afims-ui .content-wrapper .form-label` (14px / 500 / its own
		   colour); match its specificity so the label hierarchy below actually applies. */
		body.afims-ui .content-wrapper .cba-form-container .form-label {
			display: block;
			margin: 0;
			font-size: 12px;
			font-weight: 600;
			letter-spacing: .04em;
			text-transform: uppercase;
			color: var(--afims-text, #1A1B21);
		}

		body.afims-ui .content-wrapper .cba-form-container .has-error > .form-label {
			color: var(--afims-danger, #DC3545);
		}

		.cba-form-container .required {
			margin-left: 2px;
			color: var(--afims-danger, #DC3545);
		}

		.cba-form-container .input-hint {
			margin: 0;
			font-size: 11.5px;
			line-height: 1.5;
			color: var(--afims-text-muted, #62657E);
		}

		/* Program / Sub-Program / Project: text typed instead of picked is a NEW one, created when the form is saved */
		.cba-form-container .input-hint.pc-hint-new {
			color: var(--afims-primary, #0035A9);
			font-weight: 600;
		}

		.cba-form-container .pc-dropdown .pc-new {
			padding: 10px 14px;
			font-size: 13px;
			color: var(--afims-primary, #0035A9);
			background: var(--afims-surface-subtle, #F4F7FA);
		}

		/* Hidden until JS adds .visible — the text is static markup, so without
		   this rule every "Failed to load…" message showed on a healthy page. */
		.cba-form-container .error-box {
			display: none;
			margin: 0;
			padding: 8px 12px;
			font-size: 12px;
			line-height: 1.5;
			color: var(--afims-danger, #DC3545);
			background: var(--afims-danger-soft, #FAE1E3);
			border: 1px solid color-mix(in srgb, var(--afims-danger, #DC3545) 25%, transparent);
			border-radius: 8px;
		}

		.cba-form-container .error-box.visible {
			display: block;
		}

		.cba-form-container .form-section > .error-box.visible {
			margin-bottom: 12px;
		}

		/* ── Fields ──────────────────────────────────────────────────────────── */
		.cba-form-container .form-input,
		.cba-form-container .form-select,
		.cba-form-container .form-textarea {
			display: block;
			width: 100%;
			min-height: 40px;
			padding: 9px 12px;
			font-family: 'IBM Plex Sans', sans-serif;
			font-weight: 400;
			line-height: 1.4;
			color: var(--afims-text, #1A1B21);
			background-color: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 8px;
			outline: none;
			transition: border-color .15s, box-shadow .15s, background-color .15s;
		}

		.cba-form-container .form-select {
			appearance: none;
			-webkit-appearance: none;
			padding-right: 34px;
			cursor: pointer;
			background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237a7d92' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
			background-repeat: no-repeat;
			background-position: right 12px center;
			background-size: 14px;
		}

		.cba-form-container .form-textarea {
			min-height: 104px;
			line-height: 1.55;
			resize: vertical;
		}

		.cba-form-container .form-input::placeholder,
		.cba-form-container .form-textarea::placeholder {
			color: var(--afims-text-disabled, #A9AAB8);
		}

		.cba-form-container .form-input:hover:not(:disabled):not(:focus),
		.cba-form-container .form-select:hover:not(:disabled):not(:focus),
		.cba-form-container .form-textarea:hover:not(:disabled):not(:focus) {
			border-color: color-mix(in srgb, var(--afims-heading-fill, #000E3E) 35%, var(--afims-border, #E4ECF2));
		}

		.cba-form-container .form-input:focus,
		.cba-form-container .form-select:focus,
		.cba-form-container .form-textarea:focus {
			border-color: var(--afims-heading-fill, #000E3E);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-heading-fill, #000E3E) 12%, transparent);
		}

		.cba-form-container .form-input:disabled,
		.cba-form-container .form-select:disabled,
		.cba-form-container .form-textarea:disabled {
			color: var(--afims-text-disabled, #A9AAB8);
			background-color: var(--afims-surface-subtle, #F4F7FA);
			cursor: not-allowed;
		}

		/* read-only fields (the vendor's address): shown, filled in by script, not typed */
		.cba-form-container .form-input[readonly],
		.cba-form-container .form-textarea[readonly] {
			background-color: var(--afims-surface-subtle, #F4F7FA);
			cursor: default;
		}

		.cba-form-container .form-textarea.vendor-address {
			min-height: 64px;
			resize: none;
		}

		/* validation state — classes toggled by validateField() */
		.cba-form-container .form-input.error,
		.cba-form-container .form-select.error,
		.cba-form-container .form-textarea.error {
			border-color: var(--afims-danger, #DC3545);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-danger, #DC3545) 12%, transparent);
		}

		.cba-form-container .form-input.success,
		.cba-form-container .form-select.success,
		.cba-form-container .form-textarea.success {
			border-color: color-mix(in srgb, var(--afims-success, #11A752) 60%, var(--afims-border, #E4ECF2));
		}

		/* custom.css restyles every .form-select under .content-wrapper with higher
		   specificity (36px tall, stronger border, blue focus ring), which left the
		   selects shorter than the text inputs beside them. This block matches that
		   specificity so selects and inputs look identical. The compact .baa-cf-sel
		   filter selects keep their own styling. */
		body.afims-ui .content-wrapper .cba-form-container .form-select:not(.baa-cf-sel) {
			min-height: 40px;
			padding: 9px 34px 9px 12px;
			border-color: var(--afims-border, #E4ECF2);
			border-radius: 8px;
			box-shadow: none;
		}

		/* the dark theme resets the select background, which removes the chevron */
		html.afims-dark[data-bs-theme="dark"] .cba-form-container .form-select:not(.baa-cf-sel) {
			background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23b5b8cc' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
			background-repeat: no-repeat;
			background-position: right 12px center;
			background-size: 14px;
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select:not(.baa-cf-sel):hover:not(:disabled):not(:focus) {
			border-color: color-mix(in srgb, var(--afims-heading-fill, #000E3E) 35%, var(--afims-border, #E4ECF2));
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select:not(.baa-cf-sel):focus {
			border-color: var(--afims-heading-fill, #000E3E);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-heading-fill, #000E3E) 12%, transparent);
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select:not(.baa-cf-sel):disabled {
			color: var(--afims-text-disabled, #A9AAB8);
			background-color: var(--afims-surface-subtle, #F4F7FA);
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select.error:not(.baa-cf-sel) {
			border-color: var(--afims-danger, #DC3545);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-danger, #DC3545) 12%, transparent);
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select.success:not(.baa-cf-sel) {
			border-color: color-mix(in srgb, var(--afims-success, #11A752) 60%, var(--afims-border, #E4ECF2));
		}

		/* peso prefix inside amount inputs (budget lines) */
		.cba-form-container .form-group.has-prefix .input-prefix {
			position: absolute;
			left: 13px;
			bottom: 0;
			z-index: 1;
			display: flex;
			align-items: center;
			height: 40px;
			font-size: 13px;
			font-weight: 600;
			color: var(--afims-text-muted, #62657E);
			pointer-events: none;
		}

		.cba-form-container .form-group.has-prefix .form-input {
			padding-left: 30px;
		}

		.cba-form-container .amt-input {
			font-weight: 500;
			font-variant-numeric: tabular-nums;
			text-align: right;
		}

		/* async-loaded lookups: spinner while .is-loading (toggled by setLoading()) */
		.cba-form-container .select-loading-wrapper {
			position: relative;
		}

		.cba-form-container .select-loading-wrapper.is-loading::after {
			content: '';
			position: absolute;
			top: 50%;
			right: 34px;
			z-index: 2;
			width: 15px;
			height: 15px;
			margin-top: -7.5px;
			border: 2px solid var(--afims-border, #E4ECF2);
			border-top-color: var(--afims-heading-fill, #000E3E);
			border-radius: 50%;
			animation: cba-spin .75s linear infinite;
			pointer-events: none;
		}

		.cba-form-container .select-loading-wrapper:not(:has(select)).is-loading::after {
			right: 12px;
		}

		/* checkbox rows ("Is this for bidding?") */
		.cba-form-container input[type="checkbox"] {
			accent-color: var(--afims-heading-fill, #000E3E);
		}

		body.afims-ui .content-wrapper .cba-form-container .form-label:has(> input[type="checkbox"]) {
			font-size: 14px;
			font-weight: 600;
			letter-spacing: 0;
			text-transform: none;
		}

		.cba-form-container .form-group:has(#is_bidding) {
			padding: 12px 14px;
			background: var(--afims-surface-subtle, #F4F7FA);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 10px;
		}

		/* ── Budget allocation lines: each line becomes a tinted card ────────── */
		.cba-form-container .baa-line {
			margin-bottom: 14px;
			padding: 16px 48px 16px 16px;
			background: var(--afims-surface-subtle, #F4F7FA);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 10px;
		}

		.cba-form-container .baa-remove-line {
			top: 12px;
			right: 12px;
		}

		/* ── Buttons ─────────────────────────────────────────────────────────── */
		/* Size / padding / radius come from custom.css (`body.afims-ui .content-wrapper .btn`:
		   36px tall) so these buttons match every other FIMS button; only the font and
		   the 1px border are set here. */
		.cba-form-container .btn {
			font-family: 'IBM Plex Sans', sans-serif;
			font-weight: 600;
			cursor: pointer;
			border: 1px solid transparent;
		}

		.cba-form-container .btn:disabled {
			opacity: .55;
			cursor: not-allowed;
		}

		.cba-form-container .btn-icon {
			flex-shrink: 0;
			width: 16px;
			height: 16px;
		}

		.cba-form-container .btn-primary {
			color: var(--afims-pure-white, #FFFFFF);
			background: var(--afims-heading-fill, #000E3E);
			border-color: var(--afims-heading-fill, #000E3E);
		}

		.cba-form-container .btn-primary:hover:not(:disabled) {
			opacity: .9;
		}

		.cba-form-container .btn-secondary {
			color: var(--afims-text, #1A1B21);
			background: var(--afims-surface, #FFFFFF);
			border-color: var(--afims-border, #E4ECF2);
		}

		.cba-form-container .btn-secondary:hover:not(:disabled) {
			background: var(--afims-surface-subtle, #F4F7FA);
		}

		.cba-form-container .btn-draft {
			color: var(--afims-heading, #000E3E);
			background: var(--afims-surface, #FFFFFF);
			border-color: color-mix(in srgb, var(--afims-heading-fill, #000E3E) 35%, var(--afims-border, #E4ECF2));
		}

		.cba-form-container .btn-draft:hover:not(:disabled) {
			background: var(--afims-surface-subtle, #F4F7FA);
		}

		.cba-form-container .form-actions {
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 12px;
			margin-bottom: 18px;
			padding: 16px 20px;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 12px;
			box-shadow: 0 1px 2px rgba(15, 32, 69, .05);
		}

		.cba-form-container .btn-group {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 10px;
		}

		/* custom.css forces a 6px radius on .btn-group children with !important, which
		   made Save / Print / Submit differ from Cancel. Use the app's own radius. */
		.cba-form-container .btn-group > .btn:not(#afims-btn) {
			border-radius: var(--ui-radius, 8px) !important;
		}

		/* ── Reminders card ──────────────────────────────────────────────────── */
		.cba-form-container .info-card {
			padding: 18px 22px;
			background: color-mix(in srgb, var(--afims-primary, #0035A9) 6%, var(--afims-surface, #FFFFFF));
			border: 1px solid color-mix(in srgb, var(--afims-primary, #0035A9) 18%, var(--afims-border, #E4ECF2));
			border-radius: 12px;
		}

		.cba-form-container .info-card-header {
			display: flex;
			align-items: flex-start;
			gap: 14px;
		}

		.cba-form-container .info-card-icon {
			flex-shrink: 0;
			width: 22px;
			height: 22px;
			margin-top: 1px;
			color: var(--afims-primary, #0035A9);
		}

		.cba-form-container .info-card-title {
			margin: 0 0 6px;
			font-size: 13px;
			font-weight: 700;
			color: var(--afims-heading, #000E3E);
		}

		.cba-form-container .info-list {
			margin: 0;
			padding-left: 18px;
			font-size: 12.5px;
			line-height: 1.75;
			color: var(--afims-text-muted, #62657E);
		}

		/* ── Narrow screens ──────────────────────────────────────────────────── */
		@media (max-width: 760px) {
			.cba-form-container .form-row {
				grid-template-columns: minmax(0, 1fr);
			}

			.cba-form-container .form-section {
				padding: 18px 16px 20px;
			}

			.cba-form-container .baa-line {
				padding-right: 44px;
			}

			.cba-form-container .form-actions,
			.cba-form-container .btn-group {
				flex-direction: column;
				align-items: stretch;
			}
		}
	</style>

	<style>
		/* Spin animation of the small per-field lookup spinners (.select-loading-wrapper.is-loading above).
		   The full-page "loading" is the app's shared PagePreloader (app/preloader.php), not drawn here. */
		@keyframes cba-spin {
			to {
				transform: rotate(360deg);
			}
		}
	</style>

	<style>
		/* The "Submit Request" decision pop-up (cbaAskDecision). It is appended to <body> — outside .cba-form-container
		   and outside the memo list's modal, so the modal re-loading its form can't wipe it — and therefore carries its
		   own, unscoped styles. z-index sits above the memo modal's overlay (9050). */
		.cbadec-overlay {
			position: fixed;
			inset: 0;
			z-index: 9200;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 16px;
			box-sizing: border-box;
			background: rgba(15, 32, 69, .45);
			font-family: 'IBM Plex Sans', sans-serif;
		}

		.cbadec-box {
			width: 100%;
			max-width: 440px;
			max-height: 100%;
			overflow-y: auto;
			background: var(--afims-surface, #FFFFFF);
			color: var(--afims-text, #1A1B21);
			border-radius: 12px;
			box-shadow: 0 18px 48px rgba(0, 0, 0, .28);
		}

		.cbadec-hdr {
			padding: 16px 20px;
			border-bottom: 1px solid var(--afims-border, #E4ECF2);
		}

		.cbadec-title {
			margin: 0;
			font-size: 16px;
			font-weight: 600;
			color: var(--afims-heading, #000E3E);
		}

		.cbadec-body {
			padding: 16px 20px 6px;
		}

		.cbadec-text {
			margin: 0 0 12px;
			font-size: 13px;
			line-height: 1.5;
		}

		.cbadec-current {
			margin: 0 0 12px;
			font-size: 12px;
			color: var(--afims-text-secondary, #475569);
		}

		.cbadec-opt {
			display: block;
			width: 100%;
			margin: 0 0 10px;
			padding: 12px 14px;
			text-align: left;
			font-family: inherit;
			color: inherit;
			cursor: pointer;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 10px;
			transition: border-color .15s, box-shadow .15s, background .15s;
		}

		.cbadec-opt:hover,
		.cbadec-opt:focus-visible {
			outline: none;
			border-color: var(--afims-heading-fill, #000E3E);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-heading-fill, #000E3E) 12%, transparent);
		}

		.cbadec-opt-primary {
			border-color: var(--afims-heading-fill, #000E3E);
			background: color-mix(in srgb, var(--afims-heading-fill, #000E3E) 6%, var(--afims-surface, #FFFFFF));
		}

		.cbadec-opt-title {
			display: block;
			font-size: 14px;
			font-weight: 600;
			color: var(--afims-heading, #000E3E);
		}

		.cbadec-opt-desc {
			display: block;
			margin-top: 3px;
			font-size: 12px;
			line-height: 1.45;
			color: var(--afims-text-secondary, #475569);
		}

		.cbadec-opt-desc strong {
			white-space: nowrap; /* a status name ("For Preview") never breaks across two lines */
		}

		.cbadec-ftr {
			display: flex;
			justify-content: flex-end;
			padding: 10px 20px 16px;
		}

		.cbadec-cancel {
			padding: 7px 16px;
			font-family: inherit;
			font-size: 13px;
			font-weight: 600;
			color: var(--afims-text, #1A1B21);
			cursor: pointer;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 8px;
		}

		.cbadec-cancel:hover,
		.cbadec-cancel:focus-visible {
			outline: none;
			background: var(--afims-surface-subtle, #F4F7FA);
		}
	</style>

	<!-- Form Header: the CBA number / date card. Hidden while creating — numbering is not in use yet, so
	     nothing is shown or saved for it (see buildPayload). Still shown when editing a saved record. -->
	<div class="form-header"<?php echo $allocationId ? '' : ' style="display:none;"'; ?>>
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
			<h2 class="section-header" id="program_project_section_title">Program, Sub-Program and Project Details</h2>

			<div class="form-row cols-3" id="program_project_row">
				<!-- Program → GET /program/all (searchable combobox) -->
				<div class="form-group">
					<label for="prog_search" class="form-label">Program</label>
					<div class="select-loading-wrapper is-loading" id="program_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="prog_search" class="form-input" placeholder="Search program&hellip;"
								autocomplete="off" disabled>
							<div id="prog_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="program_code" name="program_code" value="">
					</div>
					<div class="error-box" id="program_error">Failed to load programs. Please refresh the page.</div>
					<p class="input-hint" id="program_hint">Pick an existing program, or type a new name to create one.</p>
				</div>

				<!-- Sub-Program → GET /sub_program/all?program_code=… (searchable combobox, needs a Program) -->
				<div class="form-group">
					<label for="subprog_search" class="form-label">Sub-Program</label>
					<div class="select-loading-wrapper" id="sub_program_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="subprog_search" class="form-input" placeholder="Select a program first"
								autocomplete="off" disabled>
							<div id="subprog_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="sub_program_code" name="sub_program_code" value="">
					</div>
					<div class="error-box" id="sub_program_error">Failed to load sub-programs. Please select the program again.</div>
					<p class="input-hint" id="sub_program_hint">Select a program first.</p>
				</div>

				<!-- Project → GET /project/all?program_code=…&sub_program_code=… (searchable combobox, needs a Sub-Program) -->
				<div class="form-group">
					<label for="proj_search" class="form-label">Project</label>
					<div class="select-loading-wrapper" id="project_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="proj_search" class="form-input" placeholder="Select a sub-program first"
								autocomplete="off" disabled>
							<div id="proj_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="project_code" name="project_code" value="">
					</div>
					<div class="error-box" id="project_error">Failed to load projects. Please select the sub-program again.</div>
					<p class="input-hint" id="project_hint">Select a sub-program first.</p>
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

		<!-- ── Vendor ─────────────────────────────────────────────────── -->
		<div class="form-section" id="vendor_section">
			<h2 class="section-header">Vendor Details</h2>

			<div class="form-row cols-vendor" id="vendor_row">
				<!-- Vendor Name → GET /vendors/all?active=1 (searchable combobox; saved as vendor_id).
				     A name that is not in the list makes Vendor Code, Vendor Type and Address pop up so a new vendor
				     can be added. -->
				<div class="form-group">
					<label for="vendor_search" class="form-label">Vendor Name</label>
					<div class="select-loading-wrapper is-loading" id="vendor_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="vendor_search" class="form-input" placeholder="Search vendor&hellip;"
								maxlength="500" autocomplete="off" disabled>
							<div id="vendor_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="vendor_id" name="vendor_id" value="">
					</div>
					<div class="error-box" id="vendor_error">Failed to load vendors. Please refresh the page.</div>
				</div>

				<!-- Vendor Code: read-only for a picked vendor, required + editable for a new one -->
				<div class="form-group" id="vendor_code_group" style="display:none;">
					<label for="vendor_code" class="form-label">Vendor Code <span class="required" id="vendor_code_required"
							style="display:none;">*</span></label>
					<input type="text" id="vendor_code" class="form-input" maxlength="50" autocomplete="off" readonly
						tabindex="-1">
				</div>

				<!-- Vendor Type → GET /vendors/types. A dropdown (optional) for a new vendor; a read-only box showing
				     the type of a picked vendor. -->
				<div class="form-group" id="vendor_type_group" style="display:none;">
					<label for="vendor_type" class="form-label">Vendor Type</label>
					<select id="vendor_type" class="form-select">
						<option value="">Select vendor type</option>
					</select>
					<input type="text" id="vendor_type_view" class="form-input" autocomplete="off" readonly tabindex="-1"
						style="display:none;">
					<div class="error-box" id="vendor_type_error">Failed to load vendor types. You can still add the vendor
						without one.</div>
				</div>

				<!-- Address: read-only for a picked vendor (from its record), editable for a new one -->
				<div class="form-group form-group-wide" id="vendor_address_group" style="display:none;">
					<label for="vendor_address" class="form-label">Address</label>
					<textarea id="vendor_address" class="form-textarea vendor-address" rows="2" readonly
						tabindex="-1"></textarea>
				</div>

				<p class="input-hint form-group-wide" id="vendor_new_hint" style="display:none;">Not in the vendor list &mdash;
					enter its code, type and address and it will be added as a new vendor when you save.</p>
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
				<!-- Value = fiscal_yr (the PK of fims.fiscal_year, saved as budget_allocation.fiscal_year); shown as the year -->
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
				<!-- Category → GET /budget_category/all (searchable combobox) -->
				<div class="form-group">
					<label for="cat_search" class="form-label">Category</label>
					<div class="select-loading-wrapper is-loading" id="category_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="cat_search" class="form-input" placeholder="Search category&hellip;"
								autocomplete="off" disabled>
							<div id="cat_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="category_id" name="category_id" value="">
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
								placeholder="Search approved CBAs by request ref no. or program…" autocomplete="off"
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
				Choose the Type of Fund, then the COB (its account code) this allocation is drawn from. Only approved budget
				requests are listed, and both lists follow the Program, Sub-Program and Project chosen above.
			</p>

			<div class="error-box" id="type_of_fund_error">Failed to load types of fund. Please refresh the page.</div>
			<div class="error-box" id="cob_error">Failed to load COBs. Please refresh the page.</div>

			<div id="baa_lines_container"></div>

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

			<!-- Summed totals of the lines (requested_amount / allocated_amount). The itemized lines, each with its COB,
			     are submitted separately as `line_transaction` (jsonb) — see collectBaaLines() / buildPayload(). -->
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

		<!-- ── Purchase Request / Purchase Order ───────────────────── -->
		<div class="form-section" id="pcr_section">
			<h2 class="section-header" id="pcr_title">Purchase Request</h2>

			<!-- CBA (and CFA / no type chosen yet) → Purchase Request; RFA / LAA → Purchase Order. Both pairs stay in
			     the form, but only the one that is showing is saved: pr_number + pr_amount, or po_number + po_amount. -->
			<div class="form-row" id="pr_row" style="margin-bottom:0;">
				<div class="form-group">
					<label for="pr_number" class="form-label">Purchase Request Number</label>
					<input type="text" id="pr_number" class="form-input" maxlength="50" autocomplete="off"
						placeholder="Enter the purchase request number">
				</div>
				<div class="form-group has-prefix">
					<label for="pr_amount" class="form-label">Purchase Request Amount</label>
					<span class="input-prefix">&#8369;</span>
					<input type="text" id="pr_amount" class="form-input amt-input" placeholder="0.00" autocomplete="off">
					<input type="hidden" id="pr_amount_raw" value="">
				</div>
			</div>

			<div class="form-row" id="po_row" style="display:none;margin-bottom:0;">
				<div class="form-group">
					<label for="po_number" class="form-label">Purchase Order Number</label>
					<input type="text" id="po_number" class="form-input" maxlength="50" autocomplete="off"
						placeholder="Enter the purchase order number">
				</div>
				<div class="form-group has-prefix">
					<label for="po_amount" class="form-label">Purchase Order Amount</label>
					<span class="input-prefix">&#8369;</span>
					<input type="text" id="po_amount" class="form-input amt-input" placeholder="0.00" autocomplete="off">
					<input type="hidden" id="po_amount_raw" value="">
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
		<input type="hidden" id="multiple_node_id" name="multiple_node_id" value="">
		<input type="hidden" id="allocation_id" name="allocation_id"
			value="<?php echo $allocationId ? (int) $allocationId : ''; ?>">

		<!-- Form Actions -->
		<div class="form-actions">
			<button type="button" class="btn btn-secondary" onclick="cbaCancel()">
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
					<span id="submit_btn_label">Submit Request</span>
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
<?php if ($cbaAsModal): ?>
		</div><!-- /.nha-modal-body -->
	</div><!-- /.nha-modal -->
</div><!-- /#cba-overlay -->
</div><!-- /.nha-root (modal wrapper) -->
<?php endif; ?>

<script
	src="<?php echo GetUrl('/_shared/FMD/budget-allocation-print/js/print-preview.js'); ?>?v=<?php echo time(); ?>">
</script>
<script>
	const API_BASE = '<?php echo $apiBase; ?>';
	const CBA_TOKEN = window.ew?.API_JWT_TOKEN ?? '';

	// ── Edit mode ────────────────────────────────────────────────────────────────
	const CBA_CONFIG = {
		allocationId: <?php echo $allocationId ? json_encode($allocationId) : 'null'; ?> ,
		// the logged-in user's own RC (see the PHP header): { rc_id, rc_code, rc_name, office_type }
		requestingRc: <?php echo json_encode($requestingRc, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?> ,
	};
	const CBA_EDIT_MODE = !!CBA_CONFIG.allocationId;
	// true when the including page rendered this form inside the overlay modal (see the PHP header)
	const CBA_AS_MODAL = <?php echo $cbaAsModal ? 'true' : 'false'; ?>;
	// created_at of the allocation being edited (set by populateEditForm). Stays
	// null in add mode — nothing is saved yet, so the RFA print date is today's.
	var CBA_CREATED_AT = null;
	// The status name of the allocation being edited (set by populateEditForm), shown in the Submit pop-up. '' in add mode.
	var CBA_CURRENT_STATUS = '';

	// ── Requesting office = a Responsibility Center, not a field on the form ───────
	// Add mode: the logged-in user's own RC (CBA_CONFIG.requestingRc, resolved in PHP from uac.users.rc_id ->
	// fims.responsibility_code). Edit mode: the RC saved on the record (populateEditForm). The API sets
	// budget_allocation.requesting_rc_id itself on create, so this is only used for the RFA / LAA print preview.
	var CBA_REQ_RC = Object.assign({ rc_id: '', rc_code: '', rc_name: '', office_type: '' }, CBA_CONFIG.requestingRc || {});

	// fims.responsibility_code.office_type -> the department_type the RFA template switch uses:
	// Regional / District offices print the REGIONAL OFFICE form, every central RC (Agency / Group / Department) the STAFF form (MAIN).
	function cbaRcDeptType(officeType) {
		var t = String(officeType || '');
		if (t === 'Regional') return 'REGIONAL';
		if (t === 'District') return 'DISTRICT';
		return t ? 'MAIN' : '';
	}

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
	const CURRENT_YEAR = <?php echo (int) date('Y'); ?> ;

	// ── Per-type series counters (populated by refreshAllocationCount) ────────────
	var CBA_SERIES = {
		CBA: 1,
		RFA: 1,
		LAA: 1
	};

	// ── Boot ─────────────────────────────────────────────────────────────────────
	// The whole page set-up. Page mode runs it once at load; modal mode runs it on every open
	// (see the Boot block at the end of this script).
	// The app's shared preloader (app/preloader.php) — cbaBoot() shows it while the lookups load.
	function cbaHideLoader() {
		window.PagePreloader?.hide();
	}

	function cbaBoot() {
		// Listeners on the <form> element itself. They are attached here, not at script load,
		// because in modal mode the form's markup is rebuilt on every open and that discards
		// whatever was attached to the previous element.
		var cbaFormEl = document.getElementById('cba_form');
		cbaFormEl.addEventListener('submit', cbaOnSubmit);
		cbaFormEl.addEventListener('keydown', cbaBlockEnter);

		pcInit(); // Program → Sub-Program → Project comboboxes

		generateCBANumber();
		addBudgetAllocationLine(); // render the first Budget Allocation Amount row

		document.getElementById('allocation_type').addEventListener('change', generateCBANumber);
		document.getElementById('allocation_type').addEventListener('change', toggleCbaIdRow);
		document.getElementById('allocation_type').addEventListener('change', updateSubmitLabel);
		document.getElementById('allocation_type').addEventListener('change', pcrApplyMode); // PR ↔ PO fields
		wireAmtInput(document.getElementById('pr_amount'));
		wireAmtInput(document.getElementById('po_amount'));
		document.getElementById('allocation_type').addEventListener('change',
			applyBudgetVersionVisibilityForDocType);
		document.getElementById('document_type').addEventListener('change', applyAllocationTypeOptionsForDocType);
		document.getElementById('document_type').addEventListener('change', generateCBANumber); // RFA code depends on document type
		document.getElementById('document_type').addEventListener('change', dropInfrastructureCategoryForCoe);
		document.getElementById('document_type').addEventListener('change',
			applyProgramProjectVisibilityForDocType);
		document.getElementById('document_type').addEventListener('change',
			applyBudgetVersionVisibilityForDocType);

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
		const _presetDocType = CBA_EDIT_MODE ? '' : (_urlParams.get('document_type') || '').toUpperCase();
		if (_presetDocType === 'CO' || _presetDocType === 'COE') {
			document.getElementById('document_type').value = _presetDocType;
			generateCBANumber(); // RFA code depends on document type (COE → 57)
			applyBudgetVersionVisibilityForDocType();
		}

		// document_type is final here (URL preset applied) — hide CFA if it is CO.
		applyAllocationTypeOptionsForDocType();
		pcrApplyMode(); // so is the allocation type (a preset RFA / LAA shows the Purchase Order fields)

		applyProgramProjectVisibilityForDocType();
		if (_presetDocType === 'CO' || _presetDocType === 'COE') {
			dropInfrastructureCategoryForCoe();
		}

		// Run all dropdown loaders; after they settle, apply CBA autofill if requested.
		// The app's shared preloader covers the screen meanwhile (cbaHideLoader() takes it down).
		// NOTE: the COB lists are not loaded here — each line loads its own, from the Program / Sub-Program /
		// Project chosen above (see cobLoad).
		window.PagePreloader?.show();
		var _dropdownLoaders = Promise.allSettled([
			loadFiscalYears(),
			loadPrograms(),
			refreshAllocationCount(),
			loadCategories(),
			loadVendors(),
			loadVendorTypes(),
			loadTypesOfFund(),
		]);

		// Take the preloader down once all dropdowns have settled. In edit mode the saved record is still to be
		// filled in (loadExistingAllocation, below), so it stays up until that has finished too.
		_dropdownLoaders.then(function() {
			if (!CBA_EDIT_MODE) cbaHideLoader();
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
				var cbaLabel = cbaLabelOf(cba);
				cbaSelect(cba.allocation_id, cbaLabel);

				// 2. Fiscal year
				if (cba.fiscal_year) {
					cbaSetFiscalYear(cba.fiscal_year);
					generateCBANumber();
				}

				// 3. Budget Allocation Amount lines — one row per line_transaction entry of the parent CBA (the
				//    same shape populateEditForm() reads), so an RFA/LAA generated from a multi-line CBA starts with
				//    the same COB lines instead of collapsing to one. A legacy single-line CBA with no
				//    line_transaction falls back to its flat requested / allocated amounts (row 0).
				// The parent list (/budget_allocation/all) leaves line_transaction out (it can be large), so the
				// parent's full record is read for its lines.
				var parentLines = Array.isArray(cba.line_transaction) ? cba.line_transaction : [];
				if (!parentLines.length) {
					try {
						var parentJson = await cba_apiFetch(`${API_BASE}/budget_allocation/read/${cba.allocation_id}`);
						if (parentJson.success && parentJson.data && Array.isArray(parentJson.data.line_transaction)) {
							parentLines = parentJson.data.line_transaction;
						}
					} catch (err) {
						console.error('[cba autofill] Failed to read the parent record:', err);
					}
				}
				var cbaLines = parentLines.length > 0 ?
					parentLines : [{
						requested_amount: cba.requested_amount,
						allocated_amount: cba.allocated_amount,
					}];

				while (BAA_ROWS.length < cbaLines.length) addBudgetAllocationLine();

				for (var cli = 0; cli < cbaLines.length; cli++) {
					var cLine = cbaLines[cli];
					var cIdx = BAA_ROWS[cli];
					if (cIdx === undefined) continue;

					baaSetAmount(cIdx, 'requested', cLine.requested_amount);
					baaSetAmount(cIdx, 'allocated', cLine.allocated_amount);
					// the COB the parent drew from (shown from the details saved with the line when it is no longer listed)
					await cobRestoreLine(cIdx, cLine);
				}
				recomputeBaaTotals();

				// 4. Document type
				if (cba.document_type) {
					var docTypeEl = document.getElementById('document_type');
					docTypeEl.value = cba.document_type;
					generateCBANumber(); // RFA code depends on document type (COE → 57)
					// document_type was set programmatically (no 'change' event fired),
					// so re-apply the CO auto-select/lock behavior manually.
					applyAllocationTypeOptionsForDocType();
					dropInfrastructureCategoryForCoe();
					applyBudgetVersionVisibilityForDocType();
				}

				// 7. Amounts — already populated per-row in step 3 above (from
				//    line_transaction, or the flat fallback).

				// 8/9. Program → Sub-Program → Project, selected by code
				await cbaSelectProgramChain(cba.program_code, cba.sub_program_code, cba.project_code);

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

				// 12. Category (by id, from the loaded /budget_category/all list)
				catSelectById(cba.category_id);

				// 12b. Vendor (by id; the address comes with it)
				await vendSelectById(cba.vendor_id);

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
	}

	// ── Edit mode: load + populate an existing allocation ─────────────────────────
	// GET /budget_allocation/read/{id} → same nested response shape used
	// throughout this file (program{}, project{}, category_type{}, file_attachments[]).
	async function loadExistingAllocation(id) {
		try {
			var json = await cba_apiFetch(`${API_BASE}/budget_allocation/read/${id}`);
			if (!json.success || !json.data) throw new Error(json.message || 'Failed to load allocation.');
			await populateEditForm(json.data);
		} catch (err) {
			console.error('[loadExistingAllocation]', err);
			alert('Failed to load allocation #' + id + ': ' + err.message);
		} finally {
			cbaHideLoader();
		}
	}

	async function populateEditForm(a) {
		var g = function(id) {
			return document.getElementById(id);
		};

		CBA_CREATED_AT = a.created_at || null;
		CBA_CURRENT_STATUS = a.status_name || '';
		// the record's own requesting office (its creator's RC, joined by the API), not the editing user's
		CBA_REQ_RC = {
			rc_id: a.requesting_rc_id || '',
			rc_code: a.requesting_rc_code || '',
			rc_name: a.requesting_rc_name || '',
			office_type: a.requesting_rc_office_type || '',
		};

		// 1. Allocation type — set first since it drives visibility of several
		//    other fields (CBA parent row, category, doc type).
		g('allocation_type').value = a.allocation_type || 'CBA';
		toggleCbaIdRow();
		updateSubmitLabel();
		pcrApplyMode();

		// 2. Allocation number — locked to the value loaded from the server
		//    (generateCBANumber() is a no-op in edit mode). If the existing
		//    record has no allocation_number (null/empty — e.g. an old or
		//    incomplete record), generate one now using the same per-type
		//    CBA/RFA/LAA format as create mode, so it's never left blank and
		//    gets carried into the update payload (buildPayload() reads
		//    straight from this hidden field).
		var allocNumber = a.allocation_number || buildAllocationNumber(a.allocation_type || 'CBA', a.document_type);
		g('allocation_number').value = allocNumber;
		g('cba_number_display').textContent = allocNumber;

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
		if (a.fiscal_year) cbaSetFiscalYear(a.fiscal_year);

		// 6. Document type
		if (a.document_type) {
			var docTypeEl = g('document_type');
			docTypeEl.value = a.document_type;
			applyAllocationTypeOptionsForDocType();
			dropInfrastructureCategoryForCoe();
			applyBudgetVersionVisibilityForDocType();
		}

		// 9. Program → Sub-Program → Project, selected by code — first, so every line's COB list is loaded once, for
		//    this chain
		await cbaSelectProgramChain(a.program_code, a.sub_program_code, a.project_code);

		// 10. Budget Allocation Amount lines — one per saved line_transaction entry (row 0 already exists from boot),
		//     each with the COB it drew from
		var savedLines = Array.isArray(a.line_transaction) ? a.line_transaction : [];
		if (savedLines.length) {
			while (BAA_ROWS.length < savedLines.length) addBudgetAllocationLine();
			for (var li = 0; li < savedLines.length; li++) {
				var line = savedLines[li];
				var idx = BAA_ROWS[li];
				if (idx === undefined) continue;

				baaSetAmount(idx, 'requested', line.requested_amount);
				baaSetAmount(idx, 'allocated', line.allocated_amount);
				await cobRestoreLine(idx, line);
			}
		} else {
			// no itemized lines saved — the flat totals go into row 0
			baaSetAmount(0, 'requested', a.requested_amount);
			baaSetAmount(0, 'allocated', a.allocated_amount);
		}
		recomputeBaaTotals();

		// 13. Purpose / description / remarks
		if (a.project_description) g('project_description').value = a.project_description;
		if (a.remarks) g('remarks').value = a.remarks;
		if (g('is_bidding')) g('is_bidding').checked = !!a.is_bidding;

		// 14. Category (by id, from the loaded /budget_category/all list)
		catSelectById(a.category_id);

		// 14b. Vendor (by id; the address comes with it)
		await vendSelectById(a.vendor_id);

		// 14c. Purchase Request / Purchase Order (number + amount)
		pcrPopulate(a);

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
			// fims.fiscal_year is just { fiscal_yr, status_id }, newest year first. The year is both the
			// value (it is the FK saved in budget_allocation.fiscal_year) and the label, like every other
			// FIMS fiscal-year dropdown. The current year is preselected when it exists.
			var json = await cba_apiFetch(`${API_BASE}/fiscal_year/all`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			select.innerHTML = '<option value="">Select fiscal year</option>';
			json.data.forEach(function(row) {
				var opt = document.createElement('option');
				opt.value = row.fiscal_yr;
				opt.textContent = row.fiscal_yr;
				opt.dataset.fiscalYear = row.fiscal_yr; // read by the print payload (fiscal_year_label)
				if (parseInt(row.fiscal_yr, 10) === CURRENT_YEAR) opt.selected = true;
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

	// Set the dropdown from a saved record. If that year is no longer in the list it is added, so the
	// form still shows it and saving does not silently blank the field.
	function cbaSetFiscalYear(year) {
		var select = document.getElementById('fiscal_year');
		var v = String(year);
		if (!Array.prototype.some.call(select.options, function(o) {
				return o.value === v;
			})) {
			var opt = document.createElement('option');
			opt.value = v;
			opt.textContent = v;
			opt.dataset.fiscalYear = v;
			select.appendChild(opt);
		}
		select.value = v;
	}

	// ── Drop a stale "Infrastructure" Category pick when Document Type = COE ──────
	// COE has no "Infrastructure" Category, so a pick made under another document
	// type is cleared (the dropdown list itself is filtered by catVisibleData()).
	// Runs on every Document Type change and after programmatic changes to it.
	function dropInfrastructureCategoryForCoe() {
		var docType = document.getElementById('document_type').value;
		if (docType === 'COE' && isInfrastructureCategorySelected()) {
			var catHidden = document.getElementById('category_id');
			catHidden.value = '';
			catHidden.dataset.label = '';
			var catSearch = document.getElementById('cat_search');
			if (catSearch) catSearch.value = '';
		}
	}

	// True when the selected Category (fims.budget_category) is
	// "Infrastructure" — looked up by id in CAT_ALL_DATA rather than trusting
	// the hidden field's dataset.label, since catClearSelection() only clears
	// .value (not dataset.label) while the user is typing a new search query.
	function isInfrastructureCategorySelected() {
		var hidden = document.getElementById('category_id');
		var id = hidden ? hidden.value : '';
		if (!id) return false;
		var cat = (CAT_ALL_DATA || []).find(function(c) {
			return String(c.budget_category_id) === String(id);
		});
		return !!cat && catIsInfrastructure(cat);
	}

	// ── Hide Program/Project (not Purpose/Description) + Cash Requirements for
	// COE docs; for CO docs, Program/Project only shows when Category =
	// Infrastructure (hidden for every other category) ─────────────────────────
	// COE allocations never carry a program/project, but they still need the
	// Purpose / Description ("particulars") field — only the Program/Project
	// row is hidden, the section itself (and its Purpose/Description row) stays
	// visible. Neither the program/sub-program/project codes nor allocated_amount carry a
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

			cbaClearProgramChain();
		} else {
			if (progProjRow) progProjRow.style.display = '';
			if (sectionTitle) sectionTitle.textContent = 'Program, Sub-Program and Project Details';
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

	// ── 3. Program → Sub-Program → Project (cascading searchable comboboxes) ──────
	// Each level lists only what belongs to the level above it:
	//   Program      GET /program/all
	//   Sub-Program  GET /sub_program/all?program_code=<program>
	//   Project      GET /project/all?program_code=<program>&sub_program_code=<sub-program>
	// Choosing, editing or clearing a level clears every level below it and loads the
	// next one. A program with no sub-programs would otherwise be a dead end
	// (projects.sub_program_code is optional), so its projects are listed directly.
	// The value of each level is its CODE — these tables have no integer id. It lives
	// in the hidden input and is sent as program_code / sub_program_code / project_code.
	//
	// A level can also be NEW: text typed into its box that was not picked from the list. Nothing is created while
	// typing; buildPayload() sends the typed text as program_name / sub_program_name / project_name and the server
	// (BudgetAllocationService::resolveTypedEntities) creates what does not exist yet — reusing an existing one
	// with the same name — in the same transaction as the memo request. Under a NEW level there is nothing to
	// pick, so the levels below it become type-only (pcNewOnly).
	var PCHAIN = {
		prog: {
			search: 'prog_search', dd: 'prog_dropdown', hidden: 'program_code', wrapper: 'program_wrapper',
			error: 'program_error', hint: 'program_hint', noun: 'program', parent: null, child: 'subprog',
			codeKey: 'program_code', nameKey: 'program_name', data: [], seq: 0,
			hintBase: 'Pick an existing program, or type a new name to create one.'
		},
		subprog: {
			search: 'subprog_search', dd: 'subprog_dropdown', hidden: 'sub_program_code', wrapper: 'sub_program_wrapper',
			error: 'sub_program_error', hint: 'sub_program_hint', noun: 'sub-program', parent: 'prog', child: 'proj',
			codeKey: 'sub_program_code', nameKey: 'sub_program_name', data: [], seq: 0, hintBase: ''
		},
		proj: {
			search: 'proj_search', dd: 'proj_dropdown', hidden: 'project_code', wrapper: 'project_wrapper',
			error: 'project_error', hint: 'project_hint', noun: 'project', parent: 'subprog', child: null,
			codeKey: 'project_code', nameKey: 'project_name', data: [], seq: 0, hintBase: ''
		},
	};

	function pcEl(key, part) {
		return document.getElementById(PCHAIN[key][part]);
	}

	function pcValue(key) {
		var hidden = pcEl(key, 'hidden');
		return hidden ? hidden.value : '';
	}

	function pcEsc(s) {
		return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function pcShowError(key) {
		showError(PCHAIN[key].error);
	}

	function pcHideError(key) {
		var box = document.getElementById(PCHAIN[key].error);
		if (box) box.classList.remove('visible');
	}

	// The text typed into a level's box when it was NOT picked from the list — i.e. a NEW program / sub-program /
	// project to create on save ('' when an existing one is picked, or the box is empty).
	function pcTyped(key) {
		if (pcValue(key)) return '';
		return (pcEl(key, 'search').value || '').replace(/\s+/g, ' ').trim();
	}

	// Sets a level's hint. A normal hint is remembered (hintBase); a "new" one (isNew) is shown on top of it, in colour,
	// and pcRefreshStates() puts the remembered one back as soon as the level stops being new.
	function pcSetHint(key, text, isNew) {
		var c = PCHAIN[key];
		if (!c.hint) return;
		var el = pcEl(key, 'hint');
		if (!isNew) c.hintBase = text;
		el.textContent = text;
		el.classList.toggle('pc-hint-new', !!isNew);
	}

	function pcRender(key, rows) {
		var c = PCHAIN[key];
		var dd = pcEl(key, 'dd');
		if (!rows.length) {
			var q = (pcEl(key, 'search').value || '').replace(/\s+/g, ' ').trim();
			dd.innerHTML = q ?
				'<div class="pc-new">No match \u2014 \u201c' + pcEsc(q) + '\u201d will be created as a new ' + c.noun + ' when you save.</div>' :
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No ' + c.noun +
				's yet \u2014 type a name to create one.</div>';
			dd.style.display = 'block';
			return;
		}
		dd.innerHTML = rows.map(function(r) {
			return '<div class="bv-item" data-code="' + pcEsc(r[c.codeKey]) + '" data-name="' + pcEsc(r[c.nameKey]) + '">' +
				'<div class="bv-item-name">' + pcEsc(r[c.nameKey]) + '</div>' +
				'<div class="bv-item-sub">' + pcEsc(r[c.codeKey]) + '</div>' +
				'</div>';
		}).join('');
		dd.style.display = 'block';
		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				pcSelect(key, this.dataset.code, this.dataset.name);
			});
		});
	}

	// Pick a level's value, then clear the levels below it and load the next one. The returned
	// promise resolves once that next level has its options, so edit mode can await it and
	// select the following level straight away.
	function pcSelect(key, code, name) {
		var c = PCHAIN[key];
		var hidden = pcEl(key, 'hidden');
		hidden.value = code;
		hidden.dataset.label = name + ' (' + code + ')';
		pcEl(key, 'search').value = hidden.dataset.label;
		pcEl(key, 'dd').style.display = 'none';
		pcClearBelow(key);
		cobChainChanged(); // the COB lists follow the chain
		pcRefreshStates();
		return c.child ? pcLoad(c.child) : Promise.resolve();
	}

	// Forget one level's value (and optionally the text in its box).
	function pcClear(key, clearText) {
		var hidden = pcEl(key, 'hidden');
		if (!hidden) return;
		hidden.value = '';
		hidden.dataset.label = '';
		if (clearText) pcEl(key, 'search').value = '';
		cobChainChanged();
	}

	// Lock a level until the one above it has a value.
	function pcDisable(key) {
		var c = PCHAIN[key];
		var parentNoun = PCHAIN[c.parent].noun;
		var search = pcEl(key, 'search');
		search.disabled = true;
		search.placeholder = 'Select a ' + parentNoun + ' first';
		pcSetHint(key, 'Select a ' + parentNoun + ' first.');
		pcEl(key, 'dd').style.display = 'none';
		pcHideError(key);
		setLoading(c.wrapper, false);
	}

	// Clear and lock every level below `key`. The seq bump makes any request still in flight for
	// those levels discard its answer instead of filling a list the user has already moved past.
	function pcClearBelow(key) {
		for (var k = PCHAIN[key].child; k; k = PCHAIN[k].child) {
			PCHAIN[k].seq++;
			PCHAIN[k].data = [];
			pcClear(k, true);
			pcDisable(k);
		}
	}

	// Open a level for typing only: its parent is NEW (typed, not picked), so there is nothing to pick from yet.
	function pcNewOnly(key) {
		var c = PCHAIN[key];
		c.seq++; // a list still loading for this level is discarded
		c.data = [];
		var search = pcEl(key, 'search');
		search.disabled = false;
		search.placeholder = 'Type a new ' + c.noun + (key === 'proj' ? '' : ' (optional)') + '\u2026';
		setLoading(c.wrapper, false);
		pcHideError(key);
	}

	// Re-derives what the typed text means after any edit or pick. A level whose box holds text that was not picked is NEW;
	// under a NEW level the levels below are type-only. Hints show which levels are new and revert when one stops being.
	function pcRefreshStates() {
		var progNew = !!pcTyped('prog');
		var subNew = !!pcTyped('subprog');

		if (progNew) {
			// a new program has no sub-programs or projects yet — a project may even sit directly under it
			pcNewOnly('subprog');
			pcNewOnly('proj');
			pcSetHint('subprog', 'This is a new program, so its sub-programs are new too \u2014 type one, or leave it blank.');
			pcSetHint('proj', 'Type a new project, or leave it blank.');
		} else if (subNew) {
			pcNewOnly('proj');
			pcSetHint('proj', 'Type a new project, or leave it blank.');
		}

		['prog', 'subprog', 'proj'].forEach(function(k) {
			var typed = pcTyped(k);
			var c = PCHAIN[k];
			if (typed) {
				pcSetHint(k, 'New ' + c.noun + ' \u201c' + typed + '\u201d \u2014 it will be created when you save.', true);
			} else if (c.hint) {
				pcSetHint(k, c.hintBase);
			}
		});
	}

	// Load a child level's options from its parent's current value.
	async function pcLoad(key) {
		var c = PCHAIN[key];
		var search = pcEl(key, 'search');
		var progCode = pcValue('prog');
		var subCode = pcValue('subprog');
		var url = key === 'subprog' ?
			API_BASE + '/sub_program/all?program_code=' + encodeURIComponent(progCode) :
			API_BASE + '/project/all?program_code=' + encodeURIComponent(progCode) +
			(subCode ? '&sub_program_code=' + encodeURIComponent(subCode) : '');

		var seq = ++c.seq;
		var loaded = false;
		pcHideError(key);
		setLoading(c.wrapper, true);
		search.disabled = true;
		search.placeholder = 'Loading\u2026';
		try {
			var json = await cba_apiFetch(url);
			if (seq !== c.seq) return; // a level above changed while this was loading
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message || 'Unexpected response format');

			c.data = json.data;
			loaded = true;
			var n = c.data.length;
			if (!n) {
				// nothing to pick: it can only be typed — a new one, created when you save
				search.disabled = false;
				search.placeholder = 'No ' + c.noun + 's yet \u2014 type to add one';
				pcSetHint(key, key === 'subprog' ?
					'This program has no sub-programs yet \u2014 type one to add it, or leave it blank (its projects are listed directly).' :
					'No projects under this ' + (subCode ? 'sub-program' : 'program') + ' yet \u2014 type one to add it.');
			} else {
				search.disabled = false;
				search.placeholder = 'Search or type a new ' + c.noun + '\u2026';
				pcSetHint(key, n + ' ' + c.noun + '(s) under this ' + (key === 'subprog' ? 'program' : (subCode ? 'sub-program' : 'program')) + '.');
			}
		} catch (err) {
			if (seq !== c.seq) return;
			console.error('[pcLoad ' + key + ']', err);
			c.data = [];
			search.placeholder = 'Could not load';
			pcShowError(key);
		} finally {
			if (seq === c.seq) setLoading(c.wrapper, false);
		}
		// no sub-programs under the chosen program: skip straight to its projects
		if (loaded && key === 'subprog' && !c.data.length && seq === c.seq) return pcLoad('proj');
	}

	// Typing in a box filters that level's already-loaded options (client-side).
	function pcWire(key) {
		var c = PCHAIN[key];
		var search = pcEl(key, 'search');
		var dd = pcEl(key, 'dd');

		function matches(q) {
			q = q.toLowerCase();
			return c.data.filter(function(r) {
				return !q || String(r[c.nameKey] || '').toLowerCase().includes(q) ||
					String(r[c.codeKey] || '').toLowerCase().includes(q);
			}).slice(0, 50);
		}

		search.addEventListener('input', function() {
			// editing the text withdraws the earlier pick — it and everything below it are cleared
			if (pcValue(key)) {
				pcClear(key);
				pcClearBelow(key);
			} else if (!pcTyped(key)) {
				pcClearBelow(key); // the box was emptied: the levels below lock again
			}
			pcRender(key, matches(this.value.trim()));
			pcRefreshStates(); // text that was typed (not picked) is a NEW one
		});
		search.addEventListener('focus', function() {
			if (!pcValue(key)) pcRender(key, matches(this.value.trim()));
		});
		search.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// Boot: wire the three boxes and lock the two child levels until a parent is chosen.
	function pcInit() {
		['prog', 'subprog', 'proj'].forEach(pcWire);
		pcDisable('subprog');
		pcDisable('proj');
	}

	async function loadPrograms() {
		var c = PCHAIN.prog;
		var search = pcEl('prog', 'search');
		try {
			var json = await cba_apiFetch(API_BASE + '/program/all');
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message || 'Unexpected response format');

			c.data = json.data;
			search.disabled = false;
			search.placeholder = 'Search or type a new program\u2026';
		} catch (err) {
			console.error('[loadPrograms]', err);
			pcShowError('prog');
		} finally {
			setLoading(c.wrapper, false);
		}
	}

	// Select program → sub-program → project from their codes (edit mode, and "generate RFA/LAA
	// from a parent CBA"). Each level is only selectable once the one above has loaded its options.
	async function cbaSelectProgramChain(progCode, subCode, projCode) {
		// The levels are filled in by code here, not chosen by the user: the COBs already picked belong to this
		// chain, so the COB reloads it triggers must not clear them.
		COB_KEEP_SELECTION++;
		try {
			var prog = progCode && PCHAIN.prog.data.find(function(r) {
				return r.program_code === progCode;
			});
			if (!prog) return;
			await pcSelect('prog', prog.program_code, prog.program_name);

			var sub = subCode && PCHAIN.subprog.data.find(function(r) {
				return r.sub_program_code === subCode;
			});
			if (sub) await pcSelect('subprog', sub.sub_program_code, sub.sub_program_name);

			var proj = projCode && PCHAIN.proj.data.find(function(r) {
				return r.project_code === projCode;
			});
			if (proj) await pcSelect('proj', proj.project_code, proj.project_name);
		} finally {
			COB_KEEP_SELECTION--;
		}
	}

	// Empty all three (used when the document type hides this row).
	function cbaClearProgramChain() {
		pcClear('prog', true);
		pcClearBelow('prog');
		pcRefreshStates();
	}

	// Drop every loaded list and invalidate in-flight requests (modal reopen).
	function pcResetState() {
		['prog', 'subprog', 'proj'].forEach(function(k) {
			PCHAIN[k].data = [];
			PCHAIN[k].seq++;
		});
	}

	// ── Category (searchable combobox) ───────────────────────────────────────────
	// GET /budget_category/all  →  [{ budget_category_id, name, … }]. The picked id lives in the
	// hidden #category_id and is saved as budget_allocation.category_id.
	var CAT_ALL_DATA = [];
	var CAT_DEBOUNCE = null;

	function catIsInfrastructure(c) {
		return String(c.name || '').trim().toLowerCase() === 'infrastructure';
	}

	async function loadCategories() {
		var searchEl = document.getElementById('cat_search');
		var wrapper = document.getElementById('category_wrapper');
		try {
			var json = await cba_apiFetch(API_BASE + '/budget_category/all');
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
			return !catIsInfrastructure(c);
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
			return '<div class="bv-item" data-id="' + pcEsc(cat.budget_category_id) + '" data-name="' + pcEsc(cat.name) + '">' +
				'<div class="bv-item-name">' + pcEsc(cat.name) + '</div>' +
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
		var hidden = document.getElementById('category_id');
		hidden.value = id;
		hidden.dataset.label = name;

		document.getElementById('cat_search').value = name;
		document.getElementById('cat_dropdown').style.display = 'none';

		applyProgramProjectVisibilityForDocType();
	}

	// Select a saved category by id (edit mode, "generate from a parent CBA"). Skipped when the id is
	// not in the list (e.g. the category was deleted since), in which case the field stays empty.
	function catSelectById(id) {
		if (id == null || id === '') return;
		var cat = CAT_ALL_DATA.find(function(c) {
			return String(c.budget_category_id) === String(id);
		});
		if (cat) catSelect(cat.budget_category_id, cat.name);
	}

	function catClearSelection() {
		document.getElementById('category_id').value = '';
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
					return String(c.name || '').toLowerCase().includes(q);
				});
				catRenderDropdown(filtered);
			}, 150);
		});

		searchEl.addEventListener('focus', function() {
			if (!document.getElementById('category_id').value) {
				catRenderDropdown(catVisibleData());
			}
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── Vendor (searchable combobox; a vendor that does not exist can be added) ───
	// GET /vendors/all?active=1  →  [{ vendor_id, vendor_code, vendor_name, vendor_type, address, … }]. The picked
	// id lives in the hidden #vendor_id and is saved as budget_allocation.vendor_id.
	//
	// VEND_MODE:
	//   'none'      nothing typed or picked — only Vendor Name shows.
	//   'existing'  a vendor from the list is picked — its code, type and address show, read-only.
	//   'new'       the typed name is not an existing vendor — Vendor Code, Vendor Type and Address pop up,
	//               editable. The vendor is created on save (POST /vendors/add, see vendEnsure) and the
	//               allocation then gets its new id.
	var VEND_ALL_DATA = [];
	var VEND_TYPES = [];           // GET /vendors/types → [{ vendor_type_id, vendor_type, description }]
	var VEND_TYPES_FAILED = false;
	var VEND_DEBOUNCE = null;
	var VEND_SEQ = 0;          // invalidates an in-flight edit-mode lookup (modal reopen)
	var VEND_LIST_MAX = 50;    // rows rendered at once; typing narrows the rest
	var VEND_MODE = 'none';

	function vendEl(id) {
		return document.getElementById(id);
	}

	// 'SERVICE_PROVIDER' → 'Service Provider'
	function vendTypeLabel(code) {
		return String(code == null ? '' : code).toLowerCase().split('_').filter(Boolean).map(function(w) {
			return w.charAt(0).toUpperCase() + w.slice(1);
		}).join(' ');
	}

	async function loadVendors() {
		var searchEl = vendEl('vendor_search');
		var wrapper = vendEl('vendor_wrapper');
		try {
			var json = await cba_apiFetch(API_BASE + '/vendors/all?active=1');
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			VEND_ALL_DATA = json.data;
			searchEl.disabled = false;
			searchEl.placeholder = 'Search or enter a vendor…';
			vendSetupSearch();
		} catch (err) {
			console.error('[loadVendors]', err);
			showError('vendor_error');
		} finally {
			if (wrapper) wrapper.classList.remove('is-loading');
		}
	}

	// Vendor types for the "new vendor" dropdown. The type is optional, so a failed load only shows a note.
	async function loadVendorTypes() {
		var sel = vendEl('vendor_type');
		try {
			var json = await cba_apiFetch(API_BASE + '/vendors/types');
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			VEND_TYPES = json.data;
			VEND_TYPES_FAILED = false;
			var keep = sel.value;
			sel.innerHTML = '<option value="">Select vendor type</option>' + VEND_TYPES.map(function(t) {
				return '<option value="' + pcEsc(t.vendor_type) + '" title="' + pcEsc(t.description) + '">' +
					pcEsc(vendTypeLabel(t.vendor_type)) + '</option>';
			}).join('');
			sel.value = keep;
		} catch (err) {
			console.error('[loadVendorTypes]', err);
			VEND_TYPES_FAILED = true;
		}
		vendSyncTypeError();
	}

	// The "could not load vendor types" note only matters while a new vendor is being entered.
	function vendSyncTypeError() {
		vendEl('vendor_type_error').classList.toggle('visible', VEND_MODE === 'new' && VEND_TYPES_FAILED);
	}

	// Show / hide / lock the Vendor Code, Vendor Type and Address fields for the given mode.
	function vendSetMode(mode) {
		VEND_MODE = mode;
		var show = mode !== 'none';
		var editable = mode === 'new';
		var code = vendEl('vendor_code');
		var addr = vendEl('vendor_address');
		var typeSel = vendEl('vendor_type');
		var typeView = vendEl('vendor_type_view');

		vendEl('vendor_code_group').style.display = show ? '' : 'none';
		vendEl('vendor_type_group').style.display = show ? '' : 'none';
		vendEl('vendor_address_group').style.display = show ? '' : 'none';
		[code, addr, typeSel, typeView].forEach(function(el) {
			el.classList.remove('error', 'success');
			var group = el.closest('.form-group');
			if (group) group.classList.remove('has-error');
		});
		[code, addr].forEach(function(el) {
			el.readOnly = !editable;
			el.tabIndex = editable ? 0 : -1;
		});
		code.required = editable; // so the form's required-field check covers it only while it is shown
		code.placeholder = editable ? 'Enter the vendor code' : '';
		addr.placeholder = editable ? 'Enter the vendor address' : '';
		typeSel.style.display = editable ? '' : 'none';      // dropdown for a new vendor …
		typeView.style.display = editable ? 'none' : '';      // … read-only text for a picked one
		vendEl('vendor_code_required').style.display = editable ? '' : 'none';
		vendEl('vendor_new_hint').style.display = editable ? '' : 'none';
		vendSyncTypeError();

		if (mode === 'none') {
			code.value = '';
			addr.value = '';
			typeSel.value = '';
			typeView.value = '';
		}
	}

	function vendFilter(q) {
		if (!q) return VEND_ALL_DATA;
		return VEND_ALL_DATA.filter(function(v) {
			return String(v.vendor_name || '').toLowerCase().includes(q) ||
				String(v.vendor_code || '').toLowerCase().includes(q) ||
				String(v.tin || '').toLowerCase().includes(q);
		});
	}

	// A vendor whose name equals the typed text (case-insensitive) — so typing a name in full counts as
	// picking it, and the same vendor is never added twice.
	function vendFindExact(name) {
		var n = String(name || '').trim().toLowerCase();
		if (!n) return null;
		return VEND_ALL_DATA.find(function(v) {
			return String(v.vendor_name || '').trim().toLowerCase() === n;
		}) || null;
	}

	function vendRenderDropdown(rows) {
		var dd = vendEl('vendor_dropdown');
		if (!rows.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-muted, #62657E);font-size:13px;">No matching vendor &mdash; it will be added as a new vendor</div>';
			dd.style.display = 'block';
			return;
		}
		var shown = rows.slice(0, VEND_LIST_MAX);
		dd.innerHTML = shown.map(function(v) {
			var sub = [v.vendor_code, vendTypeLabel(v.vendor_type)].filter(Boolean).join(' \u00b7 ');
			return '<div class="bv-item" data-id="' + pcEsc(v.vendor_id) + '">' +
				'<div class="bv-item-name">' + pcEsc(v.vendor_name) + '</div>' +
				(sub ? '<div class="bv-item-sub">' + pcEsc(sub) + '</div>' : '') +
				'</div>';
		}).join('') + (rows.length > shown.length ?
			'<div style="padding:8px 14px;color:var(--afims-text-muted, #62657E);font-size:11px;">Showing the first ' +
			shown.length + ' of ' + rows.length + ' \u2014 type to narrow the list</div>' : '');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				var v = VEND_ALL_DATA.find(function(x) {
					return String(x.vendor_id) === item.dataset.id;
				});
				if (v) vendSelectRecord(v);
			});
		});
	}

	function vendSelect(id, name, address, code, type) {
		var hidden = vendEl('vendor_id');
		hidden.value = id;
		hidden.dataset.label = name || '';

		vendEl('vendor_search').value = name || '';
		vendEl('vendor_dropdown').style.display = 'none';
		vendEl('vendor_code').value = code == null ? '' : String(code);
		vendEl('vendor_address').value = address == null ? '' : String(address);
		vendEl('vendor_type_view').value = vendTypeLabel(type);
		vendSetMode('existing');
	}

	function vendSelectRecord(v) {
		vendSelect(v.vendor_id, v.vendor_name, v.address, v.vendor_code, v.vendor_type);
	}

	// Select a saved vendor by id (edit mode, "generate from a parent CBA"). A vendor that is no longer
	// offered in the list (it has since been made inactive) is fetched on its own so the saved record
	// still shows it; if that fails too, the field simply stays empty.
	async function vendSelectById(id) {
		if (id == null || id === '') return;
		var v = VEND_ALL_DATA.find(function(x) {
			return String(x.vendor_id) === String(id);
		});
		if (!v) {
			var seq = ++VEND_SEQ;
			try {
				var json = await cba_apiFetch(API_BASE + '/vendors/read/' + encodeURIComponent(id));
				if (seq !== VEND_SEQ || !json.success || !json.data) return;
				v = json.data;
			} catch (err) {
				console.warn('[vendSelectById]', err);
				return;
			}
		}
		vendSelectRecord(v);
	}

	// The text in Vendor Name was edited: any picked vendor is withdrawn (and the code / type / address that
	// came with it). What was typed for a NEW vendor is kept, so correcting its name does not wipe the code.
	function vendOnType(text) {
		vendEl('vendor_id').value = '';
		if (VEND_MODE === 'existing') vendSetMode('none');
		if (!String(text).trim()) vendSetMode('none');
	}

	// Settle what is typed in Vendor Name when the user is done with it (blur, or at save time): an exact
	// match of an existing vendor selects it, any other non-empty text is a new vendor.
	function vendResolveTyped() {
		var name = vendEl('vendor_search').value.trim();
		if (!name || VEND_MODE === 'existing') return;
		var exact = vendFindExact(name);
		if (exact) {
			vendSelectRecord(exact);
			return;
		}
		if (VEND_MODE !== 'new') vendSetMode('new');
	}

	// Called by postToApi before the allocation is saved. For a new vendor: create it, then switch to
	// "existing" with its new id — so a retry after a later failure re-uses it instead of adding it twice.
	// Throws (the caller shows the message) when the vendor cannot be created.
	async function vendEnsure() {
		vendResolveTyped();
		if (VEND_MODE !== 'new') return;

		var name = vendEl('vendor_search').value.trim();
		var codeEl = vendEl('vendor_code');
		var code = codeEl.value.trim();
		var type = vendEl('vendor_type').value;
		var address = vendEl('vendor_address').value.trim();

		if (!code) {
			validateField(codeEl);
			codeEl.focus();
			throw new Error('Enter a vendor code for the new vendor "' + name +
				'", or pick an existing vendor from the list.');
		}

		var body = {
			vendor_code: code,
			vendor_name: name
		};
		if (type) body.vendor_type = type;
		if (address) body.address = address;

		var json;
		try {
			json = await cba_apiFetchPost(API_BASE + '/vendors/add', body);
		} catch (err) {
			codeEl.classList.add('error'); // the usual cause: the code is already taken
			throw new Error('Could not add the vendor: ' + err.message);
		}
		if (!json.success || !json.data || !json.data.vendor_id) {
			throw new Error('Could not add the vendor: ' + (json.message || 'unexpected response'));
		}

		VEND_ALL_DATA.push({
			vendor_id: json.data.vendor_id,
			vendor_code: code,
			vendor_name: name,
			vendor_type: type || null,
			address: address || null,
			status: 'ACTIVE'
		});
		vendSelect(json.data.vendor_id, name, address, code, type);
	}

	function vendSetupSearch() {
		var searchEl = vendEl('vendor_search');
		var dd = vendEl('vendor_dropdown');

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			vendOnType(this.value);
			clearTimeout(VEND_DEBOUNCE);

			if (!q) {
				vendRenderDropdown(vendFilter(''));
				return;
			}

			VEND_DEBOUNCE = setTimeout(function() {
				if (VEND_MODE === 'existing') return;
				var rows = vendFilter(q);
				vendRenderDropdown(rows);
				if (!rows.length && VEND_MODE !== 'new') vendSetMode('new'); // nothing like it exists
			}, 150);
		});

		searchEl.addEventListener('focus', function() {
			if (!vendEl('vendor_id').value && !this.value.trim()) {
				vendRenderDropdown(vendFilter(''));
			}
		});

		searchEl.addEventListener('blur', function() {
			clearTimeout(VEND_DEBOUNCE); // a pending search must not reopen the list after the user has left
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
			vendResolveTyped();
		});
	}

	// ── Purchase Request / Purchase Order ────────────────────────────────────────
	// Allocation type RFA / LAA → Purchase Order Number + Amount (po_number, po_amount). Anything else (CBA, CFA,
	// or no type chosen yet — the same default getTypeLabel() uses) → Purchase Request Number + Amount
	// (pr_number, pr_amount). Both pairs stay in the form, so switching the type back restores what was typed,
	// but only the pair that is showing is saved.
	function pcrMode() {
		var t = document.getElementById('allocation_type').value;
		return (t === 'RFA' || t === 'LAA') ? 'po' : 'pr';
	}

	function pcrApplyMode() {
		var m = pcrMode();
		document.getElementById('pr_row').style.display = m === 'pr' ? '' : 'none';
		document.getElementById('po_row').style.display = m === 'po' ? '' : 'none';
		document.getElementById('pcr_title').textContent = m === 'po' ? 'Purchase Order' : 'Purchase Request';
	}

	// Request fields for the pair that is showing. A blank value is sent as '' (the service stores NULL), so
	// emptying a field on an existing record clears it.
	function pcrPayloadFields() {
		var m = pcrMode();
		var out = {};
		out[m + '_number'] = document.getElementById(m + '_number').value.trim();
		out[m + '_amount'] = document.getElementById(m + '_amount_raw').value;
		return out;
	}

	// Edit mode: fill both pairs from the saved record (only the one for the allocation type is visible).
	function pcrPopulate(a) {
		document.getElementById('pr_number').value = a.pr_number || '';
		document.getElementById('po_number').value = a.po_number || '';
		['pr', 'po'].forEach(function(k) {
			var n = parseFloat(a[k + '_amount']) || 0;
			document.getElementById(k + '_amount').value = n > 0 ? baaFormatAmt(n) : '';
			document.getElementById(k + '_amount_raw').value = n > 0 ? n.toFixed(2) : '';
		});
	}

	// ── 5. Budget Allocation Amount lines (COB + Requested Amount + Cash Requirements) ──────────────────────
	// One line = a Type of Fund, a COB and the amounts drawn from it. A COB is an approved budget line item, listed
	// by its Account Code (its Type of Fund is the select beside it):
	//   GET /budget_line_item/approved[?program_code=&sub_program_code=&project_code=&type_of_fund_id=]
	// The line's Type of Fund select offers only the types of fund that exist on approved line items (for the chain
	// chosen above) and filters its COB list to the same type of fund; picking a COB sets it too.
	// returns the line items of budget requests whose status is Approved, narrowed to whatever is attached to the
	// Program / Sub-Program / Project chosen above (none chosen → every approved line item). Every line loads its own
	// list and reloads whenever that chain changes (see cobChainChanged). The itemized lines are sent as
	// `line_transaction` (jsonb) — see collectBaaLines() / buildPayload(); their totals feed
	// requested_amount_raw / allocated_amount_raw.
	var BAA_ROWS = []; // ordered array of live line idx values
	var BAA_ROW_SEQ = -1;
	var COB_DATA = {}; // idx -> approved line items last loaded for that line
	var COB_SIG = {}; // idx -> the program|sub-program|project that list was loaded for
	var COB_SEQ = {}; // idx -> token of the newest request, so an answer that was overtaken is dropped
	var COB_LOAD = {}; // idx -> promise of that line's newest load
	var COB_SEL = {}; // idx -> the picked line item (kept even when it is not in the current list)
	var COB_WIRED = {}; // idx -> search listeners attached
	var COB_EPOCH = 0; // bumped when the modal form is reset, so requests from the old DOM are dropped
	var COB_KEEP_SELECTION = 0; // >0 while a saved record's own chain is being filled in: a reload must not clear its picks
	var COB_SYNC_QUEUED = false;
	var COB_TOKEN_SEQ = 0;
	var COB_LIST_MAX = 50; // rows rendered at once; typing narrows the rest
	var TOF_DATA = []; // the types of fund on approved line items, for the chain chosen — shared by every line's select
	var TOF_LOADED = false;
	var TOF_SIG = null; // the Program / Sub-Program / Project that TOF_DATA was loaded for
	var TOF_TOKEN = ''; // token of the newest request, so an answer that was overtaken is dropped

	function cobEl(part, idx) {
		return document.getElementById(part + '_' + idx);
	}

	// The Program / Sub-Program / Project chosen above.
	function cobChainSignature() {
		return [pcValue('prog'), pcValue('subprog'), pcValue('proj')].join('|');
	}

	// What a line's COB list depends on: the chain above plus the line's own Type of Fund.
	function cobSignature(idx) {
		var tof = cobEl('type_of_fund_id', idx);
		return cobChainSignature() + '|' + (tof ? tof.value : '');
	}

	// The account code — what the search box shows once a COB is picked (the Type of Fund is the select beside it).
	function cobLabel(r) {
		return r.account_code || ('Line item #' + r.budget_line_item_id);
	}

	function cobAmount(v) {
		return (v == null || v === '') ? '—' : '₱' + baaFormatAmt(parseFloat(v) || 0);
	}

	function baaRowTemplate(idx) {
		return '' +
			'<div class="baa-line form-row" data-idx="' + idx + '">' +
			'<div class="form-group baa-tof-group">' +
			'<label for="type_of_fund_id_' + idx + '" class="form-label">Type of Fund</label>' +
			'<select id="type_of_fund_id_' + idx + '" class="form-select baa-type-of-fund" data-idx="' + idx + '" disabled>' +
			'<option value="">Loading types of fund&hellip;</option>' +
			'</select>' +
			'</div>' +
			'<div class="form-group baa-bv-group">' +
			'<label for="bv_search_' + idx + '" class="form-label">' +
			'<span id="bv_field_label_' + idx + '">COB</span>' +
			' <span class="required baa-bv-required" id="bv_required_mark_' + idx + '" style="display:none;">*</span>' +
			'</label>' +
			'<div style="position:relative;">' +
			'<input type="text" id="bv_search_' + idx + '" class="form-input baa-bv-search" data-idx="' + idx + '" ' +
			'placeholder="Loading COBs&hellip;" autocomplete="off" disabled>' +
			'<div id="bv_dropdown_' + idx + '" class="baa-bv-dropdown" ' +
			'style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;' +
			'background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-top:none;' +
			'border-radius:0 0 6px 6px;max-height:220px;overflow-y:auto;' +
			'box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>' +
			'</div>' +
			'<input type="hidden" id="budget_line_item_id_' + idx + '" value="">' +
			'<p class="input-hint" id="cob_hint_' + idx + '"></p>' +
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
			'<span class="baa-bv-info-label">Account Code</span>' +
			'<span id="bvi_account_' + idx + '" class="baa-bv-info-value"></span>' +
			'<span class="baa-bv-info-label">Fiscal Year</span>' +
			'<span id="bvi_fy_' + idx + '" class="baa-bv-info-value"></span>' +
			'<span class="baa-bv-info-label">Proposed Amount</span>' +
			'<span id="bvi_amount_' + idx + '" class="baa-bv-info-value strong"></span>' +
			'</div>' +
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

		var tofSel = cobEl('type_of_fund_id', idx);
		if (TOF_LOADED) cobPopulateTypeSelect(tofSel);
		tofSel.addEventListener('change', function() {
			cobLoad(idx); // the COB list follows the Type of Fund; a pick of another type is cleared
		});
		wireAmtInput(document.getElementById('requested_amount_' + idx));
		wireAmtInput(document.getElementById('allocated_amount_' + idx));
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
		cobLoad(idx);
		return idx;
	}

	function removeBudgetAllocationLine(idx) {
		if (BAA_ROWS.length <= 1) return; // always keep at least one line
		var rowEl = document.querySelector('.baa-line[data-idx="' + idx + '"]');
		if (rowEl) rowEl.remove();
		BAA_ROWS = BAA_ROWS.filter(function(i) {
			return i !== idx;
		});
		delete COB_DATA[idx];
		delete COB_SIG[idx];
		delete COB_SEQ[idx];
		delete COB_LOAD[idx];
		delete COB_SEL[idx];
		delete COB_WIRED[idx];
		updateBaaRemoveButtons();
		recomputeBaaTotals();
	}

	function updateBaaRemoveButtons() {
		var onlyOne = BAA_ROWS.length <= 1;
		document.querySelectorAll('.baa-remove-line').forEach(function(btn) {
			btn.style.visibility = onlyOne ? 'hidden' : 'visible';
		});
	}

	// ── COB  →  GET /budget_line_item/approved ───────────────────────────────────
	// Filtered by the Program / Sub-Program / Project above: every level that has a value narrows the list.
	function cobQuery(idx) {
		var parts = [];
		[
			['program_code', 'prog'],
			['sub_program_code', 'subprog'],
			['project_code', 'proj']
		].forEach(function(p) {
			var value = pcValue(p[1]);
			if (value) parts.push(p[0] + '=' + encodeURIComponent(value));
		});
		var tof = cobEl('type_of_fund_id', idx);
		if (tof && tof.value) parts.push('type_of_fund_id=' + encodeURIComponent(tof.value));
		return parts.length ? '?' + parts.join('&') : '';
	}

	function cobUpdateHint(idx) {
		var hint = cobEl('cob_hint', idx);
		if (!hint) return;
		var n = (COB_DATA[idx] || []).length;
		var scope = pcValue('proj') ? ' for this project' : pcValue('subprog') ? ' for this sub-program' :
			pcValue('prog') ? ' for this program' : '';
		var tof = cobEl('type_of_fund_id', idx);
		var tofName = (tof && tof.value && tof.selectedIndex >= 0) ? tof.options[tof.selectedIndex].textContent : '';
		var narrowed = scope + (tofName ? ' with type of fund ' + tofName : '');
		hint.textContent = n ?
			n + ' approved COB(s) available' + narrowed + '.' + (narrowed ? '' :
				' Choose a type of fund, program, sub-program or project to narrow the list.') :
			'No approved COBs found' + narrowed + '.';
	}

	// Loads (or reloads) one line's list for the chain that is chosen right now. Resolves when that list is
	// in place; never rejects. A line that already has the list for this chain does nothing.
	function cobLoad(idx, keepPick) {
		var searchEl = cobEl('bv_search', idx);
		if (!searchEl) return Promise.resolve(); // the line was removed
		var sig = cobSignature(idx);
		if (COB_SIG[idx] === sig && COB_LOAD[idx]) return COB_LOAD[idx];

		COB_SIG[idx] = sig;
		var token = COB_EPOCH + ':' + (++COB_TOKEN_SEQ);
		COB_SEQ[idx] = token;
		var keep = keepPick === true || COB_KEEP_SELECTION > 0;

		searchEl.disabled = true;
		searchEl.placeholder = 'Loading COBs…';
		var hint = cobEl('cob_hint', idx);
		if (hint) hint.textContent = 'Loading approved COBs…';
		var url = API_BASE + '/budget_line_item/approved' + cobQuery(idx);

		COB_LOAD[idx] = (async function() {
			try {
				var json = await cba_apiFetch(url);
				if (COB_SEQ[idx] !== token) return; // overtaken by a newer load (or the line / form was reset)
				if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
					'Unexpected response format');

				COB_DATA[idx] = json.data;
				var el = cobEl('bv_search', idx);
				if (!el) return;

				// A pick that this chain no longer offers goes — unless a saved record is being filled in,
				// whose COBs belong to its own chain.
				var sel = COB_SEL[idx];
				if (sel && !keep && !json.data.some(function(r) {
						return String(r.budget_line_item_id) === String(sel.budget_line_item_id);
					})) {
					cobClearSelection(idx);
					el.value = '';
				}
				el.disabled = false;
				el.placeholder = 'Search account code or title…';
				cobUpdateHint(idx);
				cobSetupSearch(idx);
			} catch (err) {
				if (COB_SEQ[idx] !== token) return;
				console.error('[cobLoad]', err);
				COB_DATA[idx] = [];
				var failed = cobEl('bv_search', idx);
				if (failed) failed.placeholder = 'Could not load';
				var failedHint = cobEl('cob_hint', idx);
				if (failedHint) failedHint.textContent = '';
				showError('cob_error');
			}
		})();
		return COB_LOAD[idx];
	}

	// Called whenever a level of the Program / Sub-Program / Project chain changes. Several levels can change
	// in one go (choosing a program clears the two below it), so the reload is done once, after they have: first the
	// types of fund on offer, then every line's COB list (a line whose type of fund went is re-filtered).
	function cobChainChanged() {
		if (COB_SYNC_QUEUED) return;
		COB_SYNC_QUEUED = true;
		Promise.resolve().then(async function() {
			COB_SYNC_QUEUED = false;
			var keep = COB_KEEP_SELECTION > 0; // read now: the flag is gone by the time the requests come back
			await loadTypesOfFund();
			BAA_ROWS.forEach(function(idx) {
				cobLoad(idx, keep);
			});
		});
	}

	function cobMatches(idx, q) {
		return (COB_DATA[idx] || []).filter(function(r) {
			return !q || String(r.account_code || '').toLowerCase().includes(q) ||
				String(r.account_title || '').toLowerCase().includes(q) ||
				String(r.particulars || '').toLowerCase().includes(q);
		});
	}

	function cobRenderDropdown(idx, rows) {
		var dd = cobEl('bv_dropdown', idx);
		if (!dd) return;
		if (!rows.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}
		var shown = rows.slice(0, COB_LIST_MAX);
		dd.innerHTML = shown.map(function(r) {
			return '<div class="bv-item" data-id="' + pcEsc(r.budget_line_item_id) + '">' +
				'<div class="bv-item-name">' + pcEsc(r.account_code || 'No account code') + '</div>' +
				(r.account_title ? '<div class="bv-item-sub">' + pcEsc(r.account_title) + '</div>' : '') +
				'<div class="bv-item-sub">FY ' + pcEsc(r.fiscal_yr) + ' · Proposed ' + pcEsc(cobAmount(r.proposed_amount)) +
				(r.project_name ? ' · ' + pcEsc(r.project_name) : '') + '</div>' +
				'</div>';
		}).join('') + (rows.length > shown.length ?
			'<div style="padding:8px 14px;color:var(--afims-text-muted, #62657E);font-size:11px;">Showing the first ' +
			shown.length + ' of ' + rows.length + ' — type to narrow the list</div>' : '');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				var rec = (COB_DATA[idx] || []).find(function(r) {
					return String(r.budget_line_item_id) === item.dataset.id;
				});
				if (rec) cobSelect(idx, rec);
			});
		});
	}

	function cobSelect(idx, rec) {
		var idEl = cobEl('budget_line_item_id', idx);
		var searchEl = cobEl('bv_search', idx);
		if (!idEl || !searchEl) return;
		COB_SEL[idx] = rec;
		idEl.value = rec.budget_line_item_id;
		searchEl.value = cobLabel(rec);
		var dd = cobEl('bv_dropdown', idx);
		if (dd) dd.style.display = 'none';
		baaBvClearError(idx);
		cobSetType(idx, rec.type_of_fund_id, rec.type_of_fund); // the line's Type of Fund follows its COB

		cobEl('bvi_account', idx).textContent = [rec.account_code, rec.account_title].filter(Boolean).join(' — ') || '—';
		cobEl('bvi_fy', idx).textContent = rec.fiscal_yr || '—';
		cobEl('bvi_amount', idx).textContent = cobAmount(rec.proposed_amount);
		cobEl('bv_info', idx).style.display = 'block';
	}

	// Forget a line's pick (and optionally the text in its box).
	function cobClearSelection(idx, clearText) {
		delete COB_SEL[idx];
		var idEl = cobEl('budget_line_item_id', idx);
		if (idEl) idEl.value = '';
		var info = cobEl('bv_info', idx);
		if (info) info.style.display = 'none';
		if (clearText) {
			var searchEl = cobEl('bv_search', idx);
			if (searchEl) searchEl.value = '';
		}
	}

	// ── Type of Fund  →  GET /budget_line_item/approved/type_of_fund ──────────────────
	// The select offers the types of fund that exist on APPROVED budget line items — within the Program / Sub-Program /
	// Project chosen above — not every type of fund there is. It is optional: it narrows a line's COB list to line items
	// of the same type of fund (type_of_fund_id goes to /budget_line_item/approved), and picking a COB sets it.
	function tofQuery() {
		var parts = [];
		[
			['program_code', 'prog'],
			['sub_program_code', 'subprog'],
			['project_code', 'proj']
		].forEach(function(p) {
			var value = pcValue(p[1]);
			if (value) parts.push(p[0] + '=' + encodeURIComponent(value));
		});
		return parts.length ? '?' + parts.join('&') : '';
	}

	// Loads (or reloads) the types of fund available for the chain that is chosen right now, into every line's select.
	// A line whose type of fund is not available there any more goes back to "All types of fund" (and so gets its COB
	// list re-filtered) — unless a saved record's own chain is being filled in, whose types of fund are kept.
	async function loadTypesOfFund() {
		var sig = cobChainSignature();
		if (TOF_LOADED && TOF_SIG === sig) return;
		TOF_SIG = sig;
		var token = COB_EPOCH + ':t' + (++COB_TOKEN_SEQ);
		TOF_TOKEN = token;
		var keep = COB_KEEP_SELECTION > 0;
		try {
			var json = await cba_apiFetch(API_BASE + '/budget_line_item/approved/type_of_fund' + tofQuery());
			if (TOF_TOKEN !== token) return; // overtaken by a newer load (or the form was reset)
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			TOF_DATA = json.data;
			TOF_LOADED = true;
			BAA_ROWS.forEach(function(idx) {
				cobPopulateTypeSelect(cobEl('type_of_fund_id', idx), !keep);
			});
		} catch (err) {
			if (TOF_TOKEN !== token) return;
			console.error('[loadTypesOfFund]', err);
			TOF_LOADED = false; // the next chain change tries again
			BAA_ROWS.forEach(function(idx) {
				var sel = cobEl('type_of_fund_id', idx);
				if (sel && !sel.value) sel.innerHTML = '<option value="">Could not load</option>';
			});
			showError('type_of_fund_error');
		}
	}

	// Fill a line's select from TOF_DATA. The value it already has (a saved line's type of fund) is kept, even when
	// that type is not on offer — unless dropMissing says it must go.
	function cobPopulateTypeSelect(select, dropMissing) {
		if (!select) return;
		var cur = select.value;
		var curText = (cur && select.selectedIndex >= 0) ? select.options[select.selectedIndex].textContent : '';
		select.innerHTML = '<option value="">' + (TOF_DATA.length ? 'All types of fund' : 'No type of fund available') + '</option>';
		TOF_DATA.forEach(function(t) {
			var opt = document.createElement('option');
			opt.value = t.type_of_fund_id;
			opt.textContent = t.name;
			select.appendChild(opt);
		});
		if (cur) {
			var offered = [].some.call(select.options, function(o) {
				return o.value === cur;
			});
			if (offered || !dropMissing) {
				if (!offered && curText) {
					var kept = document.createElement('option');
					kept.value = cur;
					kept.textContent = curText;
					select.appendChild(kept);
				}
				select.value = cur;
			}
		}
		select.disabled = false;
	}

	// Set a line's Type of Fund from code (edit mode, "generate from a parent CBA", picking a COB) — not the user
	// changing it, so the COB already picked stays. A type that is not in the list is added from the name saved with
	// the line, so the record still shows it.
	function cobSetType(idx, id, name) {
		var sel = cobEl('type_of_fund_id', idx);
		if (!sel || id == null || id === '') return;
		var v = String(id);
		if (sel.value === v) return;
		if (![].some.call(sel.options, function(o) {
				return o.value === v;
			})) {
			if (!name) return;
			var opt = document.createElement('option');
			opt.value = v;
			opt.textContent = name;
			sel.appendChild(opt);
		}
		sel.value = v;
		cobLoad(idx, true); // the list follows the Type of Fund
	}

	// A saved line (edit mode, parent CBA): its Type of Fund, then its COB.
	async function cobRestoreLine(idx, line) {
		cobSetType(idx, line.type_of_fund_id, line.type_of_fund);
		await cobSelectById(idx, line.budget_line_item_id, line);
	}

	// Select a saved line's COB by id (edit mode, "generate from a parent CBA"). It is taken from the line's list
	// when it is there; otherwise from the details saved with the line (snapshot), so a COB that has since left
	// the approved list — or belongs to another chain — still shows on the record that used it.
	async function cobSelectById(idx, id, snapshot) {
		if (id == null || id === '' || !cobEl('bv_search', idx)) return;
		await cobLoad(idx);
		var rec = (COB_DATA[idx] || []).find(function(r) {
			return String(r.budget_line_item_id) === String(id);
		});
		if (!rec && snapshot) {
			rec = {
				budget_line_item_id: id,
				type_of_fund_id: snapshot.type_of_fund_id,
				type_of_fund: snapshot.type_of_fund || '',
				account_code: snapshot.account_code || '',
				account_title: snapshot.account_title || '',
				fiscal_yr: snapshot.fiscal_yr || '',
				proposed_amount: snapshot.proposed_amount
			};
		}
		if (rec) cobSelect(idx, rec);
	}

	// Wired once per line, when its first list arrives.
	function cobSetupSearch(idx) {
		var searchEl = cobEl('bv_search', idx);
		var dd = cobEl('bv_dropdown', idx);
		if (!searchEl || !dd || COB_WIRED[idx]) return;
		COB_WIRED[idx] = true;

		searchEl.addEventListener('input', function() {
			cobClearSelection(idx); // typing withdraws the pick
			cobRenderDropdown(idx, cobMatches(idx, this.value.trim().toLowerCase()));
		});
		searchEl.addEventListener('focus', function() {
			if (!cobEl('budget_line_item_id', idx).value) {
				cobRenderDropdown(idx, cobMatches(idx, this.value.trim().toLowerCase()));
			}
		});
		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── COB required for RFA / LAA ─────────────────────────────────────────────
	// RFA and LAA must always be tied to a COB, on every Budget Allocation Amount line. Enforced on submit by
	// validateBudgetVersionsRequired() — isBudgetVersionRequired() also drives the "*" marker. For CBA / CFA the
	// COB field is shown but optional.
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

	// Submit-time check: every line must have a COB when the allocation type is RFA / LAA. Checks the hidden
	// budget_line_item_id_{idx} (the actual pick), not the search box text, so half-typed text that was never
	// picked from the dropdown doesn't pass. Returns true when valid.
	function validateBudgetVersionsRequired() {
		if (!isBudgetVersionRequired()) return true;

		var firstInvalid = null;
		BAA_ROWS.forEach(function(idx) {
			var idEl = document.getElementById('budget_line_item_id_' + idx);
			var searchEl = document.getElementById('bv_search_' + idx);
			if (!idEl || !searchEl) return;

			var missing = idEl.value.trim() === '';
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

	// ── COB field: always shown ─────────────────────────────────────────────────
	// The COB field is shown for every document type / allocation type (it used to be hidden for CBA + CO).
	// It is only *required* (the "*" marker + submit check) for RFA / LAA — see isBudgetVersionRequired().
	function applyBudgetVersionVisibilityForDocType() {
		var bvRequired = isBudgetVersionRequired();

		document.querySelectorAll('.baa-line').forEach(function(rowEl) {
			var bvGroup = rowEl.querySelector('.baa-bv-group');
			if (bvGroup) bvGroup.style.display = '';

			var mark = rowEl.querySelector('.baa-bv-required');
			if (mark) mark.style.display = bvRequired ? '' : 'none';
			// Switching away from RFA/LAA — drop any stale "missing" error.
			if (!bvRequired) baaBvClearError(rowEl.dataset.idx);
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
		input.addEventListener('blur', function() {
			var raw = baaParseAmt(this.value);
			this.value = raw > 0 ? baaFormatAmt(raw) : '';
			baaSyncRaw(this);
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

	// Collects every row into the itemized array sent as `line_transaction`. A line keeps the Type of Fund and
	// Account Code of its COB next to the id, so the record still reads correctly if that line item changes later.
	function collectBaaLines() {
		return BAA_ROWS.map(function(idx) {
			var sel = COB_SEL[idx] || null;
			var tofEl = cobEl('type_of_fund_id', idx);
			var tofId = tofEl ? tofEl.value : '';
			var tofName = (tofId && tofEl.selectedIndex >= 0) ? tofEl.options[tofEl.selectedIndex].textContent : '';
			return {
				budget_line_item_id: sel ? String(sel.budget_line_item_id) : '',
				type_of_fund_id: tofId || (sel && sel.type_of_fund_id != null ? String(sel.type_of_fund_id) : ''),
				type_of_fund: tofName || (sel ? (sel.type_of_fund || '') : ''),
				account_code: sel ? (sel.account_code || '') : '',
				account_title: sel ? (sel.account_title || '') : '',
				fiscal_yr: sel && sel.fiscal_yr != null ? String(sel.fiscal_yr) : '',
				proposed_amount: sel && sel.proposed_amount != null ? String(sel.proposed_amount) : '',
				requested_amount: (document.getElementById('requested_amount_' + idx + '_raw') || {}).value || '',
				allocated_amount: (document.getElementById('allocated_amount_' + idx + '_raw') || {}).value || '',
			};
		}).filter(function(line) {
			// Drop a fully-empty trailing row (e.g. one added then left untouched).
			return line.budget_line_item_id || line.type_of_fund_id || line.requested_amount;
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

	// Only CBAs with this status are offered as a parent (a status NAME of the memo-request statuses; the API
	// resolves it, so no install-specific id is needed here). A name that matches no status offers nothing.
	var CBA_PARENT_STATUS = 'Approved';

	// What a CBA is called in the list: its allocation number, else the request reference the database gave it
	// (RR-001, …) — every row has one, while a new CBA carries no allocation number — then program and fiscal year.
	function cbaLabelOf(c) {
		return (c.allocation_number || c.request_referrence_no || ('#' + c.allocation_id)) +
			(c.program_name ? ' — ' + c.program_name : '') +
			(c.fiscal_year ? ' · FY' + c.fiscal_year : '');
	}

	function cbaBuildItem(c, query) {
		var label = cbaLabelOf(c);
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
				return [c.request_referrence_no, c.allocation_number, c.program_name, c.sub_program_name, c.project_name, c.description]
					.some(function(s) {
						return (s || '').toLowerCase().includes(q);
					});
			}) :
			CBA_DATA;

		ul.innerHTML = '';
		CBA_ACTIVE_IDX = -1;

		if (filtered.length === 0) {
			var li = document.createElement('li');
			li.className = 'cba-no-results';
			li.textContent = q ? 'No approved CBA matches "' + q + '"' : 'No approved CBA found.';
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
			var json = await cba_apiFetch(`${API_BASE}/budget_allocation/all?allocation_type=CBA&status=${encodeURIComponent(CBA_PARENT_STATUS)}`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message || 'Bad response');

			CBA_DATA = json.data;
			CBA_LOADED = true;
			search.disabled = false;
			search.placeholder = 'Search approved CBAs by request ref no. or program…';
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

	// (the 2-minute refresh of this is started by cbaStartTimers() - see the Boot block at the end)

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
		var num = buildAllocationNumber(type, document.getElementById('document_type').value);

		document.getElementById('allocation_number').value = num;
		document.getElementById('cba_number_display').textContent = num;
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
	// statusId: the id of the memo-request status to give the record (see cbaStatusIdFor), sent as `status_id` — the only
	// field the API reads for it. Leave it out and create gives the new request 'Draft' while update leaves the status
	// alone. (The `status: 'DRAFT' / 'FOR_REVIEW'` text this used to send was never read by the API, so submitting
	// never actually changed a status.)
	function buildPayload(statusId) {
		var g = function(id) {
			return document.getElementById(id);
		};
		var v = function(id) {
			return g(id) ? g(id).value : '';
		};

		var payload = {
			allocation_type: v('allocation_type'),
			cba_id: v('cba_id'),
			// There is no memo_ref_no any more (the column was dropped, alter_table/20): a request is identified by the
			// request_referrence_no (RR-001, ...) that the DATABASE generates when the row is created — nothing to send.
			// The generated allocation number (#allocation_number, e.g. "CBA 2026-111-86-01") is deliberately NOT sent
			// either, and not copied into any other column: numbering is not saved to the database yet. It is still
			// generated into #allocation_number for display / print preview, and on edit the record keeps whatever
			// number it already has (PATCH only changes the fields sent).
			fiscal_year: v('fiscal_year'),
			document_type: v('document_type'),
			program_code: v('program_code'),
			sub_program_code: v('sub_program_code'),
			project_code: v('project_code'),
			// Typed instead of picked = a NEW program / sub-program / project: the server creates it (or reuses an
			// existing one of the same name) together with this record. '' when the level was picked or left empty.
			program_name: pcTyped('prog'),
			sub_program_name: pcTyped('subprog'),
			project_name: pcTyped('proj'),
			requested_amount: v('requested_amount_raw'),
			allocated_amount: v('allocated_amount_raw'),
			// Itemized Budget Allocation Amount lines (fmd.budget_allocations.line_transaction, jsonb).
			line_transaction: JSON.stringify(collectBaaLines()),
			documents_complete: '1',
			created_by: v('created_by'),
			multiple_node_id: v('multiple_node_id'),
			funding_status: 'PENDING',
			notification_sent: '0',
			project_description: v('project_description'),
			remarks: v('remarks'),
			is_bidding: g('is_bidding') ? (g('is_bidding').checked ? '1' : '0') : '0',
			category_id: v('category_id'),
			vendor_id: v('vendor_id'),
			...pcrPayloadFields(), // pr_number + pr_amount, or po_number + po_amount (RFA / LAA)
			file_data: getSupportingDocs(),
		};
		if (statusId != null) payload.status_id = statusId;
		return payload;
	}

	// ── POST to API ───────────────────────────────────────────────────────────────
	async function postToApi(payload) {
		// A vendor typed in that does not exist yet is created first, so the allocation can point at it.
		// Throws before anything is saved when that fails (callers show the message).
		await vendEnsure();
		payload.vendor_id = vendEl('vendor_id').value;

		var nullableFKFields = [
			'program_code',
			'sub_program_code',
			'project_code',
			'program_name',
			'sub_program_name',
			'project_name',
			'category_id',
			'vendor_id',
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
			// no status_id: create gives every new request the 'Draft' status
			var json = await postToApi(buildPayload());
			if (!json.success) throw new Error(json.message || 'Failed to save draft.');
			alert('Draft saved successfully.');
			cbaAfterSave(); // modal mode: close + refresh the list; page mode: stay on the form
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

	// Text of the main button: "Submit Request" when creating (whatever the allocation type), "Update <type>" when
	// editing a saved record.
	function submitButtonLabel() {
		return CBA_EDIT_MODE ? ('Update ' + getTypeLabel().short) : 'Submit Request';
	}

	function updateSubmitLabel() {
		var label = getTypeLabel();
		var span = document.getElementById('submit_btn_label');
		if (span) span.textContent = submitButtonLabel();

		// "CBA Number" header card label — follows the selected allocation type
		// (CBA / RFA / LAA) instead of always reading "CBA Number".
		var numLabel = document.getElementById('cba_number_label');
		if (numLabel) numLabel.textContent = label.short + ' Number';
	}

	// ── Submit decision pop-up ────────────────────────────────────────────────────
	// What "Submit Request" can turn a request into. `status` is the NAME of a memo-request status
	// (fims.settings_status, status_for = 'budget_allocation'); its id comes from the API (cbaStatusIdFor), so no
	// install-specific id lives here. 'Draft' ships with the schema, 'For Preview' with
	// sample_data/06 - fims_settings_status_budget_allocation.sql.
	var CBA_DECISIONS = {
		preview: {
			status: 'For Preview',
			done: 'submitted for preview',
			busy: 'Submitting'
		},
		draft: {
			status: 'Draft',
			done: 'saved as a draft',
			busy: 'Saving'
		},
	};

	// Resolves with the id of the memo-request status called `name`. Throws — before anything is saved — when the
	// statuses cannot be read or that one is not set up. Read on every use, so a status added while the page was open
	// is found.
	async function cbaStatusIdFor(name) {
		var json = await cba_apiFetch(`${API_BASE}/budget_allocation/statuses`);
		if (!json.success || !Array.isArray(json.data)) {
			throw new Error(json.message || 'Could not load the request statuses.');
		}
		var hit = json.data.find(function(s) {
			return String(s.name || '').trim().toLowerCase() === name.toLowerCase();
		});
		if (!hit) {
			throw new Error('The status "' + name + '" is not set up in the database yet, so nothing was saved. ' +
				'Ask an administrator to run the SQL installer (node install_system.js --sql-only).');
		}
		return parseInt(hit.id, 10);
	}

	// Asks what should happen to the request: resolves with 'preview' or 'draft', or null when it is cancelled (the
	// Cancel button, Escape, or a click outside the box). Built here and appended to <body> rather than written into the
	// page: it has to sit above the memo list's modal and survive that modal re-loading its form.
	function cbaAskDecision(typeLabel) {
		return new Promise(function(resolve) {
			if (document.getElementById('cba-decision-overlay')) { // already asking
				resolve(null);
				return;
			}
			var opener = document.activeElement;
			var overlay = document.createElement('div');
			overlay.id = 'cba-decision-overlay';
			overlay.className = 'cbadec-overlay';
			overlay.innerHTML =
				'<div class="cbadec-box" role="dialog" aria-modal="true" aria-labelledby="cbadec-title" aria-describedby="cbadec-text">' +
				'<div class="cbadec-hdr"><h3 class="cbadec-title" id="cbadec-title"></h3></div>' +
				'<div class="cbadec-body">' +
				'<p class="cbadec-text" id="cbadec-text"></p>' +
				'<p class="cbadec-current" id="cbadec-current" hidden></p>' +
				'<button type="button" class="cbadec-opt cbadec-opt-primary" data-decision="preview">' +
				'<span class="cbadec-opt-title">Submit for Preview</span>' +
				'<span class="cbadec-opt-desc">Sends the request on to be previewed. Its status becomes <strong></strong>.</span>' +
				'</button>' +
				'<button type="button" class="cbadec-opt" data-decision="draft">' +
				'<span class="cbadec-opt-title">Save as Draft</span>' +
				'<span class="cbadec-opt-desc">Keeps the request as a draft you can still change. Its status becomes <strong></strong>.</span>' +
				'</button>' +
				'</div>' +
				'<div class="cbadec-ftr"><button type="button" class="cbadec-cancel" data-decision="">Cancel</button></div>' +
				'</div>';

			// the words that vary are set as text, from CBA_DECISIONS, so they have a single source
			overlay.querySelector('#cbadec-title').textContent = CBA_EDIT_MODE ? 'Save changes' : 'Submit this request?';
			overlay.querySelector('#cbadec-text').textContent = CBA_EDIT_MODE ?
				('Your changes to this ' + typeLabel.full + ' will be saved. What should its status be?') :
				('What should happen to this ' + typeLabel.full + '?');
			overlay.querySelector('[data-decision="preview"] strong').textContent = CBA_DECISIONS.preview.status;
			overlay.querySelector('[data-decision="draft"] strong').textContent = CBA_DECISIONS.draft.status;
			if (CBA_EDIT_MODE && CBA_CURRENT_STATUS) {
				var cur = overlay.querySelector('#cbadec-current');
				cur.textContent = 'Current status: ' + CBA_CURRENT_STATUS;
				cur.hidden = false;
			}

			function finish(decision) {
				document.removeEventListener('keydown', onKey, true);
				overlay.remove();
				if (opener && typeof opener.focus === 'function') opener.focus();
				resolve(decision);
			}

			function onKey(e) {
				if (e.key === 'Escape') {
					e.preventDefault();
					e.stopPropagation();
					finish(null);
				} else if (e.key === 'Tab') { // keep the focus inside the box
					var btns = overlay.querySelectorAll('button');
					var first = btns[0],
						last = btns[btns.length - 1];
					if (!overlay.contains(document.activeElement) || (e.shiftKey && document.activeElement === first)) {
						e.preventDefault();
						(e.shiftKey ? last : first).focus();
					} else if (!e.shiftKey && document.activeElement === last) {
						e.preventDefault();
						first.focus();
					}
				}
			}
			overlay.addEventListener('mousedown', function(e) {
				if (e.target === overlay) finish(null);
			});
			overlay.addEventListener('click', function(e) {
				var b = e.target.closest('button[data-decision]');
				if (b) finish(b.dataset.decision || null);
			});
			document.addEventListener('keydown', onKey, true);
			document.body.appendChild(overlay);
			overlay.querySelector('[data-decision="preview"]').focus();
		});
	}

	// ── Submit ────────────────────────────────────────────────────────────────────
	async function cbaOnSubmit(e) {
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
			alert('COB is required for ' + getTypeLabel().short +
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

		// Replaces the old "Are you sure?" confirm(): ask what should become of the request — submitted for preview, or
		// kept as a draft — and give it that status. Cancelled -> nothing is sent.
		var choice = await cbaAskDecision(typeLabel);
		if (!choice) return;
		var decision = CBA_DECISIONS[choice];

		var submitBtn = this.querySelector('button[type="submit"]');
		submitBtn.disabled = true;
		submitBtn.innerHTML = '<span>' + decision.busy + '&hellip;</span>';

		try {
			// The id of the chosen status, looked up first: when it cannot be found this throws and NOTHING has been saved.
			var statusId = await cbaStatusIdFor(decision.status);
			var json = await postToApi(buildPayload(statusId));
			if (!json.success) throw new Error(json.message || (CBA_EDIT_MODE ? 'Update failed.' :
				'Submission failed.'));
			alert(typeLabel.full + ' ' + (CBA_EDIT_MODE ? 'updated and ' : '') + decision.done + '.');
			if (cbaAfterSave()) return; // modal mode: closed + list refreshed, nothing to redirect to
			var docType = document.getElementById('document_type').value;
			window.location.href = docType === 'COE' ? '/FMD/CoeBudgetAllocationDashboard' :
				'/FMD/BudgetAllocationDashboard';
		} catch (err) {
			console.error('[submitCBA]', err);
			alert((CBA_EDIT_MODE ? 'Update error: ' : 'Submission error: ') + err.message);
			submitBtn.disabled = false;
			var restoredLabel = submitButtonLabel();
			submitBtn.innerHTML =
				'<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> <span id="submit_btn_label">' +
				restoredLabel + '</span>';
		}
	}

	// ── Block Enter outside textarea ──────────────────────────────────────────────
	function cbaBlockEnter(e) {
		if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') e.preventDefault();
	}

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
				document_type: v('document_type'),
				// Budget Allocation Category (e.g. "Goods and Services") — printed
				// below "Sources of Fund" on CO documents (see gen_cba_docx.js).
				category_name: g('category_id') ? (g('category_id').dataset
					.label || '') : '',
				program_code: v('program_code'),
				program_name: (g('program_code') && g('program_code').dataset.label) || pcTyped('prog'),
				sub_program_code: v('sub_program_code'),
				sub_program_name: (g('sub_program_code') && g('sub_program_code').dataset.label) || pcTyped('subprog'),
				project_code: v('project_code'),
				project_name: (g('project_code') && g('project_code').dataset.label) || pcTyped('proj'),
				project_description: v('project_description'),
				requested_amount: v('requested_amount_raw'),
				allocated_amount: v('allocated_amount_raw'),
				remarks: v('remarks'),
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
				// Payee = the requesting office: the Responsibility Center of the user who created the memo request
				// (CBA_REQ_RC: uac.users.rc_id -> fims.responsibility_code).
				payload.payee_name = CBA_REQ_RC.rc_name || '';
				// Picks the RFA template: a MAIN (central) office prints the STAFF form ("(STAFF)", filled out by
				// FMD), a Regional / District office the REGIONAL OFFICE form — from the RC's office_type.
				payload.requesting_department_type = cbaRcDeptType(CBA_REQ_RC.office_type);
				// RFA type line = "<CATEGORY> – Php 30 Million and below (<OFFICE>)", using
				// the selected Budget Allocation Category's name. print-preview.js and
				// gen_rfa_docx.js both use payload.rfa_type ahead of their default
				// ("GOODS AND SERVICES – …"), so setting it here changes the preview and
				// the DOCX together. No category selected -> left unset (default applies).
				(function() {
					var cat = String(payload.category_name || '').trim();
					if (!cat) return;
					var isStaff = payload.requesting_department_type === 'MAIN';
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
				// A COB is an approved budget line item: it has no fund balance or SARO reference of its own, so those
				// print blank (total funds available falls back to the amounts below).
				var rfaBvFundBalance = 0;
				var rfaSaroRef = '';
				var rfaSaroDate = '';

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

				// office_name = the requesting office (the creator's Responsibility Center name) — the same label
				// RFA uses for its payee. COE LAA (gen_coe_laa_docx.js) reads "To transfer obligation made to
				// <office> to cover the payment for the <description>"; CO LAA (gen_laa_docx.js) prints it as the
				// addressee and in the "<office> to cover the <description>" transfer text. The generated DOCX
				// derives it server-side, but the on-screen preview never calls the server, so pass it here.
				payload.office_name = CBA_REQ_RC.rc_name || '';
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

	// ==============================================================================
	// BOOT + MODAL CONTROLLER
	//
	// Page mode  (views/BudgetAllocationForm.php): boots once, exactly as it always did.
	// Modal mode (memo-req-page.php sets $cbaAsModal): nothing runs at page load. The
	//   lookups, the 2-minute counter refresh and the loading overlay all start when the user
	//   opens the modal, and every open starts from the untouched markup, so a form that was
	//   cancelled or saved never leaks into the next one.
	// This block must stay LAST: boot reads variables declared throughout the script above.
	// ==============================================================================
	var CBA_COUNT_TIMER = null;
	var CBA_PRISTINE_HTML = null; // the form markup exactly as the server rendered it

	function cbaStartTimers() {
		if (!CBA_COUNT_TIMER) CBA_COUNT_TIMER = setInterval(refreshAllocationCount, 2 * 60 * 1000);
	}

	function cbaStopTimers() {
		clearInterval(CBA_COUNT_TIMER);
		CBA_COUNT_TIMER = null;
	}

	// Everything the form remembers between loads. Besides the data caches this includes
	// "wire once" flags and a cached <option> node that belong to one specific copy of the DOM:
	// left over from a previous open they would point at removed elements, so the combobox
	// search boxes would never get their listeners again on the second open.
	function cbaResetState() {
		CBA_CREATED_AT = null;
		CBA_REQ_RC = Object.assign({ rc_id: '', rc_code: '', rc_name: '', office_type: '' }, CBA_CONFIG.requestingRc || {});   // back to the user's own RC
		pcResetState();
		clearTimeout(CAT_DEBOUNCE); CAT_DEBOUNCE = null;
		CAT_ALL_DATA = [];
		clearTimeout(VEND_DEBOUNCE); VEND_DEBOUNCE = null; VEND_SEQ++;
		VEND_ALL_DATA = [];
		VEND_TYPES = []; VEND_TYPES_FAILED = false;
		VEND_MODE = 'none';
		BAA_ROWS = []; BAA_ROW_SEQ = -1;
		COB_EPOCH++; COB_DATA = {}; COB_SIG = {}; COB_SEQ = {}; COB_LOAD = {}; COB_SEL = {}; COB_WIRED = {};
		COB_SYNC_QUEUED = false; COB_KEEP_SELECTION = 0;
		TOF_DATA = []; TOF_LOADED = false; TOF_SIG = null; TOF_TOKEN = '';
		ALLOC_CFA_OPTION = null;
		CBA_DATA = []; CBA_LOADED = false; CBA_ACTIVE_IDX = -1;
		_sdocCount = 0;
	}

	// After a successful save. Modal mode: close and refresh the host page's list (returns true).
	// Page mode: does nothing and returns false, so the caller keeps its own behaviour.
	function cbaAfterSave() {
		if (!CBA_AS_MODAL) return false;
		window.cbaModal.close();
		if (typeof window.mrq_load === 'function') window.mrq_load();
		return true;
	}

	// The Cancel button and the modal's close button.
	function cbaCancel() {
		if (CBA_AS_MODAL) window.cbaModal.close();
		else window.history.back();
	}

	if (CBA_AS_MODAL) {
		window.cbaModal = {
			open: function() {
				var container = document.querySelector('.cba-form-container');
				if (CBA_PRISTINE_HTML === null) CBA_PRISTINE_HTML = container.innerHTML; // first open: still untouched
				else container.innerHTML = CBA_PRISTINE_HTML;                            // later opens: start over
				cbaResetState();
				document.getElementById('cba-overlay').classList.add('open');
				document.getElementById('cba-modal-body').scrollTop = 0;
				cbaBoot();
				cbaStartTimers();
			},
			close: function() {
				document.getElementById('cba-overlay').classList.remove('open');
				cbaStopTimers();
			},
		};
	} else if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			cbaBoot();
			cbaStartTimers();
		});
	} else {
		cbaBoot();
		cbaStartTimers();
	}
</script>
