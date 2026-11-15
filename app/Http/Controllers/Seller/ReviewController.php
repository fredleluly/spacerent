<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = Review::with(['space', 'user', 'photos'])
            ->whereHas('space', fn ($q) => $q->where('user_id', auth()->id()))
            ->when($request->query('unreplied'), fn ($q) => $q->whereNull('reply'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('seller.reviews.index', compact('reviews'));
    }

    public function reply(Request $request, Review $review)
    {
        abort_unless($review->space && $review->space->user_id === auth()->id(), 403);

        if ($review->reply !== null) {
            return back()->with('error', 'Ulasan ini sudah Anda balas.');
        }

        $data = $request->validate(['reply' => ['required', 'string', 'max:600']]);
        $review->forceFill(['reply' => $data['reply'], 'replied_at' => now()])->save();

        return back()->with('success', 'Balasan terkirim.');
    }
}
