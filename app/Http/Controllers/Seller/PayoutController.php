<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(PayoutService $payouts)
    {
        $seller = auth()->user();
        $recent = Booking::with('space')
            ->whereHas('space', fn ($q) => $q->withTrashed()->where('user_id', $seller->id))
            ->whereNotNull('paid_at')->latest('paid_at')->limit(15)->get();

        return view('seller.payouts.index', [
            'seller' => $seller,
            'balances' => $payouts->balances($seller),
            'recent' => $recent,
            'service' => $payouts,
            'history' => Payout::where('user_id', $seller->id)->latest('requested_at')->limit(20)->get(),
            'min' => (int) config('spacerent.payout_min', 50000),
            'commission' => (float) config('spacerent.commission_percent', 10),
        ]);
    }

    public function saveBank(Request $request)
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:60'],
            'bank_account_number' => ['required', 'string', 'regex:/^[0-9\- ]{6,30}$/'],
            'bank_account_name' => ['required', 'string', 'max:100'],
        ], ['bank_account_number.regex' => 'Nomor rekening hanya berisi angka.']);

        $request->user()->forceFill($data)->save();

        return back()->with('success', 'Data rekening disimpan. Penarikan berikutnya memakai rekening ini.');
    }

    public function request(PayoutService $payouts)
    {
        try {
            $payout = $payouts->request(auth()->user());
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Permintaan penarikan Rp '.number_format($payout->amount, 0, ',', '.').' dikirim. Admin memprosesnya ke rekening Anda.');
    }
}
