# Install Plugin Update Checker Library
# This script downloads and installs the Plugin Update Checker library

$pluginDir = $PSScriptRoot
$buildDir = "$pluginDir\build"
$pucDir = "$pluginDir\includes\plugin-update-checker"

# Create directories if they don't exist
if (!(Test-Path $buildDir)) { New-Item -ItemType Directory -Path $buildDir | Out-Null }
if (!(Test-Path "$pluginDir\includes")) { New-Item -ItemType Directory -Path "$pluginDir\includes" | Out-Null }

# Download Plugin Update Checker
Write-Host "Downloading Plugin Update Checker..." -ForegroundColor Yellow
$pucZipUrl = "https://github.com/YahnisElsts/plugin-update-checker/archive/refs/heads/master.zip"
$pucZipFile = "$buildDir\puc.zip"

# Download the zip file
Invoke-WebRequest -Uri $pucZipUrl -OutFile $pucZipFile

# Extract to temp directory
$tempExtractDir = "$buildDir\puc-temp"
if (Test-Path $tempExtractDir) { Remove-Item -Path $tempExtractDir -Recurse -Force }
New-Item -ItemType Directory -Path $tempExtractDir | Out-Null

Expand-Archive -Path $pucZipFile -DestinationPath $tempExtractDir

# Make sure the target directory exists
if (Test-Path $pucDir) { 
    Write-Host "Removing existing PUC directory..." -ForegroundColor Yellow
    Remove-Item -Path $pucDir -Recurse -Force 
}
New-Item -ItemType Directory -Path $pucDir -Force | Out-Null

# Copy files to plugin directory
$extractedDir = "$tempExtractDir\plugin-update-checker-master"
if (Test-Path $extractedDir) {
    Copy-Item -Path "$extractedDir\*" -Destination "$pucDir\" -Recurse -Force
    Write-Host "Plugin Update Checker files copied to: $pucDir" -ForegroundColor Green
} else {
    Write-Host "ERROR: Extracted directory not found at: $extractedDir" -ForegroundColor Red
    exit 1
}

# List files to verify
Write-Host "Verifying Plugin Update Checker files:" -ForegroundColor Yellow
if (Test-Path "$pucDir\plugin-update-checker.php") {
    Write-Host "  Main file found: plugin-update-checker.php" -ForegroundColor Green
} else {
    Write-Host "  ERROR: Main file NOT found: plugin-update-checker.php" -ForegroundColor Red
    exit 1
}

if (Test-Path "$pucDir\Puc") {
    Write-Host "  Puc directory found" -ForegroundColor Green
} else {
    Write-Host "  ERROR: Puc directory NOT found" -ForegroundColor Red
    exit 1
}

# Clean up
Write-Host "Cleaning up temporary files..." -ForegroundColor Yellow
Remove-Item -Path $pucZipFile -Force
Remove-Item -Path $tempExtractDir -Recurse -Force

Write-Host "Plugin Update Checker installation completed successfully!" -ForegroundColor Green
Write-Host "You can now build and deploy your plugin." -ForegroundColor Green
