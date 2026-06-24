# Setup & Menjalankan Aplikasi

## 1. Prasyarat

- PHP 8.2+ (`pdo_mysql`, `mbstring`, `gd`, `zip`, `curl`, `fileinfo`)
- Composer 2
- MySQL 8 atau MariaDB 10.6+

Cek ekstensi PHP:

```bash
php -m | grep -E "pdo_mysql|mbstring|gd|zip|curl"
```

## 2. Instalasi Dependensi

```bash
composer install
```

## 3. Konfigurasi Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
APP_NAME="Product Mapping Dashboard"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=product_mapping
DB_USERNAME=app
DB_PASSWORD=app_password

# Vertex AI (API Key)
VERTEX_API_KEY=ISI_API_KEY_ANDA
VERTEX_API_ENDPOINT=aiplatform.us.rep.googleapis.com
VERTEX_API_VERSION=v1
VERTEX_MODEL_ID=gemini-3.5-flash
VERTEX_GENERATE_CONTENT_API=generateContent
VERTEX_ENABLE_GOOGLE_SEARCH=true
```

## 4. Membuat Database

```sql
CREATE DATABASE product_mapping CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app'@'127.0.0.1' IDENTIFIED BY 'app_password';
GRANT ALL PRIVILEGES ON product_mapping.* TO 'app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

## 5. Migrasi

```bash
php artisan migrate
```

Tabel yang dibuat: `import_batches`, `products`, `vehicles`, `product_mappings`
(plus tabel bawaan Laravel: `jobs`, `cache`, `sessions`, dll).

## 6. Menjalankan

```bash
php artisan serve
# http://127.0.0.1:8000
```

## 7. Antrian (untuk aksi AI massal)

Aksi *Refine semua* dan *Auto-Mapping* memakai antrian database. Jalankan worker:

```bash
php artisan queue:work --tries=2 --timeout=300
```

Aksi AI per-baris (tombol *Refine* / *Deskripsi* pada satu item) berjalan
**sinkron** dan tidak memerlukan worker.

## 8. Tips Performa

- File kendaraan referensi berisi ribuan baris; impor memakai *chunk reading*.
- Untuk refine ribuan kendaraan, gunakan parameter batas (`limit`) pada tombol
  *Refine (raw)* agar antrian terkontrol, dan jalankan beberapa worker bila perlu.
- Naikkan `VERTEX_TIMEOUT` di `.env` bila respons AI lambat.

## 9. Troubleshooting

| Gejala | Solusi |
|---|---|
| `VERTEX_API_KEY belum diisi` saat aksi per-baris | Isi `VERTEX_API_KEY` di `.env`, lalu `php artisan config:clear`. |
| **Aksi massal gagal `VERTEX_API_KEY belum diisi` padahal per-baris bisa** | Worker antrian memuat `.env` hanya saat start. **Restart worker** setiap kali `.env` berubah: `php artisan queue:restart` lalu jalankan ulang `php artisan queue:work`. (Aksi per-baris jalan sinkron di web sehingga selalu baca `.env` terbaru.) |
| Aksi massal tidak jalan sama sekali | Pastikan `php artisan queue:work` berjalan. |
| Error koneksi DB | Cek `DB_*` di `.env` dan service MySQL aktif. |
| Upload Excel gagal | Pastikan ekstensi `zip` & `gd` aktif (dibutuhkan Laravel Excel). |
| Teks PDF kosong | PDF berbasis gambar/scan tidak punya teks; perlu OCR (di luar cakupan). |
