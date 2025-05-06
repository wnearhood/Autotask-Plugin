<?php
/**
 * Fixed init_updater() method
 * 
 * Replace your current init_updater() method with this one.
 * This will fix the GitHub release detection issue.
 */

public function init_updater() {
    // Only include the updater in admin pages
    if (!is_admin()) {
        return;
    }
    
    // Define the main plugin-update-checker file path
    $puc_main_file = plugin_dir_path(__FILE__) . 'includes/plugin-update-checker/plugin-update-checker.php';
    
    // Check if the file exists
    if (!file_exists($puc_main_file)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Autotask Time Entry: Plugin Update Checker file not found at: ' . $puc_main_file);
        }
        return;
    }
    
    try {
        // Include the update checker library
        require_once $puc_main_file;
        
        // Debug: Check what classes are available
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Autotask Time Entry: Available classes - Puc_v5_Factory: ' . (class_exists('Puc_v5_Factory') ? 'Yes' : 'No') 
                . ', YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory: ' . (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory') ? 'Yes' : 'No')
                . ', Puc_v4_Factory: ' . (class_exists('Puc_v4_Factory') ? 'Yes' : 'No'));
        }
        
        // Using namespaced class (newer version)
        if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
            $factory = 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';
            $this->updateChecker = $factory::buildUpdateChecker(
                'https://github.com/wnearhood/Autotask-Plugin',
                __FILE__,
                'autotask-time-entry'
            );
            
            // Force PUC to use releases instead of tags
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
            
            // COMMENTED OUT: Don't set branch when using releases as it can cause conflicts
            // if (method_exists($this->updateChecker, 'setBranch')) {
            //     $this->updateChecker->setBranch('main');
            // }
            
            // Enable debug mode
            if (method_exists($this->updateChecker, 'setDebugMode')) {
                $this->updateChecker->setDebugMode(true);
            }
            
            // Log information about the VCS API
            if (defined('WP_DEBUG') && WP_DEBUG && method_exists($this->updateChecker, 'getVcsApi')) {
                $vcsApi = $this->updateChecker->getVcsApi();
                error_log('Autotask Time Entry: VCS API class: ' . get_class($vcsApi));
                
                // List all available methods
                $methods = get_class_methods($vcsApi);
                error_log('Autotask Time Entry: VCS API methods: ' . print_r($methods, true));
            }
            
            // Add more debug logging
            add_filter('puc_pre_inject_update-autotask-time-entry', function($update) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Autotask Time Entry: Update data pre-injection: ' . print_r($update, true));
                }
                return $update;
            });
            
            // Set authentication
            $this->setup_github_authentication($this->updateChecker);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Autotask Time Entry: Using YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory');
            }
        }
        // Then try v5 class directly 
        else if (class_exists('Puc_v5_Factory')) {
            $this->updateChecker = Puc_v5_Factory::buildUpdateChecker(
                'https://github.com/wnearhood/Autotask-Plugin',
                __FILE__,
                'autotask-time-entry'
            );
            
            // Force PUC to use releases instead of tags
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
            
            // COMMENTED OUT: Don't set branch when using releases as it can cause conflicts
            // if (method_exists($this->updateChecker, 'setBranch')) {
            //     $this->updateChecker->setBranch('main');
            // }
            
            // Enable debug mode
            if (method_exists($this->updateChecker, 'setDebugMode')) {
                $this->updateChecker->setDebugMode(true);
            }
            
            // Set authentication
            $this->setup_github_authentication($this->updateChecker);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Autotask Time Entry: Using Puc_v5_Factory');
            }
        }
        // Fallback to v4
        else if (class_exists('Puc_v4_Factory')) {
            $this->updateChecker = Puc_v4_Factory::buildUpdateChecker(
                'https://github.com/wnearhood/Autotask-Plugin',
                __FILE__,
                'autotask-time-entry'
            );
            
            // Force PUC to use releases instead of tags
            if (method_exists($this->updateChecker, 'getVcsApi')) {
                $vcsApi = $this->updateChecker->getVcsApi();
                
                // Enable release asset support
                if (method_exists($vcsApi, 'enableReleaseAssets')) {
                    $vcsApi->enableReleaseAssets();
                }
            }
            
            // COMMENTED OUT: Don't set branch when using releases as it can cause conflicts
            // if (method_exists($this->updateChecker, 'setBranch')) {
            //     $this->updateChecker->setBranch('main');
            // }
            
            // Enable debug mode
            if (method_exists($this->updateChecker, 'setDebugMode')) {
                $this->updateChecker->setDebugMode(true);
            }
            
            // Set authentication
            $this->setup_github_authentication($this->updateChecker);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Autotask Time Entry: Using Puc_v4_Factory');
            }
        }
        else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Autotask Time Entry: Plugin Update Checker classes not found. Checking available classes...');
                
                // Check what classes are declared in the file
                $content = file_get_contents($puc_main_file);
                preg_match_all('/class\s+(\w+)/', $content, $matches);
                error_log('Classes found in plugin-update-checker.php: ' . print_r($matches[1], true));
                
                // Check what's being included
                preg_match_all('/include\s+[\'"](.+?)[\'"]/', $content, $includes);
                error_log('Files included in plugin-update-checker.php: ' . print_r($includes[1], true));
            }
        }
        
        // Add AJAX handler for manual update checks
        add_action('wp_ajax_force_update_check', array($this, 'ajax_force_update_check'));
        
    } catch (Exception $e) {
        // Simple error logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Autotask Time Entry: Error setting up updater: ' . $e->getMessage());
        }
    }
}
