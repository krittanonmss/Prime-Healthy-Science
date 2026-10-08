@extends('layout')
@section('title','Update stock')
@section('subtitle','Receive products, adjust counted balances, or set opening stock.')
@section('action')<a class="btn secondary" href="/stock">← Back to Stock</a>@endsection
@section('content')
<div class="card"><h2>Receive / Adjust / Set opening balance</h2><p>Receive adds units. Adjust sets the final counted balance and can increase or decrease stock. Reserved quantities cannot be removed.</p><form id="stock-form" method="post" class="section-gap">
@csrf<div class="fields"><div><label>Product</label><select id="stock-product" name="stock_product_id" data-searchable data-placeholder="Type to search products…" data-empty-message="No matching products found." data-search-label="Search products to receive or adjust stock">
@foreach($products as $p)<option value="{{ $p->id }}" data-initialized="{{ (int)$p->stock_initialized }}" data-minimum="{{ $p->stock_minimum }}" @selected((int)old('stock_product_id',request('stock_product_id'))===$p->id)>{{ $p->sku }} · {{ $p->name }} ({{ $p->unit }})</option>
@endforeach</select></div><div><label>Movement type</label><select name="type" id="stock-type"></select></div><div><label>Quantity received or actual counted balance</label><input name="quantity" type="number" min="0" max="100000000" required value="{{ old('quantity') }}"></div><div><label>Document date (balance updates immediately)</label><input name="date" type="date" max="{{ now()->toDateString() }}" required value="{{ old('date',now()->toDateString()) }}"></div><div><label>Alert when available quantity is at or below</label><input name="minimum" id="stock-minimum" type="number" min="0" required></div><div><label>Reason / Document reference</label><input name="reason" maxlength="500" required value="{{ old('reason') }}"></div></div><button class="section-gap">Save stock</button></form></div>

@endsection
@section('scripts')
<script>
const product=document.getElementById('stock-product');
const previousType=@json(old('type'));
const previousMinimum=@json(old('minimum'));
function selectStock(restore=false){
    const o=product.selectedOptions[0];
    if(!o){document.querySelector('#stock-form button').disabled=true;return;}
    document.getElementById('stock-form').action='/stock/'+o.value;
    const type=document.getElementById('stock-type');
    type.replaceChildren();
    for(const [value,label] of (o.dataset.initialized==='1'?[['receive','Receive stock'],['adjust','Set actual counted balance']]:[['opening','Opening balance']])){
        const option=document.createElement('option');option.value=value;option.textContent=label;type.append(option);
    }
    if(restore && previousType && [...type.options].some(option=>option.value===previousType))type.value=previousType;
    document.getElementById('stock-minimum').value=restore && previousMinimum!==null?previousMinimum:o.dataset.minimum;
}
product.onchange=()=>selectStock();selectStock(true);
</script>
@endsection
