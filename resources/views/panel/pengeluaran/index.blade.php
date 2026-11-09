@extends('layouts.dashboard')

@section('title', 'Pengeluaran Operasional')

@section('content')
@use('App\Models\Expense', 'Expense')
@use('App\Support\TransactionLabels', 'L')
@php $tz = \App\Support\BusinessCalendar::timezone(); @endphp
<div class="space-y-6">
    <x-panel.header title="Pengeluaran Operasional" subtitle="Wajib foto nota. Hanya pengeluaran yang disetujui admin yang masuk laporan keuangan.">
        @can('pengeluaran.create')
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'tambah-pengeluaran')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-xs">
                <x-icon name="plus" class="w-4 h-4" /><span>Catat Pengeluaran</span>
            </button>
        @endcan
    </x-panel.header>

    <x-card padding="p-4">
        <form method="GET" class="grid grid-cols-2 lg:grid-cols-12 gap-3">
            <select name="status" class="lg:col-span-3 rounded-xl border-slate-200 text-sm" aria-label="Status">
                <option value="">Semua status</option>
                @foreach (Expense::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="category" class="lg:col-span-3 rounded-xl border-slate-200 text-sm" aria-label="Kategori">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Dari" class="lg:col-span-2 rounded-xl border-slate-200 text-sm" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Sampai" class="lg:col-span-2 rounded-xl border-slate-200 text-sm" />
            <button type="submit" class="col-span-2 lg:col-span-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs">Filter</button>
        </form>
    </x-card>

    @if ($expenses->isEmpty())
        <x-card padding="p-0"><x-empty-state title="Belum ada pengeluaran" description="Pengeluaran yang dicatat akan tampil di sini." /></x-card>
    @else
        <x-table :headers="['Tanggal', 'Kategori', 'Deskripsi', 'Nominal', 'Oleh', 'Nota', 'Status', '']">
            @foreach ($expenses as $expense)
                <tr data-expense="{{ $expense->id }}">
                    <td class="px-5 py-3 text-xs whitespace-nowrap">{{ $expense->expense_date->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 text-xs">{{ $expense->category?->name }}</td>
                    <td class="px-5 py-3 text-xs max-w-xs">{{ $expense->description }}@if ($expense->decision_note)<br><span class="text-slate-500">Catatan admin: {{ $expense->decision_note }}</span>@endif</td>
                    <td class="px-5 py-3 text-xs font-bold whitespace-nowrap">{{ L::rupiah($expense->amount) }}</td>
                    <td class="px-5 py-3 text-xs">{{ $expense->creator?->name }}</td>
                    <td class="px-5 py-3 text-xs"><a href="{{ route('panel.pengeluaran.receipt', $expense) }}" target="_blank" rel="noopener" class="font-bold text-sky-600">Lihat</a></td>
                    <td class="px-5 py-3"><x-badge :variant="['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$expense->status]" size="sm">{{ Expense::STATUS_LABELS[$expense->status] }}</x-badge></td>
                    <td class="px-5 py-3">
                        @if ($expense->status === Expense::STATUS_PENDING)
                            @can('pengeluaran.approve')
                                <div class="flex flex-col gap-2 min-w-48">
                                    <x-panel.form :action="route('panel.pengeluaran.approve', $expense)" :token="false" :confirm="'Setujui pengeluaran ' . L::rupiah($expense->amount) . '?'">
                                        <x-panel.submit variant="success" class="w-full">Setujui</x-panel.submit>
                                    </x-panel.form>
                                    <x-panel.form :action="route('panel.pengeluaran.reject', $expense)" :token="false" class="flex gap-1">
                                        <input type="text" name="reason" required minlength="10" placeholder="Alasan tolak" class="w-full rounded-xl border-slate-200 text-xs" />
                                        <x-panel.submit variant="secondary">Tolak</x-panel.submit>
                                    </x-panel.form>
                                </div>
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
        {{ $expenses->links() }}
    @endif

    @can('pengeluaran.kategori.manage')
        <x-card title="Master Kategori">
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($categories as $category)
                    <x-panel.form :action="route('panel.pengeluaran.category.toggle', $category)" :token="false">
                        <button type="submit" class="px-3 py-1.5 rounded-full text-xs font-bold border {{ $category->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-400 line-through' }}" title="Klik untuk {{ $category->is_active ? 'nonaktifkan' : 'aktifkan' }}">{{ $category->name }}</button>
                    </x-panel.form>
                @endforeach
            </div>
            <x-panel.form :action="route('panel.pengeluaran.category.store')" :token="false" class="flex gap-2 max-w-md">
                <input type="text" name="name" required minlength="2" maxlength="100" placeholder="Kategori baru" class="w-full rounded-xl border-slate-200 text-sm" />
                <x-panel.submit>Tambah</x-panel.submit>
            </x-panel.form>
        </x-card>
    @endcan
</div>

@can('pengeluaran.create')
    <x-modal name="tambah-pengeluaran" title="Catat Pengeluaran" maxWidth="md">
        <x-panel.form :action="route('panel.pengeluaran.store')" :files="true" class="space-y-4">
            <input type="hidden" name="_modal" value="tambah-pengeluaran" />
            <label class="block text-xs"><span class="font-bold text-slate-700">Kategori *</span>
                <select name="category_id" required class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    @foreach ($categories->where('is_active', true) as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select></label>
            <x-panel.field label="Nominal (Rp)" name="amount" type="number" required min="1" />
            <x-panel.field label="Tanggal" name="expense_date" type="date" :value="\App\Support\BusinessCalendar::today()" required :max="\App\Support\BusinessCalendar::today()" />
            <label class="block text-xs"><span class="font-bold text-slate-700">Deskripsi *</span>
                <textarea name="description" rows="2" required minlength="5" maxlength="1000" class="mt-1 w-full rounded-xl border-slate-200 text-sm">{{ old('description') }}</textarea>
                @error('description')<span class="text-[11px] text-rose-600">{{ $message }}</span>@enderror</label>
            <label class="block text-xs"><span class="font-bold text-slate-700">Foto nota * <span class="font-normal text-slate-500">(JPG/PNG/WEBP/PDF, maks. {{ (int) (config('finance.expense.receipt_max_kb') / 1024) }} MB)</span></span>
                <input type="file" name="receipt" required accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-1 w-full text-sm" />
                @error('receipt')<span class="text-[11px] text-rose-600">{{ $message }}</span>@enderror</label>
            <div class="flex justify-end"><x-panel.submit>Simpan</x-panel.submit></div>
        </x-panel.form>
    </x-modal>
@endcan
@endsection
