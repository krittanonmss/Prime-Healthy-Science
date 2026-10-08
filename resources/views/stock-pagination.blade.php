@if($paginator->hasPages())
<nav class="orders-pagination" aria-label="{{ $ariaLabel ?? 'Stock list pages' }}">
    @if($paginator->onFirstPage())<span class="btn secondary" aria-disabled="true">← Previous</span>
    @else<a class="btn secondary" rel="prev" href="{{ $paginator->previousPageUrl() }}">← Previous</a>@endif
    @if($paginator->currentPage()>3)<a class="btn secondary" href="{{ $paginator->url(1) }}">1</a><span>…</span>@endif
    @foreach($paginator->getUrlRange(max(1,$paginator->currentPage()-2),min($paginator->lastPage(),$paginator->currentPage()+2)) as $page=>$url)
        <a class="btn {{ $page===$paginator->currentPage() ? '' : 'secondary' }}" href="{{ $url }}" @if($page===$paginator->currentPage()) aria-current="page" @endif>{{ $page }}</a>
    @endforeach
    @if($paginator->currentPage()<$paginator->lastPage()-2)<span>…</span><a class="btn secondary" href="{{ $paginator->url($paginator->lastPage()) }}">{{ $paginator->lastPage() }}</a>@endif
    @if($paginator->hasMorePages())<a class="btn secondary" rel="next" href="{{ $paginator->nextPageUrl() }}">Next →</a>
    @else<span class="btn secondary" aria-disabled="true">Next →</span>@endif
    <span>Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
</nav>
@endif
