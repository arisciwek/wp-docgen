<?php
/**
 * Document processor class
 * 
 * @package     WP_DocGen
 * @subpackage  Includes
 * @version     1.0.2
 * @author      arisciwek
 * 
 * Path: includes/class-wp-docgen-processor.php
 * 
 * Description:
 * Class utama untuk pemrosesan dokumen template.
 * Menghandle konversi template ke dokumen final dengan data yang diberikan.
 * Mendukung konversi ke berbagai format output (DOCX, PDF).
 * 
 * Changelog:
 * 1.0.2 - 2024-12-27 20:07 WIB
 * - Added proper image handling
 * - Fixed template processing methods
 * - Improved error handling
 * 
 * 1.0.1 - 2024-11-24
 * - Added PDF conversion support
 * - Added template validation
 * 
 * 1.0.0 - 2024-11-21 16:45 WIB
 * - Initial release
 */

if (!defined('ABSPATH')) {
   die('Direct access not permitted.');
}

class WP_DocGen_Processor {
    
    private $template;

    public function __construct() {
        $this->template = new WP_DocGen_Template();
    }
    
   /**
    * Generate document dari provider
    */
   public function generate(WP_DocGen_Provider $provider) {
       try {
           // Validate provider data
           $data = $this->validate_data($provider->get_data());
           if (is_wp_error($data)) {
               return $data;
           }

           // Validate template
           $template_path = $provider->get_template_path();
           if (!file_exists($template_path)) {
               return new WP_Error(
                   'template_not_found',
                   __('Template file not found', 'wp-docgen')
               );
           }

           // Create temp copy of template
           $temp_path = $this->create_temp_copy($template_path, $provider);
           if (is_wp_error($temp_path)) {
               return $temp_path; 
           }

           // Load required PHPWord classes if not loaded
           $required_classes = array(
               'Exception' => WP_DOCGEN_DIR . 'libs/phpword/src/PhpOffice/PhpWord/Exception/Exception.php',
               'TemplateProcessor' => WP_DOCGEN_DIR . 'libs/phpword/src/PhpOffice/PhpWord/TemplateProcessor.php',
               'Settings' => WP_DOCGEN_DIR . 'libs/phpword/src/PhpOffice/PhpWord/Settings.php',
               'IOFactory' => WP_DOCGEN_DIR . 'libs/phpword/src/PhpOffice/PhpWord/IOFactory.php'
           );

           foreach ($required_classes as $class => $path) {
               if (!class_exists('PhpOffice\\PhpWord\\' . $class) && file_exists($path)) {
                   require_once $path;
               }
           }

           // Process template
           $output_path = $this->process_template($temp_path, $data, $provider);
           
           // Cleanup temp file
           @unlink($temp_path);
           
           return $output_path;

       } catch (Exception $e) {
           return new WP_Error(
               'processing_failed',
               $e->getMessage()
           );
       }
   }

   /**
    * Validate data dari provider
    */
   private function validate_data($data) {
       if (!is_array($data)) {
           return new WP_Error(
               'invalid_data',
               __('Provider data must be an array', 'wp-docgen')
           );
       }

       // Sanitize data values
       $clean_data = array();
       foreach ($data as $key => $value) {
           if (is_string($value)) {
               $clean_data[$key] = wp_kses_post($value);
           } else {
               $clean_data[$key] = $value;
           }
       }

       return $clean_data;
   }

   /**
    * Create temporary copy of template
    */
   private function create_temp_copy($template_path, $provider) {
       $temp_dir = $provider->get_temp_dir();
       
       if (!is_dir($temp_dir) || !wp_is_writable($temp_dir)) {
           return new WP_Error(
               'invalid_temp_dir',
               __('Invalid or non-writable temporary directory', 'wp-docgen')
           );
       }

       $temp_file = $temp_dir . '/' . wp_unique_filename($temp_dir, basename($template_path));
       
       if (!copy($template_path, $temp_file)) {
           return new WP_Error(
               'copy_failed',
               __('Failed to create temporary template copy', 'wp-docgen')
           );
       }

       return $temp_file;
   }

   /**
    * Process template dengan PHPWord
    */

    private function get_output_path($provider) {
        return $provider->get_temp_dir() . '/' . 
               sanitize_file_name($provider->get_output_filename()) . '.' . 
               $provider->get_output_format();
    }

    /**
     * Process template document with provided data using PHPWord.
     * 
     * Handles the main template processing workflow:
     * 1. Initializes PHPWord template processor
     * 2. Processes all field types (date, image, QR code, etc) through WP_DocGen_Template
     * 3. Sets processed values back to the template
     * 4. Saves the final document
     * 
     * Field Types Supported:
     * - Text fields: Using setValue()
     * - Image fields: Using setImageValue() for both images and QR codes
     * - Date fields: Processed and formatted as text
     * - User fields: Processed from WP user data
     * - Site fields: Processed from WP site data
     * 
     * @since 1.0.0
     * @access private
     * 
     * @param string $template_path     Full path to template file (DOCX/ODT)
     * @param array  $data             Data array containing field values
     * @param WP_DocGen_Provider $provider Provider instance for output settings
     * 
     * @throws Exception If template processing fails
     * @return string|WP_Error Path to generated document or WP_Error on failure
     * 
     * @example
     * // Basic usage:
     * $output = $this->process_template(
     *     '/path/to/template.docx',
     *     ['company_name' => 'ACME Corp'],
     *     $provider
     * );
     * 
     * // With image:
     * $data = [
     *     'image:logo' => '/path/to/logo.png',
     *     'date:issue_date' => '2024-12-28'
     * ];
     * $output = $this->process_template($template_path, $data, $provider);
     */
    private function process_template($template_path, $data, $provider) {
        try {
            $phpWord = new \PhpOffice\PhpWord\TemplateProcessor($template_path);
            $processed_data = $this->template->process_fields($phpWord, $data);

            foreach ($processed_data as $key => $value) {
                if ($this->is_image_field($key, $value)) {
                    // Gunakan setImageValue untuk gambar
                    $phpWord->setImageValue($key, $value);
                } else {
                    // Gunakan setValue untuk teks biasa
                    $phpWord->setValue($key, $value);
                }
            }

            $output_path = $this->get_output_path($provider);
            $phpWord->saveAs($output_path);
            return $output_path;

        } catch (Exception $e) {
            error_log('Template processing error: ' . $e->getMessage());
            return new WP_Error('processing_failed', $e->getMessage());
        }
    }

    private function is_image_field($key, $value) {
        return (
            (strpos($key, 'image:') === 0 || strpos($key, 'qrcode:') === 0) && 
            is_array($value) && 
            isset($value['path']) && 
            file_exists($value['path'])
        );
    }

    private function process_image($phpWord, $key, $value) {
        try {
            $parts = explode(':', $key);
            $type = $parts[0]; // 'image' atau 'qrcode'
            
            // Set ukuran dan parameter berdasarkan tipe
            if ($type === 'image') {
                $width = isset($parts[2]) ? (int)$parts[2] : 100;
                $height = isset($parts[3]) ? (int)$parts[3] : $width;
                
                $image_params = [
                    'path' => $value,
                    'width' => $width,
                    'height' => $height,
                    'ratio' => true,
                    'dpi' => 300
                ];
                
            } else { // qrcode
                $width = isset($parts[2]) ? (int)$parts[2] : 100;
                $error_level = isset($parts[3]) ? strtoupper($parts[3]) : 'L';
                
                $image_params = [
                    'path' => $value,
                    'width' => $width,
                    'height' => $width, // QR Code selalu square
                    'ratio' => true,
                    'dpi' => 300
                ];
            }

            // Jika output adalah PDF, tambahkan parameter khusus PDF
            if ($this->is_pdf_output()) {
                $image_params = array_merge($image_params, [
                    'wrappingStyle' => 'inline',
                    'positioning' => 'relative',
                    'marginLeft' => 0,
                    'marginTop' => 0
                ]);
            }

            error_log(sprintf(
                "Processing %s - Key: %s, Path: %s, Params: %s",
                $type,
                $key,
                $value,
                json_encode($image_params)
            ));

            $phpWord->setImageValue($key, $image_params);
            error_log("Successfully set image {$key}");
            
        } catch (Exception $e) {
            error_log("Image processing error for {$key}: " . $e->getMessage());
        }
    }

    // Helper untuk cek apakah output adalah PDF
    private function is_pdf_output() {
        return (
            (isset($_POST['format']) && $_POST['format'] === 'pdf') || 
            (defined('DOING_PDF_CONVERSION') && DOING_PDF_CONVERSION === true)
        );
    }

   /**
    * Convert document to PDF using PDF converter
    */
   private function convert_to_pdf($docx_path) {
       if (!class_exists('COM')) {
           return new WP_Error(
               'pdf_conversion_failed',
               __('PDF conversion requires COM support in PHP', 'wp-docgen')
           );
       }

       try {
           $word = new COM("Word.Application") or die("Could not initialise MS Word object.");
           $word->Documents->Open($docx_path);
           
           $pdf_path = str_replace('.docx', '.pdf', $docx_path);
           
           $word->ActiveDocument->ExportAsFixedFormat(
               $pdf_path,
               17 // PDF format
           );
           
           $word->ActiveDocument->Close();
           $word->Quit();
           
           unset($word);
           
           return $pdf_path;

       } catch (Exception $e) {
           return new WP_Error(
               'pdf_conversion_failed',
               $e->getMessage()
           );
       }
   }
}
