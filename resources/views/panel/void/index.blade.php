@extends('layouts.dashboard')

@section('title', 'Persetujuan Void')

@section('content')
@use('App\Models\VoidRequest', 'VoidRequest')
@use('App\Support\TransactionLabels', 'L')
@php $tz = \App\Support\BusinessCalendar::timezone(); @endphp
<div class="space-y-6">
    <x-panel.header title="Persetujuan Void" subtitle="Void yang disetujui membuat entri pembalik di ledger pada tanggal persetujuan. Transaksi asli tidak diubah." />

    <nav class="flex gap-2" aria-label="Status pengajuan">
        @foreach (VoidRequest::STATUS_LABELS as $key => $label)
            <a href="{{ route('panel.void.index', ['status' => $key]) }}" @if ($status === $key) aria-current="page" @endif
               class="px-4 py-2 rounded-full text-xs font-bold border {{ $status === $key ? 'bg-sky-600 border-sky-600 text-white' : 'bg-white border-slate-200 text-slate-600' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($requests->isEmpty())
        <x-card padding="p-0"><x-empty-state title="Tidak ada pengajuan" description="Pengajuan void dari pegawai akan tampil di sini." /></x-card>
    @else
        <div class="space-y-3">
            @foreach ($requests as $void)
                <x-card padding="p-4">
                    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4" data-void="{{ $void->id }}">
                        <div class="text-xs space-y-1">
                            <a href="{{ route('panel.transaksi.show', $void->checkout) }}" class="font-mono font-bold text-sky-700">{{ $void->checkout->code }}</a>
                            · {{ $void->checkout->user?->name }} · {{ L::rupiah($void->checkout->total_amount) }} · {{ $void->checkout->isPaid() ? 'Lunas' : 'Belum lunas' }}
                            <p>Diajukan <span class="font-bold">{{ $void->requester?->name }}</span> {{ $void->created_at->timezone($tz)->format('d/m/Y H:i') }}</p>
                            <p class="text-slate-700">Alasan: {{ $void->reason }}</p>
                            @if ($void->decided_at)
                                <p class="text-slate-500">Diputuskan {{ $void->decider?->name }}{{ $void->decision_note ? ': ' . $void->decision_note : '' }}</p>
                            @endif
                        </div>
                        @if ($void->status === VoidRequest::STATUS_PENDING)
                            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                                <x-panel.form :action="route('panel.void.approve', $void)" :token="false" :confirm="'Setujui void ' . $void->checkout->code . '? Nominal lunas akan dibalik di ledger.'">
                                    <x-panel.submit variant="danger">Setujui Void</x-panel.submit>
                                </x-panel.form>
                                <x-panel.form :action="route('panel.void.reject', $void)" :token="false" class="flex gap-2">
                                    <input type="text" name="reason" required minlength="10" placeholder="Alasan menolak (min. 10)" class="rounded-xl border-slate-200 text-xs" />
                                    <x-panel.submit variant="secondary">Tolak</x-panel.submit>
                                </x-panel.form>
                            </div>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
        {{ $requests->links() }}
    @endif
</div>
@endsection
