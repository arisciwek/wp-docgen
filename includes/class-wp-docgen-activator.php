<?php
/**
 * Activation handler
 *
 * @package WP_DocGen
 * @version 1.0.0  
 * Path: includes/class-wp-docgen-activator.php
 * 
 * Changelog:
 * 1.0.0 - 2024-11-21 17:00 WIB
 * - Initial release
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

class WP_DocGen_Activator {

    public static function activate() {
        // Create temp directory if not exists
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wp-docgen-temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Create .htaccess to prevent direct access
        $htaccess = $temp_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "deny from all\n";
            @file_put_contents($htaccess, $rules);
        }

        // Create empty index.php
        $index = $temp_dir . '/index.php';
        if (!file_exists($index)) {
            @touch($index);
        }

        flush_rewrite_rules();
    }

    public static function deactivate() {
        // Optional: Clean temp files
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wp-docgen-temp';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        flush_rewrite_rules();
    }
}