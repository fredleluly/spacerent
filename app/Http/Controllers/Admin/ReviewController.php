<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = Review::with(['space', 'user', 'photos'])
            ->when($request->query('reported'), fn ($q) => $q->where('reported_count', '>', 0))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.reviews.index', compact('reviews'));
    }

    /** Sembunyikan/tampilkan ulasan. Rating ruangan dihitung ulang. */
    public function toggle(Review $review)
    {
        $review->forceFill(['status' => $review->status === 'published' ? 'hidden' : 'published', 'reported_count' => 0])->save();
        $review->space->refreshRating();

        return back()->with('success', $review->status === 'hidden' ? 'Ulasan disembunyikan.' : 'Ulasan ditampilkan kembali.');
    }
}
