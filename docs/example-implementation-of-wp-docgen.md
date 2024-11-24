```php
<?php
/**
 * Example implementation of WP DocGen
 * Plugin Name: My Custom Document Generator
 */

class My_Custom_Document implements WP_DocGen_Provider {
    private $template_dir;
    private $data;
    
    public function __construct($data = array()) {
        $this->template_dir = plugin_dir_path(__FILE__) . 'templates';
        $this->data = $data;
    }

    /**
     * Get data untuk dokumen
     * Contoh penggunaan custom fields
     */
    public function get_data() {
        // Data dasar
        $base_data = array(
            'nomor' => '001/ABC/XI/2024',
            'tanggal' => current_time('mysql'),
            'nama_lengkap' => 'Budi Santoso',
            'gelar' => 'S.Kom',
            'nominal' => 1500000,
            'alamat' => array(
                'jalan' => 'Jl. Contoh No. 123',
                'kecamatan' => 'Contoh',
                'kabupaten' => 'Kota Contoh',
                'provinsi' => 'Provinsi Contoh'
            )
        );

        // Merge dengan data yang dipass ke constructor
        $data = wp_parse_args($this->data, $base_data);

        // Format fields khusus
        $formatted_data = array(
            // Format tanggal Indonesia
            'tanggal_surat' => '${tanggal:' . $data['tanggal'] . ':j F Y}',
            
            // Format nama dengan gelar
            'nama_dengan_gelar' => '${gelar:' . $data['nama_lengkap'] . ':' . $data['gelar'] . '}',
            
            // Format uang
            'nominal_angka' => '${money:' . $data['nominal'] . ':Rp}',
            'nominal_terbilang' => '${terbilang:' . $data['nominal'] . '}',
            
            // Format alamat lengkap
            'alamat_lengkap' => '${alamat:' . $data['alamat']['jalan'] . 
                               ':' . $data['alamat']['kecamatan'] .
                               ':' . $data['alamat']['kabupaten'] .
                               ':' . $data['alamat']['provinsi'] . '}',
            
            // QR Code dengan nomor surat
            'qr_nomor_surat' => '${qrcode:' . $data['nomor'] . ':100}',
            
            // Data user yang login
            'user_name' => '${user:display_name}',
            'user_email' => '${user:email}',
            
            // Info website
            'site_name' => '${site:name}',
            'site_url' => '${site:url}'
        );

        return array_merge($data, $formatted_data);
    }

    /**
     * Get path ke template file
     */
    public function get_template_path() {
        return $this->template_dir . '/surat.docx';
    }
    
    /**
     * Get output filename
     */
    public function get_output_filename() {
        return 'surat-' . sanitize_title($this->data['nomor']);
    }

    /**
     * Get output format (docx, odt, atau pdf)
     */
    public function get_output_format() {
        return 'pdf';
    }

    /**
     * Get temporary directory
     */
    public function get_temp_dir() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/wp-docgen-temp';
    }
}

/**
 * Example usage in your plugin
 */
function my_plugin_generate_document() {
    // Check if WP DocGen is active
    if (!function_exists('wp_docgen')) {
        return new WP_Error('docgen_missing', 'WP Document Generator plugin is required');
    }

    // Prepare document data
    $doc_data = array(
        'nomor' => '002/ABC/XI/2024',
        'nama_lengkap' => 'Andi Wijaya',
        'gelar' => 'S.T.',
        'nominal' => 2000000
    );

    // Create document provider
    $provider = new My_Custom_Document($doc_data);

    // Generate document
    $result = wp_docgen()->generate($provider);

    if (is_wp_error($result)) {
        // Handle error
        error_log('Document generation failed: ' . $result->get_error_message());
        return $result;
    }

    // Handle success - $result contains path to generated file
    return $result;
}

/**
 * Add admin menu untuk generate dokumen
 */
function my_plugin_add_menu() {
    add_menu_page(
        'Generate Document',
        'My Documents',
        'generate_documents',
        'my-documents',
        'my_plugin_document_page'
    );
}
add_action('admin_menu', 'my_plugin_add_menu');

/**
 * Admin page callback
 */
function my_plugin_document_page() {
    // Handle form submission
    if (isset($_POST['generate_doc'])) {
        check_admin_referer('generate_document');
        
        $doc_data = array(
            'nomor' => sanitize_text_field($_POST['nomor']),
            'nama_lengkap' => sanitize_text_field($_POST['nama']),
            'gelar' => sanitize_text_field($_POST['gelar']),
            'nominal' => floatval($_POST['nominal'])
        );
        
        $result = my_plugin_generate_document($doc_data);
        
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
        } else {
            // Get URL untuk download file
            $file_url = str_replace(
                wp_upload_dir()['basedir'],
                wp_upload_dir()['baseurl'],
                $result
            );
            
            echo '<div class="notice notice-success">';
            echo '<p>' . esc_html__('Document generated successfully!', 'my-plugin') . '</p>';
            echo '<p><a href="' . esc_url($file_url) . '" class="button button-primary" download>';
            echo esc_html__('Download Document', 'my-plugin');
            echo '</a></p>';
            echo '</div>';
        }
    }
    
    // Display form
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Generate Document', 'my-plugin'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('generate_document'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="nomor"><?php echo esc_html__('Nomor Surat', 'my-plugin'); ?></label></th>
                    <td><input type="text" id="nomor" name="nomor" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="nama"><?php echo esc_html__('Nama Lengkap', 'my-plugin'); ?></label></th>
                    <td><input type="text" id="nama" name="nama" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="gelar"><?php echo esc_html__('Gelar', 'my-plugin'); ?></label></th>
                    <td><input type="text" id="gelar" name="gelar" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="nominal"><?php echo esc_html__('Nominal', 'my-plugin'); ?></label></th>
                    <td><input type="number" id="nominal" name="nominal" class="regular-text" required></td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="generate_doc" class="button button-primary" value="<?php echo esc_attr__('Generate Document', 'my-plugin'); ?>">
            </p>
        </form>
    </div>
    <?php
}

```

Berikut langkah-langkah untuk menggunakan WP DocGen dari plugin lain:

1. **Cek Dependensi**
```php
if (!function_exists('wp_docgen')) {
    // WP DocGen tidak terinstall
    return;
}
```

2. **Implementasi Interface Provider**
- Buat class yang mengimplementasikan `WP_DocGen_Provider`
- Implementasikan semua method yang diperlukan:
  - `get_data()`: Data untuk template
  - `get_template_path()`: Path ke file template
  - `get_output_filename()`: Nama file output
  - `get_output_format()`: Format output (docx/odt/pdf)
  - `get_temp_dir()`: Direktori temporary

3. **Siapkan Template**
- Buat template DOCX/ODT dengan variable fields
- Simpan di direktori plugin Anda
- Gunakan format field yang didukung:
  ```
  ${tanggal:2024-01-01:j F Y}
  ${money:1000000:Rp}
  ${terbilang:1000000}
  ${gelar:Nama:Dr.|S.Kom}
  ${qrcode:text:100}
  ${user:display_name}
  ${site:name}
  ```

4. **Generate Dokumen**
```php
$provider = new My_Custom_Document($data);
$result = wp_docgen()->generate($provider);

if (is_wp_error($result)) {
    // Handle error
} else {
    // $result berisi path ke file yang digenerate
}
```

Beberapa tips tambahan:

1. **Struktur File**:
```
your-plugin/
  ├── templates/
  │   └── document.docx
  ├── your-plugin.php
  └── includes/
      └── class-document-provider.php
```

2. **Error Handling**:
```php
try {
    $result = wp_docgen()->generate($provider);
    if (is_wp_error($result)) {
        // Handle WP_Error
    }
} catch (Exception $e) {
    // Handle exceptions
}
```

3. **Permissions**:
- Pastikan user memiliki capability `generate_documents`
- Gunakan `current_user_can('generate_documents')`

4. **File Management**:
- File hasil generate disimpan di temporary directory
- Hapus file setelah didownload/diproses
- Implementasikan cleanup routine

5. **Custom Fields**:
- Manfaatkan formatter bawaan untuk:
  - Format tanggal Indonesia
  - Format uang
  - Terbilang
  - Gelar
  - Alamat lengkap
  - QR Code

6. **Hooks yang Tersedia**:
```php
// Saat plugin diaktifkan
add_action('wp_docgen_activated', 'your_function');

// Saat plugin dinonaktifkan
add_action('wp_docgen_deactivated', 'your_function');
```

7. **Best Practices**:
- Selalu validasi input sebelum generate dokumen
- Gunakan nonce untuk form submissions
- Sanitize output filename
- Handle error dengan baik
- Bersihkan temporary files
- Lindungi akses ke file template dan output

Dengan mengikuti panduan ini, plugin Anda dapat memanfaatkan WP DocGen dengan aman dan efisien untuk kebutuhan generate dokumen.