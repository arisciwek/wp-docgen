```markdown
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
- GD library for QR code generation

## Installation

1. Upload `wp-docgen` ke folder `/wp-content/plugins/`
2. Aktifkan plugin melalui menu 'Plugins' di WordPress
3. Download PHPWord dari https://github.com/PHPOffice/PHPWord/releases
4. Extract dan copy ke `wp-docgen/libs/phpword`
5. Download PHP QR Code dari http://phpqrcode.sourceforge.net/
6. Extract dan copy ke `wp-docgen/libs/phpqrcode`

### PHP QR Code Setup
Library PHP QR Code membutuhkan konfigurasi tambahan:
- GD extension harus terinstall di PHP
- Folder cache QR code harus writable (755)
- Default folder: `wp-content/uploads/docgen-temp/qrcache`

## Usage

### Custom Fields:
Template dapat menggunakan fields:
- Date: ${date:tanggal_terbit:Y-m-d}
- User: ${user:display_name}
- Image: ${image:/path/img.jpg:100:100}
- Site: ${site:name}
- QR code: ${qrcode:text:100}

### Format QR Code:
Placeholder QR code memiliki format:
```
${qrcode:text:size[:error_level]}
```
- text: Teks/URL yang akan di-encode (required)
- size: Ukuran QR code dalam pixel (50-500) (required)
- error_level: Level error correction (L/M/Q/H) (optional, default: L)

Contoh:
```
${qrcode:https://example.com:100}
${qrcode:Hello World:200:H}
```

### Format khusus:
- Money: ${money:1000000:Rp}
- Terbilang: ${terbilang:1000000}
- Tanggal: ${tanggal:2024-01-01:j F Y}
- Gelar: ${gelar:Budi:Dr.|S.Kom}

### License:
GPL v2 or later

### Credits:
- PHPWord (https://github.com/PHPOffice/PHPWord)
- PHP QR Code (http://phpqrcode.sourceforge.net/)
```