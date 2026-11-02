<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role', 'all');
        $q = trim((string) $request->query('q', ''));

        $users = User::withCount(['spaces', 'bookings'])
            ->when(in_array($role, User::ROLES), fn ($query) => $query->where('role', $role))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('role')->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $counts = User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return view('admin.users.index', compact('users', 'role', 'q', 'counts'));
    }
}
