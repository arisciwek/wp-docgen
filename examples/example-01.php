<?php

class My_Document implements WP_DocGen_Provider {
    public function get_data() {
        return [
            'company_name' => 'PT ABC',
            'date' => current_time('mysql')
        ];
    }

    public function get_template_path() {
        return plugin_dir_path(__FILE__) . 'templates/document.docx';
    }
    
    public function get_output_filename() {
        return 'generated-doc-' . time();  
    }

    public function get_output_format() {
        return 'docx';
    }

    public function get_temp_dir() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/wp-docgen-temp';
    }
}

// Generate document
$provider = new My_Document();
$result = wp_docgen()->generate($provider);

if (!is_wp_error($result)) {
    $doc_path = $result;
    // Handle generated document
}