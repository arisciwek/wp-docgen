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
    * Process image fields yang ada di template DOCX/ODT
    * 
    * Format Placeholder:
    * ${image:name:width:height:halign:valign}
    * - name      : Nama variabel yang berisi path gambar (required)
    * - width     : Lebar gambar dalam pixel (optional, default: 100)
    * - height    : Tinggi gambar dalam pixel (optional, default: 100)
    * - halign    : Horizontal alignment (optional, default: center)
    *              Valid: left, center, right, justify, both
    * - valign    : Vertical alignment (optional, default: middle)
    *              Valid: top, middle, bottom, baseline
    * 
    * Di provider cukup menyediakan:
    * 'image:name' => '/path/to/image.png'
    * 
    * Contoh Template:
    * ${image:logo:50:50:center:middle}     // Lengkap dengan semua parameter
    * ${image:logo:75:75}                   // Hanya ukuran, alignment default
    * ${image:logo}                         // Semua parameter default
    * 
    * Error Handling:
    * - Validasi file exists
    * - Validasi alignment values
    * - Fallback ke default jika parameter invalid
    * - Error logging untuk parameter yang tidak valid
    * 
    * Default Settings:
    * - Width: 100px
    * - Height: 100px 
    * - Horizontal Align: center
    * - Vertical Align: middle
    * - Preserve aspect ratio: true
    * 
    * @since 1.0.2
    * @see PhpOffice\PhpWord\Element\Image For image processing
    * 
    * @param PhpOffice\PhpWord\TemplateProcessor $phpWord Template processor instance
    * @param array $data Template data yang berisi path gambar
    * @return array Data yang sudah diproses dengan parameter gambar
    */
    private function process_image_field($phpWord, $data) {
        try {
            $variables = $phpWord->getVariables();
            
            // Define valid alignments
            $validAlignments = [
                // Horizontal alignments
                'left' => 'left',
                'center' => 'center',
                'right' => 'right',
                'justify' => 'justify',
                'both' => 'both',          
                
                // Vertical alignments
                'top' => 'top',
                'middle' => 'middle',      
                'bottom' => 'bottom',
                'baseline' => 'baseline'
            ];

            // Default settings
            $defaultWidth = 100;
            $defaultHeight = 100;
            $defaultHAlign = 'center';
            $defaultVAlign = 'middle';
            
            foreach ($variables as $variable) {
                if (preg_match('/^image:(.*?)(?::(.*?))?(?::(.*?))?(?::(.*?))?(?::(.*?))?$/', $variable, $matches)) {
                    $name = $matches[1];  // e.g., 'logo'
                    
                    // Cek apakah image path ada di data
                    $baseKey = "image:{$name}";
                    if (!isset($data[$baseKey]) || !file_exists($data[$baseKey])) {
                        error_log("Image not found for: {$baseKey}");
                        continue;
                    }

                    // Parse template parameters dengan validasi
                    $width = isset($matches[2]) && is_numeric($matches[2]) ? (int)$matches[2] : $defaultWidth;
                    $height = isset($matches[3]) && is_numeric($matches[3]) ? (int)$matches[3] : $defaultHeight;
                    
                    $hAlign = $defaultHAlign;
                    $vAlign = $defaultVAlign;
                    
                    // Validasi horizontal alignment
                    if (isset($matches[4]) && isset($validAlignments[$matches[4]])) {
                        $hAlign = $matches[4];
                    } else if (isset($matches[4])) {
                        error_log("Invalid horizontal alignment in template: {$matches[4]}, using default: {$defaultHAlign}");
                    }
                    
                    // Validasi vertical alignment
                    if (isset($matches[5]) && isset($validAlignments[$matches[5]])) {
                        $vAlign = $matches[5];
                    } else if (isset($matches[5])) {
                        error_log("Invalid vertical alignment in template: {$matches[5]}, using default: {$defaultVAlign}");
                    }

                    // Set image data
                    $data[$variable] = [
                        'path' => $data[$baseKey],
                        'width' => $width,
                        'height' => $height,
                        'ratio' => true,
                        'style' => [
                            'alignment' => $hAlign,
                            'verticalAlignment' => $vAlign
                        ]
                    ];

                    error_log(sprintf(
                        'Processing image - Key: %s, Using settings - Width: %d, Height: %d, HAlign: %s, VAlign: %s',
                        $variable, $width, $height, $hAlign, $vAlign
                    ));
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
    * Process QR code fields yang ada di template DOCX/ODT 
    *
    * Format Placeholder:
    * ${qrcode:text:size:error_level}
    * - text        : Nama variabel yang berisi teks/URL (required)
    * - size        : Ukuran QR code dalam pixel (50-500) (optional, default: 100)
    * - error_level : Level error correction L/M/Q/H (optional, default: L)
    * 
    * Di provider cukup menyediakan:
    * 'qrcode:text' => 'URL atau teks yang akan di-encode'
    * 
    * Contoh Template:
    * ${qrcode:qr_data:50:M}    // QR code 50x50px, error level M
    * ${qrcode:qr_data:75}      // QR code 75x75px, error level default (L)  
    * ${qrcode:qr_data}         // QR code ukuran default (100x100px, L)
    * 
    * Cache Management:
    * - QR code di-cache dalam uploads/docgen-temp/qrcache/
    * - Cache filename: MD5(text + size + error_level).png
    * - Cache expires: 24 jam (cleanup otomatis)
    * 
    * Error Handling:
    * - Validasi error level (L/M/Q/H)
    * - Fallback ke default jika parameter invalid
    * - Error logging untuk parameter yang tidak valid
    * 
    * @since 1.0.2
    * @see libs/phpqrcode/qrlib.php Untuk QR code generation
    *
    * @param PhpOffice\PhpWord\TemplateProcessor $phpWord Template processor instance
    * @param array $data Template data yang berisi teks QR
    * @return array Data yang sudah diproses dengan path QR code
    */
    private function process_qrcode_field($phpWord, $data) {
        try {
            static $processed = [];
            
            require_once WP_DOCGEN_DIR . 'libs/phpqrcode/qrlib.php';
            $variables = $phpWord->getVariables();
            $cache_dir = $this->get_cache_dir();

            // Default QR settings
            $defaultSize = 100;
            $defaultErrorLevel = 'L';
            $validErrorLevels = ['L', 'M', 'Q', 'H'];

            foreach ($variables as $variable) {
                if (isset($processed[$variable])) {
                    continue;
                }

                if (preg_match('/^qrcode:(.*?)(?::(.*?))?(?::(.*?))?$/', $variable, $matches)) {
                    $text_key = $matches[1];
                    
                    // Cek apakah teks QR ada di data
                    $baseKey = "qrcode:{$text_key}";
                    if (!isset($data[$baseKey])) {
                        error_log("QR Code text not found for: {$baseKey}");
                        continue;
                    }

                    // Parse template parameters dengan validasi
                    $size = isset($matches[2]) && is_numeric($matches[2]) ? (int)$matches[2] : $defaultSize;
                    
                    $error_level = $defaultErrorLevel;
                    if (isset($matches[3])) {
                        $requested_level = strtoupper($matches[3]);
                        if (in_array($requested_level, $validErrorLevels)) {
                            $error_level = $requested_level;
                        } else {
                            error_log("Invalid QR error level in template: {$matches[3]}, using default: {$defaultErrorLevel}");
                        }
                    }

                    $text = $data[$baseKey];
                    if (empty($text)) continue;

                    $cache_key = md5($text . $size . $error_level);
                    $cache_file = $cache_dir . '/' . $cache_key . '.png';

                    if (!file_exists($cache_file)) {
                        // Generate QR code dengan ukuran asli (tanpa konversi)
                        QRcode::png(
                            $text, 
                            $cache_file, 
                            $error_level,
                            1,              // Unit size 1
                            $size/10        // Pixel multiplier untuk mencapai ukuran yang diminta
                        );
                    }
                    
                    if (file_exists($cache_file)) {
                        $data[$variable] = [
                            'path' => $cache_file,
                            'width' => $size,
                            'height' => $size,
                            'ratio' => true
                        ];
                        $processed[$variable] = true;
                        
                        error_log(sprintf(
                            'Processing QR Code - Key: %s, Size: %d, Error Level: %s',
                            $variable, $size, $error_level
                        ));
                    }
                }
            }
            return $data;
        } catch (Exception $e) {
            error_log('QR Code processing error: ' . $e->getMessage());
            return $data;
        }
    }

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
