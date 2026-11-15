<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public const TABS = [
        'all' => 'Semua',
        'unpaid' => 'Perlu dibayar',
        'upcoming' => 'Akan datang',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('status'), self::TABS) ? $request->query('status') : 'all';
        $base = $request->user()->bookings()->with(['space', 'review']);

        $scope = fn ($q, string $t) => match ($t) {
            'unpaid' => $q->where(fn ($w) => $w
                ->where(fn ($p) => $p->where('status', 'pending')->where('expires_at', '>', now()))
                ->orWhere(fn ($p) => $p->where('status', 'awaiting_approval')->where('approval_deadline_at', '>', now()))),
            'upcoming' => $q->where('status', 'confirmed')->where('end_at', '>=', now()),
            'completed' => $q->whereIn('status', ['completed', 'no_show']),
            'cancelled' => $q->where('status', 'cancelled'),
            default => $q,
        };

        $counts = [];
        foreach (array_keys(self::TABS) as $t) {
            $counts[$t] = $scope($request->user()->bookings(), $t)->count();
        }

        return view('buyer.bookings.index', [
            'bookings' => $scope($base, $tab)->latest()->paginate(8)->withQueryString(),
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => $counts,
        ]);
    }
}
