# Integrasi Vertex AI (Gemini)

Aplikasi memanggil Vertex AI memakai **API Key** (sesuai contoh curl resmi),
dengan tool **Google Search** aktif agar model dapat melengkapi data dari internet.

## Endpoint

```
POST https://{VERTEX_API_ENDPOINT}/{VERTEX_API_VERSION}/publishers/google/models/{VERTEX_MODEL_ID}:{VERTEX_GENERATE_CONTENT_API}?key={VERTEX_API_KEY}
```

Default:

| Variabel | Nilai default |
|---|---|
| `VERTEX_API_ENDPOINT` | `aiplatform.us.rep.googleapis.com` |
| `VERTEX_API_VERSION` | `v1` |
| `VERTEX_MODEL_ID` | `gemini-3.5-flash` |
| `VERTEX_GENERATE_CONTENT_API` | `generateContent` |
| `VERTEX_ENABLE_GOOGLE_SEARCH` | `true` |

## Struktur Request

Dibangun di `app/Services/VertexAiService.php`, identik dengan curl referensi:

```json
{
  "contents": [{ "role": "user", "parts": [{ "text": "..." }] }],
  "generationConfig": {
    "temperature": 1,
    "maxOutputTokens": 65535,
    "topP": 0.95,
    "thinkingConfig": { "thinkingLevel": "MEDIUM" }
  },
  "safetySettings": [
    { "category": "HARM_CATEGORY_HATE_SPEECH", "threshold": "OFF" },
    { "category": "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold": "OFF" },
    { "category": "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold": "OFF" },
    { "category": "HARM_CATEGORY_HARASSMENT", "threshold": "OFF" }
  ],
  "tools": [{ "googleSearch": {} }]
}
```

Parameter `generationConfig` & `safetySettings` dapat disetel via `config/vertex.php`
dan variabel `.env` (`VERTEX_TEMPERATURE`, `VERTEX_MAX_OUTPUT_TOKENS`,
`VERTEX_TOP_P`, `VERTEX_THINKING_LEVEL`, `VERTEX_TIMEOUT`).

## Padanan Perintah curl

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  "https://aiplatform.us.rep.googleapis.com/v1/publishers/google/models/gemini-3.5-flash:generateContent?key=${API_KEY}" \
  -d '@request.json'
```

## Pemakaian dalam Aplikasi

`app/Services/AiRefinementService.php` membuat prompt khusus untuk tiap tugas dan
meminta keluaran **JSON** (di-parse oleh `VertexAiService::parseJson`, tahan terhadap
pembungkus markdown ```json```):

| Fungsi | Tujuan | Field yang diisi |
|---|---|---|
| `refineProduct()` | Lengkapi atribut produk | `part_category`, `brand`, `type`, `dimension`, `technical_specification`, `primary_image`, `additional_images` |
| `regenerateDescription()` | Bersihkan & tulis ulang deskripsi | `description` (+ `description_status`) |
| `refineVehicle()` | Lengkapi data kendaraan | `common_name`, `release_year` |
| `mapProductToVehicles()` | Tentukan kendaraan kompatibel dari katalog | membuat baris `product_mappings` |

## Penanganan Error

- Bila `VERTEX_API_KEY` kosong → `VertexAiException`; UI menampilkan peringatan.
- Respons HTTP gagal di-log (`storage/logs`) dan status item diset `failed`.
- Parsing JSON yang gagal melempar `VertexAiException` dengan cuplikan respons.

## Catatan

- Tool `googleSearch` dan `responseMimeType=application/json` bisa tidak kompatibel
  pada sebagian versi model, sehingga keluaran JSON diminta lewat **instruksi prompt**
  lalu di-parse manual (bukan via `responseMimeType`).
- Sesuaikan `VERTEX_MODEL_ID`/`VERTEX_API_ENDPOINT` bila project Anda memakai
  region atau model berbeda.


## RAG Katalog (Embedding + Reranker)

Katalog PDF dapat dijadikan **knowledge base** dengan alur RAG:

```
PDF -> ekstrak teks (smalot/pdfparser) -> CHUNKING (per ~kata + overlap)
    -> EMBEDDING tiap chunk (Gemini) -> simpan ke tabel catalog_chunks (embedding = JSON)
Produk -> query (nama+spesifikasi) -> EMBED query -> RETRIEVAL top-K (cosine di PHP)
       -> RERANK top-N (LLM Gemini) -> konteks untuk prompt auto-mapping
```

### Endpoint embedding

```
POST https://{VERTEX_API_ENDPOINT}/{VERTEX_API_VERSION}/publishers/google/models/{VERTEX_EMBED_MODEL}:{VERTEX_EMBED_API}?key={VERTEX_API_KEY}
```

Body (embedContent):

```json
{
  "model": "publishers/google/models/gemini-embedding-001",
  "content": { "parts": [{ "text": "..." }] },
  "outputDimensionality": 768,
  "taskType": "RETRIEVAL_DOCUMENT"
}
```

Parsing respons dibuat fleksibel: mendukung `embedding.values`,
`embeddings[0].values`, dan `predictions[0].embeddings.values`.

### Konfigurasi (`.env`)

| Variabel | Default | Keterangan |
|---|---|---|
| `VERTEX_EMBED_MODEL` | `gemini-embedding-001` | model embedding |
| `VERTEX_EMBED_API` | `embedContent` | method REST |
| `VERTEX_EMBED_DIMENSIONS` | `768` | dimensi keluaran (kosong = default model) |
| `VERTEX_EMBED_DELAY_MS` | `250` | jeda antar panggilan embedding saat index |
| `VERTEX_RAG_CHUNK_WORDS` | `220` | ukuran chunk (kata) |
| `VERTEX_RAG_CHUNK_OVERLAP` | `40` | overlap antar chunk (kata) |
| `VERTEX_RAG_TOP_K` | `12` | kandidat hasil retrieval (cosine) |
| `VERTEX_RAG_TOP_N` | `5` | kandidat akhir setelah rerank |
| `VERTEX_RAG_RERANK_ENABLED` | `true` | rerank pakai LLM Gemini |

### Reranker

Reranker memakai **LLM Gemini** (`generateContent`) yang mengembalikan urutan indeks
paling relevan (portabel, tidak bergantung pada Ranking API terpisah). Bila rerank
gagal, sistem memakai urutan skor cosine.

### Komponen kode

- `VertexAiService::embed()` — panggilan `:embedContent`.
- `CatalogRagService` — `indexCatalog()`, `retrieve()` (cosine), `rerank()` (LLM), `buildContextForProduct()`.
- `IndexCatalogJob` — index katalog (chunk + embedding) via antrian.
- `MapProductToVehiclesJob` — memakai konteks RAG; fallback ke teks katalog penuh bila katalog belum di-index.
