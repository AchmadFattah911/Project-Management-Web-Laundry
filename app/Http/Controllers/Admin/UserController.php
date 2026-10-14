<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $role   = $request->input('role', '');
        $perPage = 10;

        $query = User::query()
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%")
                   ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($role, fn($q) => $q->where('role', $role))
            ->latest();

        $users = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total'     => User::count(),
            'admin'     => User::where('role', UserRole::ADMIN->value)->count(),
            'pegawai'   => User::where('role', UserRole::PEGAWAI->value)->count(),
            'pelanggan' => User::where('role', UserRole::PELANGGAN->value)->count(),
        ];

        return view('admin.users.index', compact('users', 'stats', 'search', 'role'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone'    => 'nullable|string|max:20',
            'role'     => ['required', Rule::in(['admin', 'pegawai', 'pelanggan'])],
        ]);

        $user = new User([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone'    => $validated['phone'] ?? null,
        ]);
        $user->role = UserRole::from($validated['role']);
        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'phone'    => 'nullable|string|max:20',
            'role'     => ['required', Rule::in(['admin', 'pegawai', 'pelanggan'])],
        ]);

        // Admin tidak boleh menurunkan role dirinya sendiri
        if ($user->id === auth()->id() && $validated['role'] !== 'admin') {
            return back()->with('error', 'Admin tidak diperbolehkan menurunkan role akunnya sendiri.');
        }

        // Tidak boleh menurunkan role admin terakhir di sistem
        $currentUserRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        if ($currentUserRole === 'admin' && $validated['role'] !== 'admin') {
            $adminCount = User::where('role', UserRole::ADMIN->value)->count();
            if ($adminCount <= 1) {
                return back()->with('error', 'Tidak dapat menurunkan peran admin terakhir di sistem.');
            }
        }

        $userData = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->fill($userData);
        $user->role = UserRole::from($validated['role']);
        $user->save();

        return back()->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun yang sedang aktif digunakan.');
        }

        // Admin terakhir tidak boleh dihapus
        $currentUserRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        if ($currentUserRole === 'admin') {
            $adminCount = User::where('role', UserRole::ADMIN->value)->count();
            if ($adminCount <= 1) {
                return back()->with('error', 'Tidak dapat menghapus admin terakhir di sistem.');
            }
        }

        // User yang punya order tidak boleh dihapus
        if ($user->orders()->exists()) {
            return back()->with('error', 'Pengguna tidak dapat dihapus karena memiliki riwayat pesanan laundry.');
        }

        $user->delete();

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }
}
