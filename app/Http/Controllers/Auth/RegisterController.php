<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function create(Request $request)
    {
        return view('auth.register', ['role' => $request->query('role') === 'seller' ? 'seller' : 'buyer']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{9,20}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:buyer,seller'],
        ], [
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        // role dipaksa hanya buyer|seller (admin tidak pernah dari form publik)
        $user = new User(collect($data)->only(['name', 'email', 'phone', 'password'])->all());
        $user->role = $data['role'];
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return $user->isSeller()
            ? redirect()->route('seller.dashboard')
            : redirect()->intended(route('home'));
    }
}
