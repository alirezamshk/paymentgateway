@if ($paginator->hasPages())
    <div class="pager">
        <span>{{ __('Page :page of :last', ['page' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }} · {{ __(':total results', ['total' => number_format($paginator->total())]) }}</span>
        <div class="pages">
            @if ($paginator->onFirstPage())<span class="btn secondary sm" aria-disabled="true" style="opacity:.5">{{ __('Previous') }}</span>
            @else<a class="btn secondary sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>@endif
            @if ($paginator->hasMorePages())<a class="btn secondary sm" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
            @else<span class="btn secondary sm" aria-disabled="true" style="opacity:.5">{{ __('Next') }}</span>@endif
        </div>
    </div>
@endif
