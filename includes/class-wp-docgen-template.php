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
 * - ${date:FORMAT}                   : Format tanggal (Y-m-d, d/m/Y, dll)
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
 * 1.0.2 - 2024-12-25
 * - Added QR code caching system
 * - Enhanced error handling
 * - Added security features
 * - Improved QR code positioning
 * 
 * 1.0.1 - 2024-11-24
 * - Added support for error_level parameter
 * - Fixed image alignment issues
 * - Added cache cleanup
 * 
 * 1.0.0 - 2024-11-21
 * - Initial release with basic field support
 * - Added date, user, image processing
 * - Basic QR code implementation
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

   /**
    * Process custom fields dalam template
    */
   public function process_fields($phpWord, $data) {
       foreach ($this->supported_fields as $field) {
           $method = "process_{$field}_field";
           if (method_exists($this, $method)) {
               $data = $this->$method($phpWord, $data);
           }
       }
       return $data;
   }

   /**
    * Process date fields 
    * Format: ${date:FORMAT}
    */
   private function process_date_field($phpWord, $data) {
       $template = $phpWord->getMainPart()->getContent();
       
       // Find date variables
       preg_match_all('/\$\{date:(.*?)\}/', $template, $matches);
       
       foreach ($matches[1] as $key => $format) {
           $var_name = $matches[0][$key];
           $formatted_date = date($format);
           $data[$var_name] = $formatted_date;
       }

       return $data;
   }

   /**
    * Process user fields
    * Format: ${user:FIELD}
    */
   private function process_user_field($phpWord, $data) {
       $template = $phpWord->getMainPart()->getContent();
       
       // Find user variables
       preg_match_all('/\$\{user:(.*?)\}/', $template, $matches);
       
       $current_user = wp_get_current_user();

       foreach ($matches[1] as $key => $field) {
           $var_name = $matches[0][$key];
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

           $data[$var_name] = $value;
       }

       return $data;
   }

   /**
    * Process image fields
    * Format: ${image:PATH:WIDTH:HEIGHT}
    */
   private function process_image_field($phpWord, $data) {
       $template = $phpWord->getMainPart()->getContent();
       
       // Find image variables
       preg_match_all('/\$\{image:(.*?):(.*?):(.*?)\}/', $template, $matches);

       foreach ($matches[1] as $key => $path) {
           $var_name = $matches[0][$key];
           $width = (int)$matches[2][$key];
           $height = (int)$matches[3][$key];

           if (file_exists($path)) {
               $section = $phpWord->addSection();
               $section->addImage(
                   $path,
                   array(
                       'width' => $width,
                       'height' => $height,
                       'alignment' => 'center'
                   )
               );
           }

           $data[$var_name] = '';
       }

       return $data;
   }

   /**
    * Process site fields
    * Format: ${site:FIELD} 
    */
   private function process_site_field($phpWord, $data) {
       $template = $phpWord->getMainPart()->getContent();
       
       // Find site variables
       preg_match_all('/\$\{site:(.*?)\}/', $template, $matches);

       foreach ($matches[1] as $key => $field) {
           $var_name = $matches[0][$key];
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

           $data[$var_name] = $value;
       }

       return $data;
   }
   
    /**
     * Process QR code fields
     * Handles ${qrcode:text:size[:error_level]} placeholders
     * 
     * @param PhpOffice\PhpWord\PhpWord $phpWord PHPWord instance
     * @param array $data Template data
     * @return array Updated template data
     */
    private function process_qrcode_field($phpWord, $data) {
        // Ensure QR library is available
        if (!file_exists(WP_DOCGEN_DIR . 'libs/phpqrcode/qrlib.php')) {
            error_log('WP DocGen: QR Code library not found');
            return $data;
        }
        
        require_once WP_DOCGEN_DIR . 'libs/phpqrcode/qrlib.php';
        
        // Get template content
        $template = $phpWord->getMainPart()->getContent();
        
        // Match ${qrcode:text:size[:error_level]} pattern
        preg_match_all('/\$\{qrcode:(.*?):(.*?)(?::(.*?))?\}/', $template, $matches);
        
        // Create cache directory if not exists
        $upload_dir = wp_upload_dir();
        $cache_dir = trailingslashit($upload_dir['basedir']) . 'wp-docgen/qrcache';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
            // Add index.php for security
            file_put_contents($cache_dir . '/index.php', '<?php // Silence is golden');
            // Add .htaccess
            file_put_contents($cache_dir . '/.htaccess', 'deny from all');
        }

        // Process each QR code placeholder
        foreach ($matches[1] as $key => $text) {
            try {
                $var_name = $matches[0][$key];
                $size = min(max((int)$matches[2][$key], 50), 500); // Size between 50-500px
                $error_level = isset($matches[3][$key]) ? $matches[3][$key] : 'L';
                
                // Validate error level
                if (!in_array($error_level, ['L', 'M', 'Q', 'H'])) {
                    $error_level = 'L';
                }
                
                // Generate cache filename based on content and parameters
                $cache_key = md5($text . $size . $error_level);
                $cache_file = $cache_dir . '/' . $cache_key . '.png';
                
                // Generate QR code if not in cache
                if (!file_exists($cache_file)) {
                    // Create temp file first
                    $temp_file = $cache_dir . '/temp_' . wp_unique_filename($cache_dir, 'qr.png');
                    
                    // Generate QR code with error handling
                    if (!QRcode::png($text, $temp_file, $error_level, $size/25, 2)) {
                        throw new Exception('Failed to generate QR code');
                    }
                    
                    // Move to cache if generation successful
                    rename($temp_file, $cache_file);
                }
                
                // Add image to document
                if (file_exists($cache_file)) {
                    // Get current section
                    $section = $phpWord->addSection();
                    
                    // Add image with proper styling
                    $section->addImage(
                        $cache_file,
                        array(
                            'width' => $size,
                            'height' => $size,
                            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END,
                            'wrappingStyle' => 'inline',
                            'positioning' => 'relative',
                            'marginLeft' => 1,
                            'marginTop' => 1
                        )
                    );
                    
                    // Clear placeholder
                    $data[$var_name] = '';
                } else {
                    throw new Exception('QR code file not found after generation');
                }
                
            } catch (Exception $e) {
                error_log('WP DocGen QR Code Error: ' . $e->getMessage());
                $data[$var_name] = '[QR Code Error]';
            }
        }
        
        // Cleanup old cache files (older than 24 hours)
        $files = glob($cache_dir . '/*.png');
        foreach ($files as $file) {
            if (filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }

        return $data;
    }

}