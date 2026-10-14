@props([
    'paginator' => null,
])

@if ($paginator && $paginator->hasPages())
    <div class="px-4 py-3 flex items-center justify-between border-t border-slate-200 sm:px-6 bg-white rounded-b-xl">
        <div class="flex-1 flex justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-xs font-medium text-slate-400 bg-white border border-slate-300 cursor-not-allowed rounded-md">
                    Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">
                    Sebelumnya
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="ml-3 relative inline-flex items-center px-4 py-2 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">
                    Berikutnya
                </a>
            @else
                <span class="ml-3 relative inline-flex items-center px-4 py-2 text-xs font-medium text-slate-400 bg-white border border-slate-300 cursor-not-allowed rounded-md">
                    Berikutnya
                </span>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-xs text-slate-600">
                    Menampilkan
                    <span class="font-semibold text-slate-900">{{ $paginator->firstItem() ?? 0 }}</span>
                    sampai
                    <span class="font-semibold text-slate-900">{{ $paginator->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-slate-900">{{ $paginator->total() }}</span>
                    data
                </p>
            </div>

            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-xs -space-x-px" aria-label="Pagination">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-xs font-medium text-slate-400 cursor-not-allowed">
                            <span class="sr-only">Previous</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-xs font-medium text-slate-600 hover:bg-slate-50">
                            <span class="sr-only">Previous</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="z-10 bg-sky-600 text-white relative inline-flex items-center px-3.5 py-2 border border-sky-600 text-xs font-bold">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="bg-white border-slate-300 text-slate-600 hover:bg-slate-50 relative inline-flex items-center px-3.5 py-2 border text-xs font-medium">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-xs font-medium text-slate-600 hover:bg-slate-50">
                            <span class="sr-only">Next</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-xs font-medium text-slate-400 cursor-not-allowed">
                            <span class="sr-only">Next</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    @endif
                </nav>
            </div>
        </div>
    </div>
@endif
