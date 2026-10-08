@extends('layout')
@section('title','Add product')
@section('subtitle','Create a product, then set its opening stock separately in Stock.')
@section('action')<a class="btn secondary" href="/products">← Back to Products & pricing</a>@endsection
@section('content')
<form method="post" action="/products" class="card">@csrf
    <h2>Product details</h2>
    <div class="fields section-gap">
        <div><label for="product-sku">SKU *</label><input id="product-sku" name="sku" maxlength="50" required value="{{ old('sku') }}" placeholder="e.g. NEW001"><p class="orders-search-help">Unique code using letters, numbers, dots, hyphens or underscores.</p></div>
        <div><label for="product-name">Product name *</label><input id="product-name" name="name" maxlength="200" required value="{{ old('name') }}"></div>
        <div><label for="product-unit">Stock / selling unit *</label><input id="product-unit" name="unit" list="product-unit-options" maxlength="30" required value="{{ old('unit') }}" placeholder="e.g. bottle"><datalist id="product-unit-options">@foreach(['box','sachet','bottle','tube','piece','pack','bag','jar','set'] as $unit)<option value="{{ $unit }}">@endforeach</datalist><p class="orders-search-help">Choose a suggested unit or type another. Use box / sachet for these packing units.</p></div>
    </div>
    <h2 class="section-gap">Selling prices</h2>
    @include('product-prices-fields')
    <div class="inline section-gap"><button>Add product</button><a class="btn secondary" href="/products">Cancel</a></div>
</form>
@endsection
