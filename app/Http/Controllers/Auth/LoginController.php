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

        if ($redirect = $this->safeRedirect($request->input('redirect'))) {
            return redirect()->to($redirect);
        }

        return redirect()->intended($user->homeRoute());
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
