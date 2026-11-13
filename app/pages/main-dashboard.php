<?php
/**
 * Main Dashboard for FIMS.
 *
 * Layout/sectioning redesigned to match the TREASURY reference project's
 * main-dashboard.php ("Command Center" structure): a KPI row, two rows of
 * charts/tables in a 7/5 column split with flex card headers (+ export
 * buttons), then an action-items row (activity feed | secondary panels
 * stacked). The TREASURY version renders its actual card/KPI content via
 * external JS modules (_shared/FMD/dashboard/js/*.js) that aren't present in
 * the reference copy, so there was nothing pixel-level to copy for the
 * cards themselves — this keeps this page's own real data (users, systems,
 * user levels, audit logs) but reshapes it into that same structure and
 * card styling (shadow-sm border rounded-3, flex headers).
 */
namespace PHPMaker2024\FIMS;

// Key Metrics
$totalUsers = ExecuteScalar("SELECT COUNT(*) FROM users", "DB");
$totalUsersLastMonth = ExecuteScalar("SELECT COUNT(*) FROM users WHERE date_created <= CURRENT_DATE - INTERVAL '1 month'", "DB");
$totalUsersChange = ($totalUsersLastMonth > 0) ? round((($totalUsers - $totalUsersLastMonth) / $totalUsersLastMonth) * 100, 2) : 100;

$activeUsers = ExecuteScalar("SELECT COUNT(*) FROM users WHERE is_active = TRUE", "DB");
$activeUsersPercent = ($totalUsers > 0) ? round(($activeUsers / $totalUsers) * 100, 2) : 0;

$totalSystems = ExecuteScalar("SELECT COUNT(*) FROM systems", "DB");

$totalUserLevels = ExecuteScalar("SELECT COUNT(*) FROM user_levels", "DB");

// User Growth Chart Data
$userGrowthData = ExecuteRowsAssociative("
    WITH RECURSIVE date_range AS (
        SELECT DATE_TRUNC('month', CURRENT_DATE - INTERVAL '5 months') AS month
        UNION ALL
        SELECT month + INTERVAL '1 month'
        FROM date_range
        WHERE month < DATE_TRUNC('month', CURRENT_DATE)
    )
    SELECT
        TO_CHAR(dr.month, 'Mon') as month,
        COALESCE(COUNT(u.user_id), 0) as total_users,
        COALESCE(SUM(CASE WHEN u.is_active = TRUE THEN 1 ELSE 0 END), 0) as active_users
    FROM date_range dr
    LEFT JOIN users u ON DATE_TRUNC('month', u.date_created) <= dr.month
    GROUP BY dr.month
    ORDER BY dr.month
", "DB");

$chartLabels = array_column($userGrowthData, 'month');
$totalData = array_column($userGrowthData, 'total_users');
$activeData = array_column($userGrowthData, 'active_users');

$userGrowthChartData = [
    'labels' => $chartLabels,
    'datasets' => [
        [
            'label' => 'Total Users',
            'data' => $totalData,
            'borderColor' => '#000E3E',
            'backgroundColor' => 'rgba(0,53,169,0.08)',
            'fill' => true,
            'tension' => 0.4,
            'borderWidth' => 2.5,
            'pointRadius' => 3,
            'pointHoverRadius' => 6,
            'pointBackgroundColor' => '#fff',
            'pointBorderColor' => '#000E3E',
            'pointBorderWidth' => 2
        ],
        [
            'label' => 'Active Users',
            'data' => $activeData,
            'borderColor' => 'rgba(0,14,62,.6)',
            'backgroundColor' => 'rgba(0,14,62,0.10)',
            'fill' => true,
            'tension' => 0.4,
            'borderWidth' => 2.5,
            'pointRadius' => 3,
            'pointHoverRadius' => 6,
            'pointBackgroundColor' => '#fff',
            'pointBorderColor' => 'rgba(0,14,62,.6)',
            'pointBorderWidth' => 2
        ]
    ]
];

// Audit Log Timeline
$auditLogTimeline = ExecuteRowsAssociative("
    SELECT
        action_date,
        script,
        \"user\",
        action,
        \"table\",
        action_type,
        details,
        action_count,
        aggregated_id
    FROM aggregated_audit_logs
    ORDER BY aggregated_id DESC
    LIMIT 20
", "DB");

// Function to get appropriate icon for action type
function getActionIcon($actionType) {
    switch ($actionType) {
        case 'Add':
            return 'fa-plus';
        case 'Update':
            return 'fa-edit';
        case 'Delete':
            return 'fa-trash';
        case 'Login':
            return 'fa-sign-in-alt';
        case 'Logout':
            return 'fa-sign-out-alt';
        default:
            return 'fa-info-circle';
    }
}

// Function to get appropriate color for action type
function getActionColor($actionType) {
    switch ($actionType) {
        case 'Add':
            return 'success';
        case 'Update':
            return 'info';
        case 'Delete':
            return 'danger';
        case 'Login':
            return 'primary';
        case 'Logout':
            return 'secondary';
        default:
            return 'warning';
    }
}

// Literal hex equivalent of getActionColor()'s Bootstrap-style names — used
// for inline background colors (var(--afims-success)/--afims-danger/etc.
// are never actually declared in fims.css, only ever referenced with a
// fallback, so a var() lookup here would silently render every color the
// same instead of being color-coded by action type).
function getActionColorHex($actionType) {
    switch (getActionColor($actionType)) {
        case 'success':
            return '#059669';
        case 'info':
            return '#0891b2';
        case 'danger':
            return '#dc2626';
        case 'primary':
            return '#2563eb';
        case 'secondary':
            return '#64748b';
        default:
            return '#d97706';
    }
}

// User Level Distribution
$userLevelDistribution = ExecuteRowsAssociative("
    SELECT ul.name, COUNT(*) as count
    FROM user_level_assignments ula
    JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    GROUP BY ul.user_level_id, ul.name
", "DB");

$levelLabels = array_column($userLevelDistribution, 'name');
$levelCounts = array_column($userLevelDistribution, 'count');

// Single dark-blue (#000E3E) at stepped opacity — same hue throughout
// instead of varying shades, so multi-slice charts stay one flat dark-blue
// theme (alpha is the only thing that differentiates segments).
$blueShades = ['rgba(0,14,62,1)', 'rgba(0,14,62,.82)', 'rgba(0,14,62,.66)', 'rgba(0,14,62,.5)', 'rgba(0,14,62,.38)', 'rgba(0,14,62,.28)', 'rgba(0,14,62,.2)'];

$userLevelChartData = [
    'labels' => $levelLabels,
    'datasets' => [
        [
            'data' => $levelCounts,
            'backgroundColor' => array_map(function ($i) use ($blueShades) {
                return $blueShades[$i % count($blueShades)];
            }, array_keys($levelCounts)),
            'borderWidth' => 3,
            'borderColor' => '#fff',
            'hoverOffset' => 8
        ]
    ]
];

// Top System Usage
$topSystems = ExecuteRowsAssociative("
    SELECT s.system_name, COUNT(*) as user_count
    FROM user_level_assignments ula
    JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    JOIN systems s ON ul.system_id = s.system_id
    GROUP BY s.system_id, s.system_name
    ORDER BY user_count DESC
    LIMIT 5
", "DB");

$topSystemsChartData = [
    'labels' => array_column($topSystems, 'system_name'),
    'datasets' => [
        [
            'label' => 'Users',
            'data' => array_column($topSystems, 'user_count'),
            'backgroundColor' => array_map(function ($i) use ($blueShades) {
                return $blueShades[$i % count($blueShades)];
            }, array_keys($topSystems)),
            'borderRadius' => 6,
            'borderSkipped' => false,
            'barThickness' => 18
        ]
    ]
];

// Recent User Registrations
$recentUsers = ExecuteRowsAssociative("
    SELECT u.username, u.email, u.date_created, ul.name as user_level
    FROM users u
    LEFT JOIN user_level_assignments ula ON u.user_id = ula.user_id
    LEFT JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    ORDER BY u.date_created DESC
    LIMIT 5
", "DB");

// System Health
$systemHealth = ExecuteRowsAssociative("
    SELECT s.system_name,
           CASE
               WHEN COUNT(ula.user_id) > 0 THEN 'Active'
               ELSE 'Inactive'
           END as status,
           COUNT(DISTINCT ula.user_id) as active_users
    FROM systems s
    LEFT JOIN user_levels ul ON s.system_id = ul.system_id
    LEFT JOIN user_level_assignments ula ON ul.user_level_id = ula.user_level_id
    GROUP BY s.system_id, s.system_name
", "DB");

// Prepare data for JavaScript charts
$userGrowthChartDataJson = json_encode($userGrowthChartData);
$userLevelChartDataJson = json_encode($userLevelChartData);
$topSystemsChartDataJson = json_encode($topSystemsChartData);

// URLs for quick actions
$createUserUrl = GetUrl("UsersAdd");
$manageUserLevelsUrl = GetUrl("UserLevelsList");
$manageSystemsUrl = GetUrl("SystemsList");
$viewAuditLogsUrl = GetUrl("AuditLogsList");

$dashboardCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?php echo $dashboardCssVer; ?>" />

<!-- Main content -->
<div class="container-fluid py-4">

    <!-- Clock + Weather + NHA Slideshow — ported verbatim (markup/behavior)
         from the TREASURY reference project's treasury-dashboard.php. The
         CSS (.td-wt-row, .td-clock-card, .td-weather-card, .td-slideshow,
         the animated cityscape SVG classes) is already in fims.css (merged
         in earlier from fims-treasury.css). Fully self-contained: the clock
         is plain JS Date, weather uses the public ipapi.co + open-meteo.com
         APIs (no key/auth needed), and the slideshow uses public Wikimedia
         Commons photos of the NHA building — none of it depends on this
         project's own backend.

         id="tdash" supplies --td-pad/--td-gap/--td-radius/--td-card-p, which
         .td-clock-card/.td-weather-card/.td-slideshow's own padding and
         border-radius depend on — without it those custom properties are
         undefined, which silently invalidates the whole padding/border-radius
         declaration that references them (not just falls back to 0).

         #tdash's own rule also sets min-height:100vh and a full-page
         background color (correct when it wraps TREASURY's entire page body,
         wrong here since it's just a variable scope, not the whole page body)
         — neutralized inline so it supplies just the custom properties. This
         one wrapper spans everything below through the KPI/chart/bottom rows
         too (they need the same --td-* variables), not just this row. -->
    <div id="tdash" style="min-height:0;background:transparent;">
    <div class="td-wt-row mb-4">

        <!-- Clock + Weather stacked (left) -->
        <div class="td-wt-right">

            <!-- Live Clock -->
            <div class="td-clock-card">
                <div class="td-clock-left">
                    <div style="margin-bottom:10px;">
                        <div style="font-size:1.1rem;font-weight:700;color:#fff;line-height:1.2;">Main Dashboard</div>
                        <div style="font-size:.7rem;color:rgba(255,255,255,.75);">User access, systems, and activity overview</div>
                    </div>
                    <div class="td-clock-time" id="wt-time">--:--<span class="td-clock-ampm" id="wt-ampm"></span></div>
                </div>
                <div class="td-clock-divider"></div>
                <div class="td-clock-right">
                    <div class="td-clock-day" id="wt-day">—</div>
                    <div class="td-clock-date" id="wt-date">—</div>
                    <div class="td-clock-badge"><i class="fas fa-map-marker-alt"></i> Philippines</div>
                </div>
                <!-- Animated NHA Cityscape -->
                <div class="td-house-wrap">
                    <svg class="td-house-svg" width="170" height="108" viewBox="0 0 170 108">
                        <defs>
                            <linearGradient id="skyDay" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#38bdf8"/>
                                <stop offset="80%" stop-color="#7dd3fc"/>
                                <stop offset="100%" stop-color="#bae6fd"/>
                            </linearGradient>
                            <radialGradient id="sunGlow" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="rgba(253,224,71,.75)"/>
                                <stop offset="100%" stop-color="rgba(253,224,71,0)"/>
                            </radialGradient>
                            <radialGradient id="moonGlow" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="rgba(255,255,220,.9)"/>
                                <stop offset="100%" stop-color="rgba(255,255,180,.3)"/>
                            </radialGradient>
                            <radialGradient id="lampGlow1" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="rgba(255,220,80,.8)"/>
                                <stop offset="100%" stop-color="rgba(255,220,80,0)"/>
                            </radialGradient>
                            <radialGradient id="lampGlow2" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="rgba(255,220,80,.8)"/>
                                <stop offset="100%" stop-color="rgba(255,220,80,0)"/>
                            </radialGradient>
                            <clipPath id="moonClip">
                                <circle cx="154" cy="18" r="10"/>
                            </clipPath>
                        </defs>

                        <circle class="h-star" style="animation-delay:.2s,2.7s" cx="14" cy="8" r="1.1" fill="#fff"/>
                        <circle class="h-star" style="animation-delay:.35s,3.1s" cx="35" cy="5" r="1.3" fill="#fff"/>
                        <circle class="h-star" style="animation-delay:.5s,2.3s" cx="58" cy="11" r="1" fill="#fff"/>
                        <circle class="h-star" style="animation-delay:.65s,3.5s" cx="80" cy="4" r="1.2" fill="#fff"/>
                        <circle class="h-star" style="animation-delay:.4s,2.9s" cx="105" cy="9" r="1" fill="#fff"/>
                        <circle class="h-star" style="animation-delay:.55s,3.3s" cx="128" cy="6" r="1.3" fill="#fff"/>
                        <circle class="h-star" style="animation-delay:.7s,3.7s" cx="165" cy="5" r="1.1" fill="#fff"/>

                        <g class="h-moon">
                            <circle cx="154" cy="18" r="13" fill="rgba(255,255,200,.08)"/>
                            <circle cx="154" cy="18" r="10" fill="rgba(255,248,200,.92)"/>
                            <circle cx="159" cy="14" r="8" fill="#1e40af" clip-path="url(#moonClip)"/>
                        </g>

                        <g class="h-cloud1">
                            <ellipse cx="40" cy="15" rx="17" ry="6.5" fill="rgba(255,255,255,.35)"/>
                            <ellipse cx="30" cy="18" rx="11" ry="5.5" fill="rgba(255,255,255,.35)"/>
                            <ellipse cx="52" cy="18" rx="12" ry="5" fill="rgba(255,255,255,.35)"/>
                        </g>
                        <g class="h-cloud2">
                            <ellipse cx="100" cy="9" rx="13" ry="5" fill="rgba(255,255,255,.25)"/>
                            <ellipse cx="90" cy="11" rx="9" ry="4" fill="rgba(255,255,255,.25)"/>
                            <ellipse cx="109" cy="11" rx="9" ry="4" fill="rgba(255,255,255,.25)"/>
                        </g>

                        <g class="h-sun">
                            <circle cx="148" cy="19" r="19" fill="url(#sunGlow)"/>
                            <g class="h-sun-rays" style="transform-origin:148px 19px">
                                <line x1="148" y1="4" x2="148" y2="1" stroke="#fbbf24" stroke-width="1.8" stroke-linecap="round"/>
                                <line x1="148" y1="34" x2="148" y2="37" stroke="#fbbf24" stroke-width="1.8" stroke-linecap="round"/>
                                <line x1="133" y1="19" x2="130" y2="19" stroke="#fbbf24" stroke-width="1.8" stroke-linecap="round"/>
                                <line x1="163" y1="19" x2="166" y2="19" stroke="#fbbf24" stroke-width="1.8" stroke-linecap="round"/>
                                <line x1="138" y1="9" x2="136" y2="7" stroke="#fbbf24" stroke-width="1.4" stroke-linecap="round" opacity=".75"/>
                                <line x1="158" y1="29" x2="160" y2="31" stroke="#fbbf24" stroke-width="1.4" stroke-linecap="round" opacity=".75"/>
                                <line x1="158" y1="9" x2="160" y2="7" stroke="#fbbf24" stroke-width="1.4" stroke-linecap="round" opacity=".75"/>
                                <line x1="138" y1="29" x2="136" y2="31" stroke="#fbbf24" stroke-width="1.4" stroke-linecap="round" opacity=".75"/>
                            </g>
                            <circle cx="148" cy="19" r="10" fill="#fbbf24"/>
                        </g>

                        <rect class="h-ground" x="0" y="95" width="170" height="1.5" rx=".75" fill="rgba(255,255,255,.2)"/>

                        <g class="hb1">
                            <rect x="4" y="73" width="22" height="22" rx="1" fill="rgba(255,255,255,.13)" stroke="rgba(255,255,255,.22)" stroke-width=".7"/>
                        </g>
                        <g class="hr1"><polygon points="3,73 15,60 27,73" fill="rgba(255,255,255,.22)" stroke="rgba(255,255,255,.32)" stroke-width=".7"/></g>
                        <rect class="hw" x="6" y="76" width="6" height="5" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="14" y="76" width="6" height="5" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="6" y="84" width="6" height="5" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <g class="h-door"><rect x="11" y="87" width="7" height="8" rx="1" fill="rgba(255,255,255,.16)"/></g>

                        <g class="hb2">
                            <rect x="30" y="58" width="26" height="37" rx="1" fill="rgba(255,255,255,.15)" stroke="rgba(255,255,255,.25)" stroke-width=".7"/>
                        </g>
                        <g class="hr2"><polygon points="29,58 43,45 57,58" fill="rgba(255,255,255,.24)" stroke="rgba(255,255,255,.35)" stroke-width=".7"/></g>
                        <rect class="h-chimney" x="50" y="47" width="4" height="8" rx=".8" fill="rgba(255,255,255,.18)"/>
                        <ellipse class="h-puff1" cx="52" cy="46" rx="2.5" ry="1.8" fill="rgba(255,255,255,.25)"/>
                        <ellipse class="h-puff2" cx="53" cy="44" rx="2" ry="1.5" fill="rgba(255,255,255,.18)"/>
                        <ellipse class="h-puff3" cx="51" cy="43" rx="1.5" ry="1.2" fill="rgba(255,255,255,.12)"/>
                        <rect class="hw" x="33" y="62" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="43" y="62" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="33" y="71" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="43" y="71" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="33" y="80" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="43" y="80" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>

                        <g class="hb3">
                            <rect x="60" y="35" width="40" height="60" rx="1" fill="rgba(255,255,255,.2)" stroke="rgba(255,255,255,.35)" stroke-width=".8"/>
                            <line x1="80" y1="35" x2="80" y2="95" stroke="rgba(255,255,255,.1)" stroke-width=".5"/>
                        </g>
                        <g class="hr3"><polygon points="58,35 80,15 102,35" fill="rgba(255,255,255,.3)" stroke="rgba(255,255,255,.45)" stroke-width=".8"/></g>
                        <g class="h-crane-mast"><rect x="91" y="18" width="3" height="17" rx=".5" fill="rgba(255,200,50,.7)"/></g>
                        <g class="h-crane-arm" style="transform-origin:91px 18px">
                            <line x1="91" y1="18" x2="115" y2="14" stroke="rgba(255,200,50,.7)" stroke-width="1.5"/>
                            <line x1="115" y1="14" x2="115" y2="22" stroke="rgba(255,200,50,.5)" stroke-width="1"/>
                            <circle cx="115" cy="23" r="1.5" fill="rgba(255,200,50,.6)"/>
                        </g>
                        <rect class="hw" x="63" y="40" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="74" y="40" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="85" y="40" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="63" y="50" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="74" y="50" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="85" y="50" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="63" y="60" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="74" y="60" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="85" y="60" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="63" y="70" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="85" y="70" width="8" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <g class="h-door">
                            <rect x="72" y="82" width="16" height="13" rx="1.5" fill="rgba(255,255,255,.2)" stroke="rgba(255,255,255,.3)" stroke-width=".7"/>
                            <circle cx="86" cy="89" r="1.2" fill="rgba(255,255,255,.5)"/>
                        </g>

                        <g class="hb4">
                            <rect x="104" y="55" width="28" height="40" rx="1" fill="rgba(255,255,255,.14)" stroke="rgba(255,255,255,.23)" stroke-width=".7"/>
                        </g>
                        <g class="hr4"><polygon points="103,55 118,42 133,55" fill="rgba(255,255,255,.22)" stroke="rgba(255,255,255,.32)" stroke-width=".7"/></g>
                        <rect class="hw" x="107" y="59" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="117" y="59" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="107" y="68" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="117" y="68" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="107" y="77" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="117" y="77" width="7" height="6" rx=".8" fill="rgba(255,255,255,.05)"/>

                        <g class="hb5">
                            <rect x="136" y="70" width="22" height="25" rx="1" fill="rgba(255,255,255,.11)" stroke="rgba(255,255,255,.2)" stroke-width=".7"/>
                        </g>
                        <g class="hr5"><polygon points="135,70 147,58 159,70" fill="rgba(255,255,255,.18)" stroke="rgba(255,255,255,.28)" stroke-width=".7"/></g>
                        <rect class="hw" x="139" y="73" width="6" height="5" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="148" y="73" width="6" height="5" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="139" y="81" width="6" height="5" rx=".8" fill="rgba(255,255,255,.05)"/>
                        <rect class="hw" x="148" y="81" width="6" height="5" rx=".8" fill="rgba(255,255,255,.05)"/>

                        <g class="h-lamp">
                            <line x1="27" y1="82" x2="27" y2="95" stroke="rgba(255,255,255,.4)" stroke-width="1"/>
                            <line x1="27" y1="82" x2="33" y2="80" stroke="rgba(255,255,255,.4)" stroke-width="1"/>
                            <circle class="h-lamp-glow" cx="33" cy="79" r="8" fill="url(#lampGlow1)" opacity=".9"/>
                            <circle cx="33" cy="79" r="2" fill="rgba(255,220,80,.9)"/>
                        </g>
                        <g class="h-lamp">
                            <line x1="135" y1="82" x2="135" y2="95" stroke="rgba(255,255,255,.4)" stroke-width="1"/>
                            <line x1="135" y1="82" x2="129" y2="80" stroke="rgba(255,255,255,.4)" stroke-width="1"/>
                            <circle class="h-lamp-glow" cx="129" cy="79" r="8" fill="url(#lampGlow2)" opacity=".9"/>
                            <circle cx="129" cy="79" r="2" fill="rgba(255,220,80,.9)"/>
                        </g>

                        <g class="h-car">
                            <rect x="-28" y="100" width="22" height="7" rx="2" fill="rgba(255,255,255,.25)"/>
                            <rect x="-25" y="97" width="14" height="5" rx="1.5" fill="rgba(255,255,255,.18)"/>
                            <circle cx="-23" cy="107" r="2.2" fill="rgba(255,255,255,.4)"/>
                            <circle cx="-10" cy="107" r="2.2" fill="rgba(255,255,255,.4)"/>
                            <circle class="h-headlight" cx="-7" cy="103" r="1.2" fill="rgba(255,240,150,.9)"/>
                            <rect class="h-headlight" x="-28" y="102" width="1.5" height="2" rx=".5" fill="rgba(255,80,80,.7)"/>
                        </g>
                    </svg>
                </div>
            </div>

            <!-- Weather -->
            <div class="td-weather-card">
                <div class="td-weather-left">
                    <div class="td-weather-label"><i class="fas fa-cloud-sun" style="margin-right:5px;"></i>Current Weather</div>
                    <div class="td-weather-top">
                        <div class="td-weather-icon" id="wt-icon">🌤</div>
                        <div class="td-weather-temp" id="wt-temp">—<span class="td-weather-unit">°C</span></div>
                    </div>
                    <div class="td-weather-desc" id="wt-desc">Fetching weather…</div>
                    <div class="td-weather-loc" id="wt-loc"><i class="fas fa-map-marker-alt"></i> —</div>
                </div>
                <div class="td-weather-right">
                    <div class="td-weather-stat">
                        <div class="td-weather-stat-icon" style="background:var(--afims-heading-fill, #000E3E);color:#fff;"><i class="fas fa-tint"></i></div>
                        <div><div class="td-weather-stat-val"><span id="wt-hum">—</span>%</div><div class="td-weather-stat-lbl">Humidity</div></div>
                    </div>
                    <div class="td-weather-stat">
                        <div class="td-weather-stat-icon" style="background:var(--afims-heading-fill, #000E3E);color:#fff;"><i class="fas fa-wind"></i></div>
                        <div><div class="td-weather-stat-val"><span id="wt-wind">—</span> km/h</div><div class="td-weather-stat-lbl">Wind</div></div>
                    </div>
                    <div class="td-weather-stat">
                        <div class="td-weather-stat-icon" style="background:var(--afims-heading-fill, #000E3E);color:#fff;"><i class="fas fa-thermometer-half"></i></div>
                        <div><div class="td-weather-stat-val"><span id="wt-feels">—</span>°C</div><div class="td-weather-stat-lbl">Feels like</div></div>
                    </div>
                </div>
            </div>

        </div><!-- /.td-wt-right -->

        <!-- NHA Housing Slideshow (right) -->
        <div class="td-slideshow" id="nha-slideshow">
            <div class="td-slide-counter" id="ss-counter">1 / 6</div>
            <div class="td-slide-dots" id="ss-dots"></div>
            <div class="td-slide-arr td-slide-prev" id="ss-prev"><i class="fas fa-chevron-left"></i></div>
            <div class="td-slide-arr td-slide-next" id="ss-next"><i class="fas fa-chevron-right"></i></div>
            <div class="td-slide-progress" id="ss-progress"></div>
        </div>

    </div><!-- /.td-wt-row -->

    <!-- Everything below uses the same .td-* component system (td-kpi,
         td-card, td-li, td-bx) instead of plain Bootstrap cards, for full
         visual consistency with the TREASURY reference dashboard. Stays
         inside the same #tdash wrapper opened above it — one element, one id. -->

    <!-- KPI Row -->
    <div class="td-kpi-row mb-4" style="grid-template-columns:repeat(4,1fr);">
        <div class="td-kpi">
            <div class="td-kpi-icon" style="background:#0035A9;color:#fff;"><i class="fas fa-users"></i></div>
            <div class="td-kpi-val"><?php echo $totalUsers; ?></div>
            <div class="td-kpi-lbl">Total Users</div>
            <div class="td-kpi-sub"><i class="fas fa-arrow-up" style="color:#0035A9;"></i> <?php echo $totalUsersChange; ?>% from last month</div>
            <div class="td-kpi-ghost"><i class="fas fa-users"></i></div>
        </div>
        <div class="td-kpi">
            <div class="td-kpi-icon" style="background:#059669;color:#fff;"><i class="fas fa-user-check"></i></div>
            <div class="td-kpi-val"><?php echo $activeUsers; ?></div>
            <div class="td-kpi-lbl">Active Users</div>
            <div class="td-kpi-sub"><i class="fas fa-check-circle" style="color:#059669;"></i> <?php echo $activeUsersPercent; ?>% of total</div>
            <div class="td-kpi-ghost"><i class="fas fa-user-check"></i></div>
        </div>
        <div class="td-kpi">
            <div class="td-kpi-icon" style="background:#d97706;color:#fff;"><i class="fas fa-server"></i></div>
            <div class="td-kpi-val"><?php echo $totalSystems; ?></div>
            <div class="td-kpi-lbl">Total Systems</div>
            <div class="td-kpi-sub"><i class="fas fa-circle-info" style="color:#d97706;"></i> registered</div>
            <div class="td-kpi-ghost"><i class="fas fa-server"></i></div>
        </div>
        <div class="td-kpi">
            <div class="td-kpi-icon" style="background:#7c3aed;color:#fff;"><i class="fas fa-user-shield"></i></div>
            <div class="td-kpi-val"><?php echo $totalUserLevels; ?></div>
            <div class="td-kpi-lbl">User Levels</div>
            <div class="td-kpi-sub"><i class="fas fa-shield-halved" style="color:#7c3aed;"></i> roles configured</div>
            <div class="td-kpi-ghost"><i class="fas fa-user-shield"></i></div>
        </div>
    </div>

    <!-- Charts Row 1: User Growth + User Level Distribution -->
    <div class="td-chart-row mb-4">
        <div class="td-card">
            <div class="td-card-hdr">
                <span class="td-card-title">User Growth Over Time</span>
                <span class="td-card-pill">Last 6 Months</span>
                <button type="button" class="td-view-all" style="background:none;border:none;cursor:pointer;" onclick="exportChart('userGrowthChart', 'user-growth')">Export ⬇</button>
            </div>
            <div style="position:relative;height:300px;">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>
        <div class="td-card">
            <div class="td-card-hdr">
                <span class="td-card-title">User Level Distribution</span>
            </div>
            <div style="position:relative;height:300px;">
                <canvas id="userLevelChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2: System Health + Top System Usage -->
    <div class="td-chart-row mb-4">
        <div class="td-card">
            <div class="td-card-hdr">
                <span class="td-card-title">System Health</span>
                <a href="<?php echo $manageSystemsUrl; ?>" class="td-view-all">View all →</a>
            </div>
            <div>
                <?php if (empty($systemHealth)): ?>
                <div class="td-loading"><i class="fas fa-circle-info"></i> No systems registered yet.</div>
                <?php else: ?>
                <?php foreach ($systemHealth as $system): ?>
                <div class="td-li">
                    <div class="td-li-av" style="background:var(--afims-heading-fill, #000E3E);"><?php echo HtmlEncode(mb_strtoupper(mb_substr($system['system_name'], 0, 2))); ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="td-li-name"><?php echo $system['system_name']; ?></div>
                        <div class="td-li-sub"><?php echo $system['active_users']; ?> active user<?php echo $system['active_users'] == 1 ? '' : 's'; ?></div>
                    </div>
                    <span class="td-bx <?php echo $system['status'] === 'Active' ? 'bx-g' : 'bx-gray'; ?>"><?php echo $system['status']; ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="td-card">
            <div class="td-card-hdr">
                <span class="td-card-title">Top System Usage</span>
                <span class="td-card-pill">by Users</span>
            </div>
            <?php if (empty($topSystems)): ?>
            <div class="td-loading" style="height:220px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-circle-info"></i> No systems registered yet.</div>
            <?php else: ?>
            <div style="position:relative;height:220px;">
                <canvas id="topSystemsChart"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Row: Audit Log Timeline + Recent Registrations + Quick Actions -->
    <div class="td-bottom mb-4">

        <div class="td-card">
            <div class="td-card-hdr">
                <span class="td-card-title">Audit Log Timeline</span>
                <a href="<?php echo $viewAuditLogsUrl; ?>" class="td-view-all">View all →</a>
            </div>
            <div style="max-height:320px;overflow-y:auto;">
                <?php if (empty($auditLogTimeline)): ?>
                <div class="td-loading"><i class="fas fa-circle-info"></i> No recent activity logged yet.</div>
                <?php else: ?>
                <?php foreach ($auditLogTimeline as $log): ?>
                <div class="td-li">
                    <div class="td-li-av" style="background:<?php echo getActionColorHex($log['action_type']); ?>;"><i class="fas <?php echo getActionIcon($log['action_type']); ?>" style="font-size:.6rem;"></i></div>
                    <div style="flex:1;min-width:0;">
                        <div class="td-li-name"><?php echo $log['script']; ?> by <?php echo $log['user']; ?></div>
                        <div class="td-li-sub"><?php echo $log['action_type']; ?> on <?php echo $log['table']; ?> &middot; <?php echo $log['action_date']; ?></div>
                    </div>
                    <span class="td-bx bx-gray"><?php echo $log['action_count']; ?>×</span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="td-card">
            <div class="td-card-hdr">
                <span class="td-card-title">Recent User Registrations</span>
                <a href="<?php echo $createUserUrl; ?>" class="td-view-all">View all →</a>
            </div>
            <div>
                <?php if (empty($recentUsers)): ?>
                <div class="td-loading"><i class="fas fa-circle-info"></i> No users registered yet.</div>
                <?php else: ?>
                <?php
                $avColors = ['#000E3E'];
                foreach ($recentUsers as $i => $user):
                ?>
                <div class="td-li">
                    <div class="td-li-av" style="background:<?php echo $avColors[$i % count($avColors)]; ?>;"><?php echo HtmlEncode(mb_strtoupper(mb_substr($user['username'], 0, 1))); ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="td-li-name"><?php echo $user['username']; ?></div>
                        <div class="td-li-sub"><?php echo $user['email']; ?></div>
                    </div>
                    <div class="td-li-val" style="font-weight:400;font-size:.62rem;color:var(--fims-text-muted, #64748b);"><?php echo $user['date_created']; ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="td-card">
            <div class="td-card-hdr">
                <span class="td-card-title">Quick Actions</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <a href="<?php echo $createUserUrl; ?>" class="td-mod">
                    <div class="td-mod-dot" style="background:var(--afims-heading-fill, #000E3E);"></div>
                    <div class="td-mod-name">Create New User</div>
                    <i class="fas fa-chevron-right" style="margin-left:auto;font-size:.58rem;color:var(--afims-border, #E4ECF2);"></i>
                </a>
                <a href="<?php echo $manageUserLevelsUrl; ?>" class="td-mod">
                    <div class="td-mod-dot" style="background:var(--afims-heading-fill, #000E3E);"></div>
                    <div class="td-mod-name">Manage User Levels</div>
                    <i class="fas fa-chevron-right" style="margin-left:auto;font-size:.58rem;color:var(--afims-border, #E4ECF2);"></i>
                </a>
                <a href="<?php echo $manageSystemsUrl; ?>" class="td-mod">
                    <div class="td-mod-dot" style="background:var(--afims-heading-fill, #000E3E);"></div>
                    <div class="td-mod-name">Manage Systems</div>
                    <i class="fas fa-chevron-right" style="margin-left:auto;font-size:.58rem;color:var(--afims-border, #E4ECF2);"></i>
                </a>
                <a href="<?php echo $viewAuditLogsUrl; ?>" class="td-mod">
                    <div class="td-mod-dot" style="background:var(--afims-heading-fill, #000E3E);"></div>
                    <div class="td-mod-name">View Audit Logs</div>
                    <i class="fas fa-chevron-right" style="margin-left:auto;font-size:.58rem;color:var(--afims-border, #E4ECF2);"></i>
                </a>
            </div>
        </div>

    </div>

    </div><!-- /#tdash -->

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Charts read their colors from the AFIMS palette (_shared/css/custom.css) at draw time and are
// rebuilt on "changetheme" (fired by ew.setTheme), so light and dark mode always match the cards.
var dashCharts = [];
var dashData = {
    growth: <?php echo $userGrowthChartDataJson; ?>,
    levels: <?php echo $userLevelChartDataJson; ?>,
    systems: <?php echo $topSystemsChartDataJson; ?>
};

function dashVar(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
}
function dashAlpha(color, a) {
    return 'color-mix(in srgb, ' + color + ' ' + Math.round(a * 100) + '%, transparent)';
}

function buildDashCharts() {
    dashCharts.forEach(function (c) { c.destroy(); });
    dashCharts = [];

    var c1 = dashVar('--afims-chart-1', '#2A78D6'),
        c3 = dashVar('--afims-chart-3', '#1BAF7A'),
        palette = ['--afims-chart-1', '--afims-chart-2', '--afims-chart-3', '--afims-chart-4', '--afims-chart-5', '--afims-chart-6']
            .map(function (n) { return dashVar(n, '#2A78D6'); }),
        surface = dashVar('--afims-surface', '#FFFFFF'),
        subtle  = dashVar('--afims-surface-subtle', '#F4F7FA'),
        grid    = dashVar('--afims-chart-grid', '#EDF0F4'),
        axis    = dashVar('--afims-chart-axis', '#62657E'),
        text    = dashVar('--afims-text', '#1A1B21'),
        heading = dashVar('--afims-heading', '#000E3E'),
        muted   = dashVar('--afims-text-disabled', '#A9AAB8'),
        border  = dashVar('--afims-border', '#E4ECF2');

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = axis;

    var tooltipStyle = {
        backgroundColor: surface, titleColor: text, bodyColor: text,
        borderColor: border, borderWidth: 1,
        padding: 10, cornerRadius: 10, displayColors: true, boxPadding: 4,
        titleFont: { weight: '600' }
    };

    function fade(color) {
        return function (context) {
            var chart = context.chart;
            if (!chart.chartArea) return null;
            var g = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
            g.addColorStop(0, dashAlpha(color, .28));
            g.addColorStop(1, dashAlpha(color, 0));
            return g;
        };
    }

    // ── User growth (line) ──
    var growth = JSON.parse(JSON.stringify(dashData.growth));
    [c1, c3].forEach(function (col, i) {
        var ds = growth.datasets[i];
        if (!ds) return;
        ds.borderColor = col;
        ds.backgroundColor = fade(col);
        ds.pointBackgroundColor = surface;
        ds.pointBorderColor = col;
    });
    dashCharts.push(new Chart(document.getElementById('userGrowthChart').getContext('2d'), {
        type: 'line',
        data: growth,
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 700, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0, padding: 8, color: axis, font: { size: 11 } }, grid: { color: grid }, border: { display: false } },
                x: { ticks: { padding: 8, color: axis, font: { size: 11 } }, grid: { display: false }, border: { display: false } }
            },
            plugins: {
                legend: { position: 'top', align: 'end', labels: { color: text, usePointStyle: true, pointStyle: 'circle', boxWidth: 7, padding: 16, font: { weight: '600' } } },
                tooltip: tooltipStyle
            }
        }
    }));

    // ── User level distribution (doughnut with a center total) ──
    var levels = JSON.parse(JSON.stringify(dashData.levels));
    levels.datasets[0].backgroundColor = levels.datasets[0].data.map(function (_, i) { return palette[i % palette.length]; });
    levels.datasets[0].borderColor = surface;
    var centerTextPlugin = {
        id: 'centerText',
        afterDraw: function (chart) {
            if (chart.config.type !== 'doughnut') return;
            var total = chart.data.datasets[0].data.reduce(function (a, b) { return a + Number(b || 0); }, 0);
            var ctx = chart.ctx;
            var x = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
            var y = chart.chartArea.top + (chart.chartArea.bottom - chart.chartArea.top) / 2;
            ctx.save();
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.font = '700 1.4rem Inter, sans-serif'; ctx.fillStyle = heading;
            ctx.fillText(String(total), x, y - 8);
            ctx.font = '600 .62rem Inter, sans-serif'; ctx.fillStyle = muted;
            ctx.fillText('TOTAL', x, y + 12);
            ctx.restore();
        }
    };
    dashCharts.push(new Chart(document.getElementById('userLevelChart').getContext('2d'), {
        type: 'doughnut',
        data: levels,
        plugins: [centerTextPlugin],
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '72%', spacing: 3,
            animation: { animateScale: true, animateRotate: true, duration: 700, easing: 'easeOutQuart' },
            elements: { arc: { borderRadius: 6 } },
            plugins: {
                legend: { position: 'bottom', labels: { color: text, usePointStyle: true, pointStyle: 'circle', boxWidth: 7, padding: 12, font: { weight: '600' } } },
                tooltip: tooltipStyle
            }
        }
    }));

    // ── Top system usage (horizontal bars on a faint track) ──
    // Canvas isn't rendered when there's no data (empty-state message shows instead).
    var topSystemsCanvas = document.getElementById('topSystemsChart');
    if (topSystemsCanvas) {
        var systems = JSON.parse(JSON.stringify(dashData.systems));
        systems.datasets[0].backgroundColor = c1;
        var barTrackPlugin = {
            id: 'barTrack',
            beforeDatasetsDraw: function (chart) {
                var ctx = chart.ctx, xScale = chart.scales.x;
                ctx.save();
                ctx.fillStyle = subtle;
                chart.getDatasetMeta(0).data.forEach(function (bar) {
                    var half = bar.height / 2, left = xScale.left, right = xScale.right;
                    if (ctx.roundRect) {
                        ctx.beginPath();
                        ctx.roundRect(left, bar.y - half, right - left, bar.height, half);
                        ctx.fill();
                    } else {
                        ctx.fillRect(left, bar.y - half, right - left, bar.height);
                    }
                });
                ctx.restore();
            }
        };
        dashCharts.push(new Chart(topSystemsCanvas.getContext('2d'), {
            type: 'bar',
            data: systems,
            plugins: [barTrackPlugin],
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, color: axis, font: { size: 11 } }, grid: { display: false }, border: { display: false } },
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: text, font: { size: 11, weight: '600' } } }
                },
                plugins: { legend: { display: false }, tooltip: tooltipStyle }
            }
        }));
    }
}

document.addEventListener('DOMContentLoaded', buildDashCharts);
// Mode switched (navbar dropdown or Theme Settings panel): redraw with the new palette.
// Wait a tick so custom.css's [data-bs-theme] rules have applied before the colors are read.
document.addEventListener('changetheme', function () { setTimeout(buildDashCharts, 50); });

// Export a chart canvas as a PNG — same pattern as the TREASURY dashboard's exportChart().
function exportChart(canvasId, name) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = name + '-' + new Date().toISOString().split('T')[0] + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

document.querySelector('.content-header')?.style.setProperty('display', 'none', 'important');

/* ══════════════════════════════════════════════════════════════
   Clock + animated cityscape + weather + NHA slideshow — ported
   verbatim from the TREASURY reference project's treasury-dashboard.php.
   ══════════════════════════════════════════════════════════════ */
(function () {
    /* ── Clock ── */
    var DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    function tickClock() {
        var n = new Date();
        var raw = n.getHours(), ampm = raw >= 12 ? 'PM' : 'AM';
        var h = raw % 12 || 12, m = String(n.getMinutes()).padStart(2, '0'), s = String(n.getSeconds()).padStart(2, '0');
        document.getElementById('wt-time').innerHTML = String(h).padStart(2, '0') + ':' + m + '<span class="td-clock-ampm">' + ampm + '</span>';
        document.getElementById('wt-date').innerHTML = MONTHS[n.getMonth()] + ' ' + n.getDate() + ', ' + n.getFullYear() + '<br><span style="opacity:.5;font-size:.9em;">' + s + ' sec</span>';
        document.getElementById('wt-day').textContent = DAYS[n.getDay()];
    }
    tickClock();
    setInterval(tickClock, 1000);

    // Building animation loop — alternates day/night each cycle
    var _cityNight = false;
    (function houseLoop() {
        var wrap = document.querySelector('.td-house-wrap');
        if (!wrap) return;
        var svg = wrap.querySelector('svg');
        if (!svg) return;
        if (_cityNight) {
            wrap.classList.add('is-night');
        } else {
            wrap.classList.remove('is-night');
        }

        svg.style.transition = 'opacity .4s ease';
        svg.style.opacity = '1';

        var winTimer = setTimeout(function () {
            if (_cityNight) {
                wrap.querySelectorAll('.hw').forEach(function (r) { r.classList.add('hw-lit'); });
            }
        }, 3800);

        var fadeTimer = setTimeout(function () {
            svg.style.opacity = '0';
            setTimeout(function () {
                clearTimeout(winTimer);
                _cityNight = !_cityNight;
                var fresh = svg.cloneNode(true);
                fresh.style.opacity = '0';
                fresh.style.transition = 'opacity .4s ease';
                wrap.replaceChild(fresh, svg);
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        fresh.style.opacity = '1';
                        svg = fresh;
                        houseLoop();
                    });
                });
            }, 450);
        }, 7500);
    })();

    /* ── Weather (IP-based, no permission prompt) ── */
    var WMO_ICONS = { 0: '☀️', 1: '🌤', 2: '⛅', 3: '☁️', 45: '🌫', 48: '🌫', 51: '🌦', 53: '🌦', 55: '🌧', 61: '🌧', 63: '🌧', 65: '🌧', 71: '❄️', 73: '❄️', 75: '❄️', 80: '🌦', 81: '🌧', 82: '⛈', 95: '⛈', 96: '⛈', 99: '⛈' };
    var WMO_DESC = { 0: 'Clear sky', 1: 'Mainly clear', 2: 'Partly cloudy', 3: 'Overcast', 45: 'Foggy', 48: 'Icy fog', 51: 'Light drizzle', 53: 'Drizzle', 55: 'Heavy drizzle', 61: 'Light rain', 63: 'Rain', 65: 'Heavy rain', 71: 'Light snow', 73: 'Snow', 75: 'Heavy snow', 80: 'Showers', 81: 'Rain showers', 82: 'Violent showers', 95: 'Thunderstorm', 96: 'Thunderstorm', 99: 'Thunderstorm' };
    function loadWeather() {
        fetch('https://ipapi.co/json/')
            .then(function (r) { return r.json(); })
            .then(function (geo) {
                var lat = geo.latitude, lon = geo.longitude;
                var city = (geo.city || '') + (geo.region ? ', ' + geo.region : '');
                document.getElementById('wt-loc').innerHTML = '<i class="fas fa-map-marker-alt" style="color:var(--afims-text-disabled, #A9AAB8);"></i> ' + city;
                return fetch('https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lon
                    + '&current=temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m'
                    + '&wind_speed_unit=kmh&timezone=auto');
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var c = d.current, code = c.weather_code || 0;
                document.getElementById('wt-temp').innerHTML = Math.round(c.temperature_2m) + '<span class="td-weather-unit">°C</span>';
                document.getElementById('wt-icon').textContent = WMO_ICONS[code] || '🌤';
                document.getElementById('wt-desc').textContent = WMO_DESC[code] || 'Clear';
                document.getElementById('wt-hum').textContent = Math.round(c.relative_humidity_2m);
                document.getElementById('wt-wind').textContent = Math.round(c.wind_speed_10m);
                document.getElementById('wt-feels').textContent = Math.round(c.apparent_temperature);
            })
            .catch(function () { document.getElementById('wt-desc').textContent = 'Weather unavailable'; });
    }
    loadWeather();

    /* ── NHA Housing Slideshow ── */
    (function () {
        var BASE = 'https://upload.wikimedia.org/wikipedia/commons/thumb/';
        var SLIDES = [
            { title: 'National Housing Authority', loc: 'Elliptical Road, Diliman, Quezon City', img: '5/59/National_Housing_Authority_building_%28Quezon_City%29_01.jpg/1280px-National_Housing_Authority_building_%28Quezon_City%29_01.jpg' },
            { title: 'National Housing Authority', loc: 'Elliptical Road, Diliman, Quezon City', img: '5/56/National_Housing_Authority_building_%28Quezon_City%29_03.jpg/1280px-National_Housing_Authority_building_%28Quezon_City%29_03.jpg' },
            { title: 'National Housing Authority', loc: 'Elliptical Road, Diliman, Quezon City', img: 'b/b8/National_Housing_Authority_building_%28Quezon_City%29_05.jpg/1280px-National_Housing_Authority_building_%28Quezon_City%29_05.jpg' },
            { title: 'National Housing Authority', loc: 'Elliptical Road, Diliman, Quezon City', img: '4/45/National_Housing_Authority_building_%28Quezon_City%29_08.jpg/1280px-National_Housing_Authority_building_%28Quezon_City%29_08.jpg' },
            { title: 'National Housing Authority', loc: 'Elliptical Road, Diliman, Quezon City', img: 'e/e6/National_Housing_Authority_building_%28Quezon_City%29_10.jpg/1280px-National_Housing_Authority_building_%28Quezon_City%29_10.jpg' },
            { title: 'National Housing Authority', loc: 'East Avenue, Quezon City', img: '0/0c/NHA_Building_%28East_Avenue%2C_Quezon_City%3B_03-13-2021%29.jpg/1280px-NHA_Building_%28East_Avenue%2C_Quezon_City%3B_03-13-2021%29.jpg' },
        ];

        var wrap = document.getElementById('nha-slideshow');
        if (!wrap) return;
        var dotsEl = document.getElementById('ss-dots');
        var progEl = document.getElementById('ss-progress');
        var ctrEl = document.getElementById('ss-counter');
        var cur = 0, timer, progTimer, DURATION = 5000;

        var slideEls = SLIDES.map(function (s, i) {
            var el = document.createElement('div');
            el.className = 'td-slide' + (i === 0 ? ' ss-active' : '');
            el.innerHTML =
                '<img class="td-slide-img" src="' + BASE + s.img + '" alt="' + s.title + '">'
                + '<div class="td-slide-scrim"></div>'
                + '<div class="td-slide-caption">'
                + '<div class="td-slide-eyebrow"><i class="fas fa-landmark" style="margin-right:5px;"></i>NHA · Main Office</div>'
                + '<div class="td-slide-title">' + s.title + '</div>'
                + '<div class="td-slide-meta">'
                + '<span class="td-slide-chip"><i class="fas fa-map-marker-alt"></i> ' + s.loc + '</span>'
                + '</div>'
                + '</div>';
            wrap.insertBefore(el, dotsEl);
            return el;
        });

        SLIDES.forEach(function (_, i) {
            var d = document.createElement('div');
            d.className = 'td-slide-dot' + (i === 0 ? ' ss-active' : '');
            d.addEventListener('click', function () { goTo(i); });
            dotsEl.appendChild(d);
        });

        function updateCounter() { if (ctrEl) ctrEl.textContent = (cur + 1) + ' / ' + SLIDES.length; }

        function goTo(n) {
            slideEls[cur].classList.remove('ss-active');
            dotsEl.children[cur].classList.remove('ss-active');
            cur = (n + SLIDES.length) % SLIDES.length;
            slideEls[cur].classList.add('ss-active');
            dotsEl.children[cur].classList.add('ss-active');
            updateCounter();
            startProgress();
        }
        function next() { goTo(cur + 1); }
        function prev() { goTo(cur - 1); }

        function startProgress() {
            clearTimeout(timer); clearTimeout(progTimer);
            if (progEl) { progEl.style.transition = 'none'; progEl.style.width = '0%'; }
            progTimer = setTimeout(function () {
                if (progEl) { progEl.style.transition = 'width ' + DURATION + 'ms linear'; progEl.style.width = '100%'; }
            }, 30);
            timer = setTimeout(next, DURATION);
        }

        document.getElementById('ss-next').addEventListener('click', next);
        document.getElementById('ss-prev').addEventListener('click', prev);
        updateCounter();
        startProgress();
    })();
})();
</script>
