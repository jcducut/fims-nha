' Launches the FIMS Check Print Agent with no visible console window.
' Used by the Windows Startup shortcut so the agent is already running
' by the time anyone opens check-preparation.php — see the copy of this
' file (or a shortcut to it) in:
'   %AppData%\Microsoft\Windows\Start Menu\Programs\Startup
Set fso = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")
scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)
shell.CurrentDirectory = scriptDir
shell.Run "php -S 127.0.0.1:9123 print-agent.php", 0, False
