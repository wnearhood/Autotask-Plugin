# Troubleshooting the Autotask Time Entry Plugin

## Fixing the "Puc_v4_Factory not found" Error

If you encounter the following error when activating the plugin:

```
PHP Fatal error: Uncaught Error: Class "Puc_v4_Factory" not found
```

This means the Plugin Update Checker library is missing from your plugin installation. Here are several ways to fix this issue:

### Option 1: Run the Install-PUC Script (Recommended)

1. Open PowerShell
2. Navigate to your plugin directory
3. Run the installation script:

```powershell
cd C:\path\to\plugin
.\install-puc.ps1
```

This script will download and install the Plugin Update Checker library directly.

### Option 2: Manual Installation

1. Download the Plugin Update Checker library from GitHub:
   - Go to https://github.com/YahnisElsts/plugin-update-checker
   - Click the green "Code" button and select "Download ZIP"

2. Extract the files:
   - Extract the ZIP file
   - Locate the extracted "plugin-update-checker-master" folder

3. Copy to your plugin:
   - Copy all files from "plugin-update-checker-master" 
   - Paste them into your plugin's "includes/plugin-update-checker" directory
   - Make sure "plugin-update-checker.php" is directly in that folder

### Option 3: Run a Full Deployment

If you've made changes to your plugin, you might want to run a full deployment which will also install the PUC library:

```powershell
.\deploy.ps1 -Version 1.0.3 -GitHubUsername your-username -GitHubRepo Autotask-Plugin
```

## Verifying the Fix

After applying any of these fixes, you should verify that:

1. The file `includes/plugin-update-checker/plugin-update-checker.php` exists in your plugin directory
2. The file `includes/plugin-update-checker/Puc/v4/Factory.php` exists in your plugin directory

## Prevention

To prevent this issue in the future:

1. Always use the updated `deploy.ps1` script which now verifies the PUC library is included
2. When manually uploading the plugin to WordPress, verify that the `includes/plugin-update-checker` directory is included
3. Add the PUC library to your version control system to ensure it's always present

## Additional Troubleshooting

If you're still experiencing issues:

1. Check WordPress error logs for more details
2. Temporarily disable the update checker in your plugin by modifying `init_updater()` to simply return without doing anything
3. Ensure your web server has permissions to read the plugin directory and files
