<?php

namespace App\Services\Chatbot;

use App\Services\SettingsService;

/** Pembaca pengaturan chatbot dengan tipe yang sudah dinormalisasi (database menyimpan semuanya sebagai teks). */
class ChatbotSettings
{
    public const PROVIDERS = ['rules', 'gemini', 'ollama'];

    public function __construct(private SettingsService $settings) {}

    public function enabled(): bool
    {
        return $this->bool('enabled');
    }

    public function logConversations(): bool
    {
        return $this->bool('log_conversations');
    }

    /** Provider yang dipilih admin (bila tidak valid: rules). */
    public function provider(): string
    {
        $p = strtolower((string) $this->settings->chatbot('provider'));

        return in_array($p, self::PROVIDERS, true) ? $p : 'rules';
    }

    /** Provider yang benar-benar bisa dipakai: Gemini tanpa kunci diperlakukan sebagai rules. */
    public function effectiveProvider(): string
    {
        $p = $this->provider();
        if ($p === 'gemini' && $this->geminiKey() === '') {
            return 'rules';
        }

        return $p;
    }

    public function geminiKey(): string
    {
        return (string) ($this->settings->get('chatbot.gemini_api_key') ?? '');
    }

    /** @return array<string, mixed> konfigurasi provider; $override dari form admin yang belum disimpan */
    public function providerConfig(array $override = []): array
    {
        $cfg = [
            'provider' => $this->provider(),
            'gemini_api_key' => $this->geminiKey(),
            'gemini_model' => trim((string) $this->settings->chatbot('gemini_model')) ?: 'gemini-2.0-flash',
            'ollama_base_url' => rtrim(trim((string) $this->settings->chatbot('ollama_base_url')) ?: 'http://localhost:11434', '/'),
            'ollama_model' => trim((string) $this->settings->chatbot('ollama_model')) ?: 'llama3.1',
            'temperature' => $this->float('temperature', 0.3, 0, 1),
            'max_output_tokens' => $this->int('max_output_tokens', 700, 64, 4096),
            'timeout_seconds' => $this->int('timeout_seconds', 25, 3, 120),
        ];

        foreach ($override as $k => $v) {
            if ($v === null || $v === '' || ! array_key_exists($k, $cfg)) {
                continue;
            }
            $cfg[$k] = $k === 'ollama_base_url' ? rtrim((string) $v, '/') : $v;
        }

        return $cfg;
    }

    public function rateLimitPerMinute(): int
    {
        return $this->int('rate_limit_per_minute', 12, 1, 120);
    }

    public function maxMessageChars(): int
    {
        return $this->int('max_message_chars', 500, 50, 2000);
    }

    public function historyTurns(): int
    {
        return $this->int('history_turns', 6, 0, 20);
    }

    public function welcome(): string
    {
        return (string) $this->settings->chatbot('welcome');
    }

    /** @return list<string> */
    public function suggestions(): array
    {
        $v = $this->settings->chatbot('suggestions');
        if (is_string($v)) {
            $decoded = json_decode($v, true);
            $v = is_array($decoded) ? $decoded : preg_split('/\R/', $v);
        }

        return collect((array) $v)
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->map(fn ($s) => mb_substr($s, 0, 60))
            ->take(6)
            ->values()
            ->all();
    }

    /** System prompt: isian admin, atau berkas bawaan. */
    public function systemPrompt(): string
    {
        $custom = trim((string) $this->settings->chatbot('system_prompt'));
        if ($custom !== '') {
            return $custom;
        }
        $file = config('chatbot.system_prompt_file');

        return is_string($file) && is_file($file) ? trim((string) file_get_contents($file)) : '';
    }

    private function bool(string $key): bool
    {
        $v = $this->settings->chatbot($key);

        return is_bool($v) ? $v : in_array(strtolower((string) $v), ['1', 'true', 'on', 'yes'], true);
    }

    private function int(string $key, int $default, int $min, int $max): int
    {
        $v = $this->settings->chatbot($key);

        return is_numeric($v) ? max($min, min($max, (int) $v)) : $default;
    }

    private function float(string $key, float $default, float $min, float $max): float
    {
        $v = $this->settings->chatbot($key);

        return is_numeric($v) ? max($min, min($max, (float) $v)) : $default;
    }
}
