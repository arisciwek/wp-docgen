<?php
/**
 * Interface untuk plugin yang ingin menggunakan WP DocGen
 *
 * @package WP_DocGen
 * @version 1.0.0
 * Path: includes/interfaces/interface-wp-docgen-provider.php
 * 
 * Changelog:
 * 1.0.0 - 2024-11-21 16:20 WIB
 * - Initial interface definition
 */

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}

interface WP_DocGen_Provider {
    /**
     * Get data untuk dokumen
     * Data akan di-merge dengan template
     * 
     * @return array Array associative berisi data
     */
    public function get_data();

    /**
     * Get path ke template file
     * Template bisa berupa DOCX atau ODT
     * 
     * @return string Full path ke template file
     */
    public function get_template_path();
    
    /**
     * Get output filename
     * Nama file untuk dokumen yang akan di-generate
     * Extension akan ditambahkan otomatis sesuai format
     * 
     * @return string Nama file output (tanpa extension)
     */
    public function get_output_filename();

    /**
     * Get output format
     * Format yang didukung: docx, odt, pdf
     * 
     * @return string Format output ('docx'|'odt'|'pdf') 
     */  
    public function get_output_format();

    /**
     * Get temporary directory
     * Directory untuk menyimpan file sementara saat proses
     * Harus writable
     * 
     * @return string Full path ke temporary directory
     */
    public function get_temp_dir();
}