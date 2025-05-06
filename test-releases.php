<?php
/**
 * GitHub Release Test Script
 * 
 * This script helps diagnose GitHub release detection issues by directly
 * querying the GitHub API and showing detailed release information.
 */

// Configuration
$repository = 'wnearhood/Autotask-Plugin';
$configFile = __DIR__ . '/update-config.php';
$authToken = '';

// Try to load auth token from config file
if (file_exists($configFile)) {
    include $configFile;
    if (defined('GITHUB_ACCESS_TOKEN') && !empty(GITHUB_ACCESS_TOKEN)) {
        $authToken = GITHUB_ACCESS_TOKEN;
    }
}

// Display header
echo "===================================\n";
echo "GitHub Release Testing Tool\n";
echo "===================================\n\n";

echo "Repository: $repository\n";
echo "Auth token: " . (empty($authToken) ? 'Not configured' : 'Configured') . "\n\n";

// Function to make API requests
function github_api_request($url, $authToken = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Autotask-Plugin-Tester');
    
    if (!empty($authToken)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $authToken,
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
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($statusCode >= 200 && $statusCode < 300) {
        return json_decode($response, true);
    } else {
        echo "Error: HTTP status $statusCode\n";
        $data = json_decode($response, true);
        if ($data && isset($data['message'])) {
            echo "Message: " . $data['message'] . "\n";
        }
        return null;
    }
}

// 1. Get latest release
echo "Fetching latest release...\n";
$latestReleaseUrl = "https://api.github.com/repos/$repository/releases/latest";
$latestRelease = github_api_request($latestReleaseUrl, $authToken);

if ($latestRelease) {
    echo "✓ Latest release found\n";
    echo "  Tag name: " . $latestRelease['tag_name'] . "\n";
    echo "  Release name: " . $latestRelease['name'] . "\n";
    echo "  Created at: " . $latestRelease['created_at'] . "\n";
    echo "  Published at: " . $latestRelease['published_at'] . "\n";
    echo "  Is prerelease: " . ($latestRelease['prerelease'] ? 'Yes' : 'No') . "\n";
    echo "  Is draft: " . ($latestRelease['draft'] ? 'Yes' : 'No') . "\n\n";
    
    // Assets
    if (!empty($latestRelease['assets'])) {
        echo "  Assets:\n";
        foreach ($latestRelease['assets'] as $asset) {
            echo "    - " . $asset['name'] . " (" . $asset['content_type'] . ")\n";
            echo "      Size: " . round($asset['size'] / 1024, 2) . " KB\n";
            echo "      Download URL: " . $asset['browser_download_url'] . "\n";
            echo "      Download count: " . $asset['download_count'] . "\n\n";
        }
    } else {
        echo "  No assets found for this release.\n\n";
    }
    
    // Check for ZIP assets
    $hasVersionedZip = false;
    $hasNonVersionedZip = false;
    $pluginSlug = 'autotask-time-entry';
    
    if (!empty($latestRelease['assets'])) {
        foreach ($latestRelease['assets'] as $asset) {
            if ($asset['name'] === "$pluginSlug.zip") {
                $hasNonVersionedZip = true;
            }
            
            if (strpos($asset['name'], "$pluginSlug-") === 0 && strpos($asset['name'], '.zip') !== false) {
                $hasVersionedZip = true;
            }
        }
    }
    
    if ($hasVersionedZip && $hasNonVersionedZip) {
        echo "✓ Both required ZIP files found (good)\n";
    } else {
        echo "⚠ Missing required ZIP files:\n";
        if (!$hasNonVersionedZip) {
            echo "  - $pluginSlug.zip (needed for WordPress updates)\n";
        }
        if (!$hasVersionedZip) {
            echo "  - $pluginSlug-{version}.zip (for historical reference)\n";
        }
        echo "\n";
    }
} else {
    echo "✗ Failed to fetch latest release\n\n";
}

// 2. Get all releases
echo "Fetching all releases...\n";
$allReleasesUrl = "https://api.github.com/repos/$repository/releases";
$allReleases = github_api_request($allReleasesUrl, $authToken);

if ($allReleases) {
    echo "✓ Found " . count($allReleases) . " releases\n\n";
    echo "Release history:\n";
    
    foreach ($allReleases as $index => $release) {
        echo "  " . ($index + 1) . ". " . $release['name'] . " (" . $release['tag_name'] . ")\n";
        echo "     Published: " . $release['published_at'] . "\n";
        echo "     Assets: " . count($release['assets']) . "\n";
        if ($release['draft']) {
            echo "     [DRAFT]\n";
        }
        if ($release['prerelease']) {
            echo "     [PRERELEASE]\n";
        }
        echo "\n";
    }
} else {
    echo "✗ Failed to fetch releases\n\n";
}

// 3. Get tags
echo "Fetching tags...\n";
$tagsUrl = "https://api.github.com/repos/$repository/tags";
$tags = github_api_request($tagsUrl, $authToken);

if ($tags) {
    echo "✓ Found " . count($tags) . " tags\n\n";
    echo "Tag list:\n";
    
    foreach ($tags as $index => $tag) {
        echo "  " . ($index + 1) . ". " . $tag['name'] . "\n";
        echo "     Commit: " . substr($tag['commit']['sha'], 0, 8) . "\n";
    }
    echo "\n";
} else {
    echo "✗ Failed to fetch tags\n\n";
}

// 4. Check for WordPress plugin metadata
echo "Checking for WordPress plugin header...\n";
$rawUrl = "https://raw.githubusercontent.com/$repository/main/autotask-time-entry.php";
$mainPluginFile = github_api_request($rawUrl, $authToken);

if ($mainPluginFile) {
    // Simple regex to extract plugin metadata
    preg_match('/Plugin Name:\s*(.+?)$/m', $mainPluginFile, $nameMatch);
    preg_match('/Version:\s*(.+?)$/m', $mainPluginFile, $versionMatch);
    
    if ($nameMatch && $versionMatch) {
        echo "✓ WordPress plugin header found\n";
        echo "  Plugin Name: " . trim($nameMatch[1]) . "\n";
        echo "  Version: " . trim($versionMatch[1]) . "\n\n";
        
        // Compare with latest release
        if ($latestRelease) {
            $mainVersion = trim($versionMatch[1]);
            $latestReleaseVersion = preg_replace('/^v/', '', $latestRelease['tag_name']);
            
            if (version_compare($mainVersion, $latestReleaseVersion, '==')) {
                echo "✓ Main plugin file version matches latest release\n\n";
            } else {
                echo "⚠ Version mismatch:\n";
                echo "  - Main plugin file: $mainVersion\n";
                echo "  - Latest release: $latestReleaseVersion\n\n";
            }
        }
    } else {
        echo "✗ WordPress plugin header not found or incomplete\n\n";
    }
} else {
    echo "✗ Failed to fetch main plugin file\n\n";
}

// Conclusion and recommendations
echo "===================================\n";
echo "Recommendations:\n";
echo "===================================\n\n";

// Based on the results, provide recommendations
$recommendations = [];

if (empty($authToken)) {
    $recommendations[] = "Configure a GitHub token for better API rate limits";
}

if ($latestRelease && (!$hasVersionedZip || !$hasNonVersionedZip)) {
    $recommendations[] = "Use deploy-v2.ps1 script to ensure proper ZIP files are created and uploaded";
}

if ($latestRelease && $mainVersion !== $latestReleaseVersion) {
    $recommendations[] = "Update the plugin version number in main plugin file to match latest release";
}

if (!empty($recommendations)) {
    foreach ($recommendations as $index => $recommendation) {
        echo ($index + 1) . ". $recommendation\n";
    }
} else {
    echo "Everything looks good! Your plugin should be properly detecting updates.\n";
}

echo "\n===================================\n";
echo "Try the following changes to your plugin code:\n";
echo "===================================\n\n";
echo "1. Enable release assets in your Update Checker:\n\n";

echo '// After creating the update checker
if (method_exists($this->updateChecker, \'getVcsApi\')) {
    $vcsApi = $this->updateChecker->getVcsApi();
    
    // Enable release asset support
    if (method_exists($vcsApi, \'enableReleaseAssets\')) {
        $vcsApi->enableReleaseAssets();
    }
    
    // COMMENT OUT setting branches - this can conflict with releases
    // if (method_exists($this->updateChecker, \'setBranch\')) {
    //     $this->updateChecker->setBranch(\'main\');
    // }
}' . "\n\n";

echo "2. Clear the WordPress update cache:\n\n";
echo 'delete_site_transient(\'update_plugins\');' . "\n\n";

echo "3. Force an update check:\n\n";
echo '$this->updateChecker->checkForUpdates();' . "\n\n";
