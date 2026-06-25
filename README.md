# Product Mapping Dashboard

Aplikasi dashboard berbasis **Laravel 13 + MySQL** untuk:

1. **Upload Excel Produk & Kendaraan** ke database (tanpa login).
2. **Refine data dengan AI (Vertex AI / Gemini)** yang melengkapi atribut dari hasil **Google Search**:
   - Produk: melengkapi *part category, brand, type, dimension, technical specification, gambar*, dan **regenerate deskripsi** (membersihkan HTML mentah).
   - Kendaraan: melengkapi **nama umum kendaraan** & **tahun keluaran**.
3. **Upload Katalog Produk (PDF)** lalu menjalankan **auto-mapping**: AI menentukan tiap produk kompatibel dengan kendaraan apa saja, hasilnya disimpan ke tabel **product mapping**.

> Integrasi AI memakai **API Key** (sesuai contoh curl Vertex AI), bukan service account OAuth.

---

## Arsitektur Singkat

| Lapisan | Lokasi |
|---|---|
| Database | `products`, `vehicles`, `product_mappings`, `import_batches` |
| Model | `app/Models/*` |
| Import Excel | `app/Imports/*` (Laravel Excel) |
| Ekstraksi PDF | `Smalot\PdfParser` (di `CatalogController`) |
| Klien AI (HTTP) | `app/Services/VertexAiService.php` |
| Logika refine AI | `app/Services/AiRefinementService.php` |
| Antrian AI (batch) | `app/Jobs/*` |
| Controller | `app/Http/Controllers/*` |
| Tampilan | `resources/views/*` (Blade + Tailwind CDN + Alpine) |
| Konfigurasi AI | `config/vertex.php` + variabel `VERTEX_*` di `.env` |

Dokumentasi lengkap:
- [docs/SETUP.md](docs/SETUP.md) — instalasi & menjalankan aplikasi.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — skema database & alur data.
- [docs/AI_INTEGRATION.md](docs/AI_INTEGRATION.md) — detail integrasi Vertex AI.

---

## Persyaratan

- PHP **8.2+** (diuji pada 8.4) dengan ekstensi `pdo_mysql`, `mbstring`, `gd`, `zip`, `curl`.
- **Composer 2**.
- **MySQL 8** / **MariaDB 10.6+**.
- API Key **Vertex AI (Gemini)** untuk fitur AI.

---

## Mulai Cepat

```bash
# 1. Dependensi
composer install

# 2. Konfigurasi environment
cp .env.example .env
php artisan key:generate

# 3. Edit .env -> kredensial DB + VERTEX_API_KEY
#    DB_DATABASE=product_mapping  DB_USERNAME=...  DB_PASSWORD=...
#    VERTEX_API_KEY=xxxxx

# 4. Buat database lalu migrasi
php artisan migrate

# 5. Jalankan aplikasi
php artisan serve
#    buka http://127.0.0.1:8000

# 6. (untuk aksi AI massal) jalankan worker antrian
php artisan queue:work
```

File template referensi tersedia di folder [`Ref Doc/`](Ref%20Doc/):
`Data Product.xlsx`, `Data Vehicle.xlsx`, `Product Catalog - GS.pdf`,
dan `Konfirmasi Atribut untuk Simulasi.xlsx` (contoh atribut target hasil refine).

---

## Alur Penggunaan

1. **Produk** → menu *Produk* → unggah `Data Product.xlsx` (kolom: Nama, Deskripsi, SKU, Harga).
2. **Kendaraan** → menu *Kendaraan* → unggah `Data Vehicle.xlsx` (sheet pertama `All Data`).
3. **Refine AI** → tombol *Refine* / *Deskripsi* per baris (sinkron) atau *Refine semua* (antrian).
4. **Katalog PDF** → menu *Katalog PDF* → unggah PDF → klik *Index RAG* (chunk + embedding) → *Auto-Mapping*.
5. **Hasil** → menu *Product Mapping* untuk melihat relasi produk → kendaraan.

> Aksi massal (refine semua / auto-mapping) diproses lewat antrian; pastikan
> `php artisan queue:work` berjalan.

---

## Catatan Keamanan

- Aplikasi **tanpa autentikasi** sesuai kebutuhan; jangan ekspos langsung ke publik.
- `VERTEX_API_KEY` disimpan di `.env` (sudah di-`.gitignore`). **Jangan commit** API key.
