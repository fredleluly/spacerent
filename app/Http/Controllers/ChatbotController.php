<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\ChatbotSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Endpoint publik chatbot (boleh anonim). Hanya JSON; kunci API tidak pernah dikirim ke browser. */
class ChatbotController extends Controller
{
    public function __construct(private ChatbotSettings $settings, private ChatbotService $bot) {}

    public function config(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $this->settings->enabled(),
            'welcome' => $this->settings->welcome(),
            'suggestions' => $this->settings->suggestions(),
            'max_chars' => $this->settings->maxMessageChars(),
            'authenticated' => $user !== null,
            'user_name' => $user ? explode(' ', trim($user->name))[0] : null,
            'provider' => $this->settings->effectiveProvider(),
        ]);
    }

    public function message(Request $request): JsonResponse
    {
        if (! $this->settings->enabled()) {
            return response()->json(['enabled' => false, 'answer' => 'Asisten sedang tidak aktif. Silakan hubungi tim kami lewat halaman Hubungi Kami.'], 503);
        }

        $max = $this->settings->maxMessageChars();
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.$max],
            'history' => ['nullable', 'array', 'max:40'],
            'history.*.role' => ['nullable', 'string'],
            'history.*.content' => ['nullable', 'string', 'max:4000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ], [
            'message.required' => 'Tulis pertanyaan Anda dulu.',
            'message.max' => "Pesan terlalu panjang (maksimal {$max} karakter).",
        ]);

        $session = hash('sha256', $request->session()->getId());
        // Dibatasi per akun (bila login) atau per IP, bukan per sesi: sesi bisa dibuang pengunjung untuk menghindari batas.
        $limiterKey = 'chatbot:'.($request->user() ? 'u'.$request->user()->id : 'ip'.sha1((string) $request->ip()));
        if (RateLimiter::tooManyAttempts($limiterKey, $this->settings->rateLimitPerMinute())) {
            return response()->json([
                'answer' => 'Anda mengirim pesan terlalu cepat. Tunggu sebentar lalu coba lagi.',
                'retry_after' => RateLimiter::availableIn($limiterKey),
            ], 429);
        }
        RateLimiter::hit($limiterKey, 60);

        return response()->json($this->bot->reply(
            $data['message'],
            $data['history'] ?? [],
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
            $request->user(),
            substr($session, 0, 32),
        ));
    }
}
