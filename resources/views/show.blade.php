
@extends('layout')

@section('title','Order PH-'.str_pad($order->id,5,'0',STR_PAD_LEFT))

@section('subtitle','Saved prices, payment balance and delivery progress in one place.')

@section('action')<div class="inline"><a class="btn secondary" href="/orders">← Orders</a><a class="btn" target="_blank" href="/orders/{{ $order->id }}/document?type=receipt">Receipt</a><a class="btn secondary" target="_blank" href="/orders/{{ $order->id }}/document?type=tax">Tax invoice</a><a class="btn secondary" target="_blank" href="/orders/{{ $order->id }}/document?type=goods">Goods Receipt</a></div>
@endsection

@section('content')<div class="card"><div class="receipt-head"><div><h2>prime healthy<span style="color:#a9bf68"> +</span></h2><p>Prime Healthy Science · Sales receipt</p><p>Client demonstration · Not an official tax invoice</p></div><div style="text-align:right"><b>PH-{{ str_pad($order->id,5,'0',STR_PAD_LEFT) }}</b><p>{{ \Carbon\Carbon::parse($order->created_at)->format('d M Y') }}</p><span class="badge {{ $order->channel }}">{{ $order->channel }} pricing</span></div></div><div class="fields" style="margin-bottom:25px"><div><label>CUSTOMER / DELIVERY TO</label><b>{{ $customer->name }}</b><p>{{ $customer->address }}<br>{{ $customer->phone }}<br>Tax ID: {{ $customer->tax_id ?: '—' }}</p></div><div><label>SALESPERSON</label><b>{{ $order->salesperson }}</b><p>Fulfillment: {{ ucfirst($order->status) }}</p></div></div><table><thead><tr><th>Product</th><th>Paid qty</th><th>Free qty</th><th>Unit price</th><th>Discount</th><th>Amount</th></tr></thead><tbody>
@foreach($lines as $line)<tr><td>{{ $line->name }}<small>{{ $line->sku }}</small>
@if($line->price_reason)<small>Catalog price: ฿{{ number_format(($line->catalog_price??0)/100,2) }} · {{ $line->price_reason }}</small>
@endif</td><td>{{ $line->quantity }} {{ $line->unit }}</td><td>
@if($line->unit){{ $line->free }} {{ $line->unit }}
@else{{ $line->free_box }} boxes / {{ $line->free_sachet }} sachets
@endif</td><td>฿{{ number_format($line->price/100,2) }}</td><td>{{ number_format($line->discount_basis_points/100,2) }}%<small>฿{{ number_format($line->discount_amount/100,2) }}</small></td><td>฿{{ number_format($line->total/100,2) }}</td></tr>
@endforeach</tbody></table><div class="total-box" style="max-width:400px;margin:25px 0 0 auto"><div><span>Excluding VAT</span><b>฿{{ number_format(round($order->total/1.07)/100,2) }}</b></div><div><span>VAT · 7% demo</span><b>฿{{ number_format(($order->total-round($order->total/1.07))/100,2) }}</b></div><div class="grand"><span>Total</span><b>฿{{ number_format($order->total/100,2) }}</b></div><div><span>Paid</span><b>฿{{ number_format($order->paid/100,2) }}</b></div><div><span>Outstanding</span><b>฿{{ number_format(($order->total-$order->paid)/100,2) }}</b></div></div></div><div class="grid section-gap no-print"><div class="card"><h2>Fulfillment</h2><p class="section-gap">{{ $order->stock_managed ? 'ออเดอร์นี้จัดการสต็อกแล้ว' : 'ออเดอร์เก่า: ไม่กระทบสต็อก ต้องสร้างใหม่หากต้องการจอง เพราะหน่วยของแถมเดิมยังไม่ชัดเจน' }}</p><p class="section-gap">Current stage: <span class="badge {{ $order->status }}">{{ $order->status }}</span></p>
@php($next=['confirmed'=>'packing','packing'=>'dispatched','dispatched'=>'delivered'][$order->status]??null)
@if($next)<form method="post" action="/orders/{{ $order->id }}/status" class="section-gap">
@csrf<input type="hidden" name="status" value="{{ $next }}"><button>Move to {{ ucfirst($next) }} →</button></form>
@else<p class="section-gap">{{ $order->status === 'cancelled' ? 'Order cancelled.' : '✓ Delivery completed.' }}</p>
@endif
@if(in_array($order->status,['confirmed','packing']) && !$order->paid)<form method="post" action="/orders/{{ $order->id }}/status" class="section-gap">
@csrf<input type="hidden" name="status" value="cancelled"><button class="secondary" onclick="return confirm('ยกเลิกออเดอร์และคืนยอดจอง?')">ยกเลิกออเดอร์</button></form>
@endif<p style="font-size:11px;margin-top:15px">Packing list: {{ $lines->sum('quantity') }} paid units + {{ $lines->sum('free') }} free units.</p></div><div class="card"><h2>Record payment</h2>
@if($order->status==='cancelled')<p>ออเดอร์ยกเลิกแล้ว</p>
@elseif($order->paid<$order->total)<form method="post" action="/orders/{{ $order->id }}/payment" class="section-gap">
@csrf<label>Payment amount · THB</label><div class="inline"><input name="amount" type="number" min="0.01" max="{{ ($order->total-$order->paid)/100 }}" step="0.01" value="{{ ($order->total-$order->paid)/100 }}" required><button>Record payment</button></div></form>
@else<p class="section-gap">✓ This order is fully paid.</p>
@endif</div></div>
@endsection
