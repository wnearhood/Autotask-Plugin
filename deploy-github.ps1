# Autotask Time Entry GitHub Deployment Script
# This script uses the New-GitHubRelease module to deploy to GitHub

param (
    [Parameter(Mandatory=$true)]
    [string]$Version,
    
    [Parameter(Mandatory=$false)]
    [string]$GitHubToken = $null,
    
    [Parameter(Mandatory=$false)]
    [string]$GitHubUsername = $null,
    
    [Parameter(Mandatory=$false)]
    [string]$GitHubRepo = $null
)

# Configuration
$pluginSlug = "autotask-time-entry"
$pluginDir = $PSScriptRoot
$zipFileName = "$pluginSlug.zip"  # No version in zip filename for WP updates
$versionedZipFileName = "$pluginSlug-$Version.zip" # Versioned zip for historical reference
$buildDir = "$pluginDir\build"
$distDir = "$pluginDir\dist"

# Display header
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "  Autotask Time Entry Deployment - Version $Version  " -ForegroundColor Cyan
Write-Host "====================================================" -ForegroundColor Cyan

# Ensure build and dist directories exist
Write-Host "`nCreating build and dist directories..." -ForegroundColor Yellow
if (!(Test-Path $buildDir)) { New-Item -ItemType Directory -Path $buildDir | Out-Null }
if (!(Test-Path $distDir)) { New-Item -ItemType Directory -Path $distDir | Out-Null }

# Update version in main plugin file
Write-Host "Updating version number in plugin files..." -ForegroundColor Yellow
$mainFile = "$pluginDir\autotask-time-entry.php"
$readmeFile = "$pluginDir\readme.txt"

# Update in main plugin file
(Get-Content $mainFile) -replace 'Version:\s*[0-9.]+', "Version:     $Version" | Set-Content $mainFile
(Get-Content $mainFile) -replace "define\('AUTOTASK_TIME_ENTRY_VERSION', '[0-9.]+'\)", "define('AUTOTASK_TIME_ENTRY_VERSION', '$Version')" | Set-Content $mainFile

# Update in readme.txt
(Get-Content $readmeFile) -replace 'Stable tag: [0-9.]+', "Stable tag: $Version" | Set-Content $readmeFile

# Download Plugin Update Checker if not already present
$pucMainFile = "$pluginDir\includes\plugin-update-checker\plugin-update-checker.php"
if (!(Test-Path $pucMainFile)) {
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
    $pucDir = "$pluginDir\includes\plugin-update-checker"
    if (!(Test-Path $pucDir)) {
        New-Item -ItemType Directory -Path $pucDir -Force | Out-Null
    }
    
    # Copy files to plugin directory
    $extractedDir = "$tempExtractDir\plugin-update-checker-master"
    if (Test-Path $extractedDir) {
        Copy-Item -Path "$extractedDir\*" -Destination "$pucDir\" -Recurse -Force
        Write-Host "Plugin Update Checker files copied to: $pucDir" -ForegroundColor Green
    } else {
        Write-Host "WARNING: Extracted directory not found at: $extractedDir" -ForegroundColor Yellow
    }
    
    # Clean up
    Remove-Item -Path $pucZipFile -Force
    Remove-Item -Path $tempExtractDir -Recurse -Force
    
    Write-Host "Plugin Update Checker download completed." -ForegroundColor Green
} else {
    Write-Host "Plugin Update Checker already exists at: $pucMainFile" -ForegroundColor Green
}

# Create the zip file - IMPORTANT: Must use proper folder structure for WordPress to recognize updates
Write-Host "Creating plugin zip file..." -ForegroundColor Yellow
$exclusions = @(
    ".git",
    ".github",
    "node_modules",
    "build",
    "dist",
    ".gitignore",
    "deploy.ps1",
    "deploy-v2.ps1",
    "deploy-github.ps1",
    "$zipFileName"
)

# Cleanup previous build files
if (Test-Path $zipFilePath) { Remove-Item -Path $zipFilePath -Force }
if (Test-Path $versionedZipFilePath) { Remove-Item -Path $versionedZipFilePath -Force }

# Create a temp directory for building the zip with proper WordPress plugin structure
$tempDir = "$buildDir\temp-build"
if (Test-Path $tempDir) { Remove-Item -Path $tempDir -Recurse -Force }
New-Item -ItemType Directory -Path $tempDir | Out-Null

# IMPORTANT CHANGE: Create the plugin directory directly in the temp dir
# This ensures proper WordPress plugin structure
$pluginBuildDir = "$tempDir\$pluginSlug"
New-Item -ItemType Directory -Path $pluginBuildDir | Out-Null

# Copy all files except exclusions
Get-ChildItem -Path $pluginDir -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($pluginDir.Length + 1)
    $exclude = $false
    
    foreach ($exclusion in $exclusions) {
        if ($relativePath -like "$exclusion*" -or $relativePath -eq $exclusion) {
            $exclude = $true
            break
        }
    }
    
    if (-not $exclude) {
        $targetPath = Join-Path -Path $pluginBuildDir -ChildPath $relativePath
        $targetDir = Split-Path -Path $targetPath -Parent
        
        if (!(Test-Path $targetDir)) {
            New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
        }
        
        Copy-Item -Path $_.FullName -Destination $targetPath -Force
    }
}

# Create both zip files: one for updates and one with version for releases
$zipFilePath = "$distDir\$zipFileName"
$versionedZipFilePath = "$distDir\$versionedZipFileName"

# FIXED ZIP CREATION: Create the zip with the correct structure
Write-Host "Creating ZIP files with proper WordPress plugin structure..." -ForegroundColor Yellow

# Method 1: Use Compress-Archive with proper source structure
Push-Location $tempDir
Compress-Archive -Path "$pluginSlug" -DestinationPath $zipFilePath -Force
Copy-Item -Path $zipFilePath -Destination $versionedZipFilePath -Force
Pop-Location

# Verify the zip structure
Write-Host "`nVerifying ZIP structure..." -ForegroundColor Yellow
try {
    Add-Type -Assembly System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($zipFilePath)
    
    Write-Host "ZIP contents:" -ForegroundColor Cyan
    $zip.Entries | ForEach-Object { 
        Write-Host "  - $($_.FullName)" -ForegroundColor Gray
    }
    
    # Check if the main plugin file exists at the correct path
    $mainPluginFileInZip = $zip.Entries | Where-Object { $_.FullName -eq "$pluginSlug/autotask-time-entry.php" }
    if ($mainPluginFileInZip) {
        Write-Host "✓ Main plugin file found at correct path." -ForegroundColor Green
    } else {
        Write-Host "✗ ERROR: Main plugin file not found at expected path." -ForegroundColor Red
        Write-Host "  Expected: $pluginSlug/autotask-time-entry.php" -ForegroundColor Red
    }
    
    $zip.Dispose()
}
catch {
    Write-Host "Error verifying ZIP structure: $_" -ForegroundColor Red
}

# Clean up temp directory
Remove-Item -Path $tempDir -Recurse -Force

Write-Host "Plugin zip files created successfully:" -ForegroundColor Green
Write-Host "  - $zipFilePath (for updates)" -ForegroundColor Green
Write-Host "  - $versionedZipFilePath (for releases)" -ForegroundColor Green

# Check if the New-GitHubRelease module is installed
$moduleInstalled = Get-Module -ListAvailable -Name New-GitHubRelease
if ($null -eq $moduleInstalled) {
    Write-Host "`nInstalling New-GitHubRelease module..." -ForegroundColor Yellow
    try {
        Install-Module -Name New-GitHubRelease -Force -Scope CurrentUser
    }
    catch {
        Write-Host "Error installing New-GitHubRelease module: $_" -ForegroundColor Red
        Write-Host "Attempting to install from PSGallery..." -ForegroundColor Yellow
        Install-Module -Name New-GitHubRelease -Force -Scope CurrentUser -Repository PSGallery
    }
}

# Import the module
Write-Host "Importing New-GitHubRelease module..." -ForegroundColor Yellow
Import-Module -Name New-GitHubRelease -Force

# GitHub deployment
if (!$GitHubToken) {
    # Try to get from environment variable
    $GitHubToken = $env:GITHUB_TOKEN
    
    # If still null, check the update-config.php file
    if (!$GitHubToken) {
        $configFile = "$pluginDir\update-config.php"
        if (Test-Path $configFile) {
            $configContent = Get-Content $configFile -Raw
            if ($configContent -match "define\('GITHUB_ACCESS_TOKEN', '(.+?)'\)") {
                $GitHubToken = $matches[1]
                if ($GitHubToken -eq "your-github-token-here") {
                    $GitHubToken = $null
                }
            }
        }
    }
    
    # If still null, prompt user
    if (!$GitHubToken) {
        $GitHubToken = Read-Host "Enter your GitHub token"
    }
}

# Get GitHub username if not provided
if (!$GitHubUsername) {
    $GitHubUsername = Read-Host "Enter your GitHub username"
}

# Get GitHub repo if not provided
if (!$GitHubRepo) {
    $GitHubRepo = Read-Host "Enter your GitHub repository name (without username)"
}

if ($GitHubToken -and $GitHubUsername -and $GitHubRepo) {
    Write-Host "`nDeploying to GitHub using New-GitHubRelease module..." -ForegroundColor Yellow
    
    # Prepare release parameters
    $releaseParams = @{
        GitHubUsername = $GitHubUsername
        GitHubRepositoryName = $GitHubRepo
        GitHubAccessToken = $GitHubToken
        TagName = "v$Version"
        ReleaseName = "Release v$Version"
        ReleaseNotes = "Autotask Time Entry Plugin v$Version
        
Requirements:
- WordPress: 5.0 or higher
- PHP: 7.4 or higher"
        AssetFilePaths = @(
            $zipFilePath,
            $versionedZipFilePath
        )
        IsPreRelease = $false
        IsDraft = $false
    }
    
    # Create the release
    try {
        $result = New-GitHubRelease @releaseParams
        
        # Check results
        if ($result.Succeeded -eq $true) {
            Write-Host "Release created successfully: $($result.ReleaseUrl)" -ForegroundColor Green
            
            # Update the token in update-config.php
            $configFile = "$pluginDir\update-config.php"
            if (Test-Path $configFile) {
                (Get-Content $configFile) -replace "define\('GITHUB_ACCESS_TOKEN', '.*?'\)", "define('GITHUB_ACCESS_TOKEN', '$GitHubToken')" | Set-Content $configFile
            }
            
            # Update the GitHub URL in the main plugin file
            (Get-Content $mainFile) -replace "Plugin URI:  https://github.com/[^/]+/[^/\s]+", "Plugin URI:  https://github.com/$GitHubUsername/$GitHubRepo" | Set-Content $mainFile
            
            Write-Host "`nDeployment completed successfully!" -ForegroundColor Green
            Write-Host "Plugin URI and update source have been updated with your GitHub repository." -ForegroundColor Green
            
            # Display installation instructions
            Write-Host "`nPlugin Installation Instructions:" -ForegroundColor Cyan
            Write-Host "1. Download the plugin ZIP from: $($result.ReleaseUrl)" -ForegroundColor Yellow
            Write-Host "2. In WordPress admin, go to Plugins > Add New > Upload Plugin" -ForegroundColor Yellow
            Write-Host "3. Choose the ZIP file and click 'Install Now'" -ForegroundColor Yellow
            Write-Host "4. Activate the plugin" -ForegroundColor Yellow
        }
        else {
            # Handle different failure scenarios
            if ($result.ReleaseCreationSucceeded -eq $false) {
                Write-Host "Error: Failed to create release on GitHub." -ForegroundColor Red
                Write-Host "Error message: $($result.ErrorMessage)" -ForegroundColor Red
            }
            elseif ($result.AllAssetUploadsSucceeded -eq $false) {
                Write-Host "Release created, but not all assets were uploaded successfully." -ForegroundColor Yellow
                Write-Host "Release URL: $($result.ReleaseUrl)" -ForegroundColor Yellow
                Write-Host "Error message: $($result.ErrorMessage)" -ForegroundColor Red
            }
        }
    }
    catch {
        Write-Host "Error deploying to GitHub: $_" -ForegroundColor Red
    }
}
else {
    Write-Host "`nSkipping GitHub deployment due to missing information." -ForegroundColor Yellow
    Write-Host "You can manually deploy later by running:" -ForegroundColor Yellow
    Write-Host ".\deploy-github.ps1 -Version $Version -GitHubToken [token] -GitHubUsername [username] -GitHubRepo [repo]" -ForegroundColor Yellow
}

Write-Host "`nPlugin deployment process completed!" -ForegroundColor Cyan
Write-Host "IMPORTANT NOTE: For proper WordPress auto-updates, the non-versioned file '$zipFileName' must be used as the update source." -ForegroundColor Yellow
