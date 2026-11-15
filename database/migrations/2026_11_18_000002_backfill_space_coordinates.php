<?php

use App\Support\JakartaGeo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Mengisi koordinat ruangan lama yang masih kosong dari nama kawasannya (idempoten; yang sudah terisi tidak disentuh). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('spaces')->whereNull('latitude')->orWhereNull('longitude')->orderBy('id')->get(['id', 'slug', 'district', 'area', 'address_line'])
            ->each(function ($s) {
                [$lat, $lng] = JakartaGeo::coordinates((string) $s->district, (string) $s->area, (string) $s->slug);
                DB::table('spaces')->where('id', $s->id)->update([
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'address_line' => $s->address_line ?: JakartaGeo::address((string) $s->district, (string) $s->area),
                ]);
            });
    }

    public function down(): void
    {
        // Koordinat hasil isian otomatis tidak dibedakan dari yang diisi penjual, jadi tidak dihapus.
    }
};
