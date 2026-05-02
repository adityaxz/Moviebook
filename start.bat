@echo off
set PHP_EXE=C:\Users\adity\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe
set PHP_INI=C:\Users\adity\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.ini
echo Starting MovieBook at http://localhost:8000
echo Press Ctrl+C to stop.
"%PHP_EXE%" -c "%PHP_INI%" -S localhost:8000
