# Fixing Auto-Update Issues in Autotask Time Entry Plugin

This document explains how to fix the two main issues with the plugin's auto-update functionality:

## Issue 1: PUC Classes Not Found

The Plugin Update Checker (PUC) library has been updated to version 5, and the class structure has changed. The plugin has been updated to:

1. Support multiple versions of the PUC library (v4 and v5)
2. Look for classes in different namespaces (`YahnisElsts\PluginUpdateChecker\v5\PucFactory`)
3. Add additional debug logging to help track down class loading issues

### How to Fix:

1. **Replace the main plugin file:**
   - The updated file now tries multiple class paths and provides better debugging

2. **Check for proper PUC directory structure:**
   - Verify that `includes/plugin-update-checker` contains the PUC library files
   - Make sure it has a folder structure that includes the namespaced classes:
     ```
     includes/plugin-update-checker/
     ├── plugin-update-checker.php
     ├── Puc/
     └── YahnisElsts/PluginUpdateChecker/v5/
     ```

3. **Manually download the PUC library if needed:**
   - Run `.\install-puc.ps1` to download the latest version

## Issue 2: Incorrect Update Package Structure

WordPress expects updates to maintain the same folder structure. Our original deployment script was creating a new folder name for each version (`autotask-time-entry-1.0.3`), which breaks WordPress's update mechanism.

### How to Fix:

1. **Use the new deployment script:**
   - Use `deploy-v2.ps1` instead of the original `deploy.ps1`
   - This script creates a proper folder structure for WordPress updates

2. **Create consistent ZIP files:**
   - The new script creates two ZIP files:
     - `autotask-time-entry.zip` (without version) - for WordPress updates
     - `autotask-time-entry-X.X.X.zip` (with version) - for historical releases
   
3. **Update your GitHub releases:**
   - Always include both ZIP files in your GitHub releases
   - The plugin will look for the non-versioned ZIP file for updates

## Update Testing Cycle

To test that updates are working properly:

1. **Deploy version 1.0.4:**
   ```powershell
   .\deploy-v2.ps1 -Version 1.0.4 -GitHubUsername wnearhood -GitHubRepo Autotask-Plugin
   ```

2. **Install version 1.0.4 in WordPress:**
   - Upload and activate the plugin
   - Verify it shows "Update checker is active" in the admin page

3. **Deploy version 1.0.5:**
   ```powershell
   .\deploy-v2.ps1 -Version 1.0.5 -GitHubUsername wnearhood -GitHubRepo Autotask-Plugin
   ```

4. **Check for updates in WordPress:**
   - Go to Dashboard > Updates
   - Click "Check for Updates"
   - Verify that version 1.0.5 appears as an available update

## Additional Debugging

If updates are still not working:

1. **Check WordPress debug log:**
   - Look for messages from "Autotask Time Entry"
   - Check what classes are being detected

2. **Verify GitHub release structure:**
   - Make sure the release includes both ZIP files
   - Check that download URLs are correct

3. **Inspect ZIP file contents:**
   - Extract the ZIP and verify it has the correct folder structure:
     ```
     autotask-time-entry/
     ├── autotask-time-entry.php
     ├── includes/
     │   └── plugin-update-checker/
     └── ...
     ```
