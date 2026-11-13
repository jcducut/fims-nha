param(
    [Parameter(Mandatory = $true)][string]$JobFile
)

$ErrorActionPreference = 'Stop'

try {
    $job = Get-Content -Raw -Path $JobFile | ConvertFrom-Json
    Add-Type -AssemblyName System.Drawing

    $images = @($job.images)
    if ($images.Count -eq 0) { throw "No images to print." }

    $doc = New-Object System.Drawing.Printing.PrintDocument
    $doc.PrinterSettings.PrinterName = $job.printer
    if (-not $doc.PrinterSettings.IsValid) {
        throw "Printer '$($job.printer)' was not found or is not valid on this machine."
    }
    $doc.PrinterSettings.Copies = [int16]$job.copies
    $doc.DefaultPageSettings.Color = ($job.colorMode -ne 'grayscale')
    $doc.DefaultPageSettings.Margins = New-Object System.Drawing.Printing.Margins(0, 0, 0, 0)

    # Many printer drivers silently reject/substitute an arbitrary custom
    # PaperSize object that isn't one of their own advertised presets (e.g.
    # a Brother inkjet driver on this exact setup refuses anything under
    # 5in tall) — GDI+ still draws assuming the SIZE WE ASKED FOR though,
    # so the driver's silent substitution shows up as clipped/cut print
    # output, not an error. To work reliably across every printer: pick
    # the smallest size the driver actually advertises that's big enough
    # to contain the check (exact match if one exists), and draw the check
    # at its true physical size anchored top-left — never stretched to
    # fill the page — so any leftover paper is just blank margin, never a
    # cut-off check.
    $wantW = [int]([double]$job.widthIn  * 100)
    $wantH = [int]([double]$job.heightIn * 100)
    $exact = $doc.PrinterSettings.PaperSizes | Where-Object { $_.Width -eq $wantW -and $_.Height -eq $wantH } | Select-Object -First 1
    if ($exact) {
        $doc.DefaultPageSettings.PaperSize = $exact
    } else {
        $fits = $doc.PrinterSettings.PaperSizes | Where-Object { $_.Width -ge $wantW -and $_.Height -ge $wantH } |
                Sort-Object @{Expression={$_.Width * $_.Height}} | Select-Object -First 1
        $doc.DefaultPageSettings.PaperSize = if ($fits) { $fits } else {
            # Nothing big enough is advertised — fall back to the largest
            # available rather than something guaranteed to clip further.
            $doc.PrinterSettings.PaperSizes | Sort-Object @{Expression={$_.Width * $_.Height}} -Descending | Select-Object -First 1
        }
    }

    $script:idx = 0
    $doc.add_PrintPage({
        param($sender, $e)
        $imgPath = $images[$script:idx]
        $img = [System.Drawing.Image]::FromFile($imgPath)
        $e.Graphics.DrawImage($img, 0, 0, $wantW, $wantH)
        $img.Dispose()
        $script:idx++
        $e.HasMorePages = ($script:idx -lt $images.Count)
    })

    $doc.Print()
    Write-Output "OK"
}
catch {
    Write-Error $_.Exception.Message
    exit 1
}
