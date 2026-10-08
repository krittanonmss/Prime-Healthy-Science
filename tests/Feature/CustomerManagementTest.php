<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function details(): array
    {
        return ['name' => 'ร้านยาลูกค้าใหม่', 'phone' => '0811112222', 'address' => 'ที่อยู่ใหม่ กรุงเทพมหานคร', 'channel' => 'pharmacy', 'tax_id' => '0123456789012'];
    }

    public function test_creation_and_edit_forms_are_separate_from_paginated_directory(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            DB::table('customers')->insert(['name' => 'Directory example '.$i, 'phone' => '000'.str_pad((string) $i, 7, '0', STR_PAD_LEFT), 'address' => 'Test address', 'channel' => 'retail']);
        }
        $this->get('/customers')->assertOk()->assertViewHas('customers', fn ($rows) => $rows->total() === 23 && $rows->count() === 10)->assertDontSee('name="address"', false);
        $this->get('/customers?page=3')->assertOk()->assertViewHas('customers', fn ($rows) => $rows->count() === 3);
        $this->get('/customers?q=example&channel=retail')->assertOk()->assertViewHas('customers', fn ($rows) => $rows->total() === 20)
            ->assertSee('q=example&amp;channel=retail&amp;page=2', false);
        $this->get('/customers?q=0000000001')->assertOk()->assertViewHas('customers', fn ($rows) => $rows->total() === 1);
        $this->get('/customers/create')->assertOk()->assertSee('Add customer')->assertSee('name="address"', false);
        $this->get('/customers/1/edit')->assertOk()->assertSee('Siam Wellness Clinic')->assertSee('Edit customer');
        $this->get('/customers/9999/edit')->assertNotFound();
        $this->post('/customers', $this->details())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', $this->details());
    }

    public function test_updates_preserve_historical_orders_and_new_orders_use_new_details(): void
    {
        $before = DB::table('orders')->find(1)->customer_snapshot;
        $this->post('/customers/1', $this->details())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['id' => 1] + $this->details());
        $this->assertSame($before, DB::table('orders')->find(1)->customer_snapshot);
        $this->get('/orders/1/document?type=receipt')->assertOk()->assertSee('Siam Wellness Clinic')->assertDontSee('ร้านยาลูกค้าใหม่');
        $this->get('/customers?q=0123456789012')->assertOk()->assertViewHas('customers', fn ($rows) => $rows->count() === 1 && $rows->first()->id === 1);
        DB::table('products')->where('id', 3)->update(['stock_initialized' => true, 'stock_on_hand' => 10]);
        $this->post('/orders', ['customer_id' => 1, 'channel' => 'pharmacy', 'order_date' => now()->toDateString(), 'salesperson' => 'Test', 'lines' => [['product_id' => 3, 'quantity' => 1, 'free_quantity' => 0]]])->assertSessionHasNoErrors();
        $snapshot = json_decode(DB::table('orders')->orderByDesc('id')->first()->customer_snapshot, true);
        $this->assertSame('ร้านยาลูกค้าใหม่', $snapshot['name']);
        $this->assertSame('0123456789012', $snapshot['tax_id']);
        $this->assertSame('pharmacy', $snapshot['channel']);
        $clear = $this->details();
        $clear['tax_id'] = '';
        $this->post('/customers/1', $clear)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['id' => 1, 'tax_id' => null]);
    }

    public function test_invalid_updates_do_not_change_customer_and_legacy_orders_get_snapshots(): void
    {
        $before = (array) DB::table('customers')->find(1);
        $invalid = $this->details();
        $invalid['tax_id'] = '123';
        $this->from('/customers/1/edit')->post('/customers/1', $invalid)->assertSessionHasErrors('tax_id')->assertRedirect('/customers/1/edit');
        $this->assertSame($before, (array) DB::table('customers')->find(1));
        DB::table('orders')->where('id', 1)->update(['customer_snapshot' => null]);
        $this->post('/customers/1', $this->details())->assertSessionHasNoErrors();
        $this->assertSame($before, json_decode(DB::table('orders')->find(1)->customer_snapshot, true));
        $this->post('/customers/9999',$this->details())->assertNotFound();
    }
}
