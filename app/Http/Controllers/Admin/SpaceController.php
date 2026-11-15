<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesSpaceForm;
use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    use HandlesSpaceForm;

    public function index(Request $request)
    {
        $spaces = Space::query()->with('owner')
            ->when($request->query('q'), fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.spaces.index', compact('spaces'));
    }

    public function create()
    {
        return view('admin.spaces.form', ['space' => $this->newSpace()]);
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
        if ($this->hasUpcomingCommitments($space)) {
            return back()->with('error', 'Ruangan tidak bisa dihapus: masih ada booking aktif, lunas, atau refund yang belum selesai.');
        }

        $space->delete(); // soft delete: riwayat transaksi dan ulasan tetap tersimpan

        return redirect()->route('admin.spaces.index')->with('success', 'Ruangan dihapus.');
    }
}
