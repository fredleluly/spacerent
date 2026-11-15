<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Review;
use App\Models\Space;
use App\Support\RefundPolicy;
use Carbon\Carbon;
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
            'unit' => ['nullable', 'in:jam,hari,bulan'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'min_rating' => ['nullable', 'numeric', 'between:1,5'],
            'sort' => ['nullable', 'in:'.implode(',', array_keys(Space::SORTS))],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $query = Space::filter($filters)->sortBy($filters['sort'] ?? null);

        if (! empty($filters['date'])) {
            $day = Carbon::parse($filters['date'])->startOfDay();
            $query->where('is_available', true)
                ->whereDoesntHave('blackouts', fn ($q) => $q->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day))
                ->where(fn ($q) => $q->where('price_unit', 'jam')->orWhereDoesntHave('bookings', fn ($b) => $b
                    ->occupying()->where('starts_at', '<', $day->copy()->addDay())->where('end_at', '>', $day)));
            $dow = $day->dayOfWeek;
            $query->where(fn ($q) => $q->whereNull('open_days')->orWhereJsonContains('open_days', $dow));
        }

        $spaces = $query->paginate(9)->withQueryString();

        return view('home', [
            'spaces' => $spaces,
            'filters' => $filters,
            'total' => Space::count(),
            'heroSpaces' => Space::sortBy(null)->limit(3)->get(),
            'favoriteIds' => $request->user()
                ? Favorite::where('user_id', $request->user()->id)->pluck('space_id')->all()
                : [],
        ]);
    }

    public function show(Request $request, Space $space)
    {
        $related = Space::where('category', $space->category)
            ->whereKeyNot($space->id)
            ->limit(3)
            ->get();

        $published = $space->reviews()->where('status', 'published');
        $reviewSort = $request->query('rs', 'new');
        $reviews = (clone $published)->with(['user', 'photos'])
            ->when($request->boolean('photos'), fn ($q) => $q->whereHas('photos'))
            ->when($reviewSort === 'high', fn ($q) => $q->orderByDesc('rating')->latest())
            ->when($reviewSort === 'low', fn ($q) => $q->orderBy('rating')->latest())
            ->when(! in_array($reviewSort, ['high', 'low'], true), fn ($q) => $q->latest())
            ->paginate(5, ['*'], 'rp')->withQueryString();

        $breakdown = [];
        $total = max(1, (clone $published)->count());
        foreach ([5, 4, 3, 2, 1] as $star) {
            $n = (clone $published)->where('rating', $star)->count();
            $breakdown[$star] = ['count' => $n, 'percent' => (int) round($n / $total * 100)];
        }

        $subAverages = [];
        foreach (array_keys(Review::SUB_RATINGS) as $key) {
            $avg = (clone $published)->whereNotNull($key)->avg($key);
            if ($avg) {
                $subAverages[$key] = round($avg, 1);
            }
        }

        return view('spaces.show', [
            'space' => $space,
            'related' => $related,
            'reviews' => $reviews,
            'breakdown' => $breakdown,
            'subAverages' => $subAverages,
            'photoReviews' => (clone $published)->whereHas('photos')->count(),
            'isFavorite' => $request->user() ? Favorite::where('user_id', $request->user()->id)->where('space_id', $space->id)->exists() : false,
            'policy' => RefundPolicy::POLICIES[$space->cancellation_policy] ?? RefundPolicy::POLICIES['moderate'],
        ]);
    }
}
