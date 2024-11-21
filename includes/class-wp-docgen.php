<?php
/**
 * Main plugin class 
 *
 * @package WP_DocGen
 * @version 1.0.0
 * Path: includes/class-wp-docgen.php
 * 
 * Changelog:
 * 1.0.0 - 2024-11-21 16:30 WIB
 * - Initial release with basic functionality
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

class WP_DocGen {
    /**
     * Plugin version
     * @var string
     */
    private $version;

    /**
     * Plugin instance
     * @var WP_DocGen
     */
    private static $instance = null;

    /**
     * Document processor
     * @var WP_DocGen_Processor 
     */
    private $processor;

    /**
     * Class constructor
     */
    public function __construct() {
        $this->version = WP_DOCGEN_VERSION;
        $this->processor = new WP_DocGen_Processor();
        
        if (is_null(self::$instance)) {
            self::$instance = $this;
        }
        
        return self::$instance;
    }

    /**
     * Get plugin instance
     * @return WP_DocGen
     */
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
        // Load PHPWord
        require_once WP_DOCGEN_DIR . 'libs/phpword/src/PhpWord/Autoloader.php';
        \PhpOffice\PhpWord\Autoloader::register();

        // Add hooks
        add_action('admin_notices', array($this, 'check_requirements')); 
    }

    /**
     * Generate document dari provider
     * 
     * @param WP_DocGen_Provider $provider Provider interface
     * @return string|WP_Error Path ke file yang digenerate atau WP_Error jika gagal
     */
    public function generate(WP_DocGen_Provider $provider) {
        return $this->processor->generate($provider);
    }

    /**
     * Check plugin requirements
     */
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

    /**
     * Get plugin version
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}