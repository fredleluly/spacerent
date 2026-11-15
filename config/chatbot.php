<?php

/*
 * Bawaan chatbot. Nilai yang disimpan admin di tabel settings (kunci "chatbot.<nama>")
 * menimpa bawaan ini. Baca lewat app(SettingsService::class)->chatbot('<nama>').
 */
return [
    'defaults' => [
        'enabled' => true,
        // rules = tanpa AI eksternal (mesin aturan membaca database), gemini, ollama
        'provider' => 'rules',
        'gemini_model' => 'gemini-2.0-flash',
        'ollama_base_url' => 'http://localhost:11434',
        'ollama_model' => 'llama3.1',
        'temperature' => 0.3,
        'max_output_tokens' => 700,
        'timeout_seconds' => 25,
        'rate_limit_per_minute' => 12,   // per sesi/IP
        'max_message_chars' => 500,
        'history_turns' => 6,            // giliran percakapan yang dikirim ke model
        'welcome' => 'Halo! Saya asisten SpaceRent. Tanyakan ruangan, harga, jadwal, atau kebijakan batal. Saya juga bisa mencari ruangan terdekat dari lokasi Anda.',
        'suggestions' => ['Cari ruangan terdekat', 'Ruang meeting untuk 10 orang', 'Bagaimana kebijakan refund?', 'Cara memesan'],
        'log_conversations' => true,
        'system_prompt' => null,         // null = pakai resources/chatbot/system-prompt.md
    ],

    // Kunci yang disimpan terenkripsi.
    'secrets' => ['gemini_api_key'],

    'system_prompt_file' => resource_path('chatbot/system-prompt.md'),

    // Alamat penghubung manusia bila bot tidak bisa membantu.
    'handoff' => [
        'whatsapp' => env('SPACERENT_WHATSAPP', '6281234567890'),
        'email' => env('SPACERENT_EMAIL', 'halo@spacerent.id'),
    ],
];
