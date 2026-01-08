@echo off
echo ============================================================
echo 🎯 ROULETTE HEADLESS TV DISPLAY STARTER
echo ============================================================
echo.
echo Starting Roulette Headless TV Display...
echo This will run the TV display continuously without browser dependency.
echo.
echo ✅ Benefits:
echo    - No more idle tab issues
echo    - No more draw number skipping  
echo    - 24/7 continuous operation
echo    - Automatic error recovery
echo.
echo Press Ctrl+C to stop the headless display
echo ============================================================
echo.

cd /d "C:\xampp1\htdocs\slipp"
python headless_tv_display.py

echo.
echo ============================================================
echo Headless TV Display has stopped.
echo ============================================================
pause
