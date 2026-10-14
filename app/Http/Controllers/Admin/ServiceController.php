<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $search   = $request->input('search', '');
        $category = $request->input('category', '');

        $query = Service::withCount('orders')
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhere('category', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($category, fn($q) => $q->where('category', $category))
            ->latest();

        $services = $query->paginate(10)->withQueryString();

        $stats = [
            'total'     => Service::count(),
            'tersedia'  => Service::where('status', 'tersedia')->count(),
            'sibuk'     => Service::where('status', 'sibuk')->count(),
            'nonaktif'  => Service::where('status', 'tidak_tersedia')->count(),
        ];

        return view('admin.services.index', compact('services', 'stats', 'search', 'category'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateService($request);

        $slug = Str::slug($validated['name']);
        $counter = 1;
        while (Service::where('slug', $slug)->exists()) {
            $slug = Str::slug($validated['name']) . '-' . $counter;
            $counter++;
        }

        Service::create([
            ...$validated,
            'slug' => $slug,
            'unit' => $validated['unit'] ?? 'Mesin',
        ]);

        return back()->with('success', 'Layanan berhasil ditambahkan.');
    }

    public function update(Request $request, Service $service)
    {
        $validated = $this->validateService($request, $service);

        $slug = $service->slug;
        if ($service->name !== $validated['name']) {
            $slug = Str::slug($validated['name']);
            $counter = 1;
            while (Service::where('slug', $slug)->where('id', '!=', $service->id)->exists()) {
                $slug = Str::slug($validated['name']) . '-' . $counter;
                $counter++;
            }
        }

        $service->update([
            ...$validated,
            'slug' => $slug,
            'unit' => $validated['unit'] ?? 'Mesin',
        ]);

        return back()->with('success', 'Layanan berhasil diperbarui.');
    }

    /**
     * Validasi input form layanan, lalu olah features (teks dipisah koma -> array)
     * dan upload gambar ke disk public.
     */
    private function validateService(Request $request, ?Service $service = null): array
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'category'     => ['required', Rule::in(['Self Service', 'Non-Self Service'])],
            'price'        => 'required|numeric|min:0',
            'estimate'     => 'required|string|max:100',
            'unit'         => 'nullable|string|max:50',
            'status'       => ['required', Rule::in(['tersedia', 'sibuk', 'tidak_tersedia'])],
            'description'  => 'nullable|string',
            'tag'          => 'nullable|string|max:50',
            'features'     => 'nullable|string|max:1000',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_image' => 'nullable|boolean',
        ]);

        $validated['features'] = collect(explode(',', $validated['features'] ?? ''))
            ->map(fn ($feature) => trim($feature))
            ->filter()
            ->values()
            ->all() ?: null;

        if ($request->hasFile('image')) {
            if ($service?->image) {
                Storage::disk('public')->delete($service->image);
            }
            $validated['image'] = $request->file('image')->store('services', 'public');
        } elseif ($request->boolean('remove_image') && $service?->image) {
            Storage::disk('public')->delete($service->image);
            $validated['image'] = null;
        } else {
            unset($validated['image']);
        }

        unset($validated['remove_image']);

        return $validated;
    }

    public function destroy(Service $service)
    {
        if ($service->orders()->exists()) {
            return back()->with('error', 'Layanan tidak dapat dihapus karena masih memiliki riwayat pesanan terkait. Anda dapat mengubah statusnya menjadi tidak tersedia.');
        }

        if ($service->image) {
            Storage::disk('public')->delete($service->image);
        }

        $service->delete();

        return back()->with('success', 'Layanan berhasil dihapus.');
    }
}
