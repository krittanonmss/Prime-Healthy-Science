@extends('layout')
@section('title','Products & pricing')
@section('subtitle','Manage your catalog and selling prices for each customer category.')
@section('action')<a class="btn" href="/products/create">＋ Add product</a>@endsection
@section('content')
<form method="get" action="/products" class="card" style="margin-bottom:22px">
    <label for="product-search">Search products</label>
    <div class="stock-balance-search"><input id="product-search" name="q" type="search" maxlength="200" value="{{ request('q') }}" placeholder="Search by product name or SKU…"><button>Search</button><a class="btn secondary" href="/products">Clear</a></div>
    <details class="stock-list-filters section-gap"><summary>More filters @if((int)request('per_page',10)!==10)<span class="badge">Active</span>@endif</summary>
        <div class="section-gap"><label for="products-per-page">Products per page</label><select id="products-per-page" name="per_page" style="max-width:160px">
            @foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',10)===$size)>{{ $size }}</option>@endforeach
        </select></div>
    </details>
</form>
<div id="products-results" data-ajax-region>
<p style="margin-bottom:12px">{{ number_format($products->total()) }} products found · Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} · Prices in THB</p>
<div class="card table-card"><table><thead><tr><th>Product</th><th>Unit</th><th>Retail</th><th>Hospital</th><th>Pharmacy</th><th></th></tr></thead><tbody>
@forelse($products as $p)
<tr><td><b>{{ $p->name }}</b><small>{{ $p->sku }}</small></td><td>{{ $p->unit }}</td>
    @foreach(['retail','hospital','pharmacy'] as $key)<td>{{ $p->$key===null ? 'Not set' : '฿'.number_format($p->$key/100,2) }}</td>@endforeach
    <td><a class="btn secondary" href="/products/{{ $p->id }}/pricing">Edit prices</a>@if(!$p->stock_initialized)<small><a class="link" href="/stock/update?stock_product_id={{ $p->id }}">Set opening stock →</a></small>@endif</td>
</tr>
@empty<tr><td colspan="6">No matching products found.</td></tr>@endforelse
</tbody></table></div>
@include('stock-pagination',['paginator'=>$products,'ariaLabel'=>'Product list pages'])
</div>
@endsection
