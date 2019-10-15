@echo off
chcp 65001
cls
"%~dp0\client\php\php.exe" "%~dp0\client\command.php" app:WebTool %*
pause
