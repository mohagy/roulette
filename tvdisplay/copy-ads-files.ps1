# PowerShell script to copy video ad files to the tvdisplay/adds directory
# This script should be run from the main project directory

# Define source and destination paths
$sourceDir = "C:\xampp1\htdocs\tvdisplay\adds"
$destDir = "C:\xampp1\htdocs\slipp\tvdisplay\adds"

# Create destination directory if it doesn't exist
if (-not (Test-Path $destDir)) {
    Write-Host "Creating destination directory: $destDir"
    New-Item -ItemType Directory -Path $destDir -Force | Out-Null
}

# Check if source directory exists
if (-not (Test-Path $sourceDir)) {
    Write-Host "Error: Source directory $sourceDir not found." -ForegroundColor Red
    exit 1
}

# Copy the video files
Write-Host "Copying video files from $sourceDir to $destDir" -ForegroundColor Green
Copy-Item "$sourceDir\*.mp4" -Destination $destDir -Force

# Verify the files were copied
$files = Get-ChildItem -Path $destDir -Filter "*.mp4"
if ($files.Count -gt 0) {
    Write-Host "`nSuccessfully copied $($files.Count) video files:" -ForegroundColor Green
    foreach ($file in $files) {
        Write-Host " - $($file.Name) ($([Math]::Round($file.Length / 1MB, 2)) MB)"
    }
    
    Write-Host "`nYou can now open the roulette TV display and the video ads player will appear in the left side of the screen." -ForegroundColor Cyan
} else {
    Write-Host "`nWarning: No video files were copied. Make sure there are .mp4 files in $sourceDir" -ForegroundColor Yellow
} 