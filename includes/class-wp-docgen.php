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
     * Generate document dari template ID
     * 
     * @param string $template_id Template identifier
     * @param array $extra_data Optional additional data
     * @return string|WP_Error Path ke file yang digenerate atau WP_Error jika gagal
     */
    public function generate($template_id, $extra_data = []) {
        try {
            // Allow plugins to modify template path
            $template_path = apply_filters('wp_docgen_template_path', '', $template_id);
            if (empty($template_path)) {
                return new WP_Error(
                    'template_not_found',
                    sprintf(__('No template registered for ID: %s', 'wp-docgen'), $template_id)
                );
            }

            // Get template data from plugins
            $data = apply_filters('wp_docgen_template_data', [], $template_id);
            
            // Merge with extra data
            $data = array_merge($data, $extra_data);

            // Validate data
            $data = $this->validate_data($data);
            if (is_wp_error($data)) {
                return $data;
            }

            // Fire before generate action
            do_action('wp_docgen_before_generate', $template_id, $data);

            // Get output path
            $output_path = apply_filters('wp_docgen_output_path', 
                wp_upload_dir()['basedir'] . '/wp-docgen',
                $template_id
            );

            // Create output directory if not exists
            wp_mkdir_p($output_path);

            // Get output filename
            $filename = apply_filters('wp_docgen_output_filename', 
                'document-' . time(),
                $template_id
            );

            // Process template
            $output_file = $this->processor->process_template(
                $template_path,
                $data,
                $output_path,
                $filename
            );

            if (is_wp_error($output_file)) {
                do_action('wp_docgen_generation_error', $output_file, $template_id);
                return $output_file;
            }

            // Fire after generate action
            do_action('wp_docgen_after_generate', $output_file, $template_id);

            // Document saved successfully
            do_action('wp_docgen_document_saved', $output_file, $template_id);

            return $output_file;

        } catch (Exception $e) {
            $error = new WP_Error(
                'generation_failed',
                $e->getMessage()
            );
            do_action('wp_docgen_generation_error', $error, $template_id);
            return $error;
        }
    }

    /**
     * Register custom field processor
     */
    public function register_field_processor($type, $callback, $pattern = '') {
        add_filter('wp_docgen_custom_fields', function($fields) use ($type, $callback, $pattern) {
            $fields[$type] = [
                'callback' => $callback,
                'pattern' => $pattern
            ];
            return $fields;
        });
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