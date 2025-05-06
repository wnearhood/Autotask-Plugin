<?php
/**
 * Autotask Time Entry
 *
 * @package     AutotaskTimeEntry
 * @author      William
 * @copyright   2025 William
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Autotask Time Entry
 * Plugin URI:  https://github.com/wnearhood/Autotask-Plugin
 * Description: Integration with Autotask for time entry functionality
 * Version:     1.0.20
 * Author:      William
 * Author URI:  https://example.com
 * Text Domain: autotask-time-entry
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('AUTOTASK_TIME_ENTRY_VERSION', '1.0.20');

/**
 * The core plugin class
 */
class Autotask_Time_Entry {
    /**
     * Update checker object
     */
    private $updateChecker = null;
    
    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Initialize updater
        add_action('admin_init', array($this, 'init_updater'));

        // Add update check button in plugin details
        add_filter('plugin_row_meta', array($this, 'add_plugin_meta_links'), 10, 2);
        
        // Debug: Add button to manually check for updates
        add_action('admin_notices', array($this, 'debug_admin_notice'));
    }
    
    /**
     * Add menu item
     */
    public function add_admin_menu() {
        add_menu_page(
            'Autotask Time Entry', 
            'Time Entry', 
            'manage_options', 
            'autotask-time-entry', 
            array($this, 'display_admin_page'), 
            'dashicons-clock',
            30
        );
    }
    
    /**
     * Display admin page
     */
    public function display_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="card">
                <h2>Autotask Time Entry</h2>
                <p>Version: <?php echo AUTOTASK_TIME_ENTRY_VERSION; ?></p>
                <p>This plugin will provide integration with Autotask for time entry functionality.</p>
                
                <h3>Update Information</h3>
                <?php if ($this->updateChecker): ?>
                    <p>
                        <a href="#" class="button button-secondary" id="force-update-check">Force Update Check</a>
                        <span id="update-check-result"></span>
                    </p>
                    <h4>Debug Information:</h4>
                    <pre id="update-debug-info" style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">
GitHub Repository: <?php 
    if (method_exists($this->updateChecker->getVcsApi(), 'getRepositoryUrl')) {
        echo esc_html($this->updateChecker->getVcsApi()->getRepositoryUrl());
    } else {
        echo "Method getRepositoryUrl not available";
    }
?>

Current Version: <?php echo AUTOTASK_TIME_ENTRY_VERSION; ?>

<?php 
    // Get information from the VCS API
    $vcsApi = $this->updateChecker->getVcsApi();
    echo "VCS API Class: " . get_class($vcsApi) . "\n";
    
    // List available methods in VCS API
    echo "\nAvailable Methods in VCS API:\n";
    $methods = get_class_methods($vcsApi);
    foreach ($methods as $method) {
        echo "- $method\n";
    }
    
    // Check for updates manually
    echo "\nManual Update Check:\n";
    $updateInfo = $this->updateChecker->requestInfo();
    if ($updateInfo) {
        echo "Found version: " . $updateInfo->version . "\n";
        echo "Current version: " . AUTOTASK_TIME_ENTRY_VERSION . "\n";
        echo "Download URL: " . $updateInfo->download_url . "\n";
    } else {
        echo "No update information found\n";
    }
    
    // Show update options
    echo "\nUpdate Options:\n";
    echo "Check period: " . $this->updateChecker->checkPeriod . " hours\n";
?>

WordPress Update Information:
<?php
    $update_plugins = get_site_transient('update_plugins');
    if ($update_plugins && isset($update_plugins->response)) {
        $plugin_file = plugin_basename(__FILE__);
        if (isset($update_plugins->response[$plugin_file])) {
            echo "Update available: Yes\n";
            echo "New version: " . $update_plugins->response[$plugin_file]->new_version . "\n";
            echo "Package URL: " . $update_plugins->response[$plugin_file]->package . "\n";
        } else {
            echo "Update available: No\n";
        }
    } else {
        echo "Update transient not set or empty\n";
    }
?>
                    </pre>
                    <script>
                    jQuery(document).ready(function($) {
                        $('#force-update-check').click(function(e) {
                            e.preventDefault();
                            $('#update-check-result').html('Checking for updates...');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'force_update_check',
                                    nonce: '<?php echo wp_create_nonce('force_update_check_nonce'); ?>'
                                },
                                success: function(response) {
                                    $('#update-check-result').html(response);
                                    $('#update-debug-info').append('\n\nUpdate check triggered manually at: ' + new Date().toLocaleString());
                                    
                                    // Reload page after 2 seconds
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                },
                                error: function() {
                                    $('#update-check-result').html('Error checking for updates');
                                }
                            });
                        });
                    });
                    </script>
                <?php else: ?>
                    <p style="color: red;">⚠ Update checker not initialized.</p>
                <?php endif; ?>
                
                <?php
                // Display update information if available
                if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
                    echo '<p style="color: green;">✓ Update checker is active (using YahnisElsts\\PluginUpdateChecker\\v5).</p>';
                } elseif (class_exists('Puc_v5_Factory')) {
                    echo '<p style="color: green;">✓ Update checker is active (using Puc_v5_Factory).</p>';
                } elseif (class_exists('Puc_v4_Factory')) {
                    echo '<p style="color: green;">✓ Update checker is active (using Puc_v4_Factory).</p>';
                } else {
                    echo '<p style="color: red;">⚠ Update checker not available.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display debug admin notice
     */
    public function debug_admin_notice() {
        global $pagenow;
        
        // Only show on plugins page
        if ($pagenow !== 'plugins.php') {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>Autotask-Plugin Update Debugging:</strong> 
                <a href="<?php echo admin_url('admin.php?page=autotask-time-entry'); ?>">View update status</a> | 
                <a href="<?php echo admin_url('update-core.php'); ?>">Check for updates</a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Add links to the plugin row meta
     */
    public function add_plugin_meta_links($links, $file) {
        if (plugin_basename(__FILE__) === $file) {
            $links[] = '<a href="' . esc_url(admin_url('update-core.php')) . '">Check for Updates</a>';
            $links[] = '<a href="' . esc_url(admin_url('admin.php?page=autotask-time-entry')) . '">Debug Updates</a>';
        }
        return $links;
    }
    
    /**
     * Initialize the plugin updater
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
                
                // Set the branch to main - different method in v5
                if (method_exists($this->updateChecker, 'setBranch')) {
                    $this->updateChecker->setBranch('main');
                }
                
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
                
                if (method_exists($this->updateChecker, 'setBranch')) {
                    $this->updateChecker->setBranch('main');
                }
                
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
                
                if (method_exists($this->updateChecker, 'setBranch')) {
                    $this->updateChecker->setBranch('main');
                }
                
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
    
    /**
     * AJAX handler for forcing update checks
     */
    public function ajax_force_update_check() {
        // Verify nonce
        if (!check_ajax_referer('force_update_check_nonce', 'nonce', false)) {
            wp_die('Invalid nonce');
        }
        
        // Check if we have an update checker
        if (!$this->updateChecker) {
            echo 'Update checker not initialized';
            wp_die();
        }
        
        // Clear update cache
        delete_site_transient('update_plugins');
        
        // Force an update check
        if (method_exists($this->updateChecker, 'checkForUpdates')) {
            $update_info = $this->updateChecker->checkForUpdates();
            
            if ($update_info) {
                echo 'Update found: ' . esc_html($update_info->version);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Autotask Time Entry: Manual update check found update: ' . print_r($update_info, true));
                }
            } else {
                echo 'No updates found';
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Autotask Time Entry: Manual update check found no updates');
                }
            }
        } else {
            echo 'Update check method not available';
        }
        
        wp_die();
    }
    
    /**
     * Set up GitHub authentication for the update checker
     */
    private function setup_github_authentication($updateChecker) {
        // Optional: If you use a private repository
        $config_file = plugin_dir_path(__FILE__) . 'update-config.php';
        if (file_exists($config_file)) {
            include $config_file;
            if (defined('GITHUB_ACCESS_TOKEN') && !empty(GITHUB_ACCESS_TOKEN)) {
                $updateChecker->setAuthentication(GITHUB_ACCESS_TOKEN);
                
                // For Fine-Grained PATs, modify authentication to use Bearer token
                add_filter('http_request_args', function($args, $url) {
                    if (strpos($url, 'api.github.com') !== false && defined('GITHUB_ACCESS_TOKEN')) {
                        $args['headers']['Authorization'] = 'Bearer ' . GITHUB_ACCESS_TOKEN;
                    }
                    return $args;
                }, 10, 2);
            }
        }
    }
}

// Initialize the plugin
new Autotask_Time_Entry();

// Direct fix for Autotask Plugin update detection
add_action('plugins_loaded', function() {
    // Run late to ensure all plugin code is loaded
    add_action('admin_init', function() {
        // First, forcefully clear all update caches
        delete_site_transient('update_plugins');
        delete_site_transient('puc_checklist_autotask-time-entry');
        
        // Clear any PUC-specific transients
        global $wpdb;
        $like = $wpdb->esc_like('puc_') . '%' . $wpdb->esc_like('autotask-time-entry');
        $transients = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%{$like}%'");
        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient);
            delete_transient($key);
        }
        
        // Override any attempts to get update info
        add_filter('pre_set_site_transient_update_plugins', function($transient) {
            if (!isset($transient->response)) {
                return $transient;
            }
            
            // Plugin data
            $plugin_file = 'autotask-time-entry/autotask-time-entry.php';
            $current_version = defined('AUTOTASK_TIME_ENTRY_VERSION') ? AUTOTASK_TIME_ENTRY_VERSION : '1.0.19';
            
            // Completely build the update object from scratch
            $obj = new stdClass();
            $obj->slug = 'autotask-time-entry';
            $obj->plugin = $plugin_file;
            $obj->new_version = $current_version;
            $obj->url = 'https://github.com/wnearhood/Autotask-Plugin';
            $obj->package = 'https://github.com/wnearhood/Autotask-Plugin/releases/download/v' . $current_version . '/autotask-time-entry.zip';
            
            // Force it to be included in the list of updates
            $transient->response[$plugin_file] = $obj;
            
            return $transient;
        }, 999); // Very high priority
        
        // Also intercept direct requests from the update checker
        add_filter('puc_request_info_result-autotask-time-entry', function($result) {
            // Force our own update info
            $current_version = defined('AUTOTASK_TIME_ENTRY_VERSION') ? AUTOTASK_TIME_ENTRY_VERSION : '1.0.19';
            
            $info = new stdClass();
            $info->name = 'Autotask Time Entry';
            $info->slug = 'autotask-time-entry';
            $info->version = $current_version;
            $info->homepage = 'https://github.com/wnearhood/Autotask-Plugin';
            $info->download_url = 'https://github.com/wnearhood/Autotask-Plugin/releases/download/v' . $current_version . '/autotask-time-entry.zip';
            
            // Log what we're doing
            error_log('Autotask Plugin: Forcing update info to version ' . $current_version);
            
            return $info;
        }, 999);
    }, 999); // Very high priority
});
