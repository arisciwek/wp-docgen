<?php
/**
 * WP Document Generator - Plugin utility untuk generate dokumen dari template
 *
 * @package WP_DocGen
 * @version 1.0.0
 * 
 * Plugin Name: WP Document Generator
 * Plugin URI: http://example.com/wp-docgen
 * Description: Plugin utility untuk generate dokumen dari template menggunakan PHPWord
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: arisciwek
 * Author URI: http://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-docgen
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

// Plugin version
define('WP_DOCGEN_VERSION', '1.0.0');

// Plugin paths
define('WP_DOCGEN_FILE', __FILE__);
define('WP_DOCGEN_DIR', plugin_dir_path(__FILE__));
define('WP_DOCGEN_URL', plugin_dir_url(__FILE__));
define('WP_DOCGEN_BASENAME', plugin_basename(__FILE__));

/**
 * Check system requirements before loading plugin
 */
function wp_docgen_check_system_requirements() {
    $errors = array();

    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $errors[] = sprintf(
            /* translators: 1: Current PHP version 2: Required PHP version */
            __('WP Document Generator requires PHP version %2$s or higher. Your current version is %1$s', 'wp-docgen'),
            PHP_VERSION,
            '7.4'
        );
    }

    if (version_compare(get_bloginfo('version'), '5.8', '<')) {
        $errors[] = sprintf(
            /* translators: 1: Current WordPress version 2: Required WordPress version */
            __('WP Document Generator requires WordPress version %2$s or higher. Your current version is %1$s', 'wp-docgen'),
            get_bloginfo('version'),
            '5.8'
        );
    }

    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }
        // Deactivate plugin
        deactivate_plugins(plugin_basename(__FILE__));
        return false;
    }

    return true;
}
add_action('admin_notices', 'wp_docgen_check_system_requirements');

// Only load the plugin if requirements are met
if (wp_docgen_check_system_requirements()) {
    require_once WP_DOCGEN_DIR . 'includes/interfaces/interface-wp-docgen-provider.php';
    require_once WP_DOCGEN_DIR . 'includes/class-wp-docgen-processor.php';
    require_once WP_DOCGEN_DIR . 'includes/class-wp-docgen.php';
    require_once WP_DOCGEN_DIR . 'includes/class-wp-docgen-template.php';
    require_once WP_DOCGEN_DIR . 'includes/class-wp-docgen-activator.php';

    // Activation/Deactivation hooks
    register_activation_hook(__FILE__, array('WP_DocGen_Activator', 'activate'));
    register_deactivation_hook(__FILE__, array('WP_DocGen_Activator', 'deactivate'));

    /**
     * Load plugin text domain for translations
     */
    function wp_docgen_load_textdomain() {
        load_plugin_textdomain(
            'wp-docgen',
            false,
            dirname(WP_DOCGEN_BASENAME) . '/languages/'
        );
    }
    add_action('plugins_loaded', 'wp_docgen_load_textdomain');

    /**
     * Initialize plugin
     */
    function run_wp_docgen() {
        $plugin = new WP_DocGen();
        $plugin->run();
    }
    run_wp_docgen();
}

/**
 * Helper function untuk akses global instance
 * @return WP_DocGen Main plugin instance
 */
function wp_docgen() {
    return WP_DocGen::get_instance();
}
