<?php
/**
 * Activation handler
 *
 * @package     WP_DocGen
 * @subpackage  Includes
 * @version     1.0.2
 * @author      arisciwek
 * 
 * Path: includes/class-wp-docgen-activator.php
 * 
 * Description:
 * Handle aktivasi dan deaktivasi plugin.
 * Membuat direktori yang diperlukan dan mengatur permission.
 * Mengelola cleanup saat deaktivasi.
 * 
 * Changelog:
 * 1.0.2 - 2024-12-27 20:07 WIB
 * - Added proper directory structure
 * - Added QR code cache directory
 * - Improved security measures
 * 
 * 1.0.1 - 2024-11-24
 * - Added directory permissions
 * - Added security files
 * 
 * 1.0.0 - 2024-11-21
 * - Initial release
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

class WP_DocGen_Activator {

    public static function activate() {
        // Create required directories
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        // Directories to create
        $directories = array(
            '/docgen-temp',           // Temporary files
            '/docgen-temp/qrcodes',   // QR code cache
            '/docgen-templates',      // Template storage
        );

        // Create each directory if not exists
        foreach ($directories as $dir) {
            $full_path = $base_dir . $dir;
            if (!file_exists($full_path)) {
                wp_mkdir_p($full_path);
                
                // Set directory permissions
                @chmod($full_path, 0755);
                
                // Create .htaccess to prevent direct access
                $htaccess = $full_path . '/.htaccess';
                if (!file_exists($htaccess)) {
                    $rules = "deny from all\n";
                    @file_put_contents($htaccess, $rules);
                }

                // Create empty index.php
                $index = $full_path . '/index.php';
                if (!file_exists($index)) {
                    @file_put_contents($index, "<?php\n// Silence is golden");
                }
            }
        }

        flush_rewrite_rules();
    }

    public static function deactivate() {
        // Clean temp files but preserve templates
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/docgen-temp';
        
        if (is_dir($temp_dir)) {
            self::cleanup_directory($temp_dir);
        }

        flush_rewrite_rules();
    }

    /**
     * Recursively cleanup directory
     */
    private static function cleanup_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..', '.htaccess', 'index.php'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::cleanup_directory($path);
            } else {
                @unlink($path);
            }
        }
    }
}