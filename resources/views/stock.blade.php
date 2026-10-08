
@extends('layout')

@section('title','Stock')

@section('subtitle','Review available stock and product movements in one warehouse.')
@section('action')<a class="btn" href="{{ route('stock.edit') }}">＋ Update stock</a>@endsection

@section('content')
<div class="stock-tabs" role="tablist" aria-label="Stock workspace">
    @foreach(['balances'=>'Stock balances','history'=>'Stock movement history'] as $key=>$label)
        <a id="stock-tab-{{ $key }}" class="stock-tab" role="tab" href="{{ request()->fullUrlWithQuery(['tab'=>$key]) }}" aria-controls="stock-panel-{{ $key }}" aria-selected="{{ $activeTab===$key ? 'true' : 'false' }}" tabindex="{{ $activeTab===$key ? '0' : '-1' }}" data-stock-tab="{{ $key }}">{{ $label }}</a>
    @endforeach
</div>
<section id="stock-panel-balances" role="tabpanel" aria-labelledby="stock-tab-balances" tabindex="0" @if($activeTab!=='balances') hidden @endif>
<div class="card"><h2>Stock balances</h2><p>Available = On hand − Reserved · Quantities use each SKU’s unit · Adjusted balances cannot be below reservations.</p>
<form method="get" action="/stock" class="section-gap stock-balance-filter">
    <input type="hidden" name="tab" value="balances">
    <label for="stock-balance-search">Search products</label>
    <div class="stock-balance-search"><input id="stock-balance-search" name="balance_q" value="{{ request('balance_q') }}" type="search" maxlength="200" placeholder="Search by product name or SKU…"><button>Search</button><a class="btn secondary" href="/stock?tab=balances">Clear</a></div>
    <details class="stock-list-filters section-gap"><summary>More filters
        @if((int)request('balance_per_page',20)!==20)<span class="badge">Active</span>@endif
    </summary><div class="section-gap"><label for="balance-per-page">Products per page</label><select id="balance-per-page" name="balance_per_page">
        @foreach([20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('balance_per_page',20)===$size)>{{ $size }}</option>@endforeach
    </select></div></details>
</form>
<div id="stock-balance-results" data-ajax-region data-page-parameter="balances_page" data-query-keys="balance_q,balance_per_page,balances_page"><p class="orders-search-help section-gap" role="status" aria-live="polite">{{ number_format($balances->total()) }} products found · Showing {{ $balances->firstItem() ?? 0 }}–{{ $balances->lastItem() ?? 0 }}</p>
<div class="stock-table-scroll"><table><thead><tr><th>Product</th><th>Unit</th><th>On hand</th><th>Reserved</th><th>Available</th><th>Status</th></tr></thead><tbody id="stock-balance-rows">

@forelse($balances as $p)<tr><td>{{ $p->sku }}<small>{{ $p->name }}</small></td><td>{{ $p->unit }}</td><td>{{ $p->stock_on_hand }}</td><td>{{ $p->stock_reserved }}</td><td>{{ $p->stock_on_hand-$p->stock_reserved }}</td><td>{{ !$p->stock_initialized ? 'Not initialized' : ($p->stock_on_hand-$p->stock_reserved <= $p->stock_minimum ? 'Low stock' : 'Available') }}</td></tr>
@empty
<tr><td colspan="6">No matching products found.</td></tr>
@endforelse
</tbody></table></div>
@include('stock-pagination', ['paginator'=>$balances])
</div>
</div>
</section>
<section id="stock-panel-history" role="tabpanel" aria-labelledby="stock-tab-history" tabindex="0" @if($activeTab!=='history') hidden @endif>
<div class="card"><h2>Stock movement history</h2>
<form method="get" action="/stock" class="section-gap">
<input type="hidden" name="tab" value="history">
<label for="stock-history-search">Search movements</label>
<div class="stock-balance-search"><input id="stock-history-search" name="history_q" type="search" maxlength="200" value="{{ request('history_q') }}" placeholder="Search product, SKU, order number or reference…"><button>Search</button><a class="btn secondary" href="/stock?tab=history">Clear</a></div>
<details class="stock-list-filters section-gap"><summary>Filters
@if(request()->filled('product_id') || (int)request('history_per_page',10)!==10)<span class="badge">Active</span>@endif
</summary><div class="inline section-gap"><label class="stock-history-product">Product<select name="product_id" data-searchable data-placeholder="Type to search products…" data-empty-message="No matching products found." data-search-label="Search products to filter history"><option value="">All products</option>
@foreach($products as $p)<option value="{{ $p->id }}" 
@selected(request('product_id')==$p->id)>{{ $p->sku }} · {{ $p->name }}</option>
@endforeach</select></label><div><label for="history-per-page">Movements per page</label><select id="history-per-page" name="history_per_page">
@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('history_per_page',10)===$size)>{{ $size }}</option>@endforeach
</select></div></div></details></form><div id="stock-history-results" data-ajax-region data-page-parameter="history_page" data-query-keys="product_id,history_q,history_per_page,history_page"><div class="stock-table-scroll section-gap"><table><thead><tr><th>Document date / Recorded at</th><th>SKU</th><th>Movement type</th><th>In / Out</th><th>Balance after movement</th><th>Reference</th></tr></thead><tbody>
@forelse($movements as $m)<tr><td>{{ $m->movement_date }}<small>{{ $m->created_at }}</small></td><td>{{ $m->sku }}<small>{{ $m->unit }}</small></td><td>{{ ['opening'=>'Opening balance','receive'=>'Receive','adjust'=>'Adjust','reserve'=>'Reserve','release'=>'Release reservation','dispatch'=>'Dispatch'][$m->type] }}</td><td>{{ $m->quantity }}</td><td>{{ $m->balance }}</td><td>{{ $m->reason }} 
@if($m->order_id)<a href="/orders/{{ $m->order_id }}">PH-{{ str_pad($m->order_id,5,'0',STR_PAD_LEFT) }}</a>
@endif</td></tr>
@empty<tr><td colspan="6">No matching stock movements found.</td></tr>
@endforelse</tbody></table></div><p class="orders-search-help section-gap">{{ number_format($movements->total()) }} movements found · Showing {{ $movements->firstItem() ?? 0 }}–{{ $movements->lastItem() ?? 0 }}</p>
@include('stock-pagination', ['paginator'=>$movements])
</div>
</div>

</section>
@endsection

@section('scripts')
<script src="/stock-tabs.js"></script>
@endsection
