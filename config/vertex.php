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

    // Safety settings diset OFF sesuai contoh curl.
    'safety_settings' => [
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'OFF'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'OFF'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'OFF'],
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'OFF'],
    ],
];
