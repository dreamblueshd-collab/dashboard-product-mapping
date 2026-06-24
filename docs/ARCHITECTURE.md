# Arsitektur & Skema Data

## Alur Data End-to-End

```
        Excel Produk ─┐
                      ├─► import_batches ─► products  ─┐
        Excel Vehicle ┘                    vehicles  ─┤
                                                      │  (Refine AI: lengkapi atribut
                                                      │   + regenerate deskripsi +
                                                      │   nama umum & tahun kendaraan)
                                                      ▼
        Katalog PDF ──► ekstrak teks ──► AI mapping ──► product_mappings
                                         (produk × kendaraan)
```

## Tabel

### `import_batches`
Melacak setiap aktivitas upload.

| Kolom | Keterangan |
|---|---|
| `type` | `product` \| `vehicle` \| `catalog` |
| `original_filename` | nama file asli |
| `stored_path` | path tersimpan (untuk katalog: file teks hasil ekstraksi) |
| `status` | `pending` \| `processing` \| `completed` \| `failed` |
| `total_rows`, `imported_rows` | statistik baris |
| `message` | pesan hasil/galat |

### `products`
Data mentah dari `Data Product.xlsx` + atribut hasil refine AI.

| Kelompok | Kolom |
|---|---|
| Mentah (Excel) | `sku` (SAP Part Number), `name`, `raw_description` (HTML), `price` |
| Hasil AI | `part_category`, `brand`, `type`, `dimension`, `description` (deskripsi bersih), `technical_specification`, `primary_image`, `additional_images` (JSON) |
| Status | `refine_status`, `description_status` (`raw`/`refined`/`failed`), `ai_notes`, `refined_at` |

### `vehicles`
Data mentah dari `Data Vehicle.xlsx` (16 kolom berkode) + 2 field AI.

| Kelompok | Kolom |
|---|---|
| Mentah (Excel) | `group_code/description`, `type_code/description`, `brand_code/description`, `sub_brand_code/description`, `variant_code`, `model_description`, `transmission_code/description`, `machine_type_code/description`, `machine_volume_code/description` |
| Hasil AI | `common_name` (nama umum kendaraan), `release_year` (tahun keluaran) |
| Status | `refine_status`, `ai_notes`, `refined_at` |

> **Catatan pemetaan:** sheet `All Data` memiliki header dengan label ganda
> ("Brand Description" muncul dua kali), sehingga importer memetakan kolom
> **berdasarkan posisi** (indeks 0–15), bukan nama header. Hanya **sheet pertama**
> yang diimpor; sheet lookup (Group/Type/Brand/Variant/dll) diabaikan.

### `product_mappings`
Relasi produk → kendaraan (hasil auto-mapping dari katalog).

| Kolom | Keterangan |
|---|---|
| `product_id` | FK ke `products` (cascade) |
| `vehicle_id` | FK ke `vehicles` (nullable; diisi bila match ke master) |
| `vehicle_type`, `vehicle_brand`, `vehicle_model`, `year`, `transmission` | snapshot atribut kendaraan dari AI |
| `source` | `excel` \| `ai_catalog` \| `manual` |
| `confidence` | keyakinan AI 0–100 |
| `notes` | catatan |

Mengikuti struktur kolom pada file `Konfirmasi Atribut untuk Simulasi.xlsx`
(gabungan atribut produk + aplikasi kendaraan), yang menjadi acuan "bentuk data
yang dibutuhkan" setelah refine.

## Komponen Kode

- **Importers** (`app/Imports`): `ProductsImport`, `VehiclesImport` (+ `VehicleSheetImport` untuk sheet pertama).
- **Services** (`app/Services`):
  - `VertexAiService` — pemanggilan HTTP `generateContent` + parsing teks/JSON.
  - `AiRefinementService` — membangun prompt, memanggil AI, menyimpan hasil ke DB.
- **Jobs** (`app/Jobs`): `RefineProductJob`, `RegenerateProductDescriptionJob`, `RefineVehicleJob`, `MapProductToVehiclesJob` (diproses oleh `queue:work`).
- **Controllers** (`app/Http/Controllers`): `DashboardController`, `ProductController`, `VehicleController`, `CatalogController`, `MappingController`.

## Strategi Pencocokan Kendaraan (mapping)

Saat AI mengembalikan kandidat kendaraan (brand + model), sistem mencoba
mencocokkannya ke master `vehicles` menggunakan pencarian `LIKE` pada
`brand_description` + `model_description` (fallback ke kata pertama model).
Bila tidak ada yang cocok, `vehicle_id` dibiarkan `null` namun snapshot teks
kendaraan tetap disimpan.
