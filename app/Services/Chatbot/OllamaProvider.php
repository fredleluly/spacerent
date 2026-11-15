<?php

namespace App\Services\Chatbot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Ollama lokal lewat /api/chat dengan tool calling (model harus mendukung tools, mis. llama3.1, qwen2.5). */
class OllamaProvider implements LlmProvider
{
    public function __construct(private readonly array $cfg) {}

    public function name(): string
    {
        return 'ollama';
    }

    public function chat(string $system, array $messages, array $tools, callable $executor): string
    {
        $msgs = array_merge([['role' => 'system', 'content' => $system]], $messages);
        $toolDefs = array_map(fn ($t) => ['type' => 'function', 'function' => [
            'name' => $t['name'],
            'description' => $t['description'],
            'parameters' => $this->params($t['parameters'] ?? []),
        ]], $tools);

        for ($round = 0; $round <= self::MAX_TOOL_ROUNDS; $round++) {
            $body = [
                'model' => $this->cfg['ollama_model'],
                'messages' => $msgs,
                'stream' => false,
                'options' => ['temperature' => (float) $this->cfg['temperature'], 'num_predict' => (int) $this->cfg['max_output_tokens']],
            ];
            if ($toolDefs && $round < self::MAX_TOOL_ROUNDS) {
                $body['tools'] = $toolDefs;
            }

            $message = $this->post($body)['message'] ?? [];
            $calls = $message['tool_calls'] ?? [];

            if (! $calls) {
                $text = trim((string) ($message['content'] ?? ''));
                // Sebagian model menyisipkan blok <think>...</think>; buang sebelum ditampilkan.
                $text = trim((string) preg_replace('#<think>.*?</think>#s', '', $text));
                if ($text === '') {
                    throw new ProviderException('Ollama tidak mengembalikan teks.');
                }

                return $text;
            }

            $msgs[] = ['role' => 'assistant', 'content' => (string) ($message['content'] ?? ''), 'tool_calls' => $calls];
            foreach ($calls as $c) {
                $fn = $c['function'] ?? [];
                $args = $fn['arguments'] ?? [];
                if (is_string($args)) {
                    $args = json_decode($args, true) ?: [];
                }
                $msgs[] = [
                    'role' => 'tool',
                    'tool_name' => (string) ($fn['name'] ?? ''),
                    'content' => json_encode($executor((string) ($fn['name'] ?? ''), (array) $args), JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        throw new ProviderException('Ollama terus meminta tool tanpa jawaban akhir.');
    }

    /** @return list<string> */
    public function models(): array
    {
        try {
            $res = Http::timeout(min(8, (int) $this->cfg['timeout_seconds']))->acceptJson()->get($this->cfg['ollama_base_url'].'/api/tags');
        } catch (\Throwable) {
            return [];
        }

        return $res->successful() ? collect($res->json('models', []))->pluck('name')->filter()->values()->all() : [];
    }

    private function post(array $body): array
    {
        try {
            $res = Http::timeout((int) $this->cfg['timeout_seconds'])->acceptJson()->asJson()->post($this->cfg['ollama_base_url'].'/api/chat', $body);
        } catch (ConnectionException $e) {
            throw new ProviderException('Tidak dapat terhubung ke Ollama di '.$this->cfg['ollama_base_url'].' (apakah `ollama serve` berjalan?).');
        }

        if ($res->failed()) {
            $msg = (string) ($res->json('error') ?? '');
            throw new ProviderException('Ollama HTTP '.$res->status().($msg !== '' ? ': '.mb_substr($msg, 0, 160) : ''));
        }

        return $res->json() ?? [];
    }

    /** JSON Schema standar; objek properties kosong harus berupa objek JSON, bukan array. */
    private function params(array $schema): array
    {
        if (isset($schema['properties']) && ($schema['properties'] instanceof \stdClass || $schema['properties'] === [])) {
            $schema['properties'] = new \stdClass;
        }

        return $schema ?: ['type' => 'object', 'properties' => new \stdClass];
    }
}
