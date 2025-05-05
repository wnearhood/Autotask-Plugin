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
 * Plugin URI:  https://github.com/wnearhood/autotask-plugin
 * Description: Integration with Autotask for time entry functionality
 * Version:     1.0.0
 * Author:      William
 * Author URI:  https://example.com
 * Text Domain: autotask-plugin
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin version
define('AUTOTASK_TIME_ENTRY_VERSION', '1.0.0');

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
        $this->init_updater();
    }
    
    /**
     * Add menu item
     */
    public function add_admin_menu() {
        add_menu_page(
            'Autotask Time Entry', 
            'Time Entry', 
            'manage_options', 
            'autotask-plugin', 
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
            </div>
        </div>
        <?php
    }
    
    /**
     * Initialize the plugin updater
     */
    private function init_updater() {
        // Only include the updater in admin pages
        if (!is_admin()) {
            return;
        }
        
        // Include Plugin Update Checker library
        require_once plugin_dir_path(__FILE__) . 'includes/plugin-update-checker/plugin-update-checker.php';
        
        try {
            // Configure the update checker
            $updateChecker = Puc_v4_Factory::buildUpdateChecker(
                'https://github.com/wnearhood/autotask-plugin',
                __FILE__,
                'autotask-plugin'
            );
            
            // Set the branch that contains the stable release
            $updateChecker->setBranch('main');
            
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
        } catch (Exception $e) {
            // Simple error logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Autotask Time Entry: Error setting up updater: ' . $e->getMessage());
            }
        }
    }
}

// Initialize the plugin
new Autotask_Time_Entry();
