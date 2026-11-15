<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Pengaturan dinamis dari database. Nilai rahasia (API key) disimpan terenkripsi dengan APP_KEY
 * dan tidak pernah dikirim balik ke browser (gunakan mask()).
 */
class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    /** @return array<string, array{value: ?string, secret: bool}> */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::all()
            ->mapWithKeys(fn (Setting $s) => [$s->key => ['value' => $s->value, 'secret' => $s->is_secret]])
            ->all());
    }

    public function has(string $key): bool
    {
        $row = $this->all()[$key] ?? null;

        return $row !== null && $row['value'] !== null && $row['value'] !== '';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->all()[$key] ?? null;
        if ($row === null || $row['value'] === null || $row['value'] === '') {
            return $default;
        }
        if ($row['secret']) {
            try {
                return Crypt::decryptString($row['value']);
            } catch (\Throwable) {
                return $default; // APP_KEY berubah: anggap belum diisi
            }
        }

        return $row['value'];
    }

    public function set(string $key, ?string $value, bool $secret = false): void
    {
        $stored = ($value !== null && $value !== '' && $secret) ? Crypt::encryptString($value) : $value;
        Setting::updateOrCreate(['key' => $key], ['value' => $stored, 'is_secret' => $secret]);
        Cache::forget(self::CACHE_KEY);
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    /** Tampilan aman untuk UI: "AIza****wxyz" atau null bila kosong. */
    public function mask(string $key): ?string
    {
        $v = (string) $this->get($key, '');
        if ($v === '') {
            return null;
        }

        return strlen($v) <= 8 ? str_repeat('*', strlen($v)) : substr($v, 0, 4).str_repeat('*', 6).substr($v, -4);
    }

    /** Pengaturan chatbot: nilai tersimpan menimpa bawaan di config/chatbot.php. */
    public function chatbot(string $key): mixed
    {
        return $this->get('chatbot.'.$key, config('chatbot.defaults.'.$key));
    }
}
