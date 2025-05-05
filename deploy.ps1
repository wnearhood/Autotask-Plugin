# Autotask Time Entry Deployment Script
# This script prepares and deploys the plugin to GitHub

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
$zipFileName = "$pluginSlug-$Version.zip"
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
    
    # Copy files to plugin directory
    $extractedDir = "$tempExtractDir\plugin-update-checker-master"
    Copy-Item -Path "$extractedDir\*" -Destination "$pluginDir\includes\plugin-update-checker\" -Recurse -Force
    
    # Clean up
    Remove-Item -Path $pucZipFile -Force
    Remove-Item -Path $tempExtractDir -Recurse -Force
    
    Write-Host "Plugin Update Checker downloaded and installed successfully." -ForegroundColor Green
}

# Create the zip file
Write-Host "Creating plugin zip file..." -ForegroundColor Yellow
$exclusions = @(
    ".git",
    ".github",
    "node_modules",
    "build",
    "dist",
    ".gitignore",
    "deploy.ps1",
    "$zipFileName"
)

# Create a temp directory for building the zip
$tempDir = "$buildDir\$pluginSlug"
if (Test-Path $tempDir) { Remove-Item -Path $tempDir -Recurse -Force }
New-Item -ItemType Directory -Path $tempDir | Out-Null

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
        $targetPath = Join-Path -Path $tempDir -ChildPath $relativePath
        $targetDir = Split-Path -Path $targetPath -Parent
        
        if (!(Test-Path $targetDir)) {
            New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
        }
        
        Copy-Item -Path $_.FullName -Destination $targetPath -Force
    }
}

# Create the zip file
$zipFilePath = "$distDir\$zipFileName"
if (Test-Path $zipFilePath) { Remove-Item -Path $zipFilePath -Force }
Compress-Archive -Path "$tempDir\*" -DestinationPath $zipFilePath

# Clean up temp directory
Remove-Item -Path $tempDir -Recurse -Force

Write-Host "Plugin zip file created successfully: $zipFilePath" -ForegroundColor Green

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
        
        # Upload the asset
        $uploadUrl = $response.upload_url -replace "{\?name,label}", "?name=$zipFileName"
        
        $headers["Content-Type"] = "application/zip"
        $fileBytes = [System.IO.File]::ReadAllBytes($zipFilePath)
        
        $uploadResponse = Invoke-RestMethod -Uri $uploadUrl -Method Post -Headers $headers -Body $fileBytes
        Write-Host "Plugin zip uploaded successfully: $($uploadResponse.browser_download_url)" -ForegroundColor Green
        
        # Update the token in update-config.php
        (Get-Content $configFile) -replace "define\('GITHUB_ACCESS_TOKEN', '.*?'\)", "define('GITHUB_ACCESS_TOKEN', '$GitHubToken')" | Set-Content $configFile
        
        # Update the GitHub URL in the main plugin file
        (Get-Content $mainFile) -replace "Plugin URI:  https://github.com/YOURUSERNAME/autotask-time-entry", "Plugin URI:  https://github.com/$GitHubUsername/$GitHubRepo" | Set-Content $mainFile
        (Get-Content $mainFile) -replace "'https://github.com/YOURUSERNAME/autotask-time-entry'", "'https://github.com/$GitHubUsername/$GitHubRepo'" | Set-Content $mainFile
        
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
    Write-Host ".\deploy.ps1 -Version $Version -GitHubToken [token] -GitHubUsername [username] -GitHubRepo [repo]" -ForegroundColor Yellow
}

Write-Host "`nPlugin deployment process completed!" -ForegroundColor Cyan
