<?php
/**
* Field processing utilities 
*
* @package WP_DocGen
* @version 1.0.0
* Path: includes/class-wp-docgen-fields.php
* 
* Changelog:
* 1.0.0 - 2024-11-21 17:30 WIB
* - Field processor dan formatter
*/

if (!defined('ABSPATH')) {
   die('Direct access not permitted.');
}

class WP_DocGen_Fields {

   private $money_format;
   private $date_format;
   private $number_format;

   public function __construct() {
       $this->money_format = array(
           'decimals' => 2,
           'dec_point' => ',',
           'thousands_sep' => '.'
       );

       $this->date_format = get_option('date_format', 'Y-m-d');
       
       $this->number_format = array(
           'decimals' => 0,
           'dec_point' => ',',
           'thousands_sep' => '.'
       );
   }

   /**
    * Format money field
    */
   public function format_money($number, $currency = 'Rp') {
       $formatted = number_format(
           $number,
           $this->money_format['decimals'],
           $this->money_format['dec_point'],
           $this->money_format['thousands_sep']
       );

       return $currency . ' ' . $formatted;
   }

   /**
    * Format terbilang
    */
   public function format_terbilang($number) {
        $number = abs($number);
       	$angka = array(
               '', 'satu', 'dua', 'tiga', 'empat', 'lima',
               'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'
           );
       	$temp = '';
       	
       	if ($number < 12) {
       		$temp = ' ' . $angka[$number];
       	} else if ($number < 20) {
       		$temp = $this->format_terbilang($number - 10) . ' belas';
       	} else if ($number < 100) {
       		$temp = $this->format_terbilang($number/10) . ' puluh' . $this->format_terbilang($number % 10);
       	} else if ($number < 200) {
       		$temp = ' seratus' . $this->format_terbilang($number - 100);
       	} else if ($number < 1000) {
       		$temp = $this->format_terbilang($number/100) . ' ratus' . $this->format_terbilang($number % 100);
       	} else if ($number < 2000) {
       		$temp = ' seribu' . $this->format_terbilang($number - 1000);
       	} else if ($number < 1000000) {
       		$temp = $this->format_terbilang($number/1000) . ' ribu' . $this->format_terbilang($number % 1000);
       	} else if ($number < 1000000000) {
       		$temp = $this->format_terbilang($number/1000000) . ' juta' . $this->format_terbilang($number % 1000000);
       	} else if ($number < 1000000000000) {
       		$temp = $this->format_terbilang($number/1000000000) . ' milyar' . $this->format_terbilang(fmod($number,1000000000));
       	} else if ($number < 1000000000000000) {
       		$temp = $this->format_terbilang($number/1000000000000) . ' trilyun' . $this->format_terbilang(fmod($number,1000000000000));
       	}     
       	
           return trim($temp);
   }

   /**
    * Format tanggal ke Indonesia
    */
   public function format_tanggal($date, $format = 'j F Y') {
       $date = date_create($date);
       if (!$date) return '';

       $bulan = array(
           'January' => 'Januari',
           'February' => 'Februari', 
           'March' => 'Maret',
           'April' => 'April',
           'May' => 'Mei',
           'June' => 'Juni',
           'July' => 'Juli',
           'August' => 'Agustus',
           'September' => 'September',
           'October' => 'Oktober',
           'November' => 'November',
           'December' => 'Desember'
       );

       $hari = array(
           'Sunday' => 'Minggu',
           'Monday' => 'Senin',
           'Tuesday' => 'Selasa', 
           'Wednesday' => 'Rabu',
           'Thursday' => 'Kamis',
           'Friday' => 'Jumat',
           'Saturday' => 'Sabtu'
       );

       $formatted = date_format($date, $format);
       $formatted = strtr($formatted, $bulan);
       $formatted = strtr($formatted, $hari);

       return $formatted;
   }

   /**
    * Format number
    */
   public function format_number($number) {
       return number_format(
           $number,
           $this->number_format['decimals'],
           $this->number_format['dec_point'], 
           $this->number_format['thousands_sep']
       );
   }

   /**
    * Format nama gelar
    */
   public function format_gelar($nama, $gelar_depan = '', $gelar_belakang = '') {
       $formatted = '';

       if (!empty($gelar_depan)) {
           $formatted .= $gelar_depan . ' ';
       }

       $formatted .= $nama;

       if (!empty($gelar_belakang)) {
           $formatted .= ', ' . $gelar_belakang;
       }

       return $formatted;
   }

   /**
    * Format alamat
    */
   public function format_alamat($alamat, $options = array()) {
       $defaults = array(
           'kecamatan' => '',
           'kabupaten' => '',
           'provinsi' => '',
           'kode_pos' => '',
           'separator' => ', '
       );

       $options = wp_parse_args($options, $defaults);
       $parts = array();

       if (!empty($alamat)) {
           $parts[] = $alamat;
       }

       if (!empty($options['kecamatan'])) {
           $parts[] = 'Kec. ' . $options['kecamatan']; 
       }

       if (!empty($options['kabupaten'])) {
           $parts[] = $options['kabupaten'];
       }

       if (!empty($options['provinsi'])) {
           $parts[] = $options['provinsi'];
       }

       if (!empty($options['kode_pos'])) {
           $parts[] = $options['kode_pos'];
       }

       return implode($options['separator'], array_filter($parts));
   }

   /**
    * Format custom field
    * Untuk custom field dengan format ${field_type:value:options}
    */
   public function format_field($field_string) {
       // Extract field parts
       preg_match('/\$\{(.*?):(.*?)(?::(.*?))?\}/', $field_string, $matches);
       
       if (count($matches) < 3) {
           return $field_string;
       }

       $type = $matches[1];
       $value = $matches[2];
       $options = isset($matches[3]) ? $matches[3] : '';

       switch($type) {
           case 'money':
               return $this->format_money($value, $options);
           
           case 'terbilang':
               return $this->format_terbilang($value);
               
           case 'tanggal':
               return $this->format_tanggal($value, $options);
               
           case 'number':
               return $this->format_number($value);
               
           case 'gelar':
               $gelar = explode('|', $options);
               return $this->format_gelar(
                   $value,
                   isset($gelar[0]) ? $gelar[0] : '',
                   isset($gelar[1]) ? $gelar[1] : ''
               );
               
           default:
               return $value;
       }
   }
}
