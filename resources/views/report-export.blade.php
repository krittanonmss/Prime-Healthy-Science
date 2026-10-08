@extends('layout')
@section('title','Export report')
@section('subtitle','Download sales records as Excel with the same filters used in your report.')
@section('action')<a class="btn secondary" href="/reports?{{ http_build_query(request()->only('q','from','to','channel','status','payment')) }}">← Back to Sales reports</a>@endsection
@section('content')
<form method="get" action="/reports/export" class="card" style="margin-bottom:22px">
    <h2>Report scope</h2><label class="section-gap" for="export-report-search">Search orders</label><input id="export-report-search" name="q" type="search" maxlength="200" value="{{ request('q') }}" placeholder="Order, customer, salesperson or product…">
    @include('report-filter-fields',['includeCancelled'=>true])
    <div class="inline section-gap"><button>Apply filters</button><a class="btn secondary" href="/reports/export">Clear</a></div>
</form>
<form method="get" action="/export" class="card">
    <h2>Download Sales Record</h2><p>{{ number_format($orders->count()) }} matching orders. Blank selections export all matching orders, across all report pages.</p>
    @foreach(request()->only('q','from','to','channel','status','payment') as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
    <details class="stock-list-filters section-gap"><summary>Select specific orders (optional)</summary>
        <label class="section-gap" for="export-order-search">Search order number or customer</label><input id="export-order-search" type="search" data-list-search="export-order-list" data-search-language="en" placeholder="Type to find an order…"><p id="export-order-list-status" role="status"></p>
        <p>Checked orders remain selected when you search.</p>
        <div id="export-order-list" class="export-order-list">
            @forelse($orders as $o)<label data-search-item><input type="checkbox" name="ids[]" value="{{ $o->id }}"> PH-{{ str_pad($o->id,5,'0',STR_PAD_LEFT) }} · {{ $o->customer }} · {{ $o->order_date }} · {{ ucfirst($o->status) }}</label>
            @empty<p>No matching orders. Change the filters above.</p>@endforelse
        </div>
    </details>
    <button class="section-gap" @disabled($orders->isEmpty())>↓ Download Sales-Record.xlsx</button>
    <p class="section-gap">The workbook keeps the original 18 columns. Each product line is exported separately. Cancelled orders are included only when explicitly selected in the fulfillment filter; payment filters exclude them.</p>
</form>
@endsection
