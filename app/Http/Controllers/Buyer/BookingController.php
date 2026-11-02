<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('status', 'all');

        $query = $request->user()->bookings()->with('space')->latest();

        match ($tab) {
            'unpaid' => $query->where('status', 'pending')->where('payment_status', 'unpaid')->where('expires_at', '>', now()),
            'confirmed' => $query->where('status', 'confirmed'),
            'completed' => $query->where('status', 'completed'),
            'cancelled' => $query->where('status', 'cancelled'),
            default => null,
        };

        return view('buyer.bookings.index', [
            'bookings' => $query->paginate(8)->withQueryString(),
            'tab' => $tab,
        ]);
    }
}
