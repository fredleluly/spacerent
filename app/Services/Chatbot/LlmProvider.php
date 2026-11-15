<?php

namespace App\Services\Chatbot;

/** Kontrak penyedia LLM. Implementasi menjalankan loop tool-call sendiri dan mengembalikan teks akhir. */
interface LlmProvider
{
    public const MAX_TOOL_ROUNDS = 4;

    /**
     * @param  list<array{role:string, content:string}>  $messages  riwayat + pesan terbaru (role user|assistant)
     * @param  list<array{name:string, description:string, parameters:array}>  $tools
     * @param  callable(string, array): array  $executor  menjalankan tool, mengembalikan data
     *
     * @throws ProviderException
     */
    public function chat(string $system, array $messages, array $tools, callable $executor): string;

    public function name(): string;
}
