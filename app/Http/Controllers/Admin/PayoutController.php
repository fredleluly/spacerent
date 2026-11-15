<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $payouts = Payout::with('user')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest('requested_at')->paginate(15)->withQueryString();

        return view('admin.payouts.index', [
            'payouts' => $payouts,
            'waiting' => Payout::where('status', 'requested')->sum('amount'),
        ]);
    }

    public function paid(Request $request, Payout $payout, PayoutService $service)
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:120']]);

        return $this->run(fn () => $service->markPaid($payout, $data['reference'] ?? null), 'Penarikan ditandai sudah ditransfer.');
    }

    public function reject(Request $request, Payout $payout, PayoutService $service)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:200']]);

        return $this->run(fn () => $service->reject($payout, $data['note']), 'Penarikan ditolak. Dana kembali ke saldo penjual.');
    }

    private function run(\Closure $fn, string $ok)
    {
        try {
            $fn();
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }
}
