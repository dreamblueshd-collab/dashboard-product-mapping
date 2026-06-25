<?php

namespace App\Services;

use App\Exceptions\VertexAiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien Vertex AI (Gemini) dengan autentikasi API Key.
 *
 * Mereplikasi contoh curl resmi:
 *   POST https://{endpoint}/{version}/publishers/google/models/{model}:{generateContentApi}?key={apiKey}
 *
 * Body memuat: contents, generationConfig (temperature/maxOutputTokens/topP/thinkingConfig),
 * safetySettings (OFF), dan tools [{ googleSearch: {} }] untuk pencarian internet.
 */
class VertexAiService
{
    /**
     * Apakah API key sudah dikonfigurasi.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('vertex.api_key'));
    }

    /**
     * Apakah API key untuk embedding sudah tersedia (embed key atau key utama).
     */
    public function isEmbeddingConfigured(): bool
    {
        return ! empty(config('vertex.embedding.api_key')) || ! empty(config('vertex.api_key'));
    }

    /**
     * Hasilkan embedding vektor untuk sebuah teks.
     *
     * Memanggil endpoint :embedContent dengan API key. Parsing dibuat fleksibel
     * untuk mengakomodasi beberapa bentuk respons (embedContent vs predict).
     *
     * @param  string  $taskType  mis. RETRIEVAL_DOCUMENT | RETRIEVAL_QUERY
     * @return array<int, float>
     */
    public function embed(string $text, ?string $taskType = null): array
    {
        $apiKey = config('vertex.embedding.api_key') ?: config('vertex.api_key');
        if (empty($apiKey)) {
            throw new VertexAiException('API key embedding belum diisi (VERTEX_EMBED_API_KEY atau VERTEX_API_KEY) di .env.');
        }

        $model = config('vertex.embedding.model');

        // Format Gemini API (generativelanguage): field model = "models/{model}".
        $payload = [
            'model' => 'models/'.$model,
            'content' => [
                'parts' => [['text' => $text]],
            ],
        ];

        $dimensions = config('vertex.embedding.dimensions');
        if (! empty($dimensions)) {
            $payload['outputDimensionality'] = (int) $dimensions;
        }
        if ($taskType) {
            $payload['taskType'] = $taskType;
        }

        // Catatan: endpoint Vertex AI "express mode" (aiplatform.*.rep.googleapis.com)
        // TIDAK menyediakan embedContent/predict. Embedding via API key tersedia di
        // Gemini API (generativelanguage.googleapis.com), sehingga host/versi embedding
        // dikonfigurasi terpisah dari generateContent.
        $url = sprintf(
            'https://%s/%s/models/%s:%s',
            config('vertex.embedding.endpoint'),
            config('vertex.embedding.api_version'),
            $model,
            config('vertex.embedding.api'),
        );

        $response = Http::timeout((int) config('vertex.timeout', 180))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withQueryParameters(['key' => $apiKey])
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('Vertex AI embedding gagal', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);

            throw new VertexAiException(
                'Panggilan embedding gagal (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 300)
            );
        }

        $json = $response->json() ?? [];

        // Bentuk respons yang mungkin:
        //  embedContent: { embedding: { values: [...] } }
        //  batch/predict: { embeddings: [{ values: [...] }] } / { predictions: [{ embeddings: { values: [...] } }] }
        $values = data_get($json, 'embedding.values')
            ?? data_get($json, 'embedding.value')
            ?? data_get($json, 'embeddings.0.values')
            ?? data_get($json, 'predictions.0.embeddings.values')
            ?? data_get($json, 'predictions.0.embeddings.0.values');

        if (! is_array($values) || $values === []) {
            throw new VertexAiException('Respons embedding tidak berisi vektor yang dikenali.');
        }

        return array_map('floatval', $values);
    }

    /**
     * Panggilan generateContent level rendah.
     *
     * @param  array<int, array<string, mixed>>  $parts  Daftar part, mis. [['text' => '...']]
     * @return array<string, mixed>  Respons JSON yang sudah ter-decode.
     */
    public function generateContent(array $parts): array
    {
        if (! $this->isConfigured()) {
            throw new VertexAiException('VERTEX_API_KEY belum diisi di .env. Fitur AI tidak dapat digunakan.');
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => config('vertex.generation.temperature'),
                'maxOutputTokens' => config('vertex.generation.max_output_tokens'),
                'topP' => config('vertex.generation.top_p'),
                'thinkingConfig' => [
                    'thinkingLevel' => config('vertex.generation.thinking_level'),
                ],
            ],
            'safetySettings' => config('vertex.safety_settings'),
        ];

        if (config('vertex.enable_google_search')) {
            $payload['tools'] = [
                ['googleSearch' => (object) []],
            ];
        }

        $url = sprintf(
            'https://%s/%s/publishers/google/models/%s:%s',
            config('vertex.endpoint'),
            config('vertex.api_version'),
            config('vertex.model_id'),
            config('vertex.generate_content_api'),
        );

        $response = Http::timeout((int) config('vertex.timeout', 180))
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withQueryParameters(['key' => config('vertex.api_key')])
            ->post($url, $payload);

        if ($response->failed()) {
            $body = $response->body();
            Log::error('Vertex AI request gagal', [
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 1000),
            ]);

            throw new VertexAiException(
                'Panggilan Vertex AI gagal (HTTP '.$response->status().'): '.mb_substr($body, 0, 300)
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Kirim prompt teks, kembalikan teks jawaban gabungan.
     */
    public function generateText(string $prompt): string
    {
        $response = $this->generateContent([['text' => $prompt]]);

        return $this->extractText($response);
    }

    /**
     * Kirim prompt yang meminta keluaran JSON, kembalikan array hasil parse.
     *
     * @return array<string, mixed>
     */
    public function generateJson(string $prompt): array
    {
        $text = $this->generateText($prompt);

        return $this->parseJson($text);
    }

    /**
     * Gabungkan seluruh part teks dari kandidat pertama.
     *
     * @param  array<string, mixed>  $response
     */
    public function extractText(array $response): string
    {
        $parts = data_get($response, 'candidates.0.content.parts', []);

        $text = collect($parts)
            ->map(fn ($p) => $p['text'] ?? '')
            ->filter()
            ->implode("\n");

        return trim($text);
    }

    /**
     * Ekstrak objek JSON dari teks jawaban model.
     *
     * Tahan terhadap pembungkus markdown ```json ... ``` maupun teks tambahan
     * di sekitar JSON.
     *
     * @return array<string, mixed>
     */
    public function parseJson(string $text): array
    {
        $text = trim($text);

        // Hilangkan pembungkus code fence bila ada.
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/is', $text, $m)) {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Fallback: ambil dari kurung pertama sampai kurung terakhir.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new VertexAiException('Gagal mem-parsing JSON dari respons AI: '.mb_substr($text, 0, 200));
    }
}
