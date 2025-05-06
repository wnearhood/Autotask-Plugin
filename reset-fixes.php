<?php
/**
 * Autotask Plugin - Reset and Fix Script
 * 
 * This script will help reset any cached update data and apply a more direct
 * approach to fixing the GitHub release detection issue.
 */

// Configuration
$plugin_slug = 'autotask-time-entry';
$repo_owner = 'wnearhood';
$repo_name = 'Autotask-Plugin';
$current_version = '1.0.19'; // Update this to match your current version

// Function to make a GitHub API request
function github_api_request($endpoint, $token = null) {
    $url = "https://api.github.com/$endpoint";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Autotask-Plugin-Reset-Script');
    
    if ($token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28'
        ]);
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28'
        ]);
    }
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($status >= 200 && $status < 300) {
        return json_decode($response, true);
    } else {
        echo "Error fetching GitHub data: " . ($error ? $error : "HTTP Status $status") . "\n";
        echo "Response: $response\n";
        return null;
    }
}

// Get GitHub token if available
$token = null;
$config_file = __DIR__ . '/update-config.php';
if (file_exists($config_file)) {
    include $config_file;
    if (defined('GITHUB_ACCESS_TOKEN') && !empty(GITHUB_ACCESS_TOKEN)) {
        $token = GITHUB_ACCESS_TOKEN;
        echo "Found GitHub token in config file.\n";
    }
}

echo "======================================\n";
echo "Autotask Plugin - Reset and Fix Script\n";
echo "======================================\n\n";

// 1. Get latest release information
echo "Fetching latest release information...\n";
$latest_release = github_api_request("repos/$repo_owner/$repo_name/releases/latest", $token);

if ($latest_release) {
    echo "Latest release: " . $latest_release['tag_name'] . " - " . $latest_release['name'] . "\n";
    
    // Check for assets
    if (!empty($latest_release['assets'])) {
        echo "Found " . count($latest_release['assets']) . " assets:\n";
        foreach ($latest_release['assets'] as $asset) {
            echo "  - " . $asset['name'] . " (" . $asset['content_type'] . ")\n";
            echo "    URL: " . $asset['browser_download_url'] . "\n";
        }
    } else {
        echo "WARNING: No assets found for this release!\n";
    }
} else {
    echo "Failed to fetch latest release. Check your GitHub token or internet connection.\n";
}

// Create the fix file
echo "\nCreating fix files...\n";

// 1. Create a fix to inject at the end of the plugin
$direct_fix_code = '
// Fix for GitHub release detection
add_action("admin_init", function() {
    // Force cache refresh on our plugin
    $transient = get_site_transient("update_plugins");
    if ($transient && isset($transient->response["autotask-time-entry/autotask-time-entry.php"])) {
        unset($transient->response["autotask-time-entry/autotask-time-entry.php"]);
        set_site_transient("update_plugins", $transient);
    }
    
    // Set update data directly when the plugin requests it
    add_filter("pre_set_site_transient_update_plugins", function($transient) {
        // Only modify during update checks that have response data
        if (!isset($transient->response)) {
            return $transient;
        }
        
        $plugin_path = "autotask-time-entry/autotask-time-entry.php";
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . "/" . $plugin_path);
        $current_version = $plugin_data["Version"] ?? "' . $current_version . '";
        
        // Hardcoded update data
        $update_url = "https://github.com/wnearhood/Autotask-Plugin/releases/download/v' . $current_version . '/autotask-time-entry.zip";
        
        // Create update object
        $update_obj = new stdClass();
        $update_obj->slug = "autotask-time-entry";
        $update_obj->plugin = $plugin_path;
        $update_obj->new_version = "' . $current_version . '";
        $update_obj->package = $update_url;
        $update_obj->url = "https://github.com/wnearhood/Autotask-Plugin";
        
        // Check if we need to mark as having an update
        if (isset($plugin_data["Version"]) && version_compare($current_version, $plugin_data["Version"], ">")) {
            $transient->response[$plugin_path] = $update_obj;
        } else {
            // No update needed - ensure it appears in no_update
            $transient->no_update[$plugin_path] = $update_obj;
        }
        
        return $transient;
    }, 20, 1);
});
';

// 2. Create a wp-cli fix script
$wp_cli_fix = '<?php
/**
 * Autotask Plugin - WP-CLI Fix Script
 *
 * This script can be run via WP-CLI to inject the update data directly.
 * Usage: wp eval-file fix-autotask-cli.php
 */

echo "Autotask Plugin - Direct Update Fix\n";

// Get plugin data
$plugin_path = "autotask-time-entry/autotask-time-entry.php";
$plugin_data = get_plugin_data(WP_PLUGIN_DIR . "/" . $plugin_path);
$current_version = $plugin_data["Version"] ?? "' . $current_version . '";

echo "Current plugin version: $current_version\n";

// Hardcoded update data
$update_url = "https://github.com/wnearhood/Autotask-Plugin/releases/download/v' . $current_version . '/autotask-time-entry.zip";

// Create update object
$update_obj = new stdClass();
$update_obj->slug = "autotask-time-entry";
$update_obj->plugin = $plugin_path;
$update_obj->new_version = "' . $current_version . '";
$update_obj->package = $update_url;
$update_obj->url = "https://github.com/wnearhood/Autotask-Plugin";

// Get transient
$transient = get_site_transient("update_plugins");
if (!is_object($transient)) {
    $transient = new stdClass();
    $transient->response = array();
    $transient->no_update = array();
}

// Force an update to be available
$transient->response[$plugin_path] = $update_obj;

// Save transient
set_site_transient("update_plugins", $transient);

echo "Update data injected. Plugin update should now show in WP admin.\n";
echo "Update URL: $update_url\n";
';

// Write the files
file_put_contents(__DIR__ . '/inject-plugin-fix.php', "<?php\n" . $direct_fix_code);
file_put_contents(__DIR__ . '/fix-autotask-cli.php', $wp_cli_fix);

echo "Created fix files:\n";
echo "1. inject-plugin-fix.php - Add this code to the end of your main plugin file\n";
echo "2. fix-autotask-cli.php - Run via WP-CLI to inject update data directly\n\n";

echo "Instructions:\n";
echo "Option 1 (Recommended): Add the code from inject-plugin-fix.php to the end of your main plugin file\n";
echo "Option 2: If you have WP-CLI access, run: wp eval-file fix-autotask-cli.php\n\n";

echo "Troubleshooting:\n";
echo "1. Make sure the release ZIP file is named exactly 'autotask-time-entry.zip'\n";
echo "2. Verify the download URL: https://github.com/wnearhood/Autotask-Plugin/releases/download/v$current_version/autotask-time-entry.zip\n";
echo "3. Check that your release is tagged as 'v$current_version' in GitHub\n";
