@extends('layout')
@section('title','Business overview')
@section('subtitle','A clear view of your sales, customers and daily operations.')
@section('action')<a class="btn" href="/orders/create">＋ Create order</a>@endsection
@section('content')
<a href="/stock" class="overview-stock {{ $lowStockCount ? 'overview-stock-warning' : '' }}">
    <span class="overview-stock-icon" aria-hidden="true">{{ $lowStockCount ? '⚠' : '▣' }}</span>
    <div>
        @if($lowStockCount)
            <b>สินค้าใกล้หมด / หมด {{ $lowStockCount }} รายการ</b>
            <p>ยอดพร้อมขายเหลือไม่เกินเกณฑ์แจ้งเตือนที่ตั้งไว้</p>
        @elseif($uninitializedStockCount)
            <b>สต็อกสินค้ายังตั้งค่าไม่ครบ</b>
            <p>ตั้งยอดเริ่มต้นก่อนรับออเดอร์ใหม่</p>
        @else
            <b>สต็อกพร้อมขาย · ไม่มีสินค้าใกล้หมด</b>
            <p>ยอดพร้อมขายของทุกสินค้าอยู่เหนือเกณฑ์แจ้งเตือน</p>
        @endif
        @if($uninitializedStockCount)
            <p>ยังไม่ได้ตั้งยอดเริ่มต้น {{ $uninitializedStockCount }} รายการ</p>
        @endif
    </div>
    <span class="overview-stock-link">ดู Stock →</span>
</a>
<div class="metrics"><div class="metric"><small>Total sales · Demo dataset</small><strong>฿{{ number_format($orders->sum('total')/100,2) }}</strong><em>{{ $orders->count() }} confirmed orders</em></div><div class="metric"><small>Payments received</small><strong>฿{{ number_format($orders->sum('paid')/100,2) }}</strong><em>Retail · Hospital · Pharmacy</em></div><div class="metric"><small>Outstanding balance</small><strong>฿{{ number_format(($orders->sum('total')-$orders->sum('paid'))/100,2) }}</strong><em>{{ $orders->filter(fn($o)=>$o->paid<$o->total)->count() }} orders awaiting payment</em></div><div class="metric"><small>In fulfillment</small><strong>{{ $orders->where('status','!=','delivered')->count() }} <span class="muted" style="font-size:14px">orders</span></strong><em>Ready for the next step</em></div></div>
<div class="grid"><div class="card"><div class="card-top"><div><h2>Sales by channel</h2><p>Three price categories. One connected workspace.</p></div><a class="link" href="/reports">View reports ↗</a></div>@foreach(['retail'=>'Retail','hospital'=>'Hospital','pharmacy'=>'Pharmacy'] as $key=>$name)<div class="bar-row"><div class="bar-label"><span>{{ $name }}</span><b>฿{{ number_format($orders->where('channel',$key)->sum('total')/100,2) }}</b></div><div class="bar {{ $key!=='retail'?'lime':'' }}"><i style="width:{{ $orders->sum('total') ? $orders->where('channel',$key)->sum('total')/$orders->sum('total')*100 : 0 }}%"></i></div></div>@endforeach<div class="legend">Sales include tax · Based on all saved demo orders</div></div><div class="card"><h2>Fulfillment pipeline</h2><p style="font-size:11px">Keep every order moving forward.</p><div class="steps">@foreach(['confirmed'=>'Confirmed','packing'=>'Packing','dispatched'=>'Sent','delivered'=>'Delivered'] as $key=>$label)<div class="step"><b>{{ $orders->where('status',$key)->count() }}</b><small>{{ $label }}</small></div>@endforeach</div><div class="legend">Payment and delivery progress are tracked separately.</div></div></div>
<div class="hero-note">✦ Select a customer category to apply catalog prices, or enter negotiated prices with an adjustment reason.</div>
@include('order-table',['orders'=>$orders->take(6),'tableTitle'=>'Recent orders'])
@endsection
