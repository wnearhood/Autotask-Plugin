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
 * Version:     1.0.4
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

// Define plugin version
define('AUTOTASK_TIME_ENTRY_VERSION', '1.0.4');

/**
 * The core plugin class
 */
class Autotask_Time_Entry {
    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Initialize updater
        add_action('admin_init', array($this, 'init_updater'));
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
                
                <?php
                // Display update information if available
                if (class_exists('Puc_v5_Factory') || class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory') || class_exists('Puc_v4_Factory')) {
                    echo '<p style="color: green;">✓ Update checker is active.</p>';
                } else {
                    echo '<p style="color: red;">⚠ Update checker not available.</p>';
                }
                ?>
            </div>
        </div>
        <?php
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
            
            // Try newer namespace for v5 first
            if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
                $factory = 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';
                $updateChecker = $factory::buildUpdateChecker(
                    'https://github.com/wnearhood/Autotask-Plugin',
                    __FILE__,
                    'autotask-time-entry'
                );
                
                $updateChecker->setBranch('main');
                $this->setup_github_authentication($updateChecker);
            }
            // Then try v5 class directly 
            else if (class_exists('Puc_v5_Factory')) {
                $updateChecker = Puc_v5_Factory::buildUpdateChecker(
                    'https://github.com/wnearhood/Autotask-Plugin',
                    __FILE__,
                    'autotask-time-entry'
                );
                
                $updateChecker->setBranch('main');
                $this->setup_github_authentication($updateChecker);
            }
            // Fallback to v4
            else if (class_exists('Puc_v4_Factory')) {
                $updateChecker = Puc_v4_Factory::buildUpdateChecker(
                    'https://github.com/wnearhood/Autotask-Plugin',
                    __FILE__,
                    'autotask-time-entry'
                );
                
                $updateChecker->setBranch('main');
                $this->setup_github_authentication($updateChecker);
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
            
        } catch (Exception $e) {
            // Simple error logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Autotask Time Entry: Error setting up updater: ' . $e->getMessage());
            }
        }
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
