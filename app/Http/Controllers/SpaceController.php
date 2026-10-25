<?php

namespace App\Http\Controllers;

use App\Models\Space;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'in:'.implode(',', Space::AREAS)],
            'capacity' => ['nullable', 'in:'.implode(',', array_keys(Space::CAPACITIES))],
            'category' => ['nullable', 'in:'.implode(',', array_keys(Space::CATEGORIES))],
        ]);

        $spaces = Space::filter($filters)->orderByDesc('rating')->orderBy('name')->get();

        return view('home', [
            'spaces' => $spaces,
            'filters' => $filters,
            'total' => Space::count(),
        ]);
    }

    public function show(Space $space)
    {
        $related = Space::where('category', $space->category)
            ->whereKeyNot($space->id)
            ->limit(3)
            ->get();

        return view('spaces.show', compact('space', 'related'));
    }
}
