@echo off
echo Installing Automatic Printing System...
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    echo Please install Python 3.7+ from https://python.org
    pause
    exit /b 1
)

echo Python found. Installing dependencies...
pip install -r requirements.txt

if errorlevel 1 (
    echo ERROR: Failed to install Python dependencies
    pause
    exit /b 1
)

echo.
echo Testing print system...
python api/print_slip.py "{\"slip_number\":\"TEST-001\",\"date\":\"2025-01-01 12:00:00\",\"draw_number\":\"999\",\"total_stake\":\"100.00\",\"potential_win\":\"3600.00\",\"bets\":[{\"type\":\"straight\",\"description\":\"Test Bet\",\"amount\":\"100.00\",\"odds\":\"35:1\",\"potential_return\":\"3600.00\"}]}"

echo.
echo Installation complete!
echo.
echo To use automatic printing:
echo 1. Include js/automatic_printing.js in your HTML pages
echo 2. Add data-slip-id attribute to your print buttons
echo 3. The system will automatically print without browser dialogs
echo.
pause
