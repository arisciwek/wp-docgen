<?php
/**
* Document template handling dan custom fields
*
* @package WP_DocGen
* @version 1.0.0
* Path: includes/class-wp-docgen-template.php
* 
* Changelog:
* 1.0.0 - 2024-11-21 17:15 WIB
* - Initial release with custom field support
* - Add date, user dan image field processing
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
    * Format: ${qrcode:TEXT:SIZE}
    */
   private function process_qrcode_field($phpWord, $data) {
       $template = $phpWord->getMainPart()->getContent();
       
       if (!class_exists('QRcode')) {
           require_once WP_DOCGEN_DIR . 'libs/phpqrcode/qrlib.php';
       }

       // Find QR code variables
       preg_match_all('/\$\{qrcode:(.*?):(.*?)\}/', $template, $matches);

       foreach ($matches[1] as $key => $text) {
           $var_name = $matches[0][$key];
           $size = (int)$matches[2][$key];

           // Generate QR code
           $temp_file = tempnam(sys_get_temp_dir(), 'qr');
           QRcode::png($text, $temp_file, QR_ECLEVEL_L, $size);

           if (file_exists($temp_file)) {
               $section = $phpWord->addSection();
               $section->addImage(
                   $temp_file,
                   array(
                       'width' => $size,
                       'height' => $size,
                       'alignment' => 'center'
                   )
               );
               unlink($temp_file);
           }

           $data[$var_name] = '';
       }

       return $data;
   }

}