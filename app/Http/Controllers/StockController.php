<?php

namespace App\Http\Controllers;

use App\Services\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockController
{
    public function index(Request $r)
    {
        if ($r->query('tab') === 'receive') {
            return redirect()->route('stock.edit', $r->only('stock_product_id'));
        }
        $v = $r->validate([
            'balance_q' => 'nullable|string|max:200',
            'balance_per_page' => 'nullable|in:20,50,100',
            'balances_page' => 'nullable|integer|min:1',
            'history_q' => 'nullable|string|max:200',
            'history_per_page' => 'nullable|in:10,20,50,100',
            'history_page' => 'nullable|integer|min:1',
            'product_id' => 'nullable|integer|exists:products,id',
            'page' => 'nullable|integer|min:1',
        ]);
        $products = DB::table('products')->orderBy('sku')->get();
        $balanceQuery = DB::table('products')->orderBy('sku');
        foreach (preg_split('/\s+/u', trim($v['balance_q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $balanceQuery->where(function ($q) use ($pattern) {
                $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        $balances = $balanceQuery->paginate((int) ($v['balance_per_page'] ?? 20), ['*'], 'balances_page')
            ->withQueryString()->appends(['tab' => 'balances']);
        $query = DB::table('stock_movements')->join('products', 'products.id', '=', 'stock_movements.product_id')->select('stock_movements.*', 'products.sku', 'products.name', 'products.unit')->orderByDesc('stock_movements.id');
        if ($r->filled('product_id')) {
            $query->where('product_id', $r->integer('product_id'));
        }
        $movementLabel = "CASE stock_movements.type WHEN 'opening' THEN 'opening balance' WHEN 'receive' THEN 'receive' WHEN 'adjust' THEN 'adjust' WHEN 'reserve' THEN 'reserve' WHEN 'release' THEN 'release reservation' WHEN 'dispatch' THEN 'dispatch' END";
        foreach (preg_split('/\s+/u', trim($v['history_q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function ($search) use ($term, $pattern, $movementLabel) {
                foreach (['products.name', 'products.sku', 'stock_movements.reason', 'stock_movements.movement_date', $movementLabel] as $field) {
                    $search->orWhereRaw("LOWER($field) LIKE ? ESCAPE '!'", [$pattern]);
                }
                if (preg_match('/^(?:PH-)?0*([0-9]+)$/i', $term, $match)) {
                    $search->orWhere('stock_movements.order_id', $match[1]);
                }
            });
        }
        $movements = $query->paginate((int) ($v['history_per_page'] ?? 10), ['*'], 'history_page', $r->integer('history_page', $r->integer('page', 1)))
            ->withQueryString()->appends(['tab' => 'history']);
        $activeTab = $r->query('tab', $r->filled('history_q') || $r->filled('product_id') || $r->filled('history_page') || $r->filled('page') ? 'history' : 'balances');
        if (! in_array($activeTab, ['balances', 'history'], true)) {
            $activeTab = 'balances';
        }
        $activeList = $activeTab === 'history' ? $movements : $balances;
        if ($activeList->isEmpty() && $activeList->total() > 0) {
            return redirect($activeList->url($activeList->lastPage()));
        }

        return view('stock', compact('products', 'balances', 'movements', 'activeTab'));
    }

    public function edit(Request $r)
    {
        $r->validate(['stock_product_id' => 'nullable|integer|exists:products,id']);
        $products = DB::table('products')->orderBy('sku')->get();

        return view('stock-update', compact('products'));
    }

    public function update(Request $r, int $id)
    {
        $v = $r->validate(['type' => 'required|in:opening,receive,adjust', 'quantity' => 'required|integer|min:0|max:100000000', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'reason' => 'required|string|max:500', 'minimum' => 'required|integer|min:0|max:100000000']);
        DB::transaction(function () use ($v, $id) {
            $p = DB::table('products')->where('id', $id)->lockForUpdate()->first();
            abort_unless($p, 404);
            if (($v['type'] === 'opening') === (bool) $p->stock_initialized) {
                throw ValidationException::withMessages(['stock' => 'Opening balance can only be set once and is required before receiving or adjusting stock.']);
            }
            if ($v['type'] === 'receive' && ! $v['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Received quantity must be greater than zero.']);
            }
            $delta = $v['type'] === 'receive' ? $v['quantity'] : $v['quantity'] - $p->stock_on_hand;
            if ($p->stock_on_hand + $delta < $p->stock_reserved) {
                throw ValidationException::withMessages(['stock' => 'Counted balance cannot be below reserved stock: '.$p->sku]);
            }
            Inventory::movement($p, $v['type'], $delta, $v['reason'], null, $v['date']);
            DB::table('products')->where('id', $id)->update(['stock_initialized' => true, 'stock_minimum' => $v['minimum']]);
        });

        return redirect()->route('stock.edit', ['stock_product_id' => $id])->with('success', 'Stock saved successfully.');
    }
}
