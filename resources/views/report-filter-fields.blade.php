<div class="fields section-gap">
    <div><label for="report-from">Order date from</label><input id="report-from" type="date" name="from" value="{{ old('from',request('from')) }}"></div>
    <div><label for="report-to">Order date to</label><input id="report-to" type="date" name="to" value="{{ old('to',request('to')) }}"></div>
    <div><label for="report-channel">Customer category</label><select id="report-channel" name="channel"><option value="">All categories</option>
        @foreach(['retail'=>'Retail','hospital'=>'Hospital','pharmacy'=>'Pharmacy'] as $value=>$label)<option value="{{ $value }}" @selected(old('channel',request('channel'))===$value)>{{ $label }}</option>@endforeach
    </select></div>
    <div><label for="report-status">Fulfillment</label><select id="report-status" name="status"><option value="">All active orders</option>
        @foreach(['confirmed'=>'Confirmed','packing'=>'Packing','dispatched'=>'Dispatched','delivered'=>'Delivered'] + (($includeCancelled ?? false)?['cancelled'=>'Cancelled']:[]) as $value=>$label)<option value="{{ $value }}" @selected(old('status',request('status'))===$value)>{{ $label }}</option>@endforeach
    </select></div>
    <div><label for="report-payment">Payment</label><select id="report-payment" name="payment"><option value="">All</option>
        @foreach(['unpaid'=>'Unpaid','partial'=>'Partially paid','paid'=>'Fully paid'] as $value=>$label)<option value="{{ $value }}" @selected(old('payment',request('payment'))===$value)>{{ $label }}</option>@endforeach
    </select></div>
    @if($includePageSize ?? false)
    <div><label for="reports-per-page">Orders per page</label><select id="reports-per-page" name="per_page">
        @foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',10)===$size)>{{ $size }}</option>@endforeach
    </select></div>
    @endif
</div>
