# WP Document Generator (WP DocGen)

Plugin WordPress untuk generate dokumen dari template DOCX/ODT dengan dukungan custom fields.

## Features

- Generate dokumen dari template DOCX/ODT
- Custom fields (tanggal, gambar, QR code, dll)
- Format khusus (terbilang, tanggal Indonesia, gelar) 
- PDF output support
- Simple API untuk integrasi plugin lain
- Cached QR code generation
- Secure file handling

## Requirements

- PHP 7.4+ 
- WordPress 5.8+
- PHP extensions: zip, xml, fileinfo, gd
- GD library untuk QR code generation

## Installation

1. Upload `wp-docgen` ke folder `/wp-content/plugins/`
2. Aktifkan plugin melalui menu 'Plugins' di WordPress
3. Download PHPWord dari https://github.com/PHPOffice/PHPWord/releases
4. Extract dan copy ke `wp-docgen/libs/phpword`
5. Download PHP QR Code dari http://phpqrcode.sourceforge.net/
6. Extract dan copy ke `wp-docgen/libs/phpqrcode`

## Usage

### Custom Fields Format

#### Image Fields
```
Format: ${image:name:width:height:halign:valign}
Provider cukup menyediakan: 'image:name' => '/path/to/image.png'

Parameters:
- name      : Nama variabel berisi path gambar (required)
- width     : Lebar dalam pixel (optional, default: 100)
- height    : Tinggi dalam pixel (optional, default: 100)
- halign    : Horizontal alignment (optional, default: center)
             Valid: left, center, right, justify, both
- valign    : Vertical alignment (optional, default: middle) 
             Valid: top, middle, bottom, baseline

Contoh Template:
${image:logo:50:50:center:middle}     // Lengkap
${image:logo:75:75}                   // Hanya ukuran
${image:logo}                         // Default settings
```

#### QR Code Fields
```
Format: ${qrcode:text:size:error_level}
Provider cukup menyediakan: 'qrcode:text' => 'URL/text to encode'

Parameters:
- text        : Nama variabel berisi teks/URL (required)
- size        : Ukuran dalam pixel (optional, default: 100)
- error_level : Error correction L/M/Q/H (optional, default: L)

Contoh Template:
${qrcode:qr_data:50:M}    // 50x50px, error level M
${qrcode:qr_data:75}      // 75x75px, error level L
${qrcode:qr_data}         // 100x100px, error level L
```

#### Date Fields
```
Format: ${date:field:format}

Contoh:
${date:tanggal_terbit:Y-m-d}     // 2024-12-28
${date:created_at:j F Y}         // 28 December 2024
${date:updated:H:i:s}            // 14:30:00
```

#### User Fields
```
Format: ${user:field}

Contoh:
${user:display_name}     // Nama tampilan user
${user:email}           // Email user
${user:role}            // Role/peran user
```

#### Site Fields
```
Format: ${site:field}

Contoh:
${site:name}            // Nama situs
${site:url}            // URL situs
${site:description}     // Deskripsi situs
```

### Format Khusus
- Money: ${money:1000000:Rp}
- Terbilang: ${terbilang:1000000}
- Tanggal: ${tanggal:2024-01-01:j F Y}
- Gelar: ${gelar:Budi:Dr.|S.Kom}

### Error Handling
- Validasi file exists untuk gambar
- Validasi parameter alignment dan error level
- Fallback ke default settings jika parameter invalid
- Error logging untuk troubleshooting
- Cache management untuk QR code

### Cache Directory
- Default: wp-content/uploads/docgen-temp/
- QR Cache: docgen-temp/qrcache/
- Temporary: docgen-temp/temp/
- Permissions: 755 (writable)
## Provider Implementation

To generate documents, you need to implement the `WP_DocGen_Provider` interface. Here's how to do it:

```php
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
```

## License

GPL v2 or later

## Credits

- PHPWord (https://github.com/PHPOffice/PHPWord)
- PHP QR Code (http://phpqrcode.sourceforge.net/)
