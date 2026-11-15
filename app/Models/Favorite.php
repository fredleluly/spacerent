<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    protected $fillable = ['user_id', 'space_id'];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
