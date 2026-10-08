@extends('layout')
@section('title','Edit prices')
@section('subtitle','Changes apply to new orders. Existing orders keep their saved prices.')
@section('action')<a class="btn secondary" href="/products">← Back to Products & pricing</a>@endsection
@section('content')
<form method="post" action="/products/{{ $product->id }}" class="card">@csrf
    <h2>{{ $product->name }}</h2><p>{{ $product->sku }} · {{ $product->unit }}</p>
    @include('product-prices-fields')
    <div class="inline section-gap"><button>Save prices</button><a class="btn secondary" href="/products">Cancel</a></div>
</form>
@endsection
