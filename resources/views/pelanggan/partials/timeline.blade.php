{{-- Timeline dari order_status_logs (lihat App\Support\TransactionTimeline). Variabel: $timeline --}}
@php
    $dots = ['success' => 'bg-emerald-500', 'danger' => 'bg-rose-500', 'info' => 'bg-sky-500'];
    $timezone = config('laundry.report_timezone', 'Asia/Jakarta');
@endphp

<section class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs" aria-label="Riwayat status">
    <h2 class="text-sm font-extrabold text-slate-900 mb-4">Riwayat Status</h2>
    <ol class="relative border-l border-slate-200 ml-1.5 space-y-4">
        @foreach ($timeline as $row)
            <li class="ml-4" data-timeline-row>
                <span class="absolute -left-[5px] mt-1.5 w-2.5 h-2.5 rounded-full {{ $dots[$row['tone']] ?? $dots['info'] }}" aria-hidden="true"></span>
                <p class="text-xs font-bold text-slate-800">{{ $row['label'] }}</p>
                <time class="text-[11px] text-slate-500" datetime="{{ $row['at']->toIso8601String() }}">
                    {{ $row['at']->copy()->timezone($timezone)->translatedFormat('d M Y, H:i') }} WIB
                </time>
            </li>
        @endforeach
    </ol>
</section>
