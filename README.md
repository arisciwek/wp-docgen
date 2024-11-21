# WP Document Generator (WP DocGen)

Plugin WordPress untuk generate dokumen dari template DOCX/ODT dengan dukungan custom fields.

## Features

- Generate dokumen dari template DOCX/ODT
- Custom fields (tanggal, gambar, QR code, dll)
- Format khusus (terbilang, tanggal Indonesia, gelar) 
- PDF output support
- Simple API untuk integrasi plugin lain

## Requirements

- PHP 7.4+ 
- WordPress 5.8+
- PHP extensions: zip, xml, fileinfo

## Installation

1. Upload `wp-docgen` ke folder `/wp-content/plugins/`
2. Aktifkan plugin melalui menu 'Plugins' di WordPress
3. Download PHPWord dari https://github.com/PHPOffice/PHPWord/releases
4. Extract dan copy ke `wp-docgen/libs/phpword`

## Usage

### Basic Usage

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
       return 'docx'; // docx|odt|pdf
   }

   public function get_temp_dir() {
       $upload_dir = wp_upload_dir();
       return $upload_dir['basedir'] . '/wp-docgen-temp';
   }
}

// Generate document
$provider = new My_Document();
$result = wp_docgen()->generate($provider);
```

### Custom Fields:
Template dapat menggunakan fields:
- Date: ${date:Y-m-d}
- User: ${user:display_name}
- Image: ${image:/path/img.jpg:100:100}
- Site: ${site:name}
- QR code: ${qrcode:text:100}

### Format khusus:
- Money: ${money:1000000:Rp}
- Terbilang: ${terbilang:1000000}
- Tanggal: ${tanggal:2024-01-01:j F Y}
- Gelar: ${gelar:Budi:Dr.|S.Kom}

### Contributing
- Pull requests welcome. For major changes, please open issue first.

### License:
GPL v2 or later

### Credits:
- PHPWord
- PHPQRCode
