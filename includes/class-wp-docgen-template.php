<?php
/**
 * Document template handling dan custom fields
 *
 * @package     WP_DocGen
 * @subpackage  Includes
 * @version     1.0.2
 * @author      arisciwek
 * 
 * Path: includes/class-wp-docgen-template.php
 * 
 * Description:
 * Class untuk mengelola template DOCX/ODT dengan dukungan placeholder/custom fields.
 * Menangani konversi placeholder ke content aktual sebelum generate dokumen.
 * Support untuk field standar (tanggal, user, site) dan field spesial (QR code, image).
 * Mendukung format placeholder dengan berbagai variasi dan parameter.
 * Menggunakan caching untuk mengoptimalkan performa.
 * 
 * Format Placeholder yang Didukung:
 * - ${date:TEXT:FORMAT}              : Format tanggal (j F Y, H:i, Y-m-d, d/m/Y, dll)
 * - ${user:FIELD}                    : Data user WordPress (name, email, role)
 * - ${image:PATH:WIDTH:HEIGHT}       : Insert gambar dengan dimensi
 * - ${site:FIELD}                    : Info site (name, url, description)
 * - ${qrcode:TEXT:SIZE[:ERROR]}      : Generate QR Code dengan ukuran & error level
 * 
 * Dependencies:
 * - PHPWord library untuk manipulasi dokumen
 * - PHP QR Code library (libs/phpqrcode/qrlib.php)
 * - WordPress upload directory (writable)
 * - WP filesystem API
 * 
 * Usage:
 * $template = new WP_DocGen_Template();
 * $data = $template->process_fields($phpWord, $template_data);
 * 
 * QR Code Placeholder Format:
 * ${qrcode:text:size[:error_level]}
 * - text        : Teks/URL yang akan di-encode (required)
 * - size        : Ukuran QR code dalam pixel (50-500) (required)
 * - error_level : Level error correction (L/M/Q/H) (optional, default: L)
 * 
 * Contoh:
 * ${qrcode:https://example.com:100}
 * ${qrcode:Hello World:200:H}
 * 
 * Cache Management:
 * - QR code di-cache dalam wp-content/uploads/wp-docgen/qrcache/
 * - Cache key: MD5(text + size + error_level)
 * - Cache expires: 24 jam
 * - Automatic cleanup untuk file expired
 * 
 * Error Handling:
 * - Validasi keberadaan library
 * - Try-catch untuk setiap QR generation
 * - Fallback ke placeholder jika gagal
 * - Error logging via WordPress
 * 
 * Security Features:
 * - Sanitasi input parameter
 * - Batasan ukuran QR code
 * - Cache directory protection (.htaccess)
 * - Unique filenames untuk temporary files
 * 
 * Changelog:
 * 
 * 1.0.2 - 2024-12-27 20:07 WIB
 * - Fixed QR code image generation
 * - Added image processing methods
 * - Improved template field handling
 * 
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}
class WP_DocGen_Template {

   /**
    * Custom fields yang didukung
    */
   private $supported_fields = array(
       'date',     // Format tanggal
       'image',    // Insert gambar
       'user',     // Data user WP
       'site',     // Info site
       'qrcode'    // Generate QR Code
   );

 
public function process_fields($phpWord, $data) {
    error_log('=== START TEMPLATE FIELD PROCESSING ===');
    error_log('Supported fields: ' . print_r($this->supported_fields, true));
    error_log('Initial data: ' . print_r($data, true));

    foreach ($this->supported_fields as $field) {
        $method = "process_{$field}_field";
        error_log("Checking field processor: {$field} -> {$method}");
        
        if (method_exists($this, $method)) {
            error_log("Processing field type: {$field}");
            $data = $this->$method($phpWord, $data);
            error_log("After {$field} processing, data: " . print_r($data, true));
        } else {
            error_log("Field processor not found: {$method}");
        }
    }

    error_log('=== END TEMPLATE FIELD PROCESSING ===');
    return $data;
}

   /**
    * Process date fields 
    * Format: ${date:TEXT:FORMAT}
    */
    private function process_date_field($phpWord, $data) {
        try {
            $variables = $phpWord->getVariables();
            
            foreach ($variables as $variable) {
                if (preg_match('/^date:(.*?):(.*?)$/', $variable, $matches)) {
                    $text_key = $matches[1];
                    $format = $matches[2];
                    
                    // Get date value from data or use current time
                    $date_value = isset($data[$text_key]) ? $data[$text_key] : current_time('mysql');
                    
                    // Format the date
                    $timestamp = strtotime($date_value);
                    if ($timestamp) {
                        $data[$variable] = date($format, $timestamp);
                    }
                }
            }
            
            return $data;
        } catch (Exception $e) {
            error_log('Date field processing error: ' . $e->getMessage());
            return $data;
        }
    }

       /**
        * Process user fields
        * Format: ${user:FIELD}
        */
       private function process_user_field($phpWord, $data) {
       try {
           $variables = $phpWord->getVariables();
           $current_user = wp_get_current_user();
           
           foreach ($variables as $variable) {
               if (preg_match('/^user:(.*?)$/', $variable, $matches)) {
                   $field = $matches[1];
                   $value = '';
                   
                   switch ($field) {
                       case 'name':
                           $value = $current_user->display_name;
                           break;
                       case 'email': 
                           $value = $current_user->user_email;
                           break;
                       case 'role':
                           $value = implode(', ', $current_user->roles);
                           break;
                       default:
                           if (isset($current_user->$field)) {
                               $value = $current_user->$field;
                           }
                   }
                   
                   $data[$variable] = $value;
               }
           }
           
           return $data;
       } catch (Exception $e) {
           error_log('User field processing error: ' . $e->getMessage());
           return $data;
       }
    }

   /**
    * Process image fields
    * Format: ${image:PATH:WIDTH:HEIGHT}
    */
    private function process_image_field($phpWord, $data) {
       try {
           $variables = $phpWord->getVariables();
           
           foreach ($variables as $variable) {
               if (preg_match('/^image:(.*?):(.*?):(.*?)$/', $variable, $matches)) {
                   $path = $matches[1];
                   $width = (int)$matches[2];
                   $height = (int)$matches[3];
                   
                   if (file_exists($path)) {
                       $data[$variable] = [
                           'path' => $path,
                           'width' => $width,
                           'height' => $height
                       ];
                   }
               }
           }
           
           return $data;
       } catch (Exception $e) {
           error_log('Image field processing error: ' . $e->getMessage());
           return $data;
       }
    }

       /**
        * Process site fields
        * Format: ${site:FIELD} 
        */
       private function process_site_field($phpWord, $data) {
       try {
           $variables = $phpWord->getVariables();
           
           foreach ($variables as $variable) {
               if (preg_match('/^site:(.*?)$/', $variable, $matches)) {
                   $field = $matches[1];
                   $value = '';
                   
                   switch ($field) {
                       case 'name':
                           $value = get_bloginfo('name');
                           break;
                       case 'url':
                           $value = get_bloginfo('url');
                           break;
                       case 'description':
                           $value = get_bloginfo('description');
                           break;
                       case 'admin_email':
                           $value = get_bloginfo('admin_email');
                           break;
                       case 'version':
                           $value = get_bloginfo('version');
                           break;
                   }
                   
                   $data[$variable] = $value;
               }
           }
           
           return $data;
       } catch (Exception $e) {
           error_log('Site field processing error: ' . $e->getMessage());
           return $data;
       }
    }
       
    /**
     * Process QR code fields
     * Handles ${qrcode:text:size[:error_level]} placeholders
     * 
     * @param PhpOffice\PhpWord\TemplateProcessor $templateProcessor Template processor instance
     * @param array $data Template data
     * @return array Updated template data
     */

    private function process_qrcode_field($phpWord, $data) {
        try {
            static $processed = [];
            
            require_once WP_DOCGEN_DIR . 'libs/phpqrcode/qrlib.php';
            $variables = $phpWord->getVariables();
            $cache_dir = $this->get_cache_dir();

            foreach ($variables as $variable) {
                // Skip jika variabel sudah diproses
                if (isset($processed[$variable])) {
                    continue;
                }

                if (preg_match('/^qrcode:(.*?):(.*?)(?::(.*?))?$/', $variable, $matches)) {
                    $text_key = $matches[1];
                    $size = (int)$matches[2]; 
                    $error_level = isset($matches[3]) ? strtoupper($matches[3]) : 'L';

                    // Skip jika nilai sudah berupa path file
                    if (isset($data[$variable]) && strpos($data[$variable], '.png') !== false) {
                        $processed[$variable] = true;
                        continue;
                    }

                    $text = isset($data[$text_key]) ? $data[$text_key] : '';
                    if (empty($text)) continue;

                    $cache_key = md5($text . $size . $error_level);
                    $cache_file = $cache_dir . '/' . $cache_key . '.png';

                    if (!file_exists($cache_file)) {
                        QRcode::png($text, $cache_file, $error_level, (int)($size/10), 2);
                    }

                    if (file_exists($cache_file)) {
                        $data[$variable] = $cache_file;
                        $processed[$variable] = true;
                    }
                }
            }
            return $data;
        } catch (Exception $e) {
            error_log('QR Code processing error: ' . $e->getMessage());
            return $data;
        }
    }

/*
    private function process_qrcode_field($phpWord, $data) {
        error_log('=== START QR CODE IMAGE PROCESSING ===');
        error_log('Processing QR code with data: ' . print_r($data, true));

        // Check QR library
        $qr_lib_path = WP_DOCGEN_DIR . 'libs/phpqrcode/qrlib.php';
        error_log('Looking for QR library at: ' . $qr_lib_path);
        
        if (!file_exists($qr_lib_path)) {
            error_log('QR Library not found!');
            return $data;
        }
        
        require_once $qr_lib_path;

        // Get template content
        $template = $phpWord->getMainPart()->getContent();
        
        // Find placeholders
        preg_match_all('/\$\{qrcode:(.*?):(.*?)(?::(.*?))?\}/', $template, $matches);
        error_log('Found QR code placeholders: ' . print_r($matches, true));
        
        foreach ($matches[1] as $key => $text_key) {
            try {
                $placeholder = $matches[0][$key];
                error_log("Processing placeholder: {$placeholder}");
                
                $size = min(max((int)$matches[2][$key], 50), 500);
                $error_level = isset($matches[3][$key]) ? $matches[3][$key] : 'L';
                error_log("QR Code size: {$size}, Error level: {$error_level}");
                
                // Get text from data array
                $text = isset($data[$text_key]) ? $data[$text_key] : '';
                error_log("QR Code content text: {$text}");
                
                if (empty($text)) {
                    error_log("Error: No text found for key: {$text_key}");
                    continue;
                }

                // Setup cache
                $cache_dir = $this->get_cache_dir();
                $cache_key = md5($text . $size . $error_level);
                $cache_file = $cache_dir . '/' . $cache_key . '.png';
                
                error_log('Cache directory: ' . $cache_dir);
                error_log('Cache file path: ' . $cache_file);

                // Generate QR code if not cached
                if (!file_exists($cache_file)) {
                    error_log('Cache file not found, generating new QR code...');
                    
                    // Generate QR code
                    try {
                        QRcode::png($text, $cache_file, $error_level, $size/25, 2);
                        error_log('QR Code generated successfully at: ' . $cache_file);
                        
                        // Verify file
                        if (file_exists($cache_file)) {
                            $filesize = filesize($cache_file);
                            $perms = substr(sprintf('%o', fileperms($cache_file)), -4);
                            error_log("Generated file details - Size: {$filesize} bytes, Permissions: {$perms}");
                        }
                    } catch (Exception $e) {
                        error_log('QR Code generation failed: ' . $e->getMessage());
                        continue;
                    }
                } else {
                    error_log('Using cached QR code from: ' . $cache_file);
                }

                if (file_exists($cache_file)) {
                    $data[$placeholder] = [
                        'path' => $cache_file,
                        'width' => $size,
                        'height' => $size
                    ];
                    error_log('QR Code data set in template: ' . print_r($data[$placeholder], true));
                } else {
                    error_log('Error: Generated QR code file not found!');
                }

            } catch (Exception $e) {
                error_log('QR Code Processing Error: ' . $e->getMessage());
                error_log('Error Stack Trace: ' . $e->getTraceAsString());
            }
        }

        error_log('=== END QR CODE IMAGE PROCESSING ===');
        return $data;
    }
*/


    /**
     * Get temporary directory
     * @return string
     */
    public function get_temp_dir() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/docgen-temp/qrcodes';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
            // Add index.php for security
            file_put_contents($cache_dir . '/index.php', '<?php // Silence is golden');
            // Add .htaccess
            file_put_contents($cache_dir . '/.htaccess', 'deny from all');
        }

        return $temp_dir;
    }

    /**
     * Cleanup old QR code cache files
     */
    private function cleanup_qr_cache($cache_dir) {
        // Delete files older than 24 hours
        $files = glob($cache_dir . '/*.png');
        foreach ($files as $file) {
            if (filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
    }

    /**
     * Get cache directory path
     */
    private function get_cache_dir() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/docgen-temp/qrcache';
        
        error_log('Checking cache directory: ' . $cache_dir);
        
        if (!file_exists($cache_dir)) {
            error_log('Creating cache directory...');
            $result = wp_mkdir_p($cache_dir);
            error_log('Directory creation result: ' . ($result ? 'success' : 'failed'));
            
            if ($result) {
                // Add protection files
                file_put_contents($cache_dir . '/index.php', '<?php // Silence is golden');
                file_put_contents($cache_dir . '/.htaccess', 'deny from all');
                
                // Set permissions
                chmod($cache_dir, 0755);
                error_log('Directory permissions set');
            }
        }
        
        error_log('Directory exists: ' . (file_exists($cache_dir) ? 'yes' : 'no'));
        error_log('Directory writable: ' . (is_writable($cache_dir) ? 'yes' : 'no'));
        error_log('Directory permissions: ' . substr(sprintf('%o', fileperms($cache_dir)), -4));
        
        return $cache_dir;
    }
}
