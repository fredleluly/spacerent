<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    private function rules(bool $withPhotos): array
    {
        $rules = [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'is_anonymous' => ['nullable', 'boolean'],
        ];
        foreach (array_keys(Review::SUB_RATINGS) as $key) {
            $rules[$key] = ['nullable', 'integer', 'between:1,5'];
        }
        if ($withPhotos) {
            $rules['photos'] = ['nullable', 'array', 'max:4'];
            $rules['photos.*'] = ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072'];
        }

        return $rules;
    }

    public function store(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        if (! $booking->isReviewable()) {
            return back()->with('error', 'Booking ini belum bisa diulas atau sudah diulas.');
        }

        $data = $request->validate($this->rules(true), [
            'rating.required' => 'Pilih rating bintang terlebih dahulu.',
            'photos.max' => 'Maksimal 4 foto.',
            'photos.*.image' => 'File harus berupa gambar.',
            'photos.*.mimes' => 'Format foto harus JPG, PNG, atau WebP.',
            'photos.*.max' => 'Ukuran tiap foto maksimal 3 MB.',
        ]);

        DB::transaction(function () use ($request, $booking, $data) {
            $review = Review::create([
                'booking_id' => $booking->id,
                'space_id' => $booking->space_id,
                'user_id' => $request->user()->id,
                'is_anonymous' => $request->boolean('is_anonymous'),
                'status' => 'published',
            ] + collect($data)->except(['photos', 'is_anonymous'])->all());

            foreach ($request->file('photos', []) as $file) {
                $review->photos()->create(['path' => $file->store('reviews', 'public')]);
            }
        });

        $booking->space->refreshRating();

        return redirect()->to(route('booking.show', $booking).'#ulasan')->with('success', 'Terima kasih! Ulasan Anda sudah tampil.');
    }

    public function update(Request $request, Review $review)
    {
        abort_unless($review->user_id === $request->user()->id, 403);

        if (! $review->isEditable()) {
            return back()->with('error', 'Ulasan hanya bisa diubah dalam '.config('spacerent.review_edit_days').' hari setelah ditulis.');
        }

        $data = $request->validate($this->rules(false));
        $review->update(collect($data)->except('is_anonymous')->all() + ['is_anonymous' => $request->boolean('is_anonymous')]);
        $review->space->refreshRating();

        return back()->with('success', 'Ulasan diperbarui.');
    }

    public function report(Request $request, Review $review)
    {
        $review->increment('reported_count');

        return back()->with('success', 'Terima kasih, laporan Anda akan ditinjau admin.');
    }
}
