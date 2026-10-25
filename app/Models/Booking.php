<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'code', 'space_id', 'name', 'email', 'phone', 'company', 'date',
        'start_time', 'duration', 'guests', 'total', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = 'SR-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
