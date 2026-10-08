<div class="fields section-gap">
@foreach(['retail'=>'Retail price (THB)','hospital'=>'Hospital price (THB)','pharmacy'=>'Pharmacy price (THB)'] as $key=>$label)
    <div><label for="product-price-{{ $key }}">{{ $label }}{{ $key==='retail' ? ' *' : ' (optional)' }}</label><input id="product-price-{{ $key }}" name="{{ $key }}" type="number" min="0.01" max="1000000" step="0.01" @required($key==='retail') value="{{ old($key,isset($product) && $product->$key!==null ? $product->$key/100 : '') }}"></div>
@endforeach
</div>
<p class="section-gap">Hospital and pharmacy prices may be left blank. When provided, they must differ from the retail price.</p>
