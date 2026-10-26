<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SpaceController extends Controller
{
    public function index(Request $request)
    {
        $spaces = Space::query()
            ->when($request->query('q'), fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.spaces.index', compact('spaces'));
    }

    public function create()
    {
        return view('admin.spaces.form', ['space' => new Space(['price_unit' => 'jam', 'rating' => 4.8, 'is_available' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['image'] = $this->resolveImage($request, null);

        Space::create($data);

        return redirect()->route('admin.spaces.index')->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function edit(Space $space)
    {
        return view('admin.spaces.form', compact('space'));
    }

    public function update(Request $request, Space $space)
    {
        $data = $this->validated($request, $space);
        if ($data['name'] !== $space->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $space->id);
        }
        $data['image'] = $this->resolveImage($request, $space);

        $space->update($data);

        return redirect()->route('admin.spaces.index')->with('success', 'Ruangan berhasil diperbarui.');
    }

    public function destroy(Space $space)
    {
        $this->deleteStoredImage($space->image);
        $space->delete();

        return redirect()->route('admin.spaces.index')->with('success', 'Ruangan berhasil dihapus.');
    }

    private function validated(Request $request, ?Space $space = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:'.implode(',', array_keys(Space::CATEGORIES))],
            'area' => ['required', 'in:'.implode(',', Space::AREAS)],
            'district' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:5000'],
            'price' => ['required', 'integer', 'min:0'],
            'price_unit' => ['required', 'in:jam,hari,bulan'],
            'rating' => ['required', 'numeric', 'min:0', 'max:5'],
            'description' => ['required', 'string', 'max:3000'],
            'facilities' => ['nullable', 'string', 'max:2000'],
            'image_url' => [$space ? 'nullable' : 'required_without:image_file', 'nullable', 'url', 'max:500'],
            'image_file' => ['nullable', 'image', 'max:4096'],
        ], [
            'image_url.required_without' => 'Isi URL gambar atau unggah file gambar.',
        ]);

        $data['facilities'] = collect(preg_split('/\r\n|\r|\n/', $data['facilities'] ?? ''))
            ->map(fn ($f) => trim($f))->filter()->values()->all();
        $data['is_available'] = $request->boolean('is_available');
        unset($data['image_url'], $data['image_file']);

        return $data;
    }

    private function resolveImage(Request $request, ?Space $space): string
    {
        if ($request->hasFile('image_file')) {
            if ($space) {
                $this->deleteStoredImage($space->image);
            }

            return $request->file('image_file')->store('spaces', 'public');
        }

        if ($request->filled('image_url')) {
            if ($space && $space->image !== $request->input('image_url')) {
                $this->deleteStoredImage($space->image);
            }

            return $request->input('image_url');
        }

        return $space->image;
    }

    private function deleteStoredImage(string $image): void
    {
        if (! str_starts_with($image, 'http')) {
            Storage::disk('public')->delete($image);
        }
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'ruangan';
        $slug = $base;
        $i = 2;
        while (Space::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
