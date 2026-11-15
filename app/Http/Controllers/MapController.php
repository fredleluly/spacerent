<?php

namespace App\Http\Controllers;

use App\Models\Space;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Data penanda peta (publik). Hanya ruangan tersedia yang punya koordinat. */
class MapController extends Controller
{
    public function markers(Request $request): JsonResponse
    {
        $f = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(Space::CATEGORIES))],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = (int) ($f['limit'] ?? 100);

        $scope = function (Builder $query) use ($f) {
            $query->when($f['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
                ->when(isset($f['q']) && trim($f['q']) !== '', function ($q) use ($f) {
                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($f['q'])).'%';
                    $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('district', 'like', $like)
                        ->orWhere('area', 'like', $like)->orWhere('address_line', 'like', $like));
                });
        };

        if (isset($f['lat'], $f['lng'])) {
            $spaces = Space::nearest((float) $f['lat'], (float) $f['lng'], $limit, $scope, isset($f['radius_km']) ? (float) $f['radius_km'] : null);
        } else {
            $query = Space::query()->withCoordinates()->where('is_available', true);
            $scope($query);
            $spaces = $query->sortBy(null)->limit($limit)->get();
        }

        return response()->json(['spaces' => $spaces->map->toMapMarker()->values()]);
    }
}
