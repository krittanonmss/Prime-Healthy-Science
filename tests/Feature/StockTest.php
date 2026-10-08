<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function stock(int $id, string $type, int $qty): void
    {
        $this->post('/stock/'.$id, ['type' => $type, 'quantity' => $qty, 'date' => now()->toDateString(), 'reason' => 'ตรวจนับ / เอกสารรับเข้า', 'minimum' => 2])->assertSessionHasNoErrors();
    }

    private function payload(): array
    {
        return ['customer_id' => 1, 'channel' => 'retail', 'salesperson' => 'Stock test', 'order_date' => now()->toDateString(), 'lines' => [['product_id' => 3, 'quantity' => 2, 'free_quantity' => 1], ['product_id' => 2, 'quantity' => 0, 'free_quantity' => 3]]];
    }

    private function createOrder(): int
    {
        $this->post('/orders', $this->payload())->assertSessionHasNoErrors();

        return DB::table('orders')->max('id');
    }

    public function test_opening_receiving_and_adjustment_have_history_and_validate(): void
    {
        $this->stock(3, 'opening', 10);
        $this->stock(3, 'receive', 5);
        $this->stock(3, 'adjust', 12);
        $this->assertDatabaseHas('products', ['id' => 3, 'stock_on_hand' => 12, 'stock_minimum' => 2]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => 3, 'type' => 'adjust', 'quantity' => -3, 'balance' => 12]);
        $this->post('/stock/3', ['type' => 'opening', 'quantity' => 9, 'date' => now()->toDateString(), 'reason' => 'Again', 'minimum' => 0])->assertSessionHasErrors('stock');
        $this->assertSame(3, DB::table('stock_movements')->count());
    }

    public function test_reservation_gifts_dispatch_and_duplicate_dispatch(): void
    {
        $this->stock(3, 'opening', 10);
        $this->stock(2, 'opening', 10);
        $id = $this->createOrder();
        $this->assertDatabaseHas('products', ['id' => 3, 'stock_on_hand' => 10, 'stock_reserved' => 3]);
        $this->assertDatabaseHas('products', ['id' => 2, 'stock_reserved' => 3]);
        $this->assertDatabaseHas('order_lines', ['order_id' => $id, 'product_id' => 2, 'quantity' => 0, 'free' => 3, 'free_sachet' => 3, 'total' => 0]);
        $this->get('/orders/'.$id.'/document?type=goods')->assertOk()->assertSee('sachet')->assertSee('bottle');
        $this->post('/orders/'.$id.'/status', ['status' => 'packing'])->assertSessionHasNoErrors();
        $this->post('/orders/'.$id.'/status', ['status' => 'dispatched'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => 3, 'stock_on_hand' => 7, 'stock_reserved' => 0]);
        $this->post('/orders/'.$id.'/status', ['status' => 'dispatched'])->assertSessionHasErrors('status');
        $this->post('/orders/'.$id.'/status', ['status' => 'delivered'])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('stock_movements')->where('type', 'dispatch')->count());
    }

    public function test_insufficient_stock_rolls_back_entire_order_and_reservations(): void
    {
        $this->stock(2, 'opening', 10);
        $this->stock(3, 'opening', 2);
        $count = DB::table('orders')->count();
        $movements = DB::table('stock_movements')->count();
        $this->post('/orders', $this->payload())->assertSessionHasErrors('stock');
        $this->assertSame($count, DB::table('orders')->count());
        $this->assertSame($movements, DB::table('stock_movements')->count());
        $this->assertDatabaseHas('products', ['id' => 2, 'stock_reserved' => 0]);
    }

    public function test_cancel_releases_reservation_and_blocks_payment(): void
    {
        $this->stock(3, 'opening', 10);
        $this->stock(2, 'opening', 10);
        $id = $this->createOrder();
        $this->post('/stock/3', ['type' => 'adjust', 'quantity' => 2, 'date' => now()->toDateString(), 'reason' => 'Count', 'minimum' => 0])->assertSessionHasErrors('stock');
        $this->post('/orders/'.$id.'/status', ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => 3, 'stock_on_hand' => 10, 'stock_reserved' => 0]);
        $this->post('/orders/'.$id.'/payment', ['amount' => 1])->assertSessionHasErrors('amount');
        $this->post('/orders/'.$id.'/status', ['status' => 'cancelled'])->assertSessionHasErrors('status');
    }

    public function test_repeated_sku_is_checked_as_combined_quantity_and_legacy_is_unchanged(): void
    {
        $this->stock(3, 'opening', 5);
        $v = $this->payload();
        $v['lines'] = [$v['lines'][0], $v['lines'][0]];
        $this->post('/orders', $v)->assertSessionHasErrors('stock');
        foreach (['packing', 'dispatched', 'delivered'] as $status) {
            $this->post('/orders/1/status', compact('status'))->assertSessionHasNoErrors();
        }
        $this->assertSame(0, DB::table('stock_movements')->whereNotNull('order_id')->count());
    }

    public function test_paid_order_cannot_cancel_and_uninitialized_stock_cannot_sell(): void
    {
        $this->post('/orders', $this->payload())->assertSessionHasErrors('stock');
        $this->stock(3, 'opening', 10);
        $this->stock(2, 'opening', 10);
        $id = $this->createOrder();
        $this->post('/orders/'.$id.'/payment', ['amount' => 1])->assertSessionHasNoErrors();
        $this->post('/orders/'.$id.'/status', ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('products', ['id' => 3, 'stock_reserved' => 3]);
    }

    public function test_stock_entry_page_is_separate_and_preserves_validation_input(): void
    {
        $this->get('/stock')->assertOk()->assertSee('Update stock')->assertDontSee('id="stock-form"', false);
        $this->get('/stock/update')->assertOk()->assertSee('id="stock-form"', false)->assertSee('Back to Stock');
        $this->get('/stock?tab=receive&stock_product_id=3')->assertRedirect('/stock/update?stock_product_id=3');
        $this->post('/stock/3', ['type' => 'opening', 'quantity' => 10, 'date' => now()->toDateString(), 'reason' => 'Opening count', 'minimum' => 2, 'stock_product_id' => 3])
            ->assertSessionHasNoErrors()->assertRedirect('/stock/update?stock_product_id=3');
        $this->post('/stock/3', ['type' => 'adjust', 'quantity' => 5, 'date' => now()->toDateString(), 'reason' => 'Count correction', 'minimum' => 2, 'stock_product_id' => 3])
            ->assertSessionHasNoErrors()->assertRedirect('/stock/update?stock_product_id=3');
        $this->assertDatabaseHas('products', ['id' => 3, 'stock_on_hand' => 5]);
        $this->from('/stock/update?stock_product_id=3')->post('/stock/3', ['type' => 'receive', 'quantity' => 0, 'date' => now()->toDateString(), 'reason' => 'Retain this reason', 'minimum' => 2, 'stock_product_id' => 3])
            ->assertRedirect('/stock/update?stock_product_id=3')->assertSessionHasErrors('quantity')
            ->assertSessionHas('_old_input.stock_product_id', 3)->assertSessionHas('_old_input.reason', 'Retain this reason');
    }
}
