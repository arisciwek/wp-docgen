<?php
/**
* Document processor class
* 
* @package WP_DocGen
* @version 1.0.0
* Path: includes/class-wp-docgen-processor.php
* 
* Changelog:
* 1.0.0 - 2024-11-21 16:45 WIB
* - Initial release with template processing
* - Support DOCX/ODT reading and writing
* - Temporary file handling
*/

if (!defined('ABSPATH')) {
   die('Direct access not permitted.');
}

class WP_DocGen_Processor {

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
   private function process_template($template_path, $data, $provider) {
       $template_ext = pathinfo($template_path, PATHINFO_EXTENSION);
       $output_format = $provider->get_output_format();
       
       // Initialize PHPWord
       $phpWord = new \PhpOffice\PhpWord\TemplateProcessor($template_path);
       
       // Replace variables in template
       foreach ($data as $key => $value) {
           if (is_array($value)) {
               $phpWord->cloneRowAndSetValues($key, $value);
           } else {
               $phpWord->setValue($key, $value);
           }
       }

       // Generate output filename
       $output_filename = $provider->get_output_filename();
       if (empty($output_filename)) {
           $output_filename = 'document-' . time();
       }
       
       $output_path = $provider->get_temp_dir() . '/' . 
                     sanitize_file_name($output_filename) . '.' . $output_format;

       // Save output file
       $phpWord->saveAs($output_path);

       if ($output_format === 'pdf') {
           return $this->convert_to_pdf($output_path);
       }

       return $output_path;
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