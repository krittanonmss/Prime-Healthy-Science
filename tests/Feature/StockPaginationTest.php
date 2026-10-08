<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_pagination_searches_all_products_and_keeps_full_product_selectors(): void
    {
        $this->seed();
        for ($i = 1; $i <= 45; $i++) {
            DB::table('products')->insert(['sku' => 'TEST'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'name' => 'Pagination sample '.$i, 'unit' => 'piece', 'retail' => 100]);
        }
        $this->get('/stock')->assertOk()->assertViewHas('balances', fn ($rows) => $rows->total() === 52 && $rows->count() === 20);
        $this->get('/stock?balances_page=3')->assertOk()->assertViewHas('balances', fn ($rows) => $rows->count() === 12);
        $this->get('/stock?balance_q=test045')->assertOk()
            ->assertViewHas('balances', fn ($rows) => $rows->total() === 1 && $rows->first()->sku === 'TEST045')
            ->assertViewHas('products', fn ($products) => $products->count() === 52);
        $this->get('/stock?balance_q=Pagination&balance_per_page=20')->assertOk()
            ->assertViewHas('balances', fn ($rows) => $rows->total() === 45 && $rows->count() === 20)
            ->assertSee('balance_q=Pagination&amp;balance_per_page=20&amp;tab=balances&amp;balances_page=2', false);
        $this->get('/stock?balance_per_page=50')->assertOk()->assertViewHas('balances', fn ($rows) => $rows->count() === 50);
        $this->get('/stock?balance_q=%25')->assertOk()->assertViewHas('balances', fn ($rows) => $rows->total() === 0);
        $this->get('/stock?balance_per_page=999')->assertSessionHasErrors('balance_per_page');
    }

    public function test_history_pagination_preserves_product_filter_and_selected_tab(): void
    {
        $this->seed();
        for ($i = 0; $i < 45; $i++) {
            DB::table('stock_movements')->insert(['product_id' => 3, 'type' => 'receive', 'quantity' => 1, 'balance' => $i + 1, 'movement_date' => now()->toDateString(), 'reason' => 'Pagination test', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->get('/stock?tab=history&product_id=3')->assertOk()
            ->assertViewHas('activeTab', 'history')
            ->assertViewHas('movements', fn ($rows) => $rows->total() === 45 && $rows->count() === 10)
            ->assertSee('tab=history&amp;product_id=3&amp;history_page=2', false);
        $this->get('/stock?tab=history&product_id=3&history_page=3')->assertOk()->assertViewHas('movements', fn ($rows) => $rows->count() === 10);
        $this->get('/stock?tab=history&product_id=3&history_per_page=50')->assertOk()->assertViewHas('movements', fn ($rows) => $rows->count() === 45);
        $this->get('/stock?tab=history&product_id=2')->assertOk()->assertViewHas('movements', fn ($rows) => $rows->total() === 0);
    }
}
