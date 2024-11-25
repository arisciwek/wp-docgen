<?php
/**
 * Custom PhpWord Autoloader tanpa Composer
 * 
 * @package     WP_DocGen
 * @subpackage  Includes
 * @version     1.0.1
 * @author      arisciwek
 * 
 * Path: includes/phpword-autoloader.php
 * 
 * Description:
 * Handles PhpWord autoloading tanpa memerlukan Composer.
 * Berguna untuk lingkungan WordPress yang tidak memiliki akses SSH.
 * Pre-loads kelas esensial yang sering digunakan saat generate dokumen.
 * 
 * Requires:
 * - libs/phpword/src/PhpWord/ folder dengan struktur file sesuai PhpWord
 * - ZipArchive extension di PHP
 * 
 * Changelog:
 * 1.0.3 - 2024-11-24 23:35 WIB
 * - Fixed autoloader path detection
 * - Added more detailed path logging
 * 
 * Changelog:
 * 1.0.2 - 2024-11-24 23:30 WIB
 * - Added support untuk struktur folder PhpOffice/
 * - Fixed path resolution untuk shared classes
 * - Added debug logging untuk path detection
 * 
 * Changelog:
 * 1.0.1 - 2024-11-24 
 * - Added pre-loading untuk Shared classes esensial
 * - Fixed urutan loading ZipArchive
 * - Enhanced error reporting untuk missing files
 * 
 * 1.0.0 - 2024-11-21
 * - Initial release dengan basic autoloading
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

class WP_DocGen_PhpWord_Loader {
    /**
     * Base paths untuk mencari PhpWord files
     * @var array
     */
    private static $base_paths = array(
        'phpoffice' => 'libs/phpword/src/PhpOffice/PhpWord/',
        'phpword' => 'libs/phpword/src/PhpWord/',
        'root' => 'libs/phpword/src/'
    );

    /**
     * Find file in possible paths
     * @param string $file Relative file path
     * @return string|false Full path if found, false if not
     */
    private static function find_file($file) {
        foreach (self::$base_paths as $base) {
            $full_path = WP_DOCGEN_DIR . $base . $file;
            if (file_exists($full_path)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    //error_log('DocGen PhpWord: Found ' . $file . ' at ' . $full_path);
                }
                return $full_path;
            }
            if (defined('WP_DEBUG') && WP_DEBUG) {
                //error_log('DocGen PhpWord: Checked path ' . $full_path);
            }
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('DocGen PhpWord: Could not find ' . $file . ' in any path');
        }
        return false;
    }

    /**
     * Find Autoloader.php di berbagai kemungkinan lokasi
     * @return string|false
     */
    private static function find_autoloader() {
        $possible_paths = array(
            WP_DOCGEN_DIR . 'libs/phpword/src/PhpOffice/PhpWord/Autoloader.php',
            WP_DOCGEN_DIR . 'libs/phpword/src/PhpWord/Autoloader.php',
            WP_DOCGEN_DIR . 'libs/phpword/src/Autoloader.php',
            // Coba cari di subfolder PhpOffice juga
            WP_DOCGEN_DIR . 'libs/phpword/src/PhpOffice/Autoloader.php'
        );

        foreach ($possible_paths as $path) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // error_log('DocGen PhpWord: Checking for Autoloader at ' . $path);
            }
            if (file_exists($path)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // error_log('DocGen PhpWord: Found Autoloader at ' . $path);
                }
                return $path;
            }
        }

        return false;
    }

    /**
     * Register PhpWord autoloader
     */
    public static function register() {

        // Pre-load essential shared classes
        $essential_files = array(
            'Shared/ZipArchive.php',
            'Shared/Text.php',
            'Shared/XMLWriter.php'
        );

        foreach ($essential_files as $file) {
            if ($found_path = self::find_file($file)) {
                require_once $found_path;
            }
        }

        // Find and load Autoloader
        $autoloader_path = self::find_autoloader();
        if ($autoloader_path) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                //error_log('DocGen PhpWord: Loading Autoloader from ' . $autoloader_path);
            }
            require_once $autoloader_path;
            
            // Check if class exists before registering
            if (class_exists('PhpOffice\PhpWord\Autoloader')) {
                \PhpOffice\PhpWord\Autoloader::register();
            } else {
                //error_log('DocGen PhpWord: Autoloader class not found after loading file');
            }
        } else {
            //error_log('DocGen PhpWord: Could not find Autoloader.php - will try to continue without it');
        }

        // Register our custom autoloader
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Autoload PhpWord classes
     * @param string $class Class name to load
     */
    public static function autoload($class) {
        // Only handle PhpWord classes
        if (strpos($class, 'PhpOffice\\PhpWord\\') !== 0) {
            return;
        }

        // Convert namespace ke path
        $relative_path = str_replace('\\', '/', substr($class, strlen('PhpOffice\\PhpWord\\'))) . '.php';
        
        if ($found_path = self::find_file($relative_path)) {
            require_once $found_path;
        }
    }

    /**
     * Validate PhpWord installation
     * @return bool|WP_Error
     */
    public static function validate() {
        $required_files = array(
            'TemplateProcessor.php',
            'Shared/Text.php',
            'Shared/XMLWriter.php',
            'Shared/ZipArchive.php'
        );

        $missing_files = array();

        foreach ($required_files as $file) {
            if (!self::find_file($file)) {
                $missing_files[] = $file;
            }
        }

        if (!empty($missing_files)) {
            return new WP_Error(
                'missing_phpword_files',
                sprintf(
                    __('File PhpWord yang diperlukan tidak ditemukan: %s', 'wp-docgen'),
                    implode(', ', $missing_files)
                )
            );
        }

        return true;
    }
}
