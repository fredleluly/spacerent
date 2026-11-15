<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Concerns\HandlesSpaceForm;
use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    use HandlesSpaceForm;

    public function index(Request $request)
    {
        $spaces = auth()->user()->spaces()
            ->when($request->query('q'), fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('seller.spaces.index', compact('spaces'));
    }

    public function create()
    {
        return view('seller.spaces.form', ['space' => $this->newSpace()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['image'] = $this->resolveImage($request, null);

        $space = new Space($data);
        $space->user_id = auth()->id();
        $space->save();

        return redirect()->route('seller.spaces.index')->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function edit(Space $space)
    {
        $this->authorizeOwner($space);

        return view('seller.spaces.form', ['space' => $space->load('blackouts')]);
    }

    public function update(Request $request, Space $space)
    {
        $this->authorizeOwner($space);

        $data = $this->validated($request, $space);
        if ($data['name'] !== $space->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $space->id);
        }
        $data['image'] = $this->resolveImage($request, $space);

        $space->update($data);

        return redirect()->route('seller.spaces.index')->with('success', 'Ruangan berhasil diperbarui.');
    }

    public function destroy(Space $space)
    {
        $this->authorizeOwner($space);

        if ($this->hasUpcomingCommitments($space)) {
            return back()->with('error', 'Ruangan tidak bisa dihapus: masih ada booking aktif, lunas, atau refund yang belum selesai. Nonaktifkan ruangan agar tidak menerima pesanan baru.');
        }

        $space->delete(); // soft delete: riwayat transaksi dan ulasan tetap tersimpan

        return redirect()->route('seller.spaces.index')->with('success', 'Ruangan dihapus.');
    }

    private function authorizeOwner(Space $space): void
    {
        abort_unless($space->user_id === auth()->id(), 403);
    }
}
