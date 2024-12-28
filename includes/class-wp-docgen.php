<?php
/**
 * Main plugin class 
 *
 * @package WP_DocGen
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

class WP_DocGen {
    private $version;
    private static $instance = null;
    private $processor;

    public function __construct() {
        $this->version = WP_DOCGEN_VERSION;
        $this->processor = new WP_DocGen_Processor();
        
        if (is_null(self::$instance)) {
            self::$instance = $this;
        }
        
        return self::$instance;
    }

    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin
     */
    public function run() {
        // Load custom PhpWord autoloader
        require_once WP_DOCGEN_DIR . 'includes/phpword-autoloader.php';
        
        // Validate PhpWord installation
        $validation = WP_DocGen_PhpWord_Loader::validate();
        if (is_wp_error($validation)) {
            add_action('admin_notices', function() use ($validation) {
                echo '<div class="notice notice-error"><p>' . 
                     esc_html($validation->get_error_message()) . 
                     '</p></div>';
            });
            return;
        }

        // Register PhpWord autoloader
        WP_DocGen_PhpWord_Loader::register();

        // Add hooks
        add_action('admin_notices', array($this, 'check_requirements')); 
    }
    
    // Di class-wp-docgen.php, tambahkan:
    public function generate(WP_DocGen_Provider $provider) {
        $processor = new WP_DocGen_Processor(); 
        return $processor->generate($provider);
    }

    public function check_requirements() {
        // Check writable temp directory
        if (!wp_is_writable(sys_get_temp_dir())) {
            $message = __('WP Document Generator requires a writable temporary directory. Please check your server configuration.', 'wp-docgen');
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }

        // Check PHP extensions
        $required_extensions = array('zip', 'xml', 'fileinfo');
        $missing = array();

        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        if (!empty($missing)) {
            $message = sprintf(
                /* translators: %s: Extension names */
                __('WP Document Generator requires the following PHP extensions: %s', 'wp-docgen'),
                implode(', ', $missing)
            );
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }
    }

    public function get_version() {
        return $this->version;
    }
}
