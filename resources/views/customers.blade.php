@extends('layout')
@section('title','Customers')
@section('subtitle','Find customer details and manage their default pricing category.')
@section('action')<a class="btn" href="/customers/create">＋ Add customer</a>@endsection
@section('content')
<form method="get" action="/customers" class="card" style="margin-bottom:22px">
    <label for="customer-directory-search">Search customers</label>
    <div class="stock-balance-search"><input id="customer-directory-search" name="q" type="search" maxlength="200" value="{{ request('q') }}" placeholder="Search by name, phone, tax ID or address…"><button>Search</button><a class="btn secondary" href="/customers">Clear</a></div>
    <details class="stock-list-filters section-gap"><summary>More filters
        @if(request()->filled('channel') || (int)request('per_page',10)!==10)<span class="badge">Active</span>@endif
    </summary><div class="fields section-gap">
        <div><label for="customer-channel-filter">Default pricing</label><select id="customer-channel-filter" name="channel"><option value="">All categories</option>
            @foreach(['retail'=>'Retail','hospital'=>'Hospital','pharmacy'=>'Pharmacy'] as $value=>$label)<option value="{{ $value }}" @selected(request('channel')===$value)>{{ $label }}</option>@endforeach
        </select></div>
        <div><label for="customers-per-page">Customers per page</label><select id="customers-per-page" name="per_page">
            @foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',10)===$size)>{{ $size }}</option>@endforeach
        </select></div>
    </div></details>
</form>
<div id="customers-results" data-ajax-region>
<p style="margin-bottom:12px">{{ number_format($customers->total()) }} customers found · Showing {{ $customers->firstItem() ?? 0 }}–{{ $customers->lastItem() ?? 0 }}</p>
<div class="card table-card"><table><thead><tr><th>Customer</th><th>Phone</th><th>Tax ID</th><th>Default pricing</th><th>Delivery address</th><th></th></tr></thead><tbody>
@forelse($customers as $c)
<tr><td><b>{{ $c->name }}</b></td><td>{{ $c->phone }}</td><td>{{ $c->tax_id ?: '—' }}</td><td><span class="badge {{ $c->channel }}">{{ ['retail'=>'Retail','hospital'=>'Hospital','pharmacy'=>'Pharmacy'][$c->channel] }}</span></td><td>{{ $c->address }}</td><td><a class="btn secondary" href="/customers/{{ $c->id }}/edit">Edit customer</a></td></tr>
@empty<tr><td colspan="6">No matching customers found.</td></tr>@endforelse
</tbody></table></div>
@include('stock-pagination',['paginator'=>$customers,'ariaLabel'=>'Customer list pages'])
</div>
@endsection
