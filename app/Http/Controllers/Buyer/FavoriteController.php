<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Space;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $spaces = Space::whereIn('id', Favorite::where('user_id', $request->user()->id)->pluck('space_id'))
            ->orderBy('name')->get();

        return view('buyer.favorites', compact('spaces'));
    }

    public function toggle(Request $request, Space $space)
    {
        $existing = Favorite::where('user_id', $request->user()->id)->where('space_id', $space->id);

        if ($existing->exists()) {
            $existing->delete();
            $message = 'Dihapus dari favorit.';
        } else {
            Favorite::create(['user_id' => $request->user()->id, 'space_id' => $space->id]);
            $message = 'Disimpan ke favorit.';
        }

        return back()->with('success', $message);
    }
}
