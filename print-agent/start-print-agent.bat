@echo off
cd /d "%~dp0"
echo Starting FIMS Check Print Agent on http://127.0.0.1:9123 ...
echo Keep this window open while printing checks. Close it to stop the agent.
php -S 127.0.0.1:9123 print-agent.php
pause
