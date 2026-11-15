<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function create(Request $request)
    {
        return view('auth.login', ['redirect' => $this->safeRedirect($request->query('redirect'))]);
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Email atau password salah.']);
        }

        $request->session()->regenerate();
        $user = Auth::user();

        if (($redirect = $this->safeRedirect($request->input('redirect'))) && $this->allowedFor($user, $redirect)) {
            return redirect()->to($redirect);
        }

        // Tujuan "intended" bisa sisa dari kunjungan lama (mis. /admin saat belum login). Pakai hanya bila sesuai peran.
        $intended = $request->session()->pull('url.intended');
        if ($intended && $this->allowedFor($user, $intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to($user->homeRoute());
    }

    /** Area panel hanya untuk perannya; halaman publik boleh untuk siapa saja. */
    private function allowedFor($user, string $url): bool
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        return match (true) {
            str_starts_with($path, '/admin') => $user->isAdmin(),
            str_starts_with($path, '/penjual') => $user->isSeller(),
            str_starts_with($path, '/akun') => $user->isBuyer(),
            default => true,
        };
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /** Hanya izinkan URL pada host yang sama (cegah open redirect). */
    private function safeRedirect(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }
        $host = parse_url($url, PHP_URL_HOST);

        return $host && $host === request()->getHost() ? $url : null;
    }
}
