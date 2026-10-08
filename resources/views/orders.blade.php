@extends('layout')
@section('title','Sales orders')
@section('subtitle','ค้นหาออเดอร์ ติดตามการจัดส่ง และตรวจสอบการชำระเงิน')
@section('action')<a class="btn" href="/orders/create">＋ Create order</a>@endsection
@section('content')
<form method="get" action="/orders" class="card orders-search" style="margin-bottom:22px">
    <div class="field">
        <label for="order-search">ค้นหาออเดอร์</label>
        <input id="order-search" name="q" type="search" maxlength="200" placeholder="ค้นหาเลขออเดอร์ ลูกค้า หรือสินค้า…" value="{{ request('q') }}">
    </div>
    <details class="orders-search-advanced">
        <summary>ตัวกรองเพิ่มเติม
            @if(request()->filled('channel') || request()->filled('status') || request()->filled('payment') || request()->filled('from') || request()->filled('to') || (int)request('per_page',10)!==10)
                <span class="badge">กำลังใช้งาน</span>
            @endif
        </summary>
        <p class="orders-search-help">ค้นหาได้ด้วยเลขออเดอร์ ชื่อลูกค้า เบอร์โทร เลขผู้เสียภาษี ที่อยู่ พนักงานขาย หรือชื่อสินค้า/SKU · พิมพ์หลายคำเพื่อค้นหาให้ตรงทุกคำ</p>
        <div class="orders-search-fields section-gap">
        <div><label for="order-channel">ประเภทลูกค้า</label><select id="order-channel" name="channel"><option value="">ทั้งหมด</option>
            @foreach(['retail'=>'ลูกค้าทั่วไป','hospital'=>'โรงพยาบาล','pharmacy'=>'ร้านขายยา'] as $value=>$label)
                <option value="{{ $value }}" @selected(request('channel')===$value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div><label for="order-status">สถานะจัดส่ง</label><select id="order-status" name="status"><option value="">ทั้งหมด</option>
            @foreach(['confirmed'=>'ยืนยันแล้ว','packing'=>'กำลังแพ็ก','dispatched'=>'ส่งแล้ว','delivered'=>'ส่งถึงแล้ว','cancelled'=>'ยกเลิก'] as $value=>$label)
                <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div><label for="order-payment">การชำระเงิน</label><select id="order-payment" name="payment"><option value="">ทั้งหมด</option>
            @foreach(['unpaid'=>'ยังไม่ชำระ','partial'=>'ชำระบางส่วน','paid'=>'ชำระครบ'] as $value=>$label)
                <option value="{{ $value }}" @selected(request('payment')===$value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div><label for="order-from">วันที่ขาย ตั้งแต่</label><input id="order-from" type="date" name="from" value="{{ request('from') }}"></div>
        <div><label for="order-to">วันที่ขาย ถึง</label><input id="order-to" type="date" name="to" value="{{ request('to') }}"></div>
        <div><label for="orders-per-page">จำนวนต่อหน้า</label><select id="orders-per-page" name="per_page">
            @foreach([10,20,50,100] as $size)
                <option value="{{ $size }}" @selected((int)request('per_page',10)===$size)>{{ $size }} รายการ</option>
            @endforeach
        </select></div>
        </div>
    </details>
    <div class="inline section-gap"><button>ค้นหา</button><a href="/orders" class="btn secondary">ล้างการค้นหา</a></div>
</form>
<div id="orders-results" data-ajax-region>
<p style="margin-bottom:12px">พบ {{ number_format($orders->total()) }} ออเดอร์
    @if($orders->total()) · แสดง {{ $orders->firstItem() }}–{{ $orders->lastItem() }} @endif
    · เรียงตามออเดอร์ที่สร้างล่าสุด · ตัวกรองการชำระเงินไม่รวมออเดอร์ที่ยกเลิก
</p>
@include('order-table', ['showAllOrders'=>false])
@if($orders->hasPages())
<nav class="orders-pagination" aria-label="หน้ารายการออเดอร์">
    @if($orders->onFirstPage())<span class="btn secondary" aria-disabled="true">← ก่อนหน้า</span>
    @else<a class="btn secondary" rel="prev" href="{{ $orders->previousPageUrl() }}">← ก่อนหน้า</a>@endif
    @if($orders->currentPage()>3)<a class="btn secondary" href="{{ $orders->url(1) }}">1</a><span>…</span>@endif
    @foreach($orders->getUrlRange(max(1,$orders->currentPage()-2),min($orders->lastPage(),$orders->currentPage()+2)) as $page=>$url)
        <a class="btn {{ $page===$orders->currentPage() ? '' : 'secondary' }}" href="{{ $url }}" @if($page===$orders->currentPage()) aria-current="page" @endif>{{ $page }}</a>
    @endforeach
    @if($orders->currentPage()<$orders->lastPage()-2)<span>…</span><a class="btn secondary" href="{{ $orders->url($orders->lastPage()) }}">{{ $orders->lastPage() }}</a>@endif
    @if($orders->hasMorePages())<a class="btn secondary" rel="next" href="{{ $orders->nextPageUrl() }}">ถัดไป →</a>
    @else<span class="btn secondary" aria-disabled="true">ถัดไป →</span>@endif
    <span>หน้า {{ $orders->currentPage() }} / {{ $orders->lastPage() }}</span>
</nav>
@endif
</div>
@endsection
