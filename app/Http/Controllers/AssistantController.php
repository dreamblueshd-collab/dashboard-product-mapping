<?php

namespace App\Http\Controllers;

use App\Services\AssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AssistantController extends Controller
{
    private const SESSION_KEY = 'assistant_chat';
    private const MAX_HISTORY = 12;

    public function index(Request $request, AssistantService $assistant): View
    {
        return view('assistant.index', [
            'messages' => $request->session()->get(self::SESSION_KEY, []),
            'configured' => $assistant->isConfigured(),
        ]);
    }

    public function ask(Request $request, AssistantService $assistant): RedirectResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        if (! $assistant->isConfigured()) {
            return back()->with('error', 'VERTEX_API_KEY belum diisi di .env.');
        }

        $question = trim($request->string('question')->toString());
        $messages = $request->session()->get(self::SESSION_KEY, []);

        // Riwayat untuk konteks multi-turn (sebelum menambah pertanyaan ini).
        $history = array_slice($messages, -6);

        $messages[] = ['role' => 'user', 'content' => $question, 'time' => now()->toIso8601String()];

        try {
            $result = $assistant->ask($question, $history);
            $messages[] = [
                'role' => 'assistant',
                'content' => $result['answer'] !== '' ? $result['answer'] : '(Tidak ada jawaban dari AI.)',
                'sources' => $result['sources'],
                'time' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            $messages[] = [
                'role' => 'assistant',
                'content' => 'Maaf, terjadi kesalahan saat memproses: '.$e->getMessage(),
                'time' => now()->toIso8601String(),
            ];
        }

        // Batasi panjang riwayat tersimpan.
        if (count($messages) > self::MAX_HISTORY) {
            $messages = array_slice($messages, -self::MAX_HISTORY);
        }

        $request->session()->put(self::SESSION_KEY, $messages);

        return redirect()->route('assistant.index');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('assistant.index')->with('success', 'Percakapan direset.');
    }
}
