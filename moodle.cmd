@echo off
setlocal
set PYTHONUTF8=1
where py >nul 2>&1
if not errorlevel 1 goto run_py
where python >nul 2>&1
if errorlevel 1 goto missing_python
python "%~dp0scripts\manage.py" %*
exit /b %errorlevel%
:run_py
py -3 "%~dp0scripts\manage.py" %*
exit /b %errorlevel%
:missing_python
echo Python not found. Install Python 3.10 or later, then reopen PowerShell.
exit /b 1
