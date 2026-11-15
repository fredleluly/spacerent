<?php

namespace App\Services\Chatbot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Google Gemini lewat REST (generateContent) dengan function calling. Kunci dikirim lewat header, bukan URL. */
class GeminiProvider implements LlmProvider
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(private readonly array $cfg) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function chat(string $system, array $messages, array $tools, callable $executor): string
    {
        $contents = collect($messages)->map(fn ($m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ])->values()->all();

        for ($round = 0; $round <= self::MAX_TOOL_ROUNDS; $round++) {
            $body = [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => (float) $this->cfg['temperature'],
                    'maxOutputTokens' => (int) $this->cfg['max_output_tokens'],
                ],
            ];
            // Putaran terakhir tanpa tool agar model wajib menjawab dengan data yang sudah ada.
            if ($tools && $round < self::MAX_TOOL_ROUNDS) {
                $body['tools'] = [['functionDeclarations' => array_map(fn ($t) => $this->declaration($t), $tools)]];
            }

            $json = $this->post($body);
            $parts = $json['candidates'][0]['content']['parts'] ?? [];
            $calls = array_values(array_filter($parts, fn ($p) => isset($p['functionCall'])));

            if (! $calls) {
                $text = trim(collect($parts)->pluck('text')->filter()->implode(''));
                if ($text === '') {
                    $reason = $json['candidates'][0]['finishReason'] ?? ($json['promptFeedback']['blockReason'] ?? 'kosong');
                    throw new ProviderException("Gemini tidak mengembalikan teks ({$reason}).");
                }

                return $text;
            }

            $contents[] = ['role' => 'model', 'parts' => $parts];
            $responses = [];
            foreach ($calls as $p) {
                $fc = $p['functionCall'];
                $args = is_array($fc['args'] ?? null) ? $fc['args'] : [];
                $responses[] = ['functionResponse' => [
                    'name' => $fc['name'],
                    'response' => ['result' => $executor((string) $fc['name'], $args)],
                ]];
            }
            $contents[] = ['role' => 'user', 'parts' => $responses];
        }

        throw new ProviderException('Gemini terus meminta tool tanpa jawaban akhir.');
    }

    private function post(array $body): array
    {
        $key = (string) $this->cfg['gemini_api_key'];
        if ($key === '') {
            throw new ProviderException('Kunci API Gemini belum diisi.');
        }
        $url = self::ENDPOINT.rawurlencode((string) $this->cfg['gemini_model']).':generateContent';

        try {
            $res = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout((int) $this->cfg['timeout_seconds'])->acceptJson()->asJson()->post($url, $body);
        } catch (ConnectionException $e) {
            throw new ProviderException('Tidak dapat terhubung ke Gemini (jaringan atau timeout).');
        }

        if ($res->failed()) {
            $msg = (string) ($res->json('error.message') ?? '');
            $msg = str_replace($key, '[kunci]', $msg);
            throw new ProviderException('Gemini HTTP '.$res->status().($msg !== '' ? ': '.mb_substr($msg, 0, 160) : ''));
        }

        return $res->json() ?? [];
    }

    /** Skema Gemini memakai tipe huruf besar dan tidak mengenal beberapa kata kunci JSON Schema. */
    private function declaration(array $tool): array
    {
        $decl = ['name' => $tool['name'], 'description' => $tool['description']];
        $params = $this->schema($tool['parameters'] ?? []);
        if (! empty($params['properties'])) {
            $decl['parameters'] = $params;
        }

        return $decl;
    }

    private function schema(mixed $node): mixed
    {
        if ($node instanceof \stdClass) {
            return [];
        }
        if (! is_array($node)) {
            return $node;
        }
        $out = [];
        foreach ($node as $k => $v) {
            if ($k === 'type' && is_string($v)) {
                $out[$k] = strtoupper($v);
            } elseif ($k === 'properties') {
                $props = [];
                foreach ((array) $v as $name => $def) {
                    $props[$name] = $this->schema($def);
                }
                $out[$k] = $props;
            } elseif ($k === 'enum') {
                $out[$k] = array_values(array_map('strval', $v));
            } else {
                $out[$k] = $this->schema($v);
            }
        }

        return $out;
    }
}
