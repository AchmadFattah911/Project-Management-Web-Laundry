@props(['order'])

{{-- Isian pelanggan di langkah Detail & Konfirmasi yang dibutuhkan petugas outlet. Pakai eager load 'checkout'. --}}
@if ($order->checkout?->drop_off_date)
    <div class="mt-1 text-[11px] font-semibold text-sky-700">Antar ke outlet: {{ $order->checkout->drop_off_date->translatedFormat('d M Y') }}</div>
@endif
@foreach (array_filter([$order->notes, $order->checkout?->notes]) as $note)
    <div class="mt-0.5 text-[11px] italic text-slate-600">"{{ $note }}"</div>
@endforeach
