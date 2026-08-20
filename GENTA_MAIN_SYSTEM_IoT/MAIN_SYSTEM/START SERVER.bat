@echo off
REM START SERVER - Start Flask and ngrok only (no CakePHP)

REM Resolve repository root (parent of this MAIN_SYSTEM folder)
set SCRIPT_DIR=%~dp0
for %%I in ("%SCRIPT_DIR%..") do set "REPO_ROOT=%%~fI"

REM --- Start Flask ---
echo Starting Flask server (activating newenv)...
set "VENV_PY=%REPO_ROOT%\newenv\Scripts\python.exe"
if exist "%VENV_PY%" (
    start "GENTA Flask Server" cmd /k ""%VENV_PY%" "%REPO_ROOT%\GENTA_Flask.py""
) else (
    start "GENTA Flask Server" cmd /k "cd /d \"%REPO_ROOT%\" && call \"%REPO_ROOT%\newenv\Scripts\activate.bat\" && python \"%REPO_ROOT%\GENTA_Flask.py\""
)

echo Waiting for Flask to initialize...
timeout /t 3 /nobreak >nul

REM --- Start ngrok ---
echo Starting ngrok (default public URL)...
set FLASK_HOST=127.0.0.1
set FLASK_PORT=5000
if exist "%REPO_ROOT%\ngrok-v3-stable-windows-amd64\ngrok.exe" (
    start "" cmd /k "cd /d \"%REPO_ROOT%\ngrok-v3-stable-windows-amd64\" && .\ngrok.exe http %FLASK_HOST%:%FLASK_PORT%"
) else (
    start "" cmd /k "ngrok http %FLASK_HOST%:%FLASK_PORT%"
)

echo.
echo ====================================
echo Flask server started on port 5000
echo ngrok tunnel started
echo ====================================
echo.
echo Done.
pause