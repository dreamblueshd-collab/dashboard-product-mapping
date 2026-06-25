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
        // Model embedding Gemini (configurable; mis. gemini-embedding-001 / text-embedding-004).
        'model' => env('VERTEX_EMBED_MODEL', 'gemini-embedding-001'),
        // Method REST untuk embedding (umumnya embedContent).
        'api' => env('VERTEX_EMBED_API', 'embedContent'),
        // Dimensi keluaran (kosongkan = default model). 768 = hemat storage & cukup baik.
        'dimensions' => env('VERTEX_EMBED_DIMENSIONS', 768),
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
