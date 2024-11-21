# WP DocGen Hooks Documentation

## Overview
WP DocGen menyediakan sistem hook yang memungkinkan plugin lain untuk:
1. Meregistrasi template dokumen
2. Menyediakan data untuk dokumen
3. Menangani file output
4. Kustomisasi proses generate dokumen
5. Memodifikasi custom fields

## Filter Hooks

### Template Registration
```php
// Register template path
add_filter('wp_docgen_template_path', function($template_path, $template_id) {
    if ($template_id === 'my-plugin-template') {
        return plugin_dir_path(__FILE__) . 'templates/my-template.docx';
    }
    return $template_path;
}, 10, 2);

// Register template data
add_filter('wp_docgen_template_data', function($data, $template_id) {
    if ($template_id === 'my-plugin-template') {
        return [
            'title' => 'Document Title',
            'content' => 'Document content...'
        ];
    }
    return $data;
}, 10, 2);
```

### Output Handling
```php
// Custom output path
add_filter('wp_docgen_output_path', function($output_path, $template_id) {
    if ($template_id === 'my-plugin-template') {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/my-plugin/documents/';
    }
    return $output_path;
}, 10, 2);

// Custom filename
add_filter('wp_docgen_output_filename', function($filename, $template_id) {
    if ($template_id === 'my-plugin-template') {
        return 'custom-doc-' . time();
    }
    return $filename;
}, 10, 2);
```

### Custom Fields
```php
// Add custom field type
add_filter('wp_docgen_custom_fields', function($fields) {
    $fields['my_field'] = [
        'callback' => 'my_plugin_process_field',
        'pattern' => '/\$\{my_field:(.*?)\}/'
    ];
    return $fields;
});

// Process custom field
function my_plugin_process_field($matches, $phpWord) {
    // Process field logic here
    return $processed_value;
}
```

### QR Code Customization
```php
// Customize QR code generation
add_filter('wp_docgen_qrcode_options', function($options, $text) {
    return [
        'size' => 300,
        'margin' => 10,
        'level' => 'L'
    ];
}, 10, 2);
```

## Action Hooks

### Processing Events
```php
// Before document generation
add_action('wp_docgen_before_generate', function($template_id, $data) {
    // Do something before generation
}, 10, 2);

// After document generation
add_action('wp_docgen_after_generate', function($output_path, $template_id) {
    // Do something with generated document
}, 10, 2);

// On generation error
add_action('wp_docgen_generation_error', function($error, $template_id) {
    // Handle error
}, 10, 2);
```

### Document Events
```php
// Document saved
add_action('wp_docgen_document_saved', function($doc_path, $template_id) {
    // Document saved successfully
}, 10, 2);

// Before PDF conversion
add_action('wp_docgen_before_pdf_conversion', function($docx_path) {
    // Do something before PDF conversion
});
```

## Usage Example

```php
// In your plugin:

function my_plugin_generate_document($user_id) {
    // 1. Register your template
    add_filter('wp_docgen_template_path', 'my_plugin_template_path', 10, 2);
    
    // 2. Prepare your data
    add_filter('wp_docgen_template_data', 'my_plugin_template_data', 10, 2);
    
    // 3. Set output location
    add_filter('wp_docgen_output_path', 'my_plugin_output_path', 10, 2);
    
    // 4. Generate document
    $result = wp_docgen()->generate('my-plugin-template', [
        'user_id' => $user_id
    ]);
    
    if (!is_wp_error($result)) {
        // Document generated successfully
        do_action('my_plugin_document_generated', $result);
    }
}

// Template path callback
function my_plugin_template_path($path, $template_id) {
    if ($template_id === 'my-plugin-template') {
        return plugin_dir_path(__FILE__) . 'templates/document.docx';
    }
    return $path;
}

// Template data callback
function my_plugin_template_data($data, $template_id) {
    if ($template_id === 'my-plugin-template') {
        $user_id = $data['user_id'];
        $user = get_userdata($user_id);
        
        return [
            'name' => $user->display_name,
            'email' => $user->user_email,
            'date' => current_time('mysql'),
            'qr_data' => "USER:{$user_id}"
        ];
    }
    return $data;
}

// Output path callback
function my_plugin_output_path($path, $template_id) {
    if ($template_id === 'my-plugin-template') {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/my-plugin/documents/';
    }
    return $path;
}
```

## Hook Reference

### Filters
| Hook Name | Parameters | Description |
|-----------|------------|-------------|
| wp_docgen_template_path | (string $path, string $template_id) | Set template file path |
| wp_docgen_template_data | (array $data, string $template_id) | Modify template data |
| wp_docgen_output_path | (string $path, string $template_id) | Set output directory |
| wp_docgen_output_filename | (string $filename, string $template_id) | Set output filename |
| wp_docgen_custom_fields | (array $fields) | Register custom fields |
| wp_docgen_qrcode_options | (array $options, string $text) | Modify QR code options |

### Actions
| Hook Name | Parameters | Description |
|-----------|------------|-------------|
| wp_docgen_before_generate | (string $template_id, array $data) | Before generation starts |
| wp_docgen_after_generate | (string $output_path, string $template_id) | After generation complete |
| wp_docgen_generation_error | (WP_Error $error, string $template_id) | When error occurs |
| wp_docgen_document_saved | (string $doc_path, string $template_id) | Document saved |
| wp_docgen_before_pdf_conversion | (string $docx_path) | Before PDF conversion |

## Plugin Integration Checklist

1. Register template path menggunakan `wp_docgen_template_path`
2. Siapkan data template menggunakan `wp_docgen_template_data`
3. Tentukan lokasi output menggunakan `wp_docgen_output_path`
4. Hook ke event yang dibutuhkan untuk post-processing
5. Generate dokumen menggunakan `wp_docgen()->generate()`

## Best Practices

1. Selalu gunakan prefix unik untuk template ID
2. Validasi semua data sebelum dikirim ke generator
3. Handle error dengan baik menggunakan try-catch
4. Bersihkan temporary files setelah selesai
5. Dokumentasikan format template yang digunakan