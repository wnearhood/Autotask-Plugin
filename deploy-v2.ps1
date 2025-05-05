# Autotask Time Entry Deployment Script (Version 2)
# This script prepares and deploys the plugin to GitHub with proper naming for updates

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

# Configuration - IMPORTANT: Fixed slug without version for proper updates
$pluginSlug = "autotask-time-entry"
$pluginDir = $PSScriptRoot
$zipFileName = "$pluginSlug.zip"  # No version in zip filename for WP updates
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
    
    # List files to verify
    Write-Host "Verifying Plugin Update Checker files:" -ForegroundColor Yellow
    if (Test-Path "$pucDir\plugin-update-checker.php") {
        Write-Host "  Main file found: plugin-update-checker.php" -ForegroundColor Green
    } else {
        Write-Host "  WARNING: Main file NOT found: plugin-update-checker.php" -ForegroundColor Red
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
    "$zipFileName"
)

# Create a temp directory for building the zip
$tempDir = "$buildDir\temp-build"
if (Test-Path $tempDir) { Remove-Item -Path $tempDir -Recurse -Force }
New-Item -ItemType Directory -Path $tempDir | Out-Null

# Create the plugin directory with the fixed name (no version)
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
$versionedZipFilePath = "$distDir\$pluginSlug-$Version.zip"

if (Test-Path $zipFilePath) { Remove-Item -Path $zipFilePath -Force }
if (Test-Path $versionedZipFilePath) { Remove-Item -Path $versionedZipFilePath -Force }

# Compress the proper directory structure - from the parent of pluginBuildDir
Push-Location $tempDir
Compress-Archive -Path "$pluginSlug" -DestinationPath $zipFilePath
Copy-Item -Path $zipFilePath -Destination $versionedZipFilePath
Pop-Location

# Verify the PUC library is included in the zip
$pucInZipPath = "$pluginBuildDir\includes\plugin-update-checker\plugin-update-checker.php"
if (Test-Path $pucInZipPath) {
    Write-Host "Plugin Update Checker is included in the zip file." -ForegroundColor Green
    
    # Show more details about what's in the PUC directory
    Get-ChildItem -Path "$pluginBuildDir\includes\plugin-update-checker" -Recurse | ForEach-Object {
        Write-Host "  + $($_.FullName.Substring($pluginBuildDir.Length + 1))" -ForegroundColor Gray
    }
} else {
    Write-Host "WARNING: Plugin Update Checker is NOT in the zip file!" -ForegroundColor Red
    Write-Host "This will cause errors when activating the plugin." -ForegroundColor Red
}

# Clean up temp directory
Remove-Item -Path $tempDir -Recurse -Force

Write-Host "Plugin zip files created successfully:" -ForegroundColor Green
Write-Host "  - $zipFilePath (for updates)" -ForegroundColor Green
Write-Host "  - $versionedZipFilePath (for releases)" -ForegroundColor Green

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
    Write-Host "`nDeploying to GitHub..." -ForegroundColor Yellow
    
    # Create GitHub release
    $releaseUrl = "https://api.github.com/repos/$GitHubUsername/$GitHubRepo/releases"
    $releaseData = @{
        tag_name = "v$Version"
        target_commitish = "main"
        name = "Release v$Version"
        body = "Version $Version release of Autotask Time Entry plugin.`n`nRequirements:`n- WordPress: 5.0 or higher`n- PHP: 7.4 or higher"
        draft = $false
        prerelease = $false
    } | ConvertTo-Json
    
    $headers = @{
        "Authorization" = "Bearer $GitHubToken"
        "Accept" = "application/vnd.github+json"
        "X-GitHub-Api-Version" = "2022-11-28"
    }
    
    try {
        # Create the release
        $response = Invoke-RestMethod -Uri $releaseUrl -Method Post -Headers $headers -Body $releaseData -ContentType "application/json"
        Write-Host "Release created successfully: $($response.html_url)" -ForegroundColor Green
        
        # Upload both assets
        $uploadUrl = $response.upload_url -replace "{\?name,label}", ""
        
        # Upload the versioned zip first (for historical record)
        $headers["Content-Type"] = "application/zip"
        $fileBytes = [System.IO.File]::ReadAllBytes($versionedZipFilePath)
        
        $uploadResponse = Invoke-RestMethod -Uri "$uploadUrl?name=$(Split-Path -Leaf $versionedZipFilePath)" -Method Post -Headers $headers -Body $fileBytes
        Write-Host "Versioned plugin zip uploaded successfully: $($uploadResponse.browser_download_url)" -ForegroundColor Green
        
        # Then upload the non-versioned zip (for updates) - IMPORTANT for WP updates
        $fileBytes = [System.IO.File]::ReadAllBytes($zipFilePath)
        
        $uploadResponse = Invoke-RestMethod -Uri "$uploadUrl?name=$(Split-Path -Leaf $zipFilePath)" -Method Post -Headers $headers -Body $fileBytes
        Write-Host "Update plugin zip uploaded successfully: $($uploadResponse.browser_download_url)" -ForegroundColor Green
        
        # Update the token in update-config.php
        (Get-Content $configFile) -replace "define\('GITHUB_ACCESS_TOKEN', '.*?'\)", "define('GITHUB_ACCESS_TOKEN', '$GitHubToken')" | Set-Content $configFile
        
        # Update the GitHub URL in the main plugin file
        (Get-Content $mainFile) -replace "Plugin URI:  https://github.com/[^/]+/[^/\s]+", "Plugin URI:  https://github.com/$GitHubUsername/$GitHubRepo" | Set-Content $mainFile
        
        Write-Host "`nDeployment completed successfully!" -ForegroundColor Green
        Write-Host "Plugin URI and update source have been updated with your GitHub repository." -ForegroundColor Green
    }
    catch {
        Write-Host "Error deploying to GitHub: $_" -ForegroundColor Red
        Write-Host "Response: $($_.Exception.Response.Content)" -ForegroundColor Red
    }
}
else {
    Write-Host "`nSkipping GitHub deployment due to missing information." -ForegroundColor Yellow
    Write-Host "You can manually deploy later by running:" -ForegroundColor Yellow
    Write-Host ".\deploy-v2.ps1 -Version $Version -GitHubToken [token] -GitHubUsername [username] -GitHubRepo [repo]" -ForegroundColor Yellow
}

Write-Host "`nPlugin deployment process completed!" -ForegroundColor Cyan
Write-Host "IMPORTANT NOTE: For proper WordPress auto-updates, download link must point to '$zipFileName' (without version)" -ForegroundColor Yellow
