<?php
/**
 * Autotask Update Fix
 * 
 * This file contains the needed changes to fix the GitHub release detection issue.
 * Copy the relevant code sections into your main plugin file.
 */

/**
 * Change 1: Modify the PUC configuration in init_updater() method
 * 
 * Replace this code:
 *
 * $this->updateChecker = $factory::buildUpdateChecker(
 *     'https://github.com/wnearhood/Autotask-Plugin',
 *     __FILE__,
 *     'autotask-time-entry'
 * );
 * 
 * // Set the branch to main - different method in v5
 * if (method_exists($this->updateChecker, 'setBranch')) {
 *     $this->updateChecker->setBranch('main');
 * }
 * 
 * With:
 */

$this->updateChecker = $factory::buildUpdateChecker(
    'https://github.com/wnearhood/Autotask-Plugin',
    __FILE__,
    'autotask-time-entry'
);

// Force PUC to use releases instead of tags - this fixes the wrong version issue
if (method_exists($this->updateChecker, 'getVcsApi')) {
    $vcsApi = $this->updateChecker->getVcsApi();
    
    // Enable release asset support
    if (method_exists($vcsApi, 'enableReleaseAssets')) {
        $vcsApi->enableReleaseAssets();
    }
    
    // Force using latest release
    if (method_exists($vcsApi, 'setReleaseVersionFilter')) {
        $vcsApi->setReleaseVersionFilter(function($version) {
            // Only use official releases
            return !empty($version) && strpos($version, '-') === false;
        });
    }
}

// COMMENT OUT - Don't set branch when using releases
// if (method_exists($this->updateChecker, 'setBranch')) {
//     $this->updateChecker->setBranch('main');
// }

/**
 * Change 2: Add additional debug output to display_admin_page() method
 * 
 * Add this inside the Manual Update Check section of your debug info
 */

// Check specific information about releases
if (method_exists($vcsApi, 'getLatestRelease')) {
    echo "\nLatest Release Info:\n";
    try {
        $releaseInfo = $vcsApi->getLatestRelease();
        echo "Release tag: " . $releaseInfo['tag_name'] . "\n";
        echo "Release name: " . $releaseInfo['name'] . "\n";
        echo "Is prelease: " . ($releaseInfo['prerelease'] ? 'Yes' : 'No') . "\n";
        
        // Show assets
        if (!empty($releaseInfo['assets'])) {
            echo "Release assets:\n";
            foreach ($releaseInfo['assets'] as $asset) {
                echo "- " . $asset['name'] . " (" . $asset['content_type'] . ")\n";
                echo "  URL: " . $asset['browser_download_url'] . "\n";
            }
        } else {
            echo "No release assets found.\n";
        }
    } catch (Exception $e) {
        echo "Error fetching release info: " . $e->getMessage() . "\n";
    }
}

// Force an update check right away
$this->updateChecker->checkForUpdates();
