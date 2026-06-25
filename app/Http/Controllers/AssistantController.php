<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\AssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AssistantController extends Controller
{
    private const SESSION_KEY = 'assistant_conversation_id';

    public function index(Request $request, AssistantService $assistant): View
    {
        // Memilih percakapan (dari ?conversation= atau session).
        $conversation = $this->resolveConversation($request, create: false);

        $messages = $conversation
            ? $conversation->messages()->get()
            : collect();

        // Daftar percakapan terakhir untuk sidebar.
        $recent = ChatConversation::query()
            ->withCount('messages')
            ->latest('updated_at')
            ->limit(15)
            ->get();

        return view('assistant.index', [
            'messages' => $messages,
            'conversation' => $conversation,
            'recent' => $recent,
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
        $conversation = $this->resolveConversation($request, create: true);

        // Beri judul dari pertanyaan pertama.
        if (empty($conversation->title)) {
            $conversation->update(['title' => Str::limit($question, 60)]);
        }

        // Riwayat (untuk konteks multi-turn) sebelum pertanyaan ini.
        $history = $conversation->messages()
            ->latest('id')->limit(6)->get()
            ->reverse()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => $question,
        ]);

        try {
            $result = $assistant->ask($question, $history);
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $result['answer'] !== '' ? $result['answer'] : '_(Tidak ada jawaban dari AI.)_',
                'sources' => $result['sources'],
            ]);
        } catch (Throwable $e) {
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => 'Maaf, terjadi kesalahan saat memproses: '.$e->getMessage(),
            ]);
        }

        // Sentuh updated_at agar naik ke atas daftar.
        $conversation->touch();

        return redirect()->route('assistant.index');
    }

    /**
     * Mulai percakapan baru (lupakan yang aktif di session).
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('assistant.index')->with('success', 'Memulai percakapan baru.');
    }

    /**
     * Ambil percakapan aktif: dari ?conversation=uuid, atau session.
     * Bila $create true dan belum ada, buat percakapan baru.
     */
    private function resolveConversation(Request $request, bool $create): ?ChatConversation
    {
        // Pindah ke percakapan tertentu via query param.
        if ($uuid = $request->query('conversation')) {
            $conv = ChatConversation::where('uuid', $uuid)->first();
            if ($conv) {
                $request->session()->put(self::SESSION_KEY, $conv->uuid);

                return $conv;
            }
        }

        $sessionUuid = $request->session()->get(self::SESSION_KEY);
        if ($sessionUuid) {
            $conv = ChatConversation::where('uuid', $sessionUuid)->first();
            if ($conv) {
                return $conv;
            }
        }

        if (! $create) {
            return null;
        }

        $conv = ChatConversation::create();
        $request->session()->put(self::SESSION_KEY, $conv->uuid);

        return $conv;
    }
}
