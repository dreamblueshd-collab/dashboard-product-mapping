<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vertex AI (Gemini) - autentikasi via API Key
    |--------------------------------------------------------------------------
    |
    | Konfigurasi ini mereplikasi contoh curl resmi:
    |   https://{endpoint}/{version}/publishers/google/models/{model}:{generateContentApi}?key={apiKey}
    |
    | Autentikasi memakai API key pada query string (bukan service account OAuth).
    |
    */

    'api_key' => env('VERTEX_API_KEY', ''),

    'endpoint' => env('VERTEX_API_ENDPOINT', 'aiplatform.us.rep.googleapis.com'),

    'api_version' => env('VERTEX_API_VERSION', 'v1'),

    'model_id' => env('VERTEX_MODEL_ID', 'gemini-3.5-flash'),

    'generate_content_api' => env('VERTEX_GENERATE_CONTENT_API', 'generateContent'),

    // Aktifkan tool googleSearch agar AI dapat melengkapi data dari internet.
    'enable_google_search' => (bool) env('VERTEX_ENABLE_GOOGLE_SEARCH', true),

    'generation' => [
        'temperature' => (float) env('VERTEX_TEMPERATURE', 1),
        'max_output_tokens' => (int) env('VERTEX_MAX_OUTPUT_TOKENS', 65535),
        'top_p' => (float) env('VERTEX_TOP_P', 0.95),
        'thinking_level' => env('VERTEX_THINKING_LEVEL', 'MEDIUM'),
    ],

    // Timeout (detik) untuk panggilan HTTP ke Vertex AI.
    'timeout' => (int) env('VERTEX_TIMEOUT', 180),

    // Jeda (detik) antar pemanggilan AI pada proses massal (antrian) agar tidak
    // menabrak limit RPM/TPM platform. Job ke-n dijadwalkan n * delay detik.
    'bulk_delay_seconds' => (int) env('VERTEX_BULK_DELAY_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Embedding & RAG (knowledge base katalog)
    |--------------------------------------------------------------------------
    */
    'embedding' => [
        // Provider embedding:
        //   'vertex'     -> Vertex AI (mendukung gemini-embedding-2 MULTIMODAL).
        //                   Sesuai dok resmi: butuh project+location & OAuth Bearer token.
        //   'gemini_api' -> generativelanguage.googleapis.com (text saja, mis. gemini-embedding-001) via API key.
        'provider' => env('VERTEX_EMBED_PROVIDER', 'vertex'),

        'endpoint' => env('VERTEX_EMBED_ENDPOINT', 'aiplatform.us.rep.googleapis.com'),
        'api_version' => env('VERTEX_EMBED_API_VERSION', 'v1'),
        'location' => env('VERTEX_EMBED_LOCATION', 'us'),
        'project_id' => env('VERTEX_EMBED_PROJECT_ID', ''),

        // Autentikasi: 'bearer' (OAuth access token; sesuai dok gemini-embedding-2)
        //              atau 'api_key' (query ?key=, utk Gemini API / express mode).
        'auth' => env('VERTEX_EMBED_AUTH', 'bearer'),
        'api_key' => env('VERTEX_EMBED_API_KEY', ''),
        // Access token OAuth (mis. hasil `gcloud auth print-access-token`). Berlaku ~1 jam.
        'access_token' => env('VERTEX_EMBED_ACCESS_TOKEN', ''),

        // Model embedding. gemini-embedding-2 = multimodal (text/image/dokumen/audio/video).
        'model' => env('VERTEX_EMBED_MODEL', 'gemini-embedding-2'),
        'api' => env('VERTEX_EMBED_API', 'embedContent'),
        // Dimensi keluaran. gemini-embedding-2: 128..3072 (rekomendasi 768/1536/3072).
        'dimensions' => env('VERTEX_EMBED_DIMENSIONS', 1536),
        // Jeda (milidetik) antar panggilan embedding saat index katalog (rate limit).
        'delay_ms' => (int) env('VERTEX_EMBED_DELAY_MS', 250),
    ],

    'rag' => [
        // Ukuran chunk (perkiraan jumlah kata) & overlap antar chunk.
        'chunk_words' => (int) env('VERTEX_RAG_CHUNK_WORDS', 220),
        'chunk_overlap_words' => (int) env('VERTEX_RAG_CHUNK_OVERLAP', 40),
        // Jumlah kandidat diambil saat retrieval (cosine) dan setelah rerank.
        'top_k' => (int) env('VERTEX_RAG_TOP_K', 12),
        'top_n' => (int) env('VERTEX_RAG_TOP_N', 5),
        // Gunakan LLM Gemini untuk rerank kandidat (lebih portabel daripada Ranking API).
        'rerank_enabled' => (bool) env('VERTEX_RAG_RERANK_ENABLED', true),
    ],

    // Safety settings diset OFF sesuai contoh curl.
    'safety_settings' => [
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'OFF'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'OFF'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'OFF'],
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'OFF'],
    ],
];
