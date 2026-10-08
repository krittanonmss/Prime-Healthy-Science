@extends('layout')
@section('title','Sales reports')
@section('subtitle','Review sales and outstanding balances by order date and customer category.')
@section('action')<a class="btn" href="/reports/export?{{ http_build_query(request()->only('q','from','to','channel','status','payment')) }}">↓ Export report</a>@endsection
@section('content')
<form method="get" action="/reports" class="card" style="margin-bottom:22px">
    <label for="report-search">Search sales</label>
    <div class="stock-balance-search"><input id="report-search" name="q" type="search" maxlength="200" value="{{ request('q') }}" placeholder="Search order, customer, salesperson or product…"><button>Search</button><a class="btn secondary" href="/reports">Clear</a></div>
    <details class="stock-list-filters section-gap"><summary>More filters
        @if(request()->filled('from') || request()->filled('to') || request()->filled('channel') || request()->filled('status') || request()->filled('payment') || (int)request('per_page',10)!==10)<span class="badge">Active</span>@endif
    </summary>@include('report-filter-fields',['includePageSize'=>true])</details>
</form>
<div id="reports-results" data-ajax-region>
<div class="metrics">
    <div class="metric"><small>Total sales</small><strong>฿{{ number_format($summary['sales']/100,2) }}</strong><em>{{ number_format($summary['count']) }} matching orders</em></div>
    <div class="metric"><small>Payments received</small><strong>฿{{ number_format($summary['paid']/100,2) }}</strong><em>Cumulative payments for matching orders</em></div>
    <div class="metric"><small>Outstanding balance</small><strong>฿{{ number_format($summary['outstanding']/100,2) }}</strong><em>Remaining amount to collect</em></div>
    <div class="metric"><small>Orders</small><strong>{{ number_format($summary['count']) }}</strong><em>Cancelled orders excluded</em></div>
</div>
<div class="card" style="margin-bottom:22px"><h2>Sales by customer category</h2><p>Totals cover every matching order across all pages. Dates filter order dates; received amounts include all payments recorded on those orders.</p>
    <div class="stock-table-scroll section-gap"><table><thead><tr><th>Category</th><th>Orders</th><th>Sales</th><th>Received</th><th>Outstanding</th></tr></thead><tbody>
    @foreach(['retail'=>'Retail','hospital'=>'Hospital','pharmacy'=>'Pharmacy'] as $key=>$label)
        <tr><td>{{ $label }}</td><td>{{ $channels->get($key)->order_count ?? 0 }}</td><td>฿{{ number_format(($channels->get($key)->sales ?? 0)/100,2) }}</td><td>฿{{ number_format(($channels->get($key)->paid ?? 0)/100,2) }}</td><td>฿{{ number_format((($channels->get($key)->sales ?? 0)-($channels->get($key)->paid ?? 0))/100,2) }}</td></tr>
    @endforeach
    </tbody></table></div>
</div>
<p style="margin-bottom:12px">{{ number_format($orders->total()) }} orders found · Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }}</p>
<div class="card table-card"><table><thead><tr><th>Order / Sale date</th><th>Customer</th><th>Category</th><th>Fulfillment</th><th>Total</th><th>Received</th><th>Outstanding</th><th></th></tr></thead><tbody>
@forelse($orders as $o)<tr><td><b>PH-{{ str_pad($o->id,5,'0',STR_PAD_LEFT) }}</b><small>{{ \Carbon\Carbon::parse($o->order_date ?: $o->created_at)->format('d M Y') }}</small></td><td>{{ $o->customer }}<small>{{ $o->salesperson }}</small></td><td><span class="badge {{ $o->channel }}">{{ ucfirst($o->channel) }}</span></td><td><span class="badge {{ $o->status }}">{{ ucfirst($o->status) }}</span></td><td>฿{{ number_format($o->total/100,2) }}</td><td>฿{{ number_format($o->paid/100,2) }}</td><td>฿{{ number_format(($o->total-$o->paid)/100,2) }}</td><td><a class="link" href="/orders/{{ $o->id }}">Open →</a></td></tr>
@empty<tr><td colspan="8">No matching sales found.</td></tr>@endforelse
</tbody></table></div>
@include('stock-pagination',['paginator'=>$orders,'ariaLabel'=>'Sales report pages'])
</div>
@endsection
