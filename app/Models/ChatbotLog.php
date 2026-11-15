<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan percakapan chatbot (tanpa koordinat dan tanpa kunci API). Hanya ditulis bila log_conversations aktif. */
class ChatbotLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['session_key', 'user_id', 'question', 'answer', 'provider', 'tools', 'latency_ms', 'is_error'];

    protected function casts(): array
    {
        return ['tools' => 'array', 'is_error' => 'boolean', 'latency_ms' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
