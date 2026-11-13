<?php

namespace PHPMaker2024\FIMS;

/**
 * global_script.php
 *
 * Shared page partial (PHP + HTML + JS) that is `include`d by multiple custom
 * pages in `app/pages/`. Put here ONLY what 2+ pages need: shared modals,
 * shared JS helpers, and the small amount of PHP that feeds them.
 *
 * How a page uses it (place near the end of the page, after its own markup):
 *
 *     include(dirname(__DIR__, 1) . "/global_script.php");
 *
 * Rules:
 * - Page-specific markup/JS stays in that page's own file, not here.
 * - Backend/database logic belongs in a service under `app/lib/`
 *   (see `_guidelines/api-route-service-guidelines.md`); call it through an
 *   API route from JS. Keep PHP here to the minimum needed to render shared UI.
 * - Reusable PHP helper functions belong in `app/lib/GlobalService.php`.
 * - Code shared across ALL FIMS modules belongs in `_shared/`, not here.
 * - Because this file is included by many pages, a change here affects all of
 *   them. Check every including page (grep `global_script`) before editing.
 * - Pages may set variables before the include to customize behavior; read them
 *   here with a default (e.g. `$departmentName = $departmentName ?? '';`).
 * - Escape all output: `HtmlEncode()` in PHP; `textContent` or an `escapeHtml`
 *   helper in JS.
 *
 * Currently empty. Add shared pieces as the need arises.
 */

global $Security;
if (!isset($Security)) {
    $Security = new AdvancedSecurity();
}

?>

<!-- ======================================================================
     SHARED MODALS / HTML
     (none yet)
     ====================================================================== -->

<script type="text/javascript">
    // ======================================================================
    // SHARED JS
    // (none yet) Keep helpers grouped under one namespace object to avoid
    // global name collisions between pages, e.g.:
    //
    //   window.FIMS = window.FIMS || {};
    //   FIMS.formatCurrency = function (n) { ... };
    // ======================================================================
</script>
