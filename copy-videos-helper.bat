@echo off
echo ========================================
echo   VIDEO UPLOAD HELPER
echo ========================================
echo.
echo This tool will help you copy your recorded videos
echo to the correct location for the tutorial.
echo.

REM Check if media directory exists
if not exist "media\videos" (
    echo ❌ Media directory not found!
    echo Please run setup-media-structure.bat first.
    echo.
    pause
    exit /b
)

echo 📁 Current video directory status:
echo.
if exist "media\videos\01-placing-straight-up-bet.mp4" (
    echo ✅ 01-placing-straight-up-bet.mp4 - Found
) else (
    echo ❌ 01-placing-straight-up-bet.mp4 - Missing
)

if exist "media\videos\02-complete-betting-transaction.mp4" (
    echo ✅ 02-complete-betting-transaction.mp4 - Found
) else (
    echo ❌ 02-complete-betting-transaction.mp4 - Missing
)

echo.
echo ========================================
echo   UPLOAD INSTRUCTIONS
echo ========================================
echo.
echo 1. LOCATE YOUR RECORDED VIDEOS:
echo    - Find your MP4 video files on your computer
echo    - Note their current names and locations
echo.
echo 2. COPY TO TUTORIAL DIRECTORY:
echo    - Copy your first video to: media\videos\
echo    - Rename it to: 01-placing-straight-up-bet.mp4
echo    - Copy your second video to: media\videos\
echo    - Rename it to: 02-complete-betting-transaction.mp4
echo.
echo 3. VERIFY UPLOAD:
echo    - Run this script again to check status
echo    - Or open validate-media-files.html
echo.
echo ========================================
echo   QUICK ACTIONS
echo ========================================
echo.
echo [1] Open videos folder in Explorer
echo [2] Open validation tool
echo [3] Open tutorial to test videos
echo [4] Show detailed upload guide
echo [5] Exit
echo.
set /p choice="Enter your choice (1-5): "

if "%choice%"=="1" (
    echo Opening videos folder...
    explorer "media\videos"
    goto :menu
)

if "%choice%"=="2" (
    echo Opening validation tool...
    start validate-media-files.html
    goto :menu
)

if "%choice%"=="3" (
    echo Opening tutorial...
    start cashier-roulette-tutorial.html
    goto :menu
)

if "%choice%"=="4" (
    echo Opening upload guide...
    start VIDEO_UPLOAD_GUIDE.md
    goto :menu
)

if "%choice%"=="5" (
    echo Goodbye!
    exit /b
)

echo Invalid choice. Please try again.
echo.
:menu
echo.
echo Press any key to return to menu...
pause > nul
goto :start

:start
cls
goto :begin

:begin
echo ========================================
echo   VIDEO UPLOAD HELPER
echo ========================================
echo.
echo 📁 Current video directory status:
echo.
if exist "media\videos\01-placing-straight-up-bet.mp4" (
    echo ✅ 01-placing-straight-up-bet.mp4 - Found
) else (
    echo ❌ 01-placing-straight-up-bet.mp4 - Missing
)

if exist "media\videos\02-complete-betting-transaction.mp4" (
    echo ✅ 02-complete-betting-transaction.mp4 - Found
) else (
    echo ❌ 02-complete-betting-transaction.mp4 - Missing
)

echo.
echo [1] Open videos folder in Explorer
echo [2] Open validation tool  
echo [3] Open tutorial to test videos
echo [4] Show detailed upload guide
echo [5] Exit
echo.
set /p choice="Enter your choice (1-5): "

if "%choice%"=="1" (
    echo Opening videos folder...
    explorer "media\videos"
    goto :menu
)

if "%choice%"=="2" (
    echo Opening validation tool...
    start validate-media-files.html
    goto :menu
)

if "%choice%"=="3" (
    echo Opening tutorial...
    start cashier-roulette-tutorial.html
    goto :menu
)

if "%choice%"=="4" (
    echo Opening upload guide...
    start VIDEO_UPLOAD_GUIDE.md
    goto :menu
)

if "%choice%"=="5" (
    echo Goodbye!
    exit /b
)

echo Invalid choice. Please try again.
goto :begin
