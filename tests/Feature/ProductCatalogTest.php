<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function product(): array
    {
        return ['sku' => 'new001', 'name' => 'สินค้าใหม่ทดสอบ', 'unit' => 'Bottle', 'retail' => '125.50', 'hospital' => '100', 'pharmacy' => '105.75'];
    }

    public function test_product_creation_sets_prices_and_requires_stock_initialization_before_orders(): void
    {
        $this->post('/products', $this->product())->assertSessionHasNoErrors()->assertRedirect('/products?q=NEW001');
        $p = DB::table('products')->where('sku', 'NEW001')->first();
        $this->assertNotNull($p);
        $this->assertDatabaseHas('products', ['id' => $p->id, 'unit' => 'bottle', 'retail' => 12550, 'hospital' => 10000, 'pharmacy' => 10575, 'stock_initialized' => false, 'stock_on_hand' => 0, 'stock_reserved' => 0]);
        $this->get('/products?q=NEW001')->assertOk()->assertSee('สินค้าใหม่ทดสอบ')->assertSee('Set opening stock');
        $this->get('/stock?balance_q=NEW001')->assertOk()->assertViewHas('balances', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $p->id);
        $this->get('/stock/update?stock_product_id='.$p->id)->assertOk()->assertSee('NEW001');
        $order = ['customer_id' => 1, 'channel' => 'retail', 'order_date' => now()->toDateString(), 'salesperson' => 'Test', 'lines' => [['product_id' => $p->id, 'quantity' => 1, 'free_quantity' => 0]]];
        $this->post('/orders', $order)->assertSessionHasErrors('stock');
        $this->post('/stock/'.$p->id, ['type' => 'opening', 'quantity' => 10, 'date' => now()->toDateString(), 'reason' => 'Count', 'minimum' => 2])->assertSessionHasNoErrors();
        $this->post('/orders', $order)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $p->id, 'stock_reserved' => 1]);
    }

    public function test_product_validation_rejects_duplicate_sku_and_invalid_prices_without_creating_rows(): void
    {
        $count = DB::table('products')->count();
        $v = $this->product();
        $v['sku'] = 'pro001';
        $this->post('/products', $v)->assertSessionHasErrors('sku');
        $v = $this->product();
        $v['retail'] = '1.234';
        $this->post('/products', $v)->assertSessionHasErrors('retail');
        $v = $this->product();
        $v['hospital'] = $v['retail'];
        $this->post('/products', $v)->assertSessionHasErrors('hospital');
        $this->assertSame($count, DB::table('products')->count());
        $v = $this->product();
        unset($v['hospital'],$v['pharmacy']);
        $this->post('/products', $v)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['sku' => 'NEW001', 'hospital' => null, 'pharmacy' => null]);
        $this->post('/products', $v)->assertSessionHasErrors('sku');
    }

    public function test_catalog_paginates_searches_and_price_edit_screen_preserves_order_prices(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            DB::table('products')->insert(['sku' => 'PAGE'.$i, 'name' => 'Catalog example '.$i, 'unit' => 'piece', 'retail' => 100]);
        }
        $this->get('/products')->assertOk()->assertViewHas('products', fn ($rows) => $rows->total() === 22 && $rows->count() === 10)->assertDontSee('form="price-', false);
        $this->get('/products?q=Catalog&page=2')->assertOk()->assertViewHas('products', fn ($rows) => $rows->total() === 15 && $rows->count() === 5);
        $this->get('/products/create')->assertOk()->assertSee('Add product');
        $this->get('/products/3/pricing')->assertOk()->assertSee('Edit prices');
        $old = DB::table('order_lines')->where('product_id', 3)->first()->price;
        $this->from('/products/3/pricing')->post('/products/3', ['retail' => 190, 'hospital' => 120, 'pharmacy' => 130])->assertSessionHasNoErrors()->assertRedirect('/products/3/pricing');
        $this->assertDatabaseHas('products', ['id' => 3, 'retail' => 19000]);
        $this->assertSame($old, DB::table('order_lines')->where('product_id', 3)->first()->price);
        $this->get('/products/99999/pricing')->assertNotFound();
    }
}
