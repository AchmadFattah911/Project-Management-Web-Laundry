<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $role   = $request->input('role', '');
        $perPage = 10;

        $query = User::query()
            ->when($search, fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($role, fn($q) => $q->where('role', $role))
            ->latest();

        $users = $query->paginate($perPage)->withQueryString()->through(fn($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'phone'      => $u->phone,
            'address'    => $u->address,
            'role'       => $u->role,
            'verified'   => !is_null($u->email_verified_at),
            'joined'     => $u->created_at->format('d M Y'),
        ]);

        // ── Stats ────────────────────────────────────────────
        $stats = [
            'total'    => User::count(),
            'admin'    => User::where('role', 'admin')->count(),
            'operator' => User::where('role', 'operator')->count(),
            'kurir'    => User::where('role', 'kurir')->count(),
            'pelanggan'=> User::where('role', 'pelanggan')->count(),
        ];

        // ── Chart: Distribusi Role ────────────────────────────
        $distribution = [
            $stats['admin'],
            $stats['operator'],
            $stats['kurir'],
            $stats['pelanggan'],
        ];

        // ── Chart: Registrasi 8 minggu terakhir ─────────────
        $weeklyGrowth = collect(range(7, 0))->map(function ($i) {
            $start = Carbon::now()->subWeeks($i)->startOfWeek();
            $end   = Carbon::now()->subWeeks($i)->endOfWeek();
            return [
                'label' => 'W' . Carbon::now()->subWeeks($i)->weekOfYear,
                'value' => User::whereBetween('created_at', [$start, $end])->count(),
            ];
        });

        // ── Chart: Status Verifikasi ──────────────────────────
        $verified   = User::whereNotNull('email_verified_at')->count();
        $unverified = User::whereNull('email_verified_at')->count();

        // ── Chart: Registrasi 4 bulan per role ───────────────
        $growthPerRole = collect(range(3, 0))->map(function ($i) {
            $month = Carbon::now()->subMonths($i);
            return [
                'label'    => $month->format('M'),
                'admin'    => User::where('role', 'admin')->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
                'operator' => User::where('role', 'operator')->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
                'kurir'    => User::where('role', 'kurir')->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
            ];
        });

        return Inertia::render('dashboard/admin/manajemen-users', [
            'users'          => $users,
            'stats'          => $stats,
            'filters'        => ['search' => $search, 'role' => $role],
            'chartData'      => [
                'distribution'  => $distribution,
                'weeklyGrowth'  => $weeklyGrowth,
                'verified'      => [$verified, $unverified],
                'growthPerRole' => $growthPerRole,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string',
            'role'     => ['required', Rule::in(['admin', 'operator', 'kurir', 'pelanggan'])],
        ]);

        User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string',
            'role'     => ['required', Rule::in(['admin', 'operator', 'kurir', 'pelanggan'])],
        ]);

        $data = collect($validated)->except('password')->toArray();
        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return back()->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun yang sedang aktif.');
        }

        $user->delete();

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }
}
