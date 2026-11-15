<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotLog;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\ChatbotSettings;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pengaturan chatbot AI (admin): pilih provider, kunci API terenkripsi, parameter model, pesan, dan uji koneksi. */
class ChatbotSettingsController extends Controller
{
    private const MODEL_RE = '/^[A-Za-z0-9._:\\-\\/]+$/';

    public function __construct(private SettingsService $settings, private ChatbotSettings $bot, private ChatbotService $service) {}

    public function edit()
    {
        $provider = $this->bot->provider();
        $since = now()->subDay();

        return view('admin.chatbot.edit', [
            'v' => [
                'enabled' => $this->bot->enabled(),
                'provider' => $provider,
                'gemini_model' => $this->settings->chatbot('gemini_model'),
                'ollama_base_url' => $this->settings->chatbot('ollama_base_url'),
                'ollama_model' => $this->settings->chatbot('ollama_model'),
                'temperature' => $this->settings->chatbot('temperature'),
                'max_output_tokens' => $this->settings->chatbot('max_output_tokens'),
                'timeout_seconds' => $this->settings->chatbot('timeout_seconds'),
                'rate_limit_per_minute' => $this->bot->rateLimitPerMinute(),
                'max_message_chars' => $this->bot->maxMessageChars(),
                'history_turns' => $this->bot->historyTurns(),
                'welcome' => $this->bot->welcome(),
                'suggestions' => implode("\n", $this->bot->suggestions()),
                'log_conversations' => $this->bot->logConversations(),
                'system_prompt' => (string) $this->settings->chatbot('system_prompt'),
            ],
            'keyMask' => $this->settings->mask('chatbot.gemini_api_key'),
            'defaultPrompt' => is_file((string) config('chatbot.system_prompt_file')) ? (string) file_get_contents(config('chatbot.system_prompt_file')) : '',
            'ollamaModels' => $provider === 'ollama' ? $this->service->ollamaModels() : [],
            'stats' => [
                'total24' => ChatbotLog::where('created_at', '>=', $since)->count(),
                'fallback24' => ChatbotLog::where('created_at', '>=', $since)->where('is_error', true)->count(),
                'avg_ms' => (int) round((float) ChatbotLog::where('created_at', '>=', $since)->avg('latency_ms')),
            ],
            'logs' => ChatbotLog::latest('id')->limit(20)->get(),
            'effective' => $this->bot->effectiveProvider(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(ChatbotSettings::PROVIDERS)],
            'gemini_api_key' => ['nullable', 'string', 'max:300'],
            'gemini_model' => ['nullable', 'string', 'max:80', 'regex:'.self::MODEL_RE],
            'ollama_base_url' => ['nullable', 'url:http,https', 'max:200'],
            'ollama_model' => ['nullable', 'string', 'max:80', 'regex:'.self::MODEL_RE],
            'temperature' => ['required', 'numeric', 'between:0,1'],
            'max_output_tokens' => ['required', 'integer', 'between:64,4096'],
            'timeout_seconds' => ['required', 'integer', 'between:3,120'],
            'rate_limit_per_minute' => ['required', 'integer', 'between:1,120'],
            'max_message_chars' => ['required', 'integer', 'between:50,2000'],
            'history_turns' => ['required', 'integer', 'between:0,20'],
            'welcome' => ['nullable', 'string', 'max:400'],
            'suggestions' => ['nullable', 'string', 'max:700'],
            'system_prompt' => ['nullable', 'string', 'max:8000'],
        ], [
            'regex' => 'Nama model hanya boleh berisi huruf, angka, titik, titik dua, garis, dan garis miring.',
            'ollama_base_url.url' => 'Alamat Ollama harus berupa URL http:// atau https://.',
        ]);

        $newKey = trim((string) ($data['gemini_api_key'] ?? ''));
        $clearKey = $request->boolean('clear_gemini_key');
        $willHaveKey = $clearKey ? false : ($newKey !== '' || $this->bot->geminiKey() !== '');
        if ($data['provider'] === 'gemini' && ! $willHaveKey) {
            return back()->withInput($request->except('gemini_api_key'))
                ->withErrors(['gemini_api_key' => 'Provider Gemini membutuhkan kunci API. Isi kunci dari Google AI Studio atau pilih provider lain.']);
        }

        $s = $this->settings;
        $s->set('chatbot.enabled', $request->boolean('enabled') ? '1' : '0');
        $s->set('chatbot.provider', $data['provider']);
        $s->set('chatbot.gemini_model', trim((string) ($data['gemini_model'] ?? '')) ?: config('chatbot.defaults.gemini_model'));
        $s->set('chatbot.ollama_base_url', rtrim(trim((string) ($data['ollama_base_url'] ?? '')) ?: config('chatbot.defaults.ollama_base_url'), '/'));
        $s->set('chatbot.ollama_model', trim((string) ($data['ollama_model'] ?? '')) ?: config('chatbot.defaults.ollama_model'));
        foreach (['temperature', 'max_output_tokens', 'timeout_seconds', 'rate_limit_per_minute', 'max_message_chars', 'history_turns'] as $k) {
            $s->set('chatbot.'.$k, (string) $data[$k]);
        }
        $s->set('chatbot.welcome', trim((string) ($data['welcome'] ?? '')) ?: config('chatbot.defaults.welcome'));
        $suggestions = collect(preg_split('/\R/', (string) ($data['suggestions'] ?? '')))
            ->map(fn ($l) => mb_substr(trim($l), 0, 60))->filter()->unique()->take(6)->values()->all();
        $s->set('chatbot.suggestions', json_encode($suggestions ?: config('chatbot.defaults.suggestions'), JSON_UNESCAPED_UNICODE));
        $s->set('chatbot.log_conversations', $request->boolean('log_conversations') ? '1' : '0');
        $s->set('chatbot.system_prompt', $request->boolean('reset_prompt') ? null : (trim((string) ($data['system_prompt'] ?? '')) ?: null));

        if ($clearKey) {
            $s->forget('chatbot.gemini_api_key');
        } elseif ($newKey !== '') {
            $s->set('chatbot.gemini_api_key', $newKey, true);
        }

        return redirect()->route('admin.chatbot.edit')->with('success', 'Pengaturan chatbot disimpan.');
    }

    /** Uji koneksi memakai nilai form yang belum disimpan. Kunci kosong = pakai kunci tersimpan. */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(ChatbotSettings::PROVIDERS)],
            'gemini_api_key' => ['nullable', 'string', 'max:300'],
            'gemini_model' => ['nullable', 'string', 'max:80', 'regex:'.self::MODEL_RE],
            'ollama_base_url' => ['nullable', 'url:http,https', 'max:200'],
            'ollama_model' => ['nullable', 'string', 'max:80', 'regex:'.self::MODEL_RE],
        ]);

        return response()->json($this->service->ping(array_filter($data, fn ($v) => $v !== null && $v !== '')));
    }
}
