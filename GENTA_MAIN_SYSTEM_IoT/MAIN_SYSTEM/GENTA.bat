@echo off

rem Start GENTA7.py using the project's virtual environment (newenv)
echo Starting GENTA7.py using `newenv\Scripts\python.exe`...
rem Determine repository root (parent of this MAIN_SYSTEM folder) and normalize to an absolute path
set SCRIPT_DIR=%~dp0
for %%I in ("%SCRIPT_DIR%..") do set "REPO_ROOT=%%~fI"

rem Normalize paths (use quoted assignment to preserve spaces)
set "VENV_PY=%REPO_ROOT%\newenv\Scripts\python.exe"
set "TARGET_PY=%REPO_ROOT%\GENTA7.py"

if exist "%VENV_PY%" (
	echo Using venv python: "%VENV_PY%"
	rem Start inside a new cmd window and keep it open so tracebacks remain visible
	start "GENTA7" cmd /k ""%VENV_PY%" "%TARGET_PY%" & echo. & echo --- PROCESS EXITED (see above) --- & pause"
) else (
	echo WARNING: venv python not found at "%VENV_PY%". Falling back to system python.
	start "GENTA7" python "%TARGET_PY%"
)

rem Keep the window open so errors are visible
pause
