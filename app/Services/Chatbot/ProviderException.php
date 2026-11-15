<?php

namespace App\Services\Chatbot;

/** Kegagalan provider LLM (jaringan, kunci salah, kuota, respons kosong). Pesan sudah bersih dari rahasia. */
class ProviderException extends \RuntimeException {}
