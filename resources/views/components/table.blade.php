@props([
    'headers' => [],
])

<div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-xs">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-slate-200 text-left text-sm']) }}>
        @if (count($headers) > 0)
            <thead class="bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="px-5 py-3.5">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
            {{ $slot }}
        </tbody>
    </table>
</div>
