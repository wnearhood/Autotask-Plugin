# Autotask Time Entry

A WordPress plugin for Autotask time entry functionality with automatic updates via GitHub.

## Development Setup

This plugin is designed for easy deployment and automatic updates during development.

### Prerequisites

- WordPress 5.0 or higher
- PHP 7.4 or higher
- GitHub account with repository for plugin hosting
- GitHub Personal Access Token with appropriate permissions

### Installation

1. Clone or download this plugin to your WordPress plugins directory:
   ```
   wp-content/plugins/autotask-plugin/
   ```

2. Set up your GitHub repository:
   - Create a new repository on GitHub
   - It can be either public or private

3. Configure your update token:
   - Edit `update-config.php`
   - Replace `your-github-token-here` with your GitHub Personal Access Token

4. Activate the plugin in WordPress admin panel

### Deployment

Use the included PowerShell script to deploy new versions:

```powershell
.\deploy.ps1 -Version 1.0.1 -GitHubUsername your-username -GitHubRepo your-repository
```

Parameters:
- `Version`: The new version number (required)
- `GitHubToken`: Your GitHub token (optional if set in environment or config)
- `GitHubUsername`: Your GitHub username
- `GitHubRepo`: Your GitHub repository name

Example:
```powershell
.\deploy.ps1 -Version 1.0.1 -GitHubToken ghp_1234567890abcdef -GitHubUsername johnsmith -GitHubRepo autotask-plugin
```

### Automatic Updates

Once deployed to GitHub, WordPress sites with this plugin installed will receive automatic update notifications when you release new versions.

## Development Workflow

1. Make your code changes
2. Run the deployment script with an incremented version number
3. The script will:
   - Update version numbers in plugin files
   - Create a zip package of the plugin
   - Create a new release on GitHub with the zip file
   - Configure the plugin to check for updates from your GitHub repository

## Repository Structure

- `autotask-time-entry.php`: Main plugin file
- `includes/plugin-update-checker/`: Library for handling automatic updates
- `update-config.php`: GitHub configuration (keep this secure)
- `deploy.ps1`: PowerShell deployment script
- `SECURITY.md`: Security considerations for production use

## Using the Same Repository for Code and Distribution

You can use the same GitHub repository for both code storage and distribution:

1. Push your code changes to the repository:
   ```
   git add .
   git commit -m "Description of changes"
   git push origin main
   ```

2. Run the deployment script to create a new release:
   ```
   .\deploy.ps1 -Version x.y.z
   ```

3. The plugin will now check this same repository for updates

This approach maintains a single source of truth for your code and provides a simple update mechanism.

## Security Notice

This plugin is configured for development convenience. Before production use, review `SECURITY.md` for important security considerations that need to be addressed.
