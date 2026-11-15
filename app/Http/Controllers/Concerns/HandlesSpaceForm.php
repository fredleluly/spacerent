<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Space;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Validasi, gambar, dan slug ruangan yang dipakai bersama panel penjual dan admin. */
trait HandlesSpaceForm
{
    protected function newSpace(): Space
    {
        return new Space([
            'price_unit' => 'jam', 'is_available' => true, 'open_time' => '08:00', 'close_time' => '22:00',
            'min_duration' => 1, 'buffer_minutes' => 0, 'lead_time_hours' => 2, 'max_advance_days' => 365,
            'instant_book' => true, 'cancellation_policy' => 'moderate', 'cleaning_fee' => 0,
        ]);
    }

    protected function validated(Request $request, ?Space $space = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:'.implode(',', array_keys(Space::CATEGORIES))],
            'area' => ['required', 'in:'.implode(',', Space::AREAS)],
            'district' => ['required', 'string', 'max:100'],
            // Lokasi di peta. Dibatasi wilayah Indonesia agar lintang/bujur yang tertukar langsung ketahuan.
            'latitude' => ['nullable', 'numeric', 'between:-11.5,6.5', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:94.5,141.5', 'required_with:latitude'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:5000'],
            'price' => ['required', 'integer', 'min:0'],
            'price_unit' => ['required', 'in:jam,hari,bulan'],
            'description' => ['required', 'string', 'max:3000'],
            'facilities' => ['nullable', 'string', 'max:2000'],
            'image_url' => [$space ? 'nullable' : 'required_without:image_file', 'nullable', 'url', 'max:500'],
            'image_file' => ['nullable', 'image', 'max:4096'],
            'gallery_files' => ['nullable', 'array', 'max:6'],
            'gallery_files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_gallery' => ['nullable', 'array'],
            'remove_gallery.*' => ['string'],
            'open_time' => ['sometimes', 'required', 'date_format:H:i'],
            'close_time' => ['sometimes', 'required', 'date_format:H:i', 'after:open_time'],
            'open_days' => ['sometimes', 'required', 'array', 'min:1'],
            'open_days.*' => ['integer', 'between:0,6'],
            'min_duration' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'max_duration' => ['nullable', 'integer', 'gte:min_duration', 'max:365'],
            'buffer_minutes' => ['sometimes', 'required', 'integer', 'min:0', 'max:240'],
            'lead_time_hours' => ['sometimes', 'required', 'integer', 'min:0', 'max:720'],
            'max_advance_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:730'],
            'cancellation_policy' => ['sometimes', 'required', 'in:'.implode(',', array_keys(RefundPolicy::POLICIES))],
            'cleaning_fee' => ['sometimes', 'required', 'integer', 'min:0', 'max:100000000'],
            'rules' => ['nullable', 'string', 'max:2000'],
            'checkin_info' => ['nullable', 'string', 'max:1000'],
        ], [
            'image_url.required_without' => 'Isi URL gambar atau unggah file gambar.',
            'close_time.after' => 'Jam tutup harus setelah jam buka.',
            'open_days.required' => 'Pilih minimal satu hari buka.',
            'latitude.between' => 'Lintang harus berada di wilayah Indonesia (sekitar -11 sampai 6).',
            'longitude.between' => 'Bujur harus berada di wilayah Indonesia (sekitar 95 sampai 141).',
            'latitude.required_with' => 'Isi lintang dan bujur bersamaan, atau kosongkan keduanya.',
            'longitude.required_with' => 'Isi lintang dan bujur bersamaan, atau kosongkan keduanya.',
        ]);

        $data['facilities'] = collect(preg_split('/\r\n|\r|\n/', $data['facilities'] ?? ''))
            ->map(fn ($f) => trim($f))->filter()->values()->all();
        $data['is_available'] = $request->boolean('is_available');
        $data['instant_book'] = $request->boolean('instant_book');

        if (isset($data['open_days'])) {
            $days = collect($data['open_days'])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();
            $data['open_days'] = count($days) === 7 ? null : $days;
        }
        if (! $space) {
            // Klien yang tidak mengirim aturan jadwal memakai default aman.
            $data += ['open_time' => '08:00', 'close_time' => '22:00', 'min_duration' => 1, 'buffer_minutes' => 0,
                'lead_time_hours' => 2, 'max_advance_days' => 365, 'cancellation_policy' => 'moderate', 'cleaning_fee' => 0];
        }
        if (! $request->has('instant_book') && $space) {
            $data['instant_book'] = $space->instant_book;
        } elseif (! $request->has('instant_book')) {
            $data['instant_book'] = true;
        }

        $data['gallery'] = $this->resolveGallery($request, $space);
        unset($data['image_url'], $data['image_file'], $data['gallery_files'], $data['remove_gallery']);

        return $data;
    }

    /** @return list<string> */
    protected function resolveGallery(Request $request, ?Space $space): array
    {
        $keep = collect($space?->gallery ?? []);
        foreach ($request->input('remove_gallery', []) as $remove) {
            if ($keep->contains($remove)) {
                $this->deleteStoredImage($remove);
                $keep = $keep->reject(fn ($g) => $g === $remove);
            }
        }
        foreach ($request->file('gallery_files', []) as $file) {
            $keep->push($file->store('spaces', 'public'));
        }

        return $keep->values()->take(8)->all();
    }

    protected function resolveImage(Request $request, ?Space $space): string
    {
        if ($request->hasFile('image_file')) {
            if ($space) {
                $this->deleteStoredImage($space->image);
            }

            return $request->file('image_file')->store('spaces', 'public');
        }

        if ($request->filled('image_url')) {
            if ($space && $space->image !== $request->input('image_url')) {
                $this->deleteStoredImage($space->image);
            }

            return $request->input('image_url');
        }

        return $space->image;
    }

    protected function deleteStoredImage(string $image): void
    {
        if (! str_starts_with($image, 'http')) {
            Storage::disk('public')->delete($image);
        }
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'ruangan';
        $slug = $base;
        $i = 2;
        while (Space::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** True bila masih ada booking aktif/lunas mendatang: ruangan tidak boleh dihapus. */
    protected function hasUpcomingCommitments(Space $space): bool
    {
        return $space->bookings()
            ->where(fn ($q) => $q->whereIn('status', ['confirmed', 'pending', 'awaiting_approval'])
                ->orWhereIn('payment_status', ['paid', 'refund_pending']))
            ->where('end_at', '>=', now())
            ->exists()
            || $space->bookings()->where('refund_status', 'pending')->exists();
    }
}
