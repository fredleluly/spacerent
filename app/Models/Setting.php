<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengaturan kunci-nilai yang bisa diubah dari panel admin. Gunakan SettingsService, bukan model ini langsung. */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_secret'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }
}
