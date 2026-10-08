<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Prime Healthy · Sales workspace</title><link rel="stylesheet" href="/app.css"></head><body>
<aside><a class="brand" href="/">p<span>+</span><div>prime healthy<small>SALES WORKSPACE</small></div></a><div class="nav-label">WORKSPACE</div><nav class="workspace-nav" aria-label="Workspace">
@foreach([''=>'Overview','orders'=>'Sales orders','customers'=>'Customers','reports'=>'Sales reports'] as $url=>$label)
<a class="{{ request()->is($url ?: '/') || ($url && request()->is($url.'/*')) ? 'active' : '' }}" href="/{{ $url }}"><span>{{ [''=>'◈','orders'=>'▤','customers'=>'◎','reports'=>'↗'][$url] }}</span>{{ $label }}</a>
@if($url === 'orders')
<details class="nav-product-group" @if(request()->is('products','products/*','stock','stock/*')) open @endif>
    <summary class="{{ request()->is('products','products/*','stock','stock/*') ? 'active' : '' }}"><span class="nav-product-icon" aria-hidden="true">▦</span>Products<span class="nav-product-chevron" aria-hidden="true">›</span></summary>
    <div class="nav-product-links">
        <a href="/stock" class="{{ request()->is('stock','stock/*') ? 'active' : '' }}" @if(request()->is('stock','stock/*')) aria-current="page" @endif>Stock</a>
        <a href="/products" class="{{ request()->is('products','products/*') ? 'active' : '' }}" @if(request()->is('products','products/*')) aria-current="page" @endif>Products &amp; pricing</a>
    </div>
</details>
@endif
@endforeach
</nav><div class="aside-note"><span class="dot"></span> Client presentation<small>Demo workspace · THB</small></div><div class="profile"><b>PH</b><div>Prime Healthy Science<small>Sales & operations</small></div></div></aside>
<main><header><div>Workspace <span>/</span> @yield('title')</div><div class="header-right"><span class="demo">DEMO</span>{{ now()->format('d M Y') }}<b>PH</b></div></header><section class="content"><div class="page-heading"><div><div class="eyebrow">PRIME HEALTHY SCIENCE</div><h1>@yield('title')</h1><p>@yield('subtitle')</p></div>@yield('action')</div>
@if(session('success'))<div class="notice">✓ {{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
@yield('content')<footer>Prime Healthy · Connected sales, pricing & fulfillment <span>Sample customers · Workbook product prices</span></footer></section></main><script src="/searchable-select.js"></script><script src="/list-navigation.js"></script>@yield('scripts')</body></html>
