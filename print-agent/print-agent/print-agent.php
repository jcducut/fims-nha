<?php
/**
 * FIMS Check Print Agent — tiny local HTTP server for silent check printing.
 *
 * Why this exists: browsers refuse to talk to a printer directly (no dialog
 * bypass is possible from page JS), and CSS @page size hints are only
 * honored by printers whose driver already has that exact size registered
 * — so check-preparation.php sends print jobs here instead. Plain PHP +
 * Windows-native tools only, no third-party print software:
 *   - Renders each check to a PNG via a locally-installed Chrome/Edge in
 *     headless screenshot mode.
 *   - Sends the PNG(s) straight to the named printer via print-image.ps1,
 *     which uses .NET's System.Drawing.Printing.PrintDocument (built into
 *     Windows) with an EXPLICIT custom PaperSize set in code — this is a
 *     stronger request than a browser's @page hint, so it works on more
 *     printers even without a pre-registered custom form.
 *
 * Auto-started at Windows logon via a Startup-folder shortcut — see
 * start-print-agent.vbs. Can also be run manually via start-print-agent.bat.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function findBrowserExe(): ?string
{
    $candidates = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    ];
    foreach ($candidates as $c) {
        if (is_file($c)) return $c;
    }
    return null;
}

function runPowerShellInline(string $script): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'fimsps_') . '.ps1';
    file_put_contents($tmp, $script);
    exec('powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    @unlink($tmp);
    return [$code, implode("\n", $out)];
}

// ─── GET /ping — health check, used by check-preparation.php on page load ──
if ($uri === '/ping' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'message' => 'Print agent is running.']);
    exit;
}

// ─── GET /printers — list installed printers with availability ────────────
if ($uri === '/printers' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Win32_Printer.WorkOffline is just a local Windows setting — it stays
    // "false" even when a printer is unplugged/powered off/unreachable
    // (confirmed on this exact setup: two Brother printer objects for the
    // same physical device both reported WorkOffline=false, even though
    // one of them is genuinely broken). Get-Printer's PrinterStatus is
    // spooler-tracked against real communication with the device, so it
    // actually distinguishes them ("Normal" vs "Error").
    [$code, $out] = runPowerShellInline(
        'Get-Printer | ForEach-Object { "$($_.Name)|$($_.PrinterStatus)" }'
    );
    if ($code !== 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $out]);
        exit;
    }
    $data = [];
    foreach (array_filter(array_map('trim', explode("\n", $out))) as $row) {
        $pos = strrpos($row, '|');
        if ($pos === false) continue;
        $status = substr($row, $pos + 1);
        $data[] = [
            'name'      => substr($row, 0, $pos),
            'available' => $status === 'Normal',
            'status'    => $status,
        ];
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// ─── POST /print — render + print one or more checks ───────────────────────
if ($uri === '/print' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true) ?: [];
    $printer   = trim($body['printer'] ?? '');
    $copies    = max(1, (int)($body['copies'] ?? 1));
    $widthIn   = (float)($body['widthIn']  ?? 8.1);
    $heightIn  = (float)($body['heightIn'] ?? 3);
    $colorMode = ($body['colorMode'] ?? 'color') === 'grayscale' ? 'grayscale' : 'color';
    $htmls     = $body['htmls'] ?? [];

    if ($printer === '' || !is_array($htmls) || !count($htmls)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'printer and htmls[] are required.']);
        exit;
    }

    $chrome = findBrowserExe();
    if (!$chrome) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Chrome or Edge was not found on this machine (checked the usual install paths) — one of them is required to render checks before printing.']);
        exit;
    }

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fims_print_' . uniqid();
    mkdir($dir, 0755, true);
    // 96 CSS px/in reference size, rendered at 3x scale (~288 DPI) for print quality.
    $pxW = (int)round($widthIn * 96);
    $pxH = (int)round($heightIn * 96);

    try {
        $images = [];
        foreach (array_values($htmls) as $i => $html) {
            $htmlPath = "$dir/page_$i.html";
            file_put_contents($htmlPath, $html);
            $pngPath = "$dir/page_$i.png";
            $cmd = escapeshellarg($chrome)
                 . ' --headless=new --disable-gpu --hide-scrollbars'
                 . " --window-size=$pxW,$pxH --force-device-scale-factor=3"
                 . ' --screenshot=' . escapeshellarg($pngPath) . ' '
                 . escapeshellarg('file:///' . str_replace('\\', '/', $htmlPath));
            exec($cmd . ' 2>&1', $out, $code);
            if ($code !== 0 || !is_file($pngPath)) {
                throw new \RuntimeException('Failed to render check #' . ($i + 1) . ': ' . implode("\n", $out));
            }
            $images[] = $pngPath;
        }

        $jobFile = "$dir/job.json";
        file_put_contents($jobFile, json_encode([
            'printer'   => $printer,
            'copies'    => $copies,
            'widthIn'   => $widthIn,
            'heightIn'  => $heightIn,
            'colorMode' => $colorMode,
            'images'    => $images,
        ]));

        $ps1 = __DIR__ . DIRECTORY_SEPARATOR . 'print-image.ps1';
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($ps1)
             . ' -JobFile ' . escapeshellarg($jobFile) . ' 2>&1';
        exec($cmd, $out2, $code2);
        if ($code2 !== 0) {
            throw new \RuntimeException('Printing failed: ' . implode("\n", $out2));
        }

        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } finally {
        foreach (glob("$dir/*") ?: [] as $f) @unlink($f);
        @rmdir($dir);
    }
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Not found. Endpoints: GET /ping, GET /printers, POST /print']);
