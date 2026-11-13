# Installs a shortcut in the current user's Startup folder so the FIMS
# Check Print Agent launches automatically (hidden, no console window)
# every time you log in — no more remembering to double-click the .bat.
#
# Run this once, manually:
#   Right-click install-autostart.ps1 -> Run with PowerShell
# (or open PowerShell in this folder and run: .\install-autostart.ps1)
#
# To undo later, just delete "FIMS Check Print Agent.lnk" from:
#   shell:startup

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$target    = Join-Path $scriptDir 'start-print-agent-hidden.vbs'

if (-not (Test-Path $target)) {
    Write-Host "ERROR: $target not found. Keep this script in the print-agent folder." -ForegroundColor Red
    exit 1
}

$startup = [Environment]::GetFolderPath('Startup')
$lnkPath = Join-Path $startup 'FIMS Check Print Agent.lnk'

$wsh = New-Object -ComObject WScript.Shell
$shortcut = $wsh.CreateShortcut($lnkPath)
$shortcut.TargetPath       = 'wscript.exe'
$shortcut.Arguments        = '"' + $target + '"'
$shortcut.WorkingDirectory = $scriptDir
$shortcut.Description      = 'Auto-starts the FIMS Check Print Agent (hidden) at logon'
$shortcut.Save()

Write-Host "Installed: $lnkPath" -ForegroundColor Green
Write-Host "The print agent will now start automatically (hidden) at your next login."
Write-Host "To start it right now without logging out, double-click start-print-agent.bat once."
