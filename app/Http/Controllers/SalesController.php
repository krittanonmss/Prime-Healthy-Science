<?php

namespace App\Http\Controllers;

use App\Services\Inventory;
use App\Services\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesController
{
    private function allOrders()
    {
        return DB::table('orders')->join('customers', 'customers.id', '=', 'orders.customer_id')->select('orders.*', 'customers.name as customer')->orderByDesc('orders.id')->get();
    }

    public function dashboard()
    {
        return view('dashboard', [
            'orders' => $this->allOrders()->where('status', '!=', 'cancelled'),
            'lowStockCount' => DB::table('products')->where('stock_initialized', true)->whereRaw('stock_on_hand - stock_reserved <= stock_minimum')->count(),
            'uninitializedStockCount' => DB::table('products')->where('stock_initialized', false)->count(),
        ]);
    }

    private function validatedOrderFilters(Request $r): array
    {
        $v = $r->validate([
            'q' => 'nullable|string|max:200',
            'channel' => 'nullable|in:retail,hospital,pharmacy',
            'status' => 'nullable|in:confirmed,packing,dispatched,delivered,cancelled',
            'payment' => 'nullable|in:unpaid,partial,paid',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'per_page' => 'nullable|in:10,20,50,100',
            'page' => 'nullable|integer|min:1',
        ]);
        if (! empty($v['from']) && ! empty($v['to']) && $v['to'] < $v['from']) {
            throw ValidationException::withMessages(['to' => 'วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มต้น']);
        }

        return $v;
    }

    private function filteredOrderQuery(array $v)
    {
        $customerName = "COALESCE(json_extract(orders.customer_snapshot, '$.name'), customers.name)";
        $query = DB::table('orders')->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->select('orders.*')->selectRaw($customerName.' as customer');
        foreach (['channel', 'status'] as $key) {
            if (! empty($v[$key])) {
                $query->where('orders.'.$key, $v[$key]);
            }
        }
        if (! empty($v['from'])) {
            $query->whereDate('orders.order_date', '>=', $v['from']);
        }
        if (! empty($v['to'])) {
            $query->whereDate('orders.order_date', '<=', $v['to']);
        }
        if (! empty($v['payment'])) {
            $query->where('orders.status', '!=', 'cancelled');
            match ($v['payment']) {
                'paid' => $query->whereColumn('orders.paid', '>=', 'orders.total'),
                'partial' => $query->where('orders.paid', '>', 0)->whereColumn('orders.paid', '<', 'orders.total'),
                'unpaid' => $query->where('orders.paid', 0)->where('orders.total', '>', 0),
            };
        }
        $fields = [$customerName, "COALESCE(json_extract(orders.customer_snapshot, '$.phone'), customers.phone)",
            "COALESCE(json_extract(orders.customer_snapshot, '$.tax_id'), customers.tax_id)",
            "COALESCE(json_extract(orders.customer_snapshot, '$.address'), customers.address)",
            'orders.salesperson', 'orders.sales_code', 'orders.sales_phone'];
        foreach (preg_split('/\s+/u', trim($v['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function ($search) use ($term, $pattern, $fields) {
                foreach ($fields as $field) {
                    $search->orWhereRaw("LOWER($field) LIKE ? ESCAPE '!'", [$pattern]);
                }
                if (preg_match('/^(?:PH-)?0*([0-9]+)$/i', $term, $match)) {
                    $search->orWhere('orders.id', $match[1]);
                }
                $search->orWhereExists(function ($items) use ($pattern) {
                    $items->selectRaw('1')->from('order_lines')->whereColumn('order_lines.order_id', 'orders.id')
                        ->where(function ($product) use ($pattern) {
                            $product->whereRaw("LOWER(order_lines.name) LIKE ? ESCAPE '!'", [$pattern])
                                ->orWhereRaw("LOWER(order_lines.sku) LIKE ? ESCAPE '!'", [$pattern]);
                        });
                });
            });
        }

        return $query;
    }

    public function orders(Request $r)
    {
        $v = $this->validatedOrderFilters($r);
        $query = $this->filteredOrderQuery($v);
        $orders = $query->orderByDesc('orders.id')->paginate((int) ($v['per_page'] ?? 10))->withQueryString();

        if ($orders->isEmpty() && $orders->total() > 0) {
            return redirect($orders->url($orders->lastPage()));
        }

        return view('orders', compact('orders'));
    }

    public function create()
    {
        return view('create', ['products' => DB::table('products')->get(), 'customers' => DB::table('customers')->get()]);
    }

    public function store(Request $r)
    {
        $v = $r->validate(['customer_id' => 'required|exists:customers,id', 'channel' => 'required|in:retail,hospital,pharmacy', 'order_date' => 'required|date_format:Y-m-d', 'salesperson' => 'required|string|max:100', 'sales_code' => 'nullable|string|max:30', 'sales_phone' => 'nullable|string|max:30', 'tax_id' => 'nullable|regex:/^[0-9]{13}$/', 'lines' => 'required|array|min:1|max:30', 'lines.*.product_id' => 'required|exists:products,id', 'lines.*.quantity' => 'required|integer|min:0|max:10000', 'lines.*.free_quantity' => 'required|integer|min:0|max:10000', 'lines.*.price' => 'nullable|numeric|min:0|max:1000000|decimal:0,2', 'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100|decimal:0,2', 'lines.*.price_reason' => 'nullable|string|max:500']);
        $id = DB::transaction(function () use ($v) {
            $lines = [];
            $total = 0;
            foreach ($v['lines'] as $l) {
                $p = DB::table('products')->find($l['product_id']);
                if ($l['quantity'] + $l['free_quantity'] < 1) {
                    throw ValidationException::withMessages(['lines' => 'จำนวนขายและของแถมรวมต้องมากกว่า 0']);
                }
                $catalog = $p->{$v['channel']};
                $price = ! $l['quantity'] ? 0 : (isset($l['price']) ? (int) round($l['price'] * 100) : $catalog);
                if ($price === null) {
                    throw ValidationException::withMessages(['lines' => 'Please enter a price for '.$p->sku]);
                }$reason = trim($l['price_reason'] ?? '');
                if ($l['quantity'] && $price !== $catalog && $reason === '') {
                    throw ValidationException::withMessages(['lines' => 'Please enter a price adjustment reason for '.$p->sku]);
                }
                $gross = $price * $l['quantity'];
                $basisPoints = (int) round(($l['discount_percent'] ?? 0) * 100);
                $discount = intdiv($gross * $basisPoints + 5000, 10000);
                $amount = $gross - $discount;
                $total += $amount;
                $lines[] = ['product_id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'quantity' => $l['quantity'], 'unit' => $p->unit, 'free' => $l['free_quantity'], 'free_box' => $p->unit === 'box' ? $l['free_quantity'] : 0, 'free_sachet' => $p->unit === 'sachet' ? $l['free_quantity'] : 0, 'catalog_price' => $catalog, 'price_reason' => $reason ?: null, 'price' => $price, 'discount_basis_points' => $basisPoints, 'discount_amount' => $discount, 'total' => $amount];
            }
            $customer = DB::table('customers')->find($v['customer_id']);
            $customer->tax_id = $v['tax_id'] ?? $customer->tax_id;
            $id = DB::table('orders')->insertGetId(['customer_id' => $v['customer_id'], 'customer_snapshot' => json_encode($customer, JSON_UNESCAPED_UNICODE), 'order_date' => $v['order_date'], 'channel' => $v['channel'], 'salesperson' => $v['salesperson'], 'sales_code' => $v['sales_code'] ?? '', 'sales_phone' => $v['sales_phone'] ?? '', 'total' => $total, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $l) {
                DB::table('order_lines')->insert($l + ['order_id' => $id]);
            }

            Inventory::reserve($id);

            return $id;
        });

        return redirect('/orders/'.$id)->with('success', 'Order and agreed prices saved.');
    }

    public function show($id)
    {
        $order = DB::table('orders')->find($id);
        abort_unless($order, 404);

        return view('show', ['order' => $order, 'customer' => json_decode($order->customer_snapshot) ?: DB::table('customers')->find($order->customer_id), 'lines' => DB::table('order_lines')->where('order_id', $id)->get()]);
    }

    public function status(Request $r, $id)
    {
        $r->validate(['status' => 'required|in:packing,dispatched,delivered,cancelled']);
        DB::transaction(function () use ($r, $id) {
            $o = DB::table('orders')->where('id', $id)->lockForUpdate()->first();
            abort_unless($o, 404);
            $next = ['confirmed' => 'packing', 'packing' => 'dispatched', 'dispatched' => 'delivered'];
            if (! (($r->status === 'cancelled' && in_array($o->status, ['confirmed', 'packing'])) || ($next[$o->status] ?? null) === $r->status)) {
                throw ValidationException::withMessages(['status' => 'Please follow the fulfillment sequence.']);
            }
            if ($r->status === 'cancelled' && $o->paid > 0) {
                throw ValidationException::withMessages(['status' => 'ออเดอร์รับเงินแล้ว ต้องจัดการเงินคืนก่อน จึงยังไม่อนุญาตให้ยกเลิก']);
            }
            if ($o->stock_managed && in_array($r->status, ['dispatched', 'cancelled'])) {
                Inventory::release($id, $r->status === 'dispatched');
            }
            DB::table('orders')->where('id', $id)->update(['status' => $r->status, 'updated_at' => now()]);
        });

        return back()->with('success', 'Fulfillment updated.');
    }

    public function payment(Request $r, $id)
    {
        $r->validate(['amount' => 'required|numeric|min:0.01|decimal:0,2']);
        DB::transaction(function () use ($r, $id) {
            $o = DB::table('orders')->where('id', $id)->lockForUpdate()->first();
            abort_unless($o, 404);
            if ($o->status === 'cancelled') {
                throw ValidationException::withMessages(['amount' => 'ออเดอร์ยกเลิกแล้ว']);
            }
            $amount = (int) round($r->amount * 100);
            if ($amount > $o->total - $o->paid) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the outstanding balance.']);
            }DB::table('orders')->where('id', $id)->update(['paid' => $o->paid + $amount, 'updated_at' => now()]);
        });

        return back()->with('success', 'Payment recorded.');
    }

    public function products(Request $r)
    {
        $v = $r->validate(['q' => 'nullable|string|max:200', 'per_page' => 'nullable|in:10,20,50,100', 'page' => 'nullable|integer|min:1']);
        $query = DB::table('products')->orderBy('sku');
        foreach (preg_split('/\s+/u', trim($v['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function ($q) use ($pattern) {
                $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        $products = $query->paginate((int) ($v['per_page'] ?? 10))->withQueryString();
        if ($products->isEmpty() && $products->total() > 0) {
            return redirect($products->url($products->lastPage()));
        }

        return view('products', compact('products'));
    }

    public function createProduct()
    {
        return view('product-create');
    }

    public function storeProduct(Request $r)
    {
        if (is_string($r->input('sku'))) {
            $r->merge(['sku' => mb_strtoupper(trim($r->input('sku')))]);
        }
        if (is_string($r->input('unit'))) {
            $r->merge(['unit' => mb_strtolower(trim($r->input('unit')))]);
        }
        $v = $r->validate(['sku' => 'required|string|max:50|regex:/^[A-Z0-9][A-Z0-9._-]*$/|unique:products,sku', 'name' => 'required|string|max:200', 'unit' => 'required|string|max:30']);
        if (DB::table('products')->whereRaw('LOWER(sku) = ?', [mb_strtolower($v['sku'])])->exists()) {
            throw ValidationException::withMessages(['sku' => 'This SKU already exists.']);
        }
        $prices = $this->validatedProductPrices($r);
        DB::table('products')->insert($v + $prices + ['wholesale' => $prices['hospital']]);

        return redirect('/products?'.http_build_query(['q' => $v['sku']]))->with('success', 'Product added. Set its opening balance in Stock before taking orders.');
    }

    public function editProductPrices($id)
    {
        $product = DB::table('products')->find($id);
        abort_unless($product, 404);

        return view('product-pricing', compact('product'));
    }

    private function validatedProductPrices(Request $r): array
    {
        $v = $r->validate(['retail' => 'required|numeric|min:0.01|max:1000000|decimal:0,2', 'hospital' => 'nullable|numeric|min:0.01|max:1000000|decimal:0,2', 'pharmacy' => 'nullable|numeric|min:0.01|max:1000000|decimal:0,2']);
        $prices = [];
        foreach (['retail', 'hospital', 'pharmacy'] as $key) {
            $prices[$key] = isset($v[$key]) ? (int) round($v[$key] * 100) : null;
        }
        foreach (['hospital', 'pharmacy'] as $key) {
            if ($prices[$key] !== null && $prices[$key] === $prices['retail']) {
                throw ValidationException::withMessages([$key => 'Retail prices must differ from hospital and pharmacy prices.']);
            }
        }

        return $prices;
    }

    public function price(Request $r, $id)
    {
        $prices = $this->validatedProductPrices($r);
        abort_unless(DB::table('products')->where('id', $id)->exists(), 404);
        DB::table('products')->where('id', $id)->update($prices);

        return back()->with('success', 'Prices saved. Existing orders retain their original prices.');
    }

    public function customers(Request $r)
    {
        $v = $r->validate(['q' => 'nullable|string|max:200', 'channel' => 'nullable|in:retail,hospital,pharmacy', 'per_page' => 'nullable|in:10,20,50,100', 'page' => 'nullable|integer|min:1']);
        $query = DB::table('customers')->orderByDesc('id');
        if (! empty($v['channel'])) {
            $query->where('channel', $v['channel']);
        }
        foreach (preg_split('/\s+/u', trim($v['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function ($q) use ($pattern) {
                foreach (['name', 'phone', 'tax_id', 'address'] as $field) {
                    $q->orWhereRaw("LOWER($field) LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }
        $customers = $query->paginate((int) ($v['per_page'] ?? 10))->withQueryString();
        if ($customers->isEmpty() && $customers->total() > 0) {
            return redirect($customers->url($customers->lastPage()));
        }

        return view('customers', compact('customers'));
    }

    public function createCustomer()
    {
        return view('customer-create');
    }

    public function editCustomer($id)
    {
        $customer = DB::table('customers')->find($id);
        abort_unless($customer, 404);

        return view('customer-edit', compact('customer'));
    }

    private function validatedCustomer(Request $r): array
    {
        return $r->validate(['name' => 'required|string|max:200', 'phone' => 'required|string|max:30', 'address' => 'required|string|max:1000', 'channel' => 'required|in:retail,hospital,pharmacy', 'tax_id' => 'nullable|regex:/^[0-9]{13}$/']);
    }

    public function customer(Request $r)
    {
        $v = $this->validatedCustomer($r);
        DB::table('customers')->insert($v);

        return redirect('/customers?'.http_build_query(['q' => $v['name']]))->with('success', 'Customer added.');
    }

    public function updateCustomer(Request $r, $id)
    {
        abort_unless(DB::table('customers')->where('id', $id)->exists(), 404);
        $v = $this->validatedCustomer($r);
        $v['tax_id'] = $v['tax_id'] ?? null;
        DB::transaction(function () use ($v, $id) {
            $customer = DB::table('customers')->where('id', $id)->lockForUpdate()->first();
            abort_unless($customer, 404);
            DB::table('orders')->where('customer_id', $id)->whereNull('customer_snapshot')->update(['customer_snapshot' => json_encode($customer, JSON_UNESCAPED_UNICODE)]);
            DB::table('customers')->where('id', $id)->update($v);
        });

        return redirect('/customers?'.http_build_query(['q' => $v['name']]))->with('success', 'Customer updated. Existing orders keep their saved customer details.');
    }

    public function reports(Request $r)
    {
        $v = $this->validatedOrderFilters($r);
        $query = $this->filteredOrderQuery($v)->where('orders.status', '!=', 'cancelled');
        $channels = (clone $query)->select('orders.channel')->selectRaw('COUNT(*) as order_count, SUM(orders.total) as sales, SUM(orders.paid) as paid')->groupBy('orders.channel')->get()->keyBy('channel');
        $summary = ['count' => $channels->sum('order_count'), 'sales' => $channels->sum('sales'), 'paid' => $channels->sum('paid')];
        $summary['outstanding'] = $summary['sales'] - $summary['paid'];
        $orders = $query->orderByDesc('orders.id')->paginate((int) ($v['per_page'] ?? 10))->withQueryString();
        if ($orders->isEmpty() && $orders->total() > 0) {
            return redirect($orders->url($orders->lastPage()));
        }

        return view('reports', compact('orders', 'channels', 'summary'));
    }

    public function exportReportForm(Request $r)
    {
        $v = $this->validatedOrderFilters($r);
        $query = $this->filteredOrderQuery($v);
        if (($v['status'] ?? '') !== 'cancelled') {
            $query->where('orders.status', '!=', 'cancelled');
        }
        $orders = $query->orderByDesc('orders.id')->get();

        return view('report-export', compact('orders'));
    }

    public function document(Request $r, $id)
    {
        $r->validate(['type' => 'required|in:receipt,tax,goods']);
        $order = DB::table('orders')->find($id);
        abort_unless($order, 404);

        return view('document', ['order' => $order, 'customer' => json_decode($order->customer_snapshot) ?: DB::table('customers')->find($order->customer_id), 'lines' => DB::table('order_lines')->where('order_id', $id)->get(), 'type' => $r->type]);
    }

    public function export(Request $r)
    {
        $v = $this->validatedOrderFilters($r);
        $ids = $r->validate(['ids' => 'nullable|array|max:1000', 'ids.*' => 'integer|exists:orders,id']);
        $scope = $this->filteredOrderQuery($v);
        if (($v['status'] ?? '') !== 'cancelled') {
            $scope->where('orders.status', '!=', 'cancelled');
        }
        if (! empty($ids['ids'])) {
            $scope->whereIn('orders.id', $ids['ids']);
        }
        $query = DB::table('order_lines as l')->join('orders as o', 'o.id', '=', 'l.order_id')->join('customers as c', 'c.id', '=', 'o.customer_id')->select('l.*', 'o.id as order_number', 'o.order_date', 'o.created_at', 'o.customer_snapshot', 'o.sales_code', 'o.sales_phone', 'o.salesperson', 'c.name as customer_name', 'c.address', 'c.phone');
        $query->whereIn('o.id', $scope->select('orders.id'));
        $rows = [['Year', 'Month', 'Date', 'Receipt No', 'Sales Code', 'Sales Name', 'Tel No', 'Delivery to', 'Address', 'Tel No', 'Product', 'Unit sales', 'FOC Box', 'FOC Sachet', 'Price', 'Amount', 'Ex VAT', 'VAT']];
        foreach ($query->orderBy('o.id')->orderBy('l.id')->get() as $l) {
            $date = Carbon::parse($l->order_date ?: $l->created_at);
            $c = json_decode($l->customer_snapshot);
            $net = round($l->total / 1.07);
            $rows[] = [$date->year, $date->format('F'), $date->day, 'PH-'.str_pad($l->order_number, 5, '0', STR_PAD_LEFT), $l->sales_code, $l->salesperson, $l->sales_phone, $c->name ?? $l->customer_name, $c->address ?? $l->address, $c->phone ?? $l->phone, $l->name.($l->unit && $l->free && ! in_array($l->unit, ['box', 'sachet']) ? ' [Free: '.$l->free.' '.$l->unit.']' : ''), $l->quantity, $l->free_box, $l->free_sachet, round($l->price * (10000 - $l->discount_basis_points) / 10000, 2) / 100, $l->total / 100, $net / 100, ($l->total - $net) / 100];
        }

        return response(XlsxWriter::make($rows), 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="Sales-Record.xlsx"']);
    }
}
